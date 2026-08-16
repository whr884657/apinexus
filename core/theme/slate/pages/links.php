<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$pagePack = class_exists('FrontendLink')
    ? FrontendLink::listForThemePage()
    : array('items' => array(), 'total' => 0, 'truncated' => false, 'limit' => 120);
$friendLinks = isset($pagePack['items']) && is_array($pagePack['items']) ? $pagePack['items'] : array();
$linksTotal = isset($pagePack['total']) ? (int) $pagePack['total'] : count($friendLinks);
$linksTruncated = !empty($pagePack['truncated']);
$applyUrl = $vsBase . '/applylink';
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section">
    <h1 class="st-page-title">友情链接</h1>
    <p class="st-page-desc">与优质站点互相推荐，共同成长</p>

    <?php if ($linksTruncated): ?>
    <p class="st-page-desc" style="margin-top:-0.5rem;">当前共 <?php echo (int) $linksTotal; ?> 条，本页仅展示前 <?php echo (int) $pagePack['limit']; ?> 条。</p>
    <?php endif; ?>

    <?php if (count($friendLinks) === 0): ?>
    <div class="st-card">
        <div class="st-card__title">暂无友情链接</div>
        <div class="st-card__desc">欢迎交换友链，点击下方申请。</div>
    </div>
    <?php else: ?>
    <div class="st-links-list" style="display:flex;flex-direction:column;gap:0.75rem;">
        <?php foreach ($friendLinks as $item): ?>
            <a class="st-link-item" href="<?php echo vs_e($item['siteurl']); ?>" target="_blank" rel="noopener noreferrer" data-friend-link="1">
                <strong><?php echo vs_e($item['name']); ?></strong>
                <?php if (!empty($item['description'])): ?>
                <div class="st-card__meta" style="margin-top:4px;"><?php echo vs_e($item['description']); ?></div>
                <?php endif; ?>
                <div class="st-card__meta" style="margin-top:4px;"><?php echo vs_e(!empty($item['host']) ? $item['host'] : $item['siteurl']); ?></div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:1.5rem;">
        <a class="st-bar__login" href="<?php echo vs_e($applyUrl); ?>">申请友链</a>
    </div>
</section>
</div></main>
