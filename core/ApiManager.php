<?php
/**
 * 文件：core/ApiManager.php
 * 作用：API 接口数据管理（后台接口列表 CRUD）
 *
 * 状态：normal（正常）/ disabled（禁用，前台不展示）/ maintenance（维护中，前台可见但不可请求）
 */

class ApiManager
{
    const STATUS_NORMAL = 'normal';
    const STATUS_DISABLED = 'disabled';
    const STATUS_MAINTENANCE = 'maintenance';

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
            $col = $pdo->query('SHOW COLUMNS FROM `' . self::table() . '` LIKE ' . $pdo->quote('doc_ai'));
            return $col && $col->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 前台可见接口（禁用除外；含维护中）
     *
     * @return array
     */
    public static function listPublic()
    {
        return RedisCache::remember(
            RedisCache::KEY_API_PUBLIC,
            RedisCache::TTL_API_PUBLIC,
            function () {
                return self::listAll(array(self::STATUS_NORMAL, self::STATUS_MAINTENANCE));
            }
        );
    }

    /**
     * 前台公开接口数量（不含禁用）
     *
     * @return int
     */
    public static function countPublic()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT COUNT(*) FROM `' . self::table() . '`
                 WHERE `status` IN (' . $pdo->quote(self::STATUS_NORMAL) . ', ' . $pdo->quote(self::STATUS_MAINTENANCE) . ')'
            );
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
     * 全站累计调用次数（各接口 call_count 之和）
     *
     * @return int
     */
    public static function totalCallCount()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SELECT COALESCE(SUM(`call_count`), 0) FROM `' . self::table() . '`');
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
     * @param string|array|null $status 单个状态、状态数组，或 null 表示全部
     * @return array
     */
    public static function listAll($status = null)
    {
        if (!self::tableReady()) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $sql = 'SELECT a.*, u.`username`, u.`email`
                    FROM `' . self::table() . '` AS a
                    LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`user_id`';
            $params = array();

            if (is_array($status)) {
                $statuses = array();
                foreach ($status as $s) {
                    $s = (string) $s;
                    if (self::isValidStatus($s)) {
                        $statuses[] = $s;
                    }
                }
                if (count($statuses) > 0) {
                    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                    $sql .= ' WHERE a.`status` IN (' . $placeholders . ')';
                    $params = $statuses;
                }
            } elseif ($status !== null && $status !== '') {
                $sql .= ' WHERE a.`status` = ?';
                $params[] = (string) $status;
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
                 LEFT JOIN `' . Database::table('user') . '` AS u ON u.`id` = a.`user_id`
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

        $parsed = self::normalizePayload($data);
        if (!is_array($parsed)) {
            return $parsed;
        }

        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        if ($userId < 0) {
            $userId = 0;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                 (`name`, `description`, `endpoint`, `method`, `request_params`, `response_example`,
                  `doc_normal`, `doc_ai`, `call_count`, `require_key`, `status`, `icon`, `category`, `user_id`, `created_at`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute(array(
                $parsed['name'],
                $parsed['description'],
                $parsed['endpoint'],
                $parsed['method'],
                $parsed['request_params'],
                $parsed['response_example'],
                $parsed['doc_normal'],
                $parsed['doc_ai'],
                $parsed['require_key'],
                $parsed['status'],
                $parsed['icon'],
                $parsed['category'],
                $userId,
            ));
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

        $parsed = self::normalizePayload($data);
        if (!is_array($parsed)) {
            return $parsed;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `name` = ?, `description` = ?, `endpoint` = ?, `method` = ?,
                     `request_params` = ?, `response_example` = ?, `doc_normal` = ?, `doc_ai` = ?,
                     `require_key` = ?, `status` = ?, `icon` = ?, `category` = ?, `updated_at` = NOW()
                 WHERE `id` = ?'
            );
            $stmt->execute(array(
                $parsed['name'],
                $parsed['description'],
                $parsed['endpoint'],
                $parsed['method'],
                $parsed['request_params'],
                $parsed['response_example'],
                $parsed['doc_normal'],
                $parsed['doc_ai'],
                $parsed['require_key'],
                $parsed['status'],
                $parsed['icon'],
                $parsed['category'],
                $apiId,
            ));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int    $apiId
     * @param string $status
     * @return true|string
     */
    public static function setStatus($apiId, $status)
    {
        $apiId = (int) $apiId;
        $status = (string) $status;
        if ($apiId <= 0 || !self::isValidStatus($status)) {
            return '无效操作';
        }
        if (!self::findById($apiId)) {
            return '接口不存在';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `updated_at` = NOW() WHERE `id` = ?'
            );
            $stmt->execute(array($status, $apiId));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
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
                 SET `call_count` = `call_count` + ?, `updated_at` = NOW()
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
     * @param string $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_NORMAL      => '正常',
            self::STATUS_DISABLED    => '禁用',
            self::STATUS_MAINTENANCE => '维护',
        );
        return isset($map[$status]) ? $map[$status] : (string) $status;
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isValidStatus($status)
    {
        return in_array((string) $status, array(
            self::STATUS_NORMAL,
            self::STATUS_DISABLED,
            self::STATUS_MAINTENANCE,
        ), true);
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

        $status = isset($row['status']) ? (string) $row['status'] : self::STATUS_NORMAL;
        if (isset($row['icon_raw']) && (string) $row['icon_raw'] !== '') {
            $iconRaw = (string) $row['icon_raw'];
        } else {
            $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';
        }

        return array(
            'id'               => (int) $row['id'],
            'name'             => (string) $row['name'],
            'description'      => isset($row['description']) ? (string) $row['description'] : '',
            'endpoint'         => isset($row['endpoint']) ? (string) $row['endpoint'] : '',
            'method'           => isset($row['method']) ? strtoupper((string) $row['method']) : self::METHOD_GET,
            'request_params'   => isset($row['request_params']) ? (string) $row['request_params'] : '',
            'response_example' => isset($row['response_example']) ? (string) $row['response_example'] : '',
            'doc_normal'       => isset($row['doc_normal']) ? (string) $row['doc_normal'] : '',
            'doc_ai'           => isset($row['doc_ai']) ? (string) $row['doc_ai'] : '',
            'call_count'       => isset($row['call_count']) ? (int) $row['call_count'] : 0,
            'require_key'      => self::normalizeRequireKey(isset($row['require_key']) ? $row['require_key'] : 0),
            'require_key_label'=> self::requireKeyLabel(isset($row['require_key']) ? $row['require_key'] : 0),
            'status'           => $status,
            'status_label'     => self::statusLabel($status),
            'icon'             => ApiCategoryManager::resolveIconUrl($iconRaw),
            'icon_raw'         => $iconRaw,
            'category'         => isset($row['category']) ? (string) $row['category'] : '',
            'user_id'          => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'username'         => isset($row['username']) ? (string) $row['username'] : '',
            'created_at'       => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at'       => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
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
            'id'           => $full['id'],
            'name'         => $full['name'],
            'description'  => $full['description'],
            'endpoint'     => $full['endpoint'],
            'method'       => $full['method'],
            'call_count'   => $full['call_count'],
            'require_key'       => $full['require_key'],
            'require_key_label' => $full['require_key_label'],
            'status'            => $full['status'],
            'status_label'      => $full['status_label'],
            'icon'         => $full['icon'],
            'icon_raw'     => $full['icon_raw'],
            'category'     => $full['category'],
        );
    }

    /**
     * @param array $data
     * @return array|string
     */
    private static function normalizePayload(array $data)
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

        $endpoint = trim((string) (isset($data['endpoint']) ? $data['endpoint'] : ''));
        if ($endpoint === '') {
            return '请填写接口地址';
        }
        if (mb_strlen($endpoint, 'UTF-8') > 500) {
            return '接口地址不能超过 500 个字符';
        }
        if (!preg_match('#^https?://#i', $endpoint)) {
            return '接口地址须以 http:// 或 https:// 开头';
        }

        $method = strtoupper(trim((string) (isset($data['method']) ? $data['method'] : self::METHOD_GET)));
        if (!in_array($method, array(self::METHOD_GET, self::METHOD_POST), true)) {
            return '请求方式仅支持 GET 或 POST';
        }

        $requestParams = trim((string) (isset($data['request_params']) ? $data['request_params'] : ''));
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

        $responseExample = (string) (isset($data['response_example']) ? $data['response_example'] : '');
        $docNormal = (string) (isset($data['doc_normal']) ? $data['doc_normal'] : '');
        $docAi = (string) (isset($data['doc_ai']) ? $data['doc_ai'] : '');
        if (strlen($responseExample) > 200000 || strlen($docNormal) > 200000 || strlen($docAi) > 200000) {
            return '文档或返回示例过长';
        }

        $requireKey = self::normalizeRequireKey(isset($data['require_key']) ? $data['require_key'] : self::KEY_NONE);

        $status = isset($data['status']) ? (string) $data['status'] : self::STATUS_NORMAL;
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
            'name'             => $name,
            'description'      => $description,
            'endpoint'         => $endpoint,
            'method'           => $method,
            'request_params'   => $requestParams,
            'response_example' => $responseExample,
            'doc_normal'       => $docNormal,
            'doc_ai'           => $docAi,
            'require_key'      => $requireKey,
            'status'           => $status,
            'icon'             => $icon,
            'category'         => $category,
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
