<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$categories = isset($categories) && is_array($categories) ? $categories : array();
$toolbarId = isset($toolbarId) ? (string) $toolbarId : 'dtApiToolbar';
$searchPlaceholder = isset($searchPlaceholder) ? (string) $searchPlaceholder : '搜索接口名称或描述…';
?>
<div class="dt-api-toolbar" id="<?php echo vs_e($toolbarId); ?>" data-dt-api-toolbar>
    <div class="dt-api-toolbar__search-row">
        <div class="dt-api-search">
            <svg class="dt-api-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3" stroke-linecap="round"/></svg>
            <input type="search" class="dt-api-search__input" data-dt-api-search placeholder="<?php echo vs_e($searchPlaceholder); ?>" autocomplete="off">
        </div>
        <button type="button" class="dt-btn dt-btn--ghost dt-api-reset" data-dt-api-reset hidden>重置</button>
    </div>
    <div class="dt-api-cats" data-dt-api-cats>
        <button type="button" class="dt-api-cat is-active" data-category="">全部</button>
        <?php
        $visibleLimit = 8;
        foreach ($categories as $idx => $cat):
            $hiddenClass = $idx >= $visibleLimit ? ' is-collapsed' : '';
        ?>
            <button type="button" class="dt-api-cat<?php echo $hiddenClass; ?>" data-category="<?php echo vs_e($cat); ?>"><?php echo vs_e($cat); ?></button>
        <?php endforeach; ?>
        <?php if (count($categories) > $visibleLimit): ?>
            <button type="button" class="dt-api-cat-more" data-dt-cat-more>
                <span>更多分类</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        <?php endif; ?>
    </div>
</div>
