<?php
/**
 * 文件：admin/includes/content_helpers.php
 * 作用：公告/文章列表行渲染
 */

/**
 * @param array $item
 * @param bool  $announcement
 * @return void
 */
function vs_render_content_row(array $item, $announcement = true)
{
    $id = (int) $item['id'];
    ?>
    <div class="vs-content-row" data-content-row="<?php echo $id; ?>"
         data-title="<?php echo vs_e($item['title']); ?>"
         data-summary="<?php echo vs_e($item['summary']); ?>"
         data-body="<?php echo vs_e($item['body']); ?>"
         data-cover="<?php echo vs_e(isset($item['cover']) ? $item['cover'] : ''); ?>"
         data-status="<?php echo (int) $item['status']; ?>"
         data-ispinned="<?php echo (int) $item['ispinned']; ?>"
         data-ispopup="<?php echo (int) $item['ispopup']; ?>">
        <div class="vs-content-row__main">
            <div class="vs-content-row__title"><?php echo vs_e($item['title']); ?></div>
            <div class="vs-content-row__meta">
                <span><?php echo vs_e($item['status_label']); ?></span>
                <?php if ($announcement && (int) $item['ispinned'] === 1): ?><span>置顶</span><?php endif; ?>
                <?php if ($announcement && (int) $item['ispopup'] === 1): ?><span>弹窗</span><?php endif; ?>
                <?php if (!$announcement): ?>
                    <span>阅读 <?php echo (int) $item['views']; ?></span>
                    <?php if (!empty($item['cover'])): ?><span>有封面</span><?php endif; ?>
                <?php endif; ?>
                <span><?php echo vs_e($item['createtime']); ?></span>
            </div>
        </div>
        <div class="vs-content-row__actions">
            <button type="button" class="vs-btn vs-btn--default vs-btn--sm" data-act="edit">编辑</button>
            <?php if ($announcement): ?>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-act="pin"><?php echo (int) $item['ispinned'] === 1 ? '取消置顶' : '置顶'; ?></button>
                <button type="button" class="vs-btn vs-btn--outline vs-btn--sm" data-act="popup"><?php echo (int) $item['ispopup'] === 1 ? '取消弹窗' : '设为弹窗'; ?></button>
            <?php endif; ?>
            <button type="button" class="vs-btn vs-btn--danger vs-btn--sm" data-act="delete">删除</button>
        </div>
    </div>
    <?php
}
