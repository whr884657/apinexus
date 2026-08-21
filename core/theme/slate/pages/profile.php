<?php if (!defined('VS_THEME_RENDER')) { exit; }

$profileRaw = (isset($profile) && is_array($profile)) ? $profile : null;
$notFound = !empty($notFound) || $profileRaw === null;
/** @var array $profile 始终为数组，避免静态分析在分支内误判 null */
$profile = $profileRaw !== null ? $profileRaw : array(
    'avatar' => '',
    'letter' => '',
    'username' => '',
    'bio' => '',
    'apicount' => 0,
    'join_label' => '',
    'calls_label' => '',
    'blog' => '',
    'apis' => array(),
);
$vsBase = isset($vsBase) ? $vsBase : vs_site_base_path();
$wallpaper = isset($wallpaper) ? trim((string) $wallpaper) : '';
$pingUrl = isset($pingUrl) ? (string) $pingUrl : ($vsBase . '/core/ping.php');
$apis = (!$notFound && isset($profile['apis']) && is_array($profile['apis'])) ? $profile['apis'] : array();
?>
<main class="st-main profile-st" id="profilePage" data-ping-url="<?php echo vs_e($pingUrl); ?>" data-wallpaper="<?php echo vs_e($wallpaper); ?>">
<div class="st-wrap">
    <?php if ($notFound): ?>
        <section class="st-section">
            <h1 class="st-page-title">用户不存在</h1>
            <p class="st-page-desc">该用户不存在或暂无公开主页。</p>
            <a class="st-btn" href="<?php echo vs_e($vsBase); ?>/contributors">返回贡献者</a>
        </section>
    <?php else: ?>
        <div class="st-profile-hero<?php echo $wallpaper !== '' ? ' st-profile-hero--photo' : ''; ?>">
            <?php if ($wallpaper !== ''): ?>
                <img class="st-profile-hero__img" src="<?php echo vs_e($wallpaper); ?>" alt="" width="1200" height="240" loading="eager" decoding="async" referrerpolicy="no-referrer">
            <?php endif; ?>
        </div>
        <section class="st-section st-profile-card">
            <div class="st-profile-head">
                <img class="st-profile-avatar" src="<?php echo vs_e(isset($profile['avatar']) ? $profile['avatar'] : ''); ?>" alt=""
                     loading="eager" decoding="async" referrerpolicy="no-referrer"
                     onerror="this.classList.add('st-is-hidden');this.nextElementSibling.classList.remove('st-is-hidden');">
                <div class="st-contrib__avatar st-profile-avatar-fallback st-is-hidden"><?php echo vs_e(isset($profile['letter']) ? $profile['letter'] : ''); ?></div>
                <div class="st-profile-meta">
                    <h1 class="st-page-title st-profile-name"><?php echo vs_e(isset($profile['username']) ? $profile['username'] : ''); ?></h1>
                    <p class="st-page-desc st-profile-bio"><?php echo vs_e(isset($profile['bio']) ? $profile['bio'] : ''); ?></p>
                    <div class="st-contrib__stats st-profile-stats">
                        <span><strong><?php echo (int) (isset($profile['apicount']) ? $profile['apicount'] : 0); ?></strong> 接口</span>
                        <span><strong><?php echo vs_e(isset($profile['join_label']) ? $profile['join_label'] : ''); ?></strong> 加入</span>
                        <span>总调用 <strong><?php echo vs_e(isset($profile['calls_label']) ? $profile['calls_label'] : ''); ?></strong></span>
                    </div>
                    <div class="st-profile-actions">
                        <?php if (!empty($profile['blog'])): ?>
                            <a class="st-btn" href="<?php echo vs_e($profile['blog']); ?>" target="_blank" rel="noopener noreferrer">博客</a>
                        <?php endif; ?>
                        <a class="st-btn st-btn--ghost" href="<?php echo vs_e($vsBase); ?>/contributors">贡献者</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="st-section">
            <div class="st-profile-tools">
                <input type="search" id="apiSearch" class="st-input" placeholder="搜索接口名称...">
                <div class="profile-sort-btns">
                    <button type="button" class="sort-btn active" data-sort="random">随机</button>
                    <button type="button" class="sort-btn" data-sort="asc">正序</button>
                    <button type="button" class="sort-btn" data-sort="desc">倒序</button>
                </div>
            </div>
            <div id="apiList" class="st-api-grid">
                <?php if (count($apis) === 0): ?>
                    <p class="st-page-desc">暂无公开接口</p>
                <?php else: ?>
                    <?php foreach ($apis as $api): ?>
                        <a class="st-card api-card-stack" href="<?php echo vs_e($api['detail_url']); ?>"
                           data-name="<?php echo vs_e($api['name']); ?>"
                           data-domain="<?php echo vs_e(isset($api['domain']) ? $api['domain'] : ''); ?>"
                           data-calls="<?php echo (int) $api['calls']; ?>">
                            <div class="st-card__title"><?php echo vs_e($api['name']); ?></div>
                            <div class="st-card__meta"><?php echo vs_e(isset($api['billing_label']) ? $api['billing_label'] : '免费'); ?> · 调用 <?php echo number_format((int) $api['calls']); ?></div>
                            <div class="st-card__meta api-latency-result">检测中…</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
</main>
