<?php
/**
 * 文件：core/RegisterPolicy.php
 * 作用：用户注册策略（单键模式、按身份开放、邮箱验证、邮箱后缀限制）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 *
 * register_enabled 单键数字：
 *   1 = 全部开启（普通用户 + 开发者）
 *   2 = 全部关闭
 *   3 = 仅普通用户
 *   4 = 仅开发者
 *
 * 后台「开放注册」勾选态 = 模式 1（全选）；仅开一种时总闸未勾选但仍可注册该身份。
 */

class RegisterPolicy
{
    const CONFIG_KEY = 'register_policy';
    const KEY_ENABLED = 'register_enabled';
    const KEY_EMAIL_VERIFY = 'register_email_verify';

    /** 全部开启 */
    const MODE_ALL_OPEN = '1';
    /** 全部关闭 */
    const MODE_ALL_CLOSED = '2';
    /** 仅普通用户 */
    const MODE_USER_ONLY = '3';
    /** 仅开发者 */
    const MODE_DEVELOPER_ONLY = '4';

    /**
     * 当前注册模式（1～4）
     *
     * @return string
     */
    public static function getMode()
    {
        $raw = (string) Config::get(self::KEY_ENABLED, self::MODE_ALL_OPEN);
        if ($raw === '0') {
            return self::MODE_ALL_CLOSED;
        }
        if ($raw === self::MODE_ALL_OPEN
            || $raw === self::MODE_ALL_CLOSED
            || $raw === self::MODE_USER_ONLY
            || $raw === self::MODE_DEVELOPER_ONLY) {
            return $raw;
        }
        return self::MODE_ALL_OPEN;
    }

    /**
     * 是否至少开放一种身份注册（登录页「立即注册」、强访注册页用此判断）
     *
     * @return bool
     */
    public static function isOpen()
    {
        return self::getMode() !== self::MODE_ALL_CLOSED;
    }

    /**
     * 两种身份是否都开放（后台总闸勾选态）
     *
     * @return bool
     */
    public static function isFullyOpen()
    {
        return self::getMode() === self::MODE_ALL_OPEN;
    }

    /**
     * 是否允许注册为普通用户
     *
     * @return bool
     */
    public static function allowsUserRole()
    {
        $mode = self::getMode();
        return $mode === self::MODE_ALL_OPEN || $mode === self::MODE_USER_ONLY;
    }

    /**
     * 是否允许注册为开发者
     *
     * @return bool
     */
    public static function allowsDeveloperRole()
    {
        $mode = self::getMode();
        return $mode === self::MODE_ALL_OPEN || $mode === self::MODE_DEVELOPER_ONLY;
    }

    /**
     * 指定身份是否允许注册
     *
     * @param string $role user|developer
     * @return bool
     */
    public static function allowsRole($role)
    {
        $role = UserRole::normalize($role);
        if ($role === UserRole::ROLE_DEVELOPER) {
            return self::allowsDeveloperRole();
        }
        return self::allowsUserRole();
    }

    /**
     * 注册页是否显示身份分段滑块（仅两种都开放时）
     *
     * @return bool
     */
    public static function shouldShowRoleSegment()
    {
        return self::getMode() === self::MODE_ALL_OPEN;
    }

    /**
     * 仅开放一种身份时返回固定 role；两种都开或都关返回 null
     *
     * @return string|null user|developer|null
     */
    public static function fixedRegisterRole()
    {
        $mode = self::getMode();
        if ($mode === self::MODE_USER_ONLY) {
            return UserRole::ROLE_USER;
        }
        if ($mode === self::MODE_DEVELOPER_ONLY) {
            return UserRole::ROLE_DEVELOPER;
        }
        return null;
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
     * 某类身份未开放时的对外文案
     *
     * @return string
     */
    public static function roleClosedMessage()
    {
        return '当前未开放此类账号注册';
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
     * 指定身份允许时返回 null；否则返回错误文案（含总关闭）
     *
     * @param string $role
     * @return string|null
     */
    public static function assertRoleAllowed($role)
    {
        $closed = self::assertOpen();
        if ($closed !== null) {
            return $closed;
        }
        if (!self::allowsRole($role)) {
            return self::roleClosedMessage();
        }
        return null;
    }

    /**
     * 由两个身份勾选推导模式并写入单键 register_enabled
     *
     * @param bool $allowUser
     * @param bool $allowDeveloper
     * @return void
     * @throws Exception
     */
    public static function saveRoleAllows($allowUser, $allowDeveloper)
    {
        Config::set(self::KEY_ENABLED, self::modeFromAllows($allowUser, $allowDeveloper));
    }

    /**
     * 勾选态 → 模式数字
     *
     * @param bool $allowUser
     * @param bool $allowDeveloper
     * @return string 1|2|3|4
     */
    public static function modeFromAllows($allowUser, $allowDeveloper)
    {
        if ($allowUser && $allowDeveloper) {
            return self::MODE_ALL_OPEN;
        }
        if ($allowUser) {
            return self::MODE_USER_ONLY;
        }
        if ($allowDeveloper) {
            return self::MODE_DEVELOPER_ONLY;
        }
        return self::MODE_ALL_CLOSED;
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
