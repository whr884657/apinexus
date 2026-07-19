<?php if (!defined('VS_THEME_RENDER')) { exit; }

$api = isset($api) && is_array($api) ? $api : null;
$notFound = !empty($notFound) || $api === null;
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$methods = (!$notFound && isset($api['methods']) && is_array($api['methods'])) ? $api['methods'] : array('GET');
$primaryMethod = !$notFound && !empty($api['method']) ? (string) $api['method'] : (isset($methods[0]) ? (string) $methods[0] : 'GET');
$chargeLabel = !$notFound && !empty($api['charge_label']) ? (string) $api['charge_label'] : '免费';
$points = !$notFound && isset($api['points']) ? (float) $api['points'] : 0;
if (!empty($api['charge']) && $points > 0) {
    $chargeLabel = rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.') . ' 积分';
}
$callsLabel = !$notFound ? number_format((int) (isset($api['calls']) ? $api['calls'] : 0)) : '0';
?>
<main class="main-wrapper container mx-auto px-4 detail-page" id="apiDetailPage"
      data-endpoint="<?php echo $notFound ? '' : vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>">
    <nav class="detail-crumb text-sm mb-4" aria-label="面包屑">
        <a href="<?php echo vs_e($vsBase); ?>/">首页</a>
        <span class="detail-crumb__sep">/</span>
        <a href="<?php echo vs_e($vsBase); ?>/apis">全部接口</a>
        <span class="detail-crumb__sep">/</span>
        <span><?php echo $notFound ? '未找到' : vs_e($api['name']); ?></span>
    </nav>

    <?php if ($notFound): ?>
    <section class="detail-card detail-card--empty">
        <h1 class="detail-section-title">接口不存在</h1>
        <p class="detail-lead">该接口不存在、未通过审核或已下架，请从全部接口列表重新选择。</p>
        <div class="detail-actions">
            <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
        </div>
    </section>
    <?php else: ?>

    <header class="detail-header">
        <div class="detail-header__top">
            <div class="detail-meta">
                <?php foreach ($methods as $m): ?>
                    <span class="method-badge <?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
                <?php endforeach; ?>
                <?php if (!empty($api['maintenance'])): ?>
                    <span class="api-chip api-chip--maintenance">维护中</span>
                <?php else: ?>
                    <span class="api-chip"><?php echo vs_e($chargeLabel); ?></span>
                <?php endif; ?>
                <?php if (!empty($api['needkey_label'])): ?>
                    <span class="api-chip api-chip--key">密钥：<?php echo vs_e($api['needkey_label']); ?></span>
                <?php endif; ?>
                <?php if (!empty($api['category_name'])): ?>
                    <span class="api-chip"><?php echo vs_e($api['category_name']); ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-copy" id="detailCopyAllBtn" title="复制本页关键信息">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                复制全部
            </button>
        </div>
        <h1 class="detail-title"><?php echo vs_e($api['name']); ?></h1>
        <?php if (!empty($api['desc'])): ?>
        <p class="detail-desc"><?php echo vs_e($api['desc']); ?></p>
        <?php endif; ?>
    </header>

    <section class="detail-card">
        <h2 class="detail-section-title">接口信息</h2>
        <?php if (!empty($api['endpoint'])): ?>
        <div class="endpoint-box">
            <div class="endpoint-box__text font-mono">
                <span class="endpoint-box__method"><?php echo vs_e(strtoupper($primaryMethod)); ?></span>
                <span id="detailEndpoint"><?php echo vs_e($api['endpoint']); ?></span>
            </div>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($api['endpoint']); ?>">复制</button>
        </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">请求方法</div>
                <div class="info-value info-value--method"><?php echo vs_e($api['method_label']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">所属分类</div>
                <div class="info-value"><?php echo vs_e(!empty($api['category_name']) ? $api['category_name'] : '未分类'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">调用次数</div>
                <div class="info-value info-value--calls"><?php echo vs_e($callsLabel); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">访问鉴权</div>
                <div class="info-value"><?php echo vs_e(!empty($api['needkey_label']) ? $api['needkey_label'] : '无需密钥'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">计费</div>
                <div class="info-value"><?php echo vs_e($chargeLabel); ?></div>
            </div>
            <?php if (!empty($api['createtime'])): ?>
            <div class="info-item">
                <div class="info-label">提交时间</div>
                <div class="info-value"><?php echo vs_e($api['createtime']); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label">接口 ID</div>
                <div class="info-value font-mono">#<?php echo (int) $api['id']; ?></div>
            </div>
        </div>

        <?php if (!empty($api['maintenance'])): ?>
        <div class="detail-notice detail-notice--warn">当前接口维护中，暂时无法调用。</div>
        <?php endif; ?>
    </section>

    <?php if (!empty($api['params'])): ?>
    <section class="detail-card">
        <div class="detail-section-title">
            <span>请求参数</span>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($api['params']); ?>">复制</button>
        </div>
        <div class="code-block">
            <pre class="code-content font-mono"><?php echo vs_e($api['params']); ?></pre>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['response'])): ?>
    <section class="detail-card">
        <div class="detail-section-title">
            <span>返回示例</span>
            <button type="button" class="btn-copy" data-copy="<?php echo vs_e($api['response']); ?>">复制</button>
        </div>
        <div class="code-block">
            <pre class="code-content font-mono"><?php echo vs_e($api['response']); ?></pre>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['doc'])): ?>
    <section class="detail-card">
        <h2 class="detail-section-title">详细文档</h2>
        <div class="markdown-body detail-md" data-detail-md><?php echo vs_e($api['doc']); ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($api['aidoc'])): ?>
    <section class="detail-card">
        <h2 class="detail-section-title">AI 文档</h2>
        <div class="markdown-body detail-md" data-detail-md><?php echo vs_e($api['aidoc']); ?></div>
    </section>
    <?php endif; ?>

    <div class="detail-actions">
        <a href="<?php echo vs_e($vsBase); ?>/apis" class="btn-geek">返回全部接口</a>
        <?php if (!empty($api['endpoint']) && empty($api['maintenance'])): ?>
        <a href="<?php echo vs_e($api['endpoint']); ?>" class="btn-geek" target="_blank" rel="noopener noreferrer">打开接口</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<div class="copy-toast" id="detailCopyToast" hidden>已复制</div>
