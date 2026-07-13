<?php
/**
 * 文件：core/ApiCategoryManager.php
 * 作用：API 接口分类管理
 */

class ApiCategoryManager
{
    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('category');
    }

    /**
     * 分类表是否可用
     *
     * @return bool
     */
    public static function tableReady()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            return $stmt && $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 系统内置分类图标（相对 assets 路径）
     *
     * @return array<int, string>
     */
    public static function defaultIconPaths()
    {
        return array(
            '/assets/img/category-icons/image.svg',
            '/assets/img/category-icons/video.svg',
            '/assets/img/category-icons/music.svg',
            '/assets/img/category-icons/tool.svg',
            '/assets/img/category-icons/ai.svg',
            '/assets/img/category-icons/search.svg',
            '/assets/img/category-icons/weather.svg',
            '/assets/img/category-icons/live.svg',
            '/assets/img/category-icons/hot.svg',
            '/assets/img/category-icons/baidu-hot.svg',
            '/assets/img/category-icons/toutiao-hot.svg',
            '/assets/img/category-icons/douyin-hot.svg',
            '/assets/img/category-icons/zhihu-hot.svg',
            '/assets/img/category-icons/sogou-hot.svg',
            '/assets/img/category-icons/douyin.svg',
            '/assets/img/category-icons/kuaishou.svg',
            '/assets/img/category-icons/bilibili.svg',
            '/assets/img/category-icons/wechat.svg',
            '/assets/img/category-icons/qq.svg',
            '/assets/img/category-icons/ip.svg',
            '/assets/img/category-icons/lottery.svg',
            '/assets/img/category-icons/random.svg',
            '/assets/img/category-icons/emoji.svg',
            '/assets/img/category-icons/netease.svg',
            '/assets/img/category-icons/qq-music.svg',
            '/assets/img/category-icons/honor-of-kings.svg',
            '/assets/img/category-icons/honor-of-kings-alt.svg',
            '/assets/img/category-icons/business-license.svg',
            '/assets/img/category-icons/business-license-alt.svg',
            '/assets/img/category-icons/id-card.svg',
            '/assets/img/category-icons/icp.svg',
            '/assets/img/category-icons/car-dealer.svg',
            '/assets/img/category-icons/map.svg',
            '/assets/img/category-icons/weibo.svg',
            '/assets/img/category-icons/express.svg',
        );
    }

    /**
     * 内置图标完整 URL 列表
     *
     * @return array<int, string>
     */
    public static function defaultIcons()
    {
        $base = rtrim(vs_base_url(), '/');
        $out = array();
        foreach (self::defaultIconPaths() as $path) {
            $out[] = $base . $path;
        }
        return $out;
    }

    /**
     * 解析并校验图标 URL（空值回退默认图标）
     *
     * @param string $icon
     * @return string
     */
    public static function resolveIconUrl($icon)
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            $defaults = self::defaultIcons();
            return isset($defaults[0]) ? $defaults[0] : '';
        }

        if (preg_match('#^/assets/img/category-icons/[a-z0-9\-]+\.svg$#i', $icon)) {
            return rtrim(vs_base_url(), '/') . $icon;
        }

        if (preg_match('#^https?://#i', $icon)) {
            return $icon;
        }

        $base = rtrim(vs_base_url(), '/');
        if (strpos($icon, $base) === 0) {
            return $icon;
        }

        $defaults = self::defaultIcons();
        return isset($defaults[0]) ? $defaults[0] : $icon;
    }

    /**
     * 格式化分类行（API / 前台使用）
     *
     * @param array|null $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row)) {
            return null;
        }

        $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';

        return array(
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'icon'        => self::resolveIconUrl($iconRaw),
            'icon_raw'    => $iconRaw,
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'status'      => (int) $row['status'],
        );
    }

    /**
     * @return array
     */
    public static function listAll()
    {
        if (!self::tableReady()) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $apiTable = ApiManager::table();
            $sql = 'SELECT c.*,
                    (SELECT COUNT(*) FROM `' . $apiTable . '` AS a WHERE a.`category` = c.`name`) AS api_count
                    FROM `' . self::table() . '` AS c
                    ORDER BY c.`id` DESC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 启用的分类（前台筛选用）
     *
     * @return array
     */
    public static function listEnabled()
    {
        if (!self::tableReady()) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $sql = 'SELECT c.*
                    FROM `' . self::table() . '` AS c
                    WHERE c.`status` = 1
                    ORDER BY c.`sort_order` ASC, c.`id` ASC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
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
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @return array|null
     */
    public static function findByName($name)
    {
        $name = self::normalizeName($name);
        if ($name === '' || !self::tableReady()) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `name` = ? LIMIT 1');
            $stmt->execute(array($name));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @param string $icon
     * @param string $description
     * @return array|string
     */
    public static function create($name, $icon = '', $description = '')
    {
        if (!self::tableReady()) {
            return '分类表未就绪，请先执行系统升级';
        }

        $name = self::normalizeName($name);
        if ($name === '') {
            return '请填写分类名称';
        }
        if (mb_strlen($name, 'UTF-8') > 50) {
            return '分类名称不能超过 50 个字符';
        }
        if (self::findByName($name) !== null) {
            return '分类名称已存在';
        }

        $iconStored = self::normalizeIconInput($icon);
        if ($iconStored === false) {
            return '图标链接格式不正确';
        }

        $description = self::normalizeDescription($description);
        if ($description === false) {
            return '分类描述不能超过 255 个字符';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (`name`, `icon`, `description`, `sort_order`, `status`, `created_at`)
                 VALUES (?, ?, ?, 0, 1, NOW())'
            );
            $stmt->execute(array($name, $iconStored, $description));
            $id = (int) $pdo->lastInsertId();
            RedisCache::invalidateFrontend();
            return self::formatRow(array(
                'id'          => $id,
                'name'        => $name,
                'icon'        => $iconStored,
                'description' => $description,
                'status'      => 1,
            ));
        } catch (Exception $e) {
            return '添加失败，请稍后重试';
        }
    }

    /**
     * @param int    $id
     * @param string $name
     * @param string $icon
     * @param string $description
     * @return true|string
     */
    public static function update($id, $name, $icon = '', $description = '')
    {
        $id = (int) $id;
        $row = self::findById($id);
        if (!$row) {
            return '分类不存在';
        }

        $name = self::normalizeName($name);
        if ($name === '') {
            return '请填写分类名称';
        }
        if (mb_strlen($name, 'UTF-8') > 50) {
            return '分类名称不能超过 50 个字符';
        }

        $existing = self::findByName($name);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            return '分类名称已存在';
        }

        $iconStored = self::normalizeIconInput($icon);
        if ($iconStored === false) {
            return '图标链接格式不正确';
        }

        $description = self::normalizeDescription($description);
        if ($description === false) {
            return '分类描述不能超过 255 个字符';
        }

        $oldName = (string) $row['name'];

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `name` = ?, `icon` = ?, `description` = ?, `updated_at` = NOW()
                 WHERE `id` = ?'
            );
            $stmt->execute(array($name, $iconStored, $description, $id));

            if ($oldName !== $name) {
                $apiStmt = $pdo->prepare(
                    'UPDATE `' . ApiManager::table() . '` SET `category` = ? WHERE `category` = ?'
                );
                $apiStmt->execute(array($name, $oldName));
            }

            $pdo->commit();
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int $id
     * @param int $status 0|1
     * @return true|string
     */
    public static function setStatus($id, $status)
    {
        $id = (int) $id;
        $status = (int) $status;
        if ($id <= 0 || !in_array($status, array(0, 1), true)) {
            return '无效操作';
        }
        if (!self::findById($id)) {
            return '分类不存在';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `updated_at` = NOW() WHERE `id` = ?'
            );
            $stmt->execute(array($status, $id));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
    }

    /**
     * @param int $id
     * @return true|string
     */
    public static function delete($id)
    {
        $id = (int) $id;
        $row = self::findById($id);
        if (!$row) {
            return '分类不存在';
        }

        $count = self::countApisByName((string) $row['name']);
        if ($count > 0) {
            return '该分类下仍有 ' . $count . ' 个接口，无法删除';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `id` = ?');
            $stmt->execute(array($id));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '删除失败，请稍后重试';
        }
    }

    /**
     * @param string $name
     * @return int
     */
    public static function countApisByName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM `' . ApiManager::table() . '` WHERE `category` = ?'
            );
            $stmt->execute(array($name));
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param string $icon
     * @return string|false
     */
    private static function normalizeIconInput($icon)
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            return '';
        }
        if (mb_strlen($icon, 'UTF-8') > 255) {
            return false;
        }

        $base = rtrim(vs_base_url(), '/');
        if (strpos($icon, $base) === 0) {
            $icon = substr($icon, strlen($base));
        }

        if (preg_match('#^/assets/img/category-icons/[a-z0-9\-]+\.svg$#i', $icon)) {
            return $icon;
        }

        if (preg_match('#^https?://#i', $icon)) {
            return $icon;
        }

        return false;
    }

    /**
     * @param string $description
     * @return string|false
     */
    private static function normalizeDescription($description)
    {
        $description = trim(preg_replace('/\s+/u', ' ', (string) $description));
        if (mb_strlen($description, 'UTF-8') > 255) {
            return false;
        }
        return $description;
    }

    /**
     * @param string $name
     * @return string
     */
    private static function normalizeName($name)
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }
}
