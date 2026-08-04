<?php
/**
 * 默认主题 · 开发者 API 管理页视图
 */
if (!defined('VS_THEME_RENDER')) {
    exit;
}

$tableReady = !empty($tableReady);
$apis = isset($apis) && is_array($apis) ? $apis : array();
$categories = isset($categories) && is_array($categories) ? $categories : array();
$defaultIconPaths = isset($defaultIconPaths) && is_array($defaultIconPaths) ? $defaultIconPaths : array();
$iconBase = isset($iconBase) ? (string) $iconBase : rtrim(vs_base_url(), '/');
$aiReady = !empty($aiReady);
$aiCodeOpts = isset($aiCodeOpts) && is_array($aiCodeOpts) ? $aiCodeOpts : array();
$canLocal = !empty($canLocal);
?>

<div class="vs-panel" id="userApiManagePage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-can-local="<?php echo $canLocal ? '1' : '0'; ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>">

    <?php if (!$tableReady): ?>
        <?php vs_render_notice('warning', '', '接口投稿功能尚未就绪，请联系管理员完成系统升级。', array('compact' => true)); ?>
    <?php else: ?>
        <?php
        $tip = $canLocal
            ? '可提交本地接口或代理外链。提交后需管理员审核；修改后将重新进入待审核。'
            : '当前账号仅可提交代理外链接口（对方完整地址）。提交后需管理员审核；修改后将重新进入待审核。';
        vs_render_notice('info', '', $tip, array('compact' => true));
        ?>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="userApiEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">点击右上角「提交接口」，填写信息后等待审核。</p>
            </div>
        </div>

        <div class="vs-api-list-table vs-user-api-list" id="userApiList"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-table__body">
                <?php foreach ($apis as $row): ?>
                    <?php vs_render_user_api_item($row); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($tableReady): ?>
<div class="vs-api-list-footer" id="userApiFooter"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
    <div class="vs-api-pager" id="userApiPager">
        <label class="vs-api-list-pagesize" for="userApiPageSize">
            <span class="vs-api-list-pagesize__label">每页</span>
            <select class="vs-input vs-select vs-api-list-pagesize__select" id="userApiPageSize" data-vs-pick="sheet">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
            </select>
        </label>
        <button type="button" class="vs-api-pager__nav" id="userApiPrevBtn" aria-label="上一页">上一页</button>
        <div class="vs-api-pager__nums" id="userApiPagerNums" role="navigation" aria-label="页码"></div>
        <button type="button" class="vs-api-pager__nav" id="userApiNextBtn" aria-label="下一页">下一页</button>
    </div>
    <p class="vs-api-list-stats" id="userApiStats">共 <?php echo (int) count($apis); ?> 个接口</p>
</div>
<?php endif; ?>

<?php if ($tableReady): ?>
<div class="vs-overlay vs-overlay--lg" id="userApiFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="userApiFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="userApiFormTitle">提交接口</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="userApiForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="userApiFormId" name="api_id" value="">
            <input type="hidden" id="userApiFormApiType" name="apitype" value="<?php echo $canLocal ? '0' : '1'; ?>">

            <div class="vs-api-list-form-tabs" role="tablist">
                <button type="button" class="vs-api-list-form-tab is-active" data-api-form-tab="basic" role="tab" aria-selected="true">基础</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="params" role="tab" aria-selected="false">参数</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="docs" role="tab" aria-selected="false">文档</button>
            </div>

            <div class="vs-api-list-form-pane is-active" data-api-form-pane="basic">
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormName">接口名称 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormName" name="name" maxlength="100" required
                       placeholder="例如：天气查询">
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormDesc">接口描述</label>
                <textarea class="vs-input vs-textarea" id="userApiFormDesc" name="description" rows="3"
                          placeholder="简要说明接口用途"></textarea>
            </div>

            <?php if ($canLocal): ?>
            <div class="vs-form-row">
                <label class="vs-label">接口类型</label>
                <div class="vs-api-type-tabs" id="userApiTypeTabs">
                    <button type="button" class="vs-btn vs-btn--primary vs-user-api-type-tab" data-apitype="0">本地接口</button>
                    <button type="button" class="vs-btn vs-btn--default vs-user-api-type-tab" data-apitype="1">代理外链</button>
                </div>
                <p class="vs-form-hint" id="userApiTypeHint">本地接口：只填本站路径，如 /api/img/index.php</p>
            </div>
            <?php else: ?>
            <p class="vs-form-hint">本账号仅支持提交外链接口：填写对方完整地址与短码，系统生成本站公开地址。</p>
            <?php endif; ?>

            <div class="vs-form-row" id="userApiEndpointRow"<?php echo $canLocal ? '' : ' hidden'; ?>>
                <label class="vs-label" for="userApiFormEndpoint">本地路径 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormEndpoint" name="endpoint" maxlength="500"
                       placeholder="/api/img/index.php">
            </div>
            <div class="vs-form-row" id="userApiTargetRow"<?php echo $canLocal ? ' hidden' : ''; ?>>
                <label class="vs-label" for="userApiFormTargetUrl">上游完整地址 <span class="vs-req">*</span></label>
                <input type="url" class="vs-input" id="userApiFormTargetUrl" name="targeturl" maxlength="500"
                       placeholder="https://api.example.com/v1/demo" <?php echo $canLocal ? '' : 'required'; ?>>
            </div>
            <div class="vs-form-row" id="userApiSlugRow"<?php echo $canLocal ? ' hidden' : ''; ?>>
                <label class="vs-label" for="userApiFormProxySlug">接口短码 <span class="vs-req">*</span></label>
                <input type="text" class="vs-input" id="userApiFormProxySlug" name="proxyslug" maxlength="64"
                       placeholder="例如 sjspks（3～64 位字母或数字）" pattern="[A-Za-z0-9]{3,64}"
                       autocomplete="off" <?php echo $canLocal ? '' : 'required'; ?>>
                <p class="vs-form-hint">公开地址：<?php echo vs_e($iconBase); ?>/apis/短码</p>
            </div>
            <div id="userApiClientProfileBlock">
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="userApiFormUpUaMode">出站 User-Agent</label>
                        <select class="vs-input vs-select" id="userApiFormUpUaMode" name="upuamode" data-vs-pick>
                            <option value="0">系统默认</option>
                            <option value="1">内置设备 / 浏览器</option>
                            <option value="2">自定义</option>
                            <option value="3">轮询内置（按分钟）</option>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label" for="userApiFormUpRefererMode">出站 Referer</label>
                        <select class="vs-input vs-select" id="userApiFormUpRefererMode" name="upreferermode" data-vs-pick>
                            <option value="0">不发送</option>
                            <option value="1">自定义</option>
                            <option value="2">转发客户端</option>
                        </select>
                    </div>
                </div>
                <p class="vs-form-hint">本地与代理均可配置。代理由网关自动带上；本地脚本出站请求时使用此处配置。</p>
                <div class="vs-form-row" id="userApiUpUaPresetWrap" hidden>
                    <label class="vs-label" for="userApiFormUpUaPreset">内置 UA 预设</label>
                    <select class="vs-input vs-select" id="userApiFormUpUaPreset" name="upuapreset" data-vs-pick>
                        <option value="">请选择</option>
                        <?php foreach (ProxyClientProfile::presetOptions() as $opt): ?>
                            <option value="<?php echo vs_e($opt['value']); ?>"><?php echo vs_e($opt['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vs-form-row" id="userApiUpUaCustomWrap" hidden>
                    <label class="vs-label" for="userApiFormUpUa">自定义 User-Agent</label>
                    <input type="text" class="vs-input" id="userApiFormUpUa" name="upua" maxlength="512"
                           placeholder="完整浏览器 User-Agent 字符串" autocomplete="off">
                </div>
                <div class="vs-form-row" id="userApiUpRefererWrap" hidden>
                    <label class="vs-label" for="userApiFormUpReferer">自定义 Referer</label>
                    <input type="url" class="vs-input" id="userApiFormUpReferer" name="upreferer" maxlength="500"
                           placeholder="https://example.com/" autocomplete="off">
                </div>
            </div>
            <div id="userApiUpAuthBlock"<?php echo $canLocal ? ' hidden' : ''; ?>>
                <div id="userApiUpKeyViaWrap" hidden>
                    <input type="hidden" id="userApiFormUpKeyVia" name="upkeyvia" value="0">
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="userApiFormUpMethod">上游请求方式</label>
                        <select class="vs-input vs-select" id="userApiFormUpMethod" name="upmethod" data-vs-pick>
                            <option value="0">GET</option>
                            <option value="1">POST</option>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label" for="userApiFormUpAuth">上游认证方式</label>
                        <select class="vs-input vs-select" id="userApiFormUpAuth" name="upauth" data-vs-pick>
                            <option value="0">无需认证</option>
                            <option value="1">Query API Key</option>
                            <option value="3">Header API Key</option>
                            <option value="2">Bearer Token</option>
                        </select>
                    </div>
                </div>
                <p class="vs-form-hint">上游请求方式：中继打向上游的方法（可与调用方「请求方式」不同）。</p>
                <div class="vs-form-row vs-form-row--2" id="userApiUpKeyFields" hidden>
                    <div id="userApiUpKeyNameWrap">
                        <label class="vs-label" for="userApiFormUpKeyName">参数名 / 头名称</label>
                        <input type="text" class="vs-input" id="userApiFormUpKeyName" name="upkeyname" maxlength="64"
                               placeholder="如 api_key 或 X-API-Key" autocomplete="off">
                    </div>
                    <div>
                        <label class="vs-label" for="userApiFormUpKey">上游密钥 <span class="vs-req">*</span></label>
                        <input type="password" class="vs-input" id="userApiFormUpKey" name="upkey" maxlength="500"
                               placeholder="上游平台颁发的密钥或令牌" autocomplete="new-password">
                    </div>
                </div>
                <div class="vs-form-row" id="userApiJsonRewriteBlock">
                    <label class="vs-label">JSON 字段改写</label>
                    <label class="vs-check" for="userApiFormJsonRewriteOn">
                        <input type="checkbox" id="userApiFormJsonRewriteOn" value="1">
                        <span>启用（只改上游返回的 JSON；其它类型不改）</span>
                    </label>
                    <div class="vs-json-rewrite-help">
                        <strong>怎么填「要改的字段」？</strong>
                        <ol>
                            <li>看上游返回的 JSON，找到要改的字段名。</li>
                            <li>用英文句点 <code>.</code> 串起来：例如改 <code>api_info</code> 里的 <code>developer</code>，填 <code>api_info.developer</code>。</li>
                            <li>「设置」= 改成你填的值；「删除」= 去掉该字段。</li>
                        </ol>
                        <div class="vs-json-rewrite-help__eg">示例：字段 api_info.blog → 设置 → https://你的博客。勿填库账号/密码/密钥；业务错误响应不改写。</div>
                    </div>
                    <input type="hidden" id="userApiFormJsonRewrite" name="jsonrewrite" value="">
                    <div class="vs-json-rewrite" id="userApiJsonRewriteEditor" hidden>
                        <div class="vs-json-rewrite__head">
                            <span>要改的字段</span>
                            <span>操作</span>
                            <span>新值（设置时填）</span>
                            <span></span>
                        </div>
                        <div class="vs-json-rewrite__rows" id="userApiJsonRewriteRows"></div>
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiJsonRewriteAdd">添加规则</button>
                    </div>
                </div>
                <p class="vs-form-hint">本站请求上游后回传调用方。密钥与出站 UA/Referer 仅服务端使用。</p>
            </div>

            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label">请求方式</label>
                    <div class="vs-method-toggles" id="userApiFormMethodChecks" role="group" aria-label="请求方式">
                        <button type="button" class="vs-method-toggle is-on" data-api-method="GET" aria-pressed="true">GET</button>
                        <button type="button" class="vs-method-toggle" data-api-method="POST" aria-pressed="false">POST</button>
                    </div>
                    <p class="vs-form-hint">可同时选择 GET 与 POST。</p>
                </div>
                <div>
                    <label class="vs-label" for="userApiFormNeedkey">密钥要求</label>
                    <select class="vs-input vs-select" id="userApiFormNeedkey" name="needkey" data-vs-pick>
                        <option value="0">无需 KEY</option>
                        <option value="1">KEY 必填</option>
                        <option value="2">KEY 可选</option>
                    </select>
                    <p class="vs-form-hint">「无需 KEY」与「KEY 可选」调用规则相同；选「无需 KEY」时前台通常不展示密钥填写框。</p>
                </div>
            </div>
            <div class="vs-form-row" id="userApiKeywaysRow">
                <label class="vs-label">鉴权传递方式</label>
                <div class="vs-method-toggles" id="userApiFormKeywayChecks" role="group" aria-label="鉴权传递方式">
                    <button type="button" class="vs-method-toggle is-on" data-api-keyway="query" aria-pressed="true">Query 参数</button>
                    <button type="button" class="vs-method-toggle" data-api-keyway="header" aria-pressed="false">Header</button>
                    <button type="button" class="vs-method-toggle" data-api-keyway="bearer" aria-pressed="false">Bearer</button>
                </div>
                <p class="vs-form-hint">可同时勾选多种方式；默认 Query。</p>
            </div>
            <div class="vs-form-row vs-form-row--2">
                <div>
                    <label class="vs-label" for="userApiFormQpm">QPM 每分钟上限</label>
                    <input type="number" class="vs-input" id="userApiFormQpm" name="qpm" min="0" max="1000000" step="1" value="0" placeholder="0 表示不限制">
                    <p class="vs-form-hint">0 不限制；大于 0 为每分钟最大请求数。</p>
                </div>
                <div>
                    <label class="vs-label" for="userApiFormCharge">是否收费</label>
                    <select class="vs-input vs-select" id="userApiFormCharge" name="charge" data-vs-pick>
                        <option value="0">免费</option>
                        <option value="1">收费</option>
                    </select>
                </div>
            </div>
            <div class="vs-form-row vs-form-row--2" id="userApiPriceRow" hidden>
                <div>
                    <label class="vs-label" for="userApiFormPrice">每次扣除积分</label>
                    <input type="number" class="vs-input" id="userApiFormPrice" name="price" min="0.0001" step="0.0001" placeholder="如 0.1 或 1">
                </div>
                <div></div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormCategory">所属分类</label>
                <select class="vs-input vs-select" id="userApiFormCategory" name="category" data-vs-pick>
                    <option value="">未分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">接口图标</label>
                <div class="vs-api-cat-icon-picker" id="userApiIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                <label class="vs-label vs-api-cat-icon-url-label" for="userApiIconUrl">或填写图标链接</label>
                <input type="url" class="vs-input" id="userApiIconUrl" name="icon"
                       placeholder="https://example.com/icon.png" maxlength="255">
                <p class="vs-form-hint">点选下方图标，或填写图片链接地址。</p>
            </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="params" hidden>
            <div class="vs-form-row">
                <label class="vs-label">请求参数</label>
                <textarea class="vs-input vs-textarea" id="userApiFormParams" name="params" hidden aria-hidden="true"></textarea>
                <div class="vs-params-editor" id="userApiParamsEditor" data-hidden-id="userApiFormParams"></div>
            </div>
            <div class="vs-form-row">
                <label class="vs-label" for="userApiFormResponse">返回参数示例</label>
                <textarea class="vs-input vs-textarea" id="userApiFormResponse" name="response" rows="4"
                          placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                <p class="vs-form-hint">返回示例保持 JSON 文本填写即可。</p>
            </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="docs" hidden>
            <div class="vs-form-row">
                <div class="vs-ai-gen-banner" id="userApiAiBanner" hidden data-ai-banner="doc">
                    <span class="vs-ai-gen-banner__dot" aria-hidden="true"></span>
                    <span class="vs-ai-gen-banner__text" id="userApiAiBannerText">正在生成…</span>
                    <span class="vs-ai-gen-banner__time" id="userApiAiBannerTime"></span>
                </div>
                <div class="vs-api-doc-head">
                    <label class="vs-label" for="userApiFormDoc">详细文档（Markdown）</label>
                    <?php if ($aiReady): ?>
                    <div class="vs-api-doc-head__actions">
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiDocBtn"
                                title="按章节生成详细文档">AI 生成详细文档</button>
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiDocContinueBtn" hidden
                                title="从中断章节继续">继续生成</button>
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiChatClearBtn"
                                title="清除本接口短时效对话（约 10 分钟；保存接口时会全部清空）">清除对话</button>
                    </div>
                    <?php endif; ?>
                </div>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormDoc" name="doc" rows="10"
                          data-vs-md="off" placeholder="面向调用方的详细说明…"></textarea>
                <p class="vs-form-hint"><?php echo $aiReady
                    ? '按章节自动生成详细文档；偶尔中断会自动重试一次，仍失败可点「继续生成」。勿写入上游地址或密钥。'
                    : '管理员启用 AI 后可一键生成；亦可手写 Markdown。勿写入上游地址或密钥。'; ?></p>
                <?php if ($aiReady): ?>
                <details class="vs-ai-term" id="userApiAiTermDoc" data-ai-term="doc">
                    <summary class="vs-ai-term__summary">AI 编写进程（详细文档）</summary>
                    <pre class="vs-ai-term__log font-mono" id="userApiAiTermDocLog">尚未开始生成。</pre>
                </details>
                <?php endif; ?>
            </div>
            <div class="vs-form-row">
                <div class="vs-ai-gen-banner" id="userApiAiBannerCode" hidden data-ai-banner="code">
                    <span class="vs-ai-gen-banner__dot" aria-hidden="true"></span>
                    <span class="vs-ai-gen-banner__text" id="userApiAiBannerCodeText">正在生成…</span>
                    <span class="vs-ai-gen-banner__time" id="userApiAiBannerCodeTime"></span>
                </div>
                <div class="vs-api-doc-head">
                    <label class="vs-label" for="userApiFormAidoc">代码示例（:::qs 多语言）</label>
                    <?php if ($aiReady): ?>
                    <div class="vs-api-doc-head__actions">
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiCodeBtn"
                                title="按已选鉴权×9 语言一键生成（最多 27 片）">AI 生成代码示例</button>
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiCodeRetryBtn" hidden
                                title="只重试上次失败的片">重试失败</button>
                        <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="userApiAiCodeClearBtn"
                                title="清空代码示例框与进程日志">清空示例</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($aiReady): ?>
                <div class="vs-api-ai-code-ways" id="userApiAiCodeWays" hidden>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="query" hidden
                            title="仅生成 Query 鉴权下 9 种语言">生成 Query</button>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="header" hidden
                            title="仅生成 Header 鉴权下 9 种语言">生成 Header</button>
                    <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="bearer" hidden
                            title="仅生成 Bearer 鉴权下 9 种语言">生成 Bearer</button>
                </div>
                <?php endif; ?>
                <textarea class="vs-input vs-textarea vs-api-list-code" id="userApiFormAidoc" name="aidoc" rows="10"
                          data-vs-md="off" placeholder=":::qs lang=curl&#10;...&#10;:::&#10;&#10;:::qs lang=python&#10;...&#10;:::"></textarea>
                <p class="vs-form-hint"><?php echo $aiReady
                    ? '须使用 :::qs lang=语言标识 包裹。主按钮一键生成已选鉴权×9 语言（最多 27 片）；下方可按鉴权单独生成 9 片（会合并保留其它鉴权块）。失败可点「重试失败」。'
                    : '须使用 :::qs lang=语言标识 包裹多语言示例。管理员启用 AI 后可一键生成。'; ?></p>
                <?php if ($aiReady): ?>
                <details class="vs-ai-term" id="userApiAiTermCode" data-ai-term="code">
                    <summary class="vs-ai-term__summary">AI 编写进程（代码示例）</summary>
                    <pre class="vs-ai-term__log font-mono" id="userApiAiTermCodeLog">尚未开始生成。</pre>
                </details>
                <?php endif; ?>
            </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="userApiForm" class="vs-btn vs-btn--primary" id="userApiFormSubmitBtn">提交审核</button>
        </footer>
    </div>
</div>

<style>
.vs-api-type-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.vs-user-api-list { margin-top: 12px; }
.vs-user-api-row__reason {
    margin: 0;
    font-size: 12px;
    color: #b45309;
}
.vs-user-api-row__reason[hidden] { display: none !important; }
</style>
<?php endif; ?>

<?php
echo Markdown::renderAssetsHtml();
if ($tableReady && !empty($aiReady)) {
    echo '<script>window.VS_AI_CODE=' . json_encode($aiCodeOpts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
}
