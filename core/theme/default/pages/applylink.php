<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$siteCard = isset($siteCard) && is_array($siteCard) ? $siteCard : (class_exists('FrontendLink') ? FrontendLink::siteCard() : array(
    'name' => isset($siteName) ? $siteName : 'ApiNexus',
    'url'  => $vsBase . '/',
    'desc' => isset($siteDesc) ? $siteDesc : '',
    'icon' => '',
));
$csrf = class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '';
$metaUrl = $vsBase . '/core/theme/default/api/sitemeta.php';
?>
<main class="main-wrapper container mx-auto px-4 applylink-page" style="padding-top:88px;">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>申请友链</h1>
        <p class="applylink-lead">欢迎优质网站交换友链，共同发展</p>
    </div>

    <div class="form-card form-card--info">
        <div class="tips-title">本站友链信息（请先在贵站添加）</div>
        <div class="site-card-lines">
            <p><strong>名称：</strong><?php echo vs_e($siteCard['name']); ?></p>
            <p><strong>链接：</strong><span class="font-mono"><?php echo vs_e($siteCard['url']); ?></span></p>
            <?php if (!empty($siteCard['desc'])): ?>
            <p><strong>简介：</strong><?php echo vs_e($siteCard['desc']); ?></p>
            <?php endif; ?>
            <?php if (!empty($siteCard['icon'])): ?>
            <p><strong>图标：</strong><span class="font-mono"><?php echo vs_e($siteCard['icon']); ?></span></p>
            <?php endif; ?>
            <p class="site-card-note">请先在贵站添加本站友链后再提交申请。</p>
        </div>
    </div>

    <div class="form-card">
        <div id="applyAlert" class="alert" hidden></div>

        <form id="applyLinkForm" method="post" action="<?php echo vs_e($vsBase); ?>/applylink" data-ajax="1">
            <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
            <input type="hidden" name="action" value="apply">

            <div class="form-group">
                <label class="form-label" for="applyUrl">网站链接 *</label>
                <input type="url" id="applyUrl" name="siteurl" class="form-input" required placeholder="https://example.com" maxlength="255">
                <button type="button" class="btn-geek applylink-fetch" id="applyFetchBtn">一键获取网站信息</button>
                <p class="applylink-fetch-status" id="applyFetchStatus" aria-live="polite"></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="applyName">网站名称 *</label>
                <input type="text" id="applyName" name="name" class="form-input" required placeholder="填写链接后可一键获取" maxlength="50">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyIcon">头像链接</label>
                <input type="url" id="applyIcon" name="icon" class="form-input" placeholder="可一键获取，也可手填" maxlength="255">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyDesc">网站描述</label>
                <input type="text" id="applyDesc" name="description" class="form-input" placeholder="可一键获取，也可手填" maxlength="200">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyContact">联系方式</label>
                <input type="text" id="applyContact" name="contact" class="form-input" placeholder="建议填写邮箱，审核通过后可收到通知" maxlength="100">
            </div>

            <button type="submit" class="btn-geek applylink-submit" id="applySubmitBtn">提交申请</button>
        </form>

        <div class="tips-card">
            <div class="tips-title">申请须知</div>
            <ul class="tips-list">
                <li>先填写网站链接，点击「一键获取」可自动填充名称、图标与描述</li>
                <li>联系方式建议填邮箱，审核通过后系统可发信通知您</li>
                <li>网站需正常运营，内容合法合规</li>
                <li>请在贵站添加本站友链后再申请</li>
            </ul>
        </div>

        <p class="applylink-back">
            <a href="<?php echo vs_e($vsBase); ?>/links">← 返回友情链接</a>
        </p>
    </div>
</main>
<script>
window.VS_LINK_META_URL = <?php echo json_encode($metaUrl, JSON_UNESCAPED_UNICODE); ?>;
window.VS_CSRF_TOKEN = window.VS_CSRF_TOKEN || <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
</script>
