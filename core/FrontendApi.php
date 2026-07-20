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
            'charge_label'=> ApiManager::chargeLabel(isset($row['charge']) ? $row['charge'] : 0),
            'points'      => ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0) === ApiManager::CHARGE_PAID
                ? (float) ApiManager::normalizePrice(isset($row['price']) ? $row['price'] : 0)
                : 0,
            'billing_label' => self::billingLabel(
                ApiManager::normalizeCharge(isset($row['charge']) ? $row['charge'] : 0),
                isset($row['price']) ? $row['price'] : 0
            ),
            'createtime'  => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'params_list' => self::parseParamsList(isset($row['params']) ? (string) $row['params'] : ''),
        );
    }

    /**
     * 前台计费文案：免费 / N积分/次
     *
     * @param int   $charge
     * @param mixed $price
     * @return string
     */
    public static function billingLabel($charge, $price = 0)
    {
        if (ApiManager::normalizeCharge($charge) !== ApiManager::CHARGE_PAID) {
            return '免费';
        }
        $points = (float) ApiManager::normalizePrice($price);
        if ($points <= 0) {
            return '收费';
        }
        $fmt = rtrim(rtrim(number_format($points, 4, '.', ''), '0'), '.');
        return $fmt . '积分/次';
    }

    /**
     * 解析 params JSON 数组（管理端表格结构）；失败返回空数组
     *
     * @param string $raw
     * @return array<int, array{name:string,type:string,required:bool,description:string,example:string}>
     */
    public static function parseParamsList($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $out = array();
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = '';
            if (isset($item['name'])) {
                $name = trim((string) $item['name']);
            } elseif (isset($item['key'])) {
                $name = trim((string) $item['key']);
            }
            if ($name === '') {
                continue;
            }
            $desc = '';
            if (isset($item['description'])) {
                $desc = trim((string) $item['description']);
            } elseif (isset($item['desc'])) {
                $desc = trim((string) $item['desc']);
            }
            $out[] = array(
                'name'        => $name,
                'type'        => isset($item['type']) ? trim((string) $item['type']) : 'string',
                'required'    => !empty($item['required']),
                'description' => $desc,
                'example'     => isset($item['example']) ? trim((string) $item['example']) : '',
            );
        }
        return $out;
    }

    /**
     * 美化 params JSON（供详情 JSON 视图）
     *
     * @param string $raw
     * @return string
     */
    public static function prettyParamsJson($raw)
    {
        $list = self::parseParamsList($raw);
        if ($list === array()) {
            $raw = trim((string) $raw);
            return $raw;
        }
        return (string) json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

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
            $item = self::formatForTheme($row);
            if ($item !== null) {
                $apiData[] = $item;
            }
        }
        usort($apiData, function ($a, $b) {
            $ca = isset($a['calls']) ? (int) $a['calls'] : 0;
            $cb = isset($b['calls']) ? (int) $b['calls'] : 0;
            if ($ca !== $cb) {
                return $cb - $ca;
            }
            return (int) (isset($b['id']) ? $b['id'] : 0) - (int) (isset($a['id']) ? $a['id'] : 0);
        });
        return $apiData;
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
