<?php
/**
 * 默认主题 · 开发者公开个人主页
 * 变量由 vs_frontend_page 注入；此处全部兜底，避免静态分析误报未定义变量
 *
 * @var array<string,mixed>|null $profile
 * @var bool $notFound
 * @var string $vsBase
 * @var string $wallpaper
 * @var string $pingUrl
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

if (!isset($profile) || !is_array($profile)) {
    $profile = null;
}
$notFound = !empty($notFound) || $profile === null;
$vsBase = isset($vsBase) ? (string) $vsBase : vs_site_base_path();
$wallpaper = isset($wallpaper) ? trim((string) $wallpaper) : '';
$pingUrl = isset($pingUrl) ? (string) $pingUrl : ($vsBase . '/core/ping.php');

$pfAvatar = '';
$pfUsername = '';
$pfLetter = 'U';
$pfBio = '';
$pfBioCustom = false;
$pfApiCount = 0;
$pfJoinLabel = '';
$apis = array();
$totalCalls = '0';
if (!$notFound && is_array($profile)) {
    $pfAvatar = isset($profile['avatar']) ? (string) $profile['avatar'] : '';
    $pfUsername = isset($profile['username']) ? (string) $profile['username'] : '';
    $pfLetter = isset($profile['letter']) ? (string) $profile['letter'] : 'U';
    $pfBio = isset($profile['bio']) ? (string) $profile['bio'] : '';
    $pfBioCustom = !empty($profile['bio_custom']);
    $pfApiCount = isset($profile['apicount']) ? (int) $profile['apicount'] : 0;
    $pfJoinLabel = isset($profile['join_label']) ? (string) $profile['join_label'] : '';
    $apis = isset($profile['apis']) && is_array($profile['apis']) ? $profile['apis'] : array();
    $totalCalls = isset($profile['calls_label']) ? (string) $profile['calls_label'] : '0';
}
?>
<main class="pt-14 profile-page" id="profilePage"
      data-ping-url="<?php echo vs_e($pingUrl); ?>"
      data-wallpaper="<?php echo vs_e($wallpaper); ?>">
    <div class="relative h-64 md:h-80 lg:h-96 w-full overflow-hidden profile-hero-bg">
        <?php if ($wallpaper !== ''): ?>
            <img id="bgImg1" src="<?php echo vs_e($wallpaper); ?>" alt=""
                 class="absolute inset-0 w-full h-full object-cover bg-fade opacity-100" loading="eager"
                 referrerpolicy="no-referrer" decoding="async">
            <img id="bgImg2" src="" alt=""
                 class="absolute inset-0 w-full h-full object-cover bg-fade opacity-0" loading="lazy"
                 referrerpolicy="no-referrer" decoding="async">
        <?php else: ?>
            <div class="absolute inset-0 profile-hero-fallback"></div>
        <?php endif; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
    </div>

    <div class="container mx-auto px-4 -mt-16 relative z-10">
        <?php if ($notFound): ?>
            <div class="upf-glass rounded-2xl shadow-lg p-8 mb-6 text-center">
                <h1 class="text-xl font-bold upf-text mb-2">用户不存在</h1>
                <p class="upf-text-muted text-sm mb-4">该用户不存在或暂无公开主页。</p>
                <a href="<?php echo vs_e($vsBase); ?>/contributors" class="upf-btn-outline px-4 py-2 rounded-full text-sm no-underline">返回贡献者</a>
            </div>
        <?php else: ?>
            <div class="upf-glass upf-glass--identity rounded-2xl shadow-lg mb-6">
                <div class="flex items-center gap-3">
                    <div class="relative flex-shrink-0 cursor-pointer" id="avatarBox">
                        <img src="<?php echo vs_e($pfAvatar); ?>" alt="<?php echo vs_e($pfUsername); ?>" id="avatarImg"
                             class="upf-avatar rounded-full avatar-ring object-cover"
                             referrerpolicy="no-referrer" decoding="async"
                             onerror="this.style.display='none';document.getElementById('avatarPh').style.display='flex';">
                        <div id="avatarPh" class="upf-avatar rounded-full avatar-ring flex items-center justify-center" style="display:none; background: rgba(17, 17, 17, 0.06);">
                            <span class="upf-avatar-letter font-bold" style="color: var(--accent-primary); font-family: 'JetBrains Mono', monospace;"><?php echo vs_e($pfLetter); ?></span>
                        </div>
                        <div class="upf-avatar-online bg-green-500 rounded-full" style="border-color: var(--bg-deep);"></div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h1 class="upf-identity-name font-bold truncate upf-text"><?php echo vs_e($pfUsername); ?></h1>
                        <p class="upf-text-muted upf-identity-bio line-clamp-2" id="userBio"<?php echo $pfBioCustom ? '' : ' data-vs-hitokoto="1"'; ?>><?php
                            echo $pfBioCustom ? vs_e($pfBio) : '';
                        ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 upf-identity-stats upf-text-muted">
                    <div class="flex items-center gap-1">
                        <span><span class="font-bold upf-accent font-mono"><?php echo (int) $pfApiCount; ?></span> 个接口</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span><span class="font-bold upf-accent font-mono"><?php echo vs_e($pfJoinLabel); ?></span> 加入</span>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <div class="flex items-center justify-between mb-3 px-1">
                    <h2 class="text-lg font-bold upf-text">发布的接口</h2>
                    <span class="text-sm upf-text-muted">总调用 <span class="font-bold upf-accent font-mono"><?php echo vs_e($totalCalls); ?></span> 次</span>
                </div>
                <div class="flex items-center gap-2 mb-4">
                    <div style="flex:1; position:relative;">
                        <input type="text" id="apiSearch" placeholder="搜索接口名称..." class="profile-search-input">
                    </div>
                    <div class="profile-sort-btns">
                        <button type="button" class="sort-btn active" data-sort="random" title="随机">随机</button>
                        <button type="button" class="sort-btn" data-sort="asc" title="正序">正序</button>
                        <button type="button" class="sort-btn" data-sort="desc" title="倒序">倒序</button>
                    </div>
                </div>
                <div id="apiList">
                    <?php if (count($apis) === 0): ?>
                        <p class="upf-text-muted text-sm text-center py-8">暂无公开接口</p>
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
                            $domain = isset($api['domain']) ? (string) $api['domain'] : '';
                            $detailUrl = isset($api['detail_url']) ? (string) $api['detail_url'] : '';
                            $points = isset($api['points']) ? (float) $api['points'] : 0;
                            $needkey = isset($api['needkey']) ? (int) $api['needkey'] : 0;
                            $billingLabel = !empty($api['billing_label'])
                                ? (string) $api['billing_label']
                                : ($points > 0 ? '收费' : '免费');
                            $isPaid = $points > 0
                                || (isset($api['charge']) && (int) $api['charge'] === 1);
                            ?>
                            <a href="<?php echo vs_e($detailUrl); ?>"
                               class="api-card-stack block upf-api-card-bg rounded-2xl p-5 mb-4 transition-all no-underline"
                               style="color:inherit;"
                               data-api-url="<?php echo vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>"
                               data-name="<?php echo vs_e(isset($api['name']) ? $api['name'] : ''); ?>"
                               data-id="<?php echo (int) (isset($api['id']) ? $api['id'] : 0); ?>"
                               data-domain="<?php echo vs_e($domain); ?>"
                               data-calls="<?php echo (int) (isset($api['calls']) ? $api['calls'] : 0); ?>">
                                <div class="flex items-center justify-between mb-2 gap-2">
                                    <div class="profile-api-tags">
                                        <?php foreach ($showMethods as $m): ?>
                                            <span class="method-badge <?php echo vs_e(strtolower($m)); ?>"><?php echo vs_e($m); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($methodExtra > 0): ?>
                                            <span class="api-item-more">+<?php echo (int) $methodExtra; ?></span>
                                        <?php endif; ?>
                                        <?php if ($isPaid): ?>
                                            <span class="api-chip api-chip--points"><?php echo vs_e($billingLabel); ?></span>
                                        <?php else: ?>
                                            <span class="api-chip api-chip--free">免费</span>
                                        <?php endif; ?>
                                        <?php if ($needkey === 1): ?>
                                            <span class="api-chip api-chip--key">KEY必填</span>
                                        <?php elseif ($needkey === 2): ?>
                                            <span class="api-chip api-chip--key">KEY可选</span>
                                        <?php endif; ?>
                                        <?php if (!empty($api['maintenance'])): ?>
                                            <span class="api-chip api-chip--maintenance">维护中</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs font-mono upf-text-muted flex-shrink-0" style="opacity:0.5;">#<?php echo (int) (isset($api['id']) ? $api['id'] : 0); ?></span>
                                </div>
                                <h3 class="text-base font-semibold upf-accent mb-1"><?php echo vs_e($api['name']); ?></h3>
                                <p class="upf-text-muted text-sm mb-3 line-clamp-2"><?php echo vs_e(isset($api['desc']) ? $api['desc'] : ''); ?></p>
                                <div class="flex justify-between items-center text-xs upf-text-muted">
                                    <span class="font-mono truncate max-w-[60%]"><?php echo vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?></span>
                                    <span class="api-latency flex items-center gap-1"><svg class="spin-icon" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> 检测中</span>
                                </div>
                                <div class="flex justify-between items-center text-xs upf-text-muted mt-2 pt-2" style="border-top: 1px solid var(--border-color);">
                                    <span>调用 <strong class="upf-accent font-mono"><?php echo number_format((int) (isset($api['calls']) ? $api['calls'] : 0)); ?></strong> 次</span>
                                    <span class="api-latency-result"></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
