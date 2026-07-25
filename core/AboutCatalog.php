<?php
/**
 * 文件：core/AboutCatalog.php
 * 作用：关于页「开发与维护 / 相关链接 / 技术基础」目录（云端优先，本地兜底）
 *
 * 说明：数据文件路径故意深藏于 core 多层目录，便于随仓库更新同步；页面优先拉云端。
 */

class AboutCatalog
{
    /** 相对 VS_ROOT 的隐蔽数据路径（勿在前台暴露） */
    const REL_PATH = 'core/vx/seed/r9/m2/catalog.json';

    const CACHE_KEY = 'cache:about:catalog';
    const CACHE_TTL = 1800;

    /**
     * 加载目录：Redis 缓存 → 云端 raw → 本地文件 → 内置默认
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
     * @return array
     */
    private static function fetchFresh()
    {
        $cloud = self::fetchCloud();
        if (is_array($cloud)) {
            return $cloud;
        }
        $local = self::loadLocal();
        if (is_array($local)) {
            return $local;
        }
        return self::defaults();
    }

    /**
     * 按更新源顺序拉取 raw catalog.json
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
     * @return array|null
     */
    private static function loadLocal()
    {
        $path = VS_ROOT . '/' . str_replace('\\', '/', self::REL_PATH);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
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
                $team[] = array(
                    'name'   => (string) $row['name'],
                    'role'   => isset($row['role']) ? (string) $row['role'] : '',
                    'avatar' => isset($row['avatar']) ? (string) $row['avatar'] : '',
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
                $links[] = array(
                    'name' => (string) $row['name'],
                    'href' => (string) $row['href'],
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
                $tech[] = array(
                    'name' => (string) $row['name'],
                    'href' => isset($row['href']) ? (string) $row['href'] : '',
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
     * @return array{team:array,links:array,tech:array,note:string}
     */
    private static function defaults()
    {
        return array(
            'team' => array(
                array(
                    'name'   => '尋鯨錄',
                    'role'   => '作者 · 基础维护',
                    'avatar' => 'https://q1.qlogo.cn/g?b=qq&nk=3202089153&s=640',
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
                array('name' => 'Parsedown', 'href' => 'https://parsedown.org', 'icon' => '', 'tone' => 'parsedown'),
            ),
            'note' => '本系统基于以上开源基础能力构建，感谢相关项目与贡献者。',
        );
    }
}
