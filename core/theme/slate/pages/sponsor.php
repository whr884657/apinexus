<?php if (!defined('VS_THEME_RENDER')) { exit; }

$paymentQrs = class_exists('FrontendSponsor') ? FrontendSponsor::paymentQrs() : array();
$sponsors = class_exists('FrontendSponsor') ? FrontendSponsor::listForTheme() : array();
$siteName = class_exists('SiteContext') ? SiteContext::siteName() : '本站';
$qrCount = count($paymentQrs);
$firstQr = $qrCount > 0 ? $paymentQrs[0] : null;
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">赞助我们</h1>
    <p class="st-page-desc">感谢支持 <?php echo vs_e($siteName); ?>。每一份心意都会用于站点维护与功能迭代。</p>

    <div class="st-sponsor-layout">
        <section class="st-card st-sponsor-panel st-sponsor-panel--qr" aria-labelledby="donateQrTitle">
            <h2 class="st-card__title" id="donateQrTitle">扫码赞助</h2>
            <?php if ($qrCount === 0): ?>
                <p class="st-notice-box st-sponsor-empty">管理员尚未配置收款码。配置后将在此展示支付宝 / 微信 / QQ 二维码。</p>
            <?php else: ?>
                <div class="st-sponsor-qr"
                     data-donate-qr-switch
                     data-qr-count="<?php echo (int) $qrCount; ?>">
                    <?php if ($qrCount > 1): ?>
                        <div class="st-sponsor-qr__tabs" role="tablist" aria-label="收款方式">
                            <?php foreach ($paymentQrs as $idx => $qr): ?>
                                <button type="button"
                                        class="st-sponsor-qr__tab<?php echo $idx === 0 ? ' is-active' : ''; ?>"
                                        role="tab"
                                        id="donateQrTab-<?php echo vs_e($qr['id']); ?>"
                                        aria-selected="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
                                        aria-controls="donateQrPanel"
                                        data-donate-qr-tab
                                        data-qr-index="<?php echo (int) $idx; ?>"
                                        data-qr-id="<?php echo vs_e($qr['id']); ?>"
                                        data-qr-label="<?php echo vs_e($qr['label']); ?>"
                                        data-qr-url="<?php echo vs_e($qr['url']); ?>">
                                    <?php echo vs_e($qr['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="st-sponsor-qr__stage" id="donateQrPanel" role="tabpanel" aria-labelledby="donateQrTab-<?php echo vs_e($firstQr['id']); ?>">
                        <div class="st-sponsor-qr__frame">
                            <img class="st-sponsor-qr__img is-active"
                                 id="donateQrImg"
                                 src="<?php echo vs_e($firstQr['url']); ?>"
                                 alt="<?php echo vs_e($firstQr['label'] . '收款码'); ?>"
                                 width="200"
                                 height="200"
                                 decoding="async"
                                 referrerpolicy="no-referrer">
                        </div>
                        <p class="st-sponsor-qr__label" id="donateQrLabel"><?php echo vs_e($firstQr['label']); ?></p>
                        <?php if ($qrCount > 1): ?>
                            <p class="st-sponsor-qr__hint">点击上方按钮切换收款方式</p>
                        <?php endif; ?>
                    </div>

                    <script type="application/json" id="donateQrData"><?php
                        echo json_encode($paymentQrs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                    ?></script>
                </div>
            <?php endif; ?>
        </section>

        <section class="st-sponsor-panel st-sponsor-panel--thanks" aria-labelledby="donateThanksTitle">
            <h2 class="st-card__title" id="donateThanksTitle">感谢支持</h2>
            <?php if (count($sponsors) === 0): ?>
                <p class="st-notice-box st-sponsor-empty">暂未公示支持者信息。若您已赞助，感谢您的心意；公示名单由站长在后台添加。</p>
            <?php else: ?>
                <div class="st-sponsor-grid" data-donate-sponsor-grid>
                    <?php foreach ($sponsors as $idx => $item): ?>
                        <?php
                        $tag = !empty($item['siteurl']) ? 'a' : 'div';
                        $href = !empty($item['siteurl'])
                            ? ' href="' . vs_e($item['siteurl']) . '" target="_blank" rel="noopener noreferrer"'
                            : '';
                        ?>
                        <<?php echo $tag; ?> class="st-sponsor-card donate-sponsor-card"<?php echo $href; ?>
                           style="--donate-i: <?php echo (int) $idx; ?>">
                            <?php if (!empty($item['icon'])): ?>
                                <img class="st-sponsor-card__avatar donate-sponsor-card__avatar" src="<?php echo vs_e($item['icon']); ?>" alt="<?php echo vs_e($item['name']); ?>"
                                     loading="lazy" decoding="async" referrerpolicy="no-referrer" width="48" height="48" data-ext-icon="1">
                            <?php else: ?>
                                <div class="st-sponsor-card__avatar st-sponsor-card__avatar--text donate-sponsor-card__avatar donate-sponsor-card__avatar--text"><?php echo vs_e($item['initial']); ?></div>
                            <?php endif; ?>
                            <div class="st-sponsor-card__body donate-sponsor-card__body">
                                <span class="st-sponsor-card__name donate-sponsor-card__name"><?php echo vs_e($item['name']); ?></span>
                                <?php if (!empty($item['description'])): ?>
                                    <span class="st-sponsor-card__meta donate-sponsor-card__meta"><?php echo vs_e($item['description']); ?></span>
                                <?php endif; ?>
                            </div>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
</div></main>
<script src="<?php echo vs_e(ThemeManager::assetUrl('slate', 'assets/js/pages/donate.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
