<?php
/**
 * 文件：admin/ops/mail.php
 * 作用：运营中心 · 邮件群发（双 Tab：群发 + 发送记录）
 */

require_once dirname(__DIR__) . '/init.php';

/**
 * @param array $item
 * @return array
 */
function vs_ops_mail_log_ctx(array $item)
{
    $status = isset($item['status']) ? (int) $item['status'] : 0;
    $ok = isset($item['oknum']) ? (int) $item['oknum'] : 0;
    $fail = isset($item['failnum']) ? (int) $item['failnum'] : 0;
    $total = isset($item['total']) ? (int) $item['total'] : 0;
    $resultLabel = '发送中';
    $resultClass = 'vs-badge--default';
    if ($status === MailLogManager::STATUS_DONE) {
        if ($fail <= 0) {
            $resultLabel = '全部成功';
            $resultClass = 'vs-badge--success';
        } elseif ($ok <= 0) {
            $resultLabel = '全部失败';
            $resultClass = 'vs-badge--error';
        } else {
            $resultLabel = '部分失败';
            $resultClass = 'vs-badge--warning';
        }
    } elseif ($status === MailLogManager::STATUS_ABORT) {
        $resultLabel = '已中断';
        $resultClass = 'vs-badge--warning';
    }
    return array(
        'id'           => (int) (isset($item['id']) ? $item['id'] : 0),
        'tplname'      => isset($item['tplname']) ? (string) $item['tplname'] : '',
        'subject'      => isset($item['subject']) ? (string) $item['subject'] : '',
        'filtersum'    => isset($item['filtersum']) ? (string) $item['filtersum'] : '',
        'total'        => $total,
        'oknum'        => $ok,
        'failnum'      => $fail,
        'status'       => $status,
        'status_label' => isset($item['status_label'])
            ? (string) $item['status_label']
            : MailLogManager::statusLabel($status),
        'result_label' => $resultLabel,
        'result_class' => $resultClass,
        'admin_name'   => isset($item['admin_name']) && $item['admin_name'] !== ''
            ? (string) $item['admin_name']
            : '—',
        'createtime'   => isset($item['createtime']) ? (string) $item['createtime'] : '',
        'finishtime'   => isset($item['finishtime']) ? (string) $item['finishtime'] : '',
    );
}

/**
 * 发送记录一行（对齐积分变动 / 订单：基础信息，无明细入口）
 *
 * @param array $ctx
 * @return void
 */
function vs_render_ops_mail_log_item(array $ctx)
{
    $title = $ctx['subject'] !== ''
        ? $ctx['subject']
        : ($ctx['tplname'] !== '' ? $ctx['tplname'] : '群发');
    $total = (int) $ctx['total'];
    $ok = (int) $ctx['oknum'];
    $fail = (int) $ctx['failnum'];
    ?>
    <article class="vs-finance-row vs-finance-row--maillog" data-maillog-row="<?php echo (int) $ctx['id']; ?>">
        <div class="vs-finance-cell vs-finance-cell--stack vs-finance-c-title">
            <strong class="vs-finance-user"><?php echo vs_e($title); ?></strong>
        </div>
        <div class="vs-finance-cell vs-finance-c-status">
            <span class="vs-badge <?php echo vs_e($ctx['result_class']); ?>"><?php echo vs_e($ctx['result_label']); ?></span>
        </div>
        <div class="vs-finance-cell vs-finance-cell--stack vs-finance-c-count">
            <span><?php echo $total; ?> 人</span>
            <?php if ($fail > 0 || ($total > 0 && $ok !== $total)): ?>
                <span class="vs-finance-sub">成功 <?php echo $ok; ?> · 失败 <?php echo $fail; ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-finance-cell vs-finance-c-time vs-finance-time">
            <?php echo vs_e($ctx['createtime'] !== '' ? $ctx['createtime'] : '—'); ?>
        </div>
    </article>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($action === 'gift_filter_start' || $action === 'gift_filter_tick'
        || $action === 'mail_start' || $action === 'mail_tick' || $action === 'mail_cancel'
        || $action === 'log_list'
        || $action === 'user_search' || $action === 'tpl_get') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    $mailFilterScope = class_exists('IncentiveManager')
        ? IncentiveManager::FILTER_SCOPE_MAIL
        : 'mail';

    if ($action === 'gift_filter_tick') {
        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        $result = IncentiveManager::tickFilter((int) Auth::id(), $token, $mailFilterScope);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '筛选推进失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : 'ok',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'gift_filter_start') {
        $norm = IncentiveManager::normalizeFilter($_POST);
        if (empty($norm['ok'])) {
            AjaxResponse::error(isset($norm['msg']) ? $norm['msg'] : '筛选条件无效');
        }
        $warnings = !empty($norm['warnings']) && is_array($norm['warnings']) ? $norm['warnings'] : array();
        $result = IncentiveManager::startFilter($norm['filter'], (int) Auth::id(), $warnings, $mailFilterScope);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '筛选失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : 'ok',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'mail_tick') {
        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        $result = OpsMailManager::tickSend((int) Auth::id(), $token);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '发送推进失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : 'ok',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'mail_cancel') {
        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        $result = OpsMailManager::cancelSend((int) Auth::id(), $token);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '中止失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : '已中止',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'mail_start') {
        $result = OpsMailManager::startSend(
            (int) Auth::id(),
            isset($_POST['filter_token']) ? (string) $_POST['filter_token'] : '',
            isset($_POST['tpl_mode']) ? (string) $_POST['tpl_mode'] : 'saved',
            isset($_POST['tpl_id']) ? (int) $_POST['tpl_id'] : 0,
            isset($_POST['temp_subject']) ? (string) $_POST['temp_subject'] : '',
            isset($_POST['temp_body']) ? (string) $_POST['temp_body'] : '',
            isset($_POST['filtersum']) ? (string) $_POST['filtersum'] : '',
            isset($_POST['confirm_text']) ? (string) $_POST['confirm_text'] : ''
        );
        if (empty($result['ok'])) {
            $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
            if (!empty($data['need_confirm'])) {
                AjaxResponse::json(array_merge(array(
                    'code' => 0,
                    'msg'  => isset($result['msg']) ? $result['msg'] : '需要确认',
                ), $data));
            }
            AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '启动失败');
        }
        AjaxResponse::success(
            isset($result['msg']) ? $result['msg'] : 'ok',
            isset($result['data']) ? $result['data'] : array()
        );
    }

    if ($action === 'log_list') {
        if (!MailLogManager::tableReady()) {
            AjaxResponse::error('发送记录表尚未就绪');
        }
        if (method_exists('MailLogManager', 'abortStaleRunning')) {
            $staleSec = class_exists('OpsMailManager') ? OpsMailManager::STALE_SECONDS : 900;
            MailLogManager::abortStaleRunning($staleSec);
        }
        $paged = MailLogManager::listPaged(array(
            'q'        => isset($_POST['q']) ? (string) $_POST['q'] : '',
            'status'   => isset($_POST['status']) ? (int) $_POST['status'] : -1,
            'page'     => isset($_POST['page']) ? (int) $_POST['page'] : 1,
            'pagesize' => isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20,
        ));
        ob_start();
        foreach ($paged['list'] as $item) {
            vs_render_ops_mail_log_item(vs_ops_mail_log_ctx($item));
        }
        $listHtml = ob_get_clean();
        AjaxResponse::success('ok', array(
            'list_html' => $listHtml,
            'total'     => (int) $paged['total'],
            'page'      => (int) $paged['page'],
            'pagesize'  => (int) $paged['pagesize'],
        ));
    }

    if ($action === 'user_search') {
        $q = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $page = max(1, isset($_POST['page']) ? (int) $_POST['page'] : 1);
        $pagesize = 20;
        $list = array();
        $total = 0;
        try {
            $pdo = Database::connect();
            $where = '1=1';
            $bind = array();
            if ($q !== '') {
                $like = function_exists('vs_sql_like_contains')
                    ? vs_sql_like_contains($q)
                    : ('%' . addcslashes($q, "\\%_") . '%');
                $where = '(u.`username` LIKE ? ESCAPE \'\\\\\' OR u.`email` LIKE ? ESCAPE \'\\\\\')';
                $bind[] = $like;
                $bind[] = $like;
            }
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM `' . Database::table('user') . '` u WHERE ' . $where);
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            $offset = (int) (($page - 1) * $pagesize);
            $sql = 'SELECT u.`id`, u.`username`, u.`email`, u.`avatar`, u.`status`
                    FROM `' . Database::table('user') . '` u
                    WHERE ' . $where . '
                    ORDER BY u.`id` DESC LIMIT ' . $pagesize . ' OFFSET ' . $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $email = isset($r['email']) ? trim((string) $r['email']) : '';
                $avatar = '';
                if (class_exists('UserAvatar')) {
                    $avatar = UserAvatar::resolve(array(
                        'id'       => (int) $r['id'],
                        'email'    => $email,
                        'avatar'   => isset($r['avatar']) ? (string) $r['avatar'] : '',
                    ));
                } elseif (isset($r['avatar'])) {
                    $avatar = (string) $r['avatar'];
                }
                $list[] = array(
                    'id'       => (int) $r['id'],
                    'username' => (string) $r['username'],
                    'email'    => $email,
                    'avatar'   => $avatar,
                    'has_mail' => ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? 1 : 0,
                );
            }
        } catch (Exception $e) {
            AjaxResponse::error('搜索用户失败');
        }
        AjaxResponse::success('ok', array(
            'list'     => $list,
            'total'    => $total,
            'page'     => $page,
            'pagesize' => $pagesize,
        ));
    }

    if ($action === 'tpl_get') {
        $id = isset($_POST['tpl_id']) ? (int) $_POST['tpl_id'] : 0;
        $row = ContentManager::findById($id);
        if (!$row || ContentManager::normalizeKind($row['kind']) !== ContentManager::KIND_MAILTPL) {
            AjaxResponse::error('模板不存在');
        }
        $fmt = ContentManager::formatRow($row);
        if ((int) $fmt['bindpage'] !== 0 || (int) $fmt['status'] !== ContentManager::STATUS_PUBLISHED) {
            AjaxResponse::error('该模板不可用于群发');
        }
        if (class_exists('MailNotify') && isset($fmt['body'])) {
            $fmt['body'] = MailNotify::sanitizeMailHtml((string) $fmt['body']);
        }
        AjaxResponse::success('ok', array('item' => $fmt));
    }

    AjaxResponse::error('无效操作', 400);
}

$mailReady = Config::isMailEnabled();
$redisOk = class_exists('RedisCache') && RedisCache::enabled();
$contentReady = ContentManager::tableReady();
$logReady = class_exists('MailLogManager') && MailLogManager::tableReady();
$templates = ($contentReady && method_exists('ContentManager', 'listCustomMailTemplates'))
    ? ContentManager::listCustomMailTemplates()
    : array();
$logPaged = $logReady ? MailLogManager::listPaged(array('page' => 1, 'pagesize' => 20)) : array(
    'list' => array(), 'total' => 0,
);
$logTotal = (int) $logPaged['total'];
$logItems = $logPaged['list'];

vs_admin_layout_start('邮件群发', 'ops-mail');
?>
<div class="vs-link-page vs-ops-mail-page" id="opsMailPage"
     data-mail-ready="<?php echo $mailReady ? '1' : '0'; ?>"
     data-redis-ok="<?php echo $redisOk ? '1' : '0'; ?>"
     data-log-ready="<?php echo $logReady ? '1' : '0'; ?>"
     data-log-total="<?php echo (int) $logTotal; ?>"
     data-active-tab="send">

    <div class="vs-link-page__tabs-shell">
        <div class="vs-tabs vs-api-review-tabs" id="opsMailTabs" role="tablist" aria-label="邮件群发">
            <button type="button" class="vs-tabs__btn is-active" data-tab="send" role="tab" aria-selected="true">群发</button>
            <button type="button" class="vs-tabs__btn" data-tab="logs" role="tab" aria-selected="false">发送记录</button>
        </div>
    </div>

    <div class="vs-ops-mail-pane" id="opsMailPaneSend" data-pane="send">
        <?php if (!$mailReady): ?>
            <?php vs_render_notice('warning', '邮箱未就绪', '请先在系统设置中配置 SMTP 发信。', array('compact' => true)); ?>
        <?php endif; ?>
        <?php if (!$redisOk): ?>
            <?php vs_render_notice('warning', '需要 Redis', '筛选与分片发送依赖 Redis，请先在系统设置中启用。', array('compact' => true)); ?>
        <?php endif; ?>
        <?php if (!$contentReady): ?>
            <?php vs_render_notice('warning', '尚未就绪', '请先在系统升级中执行数据库结构更新。', array('compact' => true)); ?>
        <?php else: ?>
            <?php vs_render_notice('tip', '', '仅可选用自定义模板（系统通知模板不可群发）。筛选体系与「用户激励」相同；指定用户请点选，勿手填编号。', array('compact' => true)); ?>

            <form class="vs-form vs-ops-mail-form vs-ops-mail-form--split" id="opsMailSendForm" onsubmit="return false;">
                <div class="vs-ops-mail-col vs-ops-mail-col--editor">
                    <div class="vs-field">
                        <label class="vs-label" for="opsMailTplSelect">邮件模板</label>
                        <div class="vs-ops-mail-tplrow">
                            <select class="vs-input vs-select" id="opsMailTplSelect" name="tpl_id" data-vs-pick<?php echo $redisOk && $mailReady ? '' : ' disabled'; ?>>
                                <option value="0">请选择自定义模板</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo (int) $tpl['id']; ?>"><?php echo vs_e($tpl['title']); ?></option>
                                <?php endforeach; ?>
                                <option value="temp">临时撰写…</option>
                            </select>
                        </div>
                        <?php if (empty($templates)): ?>
                            <p class="vs-form-hint">当前暂无已发布的自定义模板，请前往「系统管理 → 邮箱模板」创建后再选，或使用下方「临时撰写」。</p>
                        <?php endif; ?>
                    </div>

                    <div class="vs-ops-mail-preview" id="opsMailTplPreview" hidden>
                        <div class="vs-field">
                            <label class="vs-label">发信标题预览</label>
                            <p class="vs-ops-mail-preview__subject" id="opsMailPreviewSubject">—</p>
                        </div>
                        <div class="vs-field">
                            <label class="vs-label">正文预览</label>
                            <div class="vs-ops-mail-preview__body" id="opsMailPreviewBody"></div>
                            <p class="vs-form-hint">占位符将按每位收件人替换。</p>
                        </div>
                    </div>

                    <div class="vs-ops-mail-temp" id="opsMailTempWrap" hidden>
                        <div class="vs-field">
                            <label class="vs-label" for="opsMailTempSubject">发信标题</label>
                            <div class="vs-mailtpl-phbar" id="opsMailTempPhBar" data-ph-targets="opsMailTempSubject,opsMailTempBody">
                                <?php
                                $opsPhCommon = class_exists('MailChannels')
                                    ? MailChannels::commonPlaceholderKeys()
                                    : array('用户名', '站点名', '站点名称', '站点链接', '日期', '时间', '余额', '用户头像');
                                foreach ($opsPhCommon as $phKey) {
                                    $phKey = trim((string) $phKey);
                                    if ($phKey === '') {
                                        continue;
                                    }
                                    $phToken = '{{' . $phKey . '}}';
                                    echo '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline" data-ph="'
                                        . vs_e($phToken) . '">' . vs_e($phToken) . '</button>';
                                }
                                ?>
                            </div>
                            <p class="vs-form-hint">群发仅公共占位符（按收件人替换）。接口名/评论/令牌等场景值请到「邮箱模板」绑定对应系统场景使用。</p>
                            <input class="vs-input" type="text" id="opsMailTempSubject" maxlength="500" placeholder="例：你好，{{用户名}}">
                        </div>
                        <div class="vs-field">
                            <label class="vs-label" for="opsMailTempBody">邮件正文（HTML）</label>
                            <textarea class="vs-input vs-textarea" id="opsMailTempBody" rows="10" placeholder="HTML；可插入 {{用户名}} {{站点名}} 等"></textarea>
                        </div>
                    </div>
                </div>

                <div class="vs-ops-mail-col vs-ops-mail-col--action">
                    <div class="vs-incentive-toolbar vs-ops-mail-toolbar">
                        <div class="vs-incentive-toolbar__filterline">
                            <div class="vs-incentive-toolbar__tags">
                                <label class="vs-label vs-incentive-toolbar__label" for="opsMailTagTrigger">筛选条件</label>
                                <div class="vs-incentive-tagfield" id="opsMailTagField">
                                    <button type="button" class="vs-incentive-tagfield__trigger" id="opsMailTagTrigger" aria-haspopup="dialog" aria-expanded="false"<?php echo $redisOk ? '' : ' disabled'; ?>>
                                        <span class="vs-incentive-tagfield__chips" id="opsMailSelectedChips"></span>
                                        <span class="vs-incentive-tagfield__placeholder" id="opsMailTagPlaceholder">选择筛选标签（可多选）</span>
                                        <span class="vs-icon vs-icon--chevron" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="vs-btn vs-btn--secondary vs-incentive-filter-btn" id="opsMailFilterBtn"<?php echo $redisOk ? '' : ' disabled title="需要 Redis"'; ?>>筛选</button>
                        </div>
                        <div class="vs-ops-mail-picked" id="opsMailPickedUsers" hidden>
                            <span class="vs-label">已选用户</span>
                            <div class="vs-ops-mail-picked__chips" id="opsMailPickedChips"></div>
                        </div>
                        <button type="button" class="vs-btn vs-btn--primary vs-incentive-exec-btn" id="opsMailSendBtn"<?php echo ($redisOk && $mailReady) ? '' : ' disabled'; ?>>确认发送</button>
                    </div>

                    <div class="vs-incentive-tip" id="opsMailTipBanner" hidden role="status"></div>
                    <div class="vs-incentive-progress" id="opsMailProgressWrap" hidden>
                        <div class="vs-incentive-progress__bar">
                            <div class="vs-incentive-progress__fill" id="opsMailProgressFill"></div>
                        </div>
                        <div class="vs-incentive-statusline" id="opsMailStatusLine">
                            <span class="vs-incentive-statusline__prog" id="opsMailProgressText"></span>
                            <span class="vs-incentive-statusline__detail" id="opsMailFilterText" hidden></span>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="vs-ops-mail-pane" id="opsMailPaneLogs" data-pane="logs" hidden>
        <?php if (!$logReady): ?>
            <?php vs_render_notice('warning', '尚未就绪', '发送记录表尚未就绪，请先系统升级。', array('compact' => true)); ?>
        <?php else: ?>
            <div class="vs-search-bar vs-api-list-toolbar">
                <div class="vs-search-bar__input-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" class="vs-input vs-search-bar__input" id="opsMailLogSearch" placeholder="搜索模板名或发信标题..." autocomplete="off">
                </div>
            </div>
            <div class="vs-api-list-empty vs-api-list-empty--hero" id="opsMailLogEmpty"<?php echo $logTotal > 0 ? ' hidden' : ''; ?>>
                <div class="vs-api-list-empty__card">
                    <h3 class="vs-api-list-empty__title">暂无发送记录</h3>
                    <p class="vs-api-list-empty__desc">完成一次群发后，批次记录会出现在这里。</p>
                </div>
            </div>
            <?php /* 勿用 vs-api-list-table-wrap：手机端双 DOM 规则会 display:none，流水行无独立手机 DOM 会整页空白（E461） */ ?>
            <div class="vs-api-list-table-card vs-ops-mail-log-wrap" id="opsMailLogTableWrap"<?php echo $logTotal > 0 ? '' : ' hidden'; ?>>
                <div class="vs-finance-grid vs-ops-mail-log-grid">
                    <div class="vs-finance-row vs-finance-row--maillog vs-finance-row--head" aria-hidden="true">
                        <div class="vs-finance-cell">发信标题</div>
                        <div class="vs-finance-cell">状态</div>
                        <div class="vs-finance-cell">人数</div>
                        <div class="vs-finance-cell">时间</div>
                    </div>
                    <div class="vs-ops-mail-log-list" id="opsMailLogList">
                        <?php foreach ($logItems as $item): ?>
                            <?php vs_render_ops_mail_log_item(vs_ops_mail_log_ctx($item)); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php vs_render_list_pager(array('id' => 'opsMailLog', 'hidden' => $logTotal === 0, 'total' => $logTotal)); ?>
        <?php endif; ?>
    </div>
</div>

<div class="vs-incentive-tagpop" id="opsMailTagPop" hidden>
    <div class="vs-incentive-tagpop__backdrop" id="opsMailTagPopBackdrop"></div>
    <div class="vs-incentive-tagpop__panel" role="dialog" aria-label="筛选标签">
        <div class="vs-incentive-tagpop__head">
            <span class="vs-incentive-tagpop__title">筛选标签（可多选）</span>
            <button type="button" class="vs-incentive-tagpop__close" id="opsMailTagPopClose" aria-label="关闭">×</button>
        </div>
        <div class="vs-incentive-tagpop__body">
            <div class="vs-incentive-tagpop__grid" id="opsMailTagGrid"></div>
        </div>
        <div class="vs-incentive-tagpop__foot">
            <button type="button" class="vs-btn vs-btn--default" id="opsMailTagPopClear">清空</button>
            <button type="button" class="vs-btn vs-btn--primary" id="opsMailTagPopOk">完成</button>
        </div>
    </div>
</div>

<div class="vs-overlay vs-overlay--lg" id="opsMailUserPicker" hidden aria-hidden="true">
    <div class="vs-overlay__backdrop" data-user-picker-close="1"></div>
    <div class="vs-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="opsMailUserPickerTitle">
        <div class="vs-overlay__handle" aria-hidden="true"></div>
        <header class="vs-overlay__head">
            <h3 class="vs-overlay__title" id="opsMailUserPickerTitle">选择用户</h3>
            <button type="button" class="vs-overlay__close" data-user-picker-close="1" aria-label="关闭">&times;</button>
        </header>
        <div class="vs-overlay__body">
            <div class="vs-search-bar">
                <div class="vs-search-bar__input-wrap">
                    <input type="search" class="vs-input vs-search-bar__input" id="opsMailUserSearch" placeholder="搜索用户名或邮箱..." autocomplete="off">
                </div>
            </div>
            <div class="vs-ops-mail-userlist" id="opsMailUserList"></div>
            <p class="vs-form-hint" id="opsMailUserListHint">无有效邮箱的用户不可选。</p>
        </div>
        <footer class="vs-overlay__foot">
            <button type="button" class="vs-btn vs-btn--default" data-user-picker-close="1">取消</button>
            <button type="button" class="vs-btn vs-btn--primary" id="opsMailUserPickerOk">确定</button>
        </footer>
    </div>
</div>

<?php
vs_admin_layout_end(array('vs-pick.js', 'ops-mail.js'));
