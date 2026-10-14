<?php
/**
 * 文件：admin/ops/message.php
 * 作用：运营中心 · 站内私信（功能筹备中占位；站内公告已并入内容管理·公告管理）
 *
 * v13.26.46 安全修复：添加明确的功能权限检查
 */

require_once dirname(__DIR__) . '/init.php';

// 修复：明确检查该功能是否已开放
$featureReady = false; // 或从配置读取: Config::get('ops_message_enabled', false)

if (!$featureReady) {
    vs_admin_layout_start('站内私信', 'ops-message');
    vs_admin_placeholder_panel(
        '功能筹备中',
        '本页功能筹备中，暂未开放。站内公告请到「内容管理 → 公告管理」发布与维护（可选择站内/站外与受众）。'
    );
    vs_admin_layout_end();
    exit; // 明确终止，防止后续代码执行
}

// ========== 以下是实际功能代码（预留） ==========
// 未来恢复功能时，必须添加权限检查：
// if (!vs_admin_can_access_ops_message()) {
//     AjaxResponse::error('无权访问该功能', 403);
// }

// 功能实现...
