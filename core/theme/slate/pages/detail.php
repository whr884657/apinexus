<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$methods = (!$notFound && isset($api['methods']) && is_array($api['methods'])) ? $api['methods'] : array('GET');
?>
<main class="st-main st-main--page" id="stApiDetailPage">
<div class="st-wrap">
<section class="st-detail">
    <nav class="st-detail__crumb" aria-label="面包屑">
        <a href="<?php echo vs_e($vsBase); ?>/">首页</a>
        <span aria-hidden="true">/</span>
        <a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a>
        <span aria-hidden="true">/</span>
        <span><?php echo $notFound ? '未找到' : vs_e($api['name']); ?></span>
    </nav>

    <?php if ($notFound): ?>
    <div class="st-detail__panel st-detail__panel--empty">
        <h1 class="st-detail__title">接口不存在</h1>
        <p class="st-detail__lead">该接口不存在、未通过审核或已下架，请从全部接口列表重新选择。</p>
        <div class="st-detail__actions">
            <a class="st-detail__btn st-detail__btn--primary" href="<?php echo vs_e($vsBase); ?>/apis">返回全部接口</a>
        </div>
    </div>
    <?php else: ?>
    <header class="st-detail__hero">
        <div class="st-detail__meta">
            <?php foreach ($methods as $m): ?>
            <span class="st-api-card__method st-api-card__method--<?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
            <?php endforeach; ?>
            <?php if (!empty($api['maintenance'])): ?>
            <span class="st-detail__chip st-detail__chip--warn">维护中</span>
            <?php else: ?>
            <span class="st-detail__chip">免费</span>
            <?php endif; ?>
            <?php if (!empty($api['needkey_label'])): ?>
            <span class="st-detail__chip">密钥：<?php echo vs_e($api['needkey_label']); ?></span>
            <?php endif; ?>
            <?php if (!empty($api['category_name'])): ?>
            <span class="st-detail__chip"><?php echo vs_e($api['category_name']); ?></span>
            <?php endif; ?>
        </div>
        <h1 class="st-detail__title"><?php echo vs_e($api['name']); ?></h1>
        <p class="st-detail__id">#<?php echo (int) $api['id']; ?><?php if (!empty($api['calls'])): ?> · 调用 <?php echo (int) $api['calls']; ?> 次<?php endif; ?></p>
        <?php if (!empty($api['desc'])): ?>
        <p class="st-detail__desc"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($api['endpoint'])): ?>
    <div class="st-detail__panel">
        <h2 class="st-detail__h">调用地址</h2>
        <code class="st-detail__endpoint" id="stDetailEndpoint"><?php echo vs_e($api['endpoint']); ?></code>
        <div class="st-detail__actions st-detail__actions--inline">
            <button type="button" class="st-detail__btn" id="stDetailCopyBtn" data-copy="<?php echo vs_e($api['endpoint']); ?>">复制地址</button>
            <?php if (empty($api['maintenance'])): ?>
            <a class="st-detail__btn st-detail__btn--primary" href="<?php echo vs_e($api['endpoint']); ?>" target="_blank" rel="noopener noreferrer">打开接口</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($api['params'])): ?>
    <div class="st-detail__panel">
        <h2 class="st-detail__h">请求参数</h2>
        <pre class="st-detail__pre"><?php echo vs_e($api['params']); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($api['response'])): ?>
    <div class="st-detail__panel">
        <h2 class="st-detail__h">返回示例</h2>
        <pre class="st-detail__pre"><?php echo vs_e($api['response']); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($api['doc'])): ?>
    <div class="st-detail__panel">
        <h2 class="st-detail__h">接口文档</h2>
        <div class="st-detail__doc markdown-body"><?php echo Markdown::render((string) $api['doc']); ?></div>
    </div>
    <?php endif; ?>

    <div class="st-detail__actions">
        <a class="st-detail__btn" href="<?php echo vs_e($vsBase); ?>/apis">返回全部接口</a>
    </div>
    <?php endif; ?>
</section>
</div>
</main>
<link rel="stylesheet" href="<?php echo vs_e($vsBase); ?>/core/markdown/assets/css/markdown-render.css?v=<?php echo vs_e(VS_VERSION); ?>">
<script>
(function () {
    var btn = document.getElementById('stDetailCopyBtn');
    if (!btn || !navigator.clipboard) { return; }
    btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        navigator.clipboard.writeText(text).then(function () {
            var old = btn.textContent;
            btn.textContent = '已复制';
            setTimeout(function () { btn.textContent = old; }, 1200);
        }).catch(function () {});
    });
})();
</script>
