<?php
/**
 * 主题 three · 贡献者（版式自研 th3；数据契约 FrontendContributor）
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
<section class="th3-page">
  <div class="th3-page__inner th3-page__inner--contrib mx-auto px-4 sm:px-5 lg:px-8">
    <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 贡献者</div>
    <h1 class="font-display font-bold tracking-tight th3-page__title">贡献者</h1>
    <p class="th3-page__lead text-fg-2">感谢每一位发布接口的开发者 · 共 <?php echo count($contributors); ?> 位</p>

    <div class="th3-contrib-intro" role="note">
      <p class="th3-contrib-intro__text">下列开发者已公开分享接口。点击卡片可进入个人主页，查看其已发布的接口与调用数据。</p>
    </div>

    <?php if (count($contributors) === 0): ?>
    <div class="th3-empty mt-6">暂无公开贡献者，欢迎注册成为开发者并发布接口。</div>
    <?php else: ?>
    <div class="th3-contrib-grid mt-8">
      <?php foreach ($contributors as $c):
          $bioCustom = !empty($c['bio_custom']);
          $bioText = isset($c['bio']) ? (string) $c['bio'] : '';
      ?>
        <a class="th3-contrib-card card" href="<?php echo vs_e($c['profile_url']); ?>">
          <?php if (!empty($c['avatar'])): ?>
            <img class="th3-contrib-card__avatar" src="<?php echo vs_e($c['avatar']); ?>"
                 alt="<?php echo vs_e($c['letter']); ?>" width="80" height="80" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                 data-ext-icon="1">
          <?php else: ?>
            <span class="th3-contrib-card__avatar th3-contrib-card__avatar--text"><?php echo vs_e($c['letter']); ?></span>
          <?php endif; ?>
          <strong class="th3-contrib-card__name"><?php echo vs_e($c['username']); ?></strong>
          <p class="th3-contrib-card__bio"<?php echo $bioCustom ? '' : ' data-vs-hitokoto="1"'; ?>><?php
              echo $bioCustom ? vs_e($bioText) : '';
          ?></p>
          <div class="th3-contrib-card__stats">
            <div class="th3-contrib-stat">
              <div class="th3-contrib-stat__value"><?php echo (int) $c['apicount']; ?></div>
              <div class="th3-contrib-stat__label">接口数</div>
            </div>
            <div class="th3-contrib-stat">
              <div class="th3-contrib-stat__value"><?php echo vs_e($c['calls_label']); ?></div>
              <div class="th3-contrib-stat__label">调用次数</div>
            </div>
            <div class="th3-contrib-stat">
              <div class="th3-contrib-stat__value"><?php echo vs_e($c['join_label']); ?></div>
              <div class="th3-contrib-stat__label">加入时间</div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th3-cta-band card mt-10">
      <div>
        <h2 class="font-display font-semibold text-lg m-0 mb-1">想加入贡献者？</h2>
        <p class="text-fg-2 text-sm m-0">注册开发者并发布接口后，即可出现在本页。</p>
      </div>
      <a class="btn-primary" href="<?php echo vs_e($authUrl); ?>">立即注册</a>
    </div>
  </div>
</section>
<?php
$hitokotoSrc = ThemeManager::assetUrl('three', 'assets/js/pages/hitokoto-bio.js');
if ($hitokotoSrc !== ''):
?>
<script src="<?php echo vs_e($hitokotoSrc); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
