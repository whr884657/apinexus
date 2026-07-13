<?php
/**
 * 文件：core/FrontendUser.php
 * 作用：前台/用户中心统一用户信息调度（主题与布局通过本类获取用户资料，禁止直读数据库）
 */

class FrontendUser
{
    /**
     * 当前登录用户的标准化资料
     *
     * @return array|null
     */
    public static function current()
    {
        if (!UserAuth::check()) {
            return null;
        }

        $user = UserAuth::user();
        if (!$user) {
            return null;
        }

        return self::format($user);
    }

    /**
     * 将用户表行格式化为前台可用结构
     *
     * @param array $user
     * @return array
     */
    public static function format(array $user)
    {
        $role = UserRole::normalize(isset($user['role']) ? $user['role'] : UserRole::ROLE_USER);

        return array(
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'avatar_url' => (string) (isset($user['avatar_url']) ? $user['avatar_url'] : ''),
            'avatar' => UserAvatar::resolve($user),
            'role' => $role,
            'role_label' => UserRole::label($role),
            'can_publish_api' => UserRole::canPublishApi($role),
            'created_at' => isset($user['created_at']) ? (string) $user['created_at'] : '',
            'last_login_at' => isset($user['last_login_at']) ? (string) $user['last_login_at'] : '',
        );
    }
}
