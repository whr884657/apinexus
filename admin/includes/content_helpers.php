<?php
/**
 * 文件：admin/includes/content_helpers.php
 * 作用：公告/文章列表双 DOM 渲染（桌面表格 + 手机卡片）
 */

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
 * @param bool  $announcement
 * @return array
 */
function vs_content_row_ctx(array $item, $announcement = true)
{
    $id = (int) $item['id'];
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
    $search = mb_strtolower(
        $title . ' ' . $username . ' ' . $time . ' #' . $id,
        'UTF-8'
    );

    return array(
        'id'             => $id,
        'announcement'   => (bool) $announcement,
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
        . ' data-views="' . (int) $ctx['views'] . '"'
        . ' data-username="' . vs_e($ctx['username']) . '"'
        . ' data-author-avatar="' . vs_e($ctx['author_avatar']) . '"'
        . ' data-createtime="' . vs_e($ctx['time']) . '"';
}

/**
 * 标题单元格（文章绑定关于页时旁挂徽章；标题与标签分栏防挤变形）
 *
 * @param array $ctx
 * @param bool  $announcement
 * @return string
 */
function vs_content_title_html(array $ctx, $announcement = true)
{
    $html = '<div class="content-title-cell">';
    $html .= '<span class="content-title-cell__text" data-field="title">' . vs_e($ctx['title']) . '</span>';
    if (!$announcement && (int) $ctx['bindpage'] === ContentManager::BIND_ABOUT) {
        $html .= '<span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * @param array $ctx
 * @param bool  $announcement
 * @return string
 */
function vs_content_actions_html(array $ctx, $announcement = true)
{
    $html = '<div class="action-btns">';
    $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="edit">编辑</button>';
    if ($announcement) {
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline-warning vs-content-act" data-act="pin">'
            . ((int) $ctx['ispinned'] === 1 ? '取消置顶' : '置顶') . '</button>';
        $html .= '<button type="button" class="vs-btn vs-btn--sm vs-btn--outline vs-content-act" data-act="popup">'
            . ((int) $ctx['ispopup'] === 1 ? '取消弹窗' : '设为弹窗') . '</button>';
    } else {
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
 * @param bool  $announcement
 * @return void
 */
function vs_render_content_desktop_row(array $ctx, $announcement = true)
{
    $attrs = vs_content_data_attrs($ctx);
    ?>
    <tr<?php echo $attrs; ?>>
        <td>
            <?php echo vs_content_title_html($ctx, $announcement); ?>
        </td>
        <?php if ($announcement): ?>
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
        <td class="vs-content-actions-cell" data-field="actions">
            <?php echo vs_content_actions_html($ctx, $announcement); ?>
        </td>
    </tr>
    <?php
}

/**
 * @param array $ctx
 * @param bool  $announcement
 * @return void
 */
function vs_render_content_mobile_card(array $ctx, $announcement = true)
{
    $attrs = vs_content_data_attrs($ctx);
    $cardClass = $announcement ? 'ann-card' : 'art-card';
    ?>
    <div class="<?php echo $cardClass; ?>"<?php echo $attrs; ?>>
        <div class="<?php echo $cardClass; ?>__header">
            <span class="<?php echo $cardClass; ?>__title" data-field="title"><?php echo vs_e($ctx['title']); ?></span>
            <div class="<?php echo $cardClass; ?>__tags">
                <?php if (!$announcement && (int) $ctx['bindpage'] === ContentManager::BIND_ABOUT): ?>
                    <span class="vs-badge vs-badge--info" data-field="bind_label">关于</span>
                <?php endif; ?>
                <?php if ($announcement): ?>
                    <?php if ((int) $ctx['ispinned'] === 1): ?>
                        <span class="vs-badge vs-badge--warning" data-field="pin_label">置顶</span>
                    <?php endif; ?>
                    <?php if ((int) $ctx['ispopup'] === 1): ?>
                        <span class="vs-badge vs-badge--info" data-field="popup_label">弹窗</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="vs-badge <?php echo vs_e(vs_content_status_badge_class($ctx['status'])); ?>" data-field="status_label">
                        <?php echo vs_e($ctx['status_label']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($announcement): ?>
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
            <?php echo vs_content_actions_html($ctx, $announcement); ?>
        </div>
    </div>
    <?php
}

/**
 * 兼容旧调用名
 *
 * @param array $item
 * @param bool  $announcement
 * @return void
 */
function vs_render_content_row(array $item, $announcement = true)
{
    vs_render_content_desktop_row(vs_content_row_ctx($item, $announcement), $announcement);
}
