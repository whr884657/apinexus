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

    /** 后台/用户中心列表默认时间窗（天） */
    const DEFAULT_QUERY_DAYS = 30;
    const MAX_QUERY_DAYS = 365;
    const QUERY_TIMEOUT_MS = 5000;

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
            $newId = (int) $pdo->lastInsertId();
            if (class_exists('RedisCache')) {
                RedisCache::invalidateOrders();
            }
            return $newId;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int $days
     * @return int
     */
    public static function clampQueryDays($days)
    {
        $days = (int) $days;
        if ($days < 1) {
            $days = self::DEFAULT_QUERY_DAYS;
        }
        if ($days > self::MAX_QUERY_DAYS) {
            $days = self::MAX_QUERY_DAYS;
        }
        return $days;
    }

    /**
     * 分页列表：强制时间窗 + COUNT 无 JOIN + keyset（before_id），避免深页 OFFSET / 全表扫
     *
     * @param array $opts userid?, status?, scope(recharge|ledger)?, pagesize, days, before_id
     * @return array{list:array,total:int,page:int,pagesize:int,days:int,before_id:int,next_before_id:int,has_more:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $userid = isset($opts['userid']) ? (int) $opts['userid'] : 0;
        $status = array_key_exists('status', $opts) ? $opts['status'] : null;
        $scope = isset($opts['scope']) ? trim((string) $opts['scope']) : '';
        $days = isset($opts['days']) ? self::clampQueryDays((int) $opts['days']) : self::DEFAULT_QUERY_DAYS;
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => $page,
            'pagesize'       => $pagesize,
            'days'           => $days,
            'before_id'      => $beforeId,
            'next_before_id' => 0,
            'has_more'       => false,
        );
        if (!self::tableReady()) {
            return $empty;
        }

        try {
            $pdo = Database::connect();
            self::applyQueryTimeout($pdo);

            $where = array('o.`createtime` >= DATE_SUB(NOW(), INTERVAL ? DAY)');
            $bind = array($days);

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
            if ($beforeId > 0) {
                $where[] = 'o.`id` < ?';
                $bind[] = $beforeId;
            }
            $sqlWhere = implode(' AND ', $where);

            $countBind = $bind;
            $countWhere = $sqlWhere;
            if ($beforeId > 0) {
                // 总数按时间窗+业务条件统计，不含 before_id
                $countWhereParts = array();
                $countBind = array();
                $countWhereParts[] = 'o.`createtime` >= DATE_SUB(NOW(), INTERVAL ? DAY)';
                $countBind[] = $days;
                if ($userid > 0) {
                    $countWhereParts[] = 'o.`userid` = ?';
                    $countBind[] = $userid;
                }
                if ($scope === 'recharge') {
                    $countWhereParts[] = 'o.`direct` = ? AND o.`kind` = ?';
                    $countBind[] = self::DIRECT_INC;
                    $countBind[] = self::KIND_RECHARGE;
                } elseif ($scope === 'ledger') {
                    $countWhereParts[] = 'o.`status` = ?';
                    $countBind[] = self::STATUS_DONE;
                }
                if ($status !== null && $status !== '') {
                    $countWhereParts[] = 'o.`status` = ?';
                    $countBind[] = (int) $status;
                }
                $countWhere = implode(' AND ', $countWhereParts);
            }

            $countFactory = function () use ($pdo, $countWhere, $countBind) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . self::table() . '` o WHERE ' . $countWhere);
                $stmt->execute($countBind);
                return (int) $stmt->fetchColumn();
            };
            if (class_exists('RedisCache')) {
                $total = (int) RedisCache::remember(
                    RedisCache::ordersRangeTotalKey(array(
                        'scope'  => $scope,
                        'userid' => $userid,
                        'status' => $status,
                        'days'   => $days,
                    )),
                    RedisCache::TTL_ORDERS_RANGE_TOTAL,
                    $countFactory
                );
            } else {
                $total = (int) call_user_func($countFactory);
            }

            $needLedgerJoins = ($scope === 'ledger');
            $select = 'SELECT o.*, u.`username`';
            $from = '`' . self::table() . '` o LEFT JOIN `' . Database::table('user') . '` u ON u.`id` = o.`userid`';
            if ($needLedgerJoins) {
                $select .= ', a.`name` AS apiname, k.`secret` AS keysecret';
                $from .= ' LEFT JOIN `' . Database::table('api') . '` a ON a.`id` = o.`apiid`'
                    . ' LEFT JOIN `' . Database::table('apikey') . '` k ON k.`id` = o.`keyid`';
            } else {
                $select .= ', \'\' AS apiname, \'\' AS keysecret';
            }

            $sql = $select . ' FROM ' . $from
                . ' WHERE ' . $sqlWhere
                . ' ORDER BY o.`id` DESC'
                . ' LIMIT ' . ((int) $pagesize + 1);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hasMore = count($rows) > $pagesize;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $pagesize);
            }

            $list = array();
            foreach ($rows as $row) {
                if (!empty($row['keysecret'])) {
                    $sec = (string) $row['keysecret'];
                    if ($userid > 0) {
                        $row['keymask'] = strlen($sec) > 10
                            ? (substr($sec, 0, 6) . '****' . substr($sec, -4))
                            : '****';
                    } else {
                        $row['keymask'] = $sec;
                    }
                }
                $item = self::formatRow($row);
                if ($item !== null) {
                    $list[] = $item;
                }
            }

            $nextBefore = 0;
            if (!empty($list)) {
                $last = $list[count($list) - 1];
                $nextBefore = isset($last['id']) ? (int) $last['id'] : 0;
            }

            return array(
                'list'           => $list,
                'total'          => $total,
                'page'           => $page,
                'pagesize'       => $pagesize,
                'days'           => $days,
                'before_id'      => $beforeId,
                'next_before_id' => $nextBefore,
                'has_more'       => $hasMore,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param PDO $pdo
     * @return void
     */
    private static function applyQueryTimeout(PDO $pdo)
    {
        try {
            $pdo->exec('SET SESSION MAX_EXECUTION_TIME=' . (int) self::QUERY_TIMEOUT_MS);
        } catch (Exception $e) {
            // MySQL 5.7 / 不支持时忽略
        }
    }
}
