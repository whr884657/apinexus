<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<section class="vs-frontend-page-head">
    <h1 class="vs-frontend-page-head__title">赞助</h1>
    <p class="vs-frontend-page-head__desc">支持项目持续开发与维护</p>
</section>

<section class="vs-frontend-section">
    <?php vs_render_notice('info', '', '赞助通道与订单管理功能完善中，可在后台财务管理中配置。', array('compact' => true)); ?>
    <div class="vs-frontend-sponsor-card">
        <h3>感谢您的支持</h3>
        <p>您的赞助将用于服务器、文档与功能迭代。赞助方案上线后将在此展示。</p>
    </div>
</section>
