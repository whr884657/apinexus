<?php if (!defined('VS_THEME_RENDER')) { exit; }

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$applyUrl = $vsBase . '/applylink';
?>
<main class="st-main"><div class="st-wrap">
<section class="st-section st-links-page" data-vs-links-page="1" data-layout="slate">
    <div class="st-links-head">
        <div>
            <h1 class="st-page-title">友情链接</h1>
            <p class="st-page-desc">与优质站点互相推荐，共同成长</p>
        </div>
    </div>

    <p class="st-notice-box" data-vs-links-loading="1">正在加载友链…</p>
    <p class="st-notice-box" data-vs-links-truncated="1" hidden><span data-vs-links-trunc-text></span></p>

    <div class="st-card st-links-empty" data-vs-links-empty="1" hidden>
        <div class="st-card__title">暂无友情链接</div>
        <div class="st-card__desc">欢迎交换友链。请先在贵站添加本站信息，再提交申请。</div>
    </div>
    <div class="st-links-grid" data-vs-links-grid="1" hidden></div>

    <aside class="st-links-apply-panel">
        <div class="st-links-apply-panel__copy">
            <h2 class="st-links-apply-panel__title">申请友链</h2>
            <p class="st-links-apply-panel__hint">欢迎交换友情链接。请先在贵站添加本站信息，再提交申请。</p>
        </div>
        <a class="st-links-apply-btn st-links-apply-btn--solid" href="<?php echo vs_e($applyUrl); ?>">申请友链</a>
    </aside>
</section>
</div></main>
