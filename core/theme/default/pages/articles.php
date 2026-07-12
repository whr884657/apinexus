<?php if (!defined('VS_THEME_RENDER')) { exit; } ?>
<main class="content-wrapper" style="padding-top:70px;">
    <h1 class="page-title">文章</h1>
    <article class="article-card">
        <div class="article-card-inner right-image">
            <div class="article-card-content">
                <a href="<?php echo vs_e($vsBase); ?>/articles" class="article-card-title"><?php echo vs_e($siteName); ?> 平台使用指南</a>
                <p class="article-card-excerpt">本文介绍如何快速接入平台接口，包括注册登录、提交接口与审核流程。</p>
                <div class="article-card-meta"><span>v<?php echo vs_e(VS_VERSION); ?></span><span>公告</span></div>
            </div>
        </div>
    </article>
    <article class="article-card">
        <div class="article-card-inner">
            <div class="article-card-content">
                <a href="<?php echo vs_e($vsBase); ?>/apis" class="article-card-title">如何浏览全部接口</a>
                <p class="article-card-excerpt">在全部接口页可按分类筛选、关键词搜索，查看已通过审核的公开 API。</p>
                <div class="article-card-meta"><span>教程</span><span>接口</span></div>
            </div>
        </div>
    </article>
    <article class="article-card">
        <div class="article-card-inner">
            <div class="article-card-content">
                <span class="article-card-title" style="cursor:default;">文章模块建设中</span>
                <p class="article-card-excerpt">后续可在后台内容运营中发布更多资讯、教程与公告。</p>
                <div class="article-card-meta"><span>敬请期待</span></div>
            </div>
        </div>
    </article>
</main>
