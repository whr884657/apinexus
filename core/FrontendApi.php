<?php
/**
 * 文件：core/FrontendApi.php
 * 作用：前台主题 · 公开接口列表（统一调度，主题只调用本类）
 *
 * 说明：仅输出已通过审核的公开接口；分类 id 与 FrontendCategory 一致。
 */

class FrontendApi
{
    /**
     * 供前台主题 / JS 使用的公开接口列表
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $apiData = array();

        foreach (ApiManager::listPublic() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($name === '') {
                continue;
            }

            $catLabel = trim((string) (isset($row['category']) ? $row['category'] : ''));
            $catKey = FrontendCategory::resolveIdByName($catLabel);

            $method = strtoupper(trim((string) (isset($row['method']) ? $row['method'] : 'GET')));
            if ($method === '') {
                $method = 'GET';
            }
            $methods = array_values(array_filter(array_map('trim', explode(',', $method))));
            if ($methods === array()) {
                $methods = array('GET');
            }
            $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));

            $apiData[] = array(
                'id'              => (int) $row['id'],
                'name'            => $name,
                'desc'            => trim((string) (isset($row['description']) ? $row['description'] : '')),
                'category'        => $catKey,
                'method'          => $methods[0],
                'methods'         => $methods,
                'endpoint'        => $endpoint,
                'full_url'        => $endpoint,
                'backup_url'      => '',
                'params'          => '',
                'maintenance'     => 0,
                'require_api_key' => 0,
                'points_cost'     => 0,
            );
        }

        return $apiData;
    }

    /**
     * @return int
     */
    public static function countForTheme()
    {
        return count(self::listForTheme());
    }
}
