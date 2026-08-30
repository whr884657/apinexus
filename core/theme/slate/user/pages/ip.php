<?php
/**
 * 石板主题 · 用户 IP 配置页（白名单 + 出口代理）
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$ready = !empty($ready);
$proxyReady = !empty($proxyReady);
$list = isset($list) && is_array($list) ? $list : array();
$clientIp = isset($clientIp) ? (string) $clientIp : '';
$maxCount = isset($maxCount) ? (int) $maxCount : UserIpAllow::MAX_COUNT;
$listCount = count($list);
$proxyList = isset($proxyList) && is_array($proxyList) ? $proxyList : array();
$proxyMax = isset($proxyMax) ? (int) $proxyMax : UserIpProxy::MAX_COUNT;
$proxyStrategy = isset($proxyStrategy) ? (int) $proxyStrategy : 0;
$proxyCount = count($proxyList);
$pageReady = $ready || $proxyReady;
?>

<?php if (!$pageReady): ?>
    <?php vs_render_notice('warning', '', 'IP 配置尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
<?php else: ?>
<div class="vs-user-ip" id="userIpPage"
     data-max="<?php echo (int) $maxCount; ?>"
     data-proxy-max="<?php echo (int) $proxyMax; ?>"
     data-proxy-ready="<?php echo $proxyReady ? '1' : '0'; ?>"
     data-client-ip="<?php echo vs_e($clientIp); ?>"
     data-strategy="<?php echo (int) $proxyStrategy; ?>">

    <div class="vs-tabs vs-api-review-tabs" id="userIpTabs" role="tablist" aria-label="IP 配置">
        <button type="button" class="vs-tabs__btn is-active" data-ip-tab="allow" role="tab" aria-selected="true">IP 白名单</button>
        <button type="button" class="vs-tabs__btn" data-ip-tab="proxy" role="tab" aria-selected="false">IP 代理</button>
    </div>

    <div class="vs-user-ip__panel" id="userIpPanelAllow" data-ip-panel="allow" role="tabpanel">
        <?php if (!$ready): ?>
            <?php vs_render_notice('warning', '', 'IP 白名单尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
        <?php else: ?>
        <?php
        vs_render_notice(
            'info',
            '',
            '仅对「密钥 = 必须」的接口生效（含收费强制必须密钥）。',
            array('compact' => true)
        );
        ?>

        <div class="vs-panel vs-user-ip__list-wrap">
            <div class="vs-user-ip__list-head">
                <div class="vs-user-ip__list-head-main">
                    <h3 class="vs-user-ip__list-title">白名单</h3>
                    <span class="vs-user-ip__list-count" id="userIpCount"><?php echo (int) $listCount; ?> / <?php echo (int) $maxCount; ?></span>
                </div>
                <button type="button" class="vs-btn vs-btn--primary vs-btn--sm" id="userIpOpenAdd">添加</button>
            </div>

            <div class="vs-user-ip__meta" id="userIpMeta">
                <span class="vs-user-ip__meta-label">当前访问 IP</span>
                <code class="vs-user-ip__meta-ip" id="userIpClientIp"><?php echo vs_e($clientIp !== '' ? $clientIp : '—'); ?></code>
            </div>

            <div class="vs-api-list-empty vs-api-list-empty--hero" id="userIpEmpty"<?php echo $listCount > 0 ? ' hidden' : ''; ?>>
                <div class="vs-api-list-empty__card">
                    <h3 class="vs-api-list-empty__title">暂无限制</h3>
                    <p class="vs-api-list-empty__desc">未配置白名单时不限制来源 IP。点击「添加」写入允许的地址。</p>
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
        <?php endif; ?>
    </div>

    <div class="vs-user-ip__panel" id="userIpPanelProxy" data-ip-panel="proxy" role="tabpanel" hidden>
        <?php if (!$proxyReady): ?>
            <?php vs_render_notice('warning', '', '出口代理尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
        <?php else: ?>
        <?php
        vs_render_notice(
            'info',
            '',
            '平台不提供免费代理节点。调用时附加 vsproxy=1（可选 vsproxyid）并携带有效密钥后生效。',
            array('compact' => true)
        );
        ?>

        <div class="vs-panel vs-user-ip__card">
            <div class="vs-user-ip__strategy">
                <label class="vs-label" for="userProxyStrategy">多条启用时的选用策略</label>
                <div class="vs-user-ip__strategy-row">
                    <select class="vs-input vs-select" id="userProxyStrategy" data-vs-pick>
                        <option value="0"<?php echo $proxyStrategy === 0 ? ' selected' : ''; ?>>轮询</option>
                        <option value="1"<?php echo $proxyStrategy === 1 ? ' selected' : ''; ?>>随机</option>
                        <option value="2"<?php echo $proxyStrategy === 2 ? ' selected' : ''; ?>>优先首条（按排序）</option>
                    </select>
                    <button type="button" class="vs-btn vs-btn--outline" id="userProxyStrategySave">保存策略</button>
                </div>
            </div>
        </div>

        <div class="vs-panel vs-user-ip__list-wrap">
            <div class="vs-user-ip__list-head">
                <div class="vs-user-ip__list-head-main">
                    <h3 class="vs-user-ip__list-title">出口代理</h3>
                    <span class="vs-user-ip__list-count" id="userProxyCount"><?php echo (int) $proxyCount; ?> / <?php echo (int) $proxyMax; ?></span>
                </div>
                <button type="button" class="vs-btn vs-btn--primary vs-btn--sm" id="userProxyOpenAdd">添加</button>
            </div>

            <div class="vs-api-list-empty vs-api-list-empty--hero" id="userProxyEmpty"<?php echo $proxyCount > 0 ? ' hidden' : ''; ?>>
                <div class="vs-api-list-empty__card">
                    <h3 class="vs-api-list-empty__title">尚未配置</h3>
                    <p class="vs-api-list-empty__desc">点击「添加」保存隧道或提取 API，每账号最多 <?php echo (int) $proxyMax; ?> 条。</p>
                </div>
            </div>

            <ul class="vs-user-ip__proxy-list" id="userProxyList"<?php echo $proxyCount === 0 ? ' hidden' : ''; ?>>
                <?php foreach ($proxyList as $p): ?>
                    <?php
                    $pid = isset($p['id']) ? (int) $p['id'] : 0;
                    $ptitle = isset($p['title']) ? (string) $p['title'] : '';
                    $pmode = isset($p['mode_label']) ? (string) $p['mode_label'] : '';
                    $pproto = isset($p['proto_label']) ? (string) $p['proto_label'] : '';
                    $phost = isset($p['host']) ? (string) $p['host'] : '';
                    $pport = isset($p['port']) ? (int) $p['port'] : 0;
                    $pextract = isset($p['extract']) ? (string) $p['extract'] : '';
                    $pstatus = isset($p['status']) ? (int) $p['status'] : 0;
                    $endpoint = ((int) (isset($p['mode']) ? $p['mode'] : 0) === 1)
                        ? $pextract
                        : ($phost !== '' ? ($phost . ':' . $pport) : '—');
                    ?>
                    <li class="vs-user-ip__proxy-item" data-id="<?php echo $pid; ?>">
                        <div class="vs-user-ip__proxy-main">
                            <div class="vs-user-ip__proxy-title-row">
                                <strong class="vs-user-ip__proxy-title"><?php echo vs_e($ptitle !== '' ? $ptitle : ('#' . $pid)); ?></strong>
                                <span class="vs-user-ip__proxy-badge"><?php echo vs_e($pmode); ?></span>
                                <span class="vs-user-ip__proxy-badge"><?php echo vs_e($pproto); ?></span>
                                <span class="vs-user-ip__proxy-badge <?php echo $pstatus === 1 ? 'is-on' : 'is-off'; ?>">
                                    <?php echo $pstatus === 1 ? '启用' : '禁用'; ?>
                                </span>
                            </div>
                            <code class="vs-user-ip__proxy-endpoint" title="<?php echo vs_e($endpoint); ?>"><?php echo vs_e($endpoint); ?></code>
                            <p class="vs-user-ip__proxy-meta">ID <?php echo $pid; ?> · 调用可加 vsproxyid=<?php echo $pid; ?></p>
                        </div>
                        <div class="vs-user-ip__proxy-actions">
                            <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-proxy-act="test" data-id="<?php echo $pid; ?>">测试</button>
                            <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-proxy-act="edit" data-id="<?php echo $pid; ?>">编辑</button>
                            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-btn--sm" data-proxy-act="delete" data-id="<?php echo $pid; ?>">删除</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($ready): ?>
<div class="vs-overlay vs-overlay--form" id="userIpAllowOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userIpAllowTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userIpAllowTitle">添加 IP</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userIpAddForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <div class="vs-form-row">
                <label class="vs-label" for="userIpInput">IP 地址 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userIpInput" name="ip" maxlength="64" required autofocus
                       placeholder="例如 203.0.113.10 或 IPv6">
                <p class="vs-form-hint">支持 IPv4 / IPv6；空名单表示不限制。最多 <?php echo (int) $maxCount; ?> 条。</p>
            </div>
            <?php if ($clientIp !== ''): ?>
            <div class="vs-form-row">
                <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" id="userIpFillCurrent">填入当前访问 IP（<?php echo vs_e($clientIp); ?>）</button>
            </div>
            <?php endif; ?>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userIpAddForm" class="vs-btn vs-btn--primary" id="userIpAddBtn">确定添加</button>
        </footer>
    </div>
</div>
<?php endif; ?>

<?php if ($proxyReady): ?>
<div class="vs-overlay vs-overlay--lg" id="userProxyFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userProxyFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userProxyFormTitle">添加出口代理</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userProxyForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="userProxyId" name="id" value="0">
            <div class="vs-user-ip__form-grid">
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyTitle">备注名称</label>
                    <input type="text" class="vs-input" id="userProxyTitle" name="title" maxlength="60" placeholder="例如 快代理隧道">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyMode">对接模式</label>
                    <select class="vs-input vs-select" id="userProxyMode" name="mode" data-vs-pick>
                        <option value="0">隧道（主机+端口）</option>
                        <option value="1">提取 API</option>
                    </select>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyProto">协议</label>
                    <select class="vs-input vs-select" id="userProxyProto" name="proto" data-vs-pick>
                        <option value="0">HTTP</option>
                        <option value="1">HTTPS</option>
                        <option value="2">SOCKS5</option>
                        <option value="3">SOCKS4</option>
                    </select>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyStatus">状态</label>
                    <select class="vs-input vs-select" id="userProxyStatus" name="status" data-vs-pick>
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <div class="vs-form-row vs-user-ip__field-tunnel">
                    <label class="vs-label" for="userProxyHost">主机</label>
                    <input type="text" class="vs-input" id="userProxyHost" name="host" maxlength="255" placeholder="例如 proxy.example.com">
                </div>
                <div class="vs-form-row vs-user-ip__field-tunnel">
                    <label class="vs-label" for="userProxyPort">端口</label>
                    <input type="number" class="vs-input" id="userProxyPort" name="port" min="1" max="65535" placeholder="例如 8080">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyUser">账号（可选）</label>
                    <input type="text" class="vs-input" id="userProxyUser" name="username" maxlength="200" placeholder="厂商用户名或带地区参数">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxyPass">密码（可选）</label>
                    <input type="password" class="vs-input" id="userProxyPass" name="password" maxlength="200" placeholder="留空表示不修改已保存密码" autocomplete="new-password">
                </div>
                <div class="vs-form-row vs-user-ip__field-extract vs-user-ip__field-span">
                    <label class="vs-label" for="userProxyExtract">提取 API 地址</label>
                    <input type="url" class="vs-input" id="userProxyExtract" name="extract" maxlength="1000" placeholder="https://…/getip?…">
                </div>
                <div class="vs-form-row vs-user-ip__field-extract">
                    <label class="vs-label" for="userProxyExtfmt">提取返回格式</label>
                    <select class="vs-input vs-select" id="userProxyExtfmt" name="extfmt" data-vs-pick>
                        <option value="0">自动识别</option>
                        <option value="1">纯文本 ip:port</option>
                        <option value="2">JSON</option>
                    </select>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="userProxySort">排序（越小越前）</label>
                    <input type="number" class="vs-input" id="userProxySort" name="sort" value="0" min="-9999" max="9999">
                </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userProxyForm" class="vs-btn vs-btn--primary" id="userProxySaveBtn">保存</button>
        </footer>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
