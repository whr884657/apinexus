<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();
$applyUrl = $vsBase . '/applylink';
?>
<main class="main-wrapper container mx-auto px-4 links-page" style="padding-top:88px;" data-vs-links-page="1" data-layout="default">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><span class="section-title__mark" aria-hidden="true">//</span>友情链接</h1>
        <p class="links-lead">与优质站点互相推荐，共同成长</p>
    </div>

    <div class="empty-state" data-vs-links-loading="1">
        <p>正在加载友链…</p>
    </div>
    <div class="empty-state" data-vs-links-truncated="1" hidden>
        <p data-vs-links-trunc-text></p>
    </div>
    <div class="empty-state" data-vs-links-empty="1" hidden>
        <p>暂无友情链接</p>
    </div>
    <div class="links-grid" data-vs-links-grid="1" hidden></div>

    <div class="apply-section">
        <h2 class="apply-title">申请友链</h2>
        <p class="apply-hint">欢迎交换友情链接。请先在贵站添加本站信息，再提交申请。</p>
        <a href="<?php echo vs_e($applyUrl); ?>" class="apply-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            申请友链
        </a>
    </div>
</main>
