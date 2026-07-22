<?php if (!defined('VS_THEME_RENDER')) { exit; }

$contributors = FrontendContributor::listForTheme();
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$authUrl = isset($authUrl) ? $authUrl : ($vsBase . '/user/login');
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">公益贡献者</h1>
    <p class="st-page-desc">感谢每一位为开源社区贡献力量的开发者</p>

    <?php if (count($contributors) === 0): ?>
        <p class="st-notice-box" style="text-align:center;">暂无公开贡献者，欢迎注册成为开发者并发布接口。</p>
    <?php else: ?>
    <div class="st-grid-3">
        <?php foreach ($contributors as $c): ?>
            <a class="st-card st-contrib st-contrib--link" href="<?php echo vs_e($c['profile_url']); ?>">
                <img class="st-contrib__avatar-img" src="<?php echo vs_e($c['avatar']); ?>" alt=""
                     loading="lazy" decoding="async" referrerpolicy="no-referrer"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="st-contrib__avatar" style="display:none;"><?php echo vs_e($c['letter']); ?></div>
                <div class="st-card__title"><?php echo vs_e($c['username']); ?></div>
                <p class="st-contrib__bio"><?php echo vs_e($c['bio']); ?></p>
                <div class="st-contrib__stats">
                    <span><strong><?php echo (int) $c['apicount']; ?></strong> 接口</span>
                    <span><strong><?php echo vs_e($c['calls_label']); ?></strong> 调用</span>
                    <span><strong><?php echo vs_e($c['join_label']); ?></strong> 加入</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p class="st-notice-box" style="margin-top:16px;text-align:center;">
        <a href="<?php echo vs_e($authUrl); ?>">立即注册</a> 成为开发者，发布接口即可出现在此列表。
    </p>
</section>
</div></main>
