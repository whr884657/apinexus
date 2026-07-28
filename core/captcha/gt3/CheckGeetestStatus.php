<?php
/**
 * 文件：core/captcha/gt3/CheckGeetestStatus.php
 * 作用：极验 3 代云状态检测（官方 bypass；无 Redis 时用 session 缓存）
 *
 * 逻辑对齐官方 CheckGeetestStatus，存储改为 session。
 */

class CheckGeetestStatus
{
    const HTTP_TIMEOUT_DEFAULT = 5;
    const BYPASS_URL = 'https://bypass.geetest.com/v1/bypass_status.php';
    const SESSION_KEY = 'vs_gt3_bypass';
    const CACHE_TTL = 60;

    /**
     * @param string $gtId
     * @return bool true=云正常
     */
    public static function getGeetestStatus($gtId)
    {
        $gtId = trim((string) $gtId);
        if ($gtId === '') {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE
            && isset($_SESSION[self::SESSION_KEY])
            && is_array($_SESSION[self::SESSION_KEY])
        ) {
            $row = $_SESSION[self::SESSION_KEY];
            if (isset($row['gt'], $row['ok'], $row['t'])
                && $row['gt'] === $gtId
                && (time() - (int) $row['t']) < self::CACHE_TTL
            ) {
                return (bool) $row['ok'];
            }
        }
        $ok = self::fetchBypass($gtId);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = array(
                'gt' => $gtId,
                'ok' => $ok ? 1 : 0,
                't'  => time(),
            );
        }
        return $ok;
    }

    /**
     * @param string $gtId
     * @return bool
     */
    private static function fetchBypass($gtId)
    {
        $url = self::BYPASS_URL . '?gt=' . rawurlencode($gtId);
        $body = '';
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $body = (string) curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(array(
                'http' => array('timeout' => self::HTTP_TIMEOUT_DEFAULT),
            ));
            $body = (string) @file_get_contents($url, false, $ctx);
        }
        $arr = json_decode($body, true);
        if (!is_array($arr) || !isset($arr['status'])) {
            return false;
        }
        return strcmp((string) $arr['status'], 'success') === 0;
    }
}
