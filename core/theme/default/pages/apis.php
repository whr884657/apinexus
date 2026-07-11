<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<section class="vs-frontend-page-head">
    <h1 class="vs-frontend-page-head__title">全部接口</h1>
    <p class="vs-frontend-page-head__desc">浏览平台提供的 API / TAPI 接口列表（功能完善中）</p>
</section>

<section class="vs-frontend-section">
    <?php vs_render_notice('info', '', '接口列表模块正在建设中。用户可注册账号后自主发布接口，管理员后台发布将使用绑定用户身份。', array('compact' => true)); ?>
    <div class="vs-frontend-placeholder-grid">
        <div class="vs-frontend-placeholder-card">
            <h3>公开接口</h3>
            <p>即将展示所有可公开调用的 API 接口</p>
        </div>
        <div class="vs-frontend-placeholder-card">
            <h3>我的接口</h3>
            <p>登录后可在用户中心管理已发布的接口</p>
        </div>
    </div>
</section>
