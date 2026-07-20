<?php
/**
 * 文件：core/FrontendSponsor.php
 * 作用：前台主题 · 赞助收款码与赞助名单（主题只调用本类）
 */

class FrontendSponsor
{
    /**
     * 系统设置中的收款码（仅返回已填写 URL 的项）
     *
     * @return array<int, array{id:string,label:string,url:string}>
     */
    public static function paymentQrs()
    {
        $defs = array(
            array('id' => 'alipay', 'label' => '支付宝', 'key' => 'sponsor_qr_alipay'),
            array('id' => 'wechat', 'label' => '微信', 'key' => 'sponsor_qr_wechat'),
            array('id' => 'qq', 'label' => 'QQ', 'key' => 'sponsor_qr_qq'),
        );
        $out = array();
        foreach ($defs as $def) {
            $raw = trim((string) Config::get($def['key'], ''));
            if ($raw === '') {
                continue;
            }
            $url = self::normalizeMediaUrl($raw);
            if ($url === '') {
                continue;
            }
            $out[] = array(
                'id'    => $def['id'],
                'label' => $def['label'],
                'url'   => $url,
            );
        }
        return $out;
    }

    /**
     * @param string $raw
     * @return string
     */
    private static function normalizeMediaUrl($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }
        if (isset($raw[0]) && $raw[0] === '/') {
            return rtrim(vs_base_url(), '/') . $raw;
        }
        return '';
    }

    /**
     * @param array $row
     * @return array|null
     */
    public static function formatForTheme(array $row)
    {
        if (!isset($row['enabled_label']) && isset($row['id'])) {
            $row = LinkManager::formatRow($row);
        }

        $kind = LinkManager::normalizeKind(isset($row['kind']) ? $row['kind'] : LinkManager::KIND_SPONSOR);
        $enabled = LinkManager::normalizeEnabled(isset($row['enabled']) ? $row['enabled'] : LinkManager::ENABLED_OFF);
        if ($kind !== LinkManager::KIND_SPONSOR || $enabled !== LinkManager::ENABLED_ON) {
            return null;
        }

        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            return null;
        }

        $icon = '';
        if (!empty($row['icon_url'])) {
            $icon = (string) $row['icon_url'];
        } elseif (!empty($row['icon'])) {
            $icon = LinkManager::normalizeIcon((string) $row['icon']);
        }

        $siteurl = trim((string) (isset($row['siteurl']) ? $row['siteurl'] : ''));
        $description = trim((string) (isset($row['description']) ? $row['description'] : ''));

        return array(
            'id'          => (int) (isset($row['id']) ? $row['id'] : 0),
            'name'        => $name,
            'siteurl'     => $siteurl,
            'icon'        => $icon,
            'description' => $description,
            'initial'     => mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $out = array();
        foreach (LinkManager::listSponsorsEnabled() as $row) {
            $item = self::formatForTheme($row);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }
}
