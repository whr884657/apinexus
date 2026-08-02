<?php
/**
 * 文件：core/ApiOutboundSanitize.php
 * 作用：公开 API / 代理出站 JSON 消毒 —— 去掉后台路径与配置敏感串
 *
 * 背景（v13.25.0 / 扫描 VULN-004）：
 *   上游 JSON 或站点 JSON 改写可能把 /admin/... 管理路径、配置文件名等
 *   写进调用方可见响应，便于攻击者做路径枚举。出站前统一擦除。
 */

class ApiOutboundSanitize
{
    /**
     * 字符串是否含本站敏感路径 / 配置痕迹
     *
     * @param string $s
     * @return bool
     */
    public static function stringLooksSensitive($s)
    {
        $s = (string) $s;
        if ($s === '') {
            return false;
        }
        // 仅匹配路径形态的 /admin…，避免误伤普通文案「admin」
        // 定界符用 ~，避免模式内 # 截断
        if (preg_match('~(?:/|\\\\)admin(?:/|\\\\|\.php|\?|#|$)~i', $s)) {
            return true;
        }
        if (preg_match('~https?://[^\s\'"]+/admin(?:/|[^\s\'"]*)~i', $s)) {
            return true;
        }
        // 配置 / 安装锁定等敏感文件路径
        if (preg_match('~(?:config/database\.php|install\.lock|config/\.security)~i', $s)) {
            return true;
        }
        return false;
    }

    /**
     * SET 改写值是否允许（禁止写入后台 URL / 配置路径）
     *
     * @param mixed $value
     * @param int   $depth
     * @return bool
     */
    public static function isAllowedRewriteValue($value, $depth = 0)
    {
        if ($depth > 12) {
            return false;
        }
        if (is_string($value)) {
            return !self::stringLooksSensitive($value);
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (!self::isAllowedRewriteValue($v, $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 字段名是否像凭证 / 库连接敏感键（出站时值一律清空）
     *
     * @param string|int $key
     * @return bool
     */
    public static function keyLooksCredential($key)
    {
        if (!is_string($key) && !is_int($key)) {
            return false;
        }
        $k = strtolower(trim((string) $key));
        if ($k === '') {
            return false;
        }
        static $exact = array(
            'password', 'passwd', 'pass', 'pwd',
            'dbpass', 'dbpassword', 'db_password', 'mysql_password', 'database_password',
            'dbuser', 'db_user', 'mysql_user', 'database_user',
            'dbhost', 'db_host', 'mysql_host',
            'secret', 'client_secret', 'app_secret', 'api_secret',
            'private_key', 'privatekey',
        );
        if (in_array($k, $exact, true)) {
            return true;
        }
        // 键名含 password / secret 且较短，避免误伤 description 等
        if (strlen($k) <= 40 && (strpos($k, 'password') !== false || strpos($k, 'secret') !== false)) {
            return true;
        }
        return false;
    }

    /**
     * 递归擦除敏感字符串与凭证字段值；敏感标量改为空串
     *
     * @param mixed $node
     * @param int   $depth
     * @return mixed
     */
    public static function scrubNode($node, $depth = 0)
    {
        if ($depth > 24) {
            return $node;
        }
        if (is_string($node)) {
            return self::stringLooksSensitive($node) ? '' : $node;
        }
        if (!is_array($node)) {
            return $node;
        }
        $out = array();
        foreach ($node as $k => $v) {
            if (self::keyLooksCredential($k)) {
                // 凭证类字段：标量清空；嵌套对象/数组仍递归擦除内容
                if (is_array($v)) {
                    $out[$k] = self::scrubNode($v, $depth + 1);
                } else {
                    $out[$k] = is_string($v) || is_numeric($v) ? '' : $v;
                }
                continue;
            }
            $out[$k] = self::scrubNode($v, $depth + 1);
        }
        return $out;
    }

    /**
     * 若正文为 JSON，擦除敏感字段值后重新编码；非 JSON 原样返回
     *
     * @param string $body
     * @param string $contentType
     * @return array{body:string,changed:bool}
     */
    public static function scrubJsonBody($body, $contentType = '')
    {
        $body = (string) $body;
        if ($body === '') {
            return array('body' => $body, 'changed' => false);
        }
        $trim = ltrim($body);
        $ct = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        $looksJson = ($ct === 'application/json' || $ct === 'text/json'
            || (isset($trim[0]) && ($trim[0] === '{' || $trim[0] === '[')));
        if (!$looksJson) {
            // 正文里若直接嵌了 /admin/ 路径的纯文本，也做一次保守替换
            if (preg_match('~https?://[^\s"\']+/admin/[^\s"\']+~i', $body)) {
                $scrubbed = preg_replace('~https?://[^\s"\']+/admin/[^\s"\']+~i', '', $body);
                if (is_string($scrubbed) && $scrubbed !== $body) {
                    return array('body' => $scrubbed, 'changed' => true);
                }
            }
            return array('body' => $body, 'changed' => false);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return array('body' => $body, 'changed' => false);
        }
        $scrubbed = self::scrubNode($data);
        $enc = json_encode($scrubbed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($enc)) {
            return array('body' => $body, 'changed' => false);
        }
        return array(
            'body'    => $enc,
            'changed' => ($enc !== $body),
        );
    }
}
