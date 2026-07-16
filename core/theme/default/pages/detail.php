<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
?>
<main class="main-wrapper container mx-auto px-4" style="padding-top:70px;">
    <div class="page-header page-header--compact">
        <h1 class="section-title"><?php echo $notFound ? '接口不存在' : vs_e($api['name']); ?></h1>
        <p class="text-sm font-mono" style="color: var(--text-muted);">
            <?php if ($notFound): ?>
                该接口不存在、未通过审核或已下架
            <?php else: ?>
                #<?php echo (int) $api['id']; ?>
                <?php if (!empty($api['maintenance'])): ?> · 维护中<?php endif; ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($notFound): ?>
    <section class="py-8 text-center">
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">请从全部接口列表重新选择。</p>
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
    </section>
    <?php else: ?>
    <section class="py-4 detail">
        <div class="detail__meta flex flex-wrap gap-2 mb-4">
            <?php foreach ((isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET')) as $m): ?>
                <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
            <?php endforeach; ?>
            <?php if (!empty($api['maintenance'])): ?>
                <span class="api-chip api-chip--maintenance">维护中</span>
            <?php else: ?>
                <span class="api-chip api-chip--free">免费</span>
            <?php endif; ?>
            <?php if (!empty($api['needkey_label'])): ?>
                <span class="api-chip">密钥：<?php echo vs_e($api['needkey_label']); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($api['desc'])): ?>
        <p class="detail__desc mb-4" style="color: var(--text-muted); line-height: 1.7;"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>

        <?php if (!empty($api['endpoint'])): ?>
        <div class="mb-4">
            <h2 class="text-sm font-bold mb-2">调用地址</h2>
            <div class="endpoint-box font-mono"><?php echo vs_e($api['endpoint']); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($api['params'])): ?>
        <div class="mb-4">
            <h2 class="text-sm font-bold mb-2">请求参数</h2>
            <pre class="detail__pre font-mono"><?php echo vs_e($api['params']); ?></pre>
        </div>
        <?php endif; ?>

        <?php if (!empty($api['response'])): ?>
        <div class="mb-4">
            <h2 class="text-sm font-bold mb-2">返回示例</h2>
            <pre class="detail__pre font-mono"><?php echo vs_e($api['response']); ?></pre>
        </div>
        <?php endif; ?>

        <?php if (!empty($api['doc'])): ?>
        <div class="mb-4">
            <h2 class="text-sm font-bold mb-2">接口文档</h2>
            <div class="detail__doc" style="line-height: 1.8; color: var(--text-muted); white-space: pre-wrap;"><?php echo vs_e($api['doc']); ?></div>
        </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
            <?php if (!empty($api['endpoint']) && empty($api['maintenance'])): ?>
            <a href="<?php echo vs_e($api['endpoint']); ?>" class="btn-geek" target="_blank" rel="noopener noreferrer">打开接口</a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
