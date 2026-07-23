<?php if (!defined('VS_THEME_RENDER')) { exit; }

$contributors = FrontendContributor::listForTheme();
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$authUrl = isset($authUrl) ? $authUrl : ($vsBase . '/user/login');
?>
<main class="main-wrapper container mx-auto px-4" style="padding-top:88px;">
    <div class="page-header">
        <h1 class="section-title">公益贡献者</h1>
        <p class="text-sm page-subtitle" style="color: var(--text-muted); margin: -1.25rem 0 1.5rem;">感谢每一位为开源社区贡献力量的开发者</p>
    </div>

    <div class="contrib-intro" role="note">
        <p class="contrib-intro__text">下列开发者已公开分享接口。点击卡片可进入个人主页，查看其已发布的接口与调用数据。</p>
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
