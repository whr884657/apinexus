<?php
/**
 * 主题 three · 申请友链（自研 th3）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteCard = isset($siteCard) && is_array($siteCard)
    ? $siteCard
    : (class_exists('FrontendLink') ? FrontendLink::siteCard() : array());
$csrf = class_exists('AuthSecurity') ? AuthSecurity::csrfToken() : '';
$metaUrl = $vsBase . '/core/theme/three/api/sitemeta.php';
?>
<section class="th3-page">
  <div class="th3-page__inner th3-page__inner--apply mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 申请友链</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">申请友情链接</h1>
    <p class="th3-page__lead text-fg-2">欢迎优质网站交换友链，共同发展</p>

    <?php if (!empty($siteCard)): ?>
    <div class="th3-panel card th3-apply-info mb-6">
      <div class="th3-apply-info__title">本站友链信息（请先在贵站添加）</div>
      <div class="th3-apply-info__lines">
        <p><strong>名称：</strong><?php echo vs_e(isset($siteCard['name']) ? $siteCard['name'] : ''); ?></p>
        <p><strong>链接：</strong><span class="font-mono break-all"><?php echo vs_e(isset($siteCard['url']) ? $siteCard['url'] : ''); ?></span></p>
        <?php if (!empty($siteCard['desc'])): ?>
        <p><strong>简介：</strong><?php echo vs_e($siteCard['desc']); ?></p>
        <?php endif; ?>
        <?php if (!empty($siteCard['icon'])): ?>
        <p><strong>图标：</strong><span class="font-mono break-all"><?php echo vs_e($siteCard['icon']); ?></span></p>
        <?php endif; ?>
        <p class="th3-apply-info__note">请先在贵站添加本站友链后再提交申请。</p>
      </div>
    </div>
    <?php endif; ?>

    <div id="applyAlert" class="th3-alert" role="alert" hidden></div>

    <div class="th3-panel card th3-apply-form">
      <form id="applyLinkForm" class="th3-form" method="post" action="<?php echo vs_e($vsBase); ?>/applylink" data-ajax="1" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
        <input type="hidden" name="action" value="apply">

        <label class="th3-field">
          <span>网站链接 *</span>
          <input class="input" id="applyUrl" name="siteurl" type="url" required maxlength="255" placeholder="https://example.com" autocomplete="url">
          <button type="button" class="btn-ghost th3-apply-fetch" id="applyFetchBtn">一键获取网站信息</button>
          <span class="th3-field-hint" id="applyFetchStatus" aria-live="polite"></span>
        </label>

        <label class="th3-field">
          <span>网站名称 *</span>
          <input class="input" id="applyName" name="name" type="text" required maxlength="50" placeholder="填写链接后可一键获取">
        </label>

        <label class="th3-field">
          <span>头像链接</span>
          <input class="input" id="applyIcon" name="icon" type="url" maxlength="255" placeholder="可一键获取，也可手填">
        </label>

        <label class="th3-field">
          <span>网站描述</span>
          <input class="input" id="applyDesc" name="description" type="text" maxlength="200" placeholder="可一键获取，也可手填">
        </label>

        <label class="th3-field">
          <span>联系方式</span>
          <input class="input" id="applyContact" name="contact" type="text" maxlength="100" placeholder="建议填写邮箱，审核通过后可收到通知">
        </label>

        <button type="submit" class="btn-primary w-full justify-center" id="applySubmitBtn">提交申请</button>
      </form>

      <div class="th3-apply-tips">
        <div class="th3-apply-tips__title">申请须知</div>
        <ul class="th3-apply-tips__list">
          <li>先填写网站链接，点击「一键获取」可自动填充名称、图标与描述</li>
          <li>联系方式建议填邮箱，审核通过后系统可发信通知您</li>
          <li>网站需正常运营，内容合法合规</li>
          <li>请在贵站添加本站友链后再申请</li>
        </ul>
      </div>

      <p class="th3-apply-back text-center text-sm mt-4 mb-0">
        <a href="<?php echo vs_e($vsBase); ?>/links">← 返回友情链接</a>
      </p>
    </div>
  </div>
</section>
<script>
window.VS_CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
window.VS_LINK_META_URL = <?php echo json_encode($metaUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
