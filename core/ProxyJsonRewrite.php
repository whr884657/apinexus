<?php
/**
 * 文件：core/ProxyJsonRewrite.php
 * 作用：代理接口返回 JSON 的字段级改写（仅 JSON；设置 / 删除 / 覆盖）
 *
 * 配置存库字段：api.jsonrewrite（JSON 文本）
 * 形状：{"on":1,"ops":[{"op":"set","path":"api_info.developer","value":"尋鯨錄"},{"op":"del","path":"api_info.blog"}]}
 *
 * 安全约束：
 *   - 规则仅来自库内配置（管理员/投稿者保存），绝不信任调用方请求参数
 *   - 禁止 __proto__ / constructor / prototype 路径段，防原型污染
 *   - 限制条数、深度、体积；非法配置视为关闭
 *   - 改写失败 fail-open：原样回传上游正文（稳定性）
 *   - 非 JSON（TXT/二进制/HTML 等）一律不处理
 */

class ProxyJsonRewrite
{
    /** 最多操作条数 */
    const MAX_OPS = 40;

    /** 路径最大深度 */
    const MAX_PATH_DEPTH = 12;

    /** 单段路径最大长度 */
    const MAX_SEGMENT_LEN = 64;

    /** 整份配置入库上限（字节） */
    const MAX_CONFIG_BYTES = 65536;

    /** 单条 value JSON 编码后上限（字节） */
    const MAX_VALUE_BYTES = 16384;

    /** 路径总长上限 */
    const MAX_PATH_LEN = 256;

    /**
     * 是否已具备 jsonrewrite 字段（迁移 13.12.0 后为 true）
     *
     * @return bool
     */
    public static function hasColumn()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            if (!class_exists('ApiManager') || !ApiManager::tableReady()) {
                $ok = false;
                return $ok;
            }
            $pdo = Database::connect();
            $col = $pdo->query('SHOW COLUMNS FROM `' . ApiManager::table() . '` LIKE ' . $pdo->quote('jsonrewrite'));
            $ok = $col && $col->fetchColumn();
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 规范化并序列化配置（供入库）
     *
     * @param mixed $raw 字符串 JSON 或数组
     * @return string 合法 JSON；关闭或空则为 ''
     */
    public static function normalizeConfig($raw)
    {
        $cfg = self::parseConfig($raw);
        if ($cfg === null) {
            return '';
        }
        if (empty($cfg['on']) || empty($cfg['ops'])) {
            return '';
        }
        // value 还原为可干净序列化的 JSON 节点（空对象 → {}）
        foreach ($cfg['ops'] as $i => $op) {
            if (!is_array($op) || !isset($op['op'])) {
                continue;
            }
            if ($op['op'] === 'set' && array_key_exists('value', $op)) {
                $cfg['ops'][$i]['value'] = self::valueToJsonNode($op['value']);
            }
        }
        $enc = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($enc) || $enc === '' || strlen($enc) > self::MAX_CONFIG_BYTES) {
            return '';
        }
        return $enc;
    }

    /**
     * 解析配置为规范化数组
     *
     * @param mixed $raw
     * @return array{on:int,ops:array}|null
     */
    public static function parseConfig($raw)
    {
        if (is_array($raw)) {
            $data = $raw;
        } elseif (is_object($raw)) {
            $data = self::objectToArrayPreserveEmpty($raw);
        } else {
            $str = trim((string) $raw);
            if ($str === '' || strlen($str) > self::MAX_CONFIG_BYTES) {
                return null;
            }
            // 不用 assoc=true，避免 {"a":{}} 被弄丢空对象语义；再转安全数组供校验
            $decoded = json_decode($str);
            if (!is_object($decoded) && !is_array($decoded)) {
                return null;
            }
            $data = self::objectToArrayPreserveEmpty($decoded);
            if (!is_array($data)) {
                return null;
            }
        }

        $on = 0;
        if (isset($data['on'])) {
            $on = ((int) $data['on'] === 1) ? 1 : 0;
        } elseif (isset($data['enabled'])) {
            $on = ((int) $data['enabled'] === 1) ? 1 : 0;
        }

        $opsIn = isset($data['ops']) && is_array($data['ops']) ? $data['ops'] : array();
        $ops = array();
        foreach ($opsIn as $item) {
            if (count($ops) >= self::MAX_OPS) {
                break;
            }
            if ($item instanceof stdClass) {
                $item = self::objectToArrayPreserveEmpty($item);
            }
            if (!is_array($item)) {
                continue;
            }
            $norm = self::normalizeOp($item);
            if ($norm !== null) {
                $ops[] = $norm;
            }
        }

        return array(
            'on'  => $on,
            'ops' => $ops,
        );
    }

    /**
     * stdClass / 嵌套结构 → 数组；空对象保留为标记数组 ['__vs_obj' => true]
     *
     * @param mixed $node
     * @return mixed
     */
    private static function objectToArrayPreserveEmpty($node)
    {
        if ($node instanceof stdClass) {
            $vars = get_object_vars($node);
            if ($vars === array()) {
                return array('__vs_obj' => true);
            }
            $out = array();
            foreach ($vars as $k => $v) {
                $out[$k] = self::objectToArrayPreserveEmpty($v);
            }
            return $out;
        }
        if (is_array($node)) {
            $out = array();
            foreach ($node as $k => $v) {
                $out[$k] = self::objectToArrayPreserveEmpty($v);
            }
            return $out;
        }
        return $node;
    }

    /**
     * 配置 value → 可 json_encode 的节点（空对象标记还原为 stdClass）
     *
     * @param mixed $value
     * @return mixed
     */
    private static function valueToJsonNode($value)
    {
        if (is_array($value)) {
            if (isset($value['__vs_obj']) && $value['__vs_obj'] === true && count($value) === 1) {
                return new stdClass();
            }
            $isList = ($value === array()) || (array_keys($value) === range(0, count($value) - 1));
            if ($isList) {
                $out = array();
                foreach ($value as $v) {
                    $out[] = self::valueToJsonNode($v);
                }
                return $out;
            }
            $obj = new stdClass();
            foreach ($value as $k => $v) {
                if ($k === '__vs_obj') {
                    continue;
                }
                $obj->{$k} = self::valueToJsonNode($v);
            }
            return $obj;
        }
        return $value;
    }

    /**
     * @param array $item
     * @return array{op:string,path:string,value?:mixed}|null
     */
    private static function normalizeOp(array $item)
    {
        $op = isset($item['op']) ? strtolower(trim((string) $item['op'])) : '';
        if ($op === 'replace' || $op === 'add' || $op === 'put') {
            $op = 'set';
        }
        if ($op === 'remove' || $op === 'delete' || $op === 'unset') {
            $op = 'del';
        }
        if ($op !== 'set' && $op !== 'del') {
            return null;
        }

        $path = isset($item['path']) ? trim((string) $item['path']) : '';
        if ($path === '' && isset($item['key'])) {
            $path = trim((string) $item['key']);
        }
        $segments = self::parsePath($path);
        if ($segments === null) {
            return null;
        }
        $pathNorm = implode('.', $segments);

        if ($op === 'del') {
            return array('op' => 'del', 'path' => $pathNorm);
        }

        if (!array_key_exists('value', $item)) {
            return null;
        }
        $value = $item['value'];
        if (!self::isSafeValue($value)) {
            return null;
        }
        $probe = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (!is_string($probe) || strlen($probe) > self::MAX_VALUE_BYTES) {
            return null;
        }

        return array(
            'op'    => 'set',
            'path'  => $pathNorm,
            'value' => $value,
        );
    }

    /**
     * 解析点分路径；非法返回 null
     *
     * @param string $path
     * @return string[]|null
     */
    public static function parsePath($path)
    {
        $path = trim((string) $path);
        if ($path === '' || strlen($path) > self::MAX_PATH_LEN) {
            return null;
        }
        // 允许 a.b / a.0.b；禁止 / 与空段
        if (strpos($path, '/') !== false || strpos($path, '..') !== false) {
            return null;
        }
        $parts = explode('.', $path);
        if (!is_array($parts) || count($parts) === 0 || count($parts) > self::MAX_PATH_DEPTH) {
            return null;
        }
        $out = array();
        foreach ($parts as $seg) {
            $seg = (string) $seg;
            if ($seg === '' || strlen($seg) > self::MAX_SEGMENT_LEN) {
                return null;
            }
            $lower = strtolower($seg);
            if ($lower === '__proto__' || $lower === 'constructor' || $lower === 'prototype') {
                return null;
            }
            // 仅字母数字下划线短横线，或纯数字下标
            if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_-]*|[0-9]+)$/', $seg)) {
                return null;
            }
            $out[] = $seg;
        }
        return $out;
    }

    /**
     * value 仅允许 JSON 标量 / 数组 / 对象（关联或列表），禁止资源等
     *
     * @param mixed $value
     * @param int   $depth
     * @return bool
     */
    private static function isSafeValue($value, $depth = 0)
    {
        if ($depth > self::MAX_PATH_DEPTH) {
            return false;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }
        if (is_string($value)) {
            return strlen($value) <= self::MAX_VALUE_BYTES;
        }
        if (!is_array($value)) {
            return false;
        }
        if (isset($value['__vs_obj']) && $value['__vs_obj'] === true && count($value) === 1) {
            return true;
        }
        if (count($value) > 200) {
            return false;
        }
        foreach ($value as $k => $v) {
            if (is_string($k)) {
                $kl = strtolower($k);
                if ($kl === '__proto__' || $kl === 'constructor' || $kl === 'prototype') {
                    return false;
                }
                if (strlen($k) > self::MAX_SEGMENT_LEN) {
                    return false;
                }
            } elseif (!is_int($k)) {
                return false;
            }
            if (!self::isSafeValue($v, $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 判断正文是否应按 JSON 处理
     *
     * @param string $body
     * @param string $contentType
     * @return bool
     */
    public static function looksLikeJson($body, $contentType = '')
    {
        $body = (string) $body;
        if ($body === '') {
            return false;
        }
        $ct = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        if ($ct === 'application/json' || $ct === 'text/json'
            || substr($ct, -5) === '+json'
            || strpos($ct, 'json') !== false) {
            return true;
        }
        $trim = ltrim($body);
        if ($trim === '') {
            return false;
        }
        $c0 = $trim[0];
        return ($c0 === '{' || $c0 === '[');
    }

    /**
     * 对上游响应正文应用改写
     *
     * @param string $body
     * @param string $contentType
     * @param string $configJson 库内 jsonrewrite
     * @return array{body:string,contentType:string,changed:bool}
     */
    public static function apply($body, $contentType, $configJson)
    {
        $body = (string) $body;
        $contentType = (string) $contentType;
        $out = array(
            'body'        => $body,
            'contentType' => $contentType,
            'changed'     => false,
        );

        $cfg = self::parseConfig($configJson);
        if ($cfg === null || empty($cfg['on']) || empty($cfg['ops'])) {
            return $out;
        }
        if (!self::looksLikeJson($body, $contentType)) {
            return $out;
        }

        $data = json_decode($body);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return $out;
        }
        // 仅对象或数组根；标量 JSON 不改
        if (!is_array($data) && !($data instanceof stdClass)) {
            return $out;
        }

        $mutated = false;
        foreach ($cfg['ops'] as $op) {
            if (!is_array($op) || !isset($op['op'], $op['path'])) {
                continue;
            }
            $segments = self::parsePath($op['path']);
            if ($segments === null) {
                continue;
            }
            if ($op['op'] === 'del') {
                if (self::deleteAt($data, $segments)) {
                    $mutated = true;
                }
            } elseif ($op['op'] === 'set' && array_key_exists('value', $op)) {
                $node = self::valueToJsonNode($op['value']);
                if (self::setAt($data, $segments, $node)) {
                    $mutated = true;
                }
            }
        }

        if (!$mutated) {
            return $out;
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $enc = json_encode($data, $flags);
        if (!is_string($enc) || $enc === '') {
            return $out;
        }
        // 改写后体积仍须受中继上限约束（与 ApiProxy::RELAY_MAX_BODY 对齐）
        if (strlen($enc) > 16777216) {
            return $out;
        }

        $out['body'] = $enc;
        $out['contentType'] = 'application/json; charset=utf-8';
        $out['changed'] = true;
        return $out;
    }

    /**
     * @param array  $data
     * @param string $configJson
     * @return array
     */
    public static function applyToData(array $data, $configJson)
    {
        $enc = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!is_string($enc)) {
            return $data;
        }
        $r = self::apply($enc, 'application/json', $configJson);
        if (empty($r['changed'])) {
            return $data;
        }
        $decoded = json_decode($r['body'], true);
        return is_array($decoded) ? $decoded : $data;
    }

    /**
     * @param array|stdClass $root
     * @param string[]       $segments
     * @param mixed          $value
     * @return bool
     */
    private static function setAt(&$root, array $segments, $value)
    {
        $n = count($segments);
        if ($n === 0) {
            return false;
        }
        $cur = &$root;
        for ($i = 0; $i < $n - 1; $i++) {
            $key = $segments[$i];
            $isIndex = self::isListIndexKey($key);
            if ($isIndex) {
                $idx = (int) $key;
                if (!is_array($cur)) {
                    return false;
                }
                if (!array_key_exists($idx, $cur) || (!is_array($cur[$idx]) && !($cur[$idx] instanceof stdClass))) {
                    $cur[$idx] = new stdClass();
                }
                $cur = &$cur[$idx];
            } else {
                if ($cur instanceof stdClass) {
                    if (!isset($cur->{$key}) || (!is_array($cur->{$key}) && !($cur->{$key} instanceof stdClass))) {
                        $cur->{$key} = new stdClass();
                    }
                    $cur = &$cur->{$key};
                } elseif (is_array($cur)) {
                    if (!array_key_exists($key, $cur) || (!is_array($cur[$key]) && !($cur[$key] instanceof stdClass))) {
                        $cur[$key] = new stdClass();
                    }
                    $cur = &$cur[$key];
                } else {
                    return false;
                }
            }
        }
        $last = $segments[$n - 1];
        if (self::isListIndexKey($last)) {
            if (!is_array($cur)) {
                return false;
            }
            $cur[(int) $last] = $value;
            return true;
        }
        if ($cur instanceof stdClass) {
            $cur->{$last} = $value;
            return true;
        }
        if (is_array($cur)) {
            $cur[$last] = $value;
            return true;
        }
        return false;
    }

    /**
     * @param array|stdClass $root
     * @param string[]       $segments
     * @return bool
     */
    private static function deleteAt(&$root, array $segments)
    {
        $n = count($segments);
        if ($n === 0) {
            return false;
        }
        $cur = &$root;
        for ($i = 0; $i < $n - 1; $i++) {
            $key = $segments[$i];
            if (self::isListIndexKey($key)) {
                $idx = (int) $key;
                if (!is_array($cur) || !array_key_exists($idx, $cur)) {
                    return false;
                }
                if (!is_array($cur[$idx]) && !($cur[$idx] instanceof stdClass)) {
                    return false;
                }
                $cur = &$cur[$idx];
            } else {
                if ($cur instanceof stdClass) {
                    if (!isset($cur->{$key}) || (!is_array($cur->{$key}) && !($cur->{$key} instanceof stdClass))) {
                        return false;
                    }
                    $cur = &$cur->{$key};
                } elseif (is_array($cur)) {
                    if (!array_key_exists($key, $cur) || (!is_array($cur[$key]) && !($cur[$key] instanceof stdClass))) {
                        return false;
                    }
                    $cur = &$cur[$key];
                } else {
                    return false;
                }
            }
        }
        $last = $segments[$n - 1];
        if (self::isListIndexKey($last)) {
            $idx = (int) $last;
            if (!is_array($cur) || !array_key_exists($idx, $cur)) {
                return false;
            }
            unset($cur[$idx]);
            if (self::isListArray($cur)) {
                $cur = array_values($cur);
            }
            return true;
        }
        if ($cur instanceof stdClass) {
            if (!property_exists($cur, $last)) {
                return false;
            }
            unset($cur->{$last});
            return true;
        }
        if (is_array($cur)) {
            if (!array_key_exists($last, $cur)) {
                return false;
            }
            unset($cur[$last]);
            return true;
        }
        return false;
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isListIndexKey($key)
    {
        return (string) ((int) $key) === (string) $key && (string) $key !== '';
    }

    /**
     * @param array $arr
     * @return bool
     */
    private static function isListArray(array $arr)
    {
        if ($arr === array()) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
