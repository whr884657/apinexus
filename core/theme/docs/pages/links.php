<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();
$pagePack = class_exists('FrontendLink')
    ? FrontendLink::listForThemePage()
    : array('items' => array(), 'total' => 0, 'truncated' => false, 'limit' => 120);
$friendLinks = isset($pagePack['items']) && is_array($pagePack['items']) ? $pagePack['items'] : array();
$linksTotal = isset($pagePack['total']) ? (int) $pagePack['total'] : count($friendLinks);
$linksTruncated = !empty($pagePack['truncated']);
$applyUrl = $vsBase . '/applylink';
?>
<main class="main-wrapper container mx-auto px-4 links-page" style="padding-top:88px;">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>友情链接</h1>
        <p class="links-lead">与优质站点互相推荐，共同成长</p>
    </div>

    <?php if ($linksTruncated): ?>
    <div class="empty-state" style="margin-bottom:1.25rem;">
        <p>当前共 <?php echo (int) $linksTotal; ?> 条，为避免页面卡顿仅展示前 <?php echo (int) $pagePack['limit']; ?> 条。若数量异常偏多，请到后台检查友情链接 / 合作伙伴 / 赞助的类型是否被误改。</p>
    </div>
    <?php endif; ?>

    <?php if (count($friendLinks) === 0): ?>
    <div class="empty-state">
        <p>暂无友情链接</p>
    </div>
    <?php else: ?>
    <div class="links-grid">
        <?php foreach ($friendLinks as $item): ?>
            <a href="<?php echo vs_e($item['siteurl']); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="link-card"
               data-friend-link="1">
                <?php if (!empty($item['icon'])): ?>
                    <img class="link-avatar" src="<?php echo vs_e($item['icon']); ?>" alt="<?php echo vs_e($item['name']); ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
                <?php else: ?>
                    <div class="link-avatar"><?php echo vs_e($item['initial']); ?></div>
                <?php endif; ?>
                <div class="link-info">
                    <span class="link-name"><?php echo vs_e($item['name']); ?></span>
                    <?php if (!empty($item['description'])): ?>
                        <p class="link-desc"><?php echo vs_e($item['description']); ?></p>
                    <?php endif; ?>
                    <p class="link-url"><?php echo vs_e(!empty($item['host']) ? $item['host'] : $item['siteurl']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="apply-section">
        <h2 class="apply-title">申请友链</h2>
        <p class="apply-hint">欢迎交换友情链接。请先在贵站添加本站信息，再提交申请。</p>
        <a href="<?php echo vs_e($applyUrl); ?>" class="apply-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            申请友链
        </a>
    </div>
</main>
