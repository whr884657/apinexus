<?php
/**
 * 文件：core/AboutCatalog.php
 * 作用：管理员关于页「开发与维护 / 相关链接 / 技术栈」目录加载
 *
 * ═══════════════════════════════════════════════════════════════
 * 数据从哪来（相关人员 team 也在这份 JSON 里）
 * ═══════════════════════════════════════════════════════════════
 *
 * 唯一数据文件（相对站点根 VS_ROOT）：
 *   core/vx/seed/r9/m2/catalog.json
 * 常量：AboutCatalog::REL_PATH
 *
 * JSON 字段说明：
 *   team[]  —— 「开发与维护」相关人员（name / role / avatar / site）
 *   links[] —— 「相关链接」（name / href / icon）
 *   tech[]  —— 「技术栈」（name / href / icon / tone）
 *   note    —— 技术栈下方说明文案
 *
 * 加载顺序（有本地文件就绝不会再拉仓库）：
 *   1) Redis 缓存键 cache:about:catalog:v3（TTL 约 30 分钟）
 *   2) 本地 catalog.json（发版 ZIP / 在线更新会带上）
 *   3) 仅当本地文件缺失时：按 Updater 镜像顺序拉取仓库 raw
 *        Gitee:   https://gitee.com/{repo}/raw/main/core/vx/seed/r9/m2/catalog.json
 *        GitCode: https://raw.gitcode.com/{repo}/raw/main/core/vx/seed/r9/m2/catalog.json
 *        GitHub:  https://raw.githubusercontent.com/{repo}/main/core/vx/seed/r9/m2/catalog.json
 *   4) 仍失败则用本类 defaults()
 *
 * 改相关人员 / 技术栈：改本地 catalog.json（并同步 defaults 以免无文件时兜底不一致）。
 * 改完若页面仍旧：管理后台 Redis 管理清空业务缓存，或等缓存过期。
 *
 * 技术栈图标：一律 assets/img/ 根目录（见 admin/about.php 的 vs_about_icon_src）。
 * 兼容：云端多字段 / 新人不会撑爆旧版 normalize；未知字段忽略。
 */

class AboutCatalog
{
    /**
     * 关于页目录 JSON（相对 VS_ROOT；勿在前台 URL 暴露此路径）
     * 含 team（相关人员）/ links / tech / note
     */
    const REL_PATH = 'core/vx/seed/r9/m2/catalog.json';

    /** 改 catalog 结构或内容后递增版本，避免旧 Redis 缓存挡住新数据 */
    const CACHE_KEY = 'cache:about:catalog:v3';
    const CACHE_TTL = 1800;

    /**
     * 加载目录：Redis → 本地 JSON →（仅本地缺失）云端 raw → 内置默认
     *
     * @return array{team:array,links:array,tech:array,note:string}
     */
    public static function load()
    {
        if (class_exists('RedisCache') && RedisCache::enabled()) {
            $data = RedisCache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return self::fetchFresh();
            });
            if (is_array($data)) {
                return self::normalize($data);
            }
        }

        return self::normalize(self::fetchFresh());
    }

    /**
     * 本地优先；无本地文件才请求云端（兼容未带上 catalog 的旧包）
     *
     * @return array
     */
    private static function fetchFresh()
    {
        $local = self::loadLocal();
        if (is_array($local) && self::looksValid($local)) {
            return $local;
        }

        $cloud = self::fetchCloud();
        if (is_array($cloud) && self::looksValid($cloud)) {
            return $cloud;
        }

        return self::defaults();
    }

    /**
     * 按更新源顺序拉取仓库 raw catalog.json（仅本地缺失时调用）
     *
     * @return array|null
     */
    private static function fetchCloud()
    {
        if (!class_exists('Updater') || !method_exists('Updater', 'updateMirrors')) {
            return null;
        }
        $rel = str_replace('\\', '/', self::REL_PATH);
        foreach (Updater::updateMirrors() as $mirror) {
            $url = self::buildRawUrl($mirror, $rel);
            if ($url === '') {
                continue;
            }
            $body = Updater::httpGet($url, 4);
            if ($body === false || $body === '') {
                continue;
            }
            $data = json_decode($body, true);
            if (is_array($data) && self::looksValid($data)) {
                return $data;
            }
        }
        return null;
    }

    /**
     * @param array  $mirror
     * @param string $rel
     * @return string
     */
    private static function buildRawUrl(array $mirror, $rel)
    {
        $id = isset($mirror['id']) ? (string) $mirror['id'] : '';
        $repo = isset($mirror['repo']) ? (string) $mirror['repo'] : '';
        $branch = 'main';
        if (class_exists('Updater')) {
            $branch = Updater::DEFAULT_BRANCH;
        }
        if ($repo === '') {
            return '';
        }
        if ($id === 'gitee') {
            return 'https://gitee.com/' . $repo . '/raw/' . $branch . '/' . $rel;
        }
        if ($id === 'gitcode') {
            return 'https://raw.gitcode.com/' . $repo . '/raw/' . $branch . '/' . $rel;
        }
        if ($id === 'github') {
            return 'https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/' . $rel;
        }
        return '';
    }

    /**
     * 读本地 VS_ROOT/core/vx/seed/r9/m2/catalog.json
     *
     * @return array|null
     */
    private static function loadLocal()
    {
        $path = VS_ROOT . '/' . str_replace('\\', '/', self::REL_PATH);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array $data
     * @return bool
     */
    private static function looksValid(array $data)
    {
        return isset($data['team']) || isset($data['links']) || isset($data['tech']);
    }

    /**
     * 宽松归一化：缺段用默认补齐；未知字段忽略；新人/新图标不导致页面报错
     *
     * @param array $data
     * @return array{team:array,links:array,tech:array,note:string}
     */
    private static function normalize(array $data)
    {
        $defaults = self::defaults();
        $team = array();
        if (!empty($data['team']) && is_array($data['team'])) {
            foreach ($data['team'] as $row) {
                if (!is_array($row) || empty($row['name'])) {
                    continue;
                }
                $site = '';
                if (!empty($row['site'])) {
                    $site = self::safeHttpUrl((string) $row['site']);
                } elseif (!empty($row['url'])) {
                    $site = self::safeHttpUrl((string) $row['url']);
                }
                $team[] = array(
                    'name'   => (string) $row['name'],
                    'role'   => isset($row['role']) ? (string) $row['role'] : '',
                    'avatar' => isset($row['avatar']) ? (string) $row['avatar'] : '',
                    'site'   => $site,
                );
            }
        }
        if (!$team) {
            $team = $defaults['team'];
        }

        $links = array();
        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $row) {
                if (!is_array($row) || empty($row['name']) || empty($row['href'])) {
                    continue;
                }
                $href = self::safeHttpUrl((string) $row['href']);
                if ($href === '') {
                    continue;
                }
                $links[] = array(
                    'name' => (string) $row['name'],
                    'href' => $href,
                    'icon' => isset($row['icon']) ? (string) $row['icon'] : '',
                );
            }
        }
        if (!$links) {
            $links = $defaults['links'];
        }

        $tech = array();
        if (!empty($data['tech']) && is_array($data['tech'])) {
            foreach ($data['tech'] as $row) {
                if (!is_array($row) || empty($row['name'])) {
                    continue;
                }
                $href = '';
                if (!empty($row['href'])) {
                    $href = self::safeHttpUrl((string) $row['href']);
                }
                $tech[] = array(
                    'name' => (string) $row['name'],
                    'href' => $href,
                    'icon' => isset($row['icon']) ? (string) $row['icon'] : '',
                    'tone' => isset($row['tone']) ? (string) $row['tone'] : 'default',
                );
            }
        }
        if (!$tech) {
            $tech = $defaults['tech'];
        }

        $note = isset($data['note']) ? trim((string) $data['note']) : '';
        if ($note === '') {
            $note = $defaults['note'];
        }

        return array(
            'team'  => $team,
            'links' => $links,
            'tech'  => $tech,
            'note'  => $note,
        );
    }

    /**
     * 仅允许 http(s) 外链，避免脏数据写入 href
     *
     * @param string $url
     * @return string
     */
    private static function safeHttpUrl($url)
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        return $url;
    }

    /**
     * 内置兜底（本地与云端都不可用时）
     *
     * @return array{team:array,links:array,tech:array,note:string}
     */
    private static function defaults()
    {
        return array(
            'team' => array(
                array(
                    'name'   => '尋鯨錄',
                    'role'   => '开发 · 维护',
                    'avatar' => 'https://q1.qlogo.cn/g?b=qq&nk=3202089153&s=640',
                    'site'   => 'https://www.xunjinlu.fun/',
                ),
            ),
            'links' => array(
                array('name' => 'Gitee', 'href' => 'https://gitee.com/xunjinlu/apinexus', 'icon' => 'gitee'),
                array('name' => 'GitCode', 'href' => 'https://gitcode.com/xunjinlu/apinexus', 'icon' => 'gitcode'),
                array('name' => 'GitHub', 'href' => 'https://github.com/whr884657/apinexus', 'icon' => 'github'),
                array(
                    'name' => '发行版下载',
                    'href' => 'https://gitee.com/xunjinlu/apinexus/releases',
                    'icon' => 'gitee',
                ),
            ),
            'tech' => array(
                array('name' => 'PHP', 'href' => 'https://www.php.net', 'icon' => 'php', 'tone' => 'php'),
                array('name' => 'MySQL', 'href' => 'https://www.mysql.com', 'icon' => 'MySQL', 'tone' => 'mysql'),
                array('name' => 'Redis', 'href' => 'https://redis.io', 'icon' => 'Redis', 'tone' => 'redis'),
                array('name' => 'Nginx', 'href' => 'https://nginx.org', 'icon' => 'nginx', 'tone' => 'nginx'),
                array('name' => 'JavaScript', 'href' => 'https://developer.mozilla.org/docs/Web/JavaScript', 'icon' => 'javascript', 'tone' => 'js'),
                array('name' => 'ECharts', 'href' => 'https://echarts.apache.org', 'icon' => 'echarts', 'tone' => 'echarts'),
                array('name' => 'Parsedown', 'href' => 'https://parsedown.org', 'icon' => '', 'tone' => 'parsedown'),
            ),
            'note' => '涵盖本站后端运行环境、前端交互与常用开源组件；图标统一放在 assets/img/。感谢相关项目与贡献者。',
        );
    }
}
