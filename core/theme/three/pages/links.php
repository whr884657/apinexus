<?php
/**
 * 主题 three · 友情链接（自研 th3）
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
  <div class="th3-page__inner th3-page__inner--links mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 友情链接</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">友情链接</h1>
    <p class="th3-page__lead text-fg-2">与优质站点互相推荐，共同成长</p>

    <?php if ($linksTruncated): ?>
    <p class="th3-notice text-sm text-fg-2 mt-6 mb-0">当前共 <?php echo (int) $linksTotal; ?> 条，本页仅展示前 <?php echo (int) $pagePack['limit']; ?> 条。</p>
    <?php endif; ?>

    <?php if (count($friendLinks) === 0): ?>
    <div class="th3-empty mt-8">
      <p class="m-0 mb-1 font-semibold">暂无友情链接</p>
      <p class="m-0 text-sm">欢迎交换友链。请先在贵站添加本站信息，再通过下方入口提交申请。</p>
    </div>
    <?php else: ?>
    <div class="th3-link-grid mt-8">
      <?php foreach ($friendLinks as $item): ?>
        <a class="th3-link-card card" href="<?php echo vs_e($item['siteurl']); ?>" target="_blank" rel="noopener noreferrer" data-friend-link="1">
          <?php if (!empty($item['icon'])): ?>
            <img class="th3-link-card__avatar" src="<?php echo vs_e($item['icon']); ?>" alt="" width="56" height="56" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ext-icon="1">
          <?php else: ?>
            <span class="th3-link-card__avatar th3-link-card__avatar--text"><?php
              echo vs_e(!empty($item['initial']) ? $item['initial'] : (function_exists('mb_substr') ? mb_substr($item['name'], 0, 1, 'UTF-8') : substr($item['name'], 0, 1)));
            ?></span>
          <?php endif; ?>
          <div class="th3-link-card__body">
            <strong class="th3-link-card__name"><?php echo vs_e($item['name']); ?></strong>
            <?php if (!empty($item['description'])): ?>
            <p class="th3-link-card__desc"><?php echo vs_e($item['description']); ?></p>
            <?php endif; ?>
            <span class="th3-link-card__url font-mono"><?php echo vs_e(!empty($item['host']) ? $item['host'] : $item['siteurl']); ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th3-link-apply mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">申请友链</h2>
        <p class="text-fg-2 text-sm m-0">请先在贵站添加本站信息，再提交申请。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($applyUrl); ?>">去申请</a>
    </div>
  </div>
</section>
