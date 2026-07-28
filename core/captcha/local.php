<?php
/**
 * 文件：core/captcha/local.php
 * 作用：本地图形验证码（GD 画布：字母数字 + 噪点线条）
 */

class CaptchaLocal
{
    const SESSION_CODE = 'vs_captcha_local';
    const SESSION_TIME = 'vs_captcha_local_t';
    const TTL = 300;
    const LEN = 4;
    const WIDTH = 120;
    const HEIGHT = 40;

    /**
     * @return string
     */
    public static function makeCode()
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $max = strlen($pool) - 1;
        for ($i = 0; $i < self::LEN; $i++) {
            $code .= $pool[mt_rand(0, $max)];
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_CODE] = strtoupper($code);
            $_SESSION[self::SESSION_TIME] = time();
        }
        return $code;
    }

    /**
     * 输出 PNG 并 exit
     *
     * @return void
     */
    public static function outputPng()
    {
        if (!function_exists('imagecreatetruecolor')) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'GD unavailable';
            exit;
        }
        $code = self::makeCode();
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, mt_rand(230, 255), mt_rand(230, 255), mt_rand(230, 255));
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        for ($i = 0; $i < 6; $i++) {
            $lc = imagecolorallocate($im, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200));
            imageline($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $lc);
        }
        for ($i = 0; $i < 80; $i++) {
            $dc = imagecolorallocate($im, mt_rand(120, 220), mt_rand(120, 220), mt_rand(120, 220));
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), $dc);
        }

        $len = strlen($code);
        $slot = (int) ($w / ($len + 1));
        for ($i = 0; $i < $len; $i++) {
            $tc = imagecolorallocate($im, mt_rand(20, 90), mt_rand(20, 90), mt_rand(20, 90));
            $x = $slot * ($i + 1) - 6 + mt_rand(-3, 3);
            $y = mt_rand(8, 16);
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
     * @param string $input
     * @return bool
     */
    public static function verify($input)
    {
        $input = strtoupper(trim((string) $input));
        if ($input === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!isset($_SESSION[self::SESSION_CODE], $_SESSION[self::SESSION_TIME])) {
            return false;
        }
        if ((time() - (int) $_SESSION[self::SESSION_TIME]) > self::TTL) {
            unset($_SESSION[self::SESSION_CODE], $_SESSION[self::SESSION_TIME]);
            return false;
        }
        $expect = (string) $_SESSION[self::SESSION_CODE];
        unset($_SESSION[self::SESSION_CODE], $_SESSION[self::SESSION_TIME]);
        if (function_exists('hash_equals')) {
            return hash_equals($expect, $input);
        }
        return $expect === $input;
    }
}
