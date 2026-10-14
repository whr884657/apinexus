<?php
/**
 * 文件：core/MailLogManager.php
 * 作用：邮件群发批次记录（表 maillog；一批一行）
 */

class MailLogManager
{
    const STATUS_RUNNING = 0;
    const STATUS_DONE = 1;
    const STATUS_ABORT = 2;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('maillog');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        try {
            return DatabaseMigrator::tableExists('maillog');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    private static function hasLastactivityColumn()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cached = class_exists('DatabaseMigrator')
                && DatabaseMigrator::tableColumnExists('maillog', 'lastactivity');
        } catch (Exception $e) {
            $cached = false;
        }
        return $cached;
    }

    /**
     * 刷新发送中批次的最后活动时间（无进度增量时也要心跳，防误收尸）
     *
     * @param int $id
     * @return bool
     */
    public static function touchActivity($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady() || !self::hasLastactivityColumn()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `lastactivity` = NOW()
                 WHERE `id` = ? AND `status` = ? LIMIT 1'
            );
            $stmt->execute(array($id, self::STATUS_RUNNING));
            return (int) $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param mixed $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $n = (int) $status;
        if ($n === self::STATUS_DONE) {
            return '已完成';
        }
        if ($n === self::STATUS_ABORT) {
            return '已中断';
        }
        return '发送中';
    }

    /**
     * @param array $row
     * @return array
     */
    public static function formatRow(array $row, $withUids = false)
    {
        $id = (int) (isset($row['id']) ? $row['id'] : 0);
        $status = (int) (isset($row['status']) ? $row['status'] : self::STATUS_RUNNING);
        $out = array(
            'id'           => $id,
            'adminid'      => (int) (isset($row['adminid']) ? $row['adminid'] : 0),
            'tplid'        => (int) (isset($row['tplid']) ? $row['tplid'] : 0),
            'tplname'      => isset($row['tplname']) ? (string) $row['tplname'] : '',
            'subject'      => isset($row['subject']) ? (string) $row['subject'] : '',
            'filtersum'    => isset($row['filtersum']) ? (string) $row['filtersum'] : '',
            'total'        => (int) (isset($row['total']) ? $row['total'] : 0),
            'oknum'        => (int) (isset($row['oknum']) ? $row['oknum'] : 0),
            'failnum'      => (int) (isset($row['failnum']) ? $row['failnum'] : 0),
            'status'       => $status,
            'status_label' => self::statusLabel($status),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'finishtime'   => isset($row['finishtime']) && $row['finishtime'] !== null
                ? (string) $row['finishtime'] : '',
            'admin_name'   => isset($row['admin_name']) ? (string) $row['admin_name'] : '',
        );
        if ($withUids) {
            $out['uids'] = self::decodeIdList(isset($row['uids']) ? $row['uids'] : '[]');
            $out['fails'] = self::decodeFailList(isset($row['failjson']) ? $row['failjson'] : '');
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    public static function decodeIdList($raw)
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $list = json_decode((string) $raw, true);
        }
        if (!is_array($list)) {
            return array();
        }
        $out = array();
        foreach ($list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    /**
     * @param mixed $raw
     * @return array
     */
    public static function decodeFailList($raw)
    {
        if ($raw === null || $raw === '') {
            return array();
        }
        $list = json_decode((string) $raw, true);
        return is_array($list) ? $list : array();
    }

    /**
     * @param array $data
     * @return array|string
     */
    public static function createBatch(array $data)
    {
        if (!self::tableReady()) {
            return '发送记录表尚未就绪';
        }
        $adminId = (int) (isset($data['adminid']) ? $data['adminid'] : 0);
        $uids = self::decodeIdList(isset($data['uids']) ? $data['uids'] : array());
        $total = count($uids);
        if ($adminId <= 0 || $total <= 0) {
            return '批次参数无效';
        }
        $tplId = (int) (isset($data['tplid']) ? $data['tplid'] : 0);
        $tplName = trim((string) (isset($data['tplname']) ? $data['tplname'] : ''));
        $subject = trim((string) (isset($data['subject']) ? $data['subject'] : ''));
        $filterSum = trim((string) (isset($data['filtersum']) ? $data['filtersum'] : ''));
        if (function_exists('mb_substr')) {
            if (mb_strlen($tplName, 'UTF-8') > 200) {
                $tplName = mb_substr($tplName, 0, 200, 'UTF-8');
            }
            if (mb_strlen($subject, 'UTF-8') > 500) {
                $subject = mb_substr($subject, 0, 500, 'UTF-8');
            }
            if (mb_strlen($filterSum, 'UTF-8') > 500) {
                $filterSum = mb_substr($filterSum, 0, 500, 'UTF-8');
            }
        }
        try {
            $pdo = Database::connect();
            $hasLast = self::hasLastactivityColumn();
            if ($hasLast) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                    (`adminid`, `tplid`, `tplname`, `subject`, `filtersum`, `total`, `oknum`, `failnum`,
                     `status`, `uids`, `failjson`, `createtime`, `lastactivity`)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, NOW(), NOW())'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                    (`adminid`, `tplid`, `tplname`, `subject`, `filtersum`, `total`, `oknum`, `failnum`,
                     `status`, `uids`, `failjson`, `createtime`)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, NOW())'
                );
            }
            $stmt->execute(array(
                $adminId,
                $tplId,
                $tplName,
                $subject,
                $filterSum,
                $total,
                self::STATUS_RUNNING,
                json_encode($uids),
                '[]',
            ));
            $id = (int) $pdo->lastInsertId();
            $row = self::findById($id);
            return is_array($row) ? self::formatRow($row, true) : '创建记录失败';
        } catch (Exception $e) {
            return '创建发送记录失败';
        }
    }

    /**
     * 仅累加成功/失败计数（不读写 failjson，供 Redis 缓冲刷库）
     *
     * @param int $id
     * @param int $okDelta
     * @param int $failDelta
     * @return true|string
     */
    public static function addProgressCounters($id, $okDelta, $failDelta)
    {
        $id = (int) $id;
        $okDelta = max(0, (int) $okDelta);
        $failDelta = max(0, (int) $failDelta);
        if ($id <= 0 || !self::tableReady()) {
            return '记录无效';
        }
        if ($okDelta === 0 && $failDelta === 0) {
            return true;
        }
        try {
            $pdo = Database::connect();
            $hasLast = self::hasLastactivityColumn();
            if ($hasLast) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                     `oknum` = `oknum` + ?, `failnum` = `failnum` + ?,
                     `lastactivity` = NOW()
                     WHERE `id` = ? AND `status` = ? LIMIT 1'
                );
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                     `oknum` = `oknum` + ?, `failnum` = `failnum` + ?
                     WHERE `id` = ? AND `status` = ? LIMIT 1'
                );
            }
            $stmt->execute(array($okDelta, $failDelta, $id, self::STATUS_RUNNING));
            if ((int) $stmt->rowCount() < 1) {
                return '批次已结束，无法更新进度';
            }
            return true;
        } catch (Exception $e) {
            return '更新进度失败';
        }
    }

    /**
     * 整批覆盖失败抽样（结束/中止时由 Redis 缓冲一次性落库）
     *
     * @param int   $id
     * @param array $failItems
     * @return true|string
     */
    public static function setFailJson($id, array $failItems)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return '记录无效';
        }
        $fails = array();
        foreach ($failItems as $item) {
            if (count($fails) >= 200) {
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
            $fails[] = array(
                'uid' => (int) (isset($item['uid']) ? $item['uid'] : 0),
                'err' => $err,
            );
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `failjson` = ?
                 WHERE `id` = ? AND `status` = ? LIMIT 1'
            );
            $stmt->execute(array(json_encode($fails), $id, self::STATUS_RUNNING));
            if ((int) $stmt->rowCount() < 1) {
                return '批次已结束，无法写入失败明细';
            }
            return true;
        } catch (Exception $e) {
            return '写入失败明细失败';
        }
    }

    /**
     * @param int   $id
     * @param int   $okDelta
     * @param int   $failDelta
     * @param array $failItems 非空时才会合并写 failjson；空则只更新计数
     * @return true|string
     */
    public static function addProgress($id, $okDelta, $failDelta, array $failItems = array())
    {
        if ($failItems === array()) {
            return self::addProgressCounters($id, $okDelta, $failDelta);
        }
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return '记录无效';
        }
        try {
            $pdo = Database::connect();
            $row = self::findById($id);
            if (!is_array($row)) {
                return '记录不存在';
            }
            $status = (int) (isset($row['status']) ? $row['status'] : -1);
            if ($status !== self::STATUS_RUNNING) {
                return '批次已结束，无法更新进度';
            }
            $fails = self::decodeFailList(isset($row['failjson']) ? $row['failjson'] : '');
            foreach ($failItems as $item) {
                if (count($fails) >= 200) {
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
                $fails[] = array(
                    'uid' => (int) (isset($item['uid']) ? $item['uid'] : 0),
                    'err' => $err,
                );
            }
            $hasLast = self::hasLastactivityColumn();
            if ($hasLast) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                     `oknum` = `oknum` + ?, `failnum` = `failnum` + ?, `failjson` = ?,
                     `lastactivity` = NOW()
                     WHERE `id` = ? AND `status` = ? LIMIT 1'
                );
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                     `oknum` = `oknum` + ?, `failnum` = `failnum` + ?, `failjson` = ?
                     WHERE `id` = ? AND `status` = ? LIMIT 1'
                );
            }
            $stmt->execute(array(
                max(0, (int) $okDelta),
                max(0, (int) $failDelta),
                json_encode($fails),
                $id,
                self::STATUS_RUNNING,
            ));
            if ((int) $stmt->rowCount() < 1) {
                return '批次已结束，无法更新进度';
            }
            return true;
        } catch (Exception $e) {
            return '更新进度失败';
        }
    }

    /**
     * @param int $id
     * @param int $status
     * @return true|string
     */
    public static function finish($id, $status = self::STATUS_DONE)
    {
        $id = (int) $id;
        $status = (int) $status;
        if ($status !== self::STATUS_DONE && $status !== self::STATUS_ABORT) {
            $status = self::STATUS_DONE;
        }
        if ($id <= 0 || !self::tableReady()) {
            return '记录无效';
        }
        try {
            $pdo = Database::connect();
            $started = false;
            try {
                $started = $pdo->beginTransaction();
            } catch (Exception $e) {
                $started = false;
            }
            if ($started) {
                $sel = $pdo->prepare(
                    'SELECT `status` FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
                );
                $sel->execute(array($id));
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row) || (int) $row['status'] !== self::STATUS_RUNNING) {
                    $pdo->rollBack();
                    return '批次状态已变更';
                }
            }
            // CAS：仅发送中可结束，避免误收尸后被 DONE 盖回
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `finishtime` = NOW()
                 WHERE `id` = ? AND `status` = ? LIMIT 1'
            );
            $stmt->execute(array($status, $id, self::STATUS_RUNNING));
            if ((int) $stmt->rowCount() < 1) {
                if ($started) {
                    $pdo->rollBack();
                }
                return '批次状态已变更';
            }
            if ($started) {
                $pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
            }
            return '结束记录失败';
        }
    }

    /**
     * 将「无活动」的超时发送中批次标为已中断。
     * 判定：createtime 过旧，且该管理员当前 Redis 群发任务不是本批次（或已无任务）。
     *
     * @param int $olderThanSeconds
     * @return int 影响行数
     */
    public static function abortStaleRunning($olderThanSeconds = 900)
    {
        $olderThanSeconds = max(60, (int) $olderThanSeconds);
        if (!self::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            // 优先按 lastactivity；无列时回退 createtime（升级前兼容）
            $timeCol = self::hasLastactivityColumn() ? 'lastactivity' : 'createtime';
            $stmt = $pdo->prepare(
                'SELECT `id`, `adminid` FROM `' . self::table() . '`
                 WHERE `status` = ?
                   AND `' . $timeCol . '` < DATE_SUB(NOW(), INTERVAL ? SECOND)
                 ORDER BY `id` ASC LIMIT 100'
            );
            $stmt->execute(array(self::STATUS_RUNNING, $olderThanSeconds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows) || $rows === array()) {
                return 0;
            }
            $n = 0;
            foreach ($rows as $row) {
                $id = (int) (isset($row['id']) ? $row['id'] : 0);
                $adminId = (int) (isset($row['adminid']) ? $row['adminid'] : 0);
                if ($id <= 0) {
                    continue;
                }
                // 仍有进行中的 Redis 任务指向本批次 → 长任务，不收尸
                if (class_exists('OpsMailManager')
                    && method_exists('OpsMailManager', 'isSendActiveForLog')
                    && OpsMailManager::isSendActiveForLog($adminId, $id)) {
                    continue;
                }
                $r = self::finish($id, self::STATUS_ABORT);
                if ($r === true) {
                    $n++;
                }
            }
            return $n;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT m.*, a.`username` AS `admin_name`
                 FROM `' . self::table() . '` m
                 LEFT JOIN `' . Database::table('admin') . '` a ON a.`id` = m.`adminid`
                 WHERE m.`id` = ? LIMIT 1'
            );
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array $opts
     * @return array{list:array,total:int,page:int,pagesize:int}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $status = isset($opts['status']) ? (int) $opts['status'] : -1;
        $empty = array('list' => array(), 'total' => 0, 'page' => $page, 'pagesize' => $pagesize);
        if (!self::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $where = array('1=1');
            $bind = array();
            if ($status === self::STATUS_RUNNING || $status === self::STATUS_DONE || $status === self::STATUS_ABORT) {
                $where[] = 'm.`status` = ?';
                $bind[] = $status;
            }
            if ($q !== '') {
                $like = function_exists('vs_sql_like_contains')
                    ? vs_sql_like_contains($q)
                    : ('%' . addcslashes($q, "\\%_") . '%');
                $where[] = '(m.`tplname` LIKE ? ESCAPE \'\\\\\' OR m.`subject` LIKE ? ESCAPE \'\\\\\')';
                $bind[] = $like;
                $bind[] = $like;
            }
            $whereSql = implode(' AND ', $where);
            $from = ' FROM `' . self::table() . '` m WHERE ' . $whereSql;
            $cnt = $pdo->prepare('SELECT COUNT(*)' . $from);
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            if ($total <= 0) {
                return array('list' => array(), 'total' => 0, 'page' => 1, 'pagesize' => $pagesize);
            }
            $maxPage = max(1, (int) ceil($total / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            $offset = (int) (($page - 1) * $pagesize);
            $sql = 'SELECT m.*, a.`username` AS `admin_name`
                    FROM `' . self::table() . '` m
                    LEFT JOIN `' . Database::table('admin') . '` a ON a.`id` = m.`adminid`
                    WHERE ' . $whereSql . '
                    ORDER BY m.`id` DESC LIMIT ' . $pagesize . ' OFFSET ' . $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $list[] = self::formatRow($row, false);
                    }
                }
            }
            return array('list' => $list, 'total' => $total, 'page' => $page, 'pagesize' => $pagesize);
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 详情用户页：按 uids 查 user
     *
     * @param int    $logId
     * @param int    $page
     * @param int    $pagesize
     * @param string $q
     * @return array{list:array,total:int,page:int,pagesize:int}|string
     */
    public static function listUsersPaged($logId, $page = 1, $pagesize = 20, $q = '')
    {
        $row = self::findById($logId);
        if (!is_array($row)) {
            return '记录不存在';
        }
        $uids = self::decodeIdList(isset($row['uids']) ? $row['uids'] : '[]');
        $q = trim((string) $q);
        $page = max(1, (int) $page);
        $pagesize = max(1, min(50, (int) $pagesize));
        if (empty($uids)) {
            return array('list' => array(), 'total' => 0, 'page' => 1, 'pagesize' => $pagesize);
        }
        try {
            $pdo = Database::connect();
            $list = array();
            // 无搜索：总数=批次 uid 数；只查当前页 slice，避免每页全量 IN(2000)
            if ($q === '') {
                $total = count($uids);
                $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
                if ($page > $maxPage) {
                    $page = $maxPage;
                }
                $offset = (int) (($page - 1) * $pagesize);
                $slice = array_slice($uids, $offset, $pagesize);
                if (!empty($slice)) {
                    $ph2 = implode(',', array_fill(0, count($slice), '?'));
                    $stmt = $pdo->prepare(
                        'SELECT `id`, `username`, `email`, `avatar`, `status`
                         FROM `' . Database::table('user') . '` WHERE `id` IN (' . $ph2 . ')'
                    );
                    $stmt->execute($slice);
                    $map = array();
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $map[(int) $r['id']] = $r;
                    }
                    foreach ($slice as $uid) {
                        if (isset($map[$uid])) {
                            $r = $map[$uid];
                            $list[] = array(
                                'id'       => (int) $r['id'],
                                'username' => (string) $r['username'],
                                'email'    => (string) $r['email'],
                                'avatar'   => isset($r['avatar']) ? (string) $r['avatar'] : '',
                                'missing'  => 0,
                            );
                        } else {
                            $list[] = array(
                                'id'       => $uid,
                                'username' => '用户#' . $uid,
                                'email'    => '',
                                'avatar'   => '',
                                'missing'  => 1,
                            );
                        }
                    }
                }
                return array('list' => $list, 'total' => $total, 'page' => $page, 'pagesize' => $pagesize);
            }

            $ph = implode(',', array_fill(0, count($uids), '?'));
            $bind = $uids;
            $like = function_exists('vs_sql_like_contains')
                ? vs_sql_like_contains($q)
                : ('%' . addcslashes($q, "\\%_") . '%');
            $where = 'u.`id` IN (' . $ph . ') AND (u.`username` LIKE ? ESCAPE \'\\\\\' OR u.`email` LIKE ? ESCAPE \'\\\\\')';
            $bind[] = $like;
            $bind[] = $like;
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM `' . Database::table('user') . '` u WHERE ' . $where);
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            $offset = (int) (($page - 1) * $pagesize);
            $stmt = $pdo->prepare(
                'SELECT u.`id`, u.`username`, u.`email`, u.`avatar`, u.`status`
                 FROM `' . Database::table('user') . '` u WHERE ' . $where . '
                 ORDER BY u.`id` DESC LIMIT ' . $pagesize . ' OFFSET ' . $offset
            );
            $stmt->execute($bind);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = array(
                    'id'       => (int) $r['id'],
                    'username' => (string) $r['username'],
                    'email'    => (string) $r['email'],
                    'avatar'   => isset($r['avatar']) ? (string) $r['avatar'] : '',
                    'missing'  => 0,
                );
            }
            return array('list' => $list, 'total' => $total, 'page' => $page, 'pagesize' => $pagesize);
        } catch (Exception $e) {
            return '加载用户失败';
        }
    }
}
