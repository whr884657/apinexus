<?php
/**
 * 文件：core/FrontendApi.php
 * 作用：前台主题 · 公开接口列表与详情（统一调度，主题只调用本类）
 *
 * 说明：列表仅输出审核通过且非禁用的接口；详情允许已禁用（disabled=1，敏感地址脱敏）。
 * 维护中输出 maintenance=1，主题应拦截请求并提示「维护中」。
 * 图标字段非主题通用，未接入图标展示的主题可忽略 icon。
 */

class FrontendApi
{
    /**
     * 将库行转为前台主题用结构
     *
     * @param array $row
     * @param bool  $withAuthor 仅详情需要；列表禁止拉取作者，避免 findProfile↔formatForTheme 递归爆内存
     * @param bool  $forDetail  详情页允许已禁用（仍须审核通过）；列表保持排除
     * @return array|null
     */
    public static function formatForTheme(array $row, $withAuthor = false, $forDetail = false)
    {
        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            return null;
        }

        $status = ApiManager::normalizeStatus(isset($row['status']) ? $row['status'] : ApiManager::STATUS_NORMAL);
        if ($status === ApiManager::STATUS_DISABLED && !$forDetail) {
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
        $callPath = ApiManager::resolveCallPath($row);
        $endpoint = ApiManager::resolveCallUrl($row);
        if ($endpoint === '') {
            $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));
        }
        $iconRaw = isset($row['icon']) ? (string) $row['icon'] : '';
        $iconUrl = $iconRaw !== '' ? ApiCategoryManager::resolveIconUrl($iconRaw) : '';
        $iconPath = self::siteAssetPathFromUrl($iconUrl);
        $apitype = ApiManager::normalizeApiType(isset($row['apitype']) ? $row['apitype'] : 0);
        $id = (int) (isset($row['id']) ? $row['id'] : 0);
        $disabled = $status === ApiManager::STATUS_DISABLED ? 1 : 0;
        if ($disabled) {
            // 禁用详情仍可打开，但真实调用地址不下发（主题用模糊占位）；示例/文档可能含完整 URL，一并清空
            $callPath = '';
            $endpoint = '';
        }

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
            'call_path'   => $callPath,
            'apitype'     => $apitype,
            'params'      => isset($row['params']) ? (string) $row['params'] : '',
            'response'    => isset($row['response']) ? (string) $row['response'] : '',
            'doc'         => $disabled ? '' : (isset($row['doc']) ? (string) $row['doc'] : ''),
            'aidoc'       => $disabled ? '' : (isset($row['aidoc']) ? (string) $row['aidoc'] : ''),
            'maintenance' => $status === ApiManager::STATUS_MAINTENANCE ? 1 : 0,
            'disabled'    => $disabled,
            'needkey'     => ApiManager::normalizeRequireKey(isset($row['needkey']) ? $row['needkey'] : 0),
            'needkey_label' => ApiManager::requireKeyLabel(isset($row['needkey']) ? $row['needkey'] : 0),
            'keyways'     => ApiManager::normalizeKeyways(isset($row['keyways']) ? $row['keyways'] : ApiManager::KEYWAY_QUERY),
            'keyways_label' => ApiManager::keywaysLabel(isset($row['keyways']) ? $row['keyways'] : ApiManager::KEYWAY_QUERY),
            'qpm'         => ApiManager::normalizeQpm(isset($row['qpm']) ? $row['qpm'] : 0),
            'qpm_label'   => ApiManager::qpmLabel(isset($row['qpm']) ? $row['qpm'] : 0),
            'calls'       => isset($row['calls']) ? (int) $row['calls'] : 0,
            'icon'        => $iconUrl,
            'icon_path'   => $iconPath,
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
            'author'      => $withAuthor ? self::authorForTheme(isset($row['userid']) ? (int) $row['userid'] : 0) : null,
        );
    }

    /**
     * 从绝对 URL 取出本站 /assets/… 路径；外链或非本站资源返回空
     *
     * @param string $url
     * @return string
     */
    private static function siteAssetPathFromUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (isset($url[0]) && $url[0] === '/' && strpos($url, '/assets/') === 0) {
            return $url;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || strpos($path, '/assets/') !== 0) {
            return '';
        }
        return $path;
    }

    /**
     * 按当前访问域名重绑 endpoint / detail_url / 本站图标（Redis 只缓存路径）
     *
     * @param array $item
     * @return array
     */
    public static function bindRequestHost(array $item)
    {
        $id = (int) (isset($item['id']) ? $item['id'] : 0);

        $path = '';
        if (isset($item['call_path']) && trim((string) $item['call_path']) !== '') {
            $path = trim((string) $item['call_path']);
        } else {
            $path = self::legacyCallPathFromEndpoint(isset($item['endpoint']) ? $item['endpoint'] : '');
        }

        if ($path !== '' && preg_match('#^https?://#i', $path)) {
            $item['call_path'] = $path;
            $item['endpoint'] = $path;
        } elseif ($path !== '') {
            // 仅允许站内绝对路径；拒绝 //evil、含 .. 或控制字符的脏路径
            if (!self::isSafeSiteCallPath($path)) {
                $item['call_path'] = '';
                $epNow = isset($item['endpoint']) ? (string) $item['endpoint'] : '';
                if ($epNow === '' || !preg_match('#^https?://#i', $epNow)) {
                    $item['endpoint'] = '';
                }
            } else {
                if ($path[0] !== '/') {
                    $path = '/' . $path;
                }
                $item['call_path'] = $path;
                $item['endpoint'] = vs_site_path($path);
            }
        }

        $item['detail_url'] = $id > 0 ? vs_api_detail_url($id) : '';

        $iconPath = '';
        if (isset($item['icon_path']) && trim((string) $item['icon_path']) !== '') {
            $iconPath = trim((string) $item['icon_path']);
        } else {
            $iconPath = self::siteAssetPathFromUrl(isset($item['icon']) ? $item['icon'] : '');
        }
        if ($iconPath !== '' && self::isSafeSiteCallPath($iconPath) && strpos($iconPath, '/assets/') === 0) {
            if ($iconPath[0] !== '/') {
                $iconPath = '/' . $iconPath;
            }
            $item['icon_path'] = $iconPath;
            $item['icon'] = vs_site_path($iconPath);
        }

        return $item;
    }

    /**
     * 站内相对调用路径是否安全（禁止协议相对 URL、路径穿越、空白控制符）
     *
     * @param string $path
     * @return bool
     */
    private static function isSafeSiteCallPath($path)
    {
        $path = (string) $path;
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return false;
        }
        if (isset($path[0]) && $path[0] === '/' && isset($path[1]) && $path[1] === '/') {
            return false;
        }
        if (strpos($path, "\0") !== false || preg_match('/[\x00-\x1f\x7f]/', $path)) {
            return false;
        }
        if (strpos($path, '..') !== false) {
            return false;
        }
        return true;
    }

    /**
     * @param array<int, array> $list
     * @return array<int, array>
     */
    public static function bindRequestHostToList(array $list)
    {
        $out = array();
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = self::bindRequestHost($item);
        }
        return $out;
    }

    /**
     * 兼容旧缓存：从绝对 endpoint 还原路径（新缓存应带 call_path；外链完整 URL 亦写在 call_path）
     *
     * @param string $endpoint
     * @return string
     */
    private static function legacyCallPathFromEndpoint($endpoint)
    {
        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $endpoint)) {
            return $endpoint[0] === '/' ? $endpoint : '/' . $endpoint;
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['path'])) {
            return $endpoint;
        }
        $path = (string) $parts['path'];
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        // 旧缓存里本站入口域名已烤死：一律只留路径，按当前访问域名重绑
        return $path;
    }

    /**
     * 详情页作者轻量卡（禁止走 findProfile，避免再拉接口列表形成递归）
     *
     * @param int $userId
     * @return array{id:int,username:string,avatar:string,profile_url:string}|null
     */
    private static function authorForTheme($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !class_exists('UserRole') || !class_exists('Database')) {
            return null;
        }
        static $cache = array();
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `avatar`, `role`, `status`'
                . ' FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['status'] !== 1) {
                return $cache[$userId] = null;
            }
            if (UserRole::normalize(isset($row['role']) ? $row['role'] : '') !== UserRole::ROLE_DEVELOPER) {
                return $cache[$userId] = null;
            }
            $avatar = isset($row['avatar']) ? trim((string) $row['avatar']) : '';
            return $cache[$userId] = array(
                'id'          => (int) $row['id'],
                'username'    => isset($row['username']) ? (string) $row['username'] : '',
                'avatar'      => $avatar,
                'profile_url' => vs_profile_url($userId),
            );
        } catch (Exception $e) {
            return $cache[$userId] = null;
        }
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
     * Redis 缓存条目含 call_path（路径或外链绝对地址）；每次取出后按当前访问域名重绑 endpoint / detail_url。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForTheme()
    {
        $factory = function () {
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
        };
        if (class_exists('RedisCache')) {
            $cached = RedisCache::remember(RedisCache::KEY_FRONTEND_API, RedisCache::TTL_FRONTEND_API, $factory);
            if (!is_array($cached)) {
                return self::bindRequestHostToList($factory());
            }
            return self::bindRequestHostToList($cached);
        }
        return self::bindRequestHostToList($factory());
    }

    /**
     * 公开目录用条目：去掉 doc/aidoc/response 大字段（首页调试仍保留 params）
     *
     * @param array $item
     * @return array
     */
    public static function slimForCatalog(array $item)
    {
        unset($item['doc'], $item['aidoc'], $item['response']);
        return $item;
    }

    /**
     * 前台目录接口专用列表（listForTheme + slim，减少泄露与响应体积）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForCatalog()
    {
        $out = array();
        foreach (self::listForTheme() as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = self::slimForCatalog($item);
        }
        return $out;
    }

    /**
     * 按 ID 取前台可展示的单条接口（审核通过；详情允许已禁用）
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
        $item = self::formatForTheme($row, true, true);
        return is_array($item) ? self::bindRequestHost($item) : null;
    }

    /**
     * @return int
     */
    public static function countForTheme()
    {
        return ApiManager::countPublic();
    }
}
