<?php
/**
 * 文件：core/PointsManager.php
 * 作用：用户积分余额增减与充值履约
 */

class PointsManager
{
    /** @var int 批量入账时推迟 orders 缓存失效（>0 则 change 不逐笔 invalidate） */
    private static $bulkOrdersDefer = 0;

    /**
     * 开始批量积分入账（推迟 Redis 订单缓存 SCAN；须与 endBulkOrders 成对）
     *
     * @return void
     */
    public static function beginBulkOrders()
    {
        self::$bulkOrdersDefer++;
    }

    /**
     * 结束批量入账并统一失效订单相关缓存
     *
     * @return void
     */
    public static function endBulkOrders()
    {
        if (self::$bulkOrdersDefer > 0) {
            self::$bulkOrdersDefer--;
        }
        if (self::$bulkOrdersDefer === 0 && class_exists('RedisCache')) {
            try {
                RedisCache::invalidateOrders();
            } catch (Exception $e) {
                // ignore
            }
        }
    }

    /**
     * @param int $userId
     * @return float
     */
    public static function balance($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::hasPointsColumn()) {
            return 0.0;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT `points` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId));
            $v = $stmt->fetchColumn();
            return $v === false ? 0.0 : (float) $v;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * @return bool
     */
    public static function hasPointsColumn()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . Database::table('user') . '` LIKE ' . $pdo->quote('points'));
            $ok = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 用户表是否已有累计消耗字段（v13.22.2+）
     *
     * @return bool
     */
    public static function hasSpentColumn()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . Database::table('user') . '` LIKE ' . $pdo->quote('pointsspent'));
            $ok = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 读取用户累计消耗（优先缓存列，缺列时回落扫 orders）
     *
     * @param int $userId
     * @return float
     */
    public static function spentTotal($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return 0.0;
        }
        if (self::hasSpentColumn()) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare(
                    'SELECT `pointsspent` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array($userId));
                $v = $stmt->fetchColumn();
                if ($v !== false) {
                    return (float) $v;
                }
            } catch (Exception $e) {
                // fall through
            }
        }
        if (class_exists('OrderManager') && method_exists('OrderManager', 'sumUserSpent')) {
            return (float) OrderManager::sumUserSpent($userId);
        }
        return 0.0;
    }

    /**
     * API 调用扣费（事务）
     *
     * @param int    $userId
     * @param float  $amount
     * @param int    $apiId
     * @param int    $keyId
     * @param string $remark
     * @return array{ok:bool,msg:string,balance?:float,orderno?:string}
     */
    public static function deductApiCall($userId, $amount, $apiId, $keyId, $remark = '')
    {
        return self::change(
            (int) $userId,
            OrderManager::DIRECT_DEC,
            OrderManager::KIND_API,
            (float) $amount,
            array(
                'apiid'  => (int) $apiId,
                'keyid'  => (int) $keyId,
                'remark' => $remark !== '' ? $remark : 'API 调用扣费',
                'status' => OrderManager::STATUS_DONE,
            )
        );
    }

    /**
     * 上游失败等场景退回已预扣积分
     *
     * @param int    $userId
     * @param float  $amount
     * @param int    $apiId
     * @param int    $keyId
     * @param string $remark
     * @return array{ok:bool,msg:string,balance?:float}
     */
    public static function refundApiCall($userId, $amount, $apiId, $keyId, $remark = '')
    {
        return self::change(
            (int) $userId,
            OrderManager::DIRECT_INC,
            OrderManager::KIND_REFUND,
            (float) $amount,
            array(
                'apiid'  => (int) $apiId,
                'keyid'  => (int) $keyId,
                'remark' => $remark !== '' ? $remark : '上游失败退回',
                'status' => OrderManager::STATUS_DONE,
            )
        );
    }

    /**
     * 管理端写积分：必须当前后台管理员已登录（Auth 会话）
     *
     * @return array{ok:bool,msg:string}|null  null=已通过
     */
    private static function requireAdminSession()
    {
        if (!class_exists('Auth') || !Auth::check() || Auth::id() <= 0) {
            return array('ok' => false, 'msg' => '需要管理员登录后才能操作积分');
        }
        return null;
    }

    /**
     * 管理员调整积分（可增可减）
     *
     * @param int    $userId
     * @param float  $delta 正数加款，负数扣款
     * @param string $remark
     * @return array{ok:bool,msg:string,balance?:float}
     */
    public static function adminAdjust($userId, $delta, $remark = '')
    {
        $deny = self::requireAdminSession();
        if ($deny !== null) {
            return $deny;
        }
        $delta = (float) $delta;
        if ($delta == 0.0) {
            return array('ok' => false, 'msg' => '调整数量不能为 0');
        }
        if ($delta > 0) {
            return self::change(
                (int) $userId,
                OrderManager::DIRECT_INC,
                OrderManager::KIND_ADMIN_ADD,
                $delta,
                array(
                    'remark' => $remark !== '' ? $remark : '管理员加款',
                    'status' => OrderManager::STATUS_DONE,
                    'paytime' => date('Y-m-d H:i:s'),
                )
            );
        }
        return self::change(
            (int) $userId,
            OrderManager::DIRECT_DEC,
            OrderManager::KIND_ADMIN_SUB,
            abs($delta),
            array(
                'remark' => $remark !== '' ? $remark : '管理员扣款',
                'status' => OrderManager::STATUS_DONE,
            )
        );
    }

    /**
     * 运营激励·积分赠送（只增不减；流水 kind 仍为管理员加款，理由必填写入 remark）
     *
     * @param int    $userId
     * @param float  $amount 须 > 0
     * @param string $remark 非空；用户/后台积分变动「说明」可见
     * @return array{ok:bool,msg:string,balance?:float,orderno?:string}
     */
    public static function opsGift($userId, $amount, $remark)
    {
        $deny = self::requireAdminSession();
        if ($deny !== null) {
            return $deny;
        }
        $userId = (int) $userId;
        $amount = (float) $amount;
        $remark = trim((string) $remark);
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '用户无效');
        }
        // 拒绝 NaN/Inf；上限与 IncentiveManager::AMOUNT_MAX 对齐，防异常入账
        if (!is_finite($amount) || $amount < 1 || $amount > 10000) {
            return array('ok' => false, 'msg' => '赠送积分须至少为 1');
        }
        if ($remark === '') {
            return array('ok' => false, 'msg' => '赠送理由不能为空');
        }
        if (function_exists('mb_strlen') && mb_strlen($remark, 'UTF-8') > 120) {
            $remark = mb_substr($remark, 0, 120, 'UTF-8');
        } elseif (strlen($remark) > 120) {
            $remark = substr($remark, 0, 120);
        }
        return self::change(
            $userId,
            OrderManager::DIRECT_INC,
            OrderManager::KIND_ADMIN_ADD,
            $amount,
            array(
                'remark'  => $remark,
                'status'  => OrderManager::STATUS_DONE,
                'paytime' => date('Y-m-d H:i:s'),
            )
        );
    }

    /**
     * @deprecated 禁止绕过 CardKeyManager::redeem；保留方法签名以免旧调用静默入账
     *
     * @param int    $userId
     * @param float  $amount
     * @param string $remark
     * @return array{ok:bool,msg:string}
     */
    public static function creditCardKey($userId, $amount, $remark = '')
    {
        unset($userId, $amount, $remark);
        return array('ok' => false, 'msg' => '请使用 CardKeyManager::redeem 兑换卡密');
    }

    /**
     * 创建待支付充值单并拉起码支付
     *
     * @param int    $userId
     * @param string $payType
     * @param string $packageId
     * @param float  $money 自定义金额（无套餐时）
     * @return array{ok:bool,msg:string,data?:array}
     */
    public static function createRecharge($userId, $payType, $packageId = '', $money = 0)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '请先登录');
        }
        if (!PayConfig::isReady()) {
            return array('ok' => false, 'msg' => '充值暂未开放，请联系管理员');
        }
        if (!OrderManager::tableReady() || !self::hasPointsColumn()) {
            return array('ok' => false, 'msg' => '积分系统未就绪，请先执行数据库结构更新');
        }

        $payType = strtolower(trim((string) $payType));
        $methods = PayConfig::methods();
        if (!in_array($payType, $methods, true)) {
            return array('ok' => false, 'msg' => '不支持的支付方式');
        }

        $points = 0.0;
        $moneyVal = 0.0;
        $pkgName = '积分充值';
        $packageId = trim((string) $packageId);
        if ($packageId !== '') {
            $found = null;
            foreach (PayConfig::packages() as $pkg) {
                if ($pkg['id'] === $packageId) {
                    $found = $pkg;
                    break;
                }
            }
            if (!$found) {
                return array('ok' => false, 'msg' => '套餐不存在');
            }
            $moneyVal = (float) $found['money'];
            $points = (float) $found['points'];
            $pkgName = $found['name'];
        } else {
            $moneyVal = round((float) $money, 2);
            if ($moneyVal < 0.01) {
                return array('ok' => false, 'msg' => '请选择套餐或输入有效金额');
            }
            $calc = PayConfig::customPoints($moneyVal);
            $points = (float) $calc['points'];
            $pkgName = '自定义充值';
            if (!empty($calc['percent'])) {
                $pkgName .= '（赠' . (int) $calc['percent'] . '%）';
            }
        }
        if ($points <= 0) {
            return array('ok' => false, 'msg' => '积分数量无效');
        }

        $orderno = OrderManager::genOrderNo('PO');
        $cfg = PayConfig::all();
        $channel = isset($cfg['channel'][$payType]) ? $cfg['channel'][$payType] : '';

        $base = rtrim(vs_base_url(), '/');
        // 直接访问 core 下真实 .php；notify_url 在下单时传给码支付，无需商户后台配置、不改伪静态
        $notify = $base . '/core/play/codeplay/notify.php';
        $return = $base . '/core/play/codeplay/return.php';

        $id = OrderManager::insert(array(
            'orderno' => $orderno,
            'userid'  => $userId,
            'direct'  => OrderManager::DIRECT_INC,
            'kind'    => OrderManager::KIND_RECHARGE,
            'amount'  => $points,
            'balance' => self::balance($userId),
            'money'   => $moneyVal,
            'paytype' => $payType,
            'status'  => OrderManager::STATUS_PENDING,
            'remark'  => $pkgName,
        ));
        if (!$id) {
            return array('ok' => false, 'msg' => '创建订单失败');
        }

        $pay = CodePayClient::create(array(
            'url'          => $cfg['url'],
            'pid'          => $cfg['pid'],
            'key'          => $cfg['key'],
            'type'         => $payType,
            'out_trade_no' => $orderno,
            'notify_url'   => $notify,
            'return_url'   => $return,
            'name'         => $pkgName,
            'money'        => $moneyVal,
            'clientip'     => AuthSecurity::clientIp(),
            'param'        => (string) $userId,
            'channel_id'   => $channel,
        ));
        if (!$pay['ok']) {
            self::cancelPending($orderno);
            return array('ok' => false, 'msg' => $pay['msg']);
        }

        $data = $pay['data'];
        $displayMoney = $data['money'];
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . OrderManager::table() . '` SET `money` = ?, `tradeno` = ? WHERE `orderno` = ? AND `status` = ?'
            );
            $stmt->execute(array(
                (float) $displayMoney,
                $data['trade_no'],
                $orderno,
                OrderManager::STATUS_PENDING,
            ));
        } catch (Exception $e) {
            // ignore
        }

        if (class_exists('PayPendingWatch')) {
            PayPendingWatch::track($orderno);
        }

        return array(
            'ok'   => true,
            'msg'  => '请扫码支付',
            'data' => array(
                'orderno'    => $orderno,
                'money'      => $displayMoney,
                'points'     => PayConfig::fmtPoints($points),
                'paytype'    => $payType,
                'pay_label'  => PayConfig::methodLabel($payType),
                'qrcode'     => isset($data['qrcode']) ? $data['qrcode'] : '',
                'qr_kind'    => isset($data['qr_kind']) ? $data['qr_kind'] : 'content',
                'payurl'     => $data['payurl'],
                'expire_sec' => class_exists('PayPendingWatch') ? PayPendingWatch::ttlSeconds() : 180,
            ),
        );
    }

    /**
     * 支付回调履约（幂等）
     *
     * @param string $orderno
     * @param string $tradeno
     * @param string $money
     * @return bool
     */
    public static function completeRecharge($orderno, $tradeno = '', $money = '')
    {
        if (!OrderManager::tableReady() || !self::hasPointsColumn()) {
            return false;
        }
        $orderno = trim((string) $orderno);
        if ($orderno === '') {
            return false;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT * FROM `' . OrderManager::table() . '` WHERE `orderno` = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(array($orderno));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $pdo->rollBack();
                return false;
            }
            if ((int) $order['status'] === OrderManager::STATUS_DONE) {
                $pdo->commit();
                return true;
            }
            if ((int) $order['status'] !== OrderManager::STATUS_PENDING) {
                $pdo->rollBack();
                return false;
            }
            if ((int) $order['direct'] !== OrderManager::DIRECT_INC
                || (int) $order['kind'] !== OrderManager::KIND_RECHARGE) {
                $pdo->rollBack();
                return false;
            }

            // 码支付：禁止用回调 money 与本地订单 money 比对（见《码支付开发规范》第2.6节）。
            // 履约只认：验签通过 + 支付成功态 + out_trade_no 命中本站 orderno；积分以本地订单 amount 为准。

            $userId = (int) $order['userid'];
            $amount = (float) $order['amount'];
            $uStmt = $pdo->prepare(
                'SELECT `points` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $uStmt->execute(array($userId));
            $cur = $uStmt->fetchColumn();
            if ($cur === false) {
                $pdo->rollBack();
                return false;
            }
            $newBal = round((float) $cur + $amount, 4);
            $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `points` = ? WHERE `id` = ?'
            )->execute(array($newBal, $userId));

            $pdo->prepare(
                'UPDATE `' . OrderManager::table() . '`
                 SET `status` = ?, `balance` = ?, `tradeno` = IF(? = \'\', `tradeno`, ?),
                     `money` = IF(? = \'\' OR ? = \'0\', `money`, ?), `paytime` = NOW()
                 WHERE `id` = ?'
            )->execute(array(
                OrderManager::STATUS_DONE,
                $newBal,
                (string) $tradeno,
                (string) $tradeno,
                (string) $money,
                (string) $money,
                $money !== '' ? (float) $money : 0,
                (int) $order['id'],
            ));

            $pdo->commit();
            if (class_exists('PayPendingWatch')) {
                PayPendingWatch::untrack($orderno);
            }
            if (class_exists('RedisCache')) {
                RedisCache::invalidateOrders();
            }
            if (class_exists('MailNotify')) {
                try {
                    MailNotify::notifyRechargeSuccess($userId, $amount, $newBal, $orderno);
                } catch (Exception $e) {
                    // 发信失败不阻断充值履约
                }
                try {
                    $paidMoney = $money !== '' && is_numeric($money)
                        ? (float) $money
                        : (float) (isset($order['money']) ? $order['money'] : 0);
                    MailNotify::notifyAdminsOrderPaid($orderno, $userId, $paidMoney, $amount);
                } catch (Exception $e) {
                    // 发信失败不阻断充值履约
                }
            }
            return true;
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
            }
            return false;
        }
    }

    /**
     * @param string $orderno
     * @return bool
     */
    public static function cancelPending($orderno)
    {
        $orderno = trim((string) $orderno);
        if ($orderno === '') {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . OrderManager::table() . '` SET `status` = ? WHERE `orderno` = ? AND `status` = ?'
            );
            $stmt->execute(array(OrderManager::STATUS_CANCEL, $orderno, OrderManager::STATUS_PENDING));
            $ok = $stmt->rowCount() > 0;
            if (class_exists('PayPendingWatch')) {
                PayPendingWatch::untrack($orderno);
            }
            if ($ok && class_exists('RedisCache')) {
                RedisCache::invalidateOrders();
            }
            return $ok;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int   $userId
     * @param int   $direct
     * @param int   $kind
     * @param float $amount
     * @param array $extra
     * @return array
     */
    private static function change($userId, $direct, $kind, $amount, array $extra = array())
    {
        $userId = (int) $userId;
        $amount = round((float) $amount, 4);
        if ($userId <= 0 || $amount <= 0) {
            return array('ok' => false, 'msg' => '参数无效');
        }
        if (!self::hasPointsColumn() || !OrderManager::tableReady()) {
            return array('ok' => false, 'msg' => '积分系统未就绪');
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT `points` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(array($userId));
            $cur = $stmt->fetchColumn();
            if ($cur === false) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => '用户不存在');
            }
            $cur = (float) $cur;
            if ($direct === OrderManager::DIRECT_DEC) {
                if ($cur + 0.0000001 < $amount) {
                    $pdo->rollBack();
                    if (class_exists('MailNotify')) {
                        try {
                            MailNotify::notifyPointsInsufficient($userId, $cur, $amount);
                        } catch (Exception $e) {
                            // 发信失败不阻断主流程
                        }
                    }
                    return array('ok' => false, 'msg' => '积分余额不足');
                }
                $newBal = round($cur - $amount, 4);
            } else {
                $newBal = round($cur + $amount, 4);
            }

            if ($direct === OrderManager::DIRECT_DEC && self::hasSpentColumn()) {
                $pdo->prepare(
                    'UPDATE `' . Database::table('user') . '`
                     SET `points` = ?, `pointsspent` = `pointsspent` + ?
                     WHERE `id` = ?'
                )->execute(array($newBal, $amount, $userId));
            } else {
                $pdo->prepare(
                    'UPDATE `' . Database::table('user') . '` SET `points` = ? WHERE `id` = ?'
                )->execute(array($newBal, $userId));
            }

            $orderno = OrderManager::genOrderNo($direct === OrderManager::DIRECT_DEC ? 'DC' : 'IN');
            $ins = $pdo->prepare(
                'INSERT INTO `' . OrderManager::table() . '` (
                    `orderno`, `userid`, `direct`, `kind`, `amount`, `balance`, `money`,
                    `apiid`, `keyid`, `paytype`, `tradeno`, `status`, `remark`, `createtime`, `paytime`
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, \'\', \'\', ?, ?, NOW(), ?)'
            );
            $ins->execute(array(
                $orderno,
                $userId,
                (int) $direct,
                (int) $kind,
                $amount,
                $newBal,
                (int) (isset($extra['apiid']) ? $extra['apiid'] : 0),
                (int) (isset($extra['keyid']) ? $extra['keyid'] : 0),
                (int) (isset($extra['status']) ? $extra['status'] : OrderManager::STATUS_DONE),
                (string) (isset($extra['remark']) ? $extra['remark'] : ''),
                isset($extra['paytime']) ? $extra['paytime'] : null,
            ));

            // 密钥维度累计消耗：仅 API 扣费 / 调用退回且带 keyid（与账户 pointsspent 并存）
            $keyIdForSpent = (int) (isset($extra['keyid']) ? $extra['keyid'] : 0);
            if ($keyIdForSpent > 0 && class_exists('ApiKeyManager')
                && method_exists('ApiKeyManager', 'adjustPointsspent')) {
                if ($direct === OrderManager::DIRECT_DEC
                    && (int) $kind === OrderManager::KIND_API) {
                    ApiKeyManager::adjustPointsspent($keyIdForSpent, $amount, $pdo);
                } elseif ($direct === OrderManager::DIRECT_INC
                    && (int) $kind === OrderManager::KIND_REFUND) {
                    ApiKeyManager::adjustPointsspent($keyIdForSpent, -$amount, $pdo);
                }
            }

            $pdo->commit();
            if (self::$bulkOrdersDefer <= 0 && class_exists('RedisCache')) {
                RedisCache::invalidateOrders();
            }
            // 批量赠送时跳过逐用户 Redis 清理，避免上千次往返拖垮站点
            if (self::$bulkOrdersDefer <= 0
                && $direct === OrderManager::DIRECT_INC
                && class_exists('MailNotify')) {
                try {
                    MailNotify::clearInsufficientNoticeFlag($userId);
                } catch (Exception $e) {
                    // ignore
                }
            }
            if ($direct === OrderManager::DIRECT_DEC
                && $cur > 0.0000001
                && $newBal <= 0.0000001
                && class_exists('MailNotify')) {
                try {
                    MailNotify::notifyBalanceZero($userId, $newBal);
                } catch (Exception $e) {
                    // 发信失败不阻断积分主流程
                }
            }
            return array(
                'ok'      => true,
                'msg'     => 'ok',
                'balance' => $newBal,
                'orderno' => $orderno,
                'amount'  => $amount,
            );
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
            }
            return array('ok' => false, 'msg' => '积分变动失败');
        }
    }

    /**
     * 注册赠送积分（受系统设置开关控制；失败不抛）
     *
     * @param int $userId
     * @return array{ok:bool,msg:string,amount?:float}
     */
    public static function giftOnRegister($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '用户无效');
        }
        if (Config::get('register_gift_enabled', '0') !== '1') {
            return array('ok' => false, 'msg' => '未开启注册赠送');
        }
        $amount = (int) Config::get('register_gift_points', '0');
        if ($amount < 1) {
            return array('ok' => false, 'msg' => '赠送积分为 0');
        }
        if ($amount > 1000000) {
            $amount = 1000000;
        }
        return self::change(
            $userId,
            OrderManager::DIRECT_INC,
            OrderManager::KIND_REGISTER,
            (float) $amount,
            array(
                'remark'  => '注册赠送积分',
                'status'  => OrderManager::STATUS_DONE,
                'paytime' => date('Y-m-d H:i:s'),
            )
        );
    }

    /**
     * 每日签到流水备注（积分变动说明）
     *
     * @param string $via web=用户中心；api=系统级外部签到 API
     * @return string
     */
    public static function checkinRemark($via = 'web')
    {
        $via = strtolower(trim((string) $via));
        if ($via === 'api' || $via === 'system_api' || $via === 'sysapi') {
            return '每日签到奖励（API）';
        }
        return '每日签到奖励';
    }

    /**
     * 每日签到（随机区间积分；同用户同日仅一次）
     *
     * @param int    $userId
     * @param string $via    web=用户中心签到；api=系统外部 API 代签到（仅影响流水 remark）
     * @return array{ok:bool,msg:string,amount?:float,balance?:float}
     */
    public static function checkin($userId, $via = 'web')
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '请先登录');
        }
        if (Config::get('checkin_enabled', '0') !== '1') {
            return array('ok' => false, 'msg' => '签到功能未开启');
        }
        if (!class_exists('CheckinManager') || !CheckinManager::tableReady()) {
            return array('ok' => false, 'msg' => '签到功能尚未就绪，请先完成系统升级');
        }
        $min = (int) Config::get('checkin_points_min', '10');
        $max = (int) Config::get('checkin_points_max', '30');
        if ($min < 1) {
            $min = 1;
        }
        if ($max < $min) {
            $max = $min;
        }
        if ($max > 1000000) {
            $max = 1000000;
        }

        $amount = (float) (function_exists('random_int')
            ? random_int($min, $max)
            : mt_rand($min, $max));

        // 先占位 lastcheckin=今日，防止并发重复发放
        $saved = CheckinManager::record($userId, $amount);
        if ($saved !== true) {
            return array('ok' => false, 'msg' => is_string($saved) ? $saved : '今日已签到');
        }

        $result = self::change(
            $userId,
            OrderManager::DIRECT_INC,
            OrderManager::KIND_CHECKIN,
            $amount,
            array(
                'remark'  => self::checkinRemark($via),
                'status'  => OrderManager::STATUS_DONE,
                'paytime' => date('Y-m-d H:i:s'),
            )
        );
        if (empty($result['ok'])) {
            CheckinManager::deleteToday($userId);
            return $result;
        }
        $result['msg'] = '签到成功，获得 ' . PayConfig::fmtPoints($amount) . ' 积分';
        $result['amount'] = $amount;
        return $result;
    }
}
