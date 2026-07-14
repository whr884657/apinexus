<?php
/**
 * 文件：core/UserAvatar.php
 * 作用：用户头像解析（QQ 邮箱 / 自定义链接 / 本地随机）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class UserAvatar
{
    /** @var array|null */
    private static $localFiles = null;

    /**
     * 解析用户头像 URL
     *
     * @param array|null $user 需含 id、email，可选 avatar
     * @return string
     */
    public static function resolve($user)
    {
        if (!is_array($user) || empty($user['id'])) {
            return self::defaultAvatar();
        }

        $email = isset($user['email']) ? trim((string) $user['email']) : '';
        $qq = self::extractQqFromEmail($email);
        if ($qq !== '') {
            return 'https://q1.qlogo.cn/g?b=qq&nk=' . rawurlencode($qq) . '&s=640';
        }

        $custom = isset($user['avatar']) ? trim((string) $user['avatar']) : '';
        if ($custom !== '' && filter_var($custom, FILTER_VALIDATE_URL)) {
            return $custom;
        }

        return self::localRandomAvatar((int) $user['id']);
    }

    /**
     * 从 QQ 邮箱提取 QQ 号
     *
     * @param string $email
     * @return string
     */
    public static function extractQqFromEmail($email)
    {
        $email = strtolower(trim($email));
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        list($local, $domain) = explode('@', $email, 2);
        if (!in_array($domain, array('qq.com', 'foxmail.com', 'vip.qq.com'), true)) {
            return '';
        }

        if (preg_match('/^\d{5,12}$/', $local)) {
            return $local;
        }

        return '';
    }

    /**
     * 按用户 ID 稳定选取本地随机头像
     *
     * @param int $userId
     * @return string
     */
    public static function localRandomAvatar($userId)
    {
        $files = self::localAvatarFiles();
        if (count($files) === 0) {
            return self::defaultAvatar();
        }

        $index = abs(crc32((string) $userId)) % count($files);
        return vs_base_url() . '/assets/img/avatar/' . rawurlencode(basename($files[$index]));
    }

    /**
     * @return string
     */
    public static function defaultAvatar()
    {
        $files = self::localAvatarFiles();
        if (count($files) > 0) {
            return vs_base_url() . '/assets/img/avatar/' . rawurlencode(basename($files[0]));
        }

        return vs_base_url() . '/assets/img/gov.png';
    }

    /**
     * @return array
     */
    public static function localAvatarFiles()
    {
        if (self::$localFiles !== null) {
            return self::$localFiles;
        }

        $dir = VS_ROOT . '/assets/img/avatar';
        if (!is_dir($dir)) {
            self::$localFiles = array();
            return self::$localFiles;
        }

        $patterns = array('*.jpg', '*.jpeg', '*.png', '*.gif', '*.webp');
        $files = array();
        foreach ($patterns as $pattern) {
            $found = glob($dir . '/' . $pattern);
            if (is_array($found)) {
                $files = array_merge($files, $found);
            }
        }

        sort($files, SORT_STRING);
        self::$localFiles = $files;
        return self::$localFiles;
    }
}
