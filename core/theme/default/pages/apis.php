<?php if (!defined('VS_THEME_RENDER')) { exit; }

$categoryNames = FrontendCategory::nameMap();
$apiData = FrontendApi::listForTheme();
$apiCount = count($apiData);
$visibleLimit = FrontendCategory::tagVisibleLimit();
$catIndex = 0;
?>
<main class="main-wrapper container mx-auto px-4" style="padding-top:70px;">
    <div class="page-header">
        <h1 class="section-title">全部接口</h1>
        <p class="text-sm font-mono" style="color: var(--text-muted);">共 <span id="apiTotalCount"><?php echo (int) $apiCount; ?></span> 个 API 接口</p>
    </div>
    <section class="py-4">
        <div class="mb-4">
            <div class="flex gap-2 flex-wrap">
                <input type="text" id="apiSearchInput" class="search-input font-mono" style="flex: 1; min-width: 150px;" placeholder="搜索接口名称、描述..." oninput="filterApis()">
                <button type="button" class="btn-geek" onclick="filterApis()">搜索</button>
                <button type="button" id="apiResetBtn" class="btn-geek" style="border-color: #52525b; color: #a1a1aa; display: none;" onclick="resetApis()">重置</button>
            </div>
        </div>
        <div class="category-tags" id="apiCategoryTags">
            <a href="javascript:void(0)" class="category-tag active" data-category="<?php echo vs_e(FrontendCategory::ALL_ID); ?>" onclick="selectCategory(this, '<?php echo vs_e(FrontendCategory::ALL_ID); ?>')"><?php echo vs_e(FrontendCategory::ALL_NAME); ?></a>
            <?php foreach (FrontendCategory::listTags() as $tag): ?>
                <?php
                $hidden = $catIndex >= $visibleLimit ? ' category-hidden' : '';
                $catIndex++;
                ?>
                <a href="javascript:void(0)" class="category-tag<?php echo $hidden; ?>" data-category="<?php echo vs_e($tag['id']); ?>" onclick="selectCategory(this, '<?php echo vs_e($tag['id']); ?>')"><?php echo vs_e($tag['name']); ?></a>
            <?php endforeach; ?>
            <?php if ($catIndex > $visibleLimit): ?>
            <button type="button" class="category-more" id="catMoreBtn" onclick="toggleMoreCategories()">
                <span>更多</span>
                <svg class="expand-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"></path></svg>
            </button>
            <?php endif; ?>
        </div>
        <div id="apiCardContainer" class="card-container">
            <?php include __DIR__ . '/../partials/api-cards-html.php'; ?>
        </div>
        <div id="apiPagination" class="pagination" style="display:none;"></div>
    </section>
</main>
