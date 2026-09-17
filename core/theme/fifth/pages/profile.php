<?php
/**
 * 主题 five · 贡献者主页（首页视觉语言自研）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$vsBase = isset($vsBase) ? rtrim((string) $vsBase, '/') : vs_site_base_path();
$profileRaw = (isset($profile) && is_array($profile)) ? $profile : null;
$notFound = !empty($notFound) || $profileRaw === null;
/** @var array $profile 始终为数组，避免 IDE 在分支内误判 null */
$profile = $profileRaw !== null ? $profileRaw : array();
$pingUrl = isset($pingUrl) ? (string) $pingUrl : ($vsBase . '/core/ping.php');
$apis = (!$notFound && isset($profile['apis']) && is_array($profile['apis'])) ? $profile['apis'] : array();
?>
<section class="th5-page" id="profilePage" data-ping-url="<?php echo vs_e($pingUrl); ?>">
  <div class="th5-page__inner max-w-5xl mx-auto px-4 sm:px-5 lg:px-8">
    <?php if ($notFound): ?>
      <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 主页</div>
      <h1 class="font-display font-bold th5-page__title">用户不存在</h1>
      <p class="th5-page__lead text-fg-2">该贡献者主页不存在或已隐藏。</p>
      <a class="btn-primary mt-6 inline-flex" href="<?php echo vs_e($vsBase); ?>/contributors">返回贡献者</a>
    <?php else: ?>
      <div class="text-xs font-mono uppercase tracking-widest text-muted mb-3">/ 主页</div>
      <div class="th5-profile-head card">
        <?php if (!empty($profile['avatar'])): ?>
          <img class="th5-profile-head__avatar" src="<?php echo vs_e($profile['avatar']); ?>" alt="" width="72" height="72">
        <?php else: ?>
          <span class="th5-profile-head__avatar th5-profile-head__avatar--text"><?php echo vs_e(isset($profile['letter']) ? $profile['letter'] : '?'); ?></span>
        <?php endif; ?>
        <div>
          <h1 class="font-display font-bold text-2xl m-0"><?php echo vs_e(isset($profile['username']) ? $profile['username'] : ''); ?></h1>
          <p class="text-fg-2 text-sm mt-2 mb-0"><?php
            echo !empty($profile['bio_custom']) ? vs_e(isset($profile['bio']) ? $profile['bio'] : '') : '这位贡献者暂未填写简介';
          ?></p>
          <div class="th5-contrib-card__stats mt-3">
            <span><b><?php echo (int) (isset($profile['apicount']) ? $profile['apicount'] : count($apis)); ?></b> 接口</span>
            <?php if (!empty($profile['calls_label'])): ?>
            <span><b><?php echo vs_e($profile['calls_label']); ?></b> 调用</span>
            <?php endif; ?>
            <?php if (!empty($profile['join_label'])): ?>
            <span><b><?php echo vs_e($profile['join_label']); ?></b> 加入</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-10 mb-4">
        <h2 class="font-display font-semibold text-xl m-0">发布的接口</h2>
        <input id="apiSearch" class="input th5-profile-search" type="search" placeholder="搜索接口…" autocomplete="off">
      </div>

      <div id="apiList" class="grid sm:grid-cols-2 gap-3">
        <?php if (count($apis) === 0): ?>
          <p class="text-muted" style="grid-column:1/-1;">暂无已上线接口</p>
        <?php else: ?>
          <?php foreach ($apis as $api): ?>
            <?php
              $points = isset($api['points']) ? (float) $api['points'] : 0;
              $billing = !empty($api['billing_label']) ? (string) $api['billing_label'] : ($points > 0 ? ($points . '积分/次') : '免费');
              $methods = isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET');
              $m0 = isset($methods[0]) ? strtoupper((string) $methods[0]) : 'GET';
              $detailUrl = !empty($api['detail_url']) ? (string) $api['detail_url'] : ($vsBase . '/detail/' . (int) (isset($api['id']) ? $api['id'] : 0));
              $initial = function_exists('mb_substr') ? mb_substr((string) (isset($api['name']) ? $api['name'] : '?'), 0, 1, 'UTF-8') : substr((string) (isset($api['name']) ? $api['name'] : '?'), 0, 1);
            ?>
            <a class="th5-profile-api card"
               href="<?php echo vs_e($detailUrl); ?>"
               data-name="<?php echo vs_e(isset($api['name']) ? $api['name'] : ''); ?>"
               data-domain="<?php echo vs_e(isset($api['domain']) ? $api['domain'] : ''); ?>"
               data-calls="<?php echo (int) (isset($api['calls']) ? $api['calls'] : 0); ?>">
              <div class="th5-profile-api__top">
                <span class="tag free"><?php echo vs_e($m0); ?></span>
                <span class="tag <?php echo $points > 0 ? 'points' : 'free'; ?>"><?php echo vs_e($billing); ?></span>
              </div>
              <div class="th5-profile-api__name"><?php echo vs_e(isset($api['name']) ? $api['name'] : $initial); ?></div>
              <div class="th5-profile-api__meta text-xs text-muted">
                调用 <?php echo number_format((int) (isset($api['calls']) ? $api['calls'] : 0)); ?>
                <span class="api-latency-result"> · 检测中…</span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script src="<?php echo vs_e(ThemeManager::assetUrl('fifth', 'assets/js/pages/profile.js')); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
