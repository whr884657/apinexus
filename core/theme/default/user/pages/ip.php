<?php
/**
 * 默认主题 · 用户 IP 配置页
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$ready = !empty($ready);
$list = isset($list) && is_array($list) ? $list : array();
$clientIp = isset($clientIp) ? (string) $clientIp : '';
$maxCount = isset($maxCount) ? (int) $maxCount : UserIpAllow::MAX_COUNT;
$listCount = count($list);
?>

<?php if (!$ready): ?>
    <?php vs_render_notice('warning', '', 'IP 配置尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-user-ip" id="userIpPage"
     data-max="<?php echo (int) $maxCount; ?>"
     data-client-ip="<?php echo vs_e($clientIp); ?>">

    <div class="vs-tabs vs-api-review-tabs" id="userIpTabs" role="tablist" aria-label="IP 配置">
        <button type="button" class="vs-tabs__btn is-active" data-ip-tab="allow" role="tab" aria-selected="true">IP 白名单</button>
        <button type="button" class="vs-tabs__btn" data-ip-tab="proxy" role="tab" aria-selected="false">IP 代理</button>
    </div>

    <div class="vs-user-ip__panel" id="userIpPanelAllow" data-ip-panel="allow" role="tabpanel">
        <?php
        vs_render_notice(
            'info',
            '',
            '仅对「密钥 = 必须」的接口生效（含收费强制必须密钥）。无需 / 可选密钥的接口允许游客访问，不做 IP 拦截。白名单为空表示不限制；填写后，调用必须密钥接口时仅列表内 IP 可通过。最多 '
                . (int) $maxCount . ' 条。识别优先使用连接方真实地址；仅当站点开启信任反向代理时才采信转发头。',
            array('compact' => true)
        );
        ?>

        <div class="vs-panel vs-user-ip__card">
            <div class="vs-user-ip__meta">
                <span class="vs-user-ip__meta-label">当前访问 IP</span>
                <code class="vs-user-ip__meta-ip" id="userIpClientIp"><?php echo vs_e($clientIp !== '' ? $clientIp : '—'); ?></code>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="userIpAddCurrent"<?php echo $clientIp === '' ? ' disabled' : ''; ?>>加入白名单</button>
            </div>

            <form class="vs-user-ip__add vs-form" id="userIpAddForm" autocomplete="off" novalidate>
                <label class="vs-label" for="userIpInput">添加 IP</label>
                <div class="vs-user-ip__add-row">
                    <input type="text" class="vs-input" id="userIpInput" name="ip" maxlength="64"
                           placeholder="例如 203.0.113.10 或 IPv6" required>
                    <button type="submit" class="vs-btn vs-btn--primary" id="userIpAddBtn">添加</button>
                </div>
                <p class="vs-form-hint">支持 IPv4 / IPv6；逗号批量请逐条添加。空名单 = 不限制。</p>
            </form>
        </div>

        <div class="vs-panel vs-user-ip__list-wrap">
            <div class="vs-user-ip__list-head">
                <h3 class="vs-user-ip__list-title">白名单</h3>
                <span class="vs-user-ip__list-count" id="userIpCount"><?php echo (int) $listCount; ?> / <?php echo (int) $maxCount; ?></span>
            </div>

            <div class="vs-api-list-empty vs-api-list-empty--hero" id="userIpEmpty"<?php echo $listCount > 0 ? ' hidden' : ''; ?>>
                <div class="vs-api-list-empty__card">
                    <h3 class="vs-api-list-empty__title">暂无限制</h3>
                    <p class="vs-api-list-empty__desc">未配置白名单时，任意来源 IP 均可使用您的密钥调用（仍须密钥有效）。</p>
                </div>
            </div>

            <ul class="vs-user-ip__list" id="userIpList"<?php echo $listCount === 0 ? ' hidden' : ''; ?>>
                <?php foreach ($list as $ipItem): ?>
                    <li class="vs-user-ip__item" data-ip="<?php echo vs_e($ipItem); ?>">
                        <code class="vs-user-ip__item-ip"><?php echo vs_e($ipItem); ?></code>
                        <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-btn--sm vs-user-ip__remove" data-ip="<?php echo vs_e($ipItem); ?>">移除</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="vs-user-ip__panel" id="userIpPanelProxy" data-ip-panel="proxy" role="tabpanel" hidden>
        <div class="vs-api-list-empty vs-api-list-empty--hero">
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">IP 代理</h3>
                <p class="vs-api-list-empty__desc">功能筹备中，敬请期待。</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
