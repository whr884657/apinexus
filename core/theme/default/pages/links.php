<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$friendLinks = class_exists('FrontendLink') ? FrontendLink::listForTheme() : array();
$applyUrl = $vsBase . '/applylink';
?>
<main class="main-wrapper container mx-auto px-4 links-page">
    <div class="page-header">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>友情链接</h1>
        <p class="links-lead">与优质站点互相推荐，共同成长</p>
    </div>

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
                    <img class="link-avatar" src="<?php echo vs_e($item['icon']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
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
