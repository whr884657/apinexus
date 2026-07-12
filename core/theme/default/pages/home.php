<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
$heroTitleSetting = ThemeManager::themeSetting('hero_title', '');
$heroLeadSetting = ThemeManager::themeSetting('hero_lead', '');
$heroTitle = $heroTitleSetting !== '' ? $heroTitleSetting : ('欢迎使用 ' . $siteName);
$heroDesc = $heroLeadSetting !== '' ? $heroLeadSetting : (isset($heroDesc) ? $heroDesc : ($siteDesc !== '' ? $siteDesc : '基于 PHP + MySQL 的轻量级 Web 管理系统，全面适配电脑端与手机端。'));

$publicApis = ApiManager::listPublic();
$categories = ApiManager::categoriesFromList($publicApis);
$apiCount = ApiManager::countApproved();
$catCount = count($categories);
?>
<main class="dt-main">
<div class="dt-container">
<section class="dt-hero dt-hero--split">
    <div class="dt-hero__content">
        <div class="dt-hero__tags">
            <span class="dt-tag">开放接口</span>
            <span class="dt-tag dt-tag--muted">轻量部署</span>
        </div>
        <h1 class="dt-hero__title"><?php echo vs_e($heroTitle); ?></h1>
        <p class="dt-hero__desc"><?php echo vs_e($heroDesc); ?></p>
        <div class="dt-hero__actions">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="dt-btn dt-btn--primary">浏览接口</a>
            <a href="<?php echo vs_e($authUrl); ?>" class="dt-btn dt-btn--outline"><?php echo vs_e($authLabel); ?></a>
        </div>
    </div>
    <div class="dt-hero__panel" aria-hidden="true">
        <div class="dt-hero__panel-head">平台概览</div>
        <ul class="dt-hero__panel-list">
            <li><span>公开接口</span><strong><?php echo (int) $apiCount; ?></strong></li>
            <li><span>接口分类</span><strong><?php echo (int) $catCount; ?></strong></li>
            <li><span>系统版本</span><strong>v<?php echo vs_e(VS_VERSION); ?></strong></li>
            <li><span>终端适配</span><strong>PC / 手机</strong></li>
        </ul>
    </div>
</section>

<section class="dt-stats">
    <div class="dt-stat">
        <div class="dt-stat__value"><span class="dt-counter" data-target="<?php echo (int) $apiCount; ?>">0</span></div>
        <div class="dt-stat__label">公开接口</div>
    </div>
    <div class="dt-stat">
        <div class="dt-stat__value"><span class="dt-counter" data-target="<?php echo max(1, (int) $catCount); ?>">0</span></div>
        <div class="dt-stat__label">接口分类</div>
    </div>
    <div class="dt-stat">
        <div class="dt-stat__value"><span class="dt-counter" data-target="<?php echo (int) preg_replace('/\D/', '', VS_VERSION); ?>">0</span></div>
        <div class="dt-stat__label">主版本号</div>
    </div>
    <div class="dt-stat">
        <div class="dt-stat__value">100<span class="dt-stat__suffix">%</span></div>
        <div class="dt-stat__label">响应式布局</div>
    </div>
</section>

<section class="dt-section dt-section--apis" id="apis">
    <div class="dt-section__head">
        <h2 class="dt-section__title">接口目录</h2>
        <p class="dt-section__desc">浏览已通过审核的公开 API / TAPI 接口</p>
    </div>
    <?php
    $toolbarId = 'dtHomeApiToolbar';
    include __DIR__ . '/../partials/api-toolbar.php';
    ?>
    <?php
    $gridId = 'dtHomeApiGrid';
    $cardLimit = 9;
    $emptyMessage = '暂无已上线的公开接口。注册登录后可在用户中心提交接口，审核通过后将在此展示。';
    include __DIR__ . '/../partials/api-grid.php';
    ?>
    <?php if ($apiCount > 9): ?>
        <div class="dt-section__more">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="dt-link-more">查看全部接口 →</a>
        </div>
    <?php endif; ?>
</section>

<section class="dt-section">
    <h2 class="dt-section__title">平台特性</h2>
    <div class="dt-features">
        <div class="dt-feature">
            <div class="dt-feature__icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18" stroke-linecap="round"/></svg>
            </div>
            <h3>一键安装</h3>
            <p>访问 /install 即可完成 Web 安装向导，快速上线。</p>
        </div>
        <div class="dt-feature">
            <div class="dt-feature__icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h3>安全加密</h3>
            <p>密码加密存储，CSRF 防护与统一认证流程。</p>
        </div>
        <div class="dt-feature">
            <div class="dt-feature__icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01" stroke-linecap="round"/></svg>
            </div>
            <h3>响应式设计</h3>
            <p>电脑端顶栏导航，手机端右侧抽屉，与用户中心风格一致。</p>
        </div>
    </div>
</section>
</div>
</main>
