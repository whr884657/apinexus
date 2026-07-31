<?php
/**
 * 文件：core/AiSse.php
 * 作用：AI 流式 SSE 输出（对抗 CDN/Nginx 缓冲：关缓冲头 + 心跳）
 */

class AiSse
{
    /** @var float */
    private static $lastPingAt = 0;

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
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');
        // 部分 CDN 认此头减少缓冲
        header('Content-Encoding: none');
        self::$lastPingAt = microtime(true);
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
        $now = microtime(true);
        if ($force || ($now - self::$lastPingAt) >= 8) {
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
    }
}
