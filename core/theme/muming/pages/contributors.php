<?php
/**
 * 主题 five · 贡献者（首页视觉语言自研）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$authUrl = isset($authUrl) ? (string) $authUrl : ($vsBase . '/user/login');
$contributors = class_exists('FrontendContributor') ? FrontendContributor::listForTheme() : array();
if (!is_array($contributors)) {
    $contributors = array();
}
?>
<section class="th5-page">
  <div class="th5-page__inner max-w-7xl mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 贡献者</div>
    <h1 class="font-display font-bold tracking-tight th5-page__title">贡献者</h1>
    <p class="th5-page__lead text-fg-2">感谢每一位发布接口的开发者 · 共 <?php echo count($contributors); ?> 位</p>

    <?php if (count($contributors) === 0): ?>
    <div class="th5-panel card mt-8">暂无贡献者。</div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8">
      <?php foreach ($contributors as $c): ?>
        <a class="th5-contrib-card card" href="<?php echo vs_e($c['profile_url']); ?>">
          <?php if (!empty($c['avatar'])): ?>
            <img class="th5-contrib-card__avatar" src="<?php echo vs_e($c['avatar']); ?>" alt="" width="48" height="48" loading="lazy">
          <?php else: ?>
            <span class="th5-contrib-card__avatar th5-contrib-card__avatar--text"><?php echo vs_e($c['letter']); ?></span>
          <?php endif; ?>
          <div class="th5-contrib-card__main">
            <strong><?php echo vs_e($c['username']); ?></strong>
            <p class="th5-contrib-card__bio"><?php echo !empty($c['bio_custom']) ? vs_e($c['bio']) : ''; ?></p>
            <div class="th5-contrib-card__stats">
              <span><b><?php echo (int) $c['apicount']; ?></b> 接口</span>
              <span><b><?php echo vs_e($c['calls_label']); ?></b> 调用</span>
              <span><b><?php echo vs_e($c['join_label']); ?></b> 加入</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th5-cta-band card mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">想加入贡献者？</h2>
        <p class="text-fg-2 text-sm m-0">注册开发者并发布接口后，即可出现在本页。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($authUrl); ?>">立即登录</a>
    </div>
  </div>
</section>
