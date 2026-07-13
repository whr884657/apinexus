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
                    ORDER BY c.`sort_order` ASC, c.`id` ASC';
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
        $rows = self::listAll();
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) $row['status'] !== 1) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
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
     * @param int    $sortOrder
     * @return array{id:int,name:string,sort_order:int,status:int}|string
     */
    public static function create($name, $sortOrder = 0)
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

        $sortOrder = (int) $sortOrder;

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '` (`name`, `sort_order`, `status`, `created_at`)
                 VALUES (?, ?, 1, NOW())'
            );
            $stmt->execute(array($name, $sortOrder));
            $id = (int) $pdo->lastInsertId();
            return array(
                'id'         => $id,
                'name'       => $name,
                'sort_order' => $sortOrder,
                'status'     => 1,
            );
        } catch (Exception $e) {
            return '添加失败，请稍后重试';
        }
    }

    /**
     * @param int    $id
     * @param string $name
     * @param int    $sortOrder
     * @return true|string
     */
    public static function update($id, $name, $sortOrder = null)
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

        $oldName = (string) $row['name'];
        $sortOrder = $sortOrder === null ? (int) $row['sort_order'] : (int) $sortOrder;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `name` = ?, `sort_order` = ?, `updated_at` = NOW()
                 WHERE `id` = ?'
            );
            $stmt->execute(array($name, $sortOrder, $id));

            if ($oldName !== $name) {
                $apiStmt = $pdo->prepare(
                    'UPDATE `' . ApiManager::table() . '` SET `category` = ? WHERE `category` = ?'
                );
                $apiStmt->execute(array($name, $oldName));
            }

            $pdo->commit();
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
     * @param string $name
     * @return string
     */
    private static function normalizeName($name)
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }
}
