<?php if (!defined('VS_THEME_RENDER')) { exit; }

$publicApis = ApiManager::listPublic();
$categories = ApiManager::categoriesFromList($publicApis);
$apiCount = count($publicApis);
?>
<main class="dt-main">
<div class="dt-container">
<section class="dt-page-head">
    <h1 class="dt-page-head__title">全部接口</h1>
    <p class="dt-page-head__desc">共 <span data-dt-api-total><?php echo (int) $apiCount; ?></span> 个已通过审核的公开接口</p>
</section>
<section class="dt-section dt-section--apis">
    <?php
    $toolbarId = 'dtApisToolbar';
    include __DIR__ . '/../partials/api-toolbar.php';
    ?>
    <?php
    $gridId = 'dtApisGrid';
    $cardLimit = 0;
    include __DIR__ . '/../partials/api-grid.php';
    ?>
</section>
</div>
</main>
