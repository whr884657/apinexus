<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteCard = isset($siteCard) && is_array($siteCard) ? $siteCard : (class_exists('FrontendLink') ? FrontendLink::siteCard() : array(
    'name' => isset($siteName) ? $siteName : 'ApiNexus',
    'url'  => ($vsBase === '' ? '/' : ($vsBase . '/')),
    'desc' => isset($siteDesc) ? $siteDesc : '',
    'icon' => '',
));
$csrf = class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '';
$metaUrl = $vsBase . '/core/theme/default/api/sitemeta.php';
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">申请友链</h1>
    <p class="st-page-desc">欢迎优质网站交换友链，共同发展</p>

    <div class="st-card st-apply-info">
        <div class="st-card__title">本站友链信息（请先在贵站添加）</div>
        <div class="st-card__desc st-apply-info__lines">
            <p><strong>名称：</strong><?php echo vs_e($siteCard['name']); ?></p>
            <p><strong>链接：</strong><code><?php echo vs_e($siteCard['url']); ?></code></p>
            <?php if (!empty($siteCard['desc'])): ?>
            <p><strong>简介：</strong><?php echo vs_e($siteCard['desc']); ?></p>
            <?php endif; ?>
            <?php if (!empty($siteCard['icon'])): ?>
            <p><strong>图标：</strong><code><?php echo vs_e($siteCard['icon']); ?></code></p>
            <?php endif; ?>
            <p class="st-apply-info__note">请先在贵站添加本站友链后再提交申请。</p>
        </div>
    </div>

    <div id="applyAlert" class="st-notice-box st-apply-alert" hidden></div>

    <form id="applyLinkForm" method="post" action="<?php echo vs_e($vsBase); ?>/applylink" data-ajax="1" class="st-card st-form">
        <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
        <input type="hidden" name="action" value="apply">

        <div class="st-form-field">
            <label class="st-label" for="applyUrl">网站链接 *</label>
            <input class="st-input" type="url" id="applyUrl" name="siteurl" required placeholder="https://example.com" maxlength="255">
            <button type="button" class="st-btn st-btn--ghost st-apply-fetch" id="applyFetchBtn">一键获取网站信息</button>
            <p class="applylink-fetch-status st-apply-fetch-status" id="applyFetchStatus" aria-live="polite"></p>
        </div>

        <div class="st-form-field">
            <label class="st-label" for="applyName">网站名称 *</label>
            <input class="st-input" type="text" id="applyName" name="name" required placeholder="填写链接后可一键获取" maxlength="50">
        </div>

        <div class="st-form-field">
            <label class="st-label" for="applyIcon">头像链接</label>
            <input class="st-input" type="url" id="applyIcon" name="icon" placeholder="可一键获取，也可手填" maxlength="255">
        </div>

        <div class="st-form-field">
            <label class="st-label" for="applyDesc">网站描述</label>
            <input class="st-input" type="text" id="applyDesc" name="description" placeholder="可一键获取，也可手填" maxlength="200">
        </div>

        <div class="st-form-field">
            <label class="st-label" for="applyContact">联系方式</label>
            <input class="st-input" type="text" id="applyContact" name="contact" placeholder="建议填写邮箱，审核通过后可收到通知" maxlength="100">
        </div>

        <button type="submit" class="st-btn" id="applySubmitBtn">提交申请</button>
    </form>

    <div class="st-card st-apply-tips">
        <div class="st-card__title">申请须知</div>
        <ul class="st-apply-tips__list">
            <li>先填写网站链接，点击「一键获取」可自动填充名称、图标与描述</li>
            <li>联系方式建议填邮箱，审核通过后系统可发信通知您</li>
            <li>网站需正常运营，内容合法合规</li>
            <li>请在贵站添加本站友链后再申请</li>
        </ul>
    </div>

    <p class="st-apply-back"><a href="<?php echo vs_e($vsBase); ?>/links">← 返回友情链接</a></p>
</section>
</div></main>
<script>
window.VS_LINK_META_URL = <?php echo json_encode($metaUrl, JSON_UNESCAPED_UNICODE); ?>;
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/applylink.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
