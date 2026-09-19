<?php
/**
 * 主题 five · 赞助
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$siteName = isset($siteName) ? (string) $siteName : SiteContext::siteName();
$qrs = class_exists('FrontendSponsor') ? FrontendSponsor::paymentQrs() : array();
$sponsors = class_exists('FrontendSponsor') ? FrontendSponsor::listForTheme() : array();
if (!is_array($qrs)) {
    $qrs = array();
}
if (!is_array($sponsors)) {
    $sponsors = array();
}
$firstQr = count($qrs) > 0 ? $qrs[0] : null;
?>
<section class="th5-page">
  <div class="th5-page__inner max-w-5xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 赞助</div>
    <h1 class="font-display font-bold tracking-tight th5-page__title">赞助 <?php echo vs_e($siteName); ?></h1>
    <p class="th5-page__lead text-fg-2">感谢支持。可选扫码赞助，或浏览已公示的赞助伙伴。</p>

    <?php if (count($qrs) > 0): ?>
    <div class="th5-panel card mt-8 th5-donate">
      <div class="th5-donate__tabs" role="tablist">
        <?php foreach ($qrs as $i => $qr): ?>
          <button type="button" class="th5-pill<?php echo $i === 0 ? ' is-active' : ''; ?>"
                  id="donateQrTab-<?php echo vs_e($qr['id']); ?>"
                  data-donate-qr-tab="<?php echo vs_e($qr['id']); ?>"><?php echo vs_e($qr['label']); ?></button>
        <?php endforeach; ?>
      </div>
      <div class="th5-donate__panel" id="donateQrPanel">
        <img id="donateQrImg" src="<?php echo $firstQr ? vs_e($firstQr['url']) : ''; ?>" alt="赞助二维码" width="220" height="220" loading="lazy">
        <div class="text-sm text-fg-2 mt-3" id="donateQrLabel"><?php echo $firstQr ? vs_e($firstQr['label']) : ''; ?></div>
      </div>
      <script type="application/json" id="donateQrData"><?php echo json_encode($qrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    </div>
    <?php endif; ?>

    <h2 class="font-display font-semibold text-xl mt-12 mb-4">赞助伙伴</h2>
    <?php if (count($sponsors) === 0): ?>
      <div class="th5-panel card text-fg-2">暂无公示赞助，欢迎扫码支持。</div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($sponsors as $sp): ?>
        <?php $href = !empty($sp['siteurl']) ? (string) $sp['siteurl'] : '#'; ?>
        <a class="th5-link-card card" href="<?php echo vs_e($href); ?>"<?php echo $href !== '#' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
          <?php if (!empty($sp['icon'])): ?>
            <img class="th5-link-card__avatar" src="<?php echo vs_e($sp['icon']); ?>" alt="" width="44" height="44" loading="lazy">
          <?php else: ?>
            <span class="th5-link-card__avatar th5-link-card__avatar--text"><?php
              if (!empty($sp['initial'])) {
                  echo vs_e($sp['initial']);
              } else {
                  $nm = isset($sp['name']) ? (string) $sp['name'] : '?';
                  echo vs_e(function_exists('mb_substr') ? mb_substr($nm, 0, 1, 'UTF-8') : substr($nm, 0, 1));
              }
            ?></span>
          <?php endif; ?>
          <div class="th5-link-card__body">
            <strong><?php echo vs_e($sp['name']); ?></strong>
            <?php if (!empty($sp['description'])): ?><p><?php echo vs_e($sp['description']); ?></p><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<script src="<?php echo vs_e(ThemeManager::assetUrl('muming', 'assets/js/pages/donate.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
