<?php if (!defined('VS_THEME_RENDER')) { exit; }

$paymentQrs = class_exists('FrontendSponsor') ? FrontendSponsor::paymentQrs() : array();
$sponsors = class_exists('FrontendSponsor') ? FrontendSponsor::listForTheme() : array();
$siteName = class_exists('SiteContext') ? SiteContext::siteName() : '本站';
?>
<main class="main-wrapper container mx-auto px-4 donate-page" style="padding-top:88px;">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>赞助我们</h1>
        <p class="donate-lead">感谢支持 <?php echo vs_e($siteName); ?>。每一份心意都会用于站点维护与功能迭代。</p>
    </div>

    <section class="donate-section donate-section--qr" aria-labelledby="donateQrTitle">
        <h2 class="donate-section__title" id="donateQrTitle">扫码赞助</h2>
        <?php if (count($paymentQrs) === 0): ?>
            <p class="donate-empty">管理员尚未配置收款码。配置后将在此展示支付宝 / 微信 / QQ 二维码。</p>
        <?php else: ?>
            <div class="donate-qr-grid" data-donate-qr-grid>
                <?php foreach ($paymentQrs as $idx => $qr): ?>
                    <article class="donate-qr-card" data-donate-qr="<?php echo vs_e($qr['id']); ?>" style="--donate-i: <?php echo (int) $idx; ?>">
                        <div class="donate-qr-card__frame">
                            <img src="<?php echo vs_e($qr['url']); ?>" alt="<?php echo vs_e($qr['label'] . '收款码'); ?>"
                                 loading="lazy" referrerpolicy="no-referrer" width="160" height="160">
                        </div>
                        <h3 class="donate-qr-card__label"><?php echo vs_e($qr['label']); ?></h3>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="donate-section donate-section--list" aria-labelledby="donateListTitle">
        <h2 class="donate-section__title" id="donateListTitle">赞助榜</h2>
        <?php if (count($sponsors) === 0): ?>
            <p class="donate-empty">暂无公开赞助记录。后台添加并启用后将展示在此。</p>
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

    <section class="donate-section donate-section--more">
        <h2 class="donate-section__title">其它支持方式</h2>
        <ul class="donate-list">
            <li>为 <a href="https://gitee.com/xunjinlu/apinexus" target="_blank" rel="noopener noreferrer">Gitee 仓库</a> 点 Star</li>
            <li>提交 Bug 反馈与功能建议</li>
            <li>参与文档完善与接口贡献</li>
        </ul>
    </section>
</main>
