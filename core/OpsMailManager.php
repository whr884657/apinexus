<?php
/**
 * 文件：core/OpsMailManager.php
 * 作用：运营中心邮件群发（复用激励筛选结果；分片发信；写 maillog；队列签名）
 */

class OpsMailManager
{
    const TICK_SIZE = 20;
    /** 两次 tick 最短间隔（毫秒），防打爆 SMTP */
    const TICK_MIN_MS = 250;
    const REDIS_TTL = 900;
    /** 库内「发送中」超时收尸（秒），与 Redis 队列 TTL 对齐 */
    const STALE_SECONDS = 900;
    /** Redis 键前缀；实际键追加 :{adminId}，避免多任务互踩 */
    const REDIS_IDS = 'ops:mail:ids';
    const REDIS_META = 'ops:mail:meta';
    const REDIS_LOCK = 'ops:mail:lock';
    /** 同管理员并行 tick 互斥（防双页签并发发信/丢计数） */
    const REDIS_TICK = 'ops:mail:tick';
    /** 已弹出未发完的在途 uid（防 PHP 强杀丢件） */
    const REDIS_INFLIGHT = 'ops:mail:inflight';
    const MAX_SUBJECT_LEN = 500;
    const MAX_BODY_BYTES = 200000;
    const FILTER_SCOPE = 'mail';
    /** 单次 tick 时间预算（秒），用满即停并把未处理 uid 还回队列 */
    const TICK_TIME_BUDGET = 20;
    /** tick 互斥锁 TTL；强杀后最多卡住约该秒数（宜略大于时间预算） */
    const TICK_LOCK_TTL = 45;
    /** 进度先写 Redis，至少间隔这么多秒再刷 maillog（秒） */
    const DB_FLUSH_SEC = 1;
    /** 队列条目签名最大年龄（秒）；防陈旧重放，须覆盖慢速大任务 */
    const SIGNED_ID_MAX_AGE = 86400;
    /** 失败抽样上限（与 MailLogManager 一致） */
    const FAIL_SAMPLE_MAX = 200;

    /**
     * @param int $adminId
     * @return string
     */
    private static function redisIdsKey($adminId)
    {
        return self::REDIS_IDS . ':' . max(0, (int) $adminId);
    }

    /**
     * @param int $adminId
     * @return string
     */
    private static function redisMetaKey($adminId)
    {
        return self::REDIS_META . ':' . max(0, (int) $adminId);
    }

    /**
     * @param int $adminId
     * @return string
     */
    private static function redisLockKey($adminId)
    {
        return self::REDIS_LOCK . ':' . max(0, (int) $adminId);
    }

    /**
     * @param int $adminId
     * @return string
     */
    private static function redisTickKey($adminId)
    {
        return self::REDIS_TICK . ':' . max(0, (int) $adminId);
    }

    /**
     * @param int $adminId
     * @return string
     */
    private static function redisInflightKey($adminId)
    {
        return self::REDIS_INFLIGHT . ':' . max(0, (int) $adminId);
    }

    /**
     * 收尸用：该管理员 Redis 群发任务是否仍指向指定批次
     *
     * @param int $adminId
     * @param int $logId
     * @return bool
     */
    public static function isSendActiveForLog($adminId, $logId)
    {
        $adminId = (int) $adminId;
        $logId = (int) $logId;
        if ($adminId <= 0 || $logId <= 0) {
            return false;
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return false;
        }
        $meta = self::redisGetMeta($adminId);
        if (!is_array($meta) || !isset($meta['token']) || trim((string) $meta['token']) === '') {
            return false;
        }
        return (int) (isset($meta['log_id']) ? $meta['log_id'] : 0) === $logId;
    }

    /**
     * @param int    $adminId
     * @param string $filterToken
     * @param string $tplMode saved|temp
     * @param int    $tplId
     * @param string $tempSubject
     * @param string $tempBody
     * @param string $filterSum 前端摘要（可空；服务端会尽量重算）
     * @param string $confirmText
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function startSend(
        $adminId,
        $filterToken,
        $tplMode,
        $tplId,
        $tempSubject,
        $tempBody,
        $filterSum,
        $confirmText = ''
    ) {
        $adminId = (int) $adminId;
        if ($adminId <= 0 || !class_exists('Auth') || !Auth::check() || (int) Auth::id() !== $adminId) {
            return array('ok' => false, 'msg' => '请先登录管理后台');
        }
        if (!Config::isMailEnabled()) {
            return array('ok' => false, 'msg' => '邮箱发信未就绪，请先配置');
        }
        if (!class_exists('RedisCache') || !RedisCache::enabled() || !class_exists('RedisService')) {
            return array('ok' => false, 'msg' => '邮件群发需要 Redis，请先启用');
        }
        if (!class_exists('MailLogManager') || !MailLogManager::tableReady()) {
            return array('ok' => false, 'msg' => '发送记录表尚未就绪，请先系统升级');
        }
        if (!class_exists('IncentiveManager') || !method_exists('IncentiveManager', 'snapshotFilterIds')) {
            return array('ok' => false, 'msg' => '筛选能力未就绪');
        }
        if (self::mailSigningKey() === '') {
            return array('ok' => false, 'msg' => '群发签名密钥未就绪，请稍后重试或检查系统配置');
        }

        // 启动前收尸超时卡住的「发送中」批次
        if (method_exists('MailLogManager', 'abortStaleRunning')) {
            MailLogManager::abortStaleRunning(self::STALE_SECONDS);
        }

        $snap = IncentiveManager::snapshotFilterIds($adminId, $filterToken, self::FILTER_SCOPE);
        if (empty($snap['ok'])) {
            return array('ok' => false, 'msg' => isset($snap['msg']) ? $snap['msg'] : '请先完成筛选');
        }
        $uids = isset($snap['uids']) && is_array($snap['uids']) ? $snap['uids'] : array();
        $count = count($uids);
        if ($count < 1) {
            return array('ok' => false, 'msg' => '没有命中任何用户');
        }
        if ($count > 2000) {
            return array('ok' => false, 'msg' => '命中超过一次上限 2000，请收窄条件后重试');
        }

        $tplMode = $tplMode === 'temp' ? 'temp' : 'saved';
        $tplId = (int) $tplId;
        $subject = '';
        $body = '';
        $tplName = '';
        if ($tplMode === 'temp') {
            $subject = trim((string) $tempSubject);
            $body = trim((string) $tempBody);
            $tplName = '临时撰写';
            $tplId = 0;
            if ($subject === '' || $body === '') {
                return array('ok' => false, 'msg' => '请填写发信标题和正文');
            }
        } else {
            if ($tplId <= 0 || !class_exists('ContentManager')) {
                return array('ok' => false, 'msg' => '请先选择邮件模板，或使用临时撰写');
            }
            $row = ContentManager::findById($tplId);
            if (!$row || ContentManager::normalizeKind($row['kind']) !== ContentManager::KIND_MAILTPL) {
                return array('ok' => false, 'msg' => '该模板不存在，请重新选择');
            }
            $fmt = ContentManager::formatRow($row);
            $bind = isset($fmt['bindpage']) ? (int) $fmt['bindpage'] : 0;
            $status = isset($fmt['status']) ? (int) $fmt['status'] : 0;
            if ($status !== ContentManager::STATUS_PUBLISHED) {
                return array('ok' => false, 'msg' => '该模板未发布或已停用，请更换');
            }
            if (class_exists('MailChannels') && MailChannels::isSystemChannel($bind)) {
                return array('ok' => false, 'msg' => '系统通知模板不能用于群发，请换自定义模板');
            }
            $subject = isset($fmt['summary']) ? trim((string) $fmt['summary']) : '';
            $body = isset($fmt['body']) ? trim((string) $fmt['body']) : '';
            $tplName = isset($fmt['title']) ? trim((string) $fmt['title']) : '';
            if ($subject === '' || $body === '') {
                return array('ok' => false, 'msg' => '模板标题或正文为空，请先完善模板');
            }
        }

        $lenErr = self::assertContentLimits($subject, $body);
        if ($lenErr !== null) {
            return array('ok' => false, 'msg' => $lenErr);
        }
        if (class_exists('MailNotify')) {
            $body = MailNotify::sanitizeMailHtml($body);
        }

        if ($count > 200 && trim((string) $confirmText) !== '确认发送') {
            return array(
                'ok'   => false,
                'msg'  => '高危操作：将向 ' . $count . ' 人发送，请在确认框中确认后再发送',
                'data' => array('need_confirm' => 1, 'matched_count' => $count),
            );
        }

        $serverSum = '';
        if (method_exists('IncentiveManager', 'filterSummaryForToken')) {
            $serverSum = IncentiveManager::filterSummaryForToken($adminId, $filterToken, self::FILTER_SCOPE);
        }
        $filterSumFinal = $serverSum !== ''
            ? $serverSum
            : self::sanitizeFilterSum($filterSum);

        if (!self::acquireLock($adminId, $count, '')) {
            return array('ok' => false, 'msg' => '已有群发任务进行中，请稍后再试');
        }

        $batch = MailLogManager::createBatch(array(
            'adminid'   => $adminId,
            'tplid'     => $tplId,
            'tplname'   => $tplName,
            'subject'   => $subject,
            'filtersum' => $filterSumFinal,
            'uids'      => $uids,
        ));
        if (!is_array($batch)) {
            self::releaseLock($adminId);
            return array('ok' => false, 'msg' => is_string($batch) ? $batch : '创建发送记录失败');
        }

        $token = bin2hex(function_exists('random_bytes') ? random_bytes(8) : openssl_random_pseudo_bytes(8));
        $meta = array(
            'token'          => $token,
            'admin_id'       => $adminId,
            'log_id'         => (int) $batch['id'],
            'subject'        => $subject,
            'body'           => $body,
            'total'          => $count,
            'done'           => 0,
            'ok'             => 0,
            'fail'           => 0,
            'flushed_ok'     => 0,
            'flushed_fail'   => 0,
            'fail_items'     => array(),
            'last_db_flush'  => 0,
            'started'        => time(),
            'last_tick'      => 0,
        );
        $meta['sig'] = self::signMailMeta($meta);
        if ($meta['sig'] === '') {
            MailLogManager::finish((int) $batch['id'], MailLogManager::STATUS_ABORT);
            self::releaseLock($adminId);
            return array('ok' => false, 'msg' => '群发签名失败，请重试');
        }

        try {
            if (!self::redisPutIds($adminId, $uids, $token) || !self::redisPutMeta($adminId, $meta)) {
                MailLogManager::finish((int) $batch['id'], MailLogManager::STATUS_ABORT);
                self::clearRedis($adminId);
                self::releaseLock($adminId);
                return array('ok' => false, 'msg' => '准备发送队列失败，请重试');
            }
            self::touchLock($adminId, $token, $count);
        } catch (Exception $e) {
            MailLogManager::finish((int) $batch['id'], MailLogManager::STATUS_ABORT);
            self::clearRedis($adminId);
            self::releaseLock($adminId);
            return array('ok' => false, 'msg' => '准备发送队列失败，请重试');
        }

        // 一次性消费筛选结果，禁止同 token 重复群发
        if (method_exists('IncentiveManager', 'consumeFilterSnapshot')) {
            IncentiveManager::consumeFilterSnapshot($adminId, $filterToken, self::FILTER_SCOPE);
        }

        return array(
            'ok'   => true,
            'msg'  => '开始发送',
            'data' => array(
                'token'         => $token,
                'log_id'        => (int) $batch['id'],
                'matched_count' => $count,
                'done'          => 0,
                'total'         => $count,
                'finished'      => 0,
                'tick_min_ms'   => self::TICK_MIN_MS,
            ),
        );
    }

    /**
     * @param int    $adminId
     * @param string $token
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function tickSend($adminId, $token)
    {
        $adminId = (int) $adminId;
        $token = trim((string) $token);
        if ($adminId <= 0 || $token === '' || !class_exists('Auth') || !Auth::check() || (int) Auth::id() !== $adminId) {
            return array('ok' => false, 'msg' => '请先登录管理后台');
        }
        $meta = self::redisGetMeta($adminId);
        if (!is_array($meta) || !isset($meta['token']) || $meta['token'] !== $token) {
            return array('ok' => false, 'msg' => '发送任务已失效，请重新筛选发送');
        }
        if ((int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0) !== $adminId) {
            return array('ok' => false, 'msg' => '无权推进该发送任务');
        }
        if (!self::verifyMailMeta($meta)) {
            $logIdBad = (int) (isset($meta['log_id']) ? $meta['log_id'] : 0);
            if ($logIdBad > 0) {
                self::flushProgressToDb($logIdBad, $meta, true);
                MailLogManager::finish($logIdBad, MailLogManager::STATUS_ABORT);
            }
            self::clearRedis($adminId);
            self::releaseLock($adminId);
            return array('ok' => false, 'msg' => '发送任务校验失败（队列可能被篡改），已中断');
        }

        $progressPayload = function ($metaIn, $finished, $extra) {
            return array_merge(array(
                'token'       => isset($metaIn['token']) ? (string) $metaIn['token'] : '',
                'log_id'      => (int) (isset($metaIn['log_id']) ? $metaIn['log_id'] : 0),
                'done'        => (int) (isset($metaIn['done']) ? $metaIn['done'] : 0),
                'total'       => (int) (isset($metaIn['total']) ? $metaIn['total'] : 0),
                'oknum'       => (int) (isset($metaIn['ok']) ? $metaIn['ok'] : 0),
                'failnum'     => (int) (isset($metaIn['fail']) ? $metaIn['fail'] : 0),
                'finished'    => $finished ? 1 : 0,
                'tick_min_ms' => self::TICK_MIN_MS,
            ), $extra);
        };

        $nowMs = (int) round(microtime(true) * 1000);
        $lastTick = (int) (isset($meta['last_tick']) ? $meta['last_tick'] : 0);
        if ($lastTick > 0 && ($nowMs - $lastTick) < self::TICK_MIN_MS) {
            return array(
                'ok'   => true,
                'msg'  => 'ok',
                'data' => $progressPayload($meta, false, array('throttled' => 1)),
            );
        }

        // 并行 tick 互斥：第二个请求直接节流，不 pop 队列
        if (!self::acquireTickLock($adminId, $token)) {
            return array(
                'ok'   => true,
                'msg'  => 'ok',
                'data' => $progressPayload($meta, false, array('throttled' => 1)),
            );
        }

        try {
            $logId = (int) (isset($meta['log_id']) ? $meta['log_id'] : 0);
            $subjectTpl = isset($meta['subject']) ? (string) $meta['subject'] : '';
            $bodyTpl = isset($meta['body']) ? (string) $meta['body'] : '';
            $total = (int) (isset($meta['total']) ? $meta['total'] : 0);
            $done = (int) (isset($meta['done']) ? $meta['done'] : 0);
            $okNum = (int) (isset($meta['ok']) ? $meta['ok'] : 0);
            $failNum = (int) (isset($meta['fail']) ? $meta['fail'] : 0);

            self::touchLock($adminId, $token, $total);
            if ($logId > 0 && method_exists('MailLogManager', 'touchActivity')) {
                MailLogManager::touchActivity($logId);
            }

            // 强杀残留：把在途 uid 还回队列再 pop
            $recovered = self::redisTakeInflight($adminId, $token);
            if ($recovered === null) {
                self::flushProgressToDb($logId, $meta, true);
                MailLogManager::finish($logId, MailLogManager::STATUS_ABORT);
                self::clearRedis($adminId);
                self::releaseLock($adminId);
                return array('ok' => false, 'msg' => '发送队列异常，已中断');
            }
            if ($recovered !== array()) {
                self::redisUnshiftIds($adminId, $recovered, $token);
            }

            $batchUids = self::redisPopIds($adminId, self::TICK_SIZE, $token);
            if ($batchUids === null) {
                self::flushProgressToDb($logId, $meta, true);
                MailLogManager::finish($logId, MailLogManager::STATUS_ABORT);
                self::clearRedis($adminId);
                self::releaseLock($adminId);
                return array('ok' => false, 'msg' => '发送队列异常，已中断');
            }

            // 队列空：须 (ok+fail)≥total 才算完成；否则视为 TTL/丢件导致的假完成 → 中断
            if ($batchUids === array()) {
                $left = self::redisIdsLen($adminId);
                if ($left <= 0) {
                    return self::finalizeWhenQueueEmpty(
                        $adminId,
                        $token,
                        $logId,
                        $meta,
                        $done,
                        $okNum,
                        $failNum,
                        $total,
                        $progressPayload
                    );
                }
                $meta['last_tick'] = $nowMs;
                self::redisPutMeta($adminId, $meta);
                return array(
                    'ok'   => true,
                    'msg'  => 'ok',
                    'data' => $progressPayload($meta, false, array('throttled' => 1)),
                );
            }

            if (!self::redisPutInflight($adminId, $batchUids, $token)) {
                // 还回队列；失败则保留在途（禁止 clearRedis / 禁止在 unshift 失败后清 inflight）
                if (!self::redisUnshiftIds($adminId, $batchUids, $token)) {
                    self::redisPutInflight($adminId, $batchUids, $token);
                } else {
                    self::redisClearInflight($adminId);
                }
                self::redisPutMeta($adminId, $meta);
                return array('ok' => false, 'msg' => '发送队列暂存失败，请重试');
            }

            $okDelta = 0;
            $failDelta = 0;
            $failItems = array();
            $deadline = microtime(true) + self::TICK_TIME_BUDGET;
            $nBatch = count($batchUids);
            for ($i = 0; $i < $nBatch; $i++) {
                if ($i > 0 && microtime(true) >= $deadline) {
                    // 未处理部分还回队列；unshift 失败则保留 inflight，禁止双清丢件
                    $rest = array_slice($batchUids, $i);
                    if ($rest !== array() && !self::redisUnshiftIds($adminId, $rest, $token)) {
                        self::redisPutInflight($adminId, $rest, $token);
                    } else {
                        self::redisClearInflight($adminId);
                    }
                    break;
                }
                // 发送前收窄在途：含当前，便于强杀后至少一次重试
                self::redisPutInflight($adminId, array_slice($batchUids, $i), $token);
                $uid = (int) $batchUids[$i];
                $done++;
                $user = self::loadUser($uid);
                $email = is_array($user) ? trim((string) $user['email']) : '';
                // 群发走严格校验；注册/验证码等走 Mailer 默认宽松校验
                $emailOk = class_exists('Mailer') && method_exists('Mailer', 'isValidRecipient')
                    ? Mailer::isValidRecipient($email, true)
                    : ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL));
                if (!$emailOk) {
                    $failDelta++;
                    $failNum++;
                    $failItems[] = array('uid' => $uid, 'err' => '用户邮箱无效');
                    continue;
                }
                $vars = class_exists('MailNotify')
                    ? MailNotify::buildCommonVars($uid, $user)
                    : array();
                $subject = class_exists('MailNotify')
                    ? MailNotify::applyPlaceholders($subjectTpl, $vars, false)
                    : $subjectTpl;
                $body = class_exists('MailNotify')
                    ? MailNotify::applyPlaceholders($bodyTpl, $vars, true)
                    : $bodyTpl;
                // 群发强制轮询启用池（mode=0），避免后台「主备」一直钉死第一号
                $sendRes = class_exists('MailNotify')
                    ? MailNotify::sendOne($email, $subject, $body, array('mode' => 0))
                    : null;
                if (is_array($sendRes) && !empty($sendRes['ok'])) {
                    $okDelta++;
                    $okNum++;
                } else {
                    $failDelta++;
                    $failNum++;
                    $errMsg = is_array($sendRes) && isset($sendRes['error'])
                        ? (string) $sendRes['error']
                        : '发送失败';
                    if (function_exists('mb_substr')) {
                        $errMsg = mb_substr($errMsg, 0, 120, 'UTF-8');
                    } else {
                        $errMsg = substr($errMsg, 0, 120);
                    }
                    $failItems[] = array('uid' => $uid, 'err' => $errMsg);
                }
            }
            self::redisClearInflight($adminId);

            $meta['done'] = $done;
            $meta['ok'] = $okNum;
            $meta['fail'] = $failNum;
            if ($okDelta > 0 || $failDelta > 0) {
                self::bufferTickProgress($meta, $okDelta, $failDelta, $failItems);
            }
            $meta['last_tick'] = (int) round(microtime(true) * 1000);
            $left = self::redisIdsLen($adminId);
            $covered = $okNum + $failNum;
            $finished = ($left <= 0 && $covered >= $total && $total > 0);

            if ($left <= 0 && !$finished) {
                return self::finalizeWhenQueueEmpty(
                    $adminId,
                    $token,
                    $logId,
                    $meta,
                    $done,
                    $okNum,
                    $failNum,
                    $total,
                    $progressPayload
                );
            }

            // 进行中：≥1s 才刷计数；结束：强制刷计数 + failjson 一次落库
            $prog = self::flushProgressToDb($logId, $meta, $finished);
            if ($prog !== true) {
                self::clearRedis($adminId);
                self::releaseLock($adminId);
                return array(
                    'ok'   => false,
                    'msg'  => is_string($prog) ? $prog : '批次已结束，发送已停止',
                    'data' => $progressPayload(array(
                        'token' => $token,
                        'log_id' => $logId,
                        'done' => $done,
                        'total' => $total,
                        'ok' => $okNum,
                        'fail' => $failNum,
                    ), true, array('aborted' => 1)),
                );
            }

            if ($finished) {
                MailLogManager::finish($logId, MailLogManager::STATUS_DONE);
                self::clearRedis($adminId);
                self::releaseLock($adminId);
            } else {
                self::redisPutMeta($adminId, $meta);
                self::touchLock($adminId, $token, $total);
            }

            return array(
                'ok'   => true,
                'msg'  => $finished ? '发送完成' : 'ok',
                'data' => $progressPayload($meta, $finished, array()),
            );
        } finally {
            self::releaseTickLock($adminId, $token);
        }
    }

    /**
     * 管理员主动中止当前群发（清 Redis 队列 + 批次标中断）
     *
     * @param int    $adminId
     * @param string $token
     * @return array{ok:bool,msg?:string,data?:array}
     */
    public static function cancelSend($adminId, $token)
    {
        $adminId = (int) $adminId;
        $token = trim((string) $token);
        if ($adminId <= 0 || $token === '' || !class_exists('Auth') || !Auth::check() || (int) Auth::id() !== $adminId) {
            return array('ok' => false, 'msg' => '请先登录管理后台');
        }
        $meta = self::redisGetMeta($adminId);
        if (!is_array($meta) || !isset($meta['token']) || $meta['token'] !== $token) {
            // 队列已空：尽量按 token 无法定位时仍尝试收尸本管理员超时批次
            if (method_exists('MailLogManager', 'abortStaleRunning')) {
                MailLogManager::abortStaleRunning(self::STALE_SECONDS);
            }
            return array('ok' => false, 'msg' => '发送任务已结束或不存在');
        }
        if ((int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0) !== $adminId) {
            return array('ok' => false, 'msg' => '无权中止该发送任务');
        }
        $logId = (int) (isset($meta['log_id']) ? $meta['log_id'] : 0);
        $done = (int) (isset($meta['done']) ? $meta['done'] : 0);
        $total = (int) (isset($meta['total']) ? $meta['total'] : 0);
        $okNum = (int) (isset($meta['ok']) ? $meta['ok'] : 0);
        $failNum = (int) (isset($meta['fail']) ? $meta['fail'] : 0);
        if ($logId > 0) {
            // 中止前强制把 Redis 缓冲刷进库，避免计数/失败抽样丢失
            self::flushProgressToDb($logId, $meta, true);
            MailLogManager::finish($logId, MailLogManager::STATUS_ABORT);
        }
        self::clearRedis($adminId);
        self::releaseLock($adminId);
        return array(
            'ok'   => true,
            'msg'  => '已中止发送',
            'data' => array(
                'token'    => $token,
                'log_id'   => $logId,
                'done'     => $done,
                'total'    => $total,
                'oknum'    => $okNum,
                'failnum'  => $failNum,
                'finished' => 1,
                'aborted'  => 1,
            ),
        );
    }

    /**
     * @param string $subject
     * @param string $body
     * @return string|null
     */
    private static function assertContentLimits($subject, $body)
    {
        if (function_exists('mb_strlen')) {
            if (mb_strlen($subject, 'UTF-8') > self::MAX_SUBJECT_LEN) {
                return '发信标题不超过 ' . self::MAX_SUBJECT_LEN . ' 字';
            }
        } elseif (strlen($subject) > self::MAX_SUBJECT_LEN) {
            return '发信标题过长';
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return '正文过长（请控制在 ' . self::MAX_BODY_BYTES . ' 字节以内）';
        }
        return null;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function sanitizeFilterSum($raw)
    {
        $s = trim(strip_tags((string) $raw));
        if (function_exists('mb_substr') && mb_strlen($s, 'UTF-8') > 500) {
            $s = mb_substr($s, 0, 500, 'UTF-8');
        } elseif (strlen($s) > 500) {
            $s = substr($s, 0, 500);
        }
        return $s;
    }

    /**
     * @param int $uid
     * @return array|null
     */
    private static function loadUser($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `role`, `status`
                 FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($uid));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return string
     */
    /**
     * 群发 HMAC 密钥；禁止可预测回退。空串=不可用。
     *
     * @return string
     */
    private static function mailSigningKey()
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $secret = '';
        try {
            if (class_exists('Config') && class_exists('InstallChecker') && InstallChecker::isInstalled()) {
                $secret = (string) Config::get('ops_mail_secret', '');
                if (strlen($secret) < 32) {
                    $secret = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
                    Config::set('ops_mail_secret', $secret);
                }
            }
        } catch (Exception $e) {
            $secret = '';
        }
        if ($secret === '' || strlen($secret) < 32) {
            $key = '';
            return $key;
        }
        $key = hash('sha256', 'apinexus-ops-mail-v1|' . $secret);
        return $key;
    }

    /**
     * 签名不可变字段；done/ok/fail/last_tick 不参与
     *
     * @param array $meta
     * @return string
     */
    private static function signMailMeta(array $meta)
    {
        $k = self::mailSigningKey();
        if ($k === '') {
            return '';
        }
        $payload = implode('|', array(
            (string) (isset($meta['token']) ? $meta['token'] : ''),
            (string) (int) (isset($meta['admin_id']) ? $meta['admin_id'] : 0),
            (string) (int) (isset($meta['log_id']) ? $meta['log_id'] : 0),
            hash('sha256', (string) (isset($meta['subject']) ? $meta['subject'] : '')),
            hash('sha256', (string) (isset($meta['body']) ? $meta['body'] : '')),
            (string) (int) (isset($meta['total']) ? $meta['total'] : 0),
            (string) (int) (isset($meta['started']) ? $meta['started'] : 0),
        ));
        return hash_hmac('sha256', $payload, $k);
    }

    /**
     * @param array $meta
     * @return bool
     */
    private static function verifyMailMeta(array $meta)
    {
        $sig = isset($meta['sig']) ? (string) $meta['sig'] : '';
        if ($sig === '' || strlen($sig) < 32) {
            return false;
        }
        $expect = self::signMailMeta($meta);
        if ($expect === '') {
            return false;
        }
        return hash_equals($expect, $sig);
    }

    /**
     * @param int    $uid
     * @param string $token
     * @return string
     */
    private static function formatSignedId($uid, $token)
    {
        $uid = (int) $uid;
        $k = self::mailSigningKey();
        if ($k === '' || $uid <= 0) {
            return '';
        }
        $ts = time();
        $mac = hash_hmac('sha256', (string) $uid . '|' . (string) $token . '|' . (string) $ts, $k);
        return $uid . ':' . $ts . ':' . $mac;
    }

    /**
     * @param mixed  $entry
     * @param string $token
     * @return int
     */
    private static function parseSignedId($entry, $token)
    {
        $entry = trim((string) $entry);
        $token = trim((string) $token);
        $k = self::mailSigningKey();
        if ($entry === '' || $token === '' || $k === '' || strpos($entry, ':') === false) {
            return 0;
        }
        $parts = explode(':', $entry, 3);
        // 仅接受 uid:timestamp:hmac（拒绝旧版无时间戳永久有效格式）
        if (count($parts) !== 3) {
            return 0;
        }
        $uid = (int) $parts[0];
        $ts = (int) $parts[1];
        $mac = (string) $parts[2];
        if ($uid <= 0 || $ts <= 0 || $mac === '') {
            return 0;
        }
        if (abs(time() - $ts) > self::SIGNED_ID_MAX_AGE) {
            return 0;
        }
        $expect = hash_hmac('sha256', (string) $uid . '|' . $token . '|' . (string) $ts, $k);
        return hash_equals($expect, $mac) ? $uid : 0;
    }

    /**
     * 把本 tick 失败抽样写入 Redis meta（计数由调用方已写入；不直接打库）
     *
     * @param array $meta
     * @param int   $okDelta
     * @param int   $failDelta
     * @param array $failItems
     * @return void
     */
    private static function bufferTickProgress(array &$meta, $okDelta, $failDelta, array $failItems)
    {
        if (!isset($meta['fail_items']) || !is_array($meta['fail_items'])) {
            $meta['fail_items'] = array();
        }
        foreach ($failItems as $item) {
            if (count($meta['fail_items']) >= self::FAIL_SAMPLE_MAX) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }
            $err = isset($item['err']) ? (string) $item['err'] : '';
            if (function_exists('mb_substr')) {
                $err = mb_substr($err, 0, 200, 'UTF-8');
            } else {
                $err = substr($err, 0, 200);
            }
            $meta['fail_items'][] = array(
                'uid' => (int) (isset($item['uid']) ? $item['uid'] : 0),
                'err' => $err,
            );
        }
        if (!isset($meta['flushed_ok'])) {
            $meta['flushed_ok'] = 0;
        }
        if (!isset($meta['flushed_fail'])) {
            $meta['flushed_fail'] = 0;
        }
        if (!isset($meta['last_db_flush'])) {
            $meta['last_db_flush'] = 0;
        }
    }

    /**
     * Redis→DB：有未刷增量时先开 1 秒窗口；满窗口或 force 才写计数；force 另写 failjson
     *
     * @param int   $logId
     * @param array $meta
     * @param bool  $force
     * @return true|string
     */
    private static function flushProgressToDb($logId, array &$meta, $force = false)
    {
        $logId = (int) $logId;
        if ($logId <= 0) {
            return true;
        }
        $now = time();
        $ok = (int) (isset($meta['ok']) ? $meta['ok'] : 0);
        $fail = (int) (isset($meta['fail']) ? $meta['fail'] : 0);
        $flushedOk = (int) (isset($meta['flushed_ok']) ? $meta['flushed_ok'] : 0);
        $flushedFail = (int) (isset($meta['flushed_fail']) ? $meta['flushed_fail'] : 0);
        $okDelta = max(0, $ok - $flushedOk);
        $failDelta = max(0, $fail - $flushedFail);
        $last = (int) (isset($meta['last_db_flush']) ? $meta['last_db_flush'] : 0);

        if (!$force) {
            if ($okDelta === 0 && $failDelta === 0) {
                return true;
            }
            // 首次有缓冲：只记窗口起点，本拍不落库
            if ($last <= 0) {
                $meta['last_db_flush'] = $now;
                return true;
            }
            if (($now - $last) < self::DB_FLUSH_SEC) {
                return true;
            }
        }

        if ($okDelta > 0 || $failDelta > 0) {
            $prog = MailLogManager::addProgressCounters($logId, $okDelta, $failDelta);
            if ($prog !== true) {
                return $prog;
            }
            $meta['flushed_ok'] = $ok;
            $meta['flushed_fail'] = $fail;
        }
        $meta['last_db_flush'] = $now;

        if ($force) {
            $items = (isset($meta['fail_items']) && is_array($meta['fail_items']))
                ? $meta['fail_items'] : array();
            if ($items !== array()) {
                $r = MailLogManager::setFailJson($logId, $items);
                if ($r !== true) {
                    return $r;
                }
            }
        }
        return true;
    }

    /**
     * @param int    $adminId
     * @param string $token
     * @return bool
     */
    private static function acquireTickLock($adminId, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0 || $token === '') {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($adminId, $token) {
                $key = RedisService::buildKey(self::redisTickKey($adminId));
                $ok = $redis->set($key, $token, array('nx', 'ex' => self::TICK_LOCK_TTL));
                return (bool) $ok;
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int    $adminId
     * @param string $token
     * @return void
     */
    private static function releaseTickLock($adminId, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($adminId, $token) {
                $key = RedisService::buildKey(self::redisTickKey($adminId));
                $cur = $redis->get($key);
                if (is_string($cur) && $cur === $token) {
                    $redis->del($key);
                }
            });
        } catch (Exception $e) {
        }
    }

    /**
     * 将 uid 插回队列头部（时间预算中断时续发）
     *
     * @param int   $adminId
     * @param int[] $ids
     * @param string $token
     * @return bool
     */
    private static function redisUnshiftIds($adminId, array $ids, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0 || $token === '' || $ids === array()) {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($adminId, $ids, $token) {
                $key = RedisService::buildKey(self::redisIdsKey($adminId));
                // lPush 逆序以保持原相对顺序
                $ids = array_reverse(array_values($ids));
                foreach (array_chunk($ids, 200) as $chunk) {
                    $args = array($key);
                    foreach ($chunk as $id) {
                        $signed = self::formatSignedId((int) $id, $token);
                        if ($signed === '') {
                            return false;
                        }
                        $args[] = $signed;
                    }
                    call_user_func_array(array($redis, 'lPush'), $args);
                }
                $redis->expire($key, self::REDIS_TTL);
                return true;
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int    $adminId
     * @param int    $count
     * @param string $token
     * @return bool
     */
    private static function acquireLock($adminId, $count, $token)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($adminId, $count, $token) {
                $key = RedisService::buildKey(self::redisLockKey($adminId));
                $ok = $redis->set($key, json_encode(array(
                    'admin_id' => $adminId,
                    'count'    => (int) $count,
                    'token'    => (string) $token,
                    'at'       => time(),
                )), array('nx', 'ex' => self::REDIS_TTL));
                return (bool) $ok;
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 续期本管理员锁（校验锁内 admin_id + token）
     *
     * @param int    $adminId
     * @param string $token
     * @param int    $count
     * @return void
     */
    private static function touchLock($adminId, $token, $count)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($adminId, $token, $count) {
                $key = RedisService::buildKey(self::redisLockKey($adminId));
                $raw = $redis->get($key);
                $cur = is_string($raw) ? json_decode($raw, true) : null;
                $payload = array(
                    'admin_id' => $adminId,
                    'count'    => (int) $count,
                    'token'    => (string) $token,
                    'at'       => time(),
                );
                if (!is_array($cur)) {
                    $redis->set($key, json_encode($payload), array('ex' => self::REDIS_TTL));
                    return;
                }
                if ((int) (isset($cur['admin_id']) ? $cur['admin_id'] : 0) !== $adminId) {
                    return;
                }
                $curToken = isset($cur['token']) ? (string) $cur['token'] : '';
                if ($curToken !== '' && $curToken !== (string) $token) {
                    return;
                }
                $redis->set($key, json_encode($payload), array('ex' => self::REDIS_TTL));
            });
        } catch (Exception $e) {
        }
    }

    /**
     * @param int $adminId
     * @return void
     */
    private static function releaseLock($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($adminId) {
                $redis->del(RedisService::buildKey(self::redisLockKey($adminId)));
            });
        } catch (Exception $e) {
        }
    }

    /**
     * @param int    $adminId
     * @param int[]  $ids
     * @param string $token
     * @return bool
     */
    private static function redisPutIds($adminId, array $ids, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0 || $token === '') {
            return false;
        }
        return (bool) RedisService::withClient(function ($redis) use ($adminId, $ids, $token) {
            $key = RedisService::buildKey(self::redisIdsKey($adminId));
            $redis->del($key);
            foreach (array_chunk($ids, 200) as $chunk) {
                $args = array($key);
                foreach ($chunk as $id) {
                    $signed = self::formatSignedId((int) $id, $token);
                    if ($signed === '') {
                        return false;
                    }
                    $args[] = $signed;
                }
                call_user_func_array(array($redis, 'rPush'), $args);
            }
            $redis->expire($key, self::REDIS_TTL);
            return true;
        });
    }

    /**
     * @param int    $adminId
     * @param int    $n
     * @param string $token
     * @return int[]|null
     */
    private static function redisPopIds($adminId, $n, $token)
    {
        $adminId = (int) $adminId;
        $n = max(1, (int) $n);
        $token = (string) $token;
        if ($adminId <= 0) {
            return null;
        }
        try {
            return RedisService::withClient(function ($redis) use ($adminId, $n, $token) {
                $key = RedisService::buildKey(self::redisIdsKey($adminId));
                $out = array();
                for ($i = 0; $i < $n; $i++) {
                    $v = $redis->lPop($key);
                    if ($v === false || $v === null) {
                        break;
                    }
                    $id = self::parseSignedId($v, $token);
                    if ($id <= 0) {
                        return null;
                    }
                    $out[] = $id;
                }
                // pop 成功后续期，避免长任务中途 TTL 蒸发导致假「队列空」
                if ($out !== array()) {
                    $redis->expire($key, self::REDIS_TTL);
                }
                return $out;
            });
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 队列已空时的收尾：覆盖数达标 → DONE；否则中断（防 TTL 假完成）
     *
     * @param int      $adminId
     * @param string   $token
     * @param int      $logId
     * @param array    $meta
     * @param int      $done
     * @param int      $okNum
     * @param int      $failNum
     * @param int      $total
     * @param callable $progressPayload
     * @return array
     */
    private static function finalizeWhenQueueEmpty(
        $adminId,
        $token,
        $logId,
        array $meta,
        $done,
        $okNum,
        $failNum,
        $total,
        $progressPayload
    ) {
        $meta['done'] = (int) $done;
        $meta['ok'] = (int) $okNum;
        $meta['fail'] = (int) $failNum;
        $covered = (int) $okNum + (int) $failNum;
        $total = (int) $total;
        $complete = ($total > 0 && $covered >= $total);

        $progDone = self::flushProgressToDb($logId, $meta, true);
        if ($progDone !== true) {
            self::clearRedis($adminId);
            self::releaseLock($adminId);
            return array(
                'ok'   => false,
                'msg'  => is_string($progDone) ? $progDone : '批次已结束，发送已停止',
                'data' => $progressPayload($meta, true, array('aborted' => 1)),
            );
        }

        if ($complete) {
            MailLogManager::finish($logId, MailLogManager::STATUS_DONE);
            self::clearRedis($adminId);
            self::releaseLock($adminId);
            return array(
                'ok'   => true,
                'msg'  => '发送完成',
                'data' => $progressPayload($meta, true, array()),
            );
        }

        MailLogManager::finish($logId, MailLogManager::STATUS_ABORT);
        self::clearRedis($adminId);
        self::releaseLock($adminId);
        return array(
            'ok'   => false,
            'msg'  => '发送未完成（队列已失效或进度不完整），已中断。已处理 '
                . $covered . '/' . $total,
            'data' => $progressPayload($meta, true, array('aborted' => 1, 'incomplete' => 1)),
        );
    }

    /**
     * @param int    $adminId
     * @param int[]  $ids
     * @param string $token
     * @return bool
     */
    private static function redisPutInflight($adminId, array $ids, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0 || $token === '') {
            return false;
        }
        try {
            return (bool) RedisService::withClient(function ($redis) use ($adminId, $ids, $token) {
                $key = RedisService::buildKey(self::redisInflightKey($adminId));
                $redis->del($key);
                if ($ids === array()) {
                    return true;
                }
                $args = array($key);
                foreach ($ids as $id) {
                    $signed = self::formatSignedId((int) $id, $token);
                    if ($signed === '') {
                        return false;
                    }
                    $args[] = $signed;
                }
                call_user_func_array(array($redis, 'rPush'), $args);
                $redis->expire($key, self::REDIS_TTL);
                return true;
            });
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 取出并清空在途列表；签名失败返回 null
     *
     * @param int    $adminId
     * @param string $token
     * @return int[]|null
     */
    private static function redisTakeInflight($adminId, $token)
    {
        $adminId = (int) $adminId;
        $token = (string) $token;
        if ($adminId <= 0) {
            return array();
        }
        try {
            return RedisService::withClient(function ($redis) use ($adminId, $token) {
                $key = RedisService::buildKey(self::redisInflightKey($adminId));
                $len = (int) $redis->lLen($key);
                if ($len <= 0) {
                    return array();
                }
                $out = array();
                for ($i = 0; $i < $len; $i++) {
                    $v = $redis->lPop($key);
                    if ($v === false || $v === null) {
                        break;
                    }
                    $id = self::parseSignedId($v, $token);
                    if ($id <= 0) {
                        $redis->del($key);
                        return null;
                    }
                    $out[] = $id;
                }
                $redis->del($key);
                return $out;
            });
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param int $adminId
     * @return void
     */
    private static function redisClearInflight($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        try {
            RedisService::withClient(function ($redis) use ($adminId) {
                $redis->del(RedisService::buildKey(self::redisInflightKey($adminId)));
            });
        } catch (Exception $e) {
        }
    }

    /**
     * @param int $adminId
     * @return int
     */
    private static function redisIdsLen($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return 0;
        }
        try {
            return (int) RedisService::withClient(function ($redis) use ($adminId) {
                return (int) $redis->lLen(RedisService::buildKey(self::redisIdsKey($adminId)));
            });
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int   $adminId
     * @param array $meta
     * @return bool
     */
    private static function redisPutMeta($adminId, array $meta)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return false;
        }
        try {
            RedisCache::put(self::redisMetaKey($adminId), $meta, self::REDIS_TTL);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int $adminId
     * @return array|null
     */
    private static function redisGetMeta($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return null;
        }
        $m = RedisCache::get(self::redisMetaKey($adminId));
        return is_array($m) ? $m : null;
    }

    /**
     * @param int $adminId
     * @return void
     */
    private static function clearRedis($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        try {
            RedisCache::forget(self::redisMetaKey($adminId));
            RedisService::withClient(function ($redis) use ($adminId) {
                $redis->del(RedisService::buildKey(self::redisIdsKey($adminId)));
                $redis->del(RedisService::buildKey(self::redisInflightKey($adminId)));
            });
        } catch (Exception $e) {
        }
    }
}
