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
     * 图标库目录（物理路径）
     *
     * @return string
     */
    public static function iconLibraryDir()
    {
        return VS_ROOT . '/assets/img/category-icons';
    }

    /**
     * 系统内置分类图标（相对 assets 路径；自动扫描图标库）
     *
     * @return array<int, string>
     */
    public static function defaultIconPaths()
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $dir = self::iconLibraryDir();
        $files = array();
        if (is_dir($dir)) {
            $list = scandir($dir);
            if (is_array($list)) {
                foreach ($list as $name) {
                    if (!is_string($name) || $name === '.' || $name === '..') {
                        continue;
                    }
                    if (!preg_match('/^[a-z0-9\-]+\.svg$/i', $name)) {
                        continue;
                    }
                    $files[] = strtolower($name);
                }
            }
        }

        // 常用图标靠前，其余按文件名排序
        $preferred = array(
            'image.svg', 'video.svg', 'music.svg', 'tool.svg', 'ai.svg',
            'search.svg', 'weather.svg', 'live.svg', 'hot.svg',
            'douyin.svg', 'kuaishou.svg', 'bilibili.svg', 'wechat.svg', 'qq.svg',
            'api.svg', 'map.svg', 'express.svg', 'lottery.svg', 'random.svg',
        );
        $preferredSet = array_fill_keys($preferred, true);
        $head = array();
        foreach ($preferred as $name) {
            if (in_array($name, $files, true)) {
                $head[] = $name;
            }
        }
        $tail = array();
        foreach ($files as $name) {
            if (!isset($preferredSet[$name])) {
                $tail[] = $name;
            }
        }
        sort($tail, SORT_STRING);

        $paths = array();
        foreach (array_merge($head, $tail) as $name) {
            $paths[] = '/assets/img/category-icons/' . $name;
        }

        $cached = $paths;
        return $cached;
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
                    ORDER BY c.`sort` ASC, c.`id` ASC';
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
                'INSERT INTO `' . self::table() . '` (`name`, `icon`, `description`, `sort`, `status`, `createtime`)
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
                 SET `name` = ?, `icon` = ?, `description` = ?, `updatetime` = NOW()
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
                'UPDATE `' . self::table() . '` SET `status` = ?, `updatetime` = NOW() WHERE `id` = ?'
            );
            $stmt->execute(array($status, $id));
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            return '操作失败，请稍后重试';
        }
    }

    /**
     * 除指定 id 外的全部分类（formatRow 格式）
     *
     * @param int $excludeId
     * @return array
     */
    public static function listOthers($excludeId)
    {
        $excludeId = (int) $excludeId;
        if ($excludeId <= 0 || !self::tableReady()) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '` WHERE `id` != ? ORDER BY `id` DESC'
            );
            $stmt->execute(array($excludeId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return array();
            }

            $out = array();
            foreach ($rows as $row) {
                $formatted = self::formatRow($row);
                if ($formatted !== null) {
                    $out[] = $formatted;
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 将接口迁移至目标分类后删除源分类
     *
     * @param int $id
     * @param int $targetId
     * @return true|string
     */
    public static function deleteAndMove($id, $targetId)
    {
        $id = (int) $id;
        $targetId = (int) $targetId;
        if ($id <= 0 || $targetId <= 0) {
            return '无效操作';
        }
        if ($id === $targetId) {
            return '不能转移到自身';
        }

        $row = self::findById($id);
        if (!$row) {
            return '分类不存在';
        }

        $target = self::findById($targetId);
        if (!$target) {
            return '目标分类不存在';
        }

        $oldName = (string) $row['name'];
        $targetName = (string) $target['name'];

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $apiStmt = $pdo->prepare(
                'UPDATE `' . ApiManager::table() . '` SET `category` = ? WHERE `category` = ?'
            );
            $apiStmt->execute(array($targetName, $oldName));

            $delStmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `id` = ?');
            $delStmt->execute(array($id));

            $pdo->commit();
            RedisCache::invalidateFrontend();
            return true;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return '删除失败，请稍后重试';
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
     * 规范化图标入库值（本地 SVG 路径或外链）
     *
     * @param string $icon
     * @return string|false
     */
    public static function normalizeIconInput($icon)
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
