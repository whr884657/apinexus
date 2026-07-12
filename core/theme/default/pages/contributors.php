<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="main-wrapper container mx-auto px-4" style="padding-top:70px;">
    <div class="page-header">
        <h1 class="section-title">公益贡献者</h1>
        <p style="color: var(--text-muted);">感谢每一位为开源社区贡献力量的开发者</p>
    </div>
    <div class="thank-you-banner">
        <h2>衷心感谢</h2>
        <p>感谢以下成员对 misc-api 与接口生态的支持。<br>正是有了你们的贡献，平台才能持续迭代与完善。</p>
    </div>
    <div class="contributors-grid">
        <div class="contributor-card">
            <div class="contributor-avatar-placeholder"><span>M</span></div>
            <div class="contributor-name">misc-api 团队</div>
            <p class="contributor-bio">核心开发与架构维护</p>
            <div class="contributor-stats">
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">接口数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">调用次数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">核心</div><div class="contributor-stat-label">角色</div></div>
            </div>
        </div>
        <div class="contributor-card">
            <div class="contributor-avatar-placeholder"><span>C</span></div>
            <div class="contributor-name">社区贡献者</div>
            <p class="contributor-bio">文档、Issue 反馈与 Pull Request</p>
            <div class="contributor-stats">
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">接口数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">调用次数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">社区</div><div class="contributor-stat-label">角色</div></div>
            </div>
        </div>
        <div class="contributor-card">
            <div class="contributor-avatar-placeholder"><span>U</span></div>
            <div class="contributor-name">用户开发者</div>
            <p class="contributor-bio">提交并发布公开 API 接口</p>
            <div class="contributor-stats">
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">接口数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">—</div><div class="contributor-stat-label">调用次数</div></div>
                <div class="contributor-stat"><div class="contributor-stat-value">发布</div><div class="contributor-stat-label">角色</div></div>
            </div>
        </div>
    </div>
    <div style="text-align: center; padding: 2rem 1rem;">
        <p style="color: var(--text-muted); margin-bottom: 1rem;">想要加入贡献者行列？</p>
        <a href="<?php echo vs_e($authUrl); ?>" class="btn-geek">立即注册</a>
    </div>
</main>
