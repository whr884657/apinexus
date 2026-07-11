<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}
?>
<section class="vs-frontend-page-head">
    <h1 class="vs-frontend-page-head__title">友情链接</h1>
    <p class="vs-frontend-page-head__desc">与我们一起构建开放 API 生态的伙伴链接</p>
</section>

<section class="vs-frontend-section">
    <?php vs_render_notice('info', '', '友情链接可在后台内容运营中维护，前台将随主题展示。', array('compact' => true)); ?>
    <ul class="vs-frontend-links-list">
        <li class="vs-frontend-links-item">
            <a href="https://gitee.com/xunjinlu/misc-api" target="_blank" rel="noopener noreferrer">misc-api 开源仓库</a>
            <span class="vs-frontend-links-item__desc">项目源码与发行版</span>
        </li>
    </ul>
</section>
