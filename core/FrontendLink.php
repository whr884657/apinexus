<?php
/**
 * 文件：core/FrontendLink.php
 * 作用：前台主题 · 已通过友情链接列表（主题只调用本类）
 */

class FrontendLink
{
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
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $out = array();
        foreach (LinkManager::listApproved() as $row) {
            $item = self::formatForTheme($row);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
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
