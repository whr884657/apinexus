<?php
/**
 * 主题 three · 赞助（自研 th3：双栏 + 专用赞助卡）
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
$qrCount = count($qrs);
$firstQr = $qrCount > 0 ? $qrs[0] : null;
?>
<section class="th3-page">
  <div class="th3-page__inner th3-page__inner--sponsor mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 赞助</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">赞助 <?php echo vs_e($siteName); ?></h1>
    <p class="th3-page__lead text-fg-2">感谢支持。每一份心意都会用于站点维护与功能迭代。</p>

    <div class="th3-donate-layout mt-8">
      <section class="th3-donate-qr card" aria-labelledby="donateQrTitle">
        <h2 class="th3-donate-section__title font-display" id="donateQrTitle">扫码赞助</h2>
        <?php if ($qrCount === 0): ?>
          <p class="th3-donate__empty">管理员尚未配置收款码。配置后将在此展示支付宝 / 微信 / QQ 二维码。</p>
        <?php else: ?>
          <div class="th3-donate__tabs" role="tablist" aria-label="收款方式">
            <?php foreach ($qrs as $i => $qr): ?>
              <button type="button" class="th3-pill<?php echo $i === 0 ? ' is-active' : ''; ?>"
                      role="tab"
                      id="donateQrTab-<?php echo vs_e($qr['id']); ?>"
                      aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                      aria-controls="donateQrPanel"
                      data-donate-qr-tab="<?php echo vs_e($qr['id']); ?>"><?php echo vs_e($qr['label']); ?></button>
            <?php endforeach; ?>
          </div>
          <div class="th3-donate__stage" id="donateQrPanel" role="tabpanel" aria-labelledby="donateQrTab-<?php echo vs_e($firstQr['id']); ?>">
            <div class="th3-donate__frame">
              <img id="donateQrImg" class="th3-donate__img" src="<?php echo vs_e($firstQr['url']); ?>"
                   alt="<?php echo vs_e($firstQr['label'] . '收款码'); ?>"
                   width="200" height="200" loading="lazy" decoding="async" referrerpolicy="no-referrer">
            </div>
            <div class="th3-donate__label text-sm text-fg-2" id="donateQrLabel"><?php echo vs_e($firstQr['label']); ?></div>
            <?php if ($qrCount > 1): ?>
              <p class="th3-donate__hint">点击上方按钮切换收款方式</p>
            <?php endif; ?>
          </div>
          <script type="application/json" id="donateQrData"><?php echo json_encode($qrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
        <?php endif; ?>
      </section>

      <section class="th3-donate-thanks card" aria-labelledby="donateThanksTitle">
        <h2 class="th3-donate-section__title font-display" id="donateThanksTitle">感谢支持</h2>
        <?php if (count($sponsors) === 0): ?>
          <p class="th3-donate__empty">暂未公示支持者信息。若您已赞助，感谢您的心意；公示名单由站长在后台添加。</p>
        <?php else: ?>
          <div class="th3-sponsor-grid">
            <?php foreach ($sponsors as $idx => $sp): ?>
              <?php
              $tag = !empty($sp['siteurl']) ? 'a' : 'div';
              $href = !empty($sp['siteurl'])
                  ? ' href="' . vs_e($sp['siteurl']) . '" target="_blank" rel="noopener noreferrer"'
                  : '';
              $initial = !empty($sp['initial'])
                  ? (string) $sp['initial']
                  : (function_exists('mb_substr') ? mb_substr((string) $sp['name'], 0, 1, 'UTF-8') : substr((string) $sp['name'], 0, 1));
              ?>
              <<?php echo $tag; ?> class="th3-sponsor-card"<?php echo $href; ?> style="--donate-i: <?php echo (int) $idx; ?>">
                <?php if (!empty($sp['icon'])): ?>
                  <img class="th3-sponsor-card__avatar" src="<?php echo vs_e($sp['icon']); ?>" alt="<?php echo vs_e($sp['name']); ?>"
                       width="48" height="48" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
                <?php else: ?>
                  <span class="th3-sponsor-card__avatar th3-sponsor-card__avatar--text"><?php echo vs_e($initial); ?></span>
                <?php endif; ?>
                <div class="th3-sponsor-card__body">
                  <span class="th3-sponsor-card__name"><?php echo vs_e($sp['name']); ?></span>
                  <?php if (!empty($sp['description'])): ?>
                    <span class="th3-sponsor-card__meta"><?php echo vs_e($sp['description']); ?></span>
                  <?php endif; ?>
                </div>
              </<?php echo $tag; ?>>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</section>
<script src="<?php echo vs_e(ThemeManager::assetUrl('three', 'assets/js/pages/donate.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
