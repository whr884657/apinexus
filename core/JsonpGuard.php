<?php
/**
 * 文件：core/JsonpGuard.php
 * 作用：JSONP callback 白名单校验；剥离危险回调参数（防反射型 XSS）
 *
 * 规则（v13.25.0）：
 *   - 仅允许合法 JS 标识符作为回调名：^[A-Za-z_$][A-Za-z0-9_$]{0,63}$
 *   - 禁止括号、点号、空格、分号、斜杠等任意可执行片段
 *   - 代理网关默认剥离 callback/jsonp 等参数，禁止转发给上游
 */

class JsonpGuard
{
    /** 回调名最大长度（含首字符） */
    const MAX_LEN = 64;

    /**
     * 常见 JSONP 参数名（小写）
     *
     * @return string[]
     */
    public static function paramNames()
    {
        return array('callback', 'jsonp', 'jsonpcallback', '_callback', 'cb');
    }

    /**
     * 是否为 JSONP 相关查询参数名
     *
     * @param string $name
     * @return bool
     */
    public static function isJsonpParamName($name)
    {
        $n = strtolower(trim((string) $name));
        return in_array($n, self::paramNames(), true);
    }

    /**
     * 回调名是否安全（仅标识符，防 XSS 注入）
     *
     * @param mixed $name
     * @return bool
     */
    public static function isSafeCallbackName($name)
    {
        if (!is_string($name) && !is_numeric($name)) {
            return false;
        }
        $name = (string) $name;
        if ($name === '' || strlen($name) > self::MAX_LEN) {
            return false;
        }
        // 仅标识符；禁止 alert(1)、a=1;、function(){} 等
        return (bool) preg_match('/^[A-Za-z_$][A-Za-z0-9_$]{0,63}$/', $name);
    }

    /**
     * 规范化安全回调名；非法返回空串
     *
     * @param mixed $name
     * @return string
     */
    public static function sanitizeCallbackName($name)
    {
        $name = is_string($name) || is_numeric($name) ? trim((string) $name) : '';
        return self::isSafeCallbackName($name) ? $name : '';
    }

    /**
     * 从关联数组剥离 JSONP 参数（代理出站用）
     *
     * @param array $params
     * @return array
     */
    public static function stripCallbackParams(array $params)
    {
        $out = array();
        foreach ($params as $k => $v) {
            if (self::isJsonpParamName((string) $k)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * 用安全回调名包装 JSON 正文；非法回调则原样返回 JSON（不注入）
     *
     * @param string $jsonBody 已是合法 JSON 文本
     * @param string $callback 原始回调名
     * @return array{body:string,contentType:string,jsonp:bool}
     */
    public static function wrapJsonIfSafe($jsonBody, $callback)
    {
        $jsonBody = (string) $jsonBody;
        $safe = self::sanitizeCallbackName($callback);
        if ($safe === '') {
            return array(
                'body'        => $jsonBody,
                'contentType' => 'application/json; charset=utf-8',
                'jsonp'       => false,
            );
        }
        return array(
            'body'        => $safe . '(' . $jsonBody . ');',
            'contentType' => 'application/javascript; charset=utf-8',
            'jsonp'       => true,
        );
    }
}
