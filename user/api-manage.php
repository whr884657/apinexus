<?php
/**
 * 文件：user/api-manage.php
 * 作用：开发者 API 管理（提交接口、查看审核状态）
 *
 * 权限：未绑定管理员 → 仅可投代理外链；已绑定 → 与后台相同可选本地/代理。
 */

require_once __DIR__ . '/init.php';

vs_user_require_developer('API 管理');

$userId = (int) UserAuth::id();
$canLocal = AdminUserBinding::isUserBoundToAdmin($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    if (!UserRole::currentCanPublishApi()) {
        AjaxResponse::error('无权操作', 403);
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $payloadFromPost = function () use ($canLocal) {
        $apitype = isset($_POST['apitype']) ? (int) $_POST['apitype'] : ApiManager::APITYPE_PROXY;
        if (!$canLocal) {
            $apitype = ApiManager::APITYPE_PROXY;
        }
        $data = array(
            'name'         => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description'  => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'     => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'apitype'      => $apitype,
            'targeturl'    => isset($_POST['targeturl']) ? (string) $_POST['targeturl'] : '',
            'proxyslug'    => isset($_POST['proxyslug']) ? (string) $_POST['proxyslug'] : '',
            'upauth'       => isset($_POST['upauth']) ? (int) $_POST['upauth'] : 0,
            'upmethod'     => isset($_POST['upmethod']) ? (int) $_POST['upmethod'] : 0,
            'upkeyvia'     => isset($_POST['upkeyvia']) ? (int) $_POST['upkeyvia'] : 0,
            'upkeyname'    => isset($_POST['upkeyname']) ? (string) $_POST['upkeyname'] : '',
            'upkey'        => isset($_POST['upkey']) ? (string) $_POST['upkey'] : '',
            'upuamode'     => isset($_POST['upuamode']) ? (int) $_POST['upuamode'] : 0,
            'upuapreset'   => isset($_POST['upuapreset']) ? (string) $_POST['upuapreset'] : '',
            'upua'         => isset($_POST['upua']) ? (string) $_POST['upua'] : '',
            'upreferermode'=> isset($_POST['upreferermode']) ? (int) $_POST['upreferermode'] : 0,
            'upreferer'    => isset($_POST['upreferer']) ? (string) $_POST['upreferer'] : '',
            'jsonrewrite'  => isset($_POST['jsonrewrite']) ? (string) $_POST['jsonrewrite'] : '',
            'method'       => isset($_POST['method']) ? $_POST['method'] : 'GET',
            'params'       => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response'     => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'          => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'        => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'      => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'keyways'      => (function () {
                $raw = isset($_POST['keyways']) ? $_POST['keyways'] : 'query';
                return is_array($raw) ? implode(',', $raw) : (string) $raw;
            })(),
            'qpm'          => isset($_POST['qpm']) ? (int) $_POST['qpm'] : 0,
            'charge'       => isset($_POST['charge']) ? (int) $_POST['charge'] : 0,
            'price'        => isset($_POST['price']) ? $_POST['price'] : 0,
            'status'       => ApiManager::STATUS_NORMAL,
            'audit'        => ApiManager::AUDIT_PENDING,
            'rejectreason' => '',
            'icon'         => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'     => isset($_POST['category']) ? (string) $_POST['category'] : '',
        );
        return vs_decode_transport_fields($data, array('doc', 'aidoc', 'response', 'params', 'jsonrewrite'));
    };

    $assertOwner = function ($apiId) use ($userId) {
        $row = ApiManager::findById($apiId);
        if (!$row) {
            return '接口不存在';
        }
        if (!AdminUserBinding::userOwnsApi($userId, (int) $row['userid'])) {
            return '无权操作该接口';
        }
        return $row;
    };

    if ($action === 'get') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $row = $assertOwner($id);
        if (!is_array($row)) {
            AjaxResponse::error($row);
        }
        AjaxResponse::success('ok', array('api' => ApiManager::formatRow($row)));
    }

    if ($action === 'create') {
        if (!ApiManager::hasAuditColumn() || !ApiManager::hasRejectReasonColumn() || !ApiManager::hasProxyColumns()) {
            AjaxResponse::error('请先联系管理员完成系统升级后再提交接口');
        }
        $data = $payloadFromPost();
        $data['userid'] = $userId;
        $data['audit'] = ApiManager::AUDIT_PENDING;
        $result = ApiManager::create($data);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AiChatSession::clearAllForActor('user', (int) $userId);
        $mail = ApiNotify::notifyAdminsPending($result);
        $msg = '已提交，等待管理员审核';
        if (!$mail['ok'] && $mail['error'] !== '' && strpos($mail['error'], '已关闭') === false) {
            $msg .= '（管理员邮件未送达：' . $mail['error'] . '）';
        }
        AjaxResponse::success($msg, array(
            'api'         => $result,
            'api_summary' => ApiManager::formatRowSummary($result),
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $data = $payloadFromPost();
        $data['audit'] = ApiManager::AUDIT_PENDING;
        $data['rejectreason'] = '';
        $result = ApiManager::update($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        // 管理员侧 userid=0：绑定用户首次保存时补挂 userid，便于后续精确归属
        if ((int) $owned['userid'] === 0) {
            ApiManager::attachUserIdIfOrphan($id, $userId);
        }
        AiChatSession::clearAllForActor('user', (int) $userId);
        $row = ApiManager::findById($id);
        $formatted = ApiManager::formatRow($row);
        $mail = ApiNotify::notifyAdminsPending($formatted);
        $msg = '已保存并重新提交审核';
        if (!$mail['ok'] && $mail['error'] !== '' && strpos($mail['error'], '已关闭') === false) {
            $msg .= '（管理员邮件未送达：' . $mail['error'] . '）';
        }
        AjaxResponse::success($msg, array(
            'api'         => $formatted,
            'api_summary' => ApiManager::formatRowSummary($formatted),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $result = ApiManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已删除', array('api_id' => $id));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $owned = $assertOwner($id);
        if (!is_array($owned)) {
            AjaxResponse::error($owned);
        }
        $audit = ApiManager::normalizeAuditStatus(isset($owned['audit']) ? $owned['audit'] : ApiManager::AUDIT_PENDING);
        if ($audit !== ApiManager::AUDIT_APPROVED) {
            AjaxResponse::error('仅审核通过的接口可调整运行状态');
        }
        $status = ApiManager::normalizeStatus(isset($_POST['status']) ? $_POST['status'] : '');
        $result = ApiManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        AjaxResponse::success('状态已更新', array(
            'api_id'       => $id,
            'status'       => $status,
            'status_label' => ApiManager::statusLabel($status),
            'api_summary'  => ApiManager::formatRowSummary($row),
        ));
    }

    if ($action === 'ai_gen_doc' || $action === 'ai_gen_doc_stream' || $action === 'ai_gen_doc_section_stream' || $action === 'ai_gen_code'
        || $action === 'ai_gen_code_piece' || $action === 'ai_gen_code_piece_stream' || $action === 'ai_chat_clear') {
        if (!class_exists('AiConfig') || !AiConfig::isReady()) {
            AjaxResponse::error('请先联系管理员在系统设置中启用并配置 AI');
        }
        @ignore_user_abort(true);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $data = $payloadFromPost();
        $data['id'] = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        if ($data['id'] > 0) {
            $owned = $assertOwner($data['id']);
            if (!is_array($owned)) {
                AjaxResponse::error($owned);
            }
            $call = ApiManager::resolveCallUrl($owned);
            if ($call !== '') {
                $data['endpoint'] = $call;
                $data['callurl'] = $call;
            }
            if (empty($data['proxyslug']) && isset($owned['proxyslug'])) {
                $data['proxyslug'] = $owned['proxyslug'];
            }
            if (!isset($data['apitype'])) {
                $data['apitype'] = isset($owned['apitype']) ? (int) $owned['apitype'] : ApiManager::APITYPE_PROXY;
            }
        }
        $aiCfg = AiConfig::get();
        $aiTimeout = (int) (isset($aiCfg['timeout']) ? $aiCfg['timeout'] : 60);
        if ($aiTimeout < 30) {
            $aiTimeout = 30;
        }
        if ($aiTimeout > 600) {
            $aiTimeout = 600;
        }
        @set_time_limit($aiTimeout + 60);
        unset(
            $data['upkey'],
            $data['targeturl'],
            $data['upuamode'],
            $data['upuapreset'],
            $data['upua'],
            $data['upreferermode'],
            $data['upreferer'],
            $data['upkeyvia'],
            $data['upkeyname'],
            $data['upauth'],
            $data['jsonrewrite']
        );

        $topic = AiChatSession::topicFromApi($data);
        $docSessionKey = AiChatSession::key('user', $userId, 'doc', $topic);
        $codeSessionKey = AiChatSession::key('user', $userId, 'code', $topic);

        if ($action === 'ai_chat_clear') {
            $scope = isset($_POST['scope']) ? strtolower(trim((string) $_POST['scope'])) : 'doc';
            if ($scope === 'code') {
                AiChatSession::clear($codeSessionKey);
            } elseif ($scope === 'all') {
                AiChatSession::clear($docSessionKey);
                AiChatSession::clear($codeSessionKey);
            } else {
                AiChatSession::clear($docSessionKey);
            }
            AjaxResponse::success('已清除短时效对话记录');
        }

        if ($action === 'ai_gen_doc_section_stream') {
            $section = isset($_POST['section']) ? strtolower(trim((string) $_POST['section'])) : '';
            try {
                AiSse::begin();
                AiSse::emit('meta', array(
                    'section' => $section,
                    'topic'   => $topic,
                ));
                $gen = AiApiDoc::generateDetailDocSectionStream(
                    $data,
                    $section,
                    function ($chunk) {
                        AiSse::emit('delta', array('text' => (string) $chunk));
                    }
                );
                if (empty($gen['ok'])) {
                    AiSse::emit('error', array(
                        'msg'        => isset($gen['error']) ? (string) $gen['error'] : '生成失败',
                        'partial'    => isset($gen['partial']) ? (string) $gen['partial'] : '',
                        'section_id' => isset($gen['section_id']) ? (string) $gen['section_id'] : $section,
                        'title'      => isset($gen['title']) ? (string) $gen['title'] : '',
                    ));
                    AiSse::end();
                    exit;
                }
                AiSse::emit('done', array(
                    'section'    => $gen['section'],
                    'section_id' => $gen['section_id'],
                    'title'      => $gen['title'],
                    'msg'        => '章节已生成',
                ));
                AiSse::end();
                exit;
            } catch (Exception $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '章节生成失败，请稍后重试或点「继续生成」', 'partial' => 1));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '章节生成失败，请稍后重试或点「继续生成」', 'partial' => 1));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        if ($action === 'ai_gen_doc_stream') {
            $continue = !empty($_POST['continue']);
            try {
                AiSse::begin();
                AiSse::emit('meta', array(
                    'history'  => AiChatSession::historyAvailable(),
                    'continue' => $continue ? 1 : 0,
                    'topic'    => $topic,
                ));
                $gen = AiApiDoc::generateDetailDocStream(
                    $data,
                    $docSessionKey,
                    $continue,
                    function ($chunk) {
                        AiSse::emit('delta', array('text' => (string) $chunk));
                    }
                );
                if (empty($gen['ok'])) {
                    AiSse::emit('error', array(
                        'msg'     => isset($gen['error']) ? (string) $gen['error'] : '生成失败',
                        'doc'     => isset($gen['doc']) ? (string) $gen['doc'] : '',
                        'partial' => 1,
                    ));
                    AiSse::end();
                    exit;
                }
                AiSse::emit('done', array(
                    'doc'       => $gen['doc'],
                    'continued' => !empty($gen['continued']) ? 1 : 0,
                    'history'   => !empty($gen['history']) ? 1 : 0,
                    'msg'       => '详细文档已生成',
                ));
                AiSse::end();
                exit;
            } catch (Exception $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '生成失败，请稍后重试或点「继续生成」', 'partial' => 1));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '生成失败，请稍后重试或点「继续生成」', 'partial' => 1));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        if ($action === 'ai_gen_doc') {
            try {
                $gen = AiApiDoc::generateDetailDoc($data);
                if (!is_array($gen)) {
                    AjaxResponse::error(is_string($gen) ? preg_replace('/^错误：/', '', $gen) : '生成失败');
                }
                AjaxResponse::success('详细文档已生成', array('doc' => $gen['doc']));
            } catch (Exception $e) {
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        if ($action === 'ai_gen_code_piece_stream') {
            $auth = isset($_POST['auth']) ? (string) $_POST['auth'] : '';
            $lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
            try {
                AiSse::begin();
                AiSse::emit('meta', array(
                    'auth' => $auth,
                    'lang' => $lang,
                ));
                $gen = AiApiDoc::generateCodeSamplePieceStream(
                    $data,
                    $auth,
                    $lang,
                    function ($chunk) {
                        AiSse::emit('delta', array('text' => (string) $chunk));
                    }
                );
                if (empty($gen['ok'])) {
                    AiSse::emit('error', array(
                        'msg'     => isset($gen['error']) ? (string) $gen['error'] : '生成失败',
                        'partial' => isset($gen['partial']) ? (string) $gen['partial'] : '',
                        'auth'    => isset($gen['auth']) ? (string) $gen['auth'] : $auth,
                        'lang'    => isset($gen['lang']) ? (string) $gen['lang'] : $lang,
                    ));
                    AiSse::end();
                    exit;
                }
                if (AiChatSession::historyAvailable()) {
                    AiChatSession::appendTurn(
                        $codeSessionKey,
                        '请生成鉴权=' . $gen['auth'] . ' 语言=' . $gen['lang'] . ' 的极简示例',
                        '已完成 :::qs lang=' . $gen['lang'] . ' auth=' . $gen['auth']
                    );
                }
                AiSse::emit('done', array(
                    'piece' => $gen['piece'],
                    'auth'  => $gen['auth'],
                    'lang'  => $gen['lang'],
                    'msg'   => '单片已生成',
                ));
                AiSse::end();
                exit;
            } catch (Exception $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '生成失败，请稍后重试', 'auth' => $auth, 'lang' => $lang));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array('msg' => '生成失败，请稍后重试', 'auth' => $auth, 'lang' => $lang));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        if ($action === 'ai_gen_code_piece') {
            $auth = isset($_POST['auth']) ? (string) $_POST['auth'] : '';
            $lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
            try {
                $gen = AiApiDoc::generateCodeSamplePiece($data, $auth, $lang);
                if (!is_array($gen)) {
                    AjaxResponse::error(is_string($gen) ? preg_replace('/^错误：/', '', $gen) : '生成失败');
                }
                if (AiChatSession::historyAvailable()) {
                    AiChatSession::appendTurn(
                        $codeSessionKey,
                        '请生成鉴权=' . $gen['auth'] . ' 语言=' . $gen['lang'] . ' 的极简示例',
                        '已完成 :::qs lang=' . $gen['lang'] . ' auth=' . $gen['auth']
                    );
                }
                AjaxResponse::success('单片已生成', array(
                    'piece' => $gen['piece'],
                    'auth'  => $gen['auth'],
                    'lang'  => $gen['lang'],
                ));
            } catch (Exception $e) {
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        $gen = AiApiDoc::generateCodeSamples($data);
        if (!is_array($gen)) {
            AjaxResponse::error(is_string($gen) ? preg_replace('/^错误：/', '', $gen) : '生成失败');
        }
        $payload = array('aidoc' => $gen['aidoc']);
        if (!empty($gen['warning'])) {
            $payload['warning'] = (string) $gen['warning'];
        }
        $okMsg = !empty($gen['warning']) ? '代码示例已部分生成' : '代码示例已生成';
        AjaxResponse::success($okMsg, $payload);
    }

    AjaxResponse::error('无效操作', 400);
}

$tableReady = ApiManager::tableReady()
    && ApiManager::hasAuditColumn()
    && ApiManager::hasRejectReasonColumn()
    && ApiManager::hasProxyColumns();
$apis = $tableReady ? ApiManager::listByUser($userId) : array();
$categories = ApiCategoryManager::tableReady() ? ApiCategoryManager::listEnabled() : array();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = vs_site_base_path();
$aiReady = class_exists('AiConfig') && AiConfig::isReady();
$aiCodeOpts = class_exists('AiConfig') ? AiConfig::codeClientOptions() : array('mode' => 'sequential', 'concurrency' => 1, 'ready' => false);

/**
 * @param array $row
 * @return void
 */
function vs_render_user_api_item(array $row)
{
    $api = ApiManager::formatRowSummary($row);
    if (!$api) {
        return;
    }
    $apiId = (int) $api['id'];
    $reason = isset($api['rejectreason']) ? trim((string) $api['rejectreason']) : '';
    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
    $audit = (int) $api['audit'];
    $rowStatus = isset($api['status']) ? (int) $api['status'] : ApiManager::STATUS_NORMAL;
    $rowStatusClass = 'is-normal';
    if ($rowStatus === ApiManager::STATUS_DISABLED) {
        $rowStatusClass = 'is-disabled';
    } elseif ($rowStatus === ApiManager::STATUS_MAINTENANCE) {
        $rowStatusClass = 'is-maintenance';
    }
    $methods = isset($api['methods']) && is_array($api['methods'])
        ? $api['methods']
        : ApiManager::normalizeMethods(isset($api['method']) ? $api['method'] : 'GET');
    $approved = $audit === ApiManager::AUDIT_APPROVED;
    $keyBadge = isset($api['needkey_badge']) ? (string) $api['needkey_badge'] : ApiManager::requireKeyBadge(isset($api['needkey']) ? $api['needkey'] : 0);
    $category = isset($api['category']) ? trim((string) $api['category']) : '';
    ?>
    <div class="vs-api-item vs-user-api-row" data-api-row="<?php echo $apiId; ?>" data-api-status="<?php echo $rowStatus; ?>" data-api-audit="<?php echo $audit; ?>">
        <div class="vs-api-item__icon">
            <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">
        </div>
        <div class="vs-api-item__title">
            <span class="vs-api-item__name" data-field="name"><?php echo vs_e($api['name']); ?></span>
            <span class="vs-api-item__id">#<?php echo $apiId; ?></span>
        </div>
        <div class="vs-api-item__endpoint">
            <span class="vs-api-list-methods" data-field="method">
                <?php foreach ($methods as $m): ?>
                    <?php
                    $mSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $m));
                    if ($mSlug === '') {
                        $mSlug = 'get';
                    }
                    ?>
                    <span class="vs-api-list-method vs-api-list-method--<?php echo vs_e($mSlug); ?>"><?php echo vs_e(strtoupper((string) $m)); ?></span>
                <?php endforeach; ?>
            </span>
            <span class="vs-api-item__url" data-field="call_url" title="<?php echo vs_e($callUrl); ?>"><?php echo vs_e($callUrl); ?></span>
        </div>
        <div class="vs-api-item__tags">
            <?php
            $apitype = isset($api['apitype']) ? (int) $api['apitype'] : ApiManager::APITYPE_LOCAL;
            $typeBadge = isset($api['apitype_badge']) ? (string) $api['apitype_badge'] : ApiManager::apiTypeBadge($apitype);
            $typeTagClass = ($apitype === ApiManager::APITYPE_PROXY) ? 'vs-api-tag--proxy' : 'vs-api-tag--local';
            ?>
            <span class="vs-api-tag <?php echo $typeTagClass; ?>" data-field="apitype_badge"><?php echo vs_e($typeBadge !== '' ? $typeBadge : ($apitype === ApiManager::APITYPE_PROXY ? '代理' : '本地')); ?></span>
            <?php if ($category !== ''): ?>
                <span class="vs-api-tag vs-api-tag--cat"><?php echo vs_e($category); ?></span>
            <?php endif; ?>
            <span class="vs-api-tag vs-api-tag--free" data-field="charge_tag"><?php
                $charge = isset($api['charge']) ? (int) $api['charge'] : 0;
                $price = isset($api['price']) ? (string) $api['price'] : '0';
                echo ($charge === 1 && (float) $price > 0) ? vs_e('每次 ' . $price . ' 积分') : '免费';
            ?></span>
            <?php if ($keyBadge !== ''): ?>
                <span class="vs-api-tag vs-api-tag--key"><?php echo vs_e($keyBadge); ?></span>
            <?php endif; ?>
            <?php
            $qpmShow = isset($api['qpm']) ? (int) $api['qpm'] : 0;
            if ($qpmShow > 0):
            ?>
                <span class="vs-api-tag vs-api-tag--qpm" data-field="qpm_badge">QPM <?php echo vs_e($qpmShow . '/MIN'); ?></span>
            <?php endif; ?>
            <?php if (!$approved): ?>
                <span class="vs-api-tag vs-api-tag--audit <?php echo vs_e($api['audit_class']); ?>" data-field="audit_label"><?php echo vs_e($api['audit_label']); ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-api-item__meta">
            <div class="vs-api-item__status">
                <?php if ($approved): ?>
                    状态：<span class="vs-api-tag vs-api-tag--status <?php echo $rowStatusClass; ?>" data-field="status_label"><?php echo vs_e($api['status_label']); ?></span>
                <?php else: ?>
                    <span data-field="status_label"></span>
                <?php endif; ?>
            </div>
            <div class="vs-api-item__calls" title="请求次数">请求：<strong data-field="calls"><?php echo (int) $api['calls']; ?></strong></div>
            <div class="vs-api-item__author"></div>
        </div>
        <p class="vs-api-review-reason vs-user-api-row__reason" data-field="rejectreason"<?php echo $reason === '' ? ' hidden' : ''; ?>>
            未通过原因：<?php echo vs_e($reason); ?>
        </p>
        <div class="vs-api-item__actions vs-user-api-row__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-user-api-edit" data-api-id="<?php echo $apiId; ?>">编辑</button>
            <?php if ($approved): ?>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-normal vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_NORMAL ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="0">正常</button>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-maint vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_MAINTENANCE ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="2">维护</button>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--status vs-btn--status-disabled vs-user-api-status<?php echo $rowStatus === ApiManager::STATUS_DISABLED ? ' is-active' : ''; ?>" data-api-id="<?php echo $apiId; ?>" data-status="1">禁用</button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-user-api-delete" data-api-id="<?php echo $apiId; ?>">删除</button>
        </div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    $headerActions = '<button type="button" class="vs-btn vs-btn--primary" id="userApiAddBtn">提交接口</button>';
}

vs_user_render_page(
    'apimanage',
    'API 管理',
    'api-manage',
    array(
        'tableReady'       => $tableReady,
        'apis'             => $apis,
        'categories'       => $categories,
        'defaultIconPaths' => $defaultIconPaths,
        'iconBase'         => $iconBase,
        'aiReady'          => $aiReady,
        'aiCodeOpts'       => $aiCodeOpts,
        'canLocal'         => $canLocal,
    ),
    $headerActions,
    $tableReady ? array('icon-picker.js', 'api-params-editor.js', 'vs-syntax.js', 'user-api-manage.js') : array()
);
