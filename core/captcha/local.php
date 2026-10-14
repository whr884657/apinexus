<?php
/**
 * 文件：core/captcha/local.php
 * 作用：本地图形验证码（GD；session 存场景绑定哈希；抗 OCR 加强；校验不区分大小写）
 */

class CaptchaLocal
{
    const SESSION_HASH = 'vs_captcha_local_h';
    const SESSION_TIME = 'vs_captcha_local_t';
    const SESSION_SCENE = 'vs_captcha_local_s';
    const TTL = 300;
    const LEN = 5;
    const WIDTH = 150;
    const HEIGHT = 48;

    /**
     * 专用 pepper（配置键 captcha_pepper）；缺则生成并持久化，不再复用第三方密钥/路径
     *
     * @return string
     */
    private static function serverPepper()
    {
        $dedicated = self::ensureCaptchaPepper();
        return hash('sha256', $dedicated . '|vs_local_captcha_v6');
    }

    /**
     * @return string 非空随机 pepper（hex）
     */
    private static function ensureCaptchaPepper()
    {
        if (!class_exists('Config')) {
            return hash('sha256', (defined('VS_ROOT') ? (string) VS_ROOT : __DIR__) . '|vs_local_captcha_fallback');
        }
        try {
            $cur = trim((string) Config::get('captcha_pepper', ''));
        } catch (Exception $e) {
            $cur = '';
        }
        if ($cur !== '' && strlen($cur) >= 32) {
            return $cur;
        }
        if (function_exists('random_bytes')) {
            try {
                $cur = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $cur = '';
            }
        }
        if ($cur === '') {
            $cur = hash('sha256', uniqid((string) mt_rand(), true) . microtime(true));
        }
        try {
            Config::set('captcha_pepper', $cur);
        } catch (Exception $e) {
            // 安装前或写库失败：本请求仍用内存值，下次再落库
        }
        return $cur;
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
     * 在小画布上画单字符，略放大后旋转贴到主图（无 TTF 依赖）
     *
     * @param resource|\GdImage $im
     * @param string            $ch
     * @param int               $cx
     * @param int               $cy
     * @param int               $color
     * @return void
     */
    private static function drawWarpedChar($im, $ch, $cx, $cy, $color)
    {
        $tw = 28;
        $th = 36;
        $tmp = imagecreatetruecolor($tw, $th);
        if ($tmp === false) {
            imagestring($im, 5, $cx - 6, $cy - 8, $ch, $color);
            return;
        }
        imagealphablending($tmp, false);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $tw, $th, $transparent);
        imagealphablending($tmp, true);
        imagestring($tmp, 5, 6 + self::randInt(-2, 2), 8 + self::randInt(-2, 2), $ch, $color);

        // 相对原字形只放大一丢丢（约 1.35 倍），不撑爆 150×48 画布
        $scale = 1.35;
        $sw = (int) round($tw * $scale);
        $sh = (int) round($th * $scale);
        $scaled = imagecreatetruecolor($sw, $sh);
        if ($scaled === false) {
            $angle = self::randInt(-28, 28);
            $rotated = imagerotate($tmp, $angle, $transparent);
            imagedestroy($tmp);
            if ($rotated === false) {
                imagestring($im, 5, $cx - 6, $cy - 8, $ch, $color);
                return;
            }
        } else {
            imagealphablending($scaled, false);
            $tr2 = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            imagefilledrectangle($scaled, 0, 0, $sw, $sh, $tr2);
            imagealphablending($scaled, true);
            imagecopyresampled($scaled, $tmp, 0, 0, 0, 0, $sw, $sh, $tw, $th);
            imagedestroy($tmp);
            $angle = self::randInt(-26, 26);
            $rotated = imagerotate($scaled, $angle, $tr2);
            imagedestroy($scaled);
            if ($rotated === false) {
                imagestring($im, 5, $cx - 6, $cy - 8, $ch, $color);
                return;
            }
        }
        imagealphablending($rotated, true);
        imagesavealpha($rotated, true);
        $rw = imagesx($rotated);
        $rh = imagesy($rotated);
        imagecopy($im, $rotated, (int) ($cx - $rw / 2), (int) ($cy - $rh / 2), 0, 0, $rw, $rh);
        imagedestroy($rotated);
    }

    /**
     * 轻度水平波纹扭曲，打乱直线 OCR
     *
     * @param resource|\GdImage $im
     * @return resource|\GdImage
     */
    private static function waveDistort($im)
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $out = imagecreatetruecolor($w, $h);
        if ($out === false) {
            return $im;
        }
        $bg = imagecolorallocate($out, 235, 235, 235);
        imagefilledrectangle($out, 0, 0, $w, $h, $bg);
        // 波纹比加强版略轻一丢丢
        $amp = self::randInt(1, 3);
        $period = self::randInt(11, 18);
        for ($x = 0; $x < $w; $x++) {
            $shift = (int) round(sin($x / $period) * $amp);
            for ($y = 0; $y < $h; $y++) {
                $sy = $y + $shift;
                if ($sy < 0 || $sy >= $h) {
                    continue;
                }
                $rgb = imagecolorat($im, $x, $sy);
                imagesetpixel($out, $x, $y, $rgb);
            }
        }
        imagedestroy($im);
        return $out;
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
        imagealphablending($im, true);

        // 渐变噪点底
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $r = (int) (220 + (245 - 220) * $t) + self::randInt(-8, 8);
            $g = (int) (222 + (248 - 222) * $t) + self::randInt(-8, 8);
            $b = (int) (228 + (250 - 228) * $t) + self::randInt(-8, 8);
            $line = imagecolorallocate(
                $im,
                max(180, min(255, $r)),
                max(180, min(255, $g)),
                max(180, min(255, $b))
            );
            imageline($im, 0, $y, $w, $y, $line);
        }

        for ($i = 0; $i < 14; $i++) {
            $lc = imagecolorallocate($im, self::randInt(90, 190), self::randInt(90, 190), self::randInt(90, 190));
            if (self::randInt(0, 1) === 1 && function_exists('imagearc')) {
                imagearc(
                    $im,
                    self::randInt(0, $w),
                    self::randInt(0, $h),
                    self::randInt(20, 80),
                    self::randInt(10, 40),
                    self::randInt(0, 180),
                    self::randInt(180, 360),
                    $lc
                );
            } else {
                imageline($im, self::randInt(0, $w), self::randInt(0, $h), self::randInt(0, $w), self::randInt(0, $h), $lc);
            }
        }
        for ($i = 0; $i < 220; $i++) {
            $dc = imagecolorallocate($im, self::randInt(80, 210), self::randInt(80, 210), self::randInt(80, 210));
            imagesetpixel($im, self::randInt(0, $w - 1), self::randInt(0, $h - 1), $dc);
        }

        $len = strlen($code);
        $margin = 12;
        $usable = $w - $margin * 2;
        for ($i = 0; $i < $len; $i++) {
            $tc = imagecolorallocate($im, self::randInt(8, 70), self::randInt(8, 70), self::randInt(8, 70));
            $cx = (int) ($margin + ($usable * ($i + 0.5) / $len) + self::randInt(-3, 3));
            $cy = (int) ($h / 2 + self::randInt(-4, 4));
            self::drawWarpedChar($im, $code[$i], $cx, $cy, $tc);
        }

        // 前景细线略减一丢丢（原 5 → 4）
        for ($i = 0; $i < 4; $i++) {
            $lc = imagecolorallocate($im, self::randInt(100, 170), self::randInt(100, 170), self::randInt(100, 170));
            imageline($im, self::randInt(0, $w), self::randInt(0, $h), self::randInt(0, $w), self::randInt(0, $h), $lc);
        }

        $im = self::waveDistort($im);

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
