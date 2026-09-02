<?php
/**
 * 主题 three · 申请友链（首页视觉语言自研）
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
  <div class="th3-page__inner max-w-2xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 申请友链</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">申请友情链接</h1>
    <p class="th3-page__lead text-fg-2">请先在贵站添加本站信息，再填写下方表单。</p>

    <?php if (!empty($siteCard)): ?>
    <div class="th3-panel card mb-6">
      <div class="text-xs font-mono text-muted mb-2">本站信息（请先添加）</div>
      <div class="font-display font-semibold"><?php echo vs_e(isset($siteCard['name']) ? $siteCard['name'] : ''); ?></div>
      <div class="font-mono text-sm text-fg-2 mt-1 break-all"><?php echo vs_e(isset($siteCard['url']) ? $siteCard['url'] : ''); ?></div>
      <?php if (!empty($siteCard['desc'])): ?>
      <p class="text-sm text-fg-2 mt-2 mb-0"><?php echo vs_e($siteCard['desc']); ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div id="applyAlert" class="th3-alert" role="alert" hidden></div>

    <form id="applyLinkForm" class="th3-panel card th3-form" method="post" action="<?php echo vs_e($vsBase); ?>/applylink" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo vs_e($csrf); ?>">
      <input type="hidden" name="action" value="apply">

      <label class="th3-field">
        <span>网站地址</span>
        <div class="th3-field-row">
          <input class="input" id="applyUrl" name="siteurl" type="url" required placeholder="https://" autocomplete="url">
          <button type="button" class="btn-ghost" id="applyFetchBtn">一键获取</button>
        </div>
        <span class="th3-field-hint" id="applyFetchStatus"></span>
      </label>

      <label class="th3-field">
        <span>网站名称</span>
        <input class="input" id="applyName" name="name" type="text" required maxlength="64" placeholder="站点名称">
      </label>

      <label class="th3-field">
        <span>图标 URL（可选）</span>
        <input class="input" id="applyIcon" name="icon" type="url" maxlength="255" placeholder="https://…/favicon.ico">
      </label>

      <label class="th3-field">
        <span>简介（可选）</span>
        <textarea class="input th3-textarea" id="applyDesc" name="description" rows="3" maxlength="200" placeholder="一句话介绍"></textarea>
      </label>

      <label class="th3-field">
        <span>联系方式（可选）</span>
        <input class="input" id="applyContact" name="contact" type="text" maxlength="64" placeholder="邮箱 / QQ / 微信">
      </label>

      <button type="submit" class="btn-primary w-full justify-center" id="applySubmitBtn">提交申请</button>
      <p class="text-center text-sm text-muted mt-4 mb-0">
        <a href="<?php echo vs_e($vsBase); ?>/links" style="color:var(--accent);">返回友情链接</a>
      </p>
    </form>
  </div>
</section>
<script>
window.VS_CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
window.VS_LINK_META_URL = <?php echo json_encode($metaUrl, JSON_UNESCAPED_UNICODE); ?>;
</script>
