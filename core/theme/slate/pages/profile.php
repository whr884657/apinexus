<?php if (!defined('VS_THEME_RENDER')) { exit; }

$profileRaw = (isset($profile) && is_array($profile)) ? $profile : null;
$notFound = !empty($notFound) || $profileRaw === null;
/** @var array $profile 始终为数组，避免静态分析在分支内误判 null */
$profile = $profileRaw !== null ? $profileRaw : array(
    'avatar' => '',
    'letter' => '',
    'username' => '',
    'bio' => '',
    'bio_custom' => false,
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
$bioCustom = !empty($profile['bio_custom']);
$username = isset($profile['username']) ? (string) $profile['username'] : '';
$letter = isset($profile['letter']) ? (string) $profile['letter'] : 'U';
?>
<main class="st-main profile-st" id="profilePage" data-ping-url="<?php echo vs_e($pingUrl); ?>" data-wallpaper="<?php echo vs_e($wallpaper); ?>">
<div class="st-wrap st-profile-wrap">
    <?php if ($notFound): ?>
        <section class="st-section st-profile-empty">
            <h1 class="st-page-title">用户不存在</h1>
            <p class="st-page-desc">该用户不存在或暂无公开主页。</p>
            <a class="st-profile-back" href="<?php echo vs_e($vsBase); ?>/contributors">返回贡献者</a>
        </section>
    <?php else: ?>
        <div class="st-profile-banner<?php echo $wallpaper !== '' ? ' st-profile-banner--photo' : ''; ?>">
            <?php if ($wallpaper !== ''): ?>
                <img class="st-profile-banner__img" src="<?php echo vs_e($wallpaper); ?>" alt="" width="1200" height="240" loading="eager" decoding="async" referrerpolicy="no-referrer">
            <?php endif; ?>
            <div class="st-profile-banner__fade" aria-hidden="true"></div>
        </div>

        <section class="st-profile-identity">
            <div class="st-profile-identity__row">
                <div class="st-profile-avatar-wrap">
                    <img class="st-profile-avatar" src="<?php echo vs_e(isset($profile['avatar']) ? $profile['avatar'] : ''); ?>" alt=""
                         loading="eager" decoding="async" referrerpolicy="no-referrer"
                         onerror="this.classList.add('st-is-hidden');this.nextElementSibling.classList.remove('st-is-hidden');">
                    <div class="st-profile-avatar st-profile-avatar--fallback st-is-hidden"><?php echo vs_e($letter); ?></div>
                </div>
                <div class="st-profile-identity__meta">
                    <h1 class="st-profile-name"><?php echo vs_e($username); ?></h1>
                    <p class="st-profile-bio"<?php echo $bioCustom ? '' : ' data-vs-hitokoto="1"'; ?>><?php
                        echo $bioCustom ? vs_e(isset($profile['bio']) ? $profile['bio'] : '') : '';
                    ?></p>
                    <div class="st-profile-stats" aria-label="贡献数据">
                        <div class="st-profile-stat">
                            <strong><?php echo (int) (isset($profile['apicount']) ? $profile['apicount'] : 0); ?></strong>
                            <span>接口</span>
                        </div>
                        <div class="st-profile-stat">
                            <strong><?php echo vs_e(isset($profile['join_label']) ? $profile['join_label'] : ''); ?></strong>
                            <span>加入</span>
                        </div>
                        <div class="st-profile-stat">
                            <strong><?php echo vs_e(isset($profile['calls_label']) ? $profile['calls_label'] : '0'); ?></strong>
                            <span>总调用</span>
                        </div>
                    </div>
                    <?php if (!empty($profile['blog'])): ?>
                    <a class="st-profile-blog" href="<?php echo vs_e($profile['blog']); ?>" target="_blank" rel="noopener noreferrer">访问博客</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="st-section st-profile-apis">
            <div class="st-profile-apis__head">
                <h2 class="st-profile-apis__title">发布的接口</h2>
                <span class="st-profile-apis__count"><?php echo count($apis); ?> 个</span>
            </div>
            <div class="st-profile-tools">
                <div class="st-search st-search--profile">
                    <span class="st-search__icon" aria-hidden="true">⌕</span>
                    <input type="search" id="apiSearch" class="st-search__input" placeholder="搜索接口名称..." autocomplete="off">
                </div>
                <div class="profile-sort-btns" role="group" aria-label="排序">
                    <button type="button" class="sort-btn active" data-sort="random">随机</button>
                    <button type="button" class="sort-btn" data-sort="asc">正序</button>
                    <button type="button" class="sort-btn" data-sort="desc">倒序</button>
                </div>
            </div>
            <div id="apiList" class="st-profile-api-list">
                <?php if (count($apis) === 0): ?>
                    <p class="st-profile-apis__empty">暂无公开接口</p>
                <?php else: ?>
                    <?php foreach ($apis as $api): ?>
                        <?php
                        if (!is_array($api)) {
                            continue;
                        }
                        $methods = isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET');
                        $showMethods = array();
                        foreach ($methods as $mRaw) {
                            $mUp = strtoupper(trim((string) $mRaw));
                            if ($mUp !== '') {
                                $showMethods[] = $mUp;
                            }
                        }
                        if ($showMethods === array()) {
                            $showMethods = array('GET');
                        }
                        $methodExtra = count($showMethods) > 2 ? count($showMethods) - 2 : 0;
                        $showMethods = array_slice($showMethods, 0, 2);
                        $points = isset($api['points']) ? (float) $api['points'] : 0;
                        $billingLabel = !empty($api['billing_label'])
                            ? (string) $api['billing_label']
                            : ($points > 0 ? '收费' : '免费');
                        $isPaid = $points > 0 || (isset($api['charge']) && (int) $api['charge'] === 1);
                        ?>
                        <a class="st-profile-api api-card-stack" href="<?php echo vs_e(isset($api['detail_url']) ? $api['detail_url'] : '#'); ?>"
                           data-name="<?php echo vs_e(isset($api['name']) ? $api['name'] : ''); ?>"
                           data-domain="<?php echo vs_e(isset($api['domain']) ? $api['domain'] : ''); ?>"
                           data-calls="<?php echo (int) (isset($api['calls']) ? $api['calls'] : 0); ?>">
                            <div class="st-profile-api__top">
                                <div class="st-profile-api__methods">
                                    <?php foreach ($showMethods as $m): ?>
                                    <span class="st-api-card__method st-api-card__method--<?php echo vs_e(strtolower($m)); ?>"><?php echo vs_e($m); ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($methodExtra > 0): ?>
                                    <span class="st-api-card__method-more">+<?php echo (int) $methodExtra; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="st-api-chip <?php echo $isPaid ? 'st-api-chip--points' : 'st-api-chip--free'; ?>"><?php echo vs_e($billingLabel); ?></span>
                            </div>
                            <div class="st-profile-api__name"><?php echo vs_e(isset($api['name']) ? $api['name'] : ''); ?></div>
                            <div class="st-profile-api__meta">
                                <span>调用 <?php echo number_format((int) (isset($api['calls']) ? $api['calls'] : 0)); ?></span>
                                <span class="api-latency-result">检测中…</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
</main>
<?php
$hitokotoSrc = ThemeManager::assetUrl('default', 'assets/js/pages/hitokoto-bio.js');
if ($hitokotoSrc !== ''):
?>
<script src="<?php echo vs_e($hitokotoSrc); ?>?v=<?php echo vs_e(VS_VERSION); ?>" defer></script>
<?php endif; ?>
