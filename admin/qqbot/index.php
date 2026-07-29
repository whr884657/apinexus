<?php
/**
 * 文件：admin/qqbot/index.php
 * 作用：QQ 互联机器人对接（占位页，后续扩展）
 */

require_once dirname(__DIR__) . '/init.php';

vs_admin_layout_start('QQ BOT', 'qqbot-home');
?>

<div class="vs-panel">
    <div class="vs-panel__header">
        <h2 class="vs-panel__title">QQ BOT</h2>
    </div>
    <div class="vs-panel__body">
        <?php
        vs_render_notice(
            'info',
            '功能筹备中',
            '本板块用于对接 QQ 互联机器人，菜单与目录已预留，具体能力后续迭代。'
        );
        ?>
        <p class="vs-form-hint">当前为占位页，暂无可用配置项。</p>
    </div>
</div>

<?php vs_admin_layout_end(); ?>
