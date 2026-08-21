<?php if (!defined('VS_THEME_RENDER')) { exit; }

$contributors = FrontendContributor::listForTheme();
$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();
$authUrl = isset($authUrl) ? $authUrl : ($vsBase . '/user/login');
$contribCount = count($contributors);
?>
<main class="st-main"><div class="st-wrap">
<section class="st-contrib-page">
    <header class="st-contrib-hero">
        <p class="st-contrib-hero__eyebrow">开发者社区</p>
        <h1 class="st-contrib-hero__title">公益贡献者</h1>
        <p class="st-contrib-hero__lead">感谢每一位为开源社区贡献力量的开发者。点击卡片可进入个人主页，查看已发布接口与调用数据。</p>
        <?php if ($contribCount > 0): ?>
        <p class="st-contrib-hero__meta"><strong><?php echo (int) $contribCount; ?></strong> 位公开贡献者</p>
        <?php endif; ?>
    </header>

    <?php if ($contribCount === 0): ?>
        <div class="st-contrib-empty">
            <div class="st-contrib-empty__mark" aria-hidden="true">+</div>
            <h2 class="st-contrib-empty__title">暂无公开贡献者</h2>
            <p class="st-contrib-empty__text">欢迎注册成为开发者并发布接口，你的名字会出现在这里。</p>
            <a class="st-bar__login st-contrib-empty__cta" href="<?php echo vs_e($authUrl); ?>">立即注册</a>
        </div>
    <?php else: ?>
    <div class="st-contrib-grid">
        <?php foreach ($contributors as $c): ?>
            <?php
            $bioCustom = !empty($c['bio_custom']);
            $bioText = isset($c['bio']) ? (string) $c['bio'] : '';
            ?>
            <a class="st-contrib-card" href="<?php echo vs_e($c['profile_url']); ?>">
                <div class="st-contrib-card__top">
                    <img class="st-contrib-card__avatar" src="<?php echo vs_e($c['avatar']); ?>" alt=""
                         loading="lazy" decoding="async" referrerpolicy="no-referrer"
                         onerror="this.classList.add('st-is-hidden');this.nextElementSibling.classList.remove('st-is-hidden');">
                    <div class="st-contrib-card__avatar st-contrib-card__avatar--fallback st-is-hidden"><?php echo vs_e($c['letter']); ?></div>
                    <div class="st-contrib-card__who">
                        <div class="st-contrib-card__name"><?php echo vs_e($c['username']); ?></div>
                        <p class="st-contrib-card__bio"<?php echo $bioCustom ? '' : ' data-vs-hitokoto="1"'; ?>><?php
                            echo $bioCustom ? vs_e($bioText) : '';
                        ?></p>
                    </div>
                </div>
                <div class="st-contrib-card__stats" aria-label="贡献数据">
                    <div class="st-contrib-card__stat">
                        <strong><?php echo (int) $c['apicount']; ?></strong>
                        <span>接口</span>
                    </div>
                    <div class="st-contrib-card__stat">
                        <strong><?php echo vs_e($c['calls_label']); ?></strong>
                        <span>调用</span>
                    </div>
                    <div class="st-contrib-card__stat">
                        <strong><?php echo vs_e($c['join_label']); ?></strong>
                        <span>加入</span>
                    </div>
                </div>
                <span class="st-contrib-card__go">查看主页</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <aside class="st-contrib-join">
        <div class="st-contrib-join__copy">
            <h2 class="st-contrib-join__title">想要加入贡献者行列？</h2>
            <p class="st-contrib-join__text">注册成为开发者并发布接口后，即可出现在本页列表。</p>
        </div>
        <a class="st-bar__login st-contrib-join__cta" href="<?php echo vs_e($authUrl); ?>">立即注册</a>
    </aside>
</section>
</div></main>
<?php
$hitokotoSrc = ThemeManager::assetUrl('default', 'assets/js/pages/hitokoto-bio.js');
if ($hitokotoSrc !== ''):
?>
<script src="<?php echo vs_e($hitokotoSrc); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
