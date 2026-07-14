<?php
/**
 * 文件：core/FrontendApi.php
 * 作用：前台主题 · 公开接口列表（统一调度，主题只调用本类）
 *
 * 说明：仅输出审核通过且非禁用的接口；维护中输出 maintenance=1，主题应拦截请求并提示「维护中」。
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

            $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : ApiManager::STATUS_NORMAL);
            if ($status === ApiManager::STATUS_DISABLED) {
                continue;
            }

            if (ApiManager::hasAuditColumn()) {
                $audit = ApiManager::normalizeAuditStatus(
                    isset($row['audit']) ? $row['audit'] : ApiManager::AUDIT_APPROVED
                );
                if ($audit !== ApiManager::AUDIT_APPROVED) {
                    continue;
                }
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
                'id'          => (int) $row['id'],
                'name'        => $name,
                'desc'        => trim((string) (isset($row['description']) ? $row['description'] : '')),
                'category'    => $catKey,
                'method'      => $method,
                'methods'     => array($method),
                'endpoint'    => $endpoint,
                'params'      => isset($row['params']) ? (string) $row['params'] : '',
                'response'    => isset($row['response']) ? (string) $row['response'] : '',
                'doc'         => isset($row['doc']) ? (string) $row['doc'] : '',
                'aidoc'       => isset($row['aidoc']) ? (string) $row['aidoc'] : '',
                'maintenance' => $status === ApiManager::STATUS_MAINTENANCE ? 1 : 0,
                'needkey'     => ApiManager::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : 0),
                'calls'       => isset($row['calls']) ? (int) $row['calls'] : 0,
                'icon'        => $iconRaw !== '' ? ApiCategoryManager::resolveIconUrl($iconRaw) : '',
                'points'      => 0,
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
