<?php
/**
 * 文件：core/UserCallStats.php
 * 作用：公开「个人调用/积分」只读查询（供 api/index.php 等本地接口）
 *
 * 仅读缓存列与 user.stat7，禁止扫 apilog 回填。
 * 主题 / 前台禁止直调本类拼 SQL；用户控制台仍走 FrontendUser::dashboardStats。
 *
 * 返回键：calls / spent / points / today / tspent / week / wspent / rank / rank7
 * 请求：key + 可选 q（字母 a～i 与数字 1～9 等价连写；all / 0 = 全部）
 *   a=1 调用 … i=9 近7日排行；all 或 0 或不传 = 全部
 */

class UserCallStats
{
    /** 规范字段名（短名，对外契约） */
    const FIELD_CALLS = 'calls';
    const FIELD_SPENT = 'spent';
    const FIELD_POINTS = 'points';
    const FIELD_TODAY = 'today';
    const FIELD_TSPENT = 'tspent';
    const FIELD_WEEK = 'week';
    const FIELD_WSPENT = 'wspent';
    /** 今日接口调用排行 */
    const FIELD_RANK = 'rank';
    /** 近 7 日接口调用排行 */
    const FIELD_RANK7 = 'rank7';

    /** 排行最多返回条数 */
    const RANK_LIMIT = 10;

    /** q 码最大长度（防超长乱刷） */
    const Q_MAX_LEN = 32;

    /**
     * 全部可查字段（顺序固定；字母 a～i 与数字 1～9 一一对应）
     *
     * @return array<int,string>
     */
    public static function allFieldKeys()
    {
        return array(
            self::FIELD_CALLS,
            self::FIELD_SPENT,
            self::FIELD_POINTS,
            self::FIELD_TODAY,
            self::FIELD_TSPENT,
            self::FIELD_WEEK,
            self::FIELD_WSPENT,
            self::FIELD_RANK,
            self::FIELD_RANK7,
        );
    }

    /**
     * q 码 → 返回键（字母版与数字版权值相同）
     * a=1 调用，b=2 消耗，c=3 余额，d=4 今日调用，e=5 今日消耗，
     * f=6 近7日调用，g=7 近7日消耗，h=8 今日排行，i=9 近7日排行；
     * 0 / all / 不传 = 全部
     *
     * @return array<string,string>
     */
    public static function codeMap()
    {
        return array(
            'a' => self::FIELD_CALLS,
            '1' => self::FIELD_CALLS,
            'b' => self::FIELD_SPENT,
            '2' => self::FIELD_SPENT,
            'c' => self::FIELD_POINTS,
            '3' => self::FIELD_POINTS,
            'd' => self::FIELD_TODAY,
            '4' => self::FIELD_TODAY,
            'e' => self::FIELD_TSPENT,
            '5' => self::FIELD_TSPENT,
            'f' => self::FIELD_WEEK,
            '6' => self::FIELD_WEEK,
            'g' => self::FIELD_WSPENT,
            '7' => self::FIELD_WSPENT,
            'h' => self::FIELD_RANK,
            '8' => self::FIELD_RANK,
            'i' => self::FIELD_RANK7,
            '9' => self::FIELD_RANK7,
        );
    }

    /**
     * 从 GET/POST 解析要查的字段
     * 仅认参数 q：不传 / 0 / all = 全部；否则为字母/数字连写（如 ac、13、hi、89）
     *
     * @return array{ok:bool,fields?:array<int,string>,unknown?:array<int,string>}
     */
    public static function parseFromRequest()
    {
        $raw = '';
        if (isset($_GET['q']) && (string) $_GET['q'] !== '') {
            $raw = $_GET['q'];
        } elseif (isset($_POST['q']) && (string) $_POST['q'] !== '') {
            $raw = $_POST['q'];
        }
        if ($raw === '') {
            return array('ok' => true, 'fields' => self::allFieldKeys());
        }
        return self::parseFields($raw);
    }

    /**
     * 解析 q：字母 a～i 与数字 1～9 等价，可混写；all 与 0 = 全部。中间不要逗号。
     * 例：a / 1 → calls；ac / 13 → calls+points；all / 0 → 全部
     *
     * @param string|array $raw
     * @return array{ok:bool,fields?:array<int,string>,unknown?:array<int,string>}
     */
    public static function parseFields($raw)
    {
        if (is_array($raw)) {
            $raw = implode('', $raw);
        }
        $raw = strtolower(trim((string) $raw));
        if ($raw === '' || $raw === '0' || $raw === 'all') {
            return array('ok' => true, 'fields' => self::allFieldKeys());
        }
        if (strlen($raw) > self::Q_MAX_LEN || !preg_match('/^[1-9a-i]+$/', $raw)) {
            return array('ok' => false, 'unknown' => array($raw));
        }

        $map = self::codeMap();
        $seen = array();
        $fields = array();
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if (!isset($map[$ch])) {
                return array('ok' => false, 'unknown' => array($ch));
            }
            $key = $map[$ch];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $fields[] = $key;
        }
        if ($fields === array()) {
            return array('ok' => true, 'fields' => self::allFieldKeys());
        }
        return array('ok' => true, 'fields' => $fields);
    }

    /**
     * 从请求解析密钥归属用户（Query / Header / Bearer）
     *
     * @return array{userid:int,keyid:int,ok:bool,errcode?:int,msg?:string}
     */
    public static function resolveUserFromRequest()
    {
        $empty = array('userid' => 0, 'keyid' => 0, 'ok' => false);
        if (!class_exists('ApiKeyManager') || !ApiKeyManager::tableReady()) {
            return array_merge($empty, array(
                'errcode' => ApiError::KEY_SYSTEM,
                'msg'     => '密钥校验暂不可用',
            ));
        }
        $candidates = array();
        $seen = array();
        $push = function ($val) use (&$candidates, &$seen) {
            $val = trim((string) $val);
            if ($val === '' || isset($seen[$val])) {
                return;
            }
            $seen[$val] = true;
            $candidates[] = $val;
        };
        foreach (array('key', 'api_key', 'apikey') as $k) {
            if (isset($_GET[$k])) {
                $push($_GET[$k]);
            }
            if (isset($_POST[$k])) {
                $push($_POST[$k]);
            }
        }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $push($_SERVER['HTTP_X_API_KEY']);
        }
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_AUTHORIZATION') as $hk) {
            if (empty($_SERVER[$hk])) {
                continue;
            }
            if (preg_match('/^\s*Bearer\s+(\S+)/i', (string) $_SERVER[$hk], $m)) {
                $push($m[1]);
            }
        }
        if (!empty($_SERVER['HTTP_X_API_BEARER'])) {
            $line = trim((string) $_SERVER['HTTP_X_API_BEARER']);
            if (preg_match('/^\s*Bearer\s+(\S+)/i', $line, $m)) {
                $push($m[1]);
            } else {
                $push($line);
            }
        }
        if ($candidates === array()) {
            return array_merge($empty, array(
                'errcode' => ApiError::NO_KEY,
                'msg'     => '请提供调用密钥',
            ));
        }
        $sawDisabled = false;
        foreach ($candidates as $raw) {
            $row = ApiKeyManager::findBySecret($raw);
            if (!$row) {
                continue;
            }
            if ((int) $row['status'] !== ApiKeyManager::STATUS_ENABLED) {
                $sawDisabled = true;
                continue;
            }
            return array(
                'ok'     => true,
                'userid' => (int) $row['userid'],
                'keyid'  => (int) $row['id'],
            );
        }
        if ($sawDisabled) {
            return array_merge($empty, array(
                'errcode' => ApiError::KEY_DISABLED,
                'msg'     => '密钥已禁用',
            ));
        }
        return array_merge($empty, array(
            'errcode' => ApiError::BAD_KEY,
            'msg'     => '密钥错误',
        ));
    }

    /**
     * @param int   $userId
     * @param array $fields
     * @return array
     */
    public static function query($userId, array $fields)
    {
        $userId = (int) $userId;
        $out = array();
        if ($userId <= 0 || $fields === array()) {
            return $out;
        }

        $needStat7 = false;
        $needRank = false;
        foreach ($fields as $f) {
            if ($f === self::FIELD_TODAY || $f === self::FIELD_TSPENT
                || $f === self::FIELD_WEEK || $f === self::FIELD_WSPENT) {
                $needStat7 = true;
            }
            if ($f === self::FIELD_RANK || $f === self::FIELD_RANK7) {
                $needRank = true;
                $needStat7 = true;
            }
        }

        $todayCalls = 0;
        $todaySpent = 0.0;
        $weekCalls = 0;
        $weekSpent = 0.0;
        $rankToday = array();
        $rank7 = array();
        if ($needStat7 && class_exists('UserStat7Manager')) {
            $slice = UserStat7Manager::dashboardSlice($userId);
            $todayCalls = isset($slice['today_calls']) ? (int) $slice['today_calls'] : 0;
            $todaySpent = isset($slice['today_cost']) ? round((float) $slice['today_cost'], 4) : 0.0;
            $days = isset($slice['days']) && is_array($slice['days']) ? $slice['days'] : array();
            if (!empty($days['calls']) && is_array($days['calls'])) {
                foreach ($days['calls'] as $n) {
                    $weekCalls += (int) $n;
                }
            }
            if (!empty($days['cost']) && is_array($days['cost'])) {
                foreach ($days['cost'] as $n) {
                    $weekSpent += (float) $n;
                }
            }
            $weekSpent = round($weekSpent, 4);
            if ($needRank) {
                $topToday = isset($slice['top_today']) && is_array($slice['top_today']) ? $slice['top_today'] : array();
                $top7d = isset($slice['top_7d']) && is_array($slice['top_7d']) ? $slice['top_7d'] : array();
                $nameMap = self::apiNameMap(array_merge($topToday, $top7d));
                $rankToday = self::formatRankList($topToday, $nameMap);
                $rank7 = self::formatRankList($top7d, $nameMap);
            }
        }

        foreach ($fields as $f) {
            $f = (string) $f;
            switch ($f) {
                case self::FIELD_CALLS:
                    $out[$f] = (class_exists('ApiKeyManager') && method_exists('ApiKeyManager', 'userKeyCallsTotal'))
                        ? (int) ApiKeyManager::userKeyCallsTotal($userId)
                        : 0;
                    break;
                case self::FIELD_SPENT:
                    $out[$f] = (class_exists('PointsManager') && method_exists('PointsManager', 'spentTotal'))
                        ? round((float) PointsManager::spentTotal($userId), 4)
                        : 0.0;
                    break;
                case self::FIELD_POINTS:
                    $out[$f] = (class_exists('PointsManager') && method_exists('PointsManager', 'balance'))
                        ? round((float) PointsManager::balance($userId), 4)
                        : 0.0;
                    break;
                case self::FIELD_TODAY:
                    $out[$f] = $todayCalls;
                    break;
                case self::FIELD_TSPENT:
                    $out[$f] = $todaySpent;
                    break;
                case self::FIELD_WEEK:
                    $out[$f] = $weekCalls;
                    break;
                case self::FIELD_WSPENT:
                    $out[$f] = $weekSpent;
                    break;
                case self::FIELD_RANK:
                    $out[$f] = $rankToday;
                    break;
                case self::FIELD_RANK7:
                    $out[$f] = $rank7;
                    break;
                default:
                    break;
            }
        }
        return $out;
    }

    /**
     * 排行项：仅 name + calls（对外简洁）
     *
     * @param array $rows
     * @param array $nameMap
     * @return array<int,array{name:string,calls:int}>
     */
    private static function formatRankList(array $rows, array $nameMap)
    {
        $out = array();
        $n = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $n++;
            if ($n > self::RANK_LIMIT) {
                break;
            }
            $aid = isset($row['apiid']) ? (int) $row['apiid'] : 0;
            $calls = isset($row['calls']) ? (int) $row['calls'] : 0;
            $name = isset($nameMap[$aid]) && $nameMap[$aid] !== ''
                ? $nameMap[$aid]
                : ('接口 #' . $aid);
            $out[] = array(
                'name'  => $name,
                'calls' => $calls,
            );
        }
        return $out;
    }

    /**
     * @param array $rows
     * @return array<int,string>
     */
    private static function apiNameMap(array $rows)
    {
        $ids = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['apiid']) ? (int) $row['apiid'] : 0;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === array() || !class_exists('ApiManager') || !ApiManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $idList = array_keys($ids);
            $ph = implode(',', array_fill(0, count($idList), '?'));
            $stmt = $pdo->prepare(
                'SELECT `id`, `name` FROM `' . Database::table('api') . '` WHERE `id` IN (' . $ph . ')'
            );
            $stmt->execute(array_values($idList));
            $map = array();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $map[(int) $r['id']] = (string) $r['name'];
            }
            return $map;
        } catch (Exception $e) {
            return array();
        }
    }
}
