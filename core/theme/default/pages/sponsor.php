<?php if (!defined('VS_THEME_RENDER')) { exit; }

$paymentQrs = class_exists('FrontendSponsor') ? FrontendSponsor::paymentQrs() : array();
$sponsors = class_exists('FrontendSponsor') ? FrontendSponsor::listForTheme() : array();
$siteName = class_exists('SiteContext') ? SiteContext::siteName() : '本站';
$qrCount = count($paymentQrs);
$firstQr = $qrCount > 0 ? $paymentQrs[0] : null;
?>
<main class="main-wrapper container mx-auto px-4 donate-page" style="padding-top:88px;">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>赞助我们</h1>
        <p class="donate-lead">感谢支持 <?php echo vs_e($siteName); ?>。每一份心意都会用于站点维护与功能迭代。</p>
    </div>

    <div class="donate-layout">
        <section class="donate-panel donate-panel--qr" aria-labelledby="donateQrTitle">
            <h2 class="donate-section__title" id="donateQrTitle">扫码赞助</h2>
            <?php if ($qrCount === 0): ?>
                <p class="donate-empty">管理员尚未配置收款码。配置后将在此展示支付宝 / 微信 / QQ 二维码。</p>
            <?php else: ?>
                <div class="donate-qr-switch"
                     data-donate-qr-switch
                     data-qr-count="<?php echo (int) $qrCount; ?>">
                    <?php if ($qrCount > 1): ?>
                        <div class="donate-qr-tabs" role="tablist" aria-label="收款方式">
                            <?php foreach ($paymentQrs as $idx => $qr): ?>
                                <button type="button"
                                        class="donate-qr-tab<?php echo $idx === 0 ? ' is-active' : ''; ?>"
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

                    <div class="donate-qr-stage" id="donateQrPanel" role="tabpanel" aria-labelledby="donateQrTab-<?php echo vs_e($firstQr['id']); ?>">
                        <div class="donate-qr-stage__frame">
                            <img class="donate-qr-stage__img is-active"
                                 id="donateQrImg"
                                 src="<?php echo vs_e($firstQr['url']); ?>"
                                 alt="<?php echo vs_e($firstQr['label'] . '收款码'); ?>"
                                 width="200"
                                 height="200"
                                 decoding="async"
                                 referrerpolicy="no-referrer">
                        </div>
                        <p class="donate-qr-stage__label" id="donateQrLabel"><?php echo vs_e($firstQr['label']); ?></p>
                        <?php if ($qrCount > 1): ?>
                            <p class="donate-qr-stage__hint">点击上方按钮切换收款方式</p>
                        <?php endif; ?>
                    </div>

                    <?php /* 供 JS 读取全部二维码，避免依赖 DOM 属性截断 */ ?>
                    <script type="application/json" id="donateQrData"><?php
                        echo json_encode($paymentQrs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                    ?></script>
                </div>
            <?php endif; ?>
        </section>

        <section class="donate-panel donate-panel--thanks" aria-labelledby="donateThanksTitle">
            <h2 class="donate-section__title" id="donateThanksTitle">感谢支持</h2>
            <?php if (count($sponsors) === 0): ?>
                <p class="donate-empty">暂未公示支持者信息。若您已赞助，感谢您的心意；公示名单由站长在后台添加。</p>
            <?php else: ?>
                <div class="donate-sponsor-grid" data-donate-sponsor-grid>
                    <?php foreach ($sponsors as $idx => $item): ?>
                        <?php
                        $tag = !empty($item['siteurl']) ? 'a' : 'div';
                        $href = !empty($item['siteurl'])
                            ? ' href="' . vs_e($item['siteurl']) . '" target="_blank" rel="noopener noreferrer"'
                            : '';
                        ?>
                        <<?php echo $tag; ?> class="donate-sponsor-card"<?php echo $href; ?>
                           style="--donate-i: <?php echo (int) $idx; ?>">
                            <?php if (!empty($item['icon'])): ?>
                                <img class="donate-sponsor-card__avatar" src="<?php echo vs_e($item['icon']); ?>" alt=""
                                     loading="lazy" referrerpolicy="no-referrer" width="48" height="48">
                            <?php else: ?>
                                <div class="donate-sponsor-card__avatar donate-sponsor-card__avatar--text"><?php echo vs_e($item['initial']); ?></div>
                            <?php endif; ?>
                            <div class="donate-sponsor-card__body">
                                <span class="donate-sponsor-card__name"><?php echo vs_e($item['name']); ?></span>
                                <?php if (!empty($item['description'])): ?>
                                    <span class="donate-sponsor-card__meta"><?php echo vs_e($item['description']); ?></span>
                                <?php endif; ?>
                            </div>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
