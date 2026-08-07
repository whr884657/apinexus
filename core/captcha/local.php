<?php
/**
 * 文件：core/captcha/local.php
 * 作用：本地图形验证码（GD；session 存场景绑定哈希；含基础强度）
 */

class CaptchaLocal
{
    const SESSION_HASH = 'vs_captcha_local_h';
    const SESSION_TIME = 'vs_captcha_local_t';
    const SESSION_SCENE = 'vs_captcha_local_s';
    const TTL = 300;
    const LEN = 5;
    const WIDTH = 140;
    const HEIGHT = 44;

    /**
     * @return string
     */
    private static function serverPepper()
    {
        $parts = array(defined('VS_ROOT') ? (string) VS_ROOT : __DIR__);
        if (class_exists('Config')) {
            $parts[] = (string) Config::get('gt4_key', '');
            $parts[] = (string) Config::get('gt3_key', '');
            $parts[] = (string) Config::get('mail_smtp_pass', '');
        }
        return hash('sha256', implode('|', $parts) . '|vs_local_captcha_v3');
    }

    /**
     * 从字符池随机取 1 个字符
     *
     * @param string $pool
     * @return string
     */
    private static function pickChar($pool)
    {
        $pool = (string) $pool;
        $max = strlen($pool) - 1;
        if ($max < 0) {
            return '';
        }
        if (function_exists('random_int')) {
            return $pool[random_int(0, $max)];
        }
        return $pool[mt_rand(0, $max)];
    }

    /**
     * @param string $scene
     * @return string 明文（仅用于绘图，不回传客户端业务接口）
     */
    public static function makeCode($scene = '')
    {
        $scene = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $scene));
        // 排除易混：0/O/o、1/I/l；含大写、小写、数字
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digit = '23456789';
        $pool = $upper . $lower . $digit;

        $chars = array();
        // 至少各一类，再补满长度后打乱（LEN≥3）
        if (self::LEN >= 3) {
            $chars[] = self::pickChar($upper);
            $chars[] = self::pickChar($lower);
            $chars[] = self::pickChar($digit);
        }
        while (count($chars) < self::LEN) {
            $chars[] = self::pickChar($pool);
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = self::randInt(0, $i);
            $tmp = $chars[$i];
            $chars[$i] = $chars[$j];
            $chars[$j] = $tmp;
        }
        $code = implode('', $chars);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_HASH] = self::hashCode($code, $scene);
            $_SESSION[self::SESSION_TIME] = time();
            $_SESSION[self::SESSION_SCENE] = $scene;
        }
        return $code;
    }

    /**
     * @param string $code
     * @param string $scene
     * @return string
     */
    private static function hashCode($code, $scene = '')
    {
        $salt = '';
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $salt = session_id();
        }
        $scene = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $scene));
        // 不区分大小写：统一转大写再哈希（图仍可含大小写混排）
        return hash_hmac(
            'sha256',
            strtoupper(trim((string) $code)) . '|' . $salt . '|' . $scene,
            self::serverPepper()
        );
    }

    /**
     * @param string $scene
     * @return void
     */
    public static function outputPng($scene = '')
    {
        if (!function_exists('imagecreatetruecolor')) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'GD unavailable';
            exit;
        }
        $code = self::makeCode($scene);
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            header('HTTP/1.1 500 Internal Server Error');
            exit;
        }
        $bg = imagecolorallocate($im, self::randInt(225, 245), self::randInt(225, 245), self::randInt(225, 245));
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        for ($i = 0; $i < 8; $i++) {
            $lc = imagecolorallocate($im, self::randInt(120, 200), self::randInt(120, 200), self::randInt(120, 200));
            imageline($im, self::randInt(0, $w), self::randInt(0, $h), self::randInt(0, $w), self::randInt(0, $h), $lc);
        }
        for ($i = 0; $i < 120; $i++) {
            $dc = imagecolorallocate($im, self::randInt(100, 210), self::randInt(100, 210), self::randInt(100, 210));
            imagesetpixel($im, self::randInt(0, $w - 1), self::randInt(0, $h - 1), $dc);
        }

        $len = strlen($code);
        $slot = (int) ($w / ($len + 1));
        for ($i = 0; $i < $len; $i++) {
            $tc = imagecolorallocate($im, self::randInt(10, 80), self::randInt(10, 80), self::randInt(10, 80));
            $x = $slot * ($i + 1) - 8 + self::randInt(-4, 4);
            $y = self::randInt(10, 18);
            imagestring($im, 5, $x, $y, $code[$i], $tc);
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($im);
        imagedestroy($im);
        exit;
    }

    /**
     * @param int $min
     * @param int $max
     * @return int
     */
    private static function randInt($min, $max)
    {
        if (function_exists('random_int')) {
            return random_int($min, $max);
        }
        return mt_rand($min, $max);
    }

    /**
     * @param string $input
     * @param string $scene
     * @return bool
     */
    public static function verify($input, $scene = '')
    {
        if (!is_string($input) && !is_numeric($input)) {
            return false;
        }
        $input = trim((string) $input);
        $scene = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $scene));
        if ($input === '' || $scene === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!isset($_SESSION[self::SESSION_HASH], $_SESSION[self::SESSION_TIME], $_SESSION[self::SESSION_SCENE])) {
            return false;
        }
        if ((string) $_SESSION[self::SESSION_SCENE] !== $scene) {
            unset($_SESSION[self::SESSION_HASH], $_SESSION[self::SESSION_TIME], $_SESSION[self::SESSION_SCENE]);
            return false;
        }
        if ((time() - (int) $_SESSION[self::SESSION_TIME]) > self::TTL) {
            unset($_SESSION[self::SESSION_HASH], $_SESSION[self::SESSION_TIME], $_SESSION[self::SESSION_SCENE]);
            return false;
        }
        $expect = (string) $_SESSION[self::SESSION_HASH];
        unset($_SESSION[self::SESSION_HASH], $_SESSION[self::SESSION_TIME], $_SESSION[self::SESSION_SCENE]);
        $got = self::hashCode($input, $scene);
        if (function_exists('hash_equals')) {
            return hash_equals($expect, $got);
        }
        return $expect === $got;
    }
}
