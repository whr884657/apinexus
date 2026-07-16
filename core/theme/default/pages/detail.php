<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$methods = (!$notFound && isset($api['methods']) && is_array($api['methods'])) ? $api['methods'] : array('GET');
?>
<main class="main-wrapper container mx-auto px-4 detail-page" id="apiDetailPage">
    <nav class="detail-crumb text-sm mb-4" aria-label="面包屑">
        <a href="<?php echo vs_e($vsBase); ?>/">首页</a>
        <span class="detail-crumb__sep">/</span>
        <a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a>
        <span class="detail-crumb__sep">/</span>
        <span><?php echo $notFound ? '未找到' : vs_e($api['name']); ?></span>
    </nav>

    <?php if ($notFound): ?>
    <section class="detail-panel detail-panel--empty">
        <h1 class="section-title">接口不存在</h1>
        <p class="detail-lead">该接口不存在、未通过审核或已下架，请从全部接口列表重新选择。</p>
        <div class="detail-actions">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
        </div>
    </section>
    <?php else: ?>
    <header class="detail-hero">
        <div class="detail-meta">
            <?php foreach ($methods as $m): ?>
                <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
            <?php endforeach; ?>
            <?php if (!empty($api['maintenance'])): ?>
                <span class="api-chip api-chip--maintenance">维护中</span>
            <?php else: ?>
                <span class="api-chip api-chip--free">免费</span>
            <?php endif; ?>
            <?php if (!empty($api['needkey_label'])): ?>
                <span class="api-chip api-chip--key">密钥：<?php echo vs_e($api['needkey_label']); ?></span>
            <?php endif; ?>
            <?php if (!empty($api['category_name'])): ?>
                <span class="api-chip"><?php echo vs_e($api['category_name']); ?></span>
            <?php endif; ?>
        </div>
        <h1 class="section-title detail-title"><?php echo vs_e($api['name']); ?></h1>
        <p class="detail-id font-mono">#<?php echo (int) $api['id']; ?><?php if (!empty($api['calls'])): ?> · 调用 <?php echo (int) $api['calls']; ?> 次<?php endif; ?></p>
        <?php if (!empty($api['desc'])): ?>
        <p class="detail-desc"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($api['endpoint'])): ?>
    <section class="detail-panel">
        <h2 class="detail-h">调用地址</h2>
        <div class="endpoint-box font-mono" id="detailEndpoint"><?php echo vs_e($api['endpoint']); ?></div>
        <div class="detail-actions detail-actions--inline">
            <button type="button" class="btn-geek" id="detailCopyBtn" data-copy="<?php echo vs_e($api['endpoint']); ?>">复制地址</button>
            <?php if (empty($api['maintenance'])): ?>
            <a href="<?php echo vs_e($api['endpoint']); ?>" class="btn-geek" target="_blank" rel="noopener noreferrer">打开接口</a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['params'])): ?>
    <section class="detail-panel">
        <h2 class="detail-h">请求参数</h2>
        <pre class="detail-pre font-mono"><?php echo vs_e($api['params']); ?></pre>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['response'])): ?>
    <section class="detail-panel">
        <h2 class="detail-h">返回示例</h2>
        <pre class="detail-pre font-mono"><?php echo vs_e($api['response']); ?></pre>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['doc'])): ?>
    <section class="detail-panel">
        <h2 class="detail-h">接口文档</h2>
        <div class="detail-doc"><?php echo vs_e($api['doc']); ?></div>
    </section>
    <?php endif; ?>

    <div class="detail-actions">
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
    </div>
    <?php endif; ?>
</main>
<script>
(function () {
    var btn = document.getElementById('detailCopyBtn');
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
