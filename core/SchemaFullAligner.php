<?php
/**
 * 文件：core/SchemaFullAligner.php
 * 作用：对照 install/database.sql 终态结构，对线上库做「只补不删」的全量结构对齐
 *
 * 规则：缺表 CREATE；缺列 ADD；缺索引 ADD；NOT NULL 无默认值且模板有可移植字面量 DEFAULT 时仅 ALTER COLUMN SET DEFAULT。
 * 禁止 DROP / 禁止整列 MODIFY / 禁止执行非 CREATE TABLE / 不处理业务数据与配置种子。
 * 列定义 / 索引片段若含分号「;」则中止（防夹带第二条语句；表末尾整句分号不在此片段内）。
 * 异常对外固定句，细节仅写服务器日志。
 */

class SchemaFullAligner
{
    /**
     * 执行全量结构对齐
     *
     * @return array{ok:bool,msg:string,tables:int,columns:int,indexes:int,defaults:int,details:array}
     */
    public static function align()
    {
        if (!InstallChecker::isInstalled()) {
            return array(
                'ok' => false,
                'msg' => '系统未安装',
                'tables' => 0,
                'columns' => 0,
                'indexes' => 0,
                'defaults' => 0,
                'details' => array(),
            );
        }

        if (!DatabaseInstaller::sqlFileExists()) {
            return array(
                'ok' => false,
                'msg' => '缺少 install/database.sql，无法全量对齐',
                'tables' => 0,
                'columns' => 0,
                'indexes' => 0,
                'defaults' => 0,
                'details' => array(),
            );
        }

        $prefix = Database::prefix();
        $sql = file_get_contents(DatabaseInstaller::sqlFile());
        $sql = str_replace('{prefix}', $prefix, $sql);
        $statements = DatabaseInstaller::parseSqlStatements($sql);

        $createdTables = 0;
        $addedColumns = 0;
        $addedIndexes = 0;
        $fixedDefaults = 0;
        $details = array();

        try {
            $pdo = Database::connect();

            foreach ($statements as $statement) {
                if (!preg_match('/^\s*CREATE\s+TABLE/i', $statement)) {
                    continue;
                }

                $parsed = self::parseCreateTable($statement, $prefix);
                if ($parsed === null) {
                    continue;
                }

                $fullTable = $parsed['full'];
                $short = $parsed['short'];

                // 仅允许安全标识符（来自包内 SQL，仍做白名单）
                if (!preg_match('/^[A-Za-z0-9_]+$/', $fullTable)
                    || !preg_match('/^[A-Za-z0-9_]+$/', $short)) {
                    continue;
                }

                if (!DatabaseMigrator::tableExists($short)) {
                    DatabaseMigrator::execStatement($pdo, $statement);
                    $createdTables++;
                    $details[] = '新建表 ' . $short;
                    continue;
                }

                $liveCols = self::listLiveColumns($pdo, $fullTable);
                $prevCol = null;
                foreach ($parsed['columns'] as $col) {
                    $name = $col['name'];
                    $definition = isset($col['definition']) ? (string) $col['definition'] : '';
                    if (self::fragmentHasStatementSeparator($definition)) {
                        return self::unsafeTemplateAbort(
                            $createdTables,
                            $addedColumns,
                            $addedIndexes,
                            $fixedDefaults,
                            $details,
                            '表 ' . $short . ' 字段 ' . $name . ' 定义含分号，已中止'
                        );
                    }
                    if (!isset($liveCols[$name])) {
                        $after = ($prevCol !== null && isset($liveCols[$prevCol]))
                            ? (' AFTER `' . str_replace('`', '``', $prevCol) . '`')
                            : '';
                        $ddl = 'ALTER TABLE `' . str_replace('`', '``', $fullTable) . '`'
                            . ' ADD COLUMN ' . $definition . $after;
                        DatabaseMigrator::execStatement($pdo, $ddl);
                        $addedColumns++;
                        $details[] = '表 ' . $short . ' 新增字段 ' . $name;
                        $liveCols[$name] = array(
                            'Field' => $name,
                            'Null' => (stripos($definition, 'NOT NULL') !== false) ? 'NO' : 'YES',
                            'Default' => null,
                            'Extra' => '',
                        );
                    } elseif (self::needsDefaultFix($liveCols[$name], $definition)) {
                        $defaultExpr = self::extractDefaultExpression($definition);
                        if ($defaultExpr === null) {
                            $details[] = '表 ' . $short . ' 字段 ' . $name . ' 需补默认值但未能安全解析，已跳过';
                        } else {
                            // 只 SET DEFAULT，禁止整列 MODIFY（避免类型缩回截断数据，E288）
                            $ddl = 'ALTER TABLE `' . str_replace('`', '``', $fullTable) . '`'
                                . ' ALTER COLUMN `' . str_replace('`', '``', $name) . '`'
                                . ' SET DEFAULT ' . $defaultExpr;
                            DatabaseMigrator::execStatement($pdo, $ddl);
                            $fixedDefaults++;
                            $details[] = '表 ' . $short . ' 字段 ' . $name . ' 补齐默认值';
                        }
                    }
                    $prevCol = $name;
                }

                $liveIndexes = self::listLiveIndexNames($pdo, $fullTable);
                foreach ($parsed['indexes'] as $idx) {
                    $keyName = $idx['name'];
                    $columnsSql = isset($idx['columns_sql']) ? (string) $idx['columns_sql'] : '';
                    if (self::fragmentHasStatementSeparator($columnsSql)) {
                        return self::unsafeTemplateAbort(
                            $createdTables,
                            $addedColumns,
                            $addedIndexes,
                            $fixedDefaults,
                            $details,
                            '表 ' . $short . ' 索引 ' . $keyName . ' 片段含分号，已中止'
                        );
                    }
                    if ($keyName === 'PRIMARY') {
                        if (isset($liveIndexes['PRIMARY'])) {
                            continue;
                        }
                        $ddl = 'ALTER TABLE `' . str_replace('`', '``', $fullTable) . '`'
                            . ' ADD PRIMARY KEY ' . $columnsSql;
                    } else {
                        if (isset($liveIndexes[$keyName])) {
                            continue;
                        }
                        $ddl = 'ALTER TABLE `' . str_replace('`', '``', $fullTable) . '`'
                            . ' ADD ' . $idx['kind'] . ' `' . str_replace('`', '``', $keyName) . '` '
                            . $columnsSql;
                    }
                    DatabaseMigrator::execStatement($pdo, $ddl);
                    $addedIndexes++;
                    $details[] = '表 ' . $short . ' 新增索引 ' . $keyName;
                    $liveIndexes[$keyName] = true;
                }
            }
        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'msg' => self::safeErrorMessage($e),
                'tables' => $createdTables,
                'columns' => $addedColumns,
                'indexes' => $addedIndexes,
                'defaults' => $fixedDefaults,
                'details' => $details,
            );
        }

        $total = $createdTables + $addedColumns + $addedIndexes + $fixedDefaults;
        if ($total === 0) {
            return array(
                'ok' => true,
                'msg' => '当前库表结构已与标准模板一致，无需修改。',
                'tables' => 0,
                'columns' => 0,
                'indexes' => 0,
                'defaults' => 0,
                'details' => array(),
            );
        }

        $msg = '全量对齐已完成。本次补齐：表 ' . $createdTables
            . '、字段 ' . $addedColumns
            . '、索引 ' . $addedIndexes
            . '、默认值 ' . $fixedDefaults . '。';

        return array(
            'ok' => true,
            'msg' => $msg,
            'tables' => $createdTables,
            'columns' => $addedColumns,
            'indexes' => $addedIndexes,
            'defaults' => $fixedDefaults,
            'details' => $details,
        );
    }

    /**
     * @param string $statement
     * @param string $prefix
     * @return array|null
     */
    private static function parseCreateTable($statement, $prefix)
    {
        if (!preg_match(
            '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`\s*\((.*)\)\s*ENGINE/is',
            $statement,
            $m
        )) {
            return null;
        }

        $full = $m[1];
        $short = $full;
        if ($prefix !== '' && strpos($full, $prefix) === 0) {
            $short = substr($full, strlen($prefix));
        }

        $parts = self::splitCreateBody($m[2]);
        $columns = array();
        $indexes = array();

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^PRIMARY\s+KEY\s*(\(.+\))$/i', $part, $im)) {
                $indexes[] = array(
                    'name' => 'PRIMARY',
                    'kind' => 'PRIMARY KEY',
                    'columns_sql' => $im[1],
                );
                continue;
            }

            if (preg_match('/^(UNIQUE\s+KEY|UNIQUE\s+INDEX|KEY|INDEX)\s+`([^`]+)`\s*(\(.+\))$/i', $part, $im)) {
                $rawKind = strtoupper(preg_replace('/\s+/', ' ', trim($im[1])));
                $kind = (strpos($rawKind, 'UNIQUE') === 0) ? 'UNIQUE KEY' : 'KEY';
                $indexes[] = array(
                    'name' => $im[2],
                    'kind' => $kind,
                    'columns_sql' => $im[3],
                );
                continue;
            }

            if (preg_match('/^`([^`]+)`\s+/', $part, $cm)) {
                $columns[] = array(
                    'name' => $cm[1],
                    'definition' => $part,
                );
            }
        }

        return array(
            'full' => $full,
            'short' => $short,
            'columns' => $columns,
            'indexes' => $indexes,
        );
    }

    /**
     * @param string $body
     * @return array
     */
    private static function splitCreateBody($body)
    {
        $parts = array();
        $buf = '';
        $depth = 0;
        $inStr = false;
        $strChar = '';
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];

            if ($inStr) {
                $buf .= $ch;
                if ($ch === $strChar) {
                    if ($strChar === "'" && isset($body[$i + 1]) && $body[$i + 1] === "'") {
                        $buf .= $body[$i + 1];
                        $i++;
                        continue;
                    }
                    $inStr = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $inStr = true;
                $strChar = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === '(') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $buf .= $ch;
                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $trim = trim($buf);
                if ($trim !== '') {
                    $parts[] = $trim;
                }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        $trim = trim($buf);
        if ($trim !== '') {
            $parts[] = $trim;
        }

        return $parts;
    }

    /**
     * @param PDO    $pdo
     * @param string $fullTable
     * @return array
     */
    private static function listLiveColumns(PDO $pdo, $fullTable)
    {
        $out = array();
        $stmt = $pdo->query('SHOW FULL COLUMNS FROM `' . str_replace('`', '``', $fullTable) . '`');
        if (!$stmt) {
            return $out;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['Field'])) {
                continue;
            }
            $out[(string) $row['Field']] = $row;
        }
        return $out;
    }

    /**
     * @param PDO    $pdo
     * @param string $fullTable
     * @return array
     */
    private static function listLiveIndexNames(PDO $pdo, $fullTable)
    {
        $out = array();
        $stmt = $pdo->query('SHOW INDEX FROM `' . str_replace('`', '``', $fullTable) . '`');
        if (!$stmt) {
            return $out;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['Key_name'])) {
                continue;
            }
            $out[(string) $row['Key_name']] = true;
        }
        return $out;
    }

    /**
     * 线上列为 NOT NULL 且无默认值，模板定义含 DEFAULT 时补默认值
     *
     * @param array  $liveRow
     * @param string $definition
     * @return bool
     */
    private static function needsDefaultFix(array $liveRow, $definition)
    {
        $null = isset($liveRow['Null']) ? strtoupper((string) $liveRow['Null']) : 'YES';
        $extra = isset($liveRow['Extra']) ? strtolower((string) $liveRow['Extra']) : '';
        if ($null !== 'NO') {
            return false;
        }
        if (strpos($extra, 'auto_increment') !== false) {
            return false;
        }
        // MySQL：无 DEFAULT 时 Default 为 null；有 DEFAULT NULL 时也为 null，但 Null=YES
        if (array_key_exists('Default', $liveRow) && $liveRow['Default'] !== null) {
            return false;
        }
        if (!preg_match('/\bDEFAULT\b/i', $definition)) {
            return false;
        }
        // 模板 DEFAULT NULL 对 NOT NULL 无意义，跳过
        if (preg_match('/\bDEFAULT\s+NULL\b/i', $definition)) {
            return false;
        }
        return true;
    }

    /**
     * 从列定义中提取 DEFAULT 表达式（供 ALTER ... SET DEFAULT，禁止整列 MODIFY）
     *
     * @param string $definition
     * @return string|null
     */
    private static function extractDefaultExpression($definition)
    {
        // 仅允许可移植字面量：数字 / 单引号串 / TRUE|FALSE / b'...'
        // 跳过 CURRENT_TIMESTAMP / 括号表达式（MySQL 5.7 与 8.x SET DEFAULT 语法不一致）
        if (!preg_match(
            '/\bDEFAULT\s+('
            . 'TRUE|FALSE'
            . '|[-+]?\d+(?:\.\d+)?'
            . '|\'(?:\\\\\'|[^\'])*\''
            . '|b\'[01]+\''
            . ')/i',
            $definition,
            $m
        )) {
            return null;
        }
        $expr = trim($m[1]);
        if ($expr === '' || stripos($expr, ';') !== false) {
            return null;
        }
        return $expr;
    }

    /**
     * 列定义 / 索引列清单片段是否含语句分隔符（分号）
     * 说明：检查的是括号内切出的片段，不是 CREATE TABLE 整句末尾的分号。
     *
     * @param string $fragment
     * @return bool
     */
    private static function fragmentHasStatementSeparator($fragment)
    {
        return strpos((string) $fragment, ';') !== false;
    }

    /**
     * 模板片段不安全时中止（对外固定句；细节进 details + 日志）
     *
     * @param int    $tables
     * @param int    $columns
     * @param int    $indexes
     * @param int    $defaults
     * @param array  $details
     * @param string $detailLine
     * @return array
     */
    private static function unsafeTemplateAbort($tables, $columns, $indexes, $defaults, array $details, $detailLine)
    {
        $details[] = $detailLine;
        @error_log('[SchemaFullAligner] ' . $detailLine);
        return array(
            'ok' => false,
            'msg' => '全量对齐失败，请查看服务器日志',
            'tables' => (int) $tables,
            'columns' => (int) $columns,
            'indexes' => (int) $indexes,
            'defaults' => (int) $defaults,
            'details' => $details,
        );
    }

    /**
     * 对外固定失败句；原始异常只写服务器日志（避免 SQL / 表名进浏览器）
     *
     * @param Throwable $e
     * @return string
     */
    private static function safeErrorMessage(Throwable $e)
    {
        $raw = trim($e->getMessage());
        if ($raw !== '') {
            if (strlen($raw) > 500) {
                $raw = substr($raw, 0, 500) . '…';
            }
            @error_log('[SchemaFullAligner] ' . $raw);
        } else {
            @error_log('[SchemaFullAligner] ' . get_class($e) . ' (empty message)');
        }
        return '全量对齐失败，请查看服务器日志';
    }
}
