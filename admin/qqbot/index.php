<?php
/**
 * 文件：admin/qqbot/index.php
 * 作用：QQ 互联机器人对接（占位页，后续扩展）
 */

require_once dirname(__DIR__) . '/init.php';

vs_admin_layout_start('QQ BOT', 'qqbot-home');
vs_admin_placeholder_panel(
    'QQ BOT',
    '本板块用于对接 QQ 互联机器人，菜单与目录已预留，具体能力后续迭代。'
);
vs_admin_layout_end();
