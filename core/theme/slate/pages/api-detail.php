<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
?>
<main class="st-main" id="stApiDetailPage">
<div class="st-wrap">
<section class="st-section st-api-detail">
    <h1 class="st-page-title"><?php echo $notFound ? '接口不存在' : vs_e($api['name']); ?></h1>
    <p class="st-page-desc">
        <?php if ($notFound): ?>
            该接口不存在、未通过审核或已下架
        <?php else: ?>
            #<?php echo (int) $api['id']; ?>
            <?php if (!empty($api['maintenance'])): ?> · 维护中<?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if ($notFound): ?>
    <div class="st-api-empty">
        <p class="st-api-empty__title">找不到该接口</p>
        <a class="st-bar__login" href="<?php echo vs_e($vsBase); ?>/apis">返回全部接口</a>
    </div>
    <?php else: ?>
    <div class="st-api-detail__meta">
        <?php foreach ((isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET')) as $m): ?>
            <span class="st-api-card__method st-api-card__method--<?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
        <?php endforeach; ?>
        <?php if (!empty($api['needkey_label'])): ?>
            <span class="st-api-card__badge">密钥：<?php echo vs_e($api['needkey_label']); ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($api['desc'])): ?>
    <p class="st-api-detail__desc"><?php echo vs_e($api['desc']); ?></p>
    <?php endif; ?>

    <?php if (!empty($api['endpoint'])): ?>
    <h2 class="st-api-detail__h">调用地址</h2>
    <code class="st-api-card__endpoint"><?php echo vs_e($api['endpoint']); ?></code>
    <?php endif; ?>

    <?php if (!empty($api['params'])): ?>
    <h2 class="st-api-detail__h">请求参数</h2>
    <pre class="st-api-detail__pre"><?php echo vs_e($api['params']); ?></pre>
    <?php endif; ?>

    <?php if (!empty($api['response'])): ?>
    <h2 class="st-api-detail__h">返回示例</h2>
    <pre class="st-api-detail__pre"><?php echo vs_e($api['response']); ?></pre>
    <?php endif; ?>

    <?php if (!empty($api['doc'])): ?>
    <h2 class="st-api-detail__h">接口文档</h2>
    <div class="st-api-detail__doc"><?php echo vs_e($api['doc']); ?></div>
    <?php endif; ?>

    <div class="st-api-detail__actions">
        <a class="st-bar__login" href="<?php echo vs_e($vsBase); ?>/apis">返回全部接口</a>
        <?php if (!empty($api['endpoint']) && empty($api['maintenance'])): ?>
        <a class="st-bar__login" href="<?php echo vs_e($api['endpoint']); ?>" target="_blank" rel="noopener noreferrer">打开接口</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
</div>
</main>
