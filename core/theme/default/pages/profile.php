<?php if (!defined('VS_THEME_RENDER')) { exit; }

$notFound = !empty($notFound) || empty($profile) || !is_array($profile);
$vsBase = isset($vsBase) ? $vsBase : rtrim(vs_base_url(), '/');
$wallpaper = isset($wallpaper) ? trim((string) $wallpaper) : '';
$pingUrl = isset($pingUrl) ? (string) $pingUrl : ($vsBase . '/core/ping.php');
$apis = (!$notFound && isset($profile['apis']) && is_array($profile['apis'])) ? $profile['apis'] : array();
$totalCalls = !$notFound ? (isset($profile['calls_label']) ? $profile['calls_label'] : '0') : '0';
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
            <div class="upf-glass rounded-2xl shadow-lg p-5 mb-6">
                <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0 cursor-pointer" id="avatarBox">
                        <img src="<?php echo vs_e($profile['avatar']); ?>" alt="<?php echo vs_e($profile['username']); ?>" id="avatarImg"
                             class="w-20 h-20 md:w-28 md:h-28 rounded-full avatar-ring object-cover"
                             referrerpolicy="no-referrer" decoding="async"
                             onerror="this.style.display='none';document.getElementById('avatarPh').style.display='flex';">
                        <div id="avatarPh" class="hidden w-20 h-20 md:w-28 md:h-28 rounded-full avatar-ring flex items-center justify-center" style="background: rgba(0, 255, 157, 0.08);">
                            <span class="text-3xl md:text-4xl font-bold" style="color: var(--accent-primary); font-family: 'JetBrains Mono', monospace;"><?php echo vs_e($profile['letter']); ?></span>
                        </div>
                        <div class="absolute bottom-1 right-1 w-4 h-4 bg-green-500 rounded-full border-2" style="border-color: var(--bg-deep);"></div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl md:text-2xl font-bold mb-1 truncate upf-text"><?php echo vs_e($profile['username']); ?></h1>
                        <p class="upf-text-muted text-sm line-clamp-2" id="userBio"><?php echo vs_e($profile['bio']); ?></p>
                    </div>

                    <div class="flex-shrink-0 flex flex-col gap-2">
                        <?php if (!empty($profile['blog'])): ?>
                        <a href="<?php echo vs_e($profile['blog']); ?>" target="_blank" rel="noopener noreferrer"
                           class="upf-btn-primary px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex items-center gap-1 no-underline">
                            博客
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo vs_e($vsBase); ?>/contributors"
                           class="upf-btn-outline px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex items-center gap-1 no-underline">
                            贡献者
                        </a>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm upf-text-muted mt-4 pt-4" style="border-top: 1px solid var(--border-color);">
                    <div class="flex items-center gap-1">
                        <span><span class="font-bold upf-accent font-mono"><?php echo (int) $profile['apicount']; ?></span> 个接口</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span><span class="font-bold upf-accent font-mono"><?php echo vs_e($profile['join_label']); ?></span> 加入</span>
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
                            $methods = isset($api['methods']) && is_array($api['methods']) ? $api['methods'] : array('GET');
                            $domain = isset($api['domain']) ? (string) $api['domain'] : '';
                            $detailUrl = isset($api['detail_url']) ? (string) $api['detail_url'] : '';
                            ?>
                            <a href="<?php echo vs_e($detailUrl); ?>"
                               class="api-card-stack block upf-api-card-bg rounded-2xl p-5 mb-4 transition-all no-underline"
                               style="color:inherit;"
                               data-api-url="<?php echo vs_e(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>"
                               data-name="<?php echo vs_e($api['name']); ?>"
                               data-id="<?php echo (int) $api['id']; ?>"
                               data-domain="<?php echo vs_e($domain); ?>"
                               data-calls="<?php echo (int) (isset($api['calls']) ? $api['calls'] : 0); ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <?php foreach ($methods as $m): ?>
                                            <span class="text-xs px-2 py-0.5 rounded font-mono font-semibold tag-<?php echo vs_e(strtolower(trim((string) $m))); ?>"><?php echo vs_e(strtoupper(trim((string) $m))); ?></span>
                                        <?php endforeach; ?>
                                        <span class="text-xs px-2 py-0.5 rounded <?php echo !empty($api['charge']) ? 'tag-points' : 'tag-free'; ?> font-semibold"><?php echo vs_e(isset($api['billing_label']) ? $api['billing_label'] : '免费'); ?></span>
                                        <?php if (!empty($api['maintenance'])): ?>
                                            <span class="text-xs px-2 py-0.5 rounded" style="background:rgba(245,158,11,0.15);color:#f59e0b;">维护中</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs font-mono upf-text-muted flex-shrink-0" style="opacity:0.5;">#<?php echo (int) $api['id']; ?></span>
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
