<?php
/**
 * 文件：core/DatabaseInstaller.php
 * 作用：读取 install/database.sql 并执行建表
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class DatabaseInstaller
{
    /**
     * SQL 文件路径
     *
     * @return string
     */
    public static function sqlFile()
    {
        return VS_ROOT . '/install/database.sql';
    }

    /**
     * SQL 文件是否存在且可读
     *
     * @return bool
     */
    public static function sqlFileExists()
    {
        $file = self::sqlFile();
        return is_file($file) && is_readable($file);
    }

    /**
     * 执行 database.sql 创建数据表
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @param string $dbname
     * @param bool   $clearFirst
     * @return void
     * @throws Exception
     */
    public static function install(PDO $pdo, $prefix, $dbname, $clearFirst = false)
    {
        if (!self::sqlFileExists()) {
            throw new Exception('install/database.sql 文件不存在或不可读');
        }

        if ($clearFirst) {
            self::dropExistingTables($pdo, $prefix, $dbname);
        }

        $sql = file_get_contents(self::sqlFile());
        $sql = str_replace('{prefix}', $prefix, $sql);
        $statements = self::parseSqlStatements($sql);

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * 删除已有相关数据表
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @param string $dbname
     * @return void
     */
    public static function dropExistingTables(PDO $pdo, $prefix, $dbname)
    {
        $prefix = (string) $prefix;
        if ($prefix === '') {
            throw new Exception('数据表前缀不能为空');
        }

        $tables = self::getExistingTables($pdo, $prefix, $dbname);
        foreach ($tables as $table) {
            $table = (string) $table;
            // DROP 前再次按字面前缀校验，防止元数据查询结果误匹配共享库表。
            if (substr($table, 0, strlen($prefix)) !== $prefix) {
                continue;
            }
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        }
    }

    /**
     * 获取已有数据表
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @param string $dbname
     * @return array
     */
    public static function getExistingTables(PDO $pdo, $prefix, $dbname)
    {
        if ($dbname === '') {
            return array();
        }

        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? ESCAPE \'\\\\\''
        );
        // 前缀是字面量；必须转义 \, % 和 _，避免共享库中相似表名被纳入清表范围。
        $literalPrefix = addcslashes((string) $prefix, "\\\\%_");
        $stmt->execute(array($dbname, $literalPrefix . '%'));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tables = array();
        foreach ($rows as $row) {
            $tables[] = $row['TABLE_NAME'];
        }
        return $tables;
    }

    /**
     * 解析 SQL 语句（安装与迁移共用）
     *
     * @param string $sql
     * @return array
     */
    public static function parseSqlStatements($sql)
    {
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $parts = preg_split('/;\s*\n/', $sql);
        $statements = array();

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $statements[] = $part;
            }
        }

        return $statements;
    }
}
