<?php
/**
 * 文件：core/FrontendApi.php
 * 作用：前台主题 · 公开接口列表与详情（统一调度，主题只调用本类）
 *
 * 说明：仅输出审核通过且非禁用的接口；维护中输出 maintenance=1，主题应拦截请求并提示「维护中」。
 * 图标字段非主题通用，未接入图标展示的主题可忽略 icon。
 */

class FrontendApi
{
    /**
     * 将库行转为前台主题用结构
     *
     * @param array $row
     * @return array|null
     */
    public static function formatForTheme(array $row)
    {
        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            return null;
        }

        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : ApiManager::STATUS_NORMAL);
        if ($status === ApiManager::STATUS_DISABLED) {
            return null;
        }

        if (ApiManager::hasAuditColumn()) {
            $audit = ApiManager::normalizeAuditStatus(
                isset($row['audit']) ? $row['audit'] : ApiManager::AUDIT_APPROVED
            );
            if ($audit !== ApiManager::AUDIT_APPROVED) {
                return null;
            }
        }

        $catLabel = trim((string) (isset($row['category']) ? $row['category'] : ''));
        $catKey = FrontendCategory::resolveIdByName($catLabel);

        $methods = ApiManager::normalizeMethods(isset($row['method']) ? $row['method'] : ApiManager::METHOD_GET);
        $primaryMethod = isset($methods[0]) ? $methods[0] : ApiManager::METHOD_GET;
        $callUrl = ApiManager::resolveCallUrl($row);
        $endpoint = $callUrl !== ''
            ? $callUrl
            : trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
        $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';
        $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0);
        $id = (int) (isset($row['id']) ? $row['id'] : 0);

        return array(
            'id'          => $id,
            'name'        => $name,
            'desc'        => trim((string) (isset($row['description']) ? $row['description'] : '')),
            'category'    => $catKey,
            'category_name' => $catLabel,
            'method'      => $primaryMethod,
            'methods'     => $methods,
            'method_label'=> ApiManager::methodsLabel($methods),
            'endpoint'    => $endpoint,
            'apitype'     => $apitype,
            'params'      => isset($row['params']) ? (string) $row['params'] : '',
            'response'    => isset($row['response']) ? (string) $row['response'] : '',
            'doc'         => isset($row['doc']) ? (string) $row['doc'] : '',
            'aidoc'       => isset($row['aidoc']) ? (string) $row['aidoc'] : '',
            'maintenance' => $status === ApiManager::STATUS_MAINTENANCE ? 1 : 0,
            'needkey'     => ApiManager::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : 0),
            'needkey_label' => ApiManager::requireKeyLabel(isset($row['needkey']) ? $row['needkey'] : 0),
            'calls'       => isset($row['calls']) ? (int) $row['calls'] : 0,
            'icon'        => $iconRaw !== '' ? ApiCategoryManager::resolveIconUrl($iconRaw) : '',
            'detail_url'  => $id > 0 ? vs_api_detail_url($id) : '',
            'charge'      => ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0),
            'points'      => ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0) === ApiManager::CHARGE_PAID
                ? (float) ApiManager::normalizePrice(isset($row['price']) ? $row['price'] : 0)
                : 0,
        );
    }

    /**
     * 供前台主题 / JS 使用的公开接口列表
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        return RedisCache::remember(
            RedisCache::KEY_FRONTEND_API,
            RedisCache::TTL_FRONTEND_API,
            function () {
                $apiData = array();
                foreach (ApiManager::listPublic() as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = self::formatForTheme($row);
                    if ($item !== null) {
                        $apiData[] = $item;
                    }
                }
                return $apiData;
            }
        );
    }

    /**
     * 按 ID 取前台可展示的单条接口（审核通过且非禁用）
     *
     * @param int $apiId
     * @return array|null
     */
    public static function findForThemeById($apiId)
    {
        $apiId = (int) $apiId;
        if ($apiId <= 0) {
            return null;
        }
        $row = ApiManager::findById($apiId);
        if (!is_array($row)) {
            return null;
        }
        return self::formatForTheme($row);
    }

    /**
     * @return int
     */
    public static function countForTheme()
    {
        return count(self::listForTheme());
    }
}
