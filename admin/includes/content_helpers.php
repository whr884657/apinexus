<?php
/**
 * 文件：admin/includes/content_helpers.php
 * 作用：公告/文章列表双 DOM 渲染（桌面表格 + 手机卡片；公告含站外+站内）
 */

/**
 * @param mixed $mode true|'announcement'|'message'|false|'article'
 * @return string announcement|article|message
 */
function vs_content_normalize_mode($mode)
{
    if ($mode === true || $mode === 1 || $mode === 'announcement') {
        return 'announcement';
    }
    if ($mode === 'message') {
        return 'message';
    }
    return 'article';
}

/**
 * @param mixed $status
 * @return string
 */
function vs_content_status_badge_class($status)
{
    $status = (int) $status;
    if ($status === ContentManager::STATUS_PUBLISHED) {
        return 'vs-badge--success';
    }
    if ($status === ContentManager::STATUS_OFF) {
        return 'vs-badge--error';
    }
    return 'vs-badge--default';
}

/**
 * @param array $item formatRow
 * @param mixed $mode
 * @return array
 */
function vs_content_row_ctx(array $item, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    $id = (int) $item['id'];
    $kind = isset($item['kind'])
        ? ContentManager::normalizeKind($item['kind'])
        : ($mode === 'message' ? ContentManager::KIND_MESSAGE : ContentManager::KIND_ANNOUNCEMENT);
    $kindLabel = isset($item['kind_label'])
        ? (string) $item['kind_label']
        : ContentManager::kindLabel($kind);
    $isSiteMsg = ($kind === ContentManager::KIND_MESSAGE);
    $title = isset($item['title']) ? (string) $item['title'] : '';
    $time = isset($item['createtime']) ? (string) $item['createtime'] : '';
    if ($time !== '' && strlen($time) >= 16) {
        $time = substr($time, 0, 16);
    }
    $username = isset($item['username']) ? trim((string) $item['username']) : '';
    if ($username === '' && !empty($item['userid'])) {
        $username = '用户#' . (int) $item['userid'];
    }
    if ($username === '') {
        $username = '—';
    }
    $avatar = isset($item['author_avatar']) ? (string) $item['author_avatar'] : '';
    $bindpage = isset($item['bindpage'])
        ? ContentManager::normalizeBindPage($item['bindpage'])
        : ContentManager::BIND_NONE;
    $audmode = isset($item['audmode'])
        ? ContentManager::normalizeAudMode($item['audmode'])
        : ContentManager::AUD_NONE;
    if ($isSiteMsg && $audmode === ContentManager::AUD_NONE) {
        $audmode = ContentManager::AUD_ALL;
    }
    $audLabel = $isSiteMsg
        ? (isset($item['audmode_label'])
            ? (string) $item['audmode_label']
            : ContentManager::audModeLabel($audmode))
        : '—';
    $search = mb_strtolower(
        $title . ' ' . $username . ' ' . $time . ' #' . $id
        . ' ' . $kindLabel
        . ($isSiteMsg ? (' ' . $audLabel) : ''),
        'UTF-8'
    );

    return array(
        'id'             => $id,
        'mode'           => $mode,
        'announcement'   => $mode === 'announcement',
        'kind'           => $kind,
        'kind_label'     => $kindLabel,
        'is_site_msg'    => $isSiteMsg ? 1 : 0,
        'title'          => $title,
        'summary'        => isset($item['summary']) ? (string) $item['summary'] : '',
        'body'           => isset($item['body']) ? (string) $item['body'] : '',
        'cover'          => isset($item['cover']) ? (string) $item['cover'] : '',
        'coverlayout'    => isset($item['coverlayout'])
            ? ContentManager::normalizeCoverLayout($item['coverlayout'])
            : ContentManager::COVER_LEFT,
        'status'         => isset($item['status']) ? (int) $item['status'] : ContentManager::STATUS_PUBLISHED,
        'status_label'   => isset($item['status_label']) ? (string) $item['status_label'] : '已发布',
        'bindpage'       => $bindpage,
        'bindpage_label' => isset($item['bindpage_label'])
            ? (string) $item['bindpage_label']
            : ContentManager::bindPageLabel($bindpage),
        'ispinned'       => isset($item['ispinned']) ? (int) $item['ispinned'] : 0,
        'ispopup'        => isset($item['ispopup']) ? (int) $item['ispopup'] : 0,
        'audmode'        => $audmode,
        'audmode_label'  => $audLabel,
        'views'          => isset($item['views']) ? (int) $item['views'] : 0,
        'username'       => $username,
        'author_avatar'  => $avatar,
        'time'           => $time,
        'search'         => $search,
    );
}

/**
 * @param array $ctx
 * @return string
 */
function vs_content_data_attrs(array $ctx)
{
    return ' data-content-row="' . (int) $ctx['id'] . '"'
        . ' data-search="' . vs_e($ctx['search']) . '"'
        . ' data-kind="' . (int) $ctx['kind'] . '"'
        . ' data-kind-label="' . vs_e($ctx['kind_label']) . '"'
        . ' data-title="' . vs_e($ctx['title']) . '"'
        . ' data-summary="' . vs_e($ctx['summary']) . '"'
        . ' data-body="' . vs_e($ctx['body']) . '"'
        . ' data-cover="' . vs_e($ctx['cover']) . '"'
        . ' data-coverlayout="' . (int) $ctx['coverlayout'] . '"'
        . ' data-status="' . (int) $ctx['status'] . '"'
        . ' data-status-label="' . vs_e($ctx['status_label']) . '"'
        . ' data-bindpage="' . (int) $ctx['bindpage'] . '"'
        . ' data-ispinned="' . (int) $ctx['ispinned'] . '"'
        . ' data-ispopup="' . (int) $ctx['ispopup'] . '"'
        . ' data-audmode="' . (int) $ctx['audmode'] . '"'
        . ' data-audmode-label="' . vs_e($ctx['audmode_label']) . '"'
        . ' data-views="' . (int) $ctx['views'] . '"'
        . ' data-username="' . vs_e($ctx['username']) . '"'
        . ' data-author-avatar="' . vs_e($ctx['author_avatar']) . '"'
        . ' data-createtime="' . vs_e($ctx['time']) . '"';
}

/**
 * @param array $ctx
 * @param mixed $mode
 * @return string
 */
function vs_content_title_html(array $ctx, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    $html = '<div class="content-title-cell">';
    $html .= '<span class="content-title-cell__text" data-field="title">' . vs_e($ctx['title']) . '</span>';
    if ($mode === 'article' && (int) $ctx['bindpage'] === ContentManager::BIND_ABOUT) {
        $html .= '<span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>';
    }
    if ($mode === 'article' && (int) $ctx['ispinned'] === 1) {
        $html .= '<span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @param mixed $mode
 * @return string
 */
function vs_content_actions_html(array $ctx, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    $isSiteMsg = !empty($ctx['is_site_msg']) || (int) $ctx['kind'] === ContentManager::KIND_MESSAGE;
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="edit">编辑</button>';
    if ($mode === 'announcement' || $mode === 'message') {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-content-act" data-act="pin">'
            . ((int) $ctx['ispinned'] === 1 ? '取消置顶' : '置顶') . '</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="popup">'
            . ((int) $ctx['ispopup'] === 1 ? '取消弹窗' : '设为弹窗') . '</button>';
    } else {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-content-act" data-act="pin">'
            . ((int) $ctx['ispinned'] === 1 ? '取消置顶' : '置顶') . '</button>';
    }
    if ($mode === 'article' || $mode === 'message' || ($mode === 'announcement' && $isSiteMsg)) {
        if ((int) $ctx['status'] === ContentManager::STATUS_PUBLISHED) {
            $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="hide">隐藏</button>';
        } else {
            $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-success vs-content-act" data-act="show">显示</button>';
        }
    }
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-danger vs-content-act" data-act="delete">删除</button>';
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @param mixed $mode
 * @return void
 */
function vs_render_content_desktop_row(array $ctx, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    $attrs = vs_content_data_attrs($ctx);
    ?>
    <tr<?php echo $attrs; ?>>
        <td>
            <?php echo vs_content_title_html($ctx, $mode); ?>
        </td>
        <?php if ($mode === 'announcement'): ?>
            <td>
                <span class="vs-badge <?php echo !empty($ctx['is_site_msg']) ? 'vs-badge--info' : 'vs-badge--default'; ?>" data-field="kind_label">
                    <?php echo vs_e($ctx['kind_label']); ?>
                </span>
            </td>
            <td>
                <span class="vs-badge <?php echo !empty($ctx['is_site_msg']) ? 'vs-badge--info' : 'vs-badge--default'; ?>" data-field="audmode_label">
                    <?php echo vs_e($ctx['audmode_label']); ?>
                </span>
            </td>
            <td><span class="content-time-cell" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
            <td>
                <span class="vs-badge <?php echo (int) $ctx['ispinned'] === 1 ? 'vs-badge--warning' : 'vs-badge--default'; ?>" data-field="pin_label">
                    <?php echo (int) $ctx['ispinned'] === 1 ? '置顶' : '—'; ?>
                </span>
            </td>
            <td>
                <span class="vs-badge <?php echo (int) $ctx['ispopup'] === 1 ? 'vs-badge--info' : 'vs-badge--default'; ?>" data-field="popup_label">
                    <?php echo (int) $ctx['ispopup'] === 1 ? '弹窗' : '—'; ?>
                </span>
            </td>
        <?php elseif ($mode === 'message'): ?>
            <td>
                <span class="vs-badge vs-badge--info" data-field="audmode_label"><?php echo vs_e($ctx['audmode_label']); ?></span>
            </td>
            <td><span class="content-time-cell" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
            <td>
                <span class="vs-badge <?php echo (int) $ctx['ispinned'] === 1 ? 'vs-badge--warning' : 'vs-badge--default'; ?>" data-field="pin_label">
                    <?php echo (int) $ctx['ispinned'] === 1 ? '置顶' : '—'; ?>
                </span>
            </td>
            <td>
                <span class="vs-badge <?php echo (int) $ctx['ispopup'] === 1 ? 'vs-badge--info' : 'vs-badge--default'; ?>" data-field="popup_label">
                    <?php echo (int) $ctx['ispopup'] === 1 ? '弹窗' : '—'; ?>
                </span>
            </td>
            <td>
                <span class="vs-badge <?php echo vs_e(vs_content_status_badge_class($ctx['status'])); ?>" data-field="status_label">
                    <?php echo vs_e($ctx['status_label']); ?>
                </span>
            </td>
        <?php else: ?>
            <td>
                <div class="content-author-cell">
                    <?php if ($ctx['author_avatar'] !== ''): ?>
                        <img class="content-author-cell__avatar" src="<?php echo vs_e($ctx['author_avatar']); ?>" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer" data-field="author_avatar">
                    <?php else: ?>
                        <span class="content-author-cell__fallback" data-field="author_avatar"><?php echo vs_e(mb_substr($ctx['username'], 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                    <span class="content-author-cell__name" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
                </div>
            </td>
            <td><span class="content-time-cell" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span></td>
            <td>
                <span class="vs-badge <?php echo vs_e(vs_content_status_badge_class($ctx['status'])); ?>" data-field="status_label">
                    <?php echo vs_e($ctx['status_label']); ?>
                </span>
            </td>
        <?php endif; ?>
        <td class="vs-col-actions vs-content-actions-cell" data-field="actions">
            <?php echo vs_content_actions_html($ctx, $mode); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @param mixed $mode
 * @return void
 */
function vs_render_content_mobile_card(array $ctx, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    $attrs = vs_content_data_attrs($ctx);
    $cardClass = ($mode === 'article') ? 'art-card' : 'ann-card';
    ?>
    <div class="<?php echo $cardClass; ?>"<?php echo $attrs; ?>>
        <div class="<?php echo $cardClass; ?>__header">
            <span class="<?php echo $cardClass; ?>__title" data-field="title"><?php echo vs_e($ctx['title']); ?></span>
            <div class="<?php echo $cardClass; ?>__tags">
                <?php if ($mode === 'article' && (int) $ctx['bindpage'] === ContentManager::BIND_ABOUT): ?>
                    <span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>
                <?php endif; ?>
                <?php if ($mode === 'article' && (int) $ctx['ispinned'] === 1): ?>
                    <span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>
                <?php endif; ?>
                <?php if ($mode === 'announcement'): ?>
                    <span class="vs-badge <?php echo !empty($ctx['is_site_msg']) ? 'vs-badge--info' : 'vs-badge--default'; ?>" data-field="kind_label">
                        <?php echo vs_e($ctx['kind_label']); ?>
                    </span>
                    <?php if (!empty($ctx['is_site_msg'])): ?>
                        <span class="vs-badge vs-badge--info" data-field="audmode_label"><?php echo vs_e($ctx['audmode_label']); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($mode === 'message'): ?>
                    <span class="vs-badge vs-badge--info" data-field="audmode_label"><?php echo vs_e($ctx['audmode_label']); ?></span>
                <?php endif; ?>
                <?php if ($mode === 'announcement' || $mode === 'message'): ?>
                    <?php if ((int) $ctx['ispinned'] === 1): ?>
                        <span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>
                    <?php endif; ?>
                    <?php if ((int) $ctx['ispopup'] === 1): ?>
                        <span class="vs-badge vs-badge--info" data-field="popup_label">弹窗</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($mode === 'article' || $mode === 'message' || ($mode === 'announcement' && !empty($ctx['is_site_msg']))): ?>
                    <span class="vs-badge <?php echo vs_e(vs_content_status_badge_class($ctx['status'])); ?>" data-field="status_label">
                        <?php echo vs_e($ctx['status_label']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($mode === 'announcement' || $mode === 'message'): ?>
            <span class="<?php echo $cardClass; ?>__time" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span>
        <?php else: ?>
            <div class="<?php echo $cardClass; ?>__info">
                <span class="<?php echo $cardClass; ?>__info-item">
                    <span class="<?php echo $cardClass; ?>__info-label">作者</span>
                    <span class="<?php echo $cardClass; ?>__info-value" data-field="username"><?php echo vs_e($ctx['username']); ?></span>
                </span>
                <span class="<?php echo $cardClass; ?>__info-item">
                    <span class="<?php echo $cardClass; ?>__info-label">发布时间</span>
                    <span class="<?php echo $cardClass; ?>__info-value" data-field="createtime"><?php echo vs_e($ctx['time'] !== '' ? $ctx['time'] : '—'); ?></span>
                </span>
            </div>
        <?php endif; ?>
        <div class="<?php echo $cardClass; ?>__actions" data-field="actions">
            <?php echo vs_content_actions_html($ctx, $mode); ?>
        </div>
    </div>
    <?php
}

/**
 * 兼容旧调用名
 *
 * @param array $item
 * @param mixed $mode
 * @return void
 */
function vs_render_content_row(array $item, $mode = true)
{
    $mode = vs_content_normalize_mode($mode);
    vs_render_content_desktop_row(vs_content_row_ctx($item, $mode), $mode);
}

/**
 * 后台文章/公告 POST list 真分页 HTML
 *
 * @param int|int[] $kindOrKinds
 * @param mixed     $mode
 * @return void
 */
function vs_admin_content_list_ajax($kindOrKinds, $mode)
{
    $mode = vs_content_normalize_mode($mode);
    $opts = array(
        'q'        => isset($_POST['q']) ? (string) $_POST['q'] : '',
        'page'     => isset($_POST['page']) ? (int) $_POST['page'] : 1,
        'pagesize' => isset($_POST['pagesize']) ? (int) $_POST['pagesize'] : 20,
    );
    if (is_array($kindOrKinds)) {
        $opts['kinds'] = $kindOrKinds;
    } else {
        $opts['kind'] = $kindOrKinds;
    }
    $paged = ContentManager::listAdminPaged($opts);
    $desk = '';
    $mobile = '';
    foreach ($paged['list'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        ob_start();
        vs_render_content_desktop_row(vs_content_row_ctx($item, $mode), $mode);
        $desk .= ob_get_clean();
        ob_start();
        vs_render_content_mobile_card(vs_content_row_ctx($item, $mode), $mode);
        $mobile .= ob_get_clean();
    }
    AjaxResponse::success('ok', array(
        'desktop_html' => $desk,
        'mobile_html'  => $mobile,
        'total'        => $paged['total'],
        'page'         => $paged['page'],
        'pagesize'     => $paged['pagesize'],
    ));
}
