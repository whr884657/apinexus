<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$siteCard = isset($siteCard) && is_array($siteCard) ? $siteCard : (class_exists('FrontendLink') ? FrontendLink::siteCard() : array(
    'name' => isset($siteName) ? $siteName : 'ApiNexus',
    'url'  => $vsBase . '/',
    'desc' => isset($siteDesc) ? $siteDesc : '',
    'icon' => '',
));
$csrf = class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '';
?>
<main class="main-wrapper container mx-auto px-4 applylink-page">
    <div class="page-header">
        <h1 class="section-title">申请友链</h1>
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
                <label class="form-label" for="applyName">网站名称 *</label>
                <input type="text" id="applyName" name="name" class="form-input" required placeholder="您的网站名称" maxlength="50">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyUrl">网站链接 *</label>
                <input type="url" id="applyUrl" name="siteurl" class="form-input" required placeholder="https://example.com" maxlength="255">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyIcon">头像链接</label>
                <input type="url" id="applyIcon" name="icon" class="form-input" placeholder="https://example.com/avatar.png（选填）" maxlength="255">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyDesc">网站描述</label>
                <input type="text" id="applyDesc" name="description" class="form-input" placeholder="简短描述您的网站" maxlength="200">
            </div>

            <div class="form-group">
                <label class="form-label" for="applyContact">联系方式</label>
                <input type="text" id="applyContact" name="contact" class="form-input" placeholder="QQ / 邮箱（选填）" maxlength="100">
            </div>

            <button type="submit" class="btn-geek applylink-submit" id="applySubmitBtn">提交申请</button>
        </form>

        <div class="tips-card">
            <div class="tips-title">申请须知</div>
            <ul class="tips-list">
                <li>网站需正常运营，内容合法合规</li>
                <li>优先考虑技术类、开发类网站</li>
                <li>请在贵站添加本站友链后再申请</li>
                <li>审核通过后将在友链页与页脚展示</li>
            </ul>
        </div>

        <p class="applylink-back">
            <a href="<?php echo vs_e($vsBase); ?>/links">← 返回友情链接</a>
        </p>
    </div>
</main>
