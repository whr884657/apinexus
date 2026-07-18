<?php
/**
 * 文件：core/OrderManager.php
 * 作用：积分变动与支付订单（表 orders）
 */

class OrderManager
{
    const DIRECT_DEC = 0;
    const DIRECT_INC = 1;

    /** 增加：用户充值 */
    const KIND_RECHARGE = 0;
    /** 增加：管理员加款 */
    const KIND_ADMIN_ADD = 1;

    /** 减少：API 调用 */
    const KIND_API = 0;
    /** 减少：管理员扣款 */
    const KIND_ADMIN_SUB = 1;
    /** 减少：AI 调用（预留） */
    const KIND_AI = 2;

    const STATUS_PENDING = 0;
    const STATUS_DONE = 1;
    const STATUS_CANCEL = 2;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('orders');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            $ready = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * @return string
     */
    public static function genOrderNo($prefix = 'PO')
    {
        return $prefix . date('YmdHis') . sprintf('%04d', mt_rand(0, 9999));
    }

    /**
     * @param int $direct
     * @param int $kind
     * @return string
     */
    public static function kindLabel($direct, $kind)
    {
        $direct = (int) $direct;
        $kind = (int) $kind;
        if ($direct === self::DIRECT_INC) {
            if ($kind === self::KIND_ADMIN_ADD) {
                return '管理员加款';
            }
            return '用户充值';
        }
        if ($kind === self::KIND_ADMIN_SUB) {
            return '管理员扣款';
        }
        if ($kind === self::KIND_AI) {
            return 'AI 调用';
        }
        return 'API 调用';
    }

    /**
     * @param int $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_PENDING => '待支付',
            self::STATUS_DONE    => '已完成',
            self::STATUS_CANCEL  => '已取消',
        );
        $status = (int) $status;
        return isset($map[$status]) ? $map[$status] : '未知';
    }

    /**
     * @param array $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row)) {
            return null;
        }
        $direct = (int) $row['direct'];
        $kind = (int) $row['kind'];
        return array(
            'id'         => (int) $row['id'],
            'orderno'    => (string) $row['orderno'],
            'userid'     => (int) $row['userid'],
            'username'   => isset($row['username']) ? (string) $row['username'] : '',
            'direct'     => $direct,
            'kind'       => $kind,
            'kind_label' => self::kindLabel($direct, $kind),
            'amount'     => PayConfig::fmtPoints(isset($row['amount']) ? $row['amount'] : 0),
            'balance'    => PayConfig::fmtPoints(isset($row['balance']) ? $row['balance'] : 0),
            'money'      => number_format((float) (isset($row['money']) ? $row['money'] : 0), 2, '.', ''),
            'apiid'      => (int) (isset($row['apiid']) ? $row['apiid'] : 0),
            'apiname'    => isset($row['apiname']) ? (string) $row['apiname'] : '',
            'keyid'      => (int) (isset($row['keyid']) ? $row['keyid'] : 0),
            'keymask'    => isset($row['keymask']) ? (string) $row['keymask'] : '',
            'paytype'    => (string) (isset($row['paytype']) ? $row['paytype'] : ''),
            'pay_label'  => PayConfig::methodLabel(isset($row['paytype']) ? $row['paytype'] : ''),
            'tradeno'    => (string) (isset($row['tradeno']) ? $row['tradeno'] : ''),
            'status'       => (int) $row['status'],
            'status_label' => self::statusLabel(isset($row['status']) ? $row['status'] : 0),
            'status_class' => self::statusClass(isset($row['status']) ? $row['status'] : 0),
            'remark'       => (string) (isset($row['remark']) ? $row['remark'] : ''),
            'createtime'   => (string) (isset($row['createtime']) ? $row['createtime'] : ''),
            'paytime'      => (string) (isset($row['paytime']) ? $row['paytime'] : ''),
        );
    }

    /**
     * @param int $status
     * @return string
     */
    public static function statusClass($status)
    {
        $status = (int) $status;
        if ($status === self::STATUS_DONE) {
            return 'is-done';
        }
        if ($status === self::STATUS_CANCEL) {
            return 'is-cancel';
        }
        return 'is-pending';
    }

    /**
     * @param string $orderno
     * @return array|null
     */
    public static function findByOrderNo($orderno)
    {
        if (!self::tableReady()) {
            return null;
        }
        $orderno = trim((string) $orderno);
        if ($orderno === '') {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `orderno` = ? LIMIT 1');
            $stmt->execute(array($orderno));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array $data
     * @return int|false 新订单 ID
     */
    public static function insert(array $data)
    {
        if (!self::tableReady()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (
                    `orderno`, `userid`, `direct`, `kind`, `amount`, `balance`, `money`,
                    `apiid`, `keyid`, `paytype`, `tradeno`, `status`, `remark`, `createtime`, `paytime`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
            );
            $stmt->execute(array(
                (string) $data['orderno'],
                (int) $data['userid'],
                (int) $data['direct'],
                (int) $data['kind'],
                (float) $data['amount'],
                (float) $data['balance'],
                (float) (isset($data['money']) ? $data['money'] : 0),
                (int) (isset($data['apiid']) ? $data['apiid'] : 0),
                (int) (isset($data['keyid']) ? $data['keyid'] : 0),
                (string) (isset($data['paytype']) ? $data['paytype'] : ''),
                (string) (isset($data['tradeno']) ? $data['tradeno'] : ''),
                (int) (isset($data['status']) ? $data['status'] : self::STATUS_DONE),
                (string) (isset($data['remark']) ? $data['remark'] : ''),
                isset($data['paytime']) ? $data['paytime'] : null,
            ));
            return (int) $pdo->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param array $opts userid?, status?, scope(recharge|ledger)?, page, pagesize
     * @return array{list:array,total:int,page:int,pagesize:int}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $userid = isset($opts['userid']) ? (int) $opts['userid'] : 0;
        $status = array_key_exists('status', $opts) ? $opts['status'] : null;
        $scope = isset($opts['scope']) ? trim((string) $opts['scope']) : '';

        $empty = array('list' => array(), 'total' => 0, 'page' => $page, 'pagesize' => $pagesize);
        if (!self::tableReady()) {
            return $empty;
        }

        try {
            $pdo = Database::connect();
            $where = array('1=1');
            $bind = array();
            if ($userid > 0) {
                $where[] = 'o.`userid` = ?';
                $bind[] = $userid;
            }
            if ($scope === 'recharge') {
                $where[] = 'o.`direct` = ? AND o.`kind` = ?';
                $bind[] = self::DIRECT_INC;
                $bind[] = self::KIND_RECHARGE;
            } elseif ($scope === 'ledger') {
                $where[] = 'o.`status` = ?';
                $bind[] = self::STATUS_DONE;
            }
            if ($status !== null && $status !== '') {
                $where[] = 'o.`status` = ?';
                $bind[] = (int) $status;
            }
            $sqlWhere = implode(' AND ', $where);

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . self::table() . '` o WHERE ' . $sqlWhere);
            $stmt->execute($bind);
            $total = (int) $stmt->fetchColumn();

            $offset = ($page - 1) * $pagesize;
            $sql = 'SELECT o.*, u.`username`, a.`name` AS apiname, k.`secret` AS keysecret
                    FROM `' . self::table() . '` o
                    LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = o.`userid`
                    LEFT JOIN `' . Database::table('api') . '` a ON a.`id` = o.`apiid`
                    LEFT JOIN `' . Database::table('apikey') . '` k ON k.`id` = o.`keyid`
                    WHERE ' . $sqlWhere . '
                    ORDER BY o.`id` DESC
                    LIMIT ' . (int) $pagesize . ' OFFSET ' . (int) $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            foreach ($rows as $row) {
                if (!empty($row['keysecret'])) {
                    $sec = (string) $row['keysecret'];
                    $row['keymask'] = strlen($sec) > 10
                        ? (substr($sec, 0, 6) . '****' . substr($sec, -4))
                        : '****';
                }
                $list[] = self::formatRow($row);
            }
            return array('list' => $list, 'total' => $total, 'page' => $page, 'pagesize' => $pagesize);
        } catch (Exception $e) {
            return $empty;
        }
    }
}
