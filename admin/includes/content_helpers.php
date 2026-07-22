<?php
/**
 * 文件：admin/includes/content_helpers.php
 * 作用：公告/文章列表行渲染（对齐后台 API 列表卡片结构）
 */

/**
 * @param array $item
 * @param bool  $announcement
 * @return void
 */
function vs_render_content_row(array $item, $announcement = true)
{
    $id = (int) $item['id'];
    $coverlayout = isset($item['coverlayout'])
        ? ContentManager::normalizeCoverLayout($item['coverlayout'])
        : ContentManager::COVER_LEFT;
    $statusLabel = isset($item['status_label']) ? (string) $item['status_label'] : '已发布';
    $status = isset($item['status']) ? (int) $item['status'] : ContentManager::STATUS_PUBLISHED;
    $statusClass = 'is-normal';
    if ($status === ContentManager::STATUS_DRAFT) {
        $statusClass = 'is-disabled';
    } elseif ($status === ContentManager::STATUS_OFF) {
        $statusClass = 'is-disabled';
    }
    ?>
    <div class="vs-api-item vs-content-item" data-content-row="<?php echo $id; ?>"
         data-title="<?php echo vs_e($item['title']); ?>"
         data-summary="<?php echo vs_e(isset($item['summary']) ? $item['summary'] : ''); ?>"
         data-body="<?php echo vs_e($item['body']); ?>"
         data-cover="<?php echo vs_e(isset($item['cover']) ? $item['cover'] : ''); ?>"
         data-coverlayout="<?php echo (int) $coverlayout; ?>"
         data-status="<?php echo $status; ?>"
         data-status-label="<?php echo vs_e($statusLabel); ?>"
         data-ispinned="<?php echo (int) $item['ispinned']; ?>"
         data-ispopup="<?php echo (int) $item['ispopup']; ?>"
         data-views="<?php echo isset($item['views']) ? (int) $item['views'] : 0; ?>"
         data-createtime="<?php echo vs_e(isset($item['createtime']) ? $item['createtime'] : ''); ?>">
        <div class="vs-api-item__title">
            <span class="vs-api-item__name"><?php echo vs_e($item['title']); ?></span>
            <span class="vs-api-item__id">#<?php echo $id; ?></span>
        </div>
        <div class="vs-api-item__tags">
            <span class="vs-api-tag vs-api-tag--status <?php echo $statusClass; ?>"><?php echo vs_e($statusLabel); ?></span>
            <?php if ($announcement && (int) $item['ispinned'] === 1): ?>
                <span class="vs-api-tag vs-api-tag--cat">置顶</span>
            <?php endif; ?>
            <?php if ($announcement && (int) $item['ispopup'] === 1): ?>
                <span class="vs-api-tag vs-api-tag--proxy">弹窗</span>
            <?php endif; ?>
            <?php if (!$announcement && !empty($item['cover'])): ?>
                <span class="vs-api-tag vs-api-tag--key">有封面</span>
            <?php endif; ?>
            <?php if (!$announcement && !empty($item['coverlayout_label'])): ?>
                <span class="vs-api-tag vs-api-tag--local"><?php echo vs_e($item['coverlayout_label']); ?></span>
            <?php endif; ?>
        </div>
        <div class="vs-api-item__meta">
            <?php if (!$announcement): ?>
                <div class="vs-api-item__calls" title="阅读量">阅读：<strong><?php echo (int) $item['views']; ?></strong></div>
            <?php endif; ?>
            <div class="vs-api-item__author" title="发布时间"><?php echo vs_e(isset($item['createtime']) ? $item['createtime'] : ''); ?></div>
        </div>
        <div class="vs-api-item__actions">
            <button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-act="edit">编辑</button>
            <?php if ($announcement): ?>
                <button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-act="pin"><?php echo (int) $item['ispinned'] === 1 ? '取消置顶' : '置顶'; ?></button>
                <button type="button" class="vs-btn vs-btn--outline vs-api-list-action" data-act="popup"><?php echo (int) $item['ispopup'] === 1 ? '取消弹窗' : '设为弹窗'; ?></button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--outline vs-btn--outline-danger vs-api-list-action" data-act="delete">删除</button>
        </div>
    </div>
    <?php
}
