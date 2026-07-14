<?php
/**
 * 文件：core/FrontendApi.php
 * 作用：前台主题 · 公开接口列表（统一调度，主题只调用本类）
 *
 * 说明：禁用接口不输出；维护中接口输出 maintenance=1，主题应拦截请求并提示「维护中」。
 * 图标字段非主题通用，未接入图标展示的主题可忽略 icon。
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

            $status = isset($row['status']) ? (string) $row['status'] : ApiManager::STATUS_NORMAL;
            if ($status === ApiManager::STATUS_DISABLED) {
                continue;
            }

            $catLabel = trim((string) (isset($row['category']) ? $row['category'] : ''));
            $catKey = FrontendCategory::resolveIdByName($catLabel);

            $method = strtoupper(trim((string) (isset($row['method']) ? $row['method'] : 'GET')));
            if ($method !== 'POST') {
                $method = 'GET';
            }
            $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
            $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';

            $apiData[] = array(
                'id'               => (int) $row['id'],
                'name'             => $name,
                'desc'             => trim((string) (isset($row['description']) ? $row['description'] : '')),
                'category'         => $catKey,
                'method'           => $method,
                'methods'          => array($method),
                'endpoint'         => $endpoint,
                'full_url'         => $endpoint,
                'backup_url'       => '',
                'params'           => isset($row['request_params']) ? (string) $row['request_params'] : '',
                'response_example' => isset($row['response_example']) ? (string) $row['response_example'] : '',
                'doc_normal'       => isset($row['doc_normal']) ? (string) $row['doc_normal'] : '',
                'doc_ai'           => isset($row['doc_ai']) ? (string) $row['doc_ai'] : '',
                'maintenance'      => $status === ApiManager::STATUS_MAINTENANCE ? 1 : 0,
                'require_api_key'  => ApiManager::normalizeRequireKey(isset($row['require_key']) ? $row['require_key'] : 0),
                'call_count'       => isset($row['call_count']) ? (int) $row['call_count'] : 0,
                'icon'             => $iconRaw !== '' ? ApiCategoryManager::resolveIconUrl($iconRaw) : '',
                'points_cost'      => 0,
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
