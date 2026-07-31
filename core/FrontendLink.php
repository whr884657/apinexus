<?php
/**
 * 文件：core/FrontendLink.php
 * 作用：前台主题 · 已通过友情链接列表（主题只调用本类）
 */

class FrontendLink
{
    /** 友链页硬上限，防止 kind 误刷后海量 DOM 卡死浏览器 */
    const PAGE_HARD_LIMIT = 120;

    /**
     * @param array $row LinkManager::formatRow 或库行
     * @return array|null
     */
    public static function formatForTheme(array $row)
    {
        if (!isset($row['status_label']) && isset($row['id'])) {
            $row = LinkManager::formatRow($row);
        }

        $kind = LinkManager::normalizeKind(isset($row['kind']) ? $row['kind'] : LinkManager::KIND_FRIEND);
        $status = LinkManager::normalizeStatus(isset($row['status']) ? $row['status'] : LinkManager::STATUS_PENDING);
        $enabled = LinkManager::normalizeEnabled(isset($row['enabled']) ? $row['enabled'] : LinkManager::ENABLED_ON);
        if ($kind !== LinkManager::KIND_FRIEND
            || $status !== LinkManager::STATUS_APPROVED
            || $enabled !== LinkManager::ENABLED_ON
        ) {
            return null;
        }

        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        $siteurl = trim((string) (isset($row['siteurl']) ? $row['siteurl'] : ''));
        if ($name === '' || $siteurl === '') {
            return null;
        }

        $icon = '';
        if (!empty($row['icon_url'])) {
            $icon = (string) $row['icon_url'];
        } elseif (!empty($row['icon'])) {
            $icon = LinkManager::normalizeIcon((string) $row['icon']);
        }

        $host = '';
        $parts = parse_url($siteurl);
        if (is_array($parts) && !empty($parts['host'])) {
            $host = (string) $parts['host'];
        }

        return array(
            'id'          => (int) (isset($row['id']) ? $row['id'] : 0),
            'name'        => $name,
            'siteurl'     => $siteurl,
            'icon'        => $icon,
            'description' => trim((string) (isset($row['description']) ? $row['description'] : '')),
            'host'        => $host,
            'initial'     => mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'),
        );
    }

    /**
     * 页脚展示用：随机打乱；可限制条数
     *
     * @param int $limit 0=全部；1～10=随机取这么多
     * @return array{items:array,has_more:bool,total:int,limit:int}
     */
    public static function pickForFooter($limit = 0)
    {
        $all = self::listForTheme();
        $total = count($all);
        $limit = (int) $limit;
        if ($limit < 0) {
            $limit = 0;
        }
        // 页脚硬上限 10：即便主题选「全部」也不刷爆页脚 DOM
        if ($limit === 0 || $limit > 10) {
            $limit = 10;
        }

        if ($total <= 1) {
            return array(
                'items'    => $all,
                'has_more' => false,
                'total'    => $total,
                'limit'    => $limit,
            );
        }

        // 每次请求不同顺序
        shuffle($all);

        if ($limit >= $total) {
            return array(
                'items'    => $all,
                'has_more' => false,
                'total'    => $total,
                'limit'    => $limit,
            );
        }

        return array(
            'items'    => array_slice($all, 0, $limit),
            'has_more' => true,
            'total'    => $total,
            'limit'    => $limit,
        );
    }

    /**
     * 友链页取数（带硬上限，防卡死）
     *
     * @return array{items:array,total:int,truncated:bool,limit:int}
     */
    public static function listForThemePage()
    {
        $all = self::listForTheme();
        $total = count($all);
        $limit = self::PAGE_HARD_LIMIT;
        if ($total <= $limit) {
            return array(
                'items'    => $all,
                'total'    => $total,
                'truncated' => false,
                'limit'    => $limit,
            );
        }
        return array(
            'items'    => array_slice($all, 0, $limit),
            'total'    => $total,
            'truncated' => true,
            'limit'    => $limit,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $factory = function () {
            $out = array();
            foreach (LinkManager::listApproved() as $row) {
                $item = self::formatForTheme($row);
                if ($item !== null) {
                    $out[] = $item;
                }
            }
            return $out;
        };
        if (class_exists('RedisCache')) {
            $cached = RedisCache::remember(RedisCache::KEY_FRONTEND_LINK, RedisCache::TTL_FRONTEND_LINK, $factory);
            // 防御：缓存脏数据时再滤一层（仅保留合法友链结构）
            if (!is_array($cached)) {
                return $factory();
            }
            $clean = array();
            foreach ($cached as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $url = isset($row['siteurl']) ? trim((string) $row['siteurl']) : '';
                if ($name === '' || $url === '') {
                    continue;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    continue;
                }
                if (!empty($row['icon']) && is_string($row['icon'])) {
                    $row['icon'] = LinkManager::upgradeInsecureUrl($row['icon']);
                }
                $clean[] = $row;
            }
            return $clean;
        }
        return $factory();
    }

    /**
     * 本站友链信息（供申请页展示）
     *
     * @return array{name:string,url:string,desc:string,icon:string}
     */
    public static function siteCard()
    {
        $base = rtrim(vs_base_url(), '/');
        $icon = '';
        if (class_exists('SiteContext')) {
            $fav = trim(SiteContext::siteFavicon());
            if ($fav === '') {
                $fav = trim(SiteContext::siteLogo());
            }
            if ($fav !== '') {
                $icon = vs_favicon_href($fav);
            }
        }

        return array(
            'name' => class_exists('SiteContext') ? SiteContext::siteName() : 'ApiNexus',
            'url'  => $base . '/',
            'desc' => class_exists('SiteContext') ? SiteContext::siteDescription() : '',
            'icon' => $icon,
        );
    }
}
