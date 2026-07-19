<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="content-wrapper" style="padding-top:70px;">
    <h1 class="page-title">关于我们</h1>
    <div class="page-content markdown-body" id="page-content" data-type="html">
        <h2>关于 <?php echo vs_e($siteName); ?></h2>
        <p><strong><?php echo vs_e($siteName); ?></strong> 是基于 ApiNexus 构建的 API 接口展示与管理平台。</p>
        <h3>我们的使命</h3>
        <ul>
            <li>提供免费、稳定、易用的 API 接口展示能力</li>
            <li>降低开发者接入与站点部署成本</li>
            <li>构建开放的开发者社区</li>
        </ul>
        <h3>系统信息</h3>
        <ul>
            <li>系统版本：v<?php echo vs_e(VS_VERSION); ?></li>
            <li>当前主题：<?php echo vs_e($themeId); ?></li>
            <li>开源仓库（Gitee 主）：<a href="https://gitee.com/xunjinlu/apinexus" target="_blank" rel="noopener noreferrer">gitee.com/xunjinlu/apinexus</a></li>
            <li>镜像：<a href="https://gitcode.com/xunjinlu/apinexus" target="_blank" rel="noopener noreferrer">GitCode</a> · <a href="https://github.com/whr884657/apinexus" target="_blank" rel="noopener noreferrer">GitHub</a></li>
        </ul>
    </div>
</main>
