<?php
/**
 * 主题 three · 友情链接（首页视觉语言自研）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$pagePack = class_exists('FrontendLink')
    ? FrontendLink::listForThemePage()
    : array('items' => array(), 'total' => 0, 'truncated' => false, 'limit' => 120);
$friendLinks = isset($pagePack['items']) && is_array($pagePack['items']) ? $pagePack['items'] : array();
$linksTotal = isset($pagePack['total']) ? (int) $pagePack['total'] : count($friendLinks);
$linksTruncated = !empty($pagePack['truncated']);
$applyUrl = $vsBase . '/applylink';
?>
<section class="th3-page">
  <div class="th3-page__inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
      <div>
        <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 友情链接</div>
        <h1 class="font-display font-bold tracking-tight th3-page__title">友情链接</h1>
        <p class="th3-page__lead text-fg-2">与优质站点互相推荐，共同成长</p>
      </div>
      <a class="btn-primary justify-center" href="<?php echo vs_e($applyUrl); ?>">申请友链 <i data-lucide="arrow-up-right" style="width:14px;height:14px;"></i></a>
    </div>

    <?php if ($linksTruncated): ?>
    <p class="th3-notice text-sm text-fg-2 mb-6">当前共 <?php echo (int) $linksTotal; ?> 条，本页仅展示前 <?php echo (int) $pagePack['limit']; ?> 条。</p>
    <?php endif; ?>

    <?php if (count($friendLinks) === 0): ?>
    <div class="th3-panel card">
      <h2 class="font-display font-semibold text-lg m-0 mb-2">暂无友情链接</h2>
      <p class="text-fg-2 m-0 mb-4">欢迎交换友链。请先在贵站添加本站信息，再提交申请。</p>
      <a class="btn-ghost" href="<?php echo vs_e($applyUrl); ?>">申请友链</a>
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($friendLinks as $item): ?>
        <a class="th3-link-card card" href="<?php echo vs_e($item['siteurl']); ?>" target="_blank" rel="noopener noreferrer" data-friend-link="1">
          <?php if (!empty($item['icon'])): ?>
            <img class="th3-link-card__avatar" src="<?php echo vs_e($item['icon']); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
          <?php else: ?>
            <span class="th3-link-card__avatar th3-link-card__avatar--text"><?php
              echo vs_e(!empty($item['initial']) ? $item['initial'] : (function_exists('mb_substr') ? mb_substr($item['name'], 0, 1, 'UTF-8') : substr($item['name'], 0, 1)));
            ?></span>
          <?php endif; ?>
          <div class="th3-link-card__body">
            <strong><?php echo vs_e($item['name']); ?></strong>
            <?php if (!empty($item['description'])): ?>
            <p><?php echo vs_e($item['description']); ?></p>
            <?php endif; ?>
            <span class="font-mono text-xs text-muted"><?php echo vs_e(!empty($item['host']) ? $item['host'] : $item['siteurl']); ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th3-cta-band card mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">申请友链</h2>
        <p class="text-fg-2 text-sm m-0">请先在贵站添加本站信息，再提交申请。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($applyUrl); ?>">去申请</a>
    </div>
  </div>
</section>
