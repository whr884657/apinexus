<?php
/**
 * 文件：admin/users.php
 * 作用：用户管理（列表、OAuth 绑定状态）
 */

require_once __DIR__ . '/init.php';

$users = UserManager::all();
$userCount = count($users);

/**
 * @param array $row
 * @return string
 */
function vs_users_oauth_badges(array $row)
{
    $badges = array();
    if (trim((string) $row['oauth_qq_openid']) !== '') {
        $badges[] = '<span class="vs-oauth-badge vs-oauth-badge--qq">QQ</span>';
    }
    if (trim((string) $row['oauth_gitee_id']) !== '') {
        $badges[] = '<span class="vs-oauth-badge vs-oauth-badge--gitee">Gitee</span>';
    }
    if (empty($badges)) {
        return '<span class="vs-oauth-badge vs-oauth-badge--none">未绑定</span>';
    }
    return implode(' ', $badges);
}

/**
 * @param string|null $datetime
 * @return string
 */
function vs_users_format_time($datetime)
{
    if ($datetime === null || trim((string) $datetime) === '') {
        return '从未登录';
    }
    return (string) $datetime;
}

vs_admin_layout_start('用户管理', 'users');
?>

<div class="vs-panel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">用户列表</h2>
        <p class="vs-panel__desc">共 <?php echo (int) $userCount; ?> 位用户</p>
    </div>

    <?php if ($userCount === 0): ?>
        <?php vs_render_notice('info', '', '暂无注册用户', array('compact' => true)); ?>
    <?php else: ?>
        <div class="vs-users-desktop vs-table-wrap">
            <table class="vs-table vs-users-table">
                <thead>
                    <tr>
                        <th>用户</th>
                        <th>邮箱</th>
                        <th>第三方绑定</th>
                        <th>注册时间</th>
                        <th>最后登录</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <?php
                        $avatar = UserAvatar::resolve($row);
                        $status = (int) $row['status'] === 1;
                        ?>
                        <tr>
                            <td>
                                <div class="vs-users-cell-user">
                                    <img src="<?php echo vs_e($avatar); ?>" alt="" class="vs-users-avatar">
                                    <div>
                                        <div class="vs-users-name"><?php echo vs_e($row['username']); ?></div>
                                        <div class="vs-users-meta">ID <?php echo (int) $row['id']; ?><?php echo $status ? '' : ' · 已禁用'; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo vs_e($row['email']); ?></td>
                            <td><?php echo vs_users_oauth_badges($row); ?></td>
                            <td><?php echo vs_e($row['created_at']); ?></td>
                            <td><?php echo vs_e(vs_users_format_time(isset($row['last_login_at']) ? $row['last_login_at'] : null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="vs-users-mobile">
            <?php foreach ($users as $row): ?>
                <?php
                $avatar = UserAvatar::resolve($row);
                $status = (int) $row['status'] === 1;
                ?>
                <article class="vs-user-card">
                    <div class="vs-user-card__head">
                        <img src="<?php echo vs_e($avatar); ?>" alt="" class="vs-users-avatar">
                        <div class="vs-user-card__title">
                            <div class="vs-users-name"><?php echo vs_e($row['username']); ?></div>
                            <div class="vs-users-meta"><?php echo vs_e($row['email']); ?></div>
                        </div>
                    </div>
                    <div class="vs-user-card__grid">
                        <div class="vs-user-card__cell">
                            <span class="vs-user-card__label">用户 ID</span>
                            <span class="vs-user-card__value"><?php echo (int) $row['id']; ?><?php echo $status ? '' : '（已禁用）'; ?></span>
                        </div>
                        <div class="vs-user-card__cell">
                            <span class="vs-user-card__label">第三方绑定</span>
                            <span class="vs-user-card__value"><?php echo vs_users_oauth_badges($row); ?></span>
                        </div>
                        <div class="vs-user-card__cell">
                            <span class="vs-user-card__label">注册时间</span>
                            <span class="vs-user-card__value"><?php echo vs_e($row['created_at']); ?></span>
                        </div>
                        <div class="vs-user-card__cell">
                            <span class="vs-user-card__label">最后登录</span>
                            <span class="vs-user-card__value"><?php echo vs_e(vs_users_format_time(isset($row['last_login_at']) ? $row['last_login_at'] : null)); ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php vs_admin_layout_end(); ?>
