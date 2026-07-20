<?php
/**
 * 文件：core/FrontendPartner.php
 * 作用：前台主题 · 已启用合作伙伴列表（主题只调用本类）
 */

class FrontendPartner
{
    /**
     * @param array $row LinkManager::formatRow 或库行
     * @return array|null
     */
    public static function formatForTheme(array $row)
    {
        if (!isset($row['enabled_label']) && isset($row['id'])) {
            $row = LinkManager::formatRow($row);
        }

        $kind = LinkManager::normalizeKind(isset($row['kind']) ? $row['kind'] : LinkManager::KIND_PARTNER);
        $enabled = LinkManager::normalizeEnabled(isset($row['enabled']) ? $row['enabled'] : LinkManager::ENABLED_OFF);
        if ($kind !== LinkManager::KIND_PARTNER || $enabled !== LinkManager::ENABLED_ON) {
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

        return array(
            'id'      => (int) (isset($row['id']) ? $row['id'] : 0),
            'name'    => $name,
            'siteurl' => $siteurl,
            'icon'    => $icon,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $out = array();
        foreach (LinkManager::listPartnersEnabled() as $row) {
            $item = self::formatForTheme($row);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }
}
