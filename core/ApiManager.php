<?php
/**
 * 文件：core/ApiManager.php
 * 作用：API 接口数据管理（后台接口列表 CRUD、用户投稿）
 *
 * 接口状态 status：0 正常 / 1 禁用（前台不展示）/ 2 维护（前台可见但不可请求）
 * 审核状态 audit：0 待审核 / 1 通过 / 2 不通过（管理员发布默认通过；用户投稿为待审核）
 * 接口类型 apitype：0 本地路径 / 1 代理外链（302 至 targeturl）
 */

class ApiManager
{
    /** 接口状态：正常 */
    const STATUS_NORMAL = 0;
    /** 接口状态：禁用 */
    const STATUS_DISABLED = 1;
    /** 接口状态：维护 */
    const STATUS_MAINTENANCE = 2;

    /** 审核：待审核 */
    const AUDIT_PENDING = 0;
    /** 审核：通过 */
    const AUDIT_APPROVED = 1;
    /** 审核：不通过 */
    const AUDIT_REJECTED = 2;

    /** 接口类型：本地路径 */
    const APITYPE_LOCAL = 0;
    /** 接口类型：代理外链 */
    const APITYPE_PROXY = 1;

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    /** 密钥要求：不需要 */
    const KEY_NONE = 0;
    /** 密钥要求：必须 */
    const KEY_REQUIRED = 1;
    /** 密钥要求：可选 */
    const KEY_OPTIONAL = 2;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('api');
    }

    /**
     * 表是否具备新结构字段
     *
     * @return bool
     */
    public static function tableReady()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            if (!$stmt || !$stmt->fetchColumn()) {
                return false;
            }
            $col = $pdo->query('SHOW COLUMNS FROM `' . self::table() . '` LIKE ' . $pdo->quote('aidoc'));
            return $col && $col->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 是否已具备审核字段（迁移 3.8.0 后为 true）
     *
     * @return bool
     */
    public static function hasAuditColumn()
    {
        try {
            $pdo = Database::connect();
            $col = $pdo->query('SHOW COLUMNS FROM `' . self::table() . '` LIKE ' . $pdo->quote('audit'));
            return $col && $col->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 是否已具备审核原因字段（迁移 3.10.0 后为 true）
     *
     * @return bool
     */
    public static function hasRejectReasonColumn()
    {
        try {
            $pdo = Database::connect();
            $col = $pdo->query('SHOW COLUMNS FROM `' . self::table() . '` LIKE ' . $pdo->quote('rejectreason'));
            return $col && $col->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 是否已具备代理相关字段（迁移 3.11.0 后为 true）
     *
     * @return bool
     */
    public static function hasProxyColumns()
    {
        try {
            $pdo = Database::connect();
            $col = $pdo->query('SHOW COLUMNS FROM `' . self::table() . '` LIKE ' . $pdo->quote('proxyslug'));
            return $col && $col->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 前台可见接口：审核通过 + 非禁用（含维护中）
     *
     * @return array
     */
    public static function listPublic()
    {
        return RedisCache::remember(
            RedisCache::KEY_API_PUBLIC,
            RedisCache::TTL_API_PUBLIC,
            function () {
                return self::listFiltered(array(
                    'status_in' => array(self::STATUS_NORMAL, self::STATUS_MAINTENANCE),
                    'audit'     => self::AUDIT_APPROVED,
                ));
            }
        );
    }

    /**
     * 前台公开接口数量（审核通过且不含禁用）
     *
     * @return int
     */
    public static function countPublic()
    {
        try {
            $pdo = Database::connect();
            $sql = 'SELECT COUNT(*) FROM `' . self::table() . '`
                    WHERE `status` IN (' . (int) self::STATUS_NORMAL . ', ' . (int) self::STATUS_MAINTENANCE . ')';
            if (self::hasAuditColumn()) {
                $sql .= ' AND `audit` = ' . (int) self::AUDIT_APPROVED;
            }
            $stmt = $pdo->query($sql);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @deprecated 使用 countPublic()；保留别名兼容主题调用
     * @return int
     */
    public static function countApproved()
    {
        return self::countPublic();
    }

    /**
     * 全站累计调用次数（各接口 calls 之和）
     *
     * @return int
     */
    public static function totalCallCount()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SELECT COALESCE(SUM(`calls`), 0) FROM `' . self::table() . '`');
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            if (!class_exists('Config')) {
                return 0;
            }
            try {
                return max(0, (int) Config::get('api_total_calls', 0));
            } catch (Exception $e2) {
                return 0;
            }
        }
    }

    /**
     * 从接口列表提取去重后的分类名
     *
     * @param array $apis
     * @return array
     */
    public static function categoriesFromList(array $apis)
    {
        $cats = array();
        foreach ($apis as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) (isset($row['category']) ? $row['category'] : ''));
            if ($name !== '' && !in_array($name, $cats, true)) {
                $cats[] = $name;
            }
        }
        sort($cats, SORT_STRING);
        return $cats;
    }

    /**
     * @param int|string|array|null $status 单个状态、状态数组，或 null 表示全部
     * @return array
     */
    public static function listAll($status = null)
    {
        $opts = array();
        if (is_array($status)) {
            $opts['status_in'] = $status;
        } elseif ($status !== null && $status !== '') {
            $opts['status'] = $status;
        }
        return self::listFiltered($opts);
    }

    /**
     * 按审核状态列出（供接口审核页）
     *
     * @param int|null $auditStatus null=全部
     * @return array
     */
    public static function listByAudit($auditStatus = null)
    {
        $opts = array('user_submitted' => true);
        if ($auditStatus !== null && $auditStatus !== '') {
            $opts['audit'] = $auditStatus;
        }
        return self::listFiltered($opts);
    }

    /**
     * 审核页列表：仅开发者投稿，不含管理员后台直接发布
     *
     * @return array
     */
    public static function listForReview()
    {
        return self::listFiltered(array('user_submitted' => true));
    }

    /**
     * 某用户投稿的接口列表
     *
     * @param int $userId
     * @return array
     */
    public static function listByUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array();
        }
        return self::listFiltered(array('userid' => $userId));
    }

    /**
     * @param array $opts status|status_in|audit|userid
     * @return array
     */
    public static function listFiltered(array $opts = array())
    {
        if (!self::tableReady()) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.*, u.`username`, u.`email`
                    FROM `' . self::table() . '` AS a
                    LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`userid`';
            $where = array();
            $params = array();

            if (isset($opts['status_in']) && is_array($opts['status_in'])) {
                $statuses = array();
                foreach ($opts['status_in'] as $s) {
                    $n = self::normalizeStatus($s);
                    if (self::isValidStatus($n)) {
                        $statuses[] = $n;
                    }
                }
                if (count($statuses) > 0) {
                    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                    $where[] = 'a.`status` IN (' . $placeholders . ')';
                    foreach ($statuses as $s) {
                        $params[] = $s;
                    }
                }
            } elseif (isset($opts['status']) && $opts['status'] !== null && $opts['status'] !== '') {
                $n = self::normalizeStatus($opts['status']);
                if (self::isValidStatus($n)) {
                    $where[] = 'a.`status` = ?';
                    $params[] = $n;
                }
            }

            if (isset($opts['audit']) && $opts['audit'] !== null && $opts['audit'] !== '' && self::hasAuditColumn()) {
                $a = self::normalizeAuditStatus($opts['audit']);
                if (self::isValidAuditStatus($a)) {
                    $where[] = 'a.`audit` = ?';
                    $params[] = $a;
                }
            }

            if (isset($opts['userid']) && (int) $opts['userid'] > 0) {
                $where[] = 'a.`userid` = ?';
                $params[] = (int) $opts['userid'];
            } elseif (!empty($opts['user_submitted'])) {
                // 仅开发者投稿（userid>0）；管理员在「接口列表」发布的不进审核页
                $where[] = 'a.`userid` > 0';
            }

            if (count($where) > 0) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= ' ORDER BY a.`id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param int $apiId
     * @return array|null
     */
    public static function findById($apiId)
    {
        $apiId = (int) $apiId;
        if ($apiId <= 0 || !self::tableReady()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT a.*, u.`username`, u.`email`
                 FROM `' . self::table() . '` AS a
                 LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`userid`
                 WHERE a.`id` = ? LIMIT 1'
            );
            $stmt->execute(array($apiId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 创建接口
     *
     * @param array $data
     * @return array|string 成功返回格式化行，失败返回错误文案
     */
    public static function create(array $data)
    {
        if (!self::tableReady()) {
            return '接口数据表未就绪，请先执行数据库结构更新';
        }

        $parsed = self::normalizePayload($data, 0);
        if (!is_array($parsed)) {
            return $parsed;
        }

        $userId = isset($data['userid']) ? (int) $data['userid'] : 0;
        if ($userId < 0) {
            $userId = 0;
        }

        // 默认审核通过（管理员发布）；用户投稿须显式传入待审核
        $auditStatus = self::AUDIT_APPROVED;
        if (array_key_exists('audit', $data)) {
            $auditStatus = self::normalizeAuditStatus($data['audit']);
            if (!self::isValidAuditStatus($auditStatus)) {
                return '无效的审核状态';
            }
        }
        $rejectReason = '';
        if ($auditStatus === self::AUDIT_REJECTED && array_key_exists('rejectreason', $data)) {
            $rejectReason = self::normalizeRejectReason($data['rejectreason']);
        }

        try {
            $pdo = Database::connect();
            if (self::hasProxyColumns() && self::hasAuditColumn() && self::hasRejectReasonColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                     (`name`, `description`, `endpoint`, `apitype`, `targeturl`, `proxyslug`, `method`, `params`, `response`,
                      `doc`, `aidoc`, `calls`, `needkey`, `status`, `audit`, `rejectreason`, `icon`, `category`, `userid`, `createtime`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['apitype'],
                    $parsed['targeturl'],
                    $parsed['proxyslug'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $rejectReason,
                    $parsed['icon'],
                    $parsed['category'],
                    $userId,
                ));
            } elseif (self::hasAuditColumn() && self::hasRejectReasonColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                     (`name`, `description`, `endpoint`, `method`, `params`, `response`,
                      `doc`, `aidoc`, `calls`, `needkey`, `status`, `audit`, `rejectreason`, `icon`, `category`, `userid`, `createtime`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $rejectReason,
                    $parsed['icon'],
                    $parsed['category'],
                    $userId,
                ));
            } elseif (self::hasAuditColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                     (`name`, `description`, `endpoint`, `method`, `params`, `response`,
                      `doc`, `aidoc`, `calls`, `needkey`, `status`, `audit`, `icon`, `category`, `userid`, `createtime`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $parsed['icon'],
                    $parsed['category'],
                    $userId,
                ));
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                     (`name`, `description`, `endpoint`, `method`, `params`, `response`,
                      `doc`, `aidoc`, `calls`, `needkey`, `status`, `icon`, `category`, `userid`, `createtime`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $parsed['icon'],
                    $parsed['category'],
                    $userId,
                ));
            }
            $id = (int) $pdo->lastInsertId();
            RedisCache::invalidateFrontend();
            $row = self::findById($id);
            return self::formatRow($row);
        } catch (Exception $e) {
            return '创建失败，请稍后重试';
        }
    }

    /**
     * 更新接口
     *
     * @param int   $apiId
     * @param array $data
     * @return true|string
     */
    public static function update($apiId, array $data)
    {
        $apiId = (int) $apiId;
        $row = self::findById($apiId);
        if (!$row) {
            return '接口不存在';
        }

        $parsed = self::normalizePayload($data, $apiId);
        if (!is_array($parsed)) {
            return $parsed;
        }

        $hasAuditInPayload = array_key_exists('audit', $data);
        $auditStatus = self::AUDIT_APPROVED;
        if ($hasAuditInPayload) {
            $auditStatus = self::normalizeAuditStatus($data['audit']);
            if (!self::isValidAuditStatus($auditStatus)) {
                return '无效的审核状态';
            }
        }

        $clearReject = $hasAuditInPayload && $auditStatus !== self::AUDIT_REJECTED;
        $rejectReason = null;
        if ($hasAuditInPayload && $auditStatus === self::AUDIT_REJECTED && array_key_exists('rejectreason', $data)) {
            $rejectReason = self::normalizeRejectReason($data['rejectreason']);
        } elseif ($clearReject) {
            $rejectReason = '';
        }

        try {
            $pdo = Database::connect();
            if (self::hasProxyColumns() && $hasAuditInPayload && self::hasAuditColumn() && self::hasRejectReasonColumn() && $rejectReason !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `name` = ?, `description` = ?, `endpoint` = ?, `apitype` = ?, `targeturl` = ?, `proxyslug` = ?,
                         `method` = ?, `params` = ?, `response` = ?, `doc` = ?, `aidoc` = ?,
                         `needkey` = ?, `status` = ?, `audit` = ?, `rejectreason` = ?,
                         `icon` = ?, `category` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['apitype'],
                    $parsed['targeturl'],
                    $parsed['proxyslug'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $rejectReason,
                    $parsed['icon'],
                    $parsed['category'],
                    $apiId,
                ));
            } elseif (self::hasProxyColumns() && !$hasAuditInPayload) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `name` = ?, `description` = ?, `endpoint` = ?, `apitype` = ?, `targeturl` = ?, `proxyslug` = ?,
                         `method` = ?, `params` = ?, `response` = ?, `doc` = ?, `aidoc` = ?,
                         `needkey` = ?, `status` = ?, `icon` = ?, `category` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['apitype'],
                    $parsed['targeturl'],
                    $parsed['proxyslug'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $parsed['icon'],
                    $parsed['category'],
                    $apiId,
                ));
            } elseif ($hasAuditInPayload && self::hasAuditColumn() && self::hasRejectReasonColumn() && $rejectReason !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `name` = ?, `description` = ?, `endpoint` = ?, `method` = ?,
                         `params` = ?, `response` = ?, `doc` = ?, `aidoc` = ?,
                         `needkey` = ?, `status` = ?, `audit` = ?, `rejectreason` = ?,
                         `icon` = ?, `category` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $rejectReason,
                    $parsed['icon'],
                    $parsed['category'],
                    $apiId,
                ));
            } elseif ($hasAuditInPayload && self::hasAuditColumn()) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `name` = ?, `description` = ?, `endpoint` = ?, `method` = ?,
                         `params` = ?, `response` = ?, `doc` = ?, `aidoc` = ?,
                         `needkey` = ?, `status` = ?, `audit` = ?, `icon` = ?, `category` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $auditStatus,
                    $parsed['icon'],
                    $parsed['category'],
                    $apiId,
                ));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `name` = ?, `description` = ?, `endpoint` = ?, `method` = ?,
                         `params` = ?, `response` = ?, `doc` = ?, `aidoc` = ?,
                         `needkey` = ?, `status` = ?, `icon` = ?, `category` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array(
                    $parsed['name'],
                    $parsed['description'],
                    $parsed['endpoint'],
                    $parsed['method'],
                    $parsed['params'],
                    $parsed['response'],
                    $parsed['doc'],
                    $parsed['aidoc'],
                    $parsed['needkey'],
                    $parsed['status'],
                    $parsed['icon'],
                    $parsed['category'],
                    $apiId,
                ));
            }
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int          $apiId
     * @param int|string   $status
     * @return true|string
     */
    public static function setStatus($apiId, $status)
    {
        $apiId = (int) $apiId;
        $status = self::normalizeStatus($status);
        if ($apiId <= 0 || !self::isValidStatus($status)) {
            return '无效操作';
        }
        if (!self::findById($apiId)) {
            return '接口不存在';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `updatetime` = NOW() WHERE `id` = ?'
            );
            $stmt->execute(array($status, $apiId));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
    }

    /**
     * 设置审核状态
     *
     * @param int         $apiId
     * @param int|string  $auditStatus
     * @param string|null $rejectReason 不通过时可填原因；通过/待审时自动清空
     * @return true|string
     */
    public static function setAuditStatus($apiId, $auditStatus, $rejectReason = null)
    {
        $apiId = (int) $apiId;
        $auditStatus = self::normalizeAuditStatus($auditStatus);
        if ($apiId <= 0 || !self::isValidAuditStatus($auditStatus)) {
            return '无效操作';
        }
        if (!self::hasAuditColumn()) {
            return '请先执行数据库结构更新';
        }
        if (!self::findById($apiId)) {
            return '接口不存在';
        }

        $reason = '';
        if ($auditStatus === self::AUDIT_REJECTED) {
            $reason = self::normalizeRejectReason($rejectReason);
        }

        try {
            $pdo = Database::connect();
            if (self::hasRejectReasonColumn()) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '`
                     SET `audit` = ?, `rejectreason` = ?, `updatetime` = NOW()
                     WHERE `id` = ?'
                );
                $stmt->execute(array($auditStatus, $reason, $apiId));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET `audit` = ?, `updatetime` = NOW() WHERE `id` = ?'
                );
                $stmt->execute(array($auditStatus, $apiId));
            }
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function normalizeRejectReason($value)
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, 500, 'UTF-8');
        } else {
            $text = substr($text, 0, 500);
        }
        return $text;
    }

    /**
     * @param int $apiId
     * @return true|string
     */
    public static function delete($apiId)
    {
        $apiId = (int) $apiId;
        if (!self::findById($apiId)) {
            return '接口不存在';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `id` = ?');
            $stmt->execute(array($apiId));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '删除失败，请稍后重试';
        }
    }

    /**
     * 增加调用计数（后续网关/代理可调用）
     *
     * @param int $apiId
     * @param int $delta
     * @return bool
     */
    public static function incrementCallCount($apiId, $delta = 1)
    {
        $apiId = (int) $apiId;
        $delta = (int) $delta;
        if ($apiId <= 0 || $delta === 0) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `calls` = `calls` + ?, `updatetime` = NOW()
                 WHERE `id` = ?'
            );
            $stmt->execute(array($delta, $apiId));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 归一接口状态（仅接受数字 0/1/2）
     *
     * @param mixed $value
     * @return int
     */
    public static function normalizeStatus($value)
    {
        $n = (int) $value;
        if ($n === self::STATUS_DISABLED || $n === self::STATUS_MAINTENANCE) {
            return $n;
        }
        return self::STATUS_NORMAL;
    }

    /**
     * @param mixed $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $status = self::normalizeStatus($status);
        $map = array(
            self::STATUS_NORMAL      => '正常',
            self::STATUS_DISABLED    => '禁用',
            self::STATUS_MAINTENANCE => '维护',
        );
        return isset($map[$status]) ? $map[$status] : '正常';
    }

    /**
     * @param mixed $status
     * @return bool
     */
    public static function isValidStatus($status)
    {
        $n = is_int($status) ? $status : self::normalizeStatus($status);
        return in_array($n, array(
            self::STATUS_NORMAL,
            self::STATUS_DISABLED,
            self::STATUS_MAINTENANCE,
        ), true);
    }

    /**
     * @param mixed $value
     * @return int
     */
    public static function normalizeAuditStatus($value)
    {
        $n = (int) $value;
        if ($n === self::AUDIT_APPROVED || $n === self::AUDIT_REJECTED) {
            return $n;
        }
        return self::AUDIT_PENDING;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public static function isValidAuditStatus($value)
    {
        $n = (int) $value;
        return $n === self::AUDIT_PENDING
            || $n === self::AUDIT_APPROVED
            || $n === self::AUDIT_REJECTED;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function auditStatusLabel($value)
    {
        $n = self::normalizeAuditStatus($value);
        if ($n === self::AUDIT_APPROVED) {
            return '审核通过';
        }
        if ($n === self::AUDIT_REJECTED) {
            return '审核不通过';
        }
        return '待审核';
    }

    /**
     * CSS 辅助类名（仅 class，不含数字含义文案）
     *
     * @param mixed $value
     * @return string
     */
    public static function auditStatusClass($value)
    {
        $n = self::normalizeAuditStatus($value);
        if ($n === self::AUDIT_APPROVED) {
            return 'is-approved';
        }
        if ($n === self::AUDIT_REJECTED) {
            return 'is-rejected';
        }
        return 'is-pending';
    }

    /**
     * 格式化接口行（后台 AJAX / 列表）
     *
     * @param array|null $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row)) {
            return null;
        }

        $status = self::normalizeStatus(isset($row['status']) ? $row['status'] : self::STATUS_NORMAL);
        $auditStatus = self::normalizeAuditStatus(
            isset($row['audit']) ? $row['audit'] : self::AUDIT_APPROVED
        );
        if (isset($row['icon_raw']) && (string) $row['icon_raw'] !== '') {
            $iconRaw = (string) $row['icon_raw'];
        } else {
            $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';
        }

        return array(
            'id'            => (int) $row['id'],
            'name'          => (string) $row['name'],
            'description'   => isset($row['description']) ? (string) $row['description'] : '',
            'endpoint'      => isset($row['endpoint']) ? (string) $row['endpoint'] : '',
            'apitype'       => self::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : self::APITYPE_LOCAL),
            'apitype_label' => self::apiTypeLabel(isset($row['apitype']) ? $row['apitype'] : self::APITYPE_LOCAL),
            'apitype_badge' => self::apiTypeBadge(isset($row['apitype']) ? $row['apitype'] : self::APITYPE_LOCAL),
            'targeturl'     => isset($row['targeturl']) ? (string) $row['targeturl'] : '',
            'proxyslug'     => isset($row['proxyslug']) ? (string) $row['proxyslug'] : '',
            'call_url'      => self::resolveCallUrl($row),
            'method'        => isset($row['method']) ? strtoupper((string) $row['method']) : self::METHOD_GET,
            'params'        => isset($row['params']) ? (string) $row['params'] : '',
            'response'      => isset($row['response']) ? (string) $row['response'] : '',
            'doc'           => isset($row['doc']) ? (string) $row['doc'] : '',
            'aidoc'         => isset($row['aidoc']) ? (string) $row['aidoc'] : '',
            'calls'         => isset($row['calls']) ? (int) $row['calls'] : 0,
            'needkey'       => self::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : 0),
            'needkey_label' => self::requireKeyLabel(isset($row['needkey']) ? $row['needkey'] : 0),
            'needkey_badge' => self::requireKeyBadge(isset($row['needkey']) ? $row['needkey'] : 0),
            'status'         => $status,
            'status_label'   => self::statusLabel($status),
            'audit'          => $auditStatus,
            'audit_label'    => self::auditStatusLabel($auditStatus),
            'audit_class'    => self::auditStatusClass($auditStatus),
            'rejectreason'   => isset($row['rejectreason']) ? (string) $row['rejectreason'] : '',
            'email'          => isset($row['email']) ? (string) $row['email'] : '',
            'icon'           => ApiCategoryManager::resolveIconUrl($iconRaw),
            'icon_raw'       => $iconRaw,
            'category'       => isset($row['category']) ? (string) $row['category'] : '',
            'userid'         => isset($row['userid']) ? (int) $row['userid'] : 0,
            'username'       => isset($row['username']) ? (string) $row['username'] : '',
            'createtime'     => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'     => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
        );
    }

    /**
     * 列表行轻量数据（不含大文本，避免 HTML data-* 过大）
     *
     * @param array|null $row
     * @return array|null
     */
    public static function formatRowSummary($row)
    {
        $full = self::formatRow($row);
        if (!is_array($full)) {
            return null;
        }

        return array(
            'id'             => $full['id'],
            'name'           => $full['name'],
            'description'    => $full['description'],
            'endpoint'       => $full['endpoint'],
            'apitype'        => $full['apitype'],
            'apitype_label'  => $full['apitype_label'],
            'apitype_badge'  => $full['apitype_badge'],
            'targeturl'      => $full['targeturl'],
            'proxyslug'      => $full['proxyslug'],
            'call_url'       => $full['call_url'],
            'method'         => $full['method'],
            'calls'          => $full['calls'],
            'needkey'        => $full['needkey'],
            'needkey_label'  => $full['needkey_label'],
            'needkey_badge'  => $full['needkey_badge'],
            'status'         => $full['status'],
            'status_label'   => $full['status_label'],
            'audit'          => $full['audit'],
            'audit_label'    => $full['audit_label'],
            'audit_class'    => $full['audit_class'],
            'rejectreason'   => $full['rejectreason'],
            'icon'           => $full['icon'],
            'icon_raw'       => $full['icon_raw'],
            'category'       => $full['category'],
            'userid'         => $full['userid'],
            'username'       => $full['username'],
        );
    }

    /**
     * 归一接口类型
     *
     * @param mixed $value
     * @return int
     */
    public static function normalizeApiType($value)
    {
        return ((int) $value === self::APITYPE_PROXY) ? self::APITYPE_PROXY : self::APITYPE_LOCAL;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function apiTypeLabel($value)
    {
        return self::normalizeApiType($value) === self::APITYPE_PROXY ? '代理外链' : '本地接口';
    }

    /**
     * 列表卡片用短标签（仅代理显示「代理」，本地不占位）
     *
     * @param mixed $value
     * @return string
     */
    public static function apiTypeBadge($value)
    {
        return self::normalizeApiType($value) === self::APITYPE_PROXY ? '代理' : '';
    }

    /**
     * 列表卡片密钥角标：无要求则空；可选 / 必填
     *
     * @param mixed $value
     * @return string
     */
    public static function requireKeyBadge($value)
    {
        $key = self::normalizeRequireKey($value);
        if ($key === self::KEY_REQUIRED) {
            return 'KEY必填';
        }
        if ($key === self::KEY_OPTIONAL) {
            return 'KEY可选';
        }
        return '';
    }

    /**
     * 前台/调试用的绝对调用地址
     *
     * @param array $row
     * @return string
     */
    public static function resolveCallUrl(array $row)
    {
        $apitype = self::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : self::APITYPE_LOCAL);
        if ($apitype === self::APITYPE_PROXY) {
            $slug = isset($row['proxyslug']) ? (string) $row['proxyslug'] : '';
            return ApiProxy::publicUrl($slug);
        }
        $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
        if ($endpoint === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $endpoint)) {
            return $endpoint;
        }
        if ($endpoint[0] !== '/') {
            $endpoint = '/' . $endpoint;
        }
        return rtrim(vs_base_url(), '/') . $endpoint;
    }

    /**
     * @param array $data
     * @param int   $excludeId 更新时排除自身短码占用
     * @return array|string
     */
    private static function normalizePayload(array $data, $excludeId = 0)
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) (isset($data['name']) ? $data['name'] : '')));
        if ($name === '') {
            return '请填写接口名称';
        }
        if (mb_strlen($name, 'UTF-8') > 100) {
            return '接口名称不能超过 100 个字符';
        }

        $description = trim((string) (isset($data['description']) ? $data['description'] : ''));
        if (mb_strlen($description, 'UTF-8') > 5000) {
            return '接口描述过长';
        }

        $apitype = self::normalizeApiType(isset($data['apitype']) ? $data['apitype'] : self::APITYPE_LOCAL);
        $targeturl = '';
        $proxyslug = '';
        $endpoint = trim((string) (isset($data['endpoint']) ? $data['endpoint'] : ''));

        if ($apitype === self::APITYPE_PROXY) {
            $targeturl = trim((string) (isset($data['targeturl']) ? $data['targeturl'] : ''));
            if ($targeturl === '') {
                // 兼容旧表单：把 endpoint 当作上游
                $targeturl = $endpoint;
            }
            if ($targeturl === '' || !preg_match('#^https?://#i', $targeturl)) {
                return '请填写完整的上游地址（以 http:// 或 https:// 开头）';
            }
            if (mb_strlen($targeturl, 'UTF-8') > 500) {
                return '代理上游地址不能超过 500 个字符';
            }
            $proxyslug = ApiProxy::normalizeSlug(isset($data['proxyslug']) ? $data['proxyslug'] : '');
            if ($proxyslug === '') {
                return '请填写 3～64 位字母或数字短码';
            }
            if (ApiProxy::slugExists($proxyslug, $excludeId > 0 ? $excludeId : null)) {
                return '该短码已被占用，请更换';
            }
            $endpoint = ApiProxy::publicPath($proxyslug);
        } else {
            if ($endpoint === '') {
                return '请填写本地接口路径';
            }
            if (mb_strlen($endpoint, 'UTF-8') > 500) {
                return '接口地址不能超过 500 个字符';
            }
            if (preg_match('#^https?://#i', $endpoint)) {
                // 兼容历史：完整 URL 视为本地直连
            } else {
                if ($endpoint[0] !== '/') {
                    $endpoint = '/' . ltrim($endpoint, '/');
                }
                if (strpos($endpoint, '//') === 0 || strpos($endpoint, '..') !== false) {
                    return '本地接口路径不合法';
                }
                if (!preg_match('#^/[A-Za-z0-9_./%~+-]+$#', $endpoint)) {
                    return '本地接口路径仅支持字母、数字与常见路径字符';
                }
            }
            $targeturl = '';
            $proxyslug = '';
        }

        $method = strtoupper(trim((string) (isset($data['method']) ? $data['method'] : self::METHOD_GET)));
        if (!in_array($method, array(self::METHOD_GET, self::METHOD_POST), true)) {
            return '请求方式仅支持 GET 或 POST';
        }

        $requestParams = trim((string) (isset($data['params']) ? $data['params'] : ''));
        if ($requestParams !== '') {
            $decoded = json_decode($requestParams, true);
            if (!is_array($decoded)) {
                return '请求参数须为合法 JSON 数组，例如 [{"name":"q","type":"string","required":true}]';
            }
            $requestParams = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($requestParams === false) {
                return '请求参数编码失败';
            }
        }

        $responseExample = (string) (isset($data['response']) ? $data['response'] : '');
        $docNormal = (string) (isset($data['doc']) ? $data['doc'] : '');
        $docAi = (string) (isset($data['aidoc']) ? $data['aidoc'] : '');
        if (strlen($responseExample) > 200000 || strlen($docNormal) > 200000 || strlen($docAi) > 200000) {
            return '文档或返回示例过长';
        }

        $requireKey = self::normalizeRequireKey(isset($data['needkey']) ? $data['needkey'] : self::KEY_NONE);

        $status = self::normalizeStatus(isset($data['status']) ? $data['status'] : self::STATUS_NORMAL);
        if (!self::isValidStatus($status)) {
            return '无效的接口状态';
        }

        $icon = ApiCategoryManager::normalizeIconInput(isset($data['icon']) ? $data['icon'] : '');
        if ($icon === false) {
            return '图标链接格式不正确（请使用本地 SVG 或 http(s) 链接）';
        }

        $category = trim(preg_replace('/\s+/u', ' ', (string) (isset($data['category']) ? $data['category'] : '')));
        if (mb_strlen($category, 'UTF-8') > 50) {
            return '分类名称过长';
        }
        if ($category !== '' && class_exists('ApiCategoryManager') && ApiCategoryManager::tableReady()) {
            if (ApiCategoryManager::findByName($category) === null) {
                return '所选分类不存在';
            }
        }

        return array(
            'name'        => $name,
            'description' => $description,
            'endpoint'    => $endpoint,
            'apitype'     => $apitype,
            'targeturl'   => $targeturl,
            'proxyslug'   => $proxyslug,
            'method'      => $method,
            'params'      => $requestParams,
            'response'    => $responseExample,
            'doc'         => $docNormal,
            'aidoc'       => $docAi,
            'needkey'     => $requireKey,
            'status'      => $status,
            'icon'        => $icon,
            'category'    => $category,
        );
    }

    /**
     * @param mixed $value
     * @return int 0|1|2
     */
    public static function normalizeRequireKey($value)
    {
        $v = (int) $value;
        if ($v === self::KEY_REQUIRED || $v === self::KEY_OPTIONAL) {
            return $v;
        }
        return self::KEY_NONE;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function requireKeyLabel($value)
    {
        $map = array(
            self::KEY_NONE     => '不需要',
            self::KEY_REQUIRED => '必须',
            self::KEY_OPTIONAL => '可选',
        );
        $key = self::normalizeRequireKey($value);
        return isset($map[$key]) ? $map[$key] : '不需要';
    }
}
