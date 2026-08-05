<?php
/**
 * 文件：core/RegisterPolicy.php
 * 作用：用户注册策略（开放开关、邮箱验证、邮箱后缀限制等）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class RegisterPolicy
{
    const CONFIG_KEY = 'register_policy';
    const KEY_ENABLED = 'register_enabled';
    const KEY_EMAIL_VERIFY = 'register_email_verify';

    /**
     * 是否开放用户注册（默认开放）
     *
     * @return bool
     */
    public static function isOpen()
    {
        return Config::get(self::KEY_ENABLED, '1') === '1';
    }

    /**
     * 注册是否必须邮箱验证码（默认必须）
     *
     * @return bool
     */
    public static function requiresEmailVerify()
    {
        return Config::get(self::KEY_EMAIL_VERIFY, '1') === '1';
    }

    /**
     * 关闭注册时的统一对外文案
     *
     * @return string
     */
    public static function closedMessage()
    {
        return '已停止注册，如有问题请联系管理员';
    }

    /**
     * 开放注册时返回 null；关闭时返回错误文案
     *
     * @return string|null
     */
    public static function assertOpen()
    {
        return self::isOpen() ? null : self::closedMessage();
    }

    /**
     * 读取注册策略配置
     *
     * @return array{email_suffixes: string[]}
     */
    public static function getPolicy()
    {
        $raw = (string) Config::get(self::CONFIG_KEY, '');
        if ($raw === '') {
            return array('email_suffixes' => array());
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array('email_suffixes' => array());
        }

        $suffixes = array();
        if (!empty($data['email_suffixes']) && is_array($data['email_suffixes'])) {
            foreach ($data['email_suffixes'] as $item) {
                $normalized = self::normalizeSuffix($item);
                if ($normalized !== '') {
                    $suffixes[] = $normalized;
                }
            }
        }

        return array('email_suffixes' => array_values(array_unique($suffixes)));
    }

    /**
     * 保存注册策略（JSON 写入 config）
     *
     * @param array $suffixes
     * @return void
     * @throws Exception
     */
    public static function saveEmailSuffixes(array $suffixes)
    {
        $normalized = array();
        foreach ($suffixes as $item) {
            $value = self::normalizeSuffix($item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $payload = array(
            'email_suffixes' => array_values(array_unique($normalized)),
        );

        Config::set(self::CONFIG_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 是否启用了邮箱后缀限制
     *
     * @return bool
     */
    public static function hasEmailSuffixRestriction()
    {
        return count(self::getPolicy()['email_suffixes']) > 0;
    }

    /**
     * 校验邮箱后缀是否允许注册
     *
     * @param string $email
     * @return string|null 不允许时返回错误文案
     */
    public static function validateEmailSuffix($email)
    {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '请输入有效的邮箱地址';
        }

        $suffixes = self::getPolicy()['email_suffixes'];
        if (count($suffixes) === 0) {
            return null;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return '请输入有效的邮箱地址';
        }

        $domain = strtolower(substr($email, $at + 1));
        foreach ($suffixes as $suffix) {
            if ($domain === $suffix || substr($domain, -strlen('.' . $suffix)) === '.' . $suffix) {
                return null;
            }
        }

        $display = implode('、', array_map(function ($item) {
            return '@' . $item;
        }, $suffixes));

        return '当前仅支持以下邮箱后缀注册：' . $display;
    }

    /**
     * 将表单输入（多行或逗号分隔）解析为后缀数组
     *
     * @param string $input
     * @return string[]
     */
    public static function parseSuffixInput($input)
    {
        $input = str_replace(array("\r\n", "\r"), "\n", (string) $input);
        $parts = preg_split('/[\n,，;；]+/', $input);
        $result = array();

        if (is_array($parts)) {
            foreach ($parts as $part) {
                $normalized = self::normalizeSuffix($part);
                if ($normalized !== '') {
                    $result[] = $normalized;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 将后缀数组格式化为表单展示文本
     *
     * @param string[] $suffixes
     * @return string
     */
    public static function formatSuffixInput(array $suffixes)
    {
        if (count($suffixes) === 0) {
            return '';
        }

        return implode("\n", $suffixes);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function normalizeSuffix($value)
    {
        $value = strtolower(trim((string) $value));
        $value = ltrim($value, '@');
        $value = preg_replace('/^\.+/', '', $value);

        if ($value === '' || strpos($value, ' ') !== false) {
            return '';
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i', $value)) {
            return '';
        }

        return $value;
    }
}
