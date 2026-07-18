<?php
/**
 * 文件：core/PointsManager.php
 * 作用：用户积分余额增减与充值履约
 */

class PointsManager
{
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
     * 管理员调整积分（可增可减）
     *
     * @param int    $userId
     * @param float  $delta 正数加款，负数扣款
     * @param string $remark
     * @return array{ok:bool,msg:string,balance?:float}
     */
    public static function adminAdjust($userId, $delta, $remark = '')
    {
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
            $points = round($moneyVal * PayConfig::rate(), 4);
            $pkgName = '自定义充值';
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

        return array(
            'ok'   => true,
            'msg'  => '请扫码支付',
            'data' => array(
                'orderno'    => $orderno,
                'money'      => $displayMoney,
                'points'     => PayConfig::fmtPoints($points),
                'paytype'    => $payType,
                'pay_label'  => PayConfig::methodLabel($payType),
                'qrcode'     => $data['qrcode'],
                'payurl'     => $data['payurl'],
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
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . OrderManager::table() . '` SET `status` = ? WHERE `orderno` = ? AND `status` = ?'
            );
            $stmt->execute(array(OrderManager::STATUS_CANCEL, $orderno, OrderManager::STATUS_PENDING));
            return $stmt->rowCount() > 0;
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
                    return array('ok' => false, 'msg' => '积分余额不足');
                }
                $newBal = round($cur - $amount, 4);
            } else {
                $newBal = round($cur + $amount, 4);
            }

            $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `points` = ? WHERE `id` = ?'
            )->execute(array($newBal, $userId));

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

            $pdo->commit();
            return array(
                'ok'      => true,
                'msg'     => 'ok',
                'balance' => $newBal,
                'orderno' => $orderno,
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
}
