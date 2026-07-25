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
    /** 增加：注册赠送 */
    const KIND_REGISTER = 2;
    /** 增加：每日签到 */
    const KIND_CHECKIN = 3;

    /** 减少：API 调用 */
    const KIND_API = 0;
    /** 减少：管理员扣款 */
    const KIND_ADMIN_SUB = 1;
    /** 减少：AI 调用（预留） */
    const KIND_AI = 2;

    const STATUS_PENDING = 0;
    const STATUS_DONE = 1;
    const STATUS_CANCEL = 2;

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
            if ($kind === self::KIND_REGISTER) {
                return '注册赠送';
            }
            if ($kind === self::KIND_CHECKIN) {
                return '每日签到';
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
     * 类型标签 CSS 修饰类
     *
     * @param int $direct
     * @param int $kind
     * @return string
     */
    public static function kindClass($direct, $kind)
    {
        $direct = (int) $direct;
        $kind = (int) $kind;
        if ($direct === self::DIRECT_INC) {
            if ($kind === self::KIND_ADMIN_ADD) {
                return 'is-admin-add';
            }
            if ($kind === self::KIND_REGISTER) {
                return 'is-register';
            }
            if ($kind === self::KIND_CHECKIN) {
                return 'is-checkin';
            }
            return 'is-recharge';
        }
        if ($kind === self::KIND_ADMIN_SUB) {
            return 'is-admin-sub';
        }
        if ($kind === self::KIND_AI) {
            return 'is-ai';
        }
        return 'is-api';
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
            'kind_class' => self::kindClass($direct, $kind),
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
     * 分页列表：每页条数 + keyset；附带筛选总数（短 TTL 缓存 COUNT，禁止深页 OFFSET）
     *
     * @param array $opts userid?, status?, scope(recharge|ledger)?, q?, pagesize, before_id
     * @return array{list:array,total:int,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $userid = isset($opts['userid']) ? (int) $opts['userid'] : 0;
        $status = array_key_exists('status', $opts) ? $opts['status'] : null;
        $scope = isset($opts['scope']) ? trim((string) $opts['scope']) : '';
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        if (function_exists('mb_substr')) {
            if (mb_strlen($q) > 64) {
                $q = mb_substr($q, 0, 64);
            }
        } elseif (strlen($q) > 64) {
            $q = substr($q, 0, 64);
        }
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => $page,
            'pagesize'       => $pagesize,
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

            $needLedgerJoins = ($scope === 'ledger');

            if ($q !== '') {
                $search = self::buildSearchFilter($q, $needLedgerJoins);
                if ($search['sql'] !== '') {
                    $where[] = $search['sql'];
                    foreach ($search['bind'] as $b) {
                        $bind[] = $b;
                    }
                }
            }

            $countWhere = $where;
            $countBind = $bind;
            if ($beforeId > 0) {
                $where[] = 'o.`id` < ?';
                $bind[] = $beforeId;
            }
            $sqlWhere = implode(' AND ', $where);

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

            $total = 0;
            try {
                $total = self::countFilteredCached(array(
                    'scope'  => $scope,
                    'userid' => $userid,
                    'status' => $status,
                    'q'      => $q,
                ), $countWhere, $countBind);
            } catch (Exception $e) {
                $total = count($list);
            }

            return array(
                'list'           => $list,
                'total'          => $total,
                'page'           => $page,
                'pagesize'       => $pagesize,
                'before_id'      => $beforeId,
                'next_before_id' => $nextBefore,
                'has_more'       => $hasMore,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 构建搜索条件（先解析用户/类型，再精确过滤，避免全表 LIKE 拉爆）
     *
     * @param string $q
     * @param bool   $includeApiName
     * @return array{sql:string,bind:array}
     */
    private static function buildSearchFilter($q, $includeApiName = false)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array('sql' => '', 'bind' => array());
        }

        $kinds = self::kindSearchPairs($q);
        $kindPrimary = self::isPrimaryKindQuery($q, $kinds);
        $userIds = $kindPrimary ? array() : self::resolveSearchUserIds($q);
        $apiIds = ($includeApiName && !$kindPrimary) ? self::resolveSearchApiIds($q) : array();

        $or = array();
        $bind = array();

        // 类型关键词：走 (direct,kind) 复合索引，禁止再叠全表 LIKE
        if ($kinds !== array()) {
            foreach ($kinds as $pair) {
                $or[] = '(o.`direct` = ? AND o.`kind` = ?)';
                $bind[] = (int) $pair[0];
                $bind[] = (int) $pair[1];
            }
            if ($kindPrimary) {
                return array(
                    'sql'  => '(' . implode(' OR ', $or) . ')',
                    'bind' => $bind,
                );
            }
        }

        if ($userIds !== array()) {
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $or[] = 'o.`userid` IN (' . $ph . ')';
            foreach ($userIds as $uid) {
                $bind[] = (int) $uid;
            }
        } elseif (ctype_digit($q)) {
            $or[] = 'o.`userid` = ?';
            $bind[] = (int) $q;
        }

        if ($apiIds !== array()) {
            $ph = implode(',', array_fill(0, count($apiIds), '?'));
            $or[] = 'o.`apiid` IN (' . $ph . ')';
            foreach ($apiIds as $aid) {
                $bind[] = (int) $aid;
            }
        }

        // 订单号 / 平台单号：等值 + 前缀（可用索引），禁止 '%xxx%'
        if (preg_match('/^[A-Za-z0-9_-]{4,64}$/', $q)) {
            $or[] = 'o.`orderno` = ?';
            $bind[] = $q;
            $or[] = 'o.`orderno` LIKE ?';
            $bind[] = $q . '%';
            $or[] = 'o.`tradeno` = ?';
            $bind[] = $q;
            $or[] = 'o.`tradeno` LIKE ?';
            $bind[] = $q . '%';
        }

        // 说明：仅在未命中用户/类型/单号时作为兜底（短词），降低全表扫描概率
        $qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
        if ($or === array() && $qLen >= 2 && $qLen <= 32) {
            $or[] = 'o.`remark` LIKE ?';
            $bind[] = '%' . $q . '%';
        } elseif ($userIds === array() && $kinds === array() && $apiIds === array() && $qLen >= 2 && $qLen <= 32) {
            // 有单号形态但仍要覆盖备注中文（如只搜备注片段）
            if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $q)) {
                $or[] = 'o.`remark` LIKE ?';
                $bind[] = '%' . $q . '%';
            }
        }

        if ($or === array()) {
            return array('sql' => '0 = 1', 'bind' => array());
        }

        return array(
            'sql'  => '(' . implode(' OR ', $or) . ')',
            'bind' => $bind,
        );
    }

    /**
     * 查询词是否主要为类型文案（如「注册赠送」「每日签到」）
     *
     * @param string $q
     * @param array  $kinds
     * @return bool
     */
    private static function isPrimaryKindQuery($q, array $kinds)
    {
        if ($kinds === array()) {
            return false;
        }
        $q = trim((string) $q);
        $labels = array(
            '充值', '用户充值', '加款', '管理员加款', '注册', '赠送', '注册赠送', '注册赠送积分',
            '签到', '每日签到', 'API调用', '接口调用', '调用接口', '扣款', '管理员扣款', 'AI调用',
        );
        foreach ($labels as $label) {
            if ($q === $label) {
                return true;
            }
        }
        if (!function_exists('mb_strpos')) {
            return false;
        }
        foreach (array('注册赠送', '每日签到', '管理员加款', '管理员扣款', '用户充值', 'API调用', 'AI调用', '接口调用') as $strong) {
            if (mb_strpos($q, $strong) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 先查用户表拿 ID（走唯一索引/前缀），再过滤 orders.userid
     *
     * @param string $q
     * @return int[]
     */
    private static function resolveSearchUserIds($q)
    {
        $q = trim((string) $q);
        if ($q === '' || ctype_digit($q)) {
            return array();
        }
        static $memo = array();
        if (isset($memo[$q])) {
            return $memo[$q];
        }
        $ids = array();
        try {
            $pdo = Database::connect();
            self::applyQueryTimeout($pdo);
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '` WHERE `username` = ? OR `email` = ? LIMIT 30'
            );
            $stmt->execute(array($q, $q));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int) $id;
            }
            if ($ids === array()) {
                $prefix = $q . '%';
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . $table . '` WHERE `username` LIKE ? OR `email` LIKE ? ORDER BY `id` DESC LIMIT 50'
                );
                $stmt->execute(array($prefix, $prefix));
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $ids[] = (int) $id;
                }
            }
            if ($ids === array()) {
                $qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
                if ($qLen >= 2 && $qLen <= 64) {
                    $fuzzy = '%' . $q . '%';
                    $stmt = $pdo->prepare(
                        'SELECT `id` FROM `' . $table . '` WHERE `username` LIKE ? OR `email` LIKE ? ORDER BY `id` DESC LIMIT 50'
                    );
                    $stmt->execute(array($fuzzy, $fuzzy));
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                        $ids[] = (int) $id;
                    }
                }
            }
        } catch (Exception $e) {
            $ids = array();
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return $memo[$q] = $ids;
    }

    /**
     * 先查接口名拿 ID，再过滤 orders.apiid
     *
     * @param string $q
     * @return int[]
     */
    private static function resolveSearchApiIds($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array();
        }
        static $memo = array();
        if (isset($memo[$q])) {
            return $memo[$q];
        }
        $ids = array();
        try {
            $pdo = Database::connect();
            self::applyQueryTimeout($pdo);
            $table = Database::table('api');
            $prefix = $q . '%';
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '` WHERE `name` = ? OR `name` LIKE ? ORDER BY `id` DESC LIMIT 50'
            );
            $stmt->execute(array($q, $prefix));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int) $id;
            }
            if ($ids === array()) {
                $qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
                if ($qLen >= 2 && $qLen <= 64) {
                    $fuzzy = '%' . $q . '%';
                    $stmt = $pdo->prepare(
                        'SELECT `id` FROM `' . $table . '` WHERE `name` LIKE ? ORDER BY `id` DESC LIMIT 50'
                    );
                    $stmt->execute(array($fuzzy));
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                        $ids[] = (int) $id;
                    }
                }
            }
        } catch (Exception $e) {
            $ids = array();
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return $memo[$q] = $ids;
    }

    /**
     * 按类型文案关键词匹配 direct+kind（如「注册」「注册赠送」「签到」）
     *
     * @param string $q
     * @return array<int,array{0:int,1:int}>
     */
    private static function kindSearchPairs($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array();
        }
        $map = array(
            array(self::DIRECT_INC, self::KIND_RECHARGE, array('充值', '用户充值')),
            array(self::DIRECT_INC, self::KIND_ADMIN_ADD, array('加款', '管理员加款')),
            array(self::DIRECT_INC, self::KIND_REGISTER, array('注册', '赠送', '注册赠送', '注册赠送积分')),
            array(self::DIRECT_INC, self::KIND_CHECKIN, array('签到', '每日签到')),
            array(self::DIRECT_DEC, self::KIND_API, array('API调用', '接口调用', '调用接口')),
            array(self::DIRECT_DEC, self::KIND_ADMIN_SUB, array('扣款', '管理员扣款')),
            array(self::DIRECT_DEC, self::KIND_AI, array('AI调用')),
        );
        $out = array();
        $seen = array();
        foreach ($map as $row) {
            $hit = false;
            foreach ($row[2] as $label) {
                if ($q === $label) {
                    $hit = true;
                    break;
                }
                // 仅允许「查询词包含完整类型文案」，禁止「用户」误匹配「用户充值」导致种类 OR 扫爆大表
                if (function_exists('mb_strpos')) {
                    if ($label !== '' && mb_strpos($q, $label) !== false) {
                        $hit = true;
                        break;
                    }
                } elseif ($label !== '' && strpos($q, $label) !== false) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit && (strcasecmp($q, 'api') === 0 || strcasecmp($q, 'ai') === 0)) {
                if ($row[1] === self::KIND_API && strcasecmp($q, 'api') === 0 && $row[0] === self::DIRECT_DEC) {
                    $hit = true;
                }
                if ($row[1] === self::KIND_AI && strcasecmp($q, 'ai') === 0 && $row[0] === self::DIRECT_DEC) {
                    $hit = true;
                }
            }
            if (!$hit) {
                continue;
            }
            $key = $row[0] . ':' . $row[1];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = array($row[0], $row[1]);
        }
        return $out;
    }

    /**
     * 当前筛选条件下总数（短 TTL 缓存；不含 before_id；WHERE 已用 EXISTS 时无需 JOIN）
     *
     * @param array $cacheOpts
     * @param array $where
     * @param array $bind
     * @return int
     */
    private static function countFilteredCached(array $cacheOpts, array $where, array $bind)
    {
        $factory = function () use ($where, $bind) {
            try {
                $pdo = Database::connect();
                self::applyQueryTimeout($pdo);
                $sql = 'SELECT COUNT(*) FROM `' . self::table() . '` o WHERE ' . implode(' AND ', $where);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                return max(0, (int) $stmt->fetchColumn());
            } catch (Exception $e) {
                return 0;
            }
        };

        if (class_exists('RedisCache')) {
            return (int) RedisCache::remember(
                RedisCache::ordersRangeTotalKey($cacheOpts),
                RedisCache::TTL_ORDERS_RANGE_TOTAL,
                $factory
            );
        }
        return (int) call_user_func($factory);
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
