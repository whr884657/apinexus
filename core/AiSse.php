<?php
/**
 * 文件：core/AiSse.php
 * 作用：AI 流式 SSE 输出（对抗 CDN/Nginx 缓冲：关缓冲头 + 心跳）
 */

class AiSse
{
    /** @var float */
    private static $lastPingAt = 0;

    /** @var bool 是否已 begin（供 curl 进度回调判断，避免误写到 JSON 响应） */
    private static $active = false;

    /**
     * @return bool
     */
    public static function isActive()
    {
        return self::$active;
    }

    /**
     * @return void
     */
    public static function begin()
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        // no-transform：避免 CDN/代理 gzip 整包再吐；no-store：禁止边缘缓存 SSE
        header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');
        // 部分 CDN 认此头减少缓冲；勿声明 gzip
        header('Content-Encoding: identity');
        header('CDN-Cache-Control: no-store');
        header('Surrogate-Control: no-store');
        self::$active = true;
        self::$lastPingAt = microtime(true);
        // 首包垫片：部分浏览器/边缘会缓冲前 1～2KB 才开始渲染
        self::comment(str_repeat('pad ', 64));
        self::comment('ok');
    }

    /**
     * @param string $event
     * @param array  $data
     * @return void
     */
    public static function emit($event, array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"msg":"encode_error"}';
        }
        echo 'event: ' . preg_replace('/[^a-z0-9_\-]/i', '', (string) $event) . "\n";
        foreach (explode("\n", $json) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        self::flush();
        self::maybePing(true);
    }

    /**
     * SSE 注释心跳（保持 CDN/代理空闲连接）
     *
     * @param string $text
     * @return void
     */
    public static function comment($text = 'ping')
    {
        echo ': ' . str_replace(array("\r", "\n"), ' ', (string) $text) . "\n\n";
        self::flush();
        self::$lastPingAt = microtime(true);
    }

    /**
     * @param bool $force
     * @return void
     */
    public static function maybePing($force = false)
    {
        if (!self::$active) {
            return;
        }
        $now = microtime(true);
        // CDN 空闲掐断常见 10～15 秒；心跳略密一点
        if ($force || ($now - self::$lastPingAt) >= 5) {
            self::comment('ping');
        }
    }

    /**
     * @return void
     */
    public static function flush()
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * @return void
     */
    public static function end()
    {
        self::flush();
        self::$active = false;
    }
}
