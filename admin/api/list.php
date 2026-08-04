<?php
/**
 * 文件：admin/api/list.php
 * 作用：接口列表（后台添加 / 编辑 / 状态管理）
 */

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    $payloadFromPost = function () {
        $keywaysRaw = isset($_POST['keyways']) ? $_POST['keyways'] : 'query';
        if (is_array($keywaysRaw)) {
            $keywaysRaw = implode(',', $keywaysRaw);
        }
        $data = array(
            'name'        => isset($_POST['name']) ? (string) $_POST['name'] : '',
            'description' => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'endpoint'    => isset($_POST['endpoint']) ? (string) $_POST['endpoint'] : '',
            'apitype'     => isset($_POST['apitype']) ? (int) $_POST['apitype'] : ApiManager::APITYPE_LOCAL,
            'targeturl'   => isset($_POST['targeturl']) ? (string) $_POST['targeturl'] : '',
            'proxyslug'   => isset($_POST['proxyslug']) ? (string) $_POST['proxyslug'] : '',
            'upauth'      => isset($_POST['upauth']) ? (int) $_POST['upauth'] : 0,
            'upmethod'    => isset($_POST['upmethod']) ? (int) $_POST['upmethod'] : 0,
            'upkeyvia'    => isset($_POST['upkeyvia']) ? (int) $_POST['upkeyvia'] : 0,
            'upkeyname'   => isset($_POST['upkeyname']) ? (string) $_POST['upkeyname'] : '',
            'upkey'       => isset($_POST['upkey']) ? (string) $_POST['upkey'] : '',
            'upuamode'    => isset($_POST['upuamode']) ? (int) $_POST['upuamode'] : 0,
            'upuapreset'  => isset($_POST['upuapreset']) ? (string) $_POST['upuapreset'] : '',
            'upua'        => isset($_POST['upua']) ? (string) $_POST['upua'] : '',
            'upreferermode' => isset($_POST['upreferermode']) ? (int) $_POST['upreferermode'] : 0,
            'upreferer'   => isset($_POST['upreferer']) ? (string) $_POST['upreferer'] : '',
            'jsonrewrite' => isset($_POST['jsonrewrite']) ? (string) $_POST['jsonrewrite'] : '',
            'method'      => isset($_POST['method']) ? $_POST['method'] : 'GET',
            'params'      => isset($_POST['params']) ? (string) $_POST['params'] : '',
            'response'    => isset($_POST['response']) ? (string) $_POST['response'] : '',
            'doc'         => isset($_POST['doc']) ? (string) $_POST['doc'] : '',
            'aidoc'       => isset($_POST['aidoc']) ? (string) $_POST['aidoc'] : '',
            'needkey'     => isset($_POST['needkey']) ? (int) $_POST['needkey'] : 0,
            'keyways'     => $keywaysRaw,
            'qpm'         => isset($_POST['qpm']) ? (int) $_POST['qpm'] : 0,
            'charge'      => isset($_POST['charge']) ? (int) $_POST['charge'] : 0,
            'price'       => isset($_POST['price']) ? $_POST['price'] : 0,
            'status'      => isset($_POST['status']) ? $_POST['status'] : ApiManager::STATUS_NORMAL,
            'icon'        => isset($_POST['icon']) ? (string) $_POST['icon'] : '',
            'category'    => isset($_POST['category']) ? (string) $_POST['category'] : '',
        );
        // doc / aidoc / response / params / jsonrewrite 可能含代码样例，经 VS64 传输规避 WAF 误拦（接口描述保持明文，勿编码）
        return vs_decode_transport_fields($data, array('doc', 'aidoc', 'response', 'params', 'jsonrewrite'));
    };

    if ($action === 'get') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $row = ApiManager::findById($id);
        if (!$row) {
            AjaxResponse::error('接口不存在');
        }
        AjaxResponse::success('ok', array('api' => ApiManager::formatRow($row)));
    }

    if ($action === 'create') {
        $data = $payloadFromPost();
        $publishUid = AdminUserBinding::publishUserId((int) Auth::id());
        if (!is_int($publishUid)) {
            AjaxResponse::error((string) $publishUid);
        }
        // 使用绑定用户身份展示投稿者；审核默认通过，不进「待审核」
        $data['userid'] = $publishUid;
        $data['audit'] = ApiManager::AUDIT_APPROVED;
        $result = ApiManager::create($data);
        if (!is_array($result)) {
            AjaxResponse::error($result);
        }
        AiChatSession::clearAllForActor('admin', (int) Auth::id());
        AjaxResponse::success('接口已添加', array(
            'api'         => $result,
            'api_summary' => ApiManager::formatRowSummary($result),
        ));
    }

    if ($action === 'update') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $data = $payloadFromPost();
        // 管理员后台编辑不改审核态（本页无审核控件；发布侧一律视为已通过）
        $data['audit'] = ApiManager::AUDIT_APPROVED;
        $result = ApiManager::update($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        $row = ApiManager::findById($id);
        // 历史管理员发布未挂身份：保存时补挂当前绑定用户
        if ($row && (int) $row['userid'] === 0) {
            $publishUid = AdminUserBinding::publishUserId((int) Auth::id());
            if (is_int($publishUid) && $publishUid > 0) {
                ApiManager::attachUserIdIfOrphan($id, $publishUid);
                $row = ApiManager::findById($id);
            }
        }
        AiChatSession::clearAllForActor('admin', (int) Auth::id());
        $formatted = ApiManager::formatRow($row);
        AjaxResponse::success('接口已保存', array(
            'api'         => $formatted,
            'api_summary' => ApiManager::formatRowSummary($formatted),
        ));
    }

    if ($action === 'set_status') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $status = ApiManager::normalizeStatus(isset($_POST['status']) ? $_POST['status'] : '');
        $result = ApiManager::setStatus($id, $status);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('状态已更新', array(
            'api_id'       => $id,
            'status'       => $status,
            'status_label' => ApiManager::statusLabel($status),
        ));
    }

    if ($action === 'set_audit') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $audit = ApiManager::normalizeAuditStatus(isset($_POST['audit']) ? $_POST['audit'] : '');
        $result = ApiManager::setAuditStatus($id, $audit);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('审核状态已更新', array(
            'api_id'      => $id,
            'audit'       => $audit,
            'audit_label' => ApiManager::auditStatusLabel($audit),
        ));
    }

    if ($action === 'delete') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $result = ApiManager::delete($id);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('接口已删除', array('api_id' => $id));
    }

    if ($action === 'draft_save') {
        $id = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        if ($id <= 0) {
            AjaxResponse::success('草稿已记在本地', array('local_only' => 1));
        }
        $data = $payloadFromPost();
        // 自动草稿：表单半填时用库中字段补齐空值，避免校验失败导致静默丢草稿
        $row = ApiManager::findById($id);
        if (is_array($row)) {
            $fillKeys = array(
                'name', 'description', 'endpoint', 'targeturl', 'proxyslug',
                'method', 'params', 'response', 'doc', 'aidoc', 'category',
                'icon', 'upkeyname', 'upkey',
            );
            foreach ($fillKeys as $k) {
                $cur = isset($data[$k]) ? trim((string) $data[$k]) : '';
                if ($cur === '' && isset($row[$k]) && (string) $row[$k] !== '') {
                    $data[$k] = $row[$k];
                }
            }
            // 代理接口：表单若未带 apitype=1 但库中是代理，且本地 endpoint 仍空，按代理补齐
            if ((int) (isset($row['apitype']) ? $row['apitype'] : 0) === 1
                && (int) $data['apitype'] !== 1
                && trim((string) $data['endpoint']) === '') {
                $data['apitype'] = 1;
                if (trim((string) $data['targeturl']) === '' && !empty($row['targeturl'])) {
                    $data['targeturl'] = $row['targeturl'];
                }
                if (trim((string) $data['proxyslug']) === '' && !empty($row['proxyslug'])) {
                    $data['proxyslug'] = $row['proxyslug'];
                }
            }
        }
        $data['audit'] = ApiManager::AUDIT_APPROVED;
        $result = ApiManager::update($id, $data);
        if ($result !== true) {
            AjaxResponse::error($result);
        }
        AjaxResponse::success('已自动保存', array('api_id' => $id, 'silent' => 1));
    }

    if ($action === 'ai_gen_doc' || $action === 'ai_gen_doc_stream' || $action === 'ai_gen_doc_section_stream' || $action === 'ai_gen_code'
        || $action === 'ai_gen_code_piece' || $action === 'ai_gen_code_piece_stream' || $action === 'ai_chat_clear') {
        if (!AiConfig::isReady()) {
            AjaxResponse::error('请先在系统设置中启用并配置 AI');
        }
        @ignore_user_abort(true);
        // 认证/CSRF 已完成：立刻释放 Session 锁，否则并行分片会被 PHP 会话文件串行卡住
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $data = $payloadFromPost();
        $data['id'] = isset($_POST['api_id']) ? (int) $_POST['api_id'] : 0;
        $aiCfg = AiConfig::get();
        $aiTimeout = (int) (isset($aiCfg['timeout']) ? $aiCfg['timeout'] : 60);
        if ($aiTimeout < 30) {
            $aiTimeout = 30;
        }
        if ($aiTimeout > 600) {
            $aiTimeout = 600;
        }
        // 分片：单片只占一份超时；整包由前端多次请求，避免网关/PHP 一次拖死
        @set_time_limit($aiTimeout + 60);
        // 已有接口时补全对外调用地址（代理不暴露上游）
        if ($data['id'] > 0) {
            $row = ApiManager::findById($data['id']);
            if (is_array($row)) {
                $call = ApiManager::resolveCallUrl($row);
                if ($call !== '') {
                    $data['endpoint'] = $call;
                    $data['callurl'] = $call;
                }
                if (empty($data['proxyslug']) && isset($row['proxyslug'])) {
                    $data['proxyslug'] = $row['proxyslug'];
                }
                if (!isset($data['apitype'])) {
                    $data['apitype'] = isset($row['apitype']) ? (int) $row['apitype'] : 0;
                }
            }
        }
        // 绝不把上游密钥 / 出站指纹交给生成逻辑
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

        $adminId = (int) Auth::id();
        $topic = AiChatSession::topicFromApi($data);
        $docSessionKey = AiChatSession::key('admin', $adminId, 'doc', $topic);
        $codeSessionKey = AiChatSession::key('admin', $adminId, 'code', $topic);

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
                    'history'   => AiChatSession::historyAvailable(),
                    'continue'  => $continue ? 1 : 0,
                    'topic'     => $topic,
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
                        'msg'  => isset($gen['error']) ? (string) $gen['error'] : '生成失败',
                        'doc'  => isset($gen['doc']) ? (string) $gen['doc'] : '',
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

        // v12.0.0：前端分片调用；单片 = 一种鉴权 × 一种语言（SSE 流式）
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
                    AiSse::emit('error', array(
                        'msg'  => '生成失败，请稍后重试',
                        'auth' => $auth,
                        'lang' => $lang,
                    ));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            } catch (Throwable $e) {
                if (class_exists('AiSse') && AiSse::isActive()) {
                    AiSse::emit('error', array(
                        'msg'  => '生成失败，请稍后重试',
                        'auth' => $auth,
                        'lang' => $lang,
                    ));
                    AiSse::end();
                    exit;
                }
                AjaxResponse::error('生成失败，请稍后重试');
            }
        }

        // v12.0.0：前端分片调用；单片 = 一种鉴权 × 一种语言
        if ($action === 'ai_gen_code_piece') {
            $auth = isset($_POST['auth']) ? (string) $_POST['auth'] : '';
            $lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
            try {
                $gen = AiApiDoc::generateCodeSamplePiece($data, $auth, $lang);
                if (!is_array($gen)) {
                    AjaxResponse::error(is_string($gen) ? preg_replace('/^错误：/', '', $gen) : '生成失败');
                }
                // 短时效：记一笔「已完成某片」，便于同会话风格连贯（压缩）
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

        // 兼容旧客户端：仍可一次跑完，但易超时；推荐刷新页面使用分片
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

$tableReady = ApiManager::tableReady();
$apis = $tableReady ? ApiManager::listAll() : array();
$defaultIconPaths = ApiCategoryManager::defaultIconPaths();
$iconBase = rtrim(vs_base_url(), '/');
$categories = ApiCategoryManager::tableReady() ? ApiCategoryManager::listEnabled() : array();

$countTotal = count($apis);
$countMaint = 0;
$countPending = 0;
foreach ($apis as $_row) {
    if ((int) (isset($_row['status']) ? $_row['status'] : 0) === ApiManager::STATUS_MAINTENANCE) {
        $countMaint += 1;
    }
    if (ApiManager::hasAuditColumn() && (int) (isset($_row['audit']) ? $_row['audit'] : 1) === ApiManager::AUDIT_PENDING) {
        $countPending += 1;
    }
}
$titleMeta = '当前接口总数 ' . $countTotal;
if ($countMaint > 0 || $countPending > 0) {
    if ($countMaint > 0) {
        $titleMeta .= '，维护中 ' . $countMaint;
    }
    if ($countPending > 0) {
        $titleMeta .= '，待审核 ' . $countPending;
    }
}

/**
 * @param int $status
 * @return string
 */
function vs_api_list_status_text($status)
{
    $status = ApiManager::normalizeStatus($status);
    if ($status === ApiManager::STATUS_DISABLED) {
        return '已禁用';
    }
    if ($status === ApiManager::STATUS_MAINTENANCE) {
        return '维护中';
    }
    return '正常';
}

/**
 * @param int $status
 * @return string
 */
function vs_api_list_status_badge_class($status)
{
    $status = ApiManager::normalizeStatus($status);
    if ($status === ApiManager::STATUS_DISABLED) {
        return 'vs-badge--error';
    }
    if ($status === ApiManager::STATUS_MAINTENANCE) {
        return 'vs-badge--warning';
    }
    return 'vs-badge--success';
}

/**
 * @param string $keyBadge
 * @return string
 */
function vs_api_list_key_badge_html($keyBadge)
{
    $keyBadge = trim((string) $keyBadge);
    if ($keyBadge === '') {
        return '<span class="key-badge key-badge--none" data-field="needkey_badge">KEY 不必要</span>';
    }
    $class = 'key-badge--optional';
    if (strpos($keyBadge, '必填') !== false) {
        $class = 'key-badge--required';
    }
    return '<span class="key-badge ' . $class . '" data-field="needkey_badge">' . vs_e($keyBadge) . '</span>';
}

/**
 * @param int $charge
 * @param mixed $price
 * @return string
 */
function vs_api_list_charge_badge_html($charge, $price)
{
    $charge = (int) $charge;
    $priceStr = (string) $price;
    if ($charge === 1 && (float) $priceStr > 0) {
        return '<span class="charge-badge charge-badge--points" data-field="charge_tag">'
            . vs_e($priceStr . '积分/次') . '</span>';
    }
    return '<span class="charge-badge charge-badge--free" data-field="charge_tag">免费</span>';
}

/**
 * QPM>0 时显示；0 不输出
 *
 * @param mixed $qpm
 * @return string
 */
function vs_api_list_qpm_badge_html($qpm)
{
    $n = (int) $qpm;
    if ($n <= 0) {
        return '';
    }
    return '<span class="qpm-badge qpm-badge--limit" data-field="qpm_badge">QPM ' . vs_e($n . '/MIN') . '</span>';
}

/**
 * @param array $methods
 * @return string
 */
function vs_api_list_method_badges_html(array $methods)
{
    $html = '<div class="method-list" data-field="method">';
    foreach ($methods as $m) {
        $mSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $m));
        if ($mSlug === '') {
            $mSlug = 'get';
        }
        $html .= '<span class="method-badge method-badge--' . vs_e($mSlug) . '">'
            . vs_e(strtoupper((string) $m)) . '</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param int $apiId
 * @param int $status
 * @return string
 */
function vs_api_list_action_buttons_html($apiId, $status)
{
    $apiId = (int) $apiId;
    $status = ApiManager::normalizeStatus($status);
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-api-list-action" data-api-action="edit" data-api-id="'
        . $apiId . '">编辑</button>';
    if ($status === ApiManager::STATUS_NORMAL) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="maintenance" data-api-id="'
            . $apiId . '">维护</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="disable" data-api-id="'
            . $apiId . '">禁用</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="'
            . $apiId . '">删除</button>';
    } elseif ($status === ApiManager::STATUS_MAINTENANCE) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-list-action" data-api-action="normal" data-api-id="'
            . $apiId . '">恢复正常</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-api-list-action" data-api-action="disable" data-api-id="'
            . $apiId . '">禁用</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="'
            . $apiId . '">删除</button>';
    } else {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-api-list-action" data-api-action="normal" data-api-id="'
            . $apiId . '">启用</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-api-list-action" data-api-action="delete" data-api-id="'
            . $apiId . '">删除</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $row
 * @return array|null
 */
function vs_api_list_row_context(array $row)
{
    $api = ApiManager::formatRowSummary($row);
    if (!$api) {
        return null;
    }
    $apiId = (int) $api['id'];
    $status = (int) $api['status'];
    $auditStatus = isset($api['audit']) ? (int) $api['audit'] : ApiManager::AUDIT_APPROVED;
    $callUrl = isset($api['call_url']) ? (string) $api['call_url'] : (string) $api['endpoint'];
    $typeBadge = isset($api['apitype_badge']) ? (string) $api['apitype_badge'] : ApiManager::apiTypeBadge(isset($api['apitype']) ? $api['apitype'] : 0);
    $keyBadge = isset($api['needkey_badge']) ? (string) $api['needkey_badge'] : ApiManager::requireKeyBadge(isset($api['needkey']) ? $api['needkey'] : 0);
    $category = isset($api['category']) ? trim((string) $api['category']) : '';
    $username = isset($api['username']) ? trim((string) $api['username']) : '';
    if ($username === '') {
        if ((int) $api['userid'] > 0) {
            $username = '用户#' . (int) $api['userid'];
        } else {
            $bound = AdminUserBinding::getBoundUser((int) Auth::id());
            $username = ($bound && !empty($bound['username'])) ? (string) $bound['username'] : '管理员';
        }
    }
    $methods = isset($api['methods']) && is_array($api['methods'])
        ? $api['methods']
        : ApiManager::normalizeMethods(isset($api['method']) ? $api['method'] : 'GET');
    $searchHay = mb_strtolower(
        $api['name'] . ' ' . $callUrl . ' ' . $api['endpoint'] . ' ' . $category . ' ' . $typeBadge . ' ' . $username,
        'UTF-8'
    );
    $payloadJson = json_encode($api, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $typeClass = ($typeBadge === '代理') ? 'type-badge--proxy' : 'type-badge--local';
    $calls = (int) $api['calls'];
    $charge = isset($api['charge']) ? (int) $api['charge'] : 0;
    $price = isset($api['price']) ? (string) $api['price'] : '0';
    $statusText = vs_api_list_status_text($status);
    $statusBadgeClass = vs_api_list_status_badge_class($status);

    return array(
        'api'              => $api,
        'apiId'            => $apiId,
        'status'           => $status,
        'auditStatus'      => $auditStatus,
        'callUrl'          => $callUrl,
        'typeBadge'        => $typeBadge,
        'typeClass'        => $typeClass,
        'keyBadge'         => $keyBadge,
        'category'         => $category,
        'username'         => $username,
        'methods'          => $methods,
        'searchHay'        => $searchHay,
        'payloadJson'      => $payloadJson !== false ? $payloadJson : '{}',
        'calls'            => $calls,
        'charge'           => $charge,
        'price'            => $price,
        'statusText'       => $statusText,
        'statusBadgeClass' => $statusBadgeClass,
    );
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_api_list_desktop_row(array $ctx)
{
    $api = $ctx['api'];
    $rowAttrs = ' data-api-row="' . (int) $ctx['apiId'] . '"'
        . ' data-api-status="' . (int) $ctx['status'] . '"'
        . ' data-api-audit="' . (int) $ctx['auditStatus'] . '"'
        . ' data-api-category="' . vs_e($ctx['category']) . '"'
        . ' data-api-calls="' . (int) $ctx['calls'] . '"'
        . ' data-api-name="' . vs_e($api['name']) . '"'
        . ' data-search="' . vs_e($ctx['searchHay']) . '"'
        . ' data-payload=\'' . $ctx['payloadJson'] . '\'';
    ?>
    <tr<?php echo $rowAttrs; ?>>
        <td><span class="api-id" data-field="id"><?php echo (int) $ctx['apiId']; ?></span></td>
        <td>
            <div class="api-name-cell">
                <div class="api-icon">
                    <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <span class="api-name-text" data-field="name"><?php echo vs_e($api['name']); ?></span>
            </div>
        </td>
        <td><span class="vs-api-list-author" data-field="username"><?php echo vs_e($ctx['username']); ?></span></td>
        <td data-field="category_cell"><?php if ($ctx['category'] !== ''): ?>
            <span class="vs-badge vs-badge--default" data-field="category"><?php echo vs_e($ctx['category']); ?></span>
        <?php else: ?><span class="vs-badge vs-badge--default" data-field="category">未分类</span><?php endif; ?></td>
        <td><span class="type-badge <?php echo vs_e($ctx['typeClass']); ?>" data-field="apitype_badge"><?php echo vs_e($ctx['typeBadge']); ?></span></td>
        <td><?php echo vs_api_list_method_badges_html($ctx['methods']); ?></td>
        <td><?php echo vs_api_list_charge_badge_html($ctx['charge'], $ctx['price']); ?></td>
        <td><?php echo vs_api_list_key_badge_html($ctx['keyBadge']); ?><?php echo vs_api_list_qpm_badge_html(isset($ctx['api']['qpm']) ? $ctx['api']['qpm'] : 0); ?></td>
        <td><span class="vs-badge <?php echo vs_e($ctx['statusBadgeClass']); ?>" data-field="status_label"><?php echo vs_e($ctx['statusText']); ?></span></td>
        <td class="vs-api-list-calls-cell"><span data-field="calls"><?php echo number_format((int) $ctx['calls']); ?></span></td>
        <td><?php echo vs_api_list_action_buttons_html($ctx['apiId'], $ctx['status']); ?></td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @return void
 */
function vs_render_api_list_mobile_card(array $ctx)
{
    $api = $ctx['api'];
    $rowAttrs = ' data-api-row="' . (int) $ctx['apiId'] . '"'
        . ' data-api-status="' . (int) $ctx['status'] . '"'
        . ' data-api-audit="' . (int) $ctx['auditStatus'] . '"'
        . ' data-api-category="' . vs_e($ctx['category']) . '"'
        . ' data-api-calls="' . (int) $ctx['calls'] . '"'
        . ' data-api-name="' . vs_e($api['name']) . '"'
        . ' data-search="' . vs_e($ctx['searchHay']) . '"'
        . ' data-payload=\'' . $ctx['payloadJson'] . '\'';
    ?>
    <div class="api-card"<?php echo $rowAttrs; ?>>
        <div class="api-card__header">
            <div class="api-card__header-left">
                <span class="api-id" data-field="id">#<?php echo (int) $ctx['apiId']; ?></span>
                <div class="api-card__icon">
                    <img src="<?php echo vs_e($api['icon']); ?>" alt="" width="32" height="32" loading="lazy" referrerpolicy="no-referrer" data-field="icon">
                </div>
                <span class="api-card__name" data-field="name"><?php echo vs_e($api['name']); ?></span>
            </div>
            <span class="vs-badge <?php echo vs_e($ctx['statusBadgeClass']); ?>" data-field="status_label"><?php echo vs_e($ctx['statusText']); ?></span>
        </div>
        <div class="api-card__tags" data-field="tags">
            <?php if ($ctx['category'] !== ''): ?>
                <span class="vs-badge vs-badge--default" data-field="category"><?php echo vs_e($ctx['category']); ?></span>
            <?php endif; ?>
            <span class="type-badge <?php echo vs_e($ctx['typeClass']); ?>" data-field="apitype_badge"><?php echo vs_e($ctx['typeBadge']); ?></span>
            <?php echo vs_api_list_charge_badge_html($ctx['charge'], $ctx['price']); ?>
            <?php echo vs_api_list_key_badge_html($ctx['keyBadge']); ?>
            <?php echo vs_api_list_qpm_badge_html(isset($ctx['api']['qpm']) ? $ctx['api']['qpm'] : 0); ?>
        </div>
        <div class="api-card__info">
            <span class="api-card__info-item"><span class="api-card__info-label">提交者</span> <span class="api-card__info-value" data-field="username"><?php echo vs_e($ctx['username']); ?></span></span>
            <span class="api-card__info-item"><span class="api-card__info-label">方式</span> <?php
                foreach ($ctx['methods'] as $m) {
                    $mSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $m));
                    if ($mSlug === '') {
                        $mSlug = 'get';
                    }
                    echo '<span class="method-badge method-badge--' . vs_e($mSlug) . '">' . vs_e(strtoupper((string) $m)) . '</span>';
                }
            ?></span>
            <span class="api-card__info-item"><span class="api-card__info-label">调用</span> <span class="api-card__calls" data-field="calls"><?php echo number_format((int) $ctx['calls']); ?></span></span>
        </div>
        <div class="api-card__actions"><?php echo vs_api_list_action_buttons_html($ctx['apiId'], $ctx['status']); ?></div>
    </div>
    <?php
}

$headerActions = '';
if ($tableReady) {
    ob_start();
    ?>
    <div class="vs-search-bar vs-api-list-toolbar">
        <div class="vs-search-bar__input-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" class="vs-input vs-search-bar__input" id="apiListSearchInput"
                   placeholder="搜索接口名称、ID 或路径..." autocomplete="off">
        </div>
        <button type="button" class="vs-btn vs-btn--primary" id="apiListOpenAddBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            添加接口
        </button>
    </div>
    <?php
    $headerActions = ob_get_clean();
}

vs_admin_layout_start('接口列表', 'api-list', $headerActions);
?>

<div id="apiListPage"
     data-icon-base="<?php echo vs_e($iconBase); ?>"
     data-default-icons="<?php echo vs_e(json_encode($defaultIconPaths, JSON_UNESCAPED_UNICODE)); ?>"
     data-stats-total="<?php echo (int) $countTotal; ?>"
     data-stats-maint="<?php echo (int) $countMaint; ?>"
     data-stats-pending="<?php echo (int) $countPending; ?>">

    <?php if ($tableReady): ?>
    <div class="vs-filter-row vs-api-list-filters" id="apiListFilters">
        <select class="vs-input vs-select" id="apiListFilterCategory" data-vs-pick aria-label="筛选分类">
            <option value="">全部分类</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="vs-input vs-select" id="apiListFilterStatus" data-vs-pick aria-label="筛选状态">
            <option value="">全部状态</option>
            <option value="0">正常</option>
            <option value="1">已禁用</option>
            <option value="2">维护中</option>
        </select>
        <select class="vs-input vs-select" id="apiListFilterSort" data-vs-pick aria-label="排序方式">
            <option value="newest">排序：最新创建</option>
            <option value="calls-desc">排序：调用量降序</option>
            <option value="calls-asc">排序：调用量升序</option>
            <option value="name-az">排序：名称 A-Z</option>
        </select>
    </div>
    <?php endif; ?>

    <div class="vs-panel vs-api-list-panel">
    <?php if (!$tableReady): ?>
        <div class="vs-api-list-upgrade">
            <?php vs_render_notice('warning', '', '接口管理功能尚未就绪，请先前往「系统升级」完成更新后再使用。', array('compact' => true)); ?>
            <a class="vs-btn vs-btn--primary" href="<?php echo vs_e(vs_base_url() . '/admin/upgrade'); ?>">前往系统升级</a>
        </div>
    <?php else: ?>
        <div class="vs-api-list-tip vs-api-list-tip--enter">
            <?php vs_render_notice('info', '', '正常：可对外提供服务。维护：站点前台仍可看到，但暂不可请求。禁用：站点前台不显示。未通过审核的接口也不会在站点前台展示。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-list-empty vs-api-list-empty--hero" id="apiListEmpty"<?php echo count($apis) > 0 ? ' hidden' : ''; ?>>
            <div class="vs-api-list-empty__card">
                <h3 class="vs-api-list-empty__title">暂无接口</h3>
                <p class="vs-api-list-empty__desc">请点击右上角「添加接口」进行配置（名称、地址、参数与文档等）。</p>
            </div>
        </div>

        <div class="vs-api-list-empty" id="apiListSearchEmpty" hidden>
            <?php vs_render_notice('info', '', '没有匹配的接口。', array('compact' => true)); ?>
        </div>

        <div class="vs-api-list-table-card vs-api-list-table-wrap" id="apiListTableWrap"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <div class="vs-table-responsive">
                <table class="vs-table vs-api-list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>接口名称</th>
                            <th>提交者</th>
                            <th>分类</th>
                            <th>接口类型</th>
                            <th>请求方式</th>
                            <th>收费状态</th>
                            <th>KEY要求</th>
                            <th>状态</th>
                            <th>调用次数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="apiListBody">
                        <?php foreach ($apis as $row): ?>
                            <?php
                            $listCtx = vs_api_list_row_context($row);
                            if ($listCtx) {
                                vs_render_api_list_desktop_row($listCtx);
                            }
                            ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-api-cards" id="apiListMobile"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($apis as $row): ?>
                <?php
                $listCtx = vs_api_list_row_context($row);
                if ($listCtx) {
                    vs_render_api_list_mobile_card($listCtx);
                }
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <?php if ($tableReady): ?>
    <div class="vs-api-list-footer" id="apiListFooter"<?php echo count($apis) === 0 ? ' hidden' : ''; ?>>
        <div class="vs-api-pager" id="apiListPager">
            <label class="vs-api-list-pagesize" for="apiListPageSize">
                <span class="vs-api-list-pagesize__label">每页</span>
                <select class="vs-input vs-select vs-api-list-pagesize__select" id="apiListPageSize" data-vs-pick="sheet">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
            <button type="button" class="vs-api-pager__nav" id="apiListPrevBtn" aria-label="上一页">上一页</button>
            <div class="vs-api-pager__nums" id="apiListPagerNums" role="navigation" aria-label="页码"></div>
            <button type="button" class="vs-api-pager__nav" id="apiListNextBtn" aria-label="下一页">下一页</button>
        </div>
        <p class="vs-api-list-stats" id="apiListStats"><?php echo vs_e($titleMeta); ?></p>
    </div>
    <?php endif; ?>
</div>

<div class="vs-overlay vs-overlay--lg" id="apiListFormOverlay" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-overlay-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-labelledby="apiListFormTitle" aria-modal="true">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="apiListFormTitle">添加接口</h3>
            <button type="button" class="vs-overlay__close" data-overlay-close="1" aria-label="关闭">&times;</button>
        </header>
        <form id="apiListForm" class="vs-overlay__body vs-form" autocomplete="off" novalidate>
            <input type="hidden" id="apiListFormId" name="api_id" value="">

            <div class="vs-api-list-form-tabs" role="tablist">
                <button type="button" class="vs-api-list-form-tab is-active" data-api-form-tab="basic" role="tab" aria-selected="true">基础</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="params" role="tab" aria-selected="false">参数</button>
                <button type="button" class="vs-api-list-form-tab" data-api-form-tab="docs" role="tab" aria-selected="false">文档</button>
            </div>

            <div class="vs-api-list-form-pane is-active" data-api-form-pane="basic">
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormName">接口名称 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormName" name="name" maxlength="100" required
                           placeholder="例如：天气查询">
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormDesc">接口描述</label>
                    <textarea class="vs-input vs-textarea" id="apiListFormDesc" name="description" rows="3"
                              placeholder="简要说明接口用途"></textarea>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormStatus">接口状态</label>
                        <select class="vs-input vs-select" id="apiListFormStatus" name="status" data-vs-pick>
                            <option value="0">正常</option>
                            <option value="2">维护</option>
                            <option value="1">禁用</option>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label">请求方式 <span class="vs-req">*</span></label>
                        <div class="vs-method-toggles" id="apiListFormMethodChecks" role="group" aria-label="请求方式">
                            <button type="button" class="vs-method-toggle is-on" data-api-method="GET" aria-pressed="true">GET</button>
                            <button type="button" class="vs-method-toggle" data-api-method="POST" aria-pressed="false">POST</button>
                        </div>
                        <p class="vs-form-hint">可同时选择 GET 与 POST。</p>
                    </div>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label">接口类型</label>
                    <div class="vs-api-type-tabs" id="apiListTypeTabs" role="tablist">
                        <button type="button" class="vs-btn vs-btn--primary vs-api-type-tab" data-apitype="0">本地接口</button>
                        <button type="button" class="vs-btn vs-btn--default vs-api-type-tab" data-apitype="1">代理外链</button>
                    </div>
                    <input type="hidden" id="apiListFormApiType" name="apitype" value="0">
                    <p class="vs-form-hint" id="apiListTypeHint">本地接口：只填本站路径，如 /api/img/index.php</p>
                </div>
                <div class="vs-form-row" id="apiListEndpointRow">
                    <label class="vs-label" for="apiListFormEndpoint" id="apiListEndpointLabel">本地路径 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormEndpoint" name="endpoint" maxlength="500" required
                           placeholder="/api/img/index.php">
                </div>
                <div class="vs-form-row" id="apiListTargetRow" hidden>
                    <label class="vs-label" for="apiListFormTargetUrl">上游完整地址 <span class="vs-req">*</span></label>
                    <input type="url" class="vs-input" id="apiListFormTargetUrl" name="targeturl" maxlength="500"
                           placeholder="https://api.example.com/v1/demo">
                    <p class="vs-form-hint">访问本站公开地址时，将转发到该上游（需密钥时由本站代为附加，不会暴露给调用方）。</p>
                </div>
                <div class="vs-form-row" id="apiListSlugRow" hidden>
                    <label class="vs-label" for="apiListFormProxySlug">接口短码 <span class="vs-req">*</span></label>
                    <input type="text" class="vs-input" id="apiListFormProxySlug" name="proxyslug" maxlength="64"
                           placeholder="例如 sjspks（3～64 位字母或数字）" pattern="[A-Za-z0-9]{3,64}" autocomplete="off">
                    <p class="vs-form-hint">公开地址形如 <?php echo vs_e(rtrim(vs_base_url(), '/')); ?>/apis/短码</p>
                </div>
                <div id="apiListClientProfileBlock">
                    <div class="vs-form-row vs-form-row--2">
                        <div>
                            <label class="vs-label" for="apiListFormUpUaMode">出站 User-Agent</label>
                            <select class="vs-input vs-select" id="apiListFormUpUaMode" name="upuamode" data-vs-pick>
                                <option value="0">系统默认</option>
                                <option value="1">内置设备 / 浏览器</option>
                                <option value="2">自定义</option>
                                <option value="3">轮询内置（按分钟）</option>
                            </select>
                        </div>
                        <div>
                            <label class="vs-label" for="apiListFormUpRefererMode">出站 Referer</label>
                            <select class="vs-input vs-select" id="apiListFormUpRefererMode" name="upreferermode" data-vs-pick>
                                <option value="0">不发送</option>
                                <option value="1">自定义</option>
                                <option value="2">转发客户端</option>
                            </select>
                        </div>
                    </div>
                    <p class="vs-form-hint">本地与代理均可配置。代理：网关中继上游时带上；本地：脚本内用 <code>ApiStats::outboundHeaders()</code> 读取。UA 系统默认=本站标识；Referer：不发送 / 自定义 / 转发调用方。</p>
                    <div class="vs-form-row" id="apiListUpUaPresetWrap" hidden>
                        <label class="vs-label" for="apiListFormUpUaPreset">内置 UA 预设</label>
                        <select class="vs-input vs-select" id="apiListFormUpUaPreset" name="upuapreset" data-vs-pick>
                            <option value="">请选择</option>
                            <?php foreach (ProxyClientProfile::presetOptions() as $opt): ?>
                                <option value="<?php echo vs_e($opt['value']); ?>"><?php echo vs_e($opt['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vs-form-row" id="apiListUpUaCustomWrap" hidden>
                        <label class="vs-label" for="apiListFormUpUa">自定义 User-Agent</label>
                        <input type="text" class="vs-input" id="apiListFormUpUa" name="upua" maxlength="512"
                               placeholder="完整浏览器 User-Agent 字符串" autocomplete="off">
                    </div>
                    <div class="vs-form-row" id="apiListUpRefererWrap" hidden>
                        <label class="vs-label" for="apiListFormUpReferer">自定义 Referer</label>
                        <input type="url" class="vs-input" id="apiListFormUpReferer" name="upreferer" maxlength="500"
                               placeholder="https://example.com/" autocomplete="off">
                    </div>
                </div>
                <div id="apiListUpAuthBlock" hidden>
                    <div id="apiListUpKeyViaWrap" hidden>
                        <input type="hidden" id="apiListFormUpKeyVia" name="upkeyvia" value="0">
                    </div>
                    <div class="vs-form-row vs-form-row--2">
                        <div>
                            <label class="vs-label" for="apiListFormUpMethod">上游请求方式</label>
                            <select class="vs-input vs-select" id="apiListFormUpMethod" name="upmethod" data-vs-pick>
                                <option value="0">GET</option>
                                <option value="1">POST</option>
                            </select>
                        </div>
                        <div>
                            <label class="vs-label" for="apiListFormUpAuth">上游认证方式</label>
                            <select class="vs-input vs-select" id="apiListFormUpAuth" name="upauth" data-vs-pick>
                                <option value="0">无需认证</option>
                                <option value="1">Query API Key</option>
                                <option value="3">Header API Key</option>
                                <option value="2">Bearer Token</option>
                            </select>
                        </div>
                    </div>
                    <p class="vs-form-hint">上游请求方式：中继真正打向上游的方法（可与上方调用方「请求方式」不同）。例：调用方用 GET，上游只收 POST 时选 POST。</p>
                    <div class="vs-form-row vs-form-row--2" id="apiListUpKeyFields" hidden>
                        <div id="apiListUpKeyNameWrap">
                            <label class="vs-label" for="apiListFormUpKeyName">参数名 / 头名称</label>
                            <input type="text" class="vs-input" id="apiListFormUpKeyName" name="upkeyname" maxlength="64"
                                   placeholder="如 api_key 或 X-API-Key" autocomplete="off">
                        </div>
                        <div>
                            <label class="vs-label" for="apiListFormUpKey">上游密钥 <span class="vs-req">*</span></label>
                            <input type="password" class="vs-input" id="apiListFormUpKey" name="upkey" maxlength="500"
                                   placeholder="上游平台颁发的密钥或令牌" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="vs-form-row" id="apiListJsonRewriteBlock">
                        <label class="vs-label">JSON 字段改写</label>
                        <label class="vs-check" for="apiListFormJsonRewriteOn">
                            <input type="checkbox" id="apiListFormJsonRewriteOn" value="1">
                            <span>启用（只改上游返回的 JSON；TXT / 图片 / 视频 / 跳转不改）</span>
                        </label>
                        <div class="vs-json-rewrite-help">
                            <strong>怎么填「要改的字段」？</strong>
                            <ol>
                                <li>打开上游返回的 JSON，找到要改的那一层字段名。</li>
                                <li>用英文句点 <code>.</code> 把层层字段名串起来，例如要改 <code>api_info</code> 里面的 <code>developer</code>，就填 <code>api_info.developer</code>。</li>
                                <li>选「设置」= 改成你填的值（没有就新建）；选「删除」= 去掉这个字段。最多 40 条。</li>
                            </ol>
                            <div class="vs-json-rewrite-help__eg">示例：上游 {"api_info":{"developer":"别人"}}
要改成你的名字 → 字段填 api_info.developer，操作选「设置」，值填 尋鯨錄。
禁止把本站管理后台地址（含 /admin）写进改写值。
禁止把数据库账号、密码、密钥等敏感值填进改写；业务错误响应不会应用改写。</div>
                        </div>
                        <input type="hidden" id="apiListFormJsonRewrite" name="jsonrewrite" value="">
                        <div class="vs-json-rewrite" id="apiListJsonRewriteEditor" hidden>
                            <div class="vs-json-rewrite__head">
                                <span>要改的字段</span>
                                <span>操作</span>
                                <span>新值（设置时填；可写普通文字或 JSON）</span>
                                <span></span>
                            </div>
                            <div class="vs-json-rewrite__rows" id="apiListJsonRewriteRows"></div>
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListJsonRewriteAdd">添加规则</button>
                        </div>
                    </div>
                    <p class="vs-form-hint">本站先请求上游，再把结果交给调用方。上游认证与 JSON 改写仅代理可用；出站 UA / Referer 见上方，不会写进对外文档。</p>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormCategory">所属分类</label>
                        <select class="vs-input vs-select" id="apiListFormCategory" name="category" data-vs-pick>
                            <option value="">未分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo vs_e($cat['name']); ?>"><?php echo vs_e($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="vs-label" for="apiListFormRequireKey">是否需要密钥</label>
                        <select class="vs-input vs-select" id="apiListFormRequireKey" name="needkey" data-vs-pick>
                            <option value="0">无需 KEY</option>
                            <option value="1">KEY 必填</option>
                            <option value="2">KEY 可选</option>
                        </select>
                    </div>
                </div>
                <div class="vs-form-row" id="apiListKeywaysRow">
                    <label class="vs-label">鉴权传递方式</label>
                    <div class="vs-method-toggles" id="apiListFormKeywayChecks" role="group" aria-label="鉴权传递方式">
                        <button type="button" class="vs-method-toggle is-on" data-api-keyway="query" aria-pressed="true">Query 参数</button>
                        <button type="button" class="vs-method-toggle" data-api-keyway="header" aria-pressed="false">Header</button>
                        <button type="button" class="vs-method-toggle" data-api-keyway="bearer" aria-pressed="false">Bearer</button>
                    </div>
                    <p class="vs-form-hint">可同时勾选多种方式；默认 Query。选中几种前台就列出几种（Query / Header / Bearer），不会显示「全部支持」。</p>
                </div>
                <div class="vs-form-row vs-form-row--2">
                    <div>
                        <label class="vs-label" for="apiListFormQpm">QPM 每分钟上限</label>
                        <input type="number" class="vs-input" id="apiListFormQpm" name="qpm" min="0" max="1000000" step="1" value="0" placeholder="0 表示不限制">
                    </div>
                    <div>
                        <label class="vs-label" for="apiListFormCharge">是否收费</label>
                        <select class="vs-input vs-select" id="apiListFormCharge" name="charge" data-vs-pick>
                            <option value="0">免费</option>
                            <option value="1">收费</option>
                        </select>
                    </div>
                </div>
                <div class="vs-form-row vs-form-row--2" id="apiListPriceRow" hidden>
                    <div>
                        <label class="vs-label" for="apiListFormPrice">每次扣除积分</label>
                        <input type="number" class="vs-input" id="apiListFormPrice" name="price" min="0.0001" step="0.0001" placeholder="如 0.1 或 1">
                    </div>
                    <div></div>
                </div>
                <p class="vs-form-hint">「无需 KEY」与「KEY 可选」调用规则相同；选「无需 KEY」时前台通常不展示密钥填写框，「KEY 可选」会展示可空输入。QPM 填 0 不限制；大于 0 为每分钟最大请求数（无需/可选密钥按 IP，必填密钥按 IP+密钥）。本页发布的接口默认审核通过。收费接口调用时须提供有效密钥且余额足够。</p>
                <div class="vs-form-row">
                    <label class="vs-label">接口图标</label>
                    <div class="vs-api-cat-icon-picker" id="apiListIconPicker" role="listbox" aria-label="选择本地 SVG 图标"></div>
                    <label class="vs-label vs-api-cat-icon-url-label" for="apiListIconUrl">或填写图标链接</label>
                    <input type="url" class="vs-input" id="apiListIconUrl" name="icon"
                           placeholder="https://example.com/icon.png" maxlength="255">
                    <p class="vs-form-hint">点选下方图标，或填写图片链接地址。自定义本地图标：把 <code>.svg</code> 文件放到站点目录 <code>assets/img/category-icons/</code>（建议用数字文件名，如 <code>99.svg</code>），刷新本页后会自动出现在可选列表中。</p>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="params" hidden>
                <div class="vs-form-row">
                    <label class="vs-label">请求参数</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormParams" name="params" hidden aria-hidden="true"></textarea>
                    <div class="vs-params-editor" id="apiListParamsEditor" data-hidden-id="apiListFormParams"></div>
                </div>
                <div class="vs-form-row">
                    <label class="vs-label" for="apiListFormResponse">返回参数示例</label>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormResponse" name="response" rows="8"
                              placeholder='{"code":1,"msg":"ok","data":{}}'></textarea>
                    <p class="vs-form-hint">返回示例保持 JSON 文本填写即可。</p>
                </div>
            </div>

            <div class="vs-api-list-form-pane" data-api-form-pane="docs" hidden>
                <div class="vs-form-row">
                    <div class="vs-ai-gen-banner" id="apiListAiBanner" hidden data-ai-banner="doc">
                        <span class="vs-ai-gen-banner__dot" aria-hidden="true"></span>
                        <span class="vs-ai-gen-banner__text" id="apiListAiBannerText">正在生成…</span>
                        <span class="vs-ai-gen-banner__time" id="apiListAiBannerTime"></span>
                    </div>
                    <div class="vs-api-doc-head">
                        <label class="vs-label" for="apiListFormDocNormal">详细文档（Markdown）</label>
                        <div class="vs-api-doc-head__actions">
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiDocBtn"
                                    title="按章节生成详细文档">AI 生成详细文档</button>
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiDocContinueBtn" hidden
                                    title="从中断章节继续">继续生成</button>
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiChatClearBtn"
                                    title="清除本接口短时效对话（约 10 分钟；保存接口时会全部清空）">清除对话</button>
                        </div>
                    </div>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocNormal" name="doc" rows="10"
                              data-vs-md="off" placeholder="面向调用方的详细说明…"></textarea>
                    <p class="vs-form-hint">按章节自动生成详细文档；偶尔中断会自动重试一次，仍失败可点「继续生成」。勿写入上游地址或密钥。</p>
                    <details class="vs-ai-term" id="apiListAiTermDoc" data-ai-term="doc">
                        <summary class="vs-ai-term__summary">AI 编写进程（详细文档）</summary>
                        <pre class="vs-ai-term__log font-mono" id="apiListAiTermDocLog">尚未开始生成。</pre>
                    </details>
                </div>
                <div class="vs-form-row">
                    <div class="vs-ai-gen-banner" id="apiListAiBannerCode" hidden data-ai-banner="code">
                        <span class="vs-ai-gen-banner__dot" aria-hidden="true"></span>
                        <span class="vs-ai-gen-banner__text" id="apiListAiBannerCodeText">正在生成…</span>
                        <span class="vs-ai-gen-banner__time" id="apiListAiBannerCodeTime"></span>
                    </div>
                    <div class="vs-api-doc-head">
                        <label class="vs-label" for="apiListFormDocAi">代码示例（:::qs 多语言）</label>
                        <div class="vs-api-doc-head__actions">
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiCodeBtn"
                                    title="按已选鉴权×9 语言一键生成（最多 27 片）">AI 生成代码示例</button>
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiCodeRetryBtn" hidden
                                    title="只重试上次失败的片">重试失败</button>
                            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" id="apiListAiCodeClearBtn"
                                    title="清空代码示例框与进程日志">清空示例</button>
                        </div>
                    </div>
                    <div class="vs-api-ai-code-ways" id="apiListAiCodeWays" hidden>
                        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="query" hidden
                                title="仅生成 Query 鉴权下 9 种语言">生成 Query</button>
                        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="header" hidden
                                title="仅生成 Header 鉴权下 9 种语言">生成 Header</button>
                        <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-ai-code-auth="bearer" hidden
                                title="仅生成 Bearer 鉴权下 9 种语言">生成 Bearer</button>
                    </div>
                    <textarea class="vs-input vs-textarea vs-api-list-code" id="apiListFormDocAi" name="aidoc" rows="10"
                              data-vs-md="off" placeholder=":::qs lang=curl&#10;...&#10;:::&#10;&#10;:::qs lang=python&#10;...&#10;:::"></textarea>
                    <p class="vs-form-hint">须使用 :::qs lang=语言标识 包裹。主按钮一键生成已选鉴权×9 语言（最多 27 片）；下方可按鉴权单独生成 9 片（会合并保留其它鉴权块）。失败可点「重试失败」。</p>
                    <details class="vs-ai-term" id="apiListAiTermCode" data-ai-term="code">
                        <summary class="vs-ai-term__summary">AI 编写进程（代码示例）</summary>
                        <pre class="vs-ai-term__log font-mono" id="apiListAiTermCodeLog">尚未开始生成。</pre>
                    </details>
                </div>
            </div>
        </form>
        <footer class="vs-overlay__foot">
            <span class="vs-api-draft-hint" id="apiListDraftHint" hidden>已自动保存</span>
            <button type="button" class="vs-btn vs-btn--default" data-overlay-close="1">取消</button>
            <button type="submit" form="apiListForm" class="vs-btn vs-btn--primary" id="apiListFormSubmitBtn">保存</button>
        </footer>
    </div>
</div>

<?php
echo Markdown::renderAssetsHtml();
?>
<script>window.VS_AI_CODE=<?php echo json_encode(AiConfig::codeClientOptions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<?php
vs_admin_layout_end(array('vs-pick.js', 'icon-picker.js', 'api-params-editor.js', 'vs-syntax.js', 'api-list.js'));
?>
