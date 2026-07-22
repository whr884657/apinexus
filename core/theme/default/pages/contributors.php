<?php if (!defined('VS_THEME_RENDER')) { exit; }

$contributors = FrontendContributor::listForTheme();
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$authUrl = isset($authUrl) ? $authUrl : ($vsBase . '/user/login');
?>
<main class="main-wrapper container mx-auto px-4" style="padding-top:88px;">
    <div class="page-header">
        <h1 class="section-title">公益贡献者</h1>
        <p class="text-sm font-mono page-subtitle" style="color: var(--text-muted); margin: -1.25rem 0 1.5rem;">感谢每一位为开源社区贡献力量的开发者</p>
    </div>
    <div class="thank-you-banner">
        <h2>衷心感谢</h2>
        <p>感谢以下开发者无私分享他们的 API 接口，为整个开发者社区提供了宝贵的资源。<br>正是有了你们的贡献，才让更多开发者能够快速构建自己的项目。</p>
    </div>

    <?php if (count($contributors) === 0): ?>
        <div class="empty-state">暂无公开贡献者，欢迎注册成为开发者并发布接口。</div>
    <?php else: ?>
    <div class="contributors-grid">
        <?php foreach ($contributors as $c): ?>
            <a href="<?php echo vs_e($c['profile_url']); ?>" class="contributor-card contributor-card-link">
                <img src="<?php echo vs_e($c['avatar']); ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                     alt="<?php echo vs_e($c['username']); ?>" class="contributor-avatar"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="contributor-avatar-placeholder" style="display:none;"><span><?php echo vs_e($c['letter']); ?></span></div>
                <div class="contributor-name"><?php echo vs_e($c['username']); ?></div>
                <p class="contributor-bio"><?php echo vs_e($c['bio']); ?></p>
                <div class="contributor-stats">
                    <div class="contributor-stat">
                        <div class="contributor-stat-value"><?php echo (int) $c['apicount']; ?></div>
                        <div class="contributor-stat-label">接口数</div>
                    </div>
                    <div class="contributor-stat">
                        <div class="contributor-stat-value"><?php echo vs_e($c['calls_label']); ?></div>
                        <div class="contributor-stat-label">调用次数</div>
                    </div>
                    <div class="contributor-stat">
                        <div class="contributor-stat-value"><?php echo vs_e($c['join_label']); ?></div>
                        <div class="contributor-stat-label">加入时间</div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="text-align: center; padding: 2rem 1rem;">
        <p style="color: var(--text-muted); margin-bottom: 1rem;">想要加入贡献者行列？</p>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek">立即注册</a>
    </div>
</main>
