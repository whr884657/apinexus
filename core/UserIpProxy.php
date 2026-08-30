<?php
/**
 * 文件：core/UserIpProxy.php
 * 作用：用户自备出口 IP 代理（隧道 / 提取）；每用户最多 5 条
 *
 * 调用侧仅传 vsproxy：三位短码=固定该条；a=轮询；b=随机；c=优先首条；1/true=启用并按账号已存策略。
 * 与 ApiProxy（反向中继上游 URL）无关：本类只负责 CURLOPT_PROXY 出站换出口 IP。
 */

class UserIpProxy
{
    const MAX_COUNT = 5;

    const MODE_TUNNEL = 0;
    const MODE_EXTRACT = 1;

    const PROTO_HTTP = 0;
    const PROTO_HTTPS = 1;
    const PROTO_SOCKS5 = 2;
    const PROTO_SOCKS4 = 3;

    const STRATEGY_ROUND = 0;
    const STRATEGY_RANDOM = 1;
    const STRATEGY_FIRST = 2;

    const EXTFMT_AUTO = 0;
    const EXTFMT_TEXT = 1;
    const EXTFMT_JSON = 2;

    /** 公网连通性测试地址（知名站点，按序尝试） */
    const TEST_URL = 'https://www.baidu.com/';

    /**
     * @return array<int, string>
     */
    public static function publicTestUrls()
    {
        return array(
            'https://www.baidu.com/',
            'https://v1.hitokoto.cn/',
            'https://www.qq.com/',
        );
    }

    /** @var bool|null 由网关收集参数时注入，避免与 php://input 争用 */
    private static $wantOverride = null;

    /** @var string|null 三位调用短码（空=按策略） */
    private static $codeOverride = null;

    /** @var int|null 本请求策略覆盖；null=用账号已存策略 */
    private static $strategyOverride = null;

    /**
     * 解析 vsproxy 单参数
     *
     * @param mixed $raw
     * @return array{want:bool,code:string,strategy:int|null}
     */
    public static function parseVsproxyValue($raw)
    {
        if ($raw === null || is_array($raw)) {
            return array('want' => false, 'code' => '', 'strategy' => null);
        }
        $s = strtolower(trim((string) $raw));
        if ($s === '') {
            return array('want' => false, 'code' => '', 'strategy' => null);
        }
        if ($s === 'a') {
            return array('want' => true, 'code' => '', 'strategy' => self::STRATEGY_ROUND);
        }
        if ($s === 'b') {
            return array('want' => true, 'code' => '', 'strategy' => self::STRATEGY_RANDOM);
        }
        if ($s === 'c') {
            return array('want' => true, 'code' => '', 'strategy' => self::STRATEGY_FIRST);
        }
        $code = self::normalizeProxyCode($s);
        if ($code !== '') {
            return array('want' => true, 'code' => $code, 'strategy' => null);
        }
        if (self::truthyFlag($s)) {
            return array('want' => true, 'code' => '', 'strategy' => null);
        }
        return array('want' => false, 'code' => '', 'strategy' => null);
    }

    /**
     * 网关 / 统计层注入本请求出口意图
     *
     * @param bool     $want
     * @param string   $proxyCode 三位短码；空表示不指定
     * @param int|null $strategy  0/1/2 覆盖；null=用账号策略
     * @return void
     */
    public static function noteRequestFlags($want, $proxyCode = '', $strategy = null)
    {
        self::$wantOverride = (bool) $want;
        $code = self::normalizeProxyCode($proxyCode);
        self::$codeOverride = ($code !== '') ? $code : '';
        if ($strategy === null) {
            self::$strategyOverride = null;
        } else {
            $n = (int) $strategy;
            if ($n === self::STRATEGY_RANDOM || $n === self::STRATEGY_FIRST || $n === self::STRATEGY_ROUND) {
                self::$strategyOverride = $n;
            } else {
                self::$strategyOverride = self::STRATEGY_ROUND;
            }
        }
    }

    /**
     * 规范化调用短码：仅接受恰好 3 位 [0-9a-z]（小写）；拒绝纯数字主键用法
     *
     * @param mixed $raw
     * @return string
     */
    public static function normalizeProxyCode($raw)
    {
        $s = strtolower(trim((string) $raw));
        if ($s === '' || !preg_match('/^[0-9a-z]{3}$/', $s)) {
            return '';
        }
        return $s;
    }

    /**
     * 生成用户内唯一的三位随机短码
     *
     * @param int $userId
     * @return string
     */
    public static function generateProxyCode($userId)
    {
        $userId = (int) $userId;
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $len = strlen($chars);
        for ($attempt = 0; $attempt < 80; $attempt++) {
            $code = '';
            for ($i = 0; $i < 3; $i++) {
                $code .= $chars[random_int(0, $len - 1)];
            }
            if ($userId <= 0 || !self::tableReady()) {
                return $code;
            }
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM `' . Database::table('ipproxy') . '` WHERE `userid` = ? AND `proxycode` = ? LIMIT 1'
                );
                $stmt->execute(array($userId, $code));
                if (!$stmt->fetchColumn()) {
                    return $code;
                }
            } catch (Exception $e) {
                return $code;
            }
        }
        // 极端碰撞：时间片后缀仍取 3 位
        return substr(strtolower(base_convert((string) (time() % 46656), 10, 36) . '000'), 0, 3);
    }

    /**
     * 为缺少短码的历史行回填（Migrator / 列表前调用）
     *
     * @return void
     */
    public static function backfillMissingProxyCodes()
    {
        if (!self::tableReady() || !self::tableColumnExistsLocal('proxycode')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT `id`,`userid` FROM `' . Database::table('ipproxy') . '` WHERE `proxycode` = \'\' OR `proxycode` IS NULL'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
            if (!is_array($rows)) {
                return;
            }
            $upd = $pdo->prepare(
                'UPDATE `' . Database::table('ipproxy') . '` SET `proxycode` = ? WHERE `id` = ? AND `userid` = ?'
            );
            foreach ($rows as $row) {
                $uid = isset($row['userid']) ? (int) $row['userid'] : 0;
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($uid <= 0 || $id <= 0) {
                    continue;
                }
                $code = self::generateProxyCode($uid);
                $upd->execute(array($code, $id, $uid));
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * @param string $col
     * @return bool
     */
    private static function tableColumnExistsLocal($col)
    {
        if (class_exists('DatabaseMigrator') && method_exists('DatabaseMigrator', 'tableColumnExists')) {
            return DatabaseMigrator::tableColumnExists('ipproxy', $col);
        }
        return true;
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = class_exists('DatabaseMigrator')
                ? DatabaseMigrator::tableExists('ipproxy')
                : false;
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * @return bool
     */
    public static function strategyColumnReady()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = class_exists('DatabaseMigrator')
                ? DatabaseMigrator::tableColumnExists('user', 'proxystrategy')
                : false;
        } catch (Exception $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 请求是否声明启用出口代理（优先 noteRequestFlags；否则解析 vsproxy）
     *
     * @return bool
     */
    public static function requestWantsEgress()
    {
        if (self::$wantOverride !== null) {
            return self::$wantOverride;
        }
        $p = self::parseVsproxyValue(self::readRequestValue('vsproxy'));
        return !empty($p['want']);
    }

    /**
     * 可选：指定代理配置短码（三位）；空=按策略在多条启用配置中选用
     *
     * @return string
     */
    public static function requestProxyCode()
    {
        if (self::$codeOverride !== null) {
            return self::normalizeProxyCode(self::$codeOverride);
        }
        $p = self::parseVsproxyValue(self::readRequestValue('vsproxy'));
        return isset($p['code']) ? (string) $p['code'] : '';
    }

    /**
     * 本请求选用策略（a/b/c 覆盖；否则账号已存策略）
     *
     * @param int $userId
     * @return int
     */
    public static function requestStrategy($userId)
    {
        if (self::$strategyOverride !== null) {
            return (int) self::$strategyOverride;
        }
        $p = self::parseVsproxyValue(self::readRequestValue('vsproxy'));
        if (isset($p['strategy']) && $p['strategy'] !== null) {
            return (int) $p['strategy'];
        }
        return self::strategyForUser($userId);
    }

    /**
     * @deprecated 已改为短码；保留空壳避免旧调用致命错误
     * @return int
     */
    public static function requestProxyId()
    {
        return 0;
    }

    /**
     * @param mixed $v
     * @return bool
     */
    public static function truthyFlag($v)
    {
        $s = strtolower(trim((string) $v));
        return ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'on');
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    private static function readRequestValue($name)
    {
        $name = (string) $name;
        if ($name === '') {
            return null;
        }
        if (isset($_GET[$name]) && !is_array($_GET[$name])) {
            return $_GET[$name];
        }
        if (isset($_POST[$name]) && !is_array($_POST[$name])) {
            return $_POST[$name];
        }
        return null;
    }

    /**
     * @param int $userId
     * @return int 0|1|2
     */
    public static function strategyForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::strategyColumnReady()) {
            return self::STRATEGY_ROUND;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `proxystrategy` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $v = $stmt->fetchColumn();
            $n = (int) $v;
            if ($n === self::STRATEGY_RANDOM || $n === self::STRATEGY_FIRST) {
                return $n;
            }
            return self::STRATEGY_ROUND;
        } catch (Exception $e) {
            return self::STRATEGY_ROUND;
        }
    }

    /**
     * @param int $userId
     * @param int $strategy
     * @return array{ok:bool,msg:string,strategy?:int}
     */
    public static function saveStrategy($userId, $strategy)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '用户无效');
        }
        if (!self::strategyColumnReady()) {
            return array('ok' => false, 'msg' => '出口代理尚未就绪，请联系管理员完成系统升级');
        }
        $strategy = (int) $strategy;
        if ($strategy !== self::STRATEGY_ROUND
            && $strategy !== self::STRATEGY_RANDOM
            && $strategy !== self::STRATEGY_FIRST) {
            return array('ok' => false, 'msg' => '选用策略无效');
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `proxystrategy` = ? WHERE `id` = ?'
            );
            $stmt->execute(array($strategy, $userId));
            return array('ok' => true, 'msg' => '策略已保存', 'strategy' => $strategy);
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '保存失败，请稍后重试');
        }
    }

    /**
     * 列表（不含密码明文）
     *
     * @param int $userId
     * @return array{ok:bool,msg:string,list?:array,count?:int,max?:int,strategy?:int}
     */
    public static function listForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '用户无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '出口代理尚未就绪，请联系管理员完成系统升级');
        }
        self::backfillMissingProxyCodes();
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`,`title`,`mode`,`proto`,`host`,`port`,`username`,`extract`,`extfmt`,`jsonhost`,`jsonport`,'
                . '`proxycode`,`ttlmin`,`cachehost`,`cacheport`,`cacheexp`,`status`,`sort`,`createtime`,`updatetime`,'
                . ' CASE WHEN `password` = \'\' THEN 0 ELSE 1 END AS `haspass`'
                . ' FROM `' . Database::table('ipproxy') . '`'
                . ' WHERE `userid` = ? ORDER BY `sort` ASC, `id` ASC'
            );
            $stmt->execute(array($userId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $list[] = self::formatPublicRow($row);
                }
            }
            return array(
                'ok'       => true,
                'msg'      => 'ok',
                'list'     => $list,
                'count'    => count($list),
                'max'      => self::MAX_COUNT,
                'strategy' => self::strategyForUser($userId),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '读取失败，请稍后重试');
        }
    }

    /**
     * @param array $row
     * @return array
     */
    public static function formatPublicRow(array $row)
    {
        $ttlmin = isset($row['ttlmin']) ? (int) $row['ttlmin'] : 10;
        if ($ttlmin < 0) {
            $ttlmin = 0;
        }
        if ($ttlmin > 10080) {
            $ttlmin = 10080;
        }
        return array(
            'id'         => isset($row['id']) ? (int) $row['id'] : 0,
            'title'      => isset($row['title']) ? (string) $row['title'] : '',
            'mode'       => isset($row['mode']) ? (int) $row['mode'] : self::MODE_TUNNEL,
            'proto'      => isset($row['proto']) ? (int) $row['proto'] : self::PROTO_HTTP,
            'host'       => isset($row['host']) ? (string) $row['host'] : '',
            'port'       => isset($row['port']) ? (int) $row['port'] : 0,
            'username'   => isset($row['username']) ? (string) $row['username'] : '',
            'extract'    => isset($row['extract']) ? (string) $row['extract'] : '',
            'extfmt'     => isset($row['extfmt']) ? (int) $row['extfmt'] : self::EXTFMT_AUTO,
            'jsonhost'   => isset($row['jsonhost']) ? (string) $row['jsonhost'] : '',
            'jsonport'   => isset($row['jsonport']) ? (string) $row['jsonport'] : '',
            'proxycode'  => isset($row['proxycode']) ? (string) $row['proxycode'] : '',
            'ttlmin'     => $ttlmin,
            'cachehost'  => isset($row['cachehost']) ? (string) $row['cachehost'] : '',
            'cacheport'  => isset($row['cacheport']) ? (int) $row['cacheport'] : 0,
            'cacheexp'   => isset($row['cacheexp']) ? (string) $row['cacheexp'] : '',
            'status'     => isset($row['status']) ? (int) $row['status'] : 0,
            'sort'       => isset($row['sort']) ? (int) $row['sort'] : 0,
            'haspass'    => !empty($row['haspass']) || (isset($row['password']) && (string) $row['password'] !== ''),
            'createtime' => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime' => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
            'protolabel' => self::protoLabel(isset($row['proto']) ? (int) $row['proto'] : 0),
            'modelabel'  => self::modeLabel(isset($row['mode']) ? (int) $row['mode'] : 0),
        );
    }

    /**
     * @param int $proto
     * @return string
     */
    public static function protoLabel($proto)
    {
        $map = array(
            self::PROTO_HTTP  => 'HTTP',
            self::PROTO_HTTPS => 'HTTPS',
            self::PROTO_SOCKS5 => 'SOCKS5',
            self::PROTO_SOCKS4 => 'SOCKS4',
        );
        $proto = (int) $proto;
        return isset($map[$proto]) ? $map[$proto] : 'HTTP';
    }

    /**
     * @param int $mode
     * @return string
     */
    public static function modeLabel($mode)
    {
        return ((int) $mode === self::MODE_EXTRACT) ? '提取 API' : '隧道';
    }

    /**
     * 新增或更新
     *
     * @param int   $userId
     * @param array $input
     * @return array{ok:bool,msg:string,row?:array,list?:array}
     */
    public static function save($userId, array $input)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '用户无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '出口代理尚未就绪，请联系管理员完成系统升级');
        }

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $parsed = self::parseInput($input, $id > 0);
        if (empty($parsed['ok'])) {
            return array('ok' => false, 'msg' => isset($parsed['msg']) ? $parsed['msg'] : '参数无效');
        }
        $data = $parsed['data'];

        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');

            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `id` = ? AND `userid` = ? LIMIT 1');
                $stmt->execute(array($id, $userId));
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    return array('ok' => false, 'msg' => '记录不存在');
                }
                $password = $data['password'];
                if ($password === null) {
                    $password = isset($old['password']) ? (string) $old['password'] : '';
                }
                $clearCache = (
                    (string) (isset($old['extract']) ? $old['extract'] : '') !== (string) $data['extract']
                    || (int) (isset($old['extfmt']) ? $old['extfmt'] : 0) !== (int) $data['extfmt']
                    || (string) (isset($old['jsonhost']) ? $old['jsonhost'] : '') !== (string) $data['jsonhost']
                    || (string) (isset($old['jsonport']) ? $old['jsonport'] : '') !== (string) $data['jsonport']
                );
                $sql = 'UPDATE `' . $table . '` SET'
                    . ' `title`=?,`mode`=?,`proto`=?,`host`=?,`port`=?,`username`=?,`password`=?,`extract`=?,`extfmt`=?,`jsonhost`=?,`jsonport`=?,`ttlmin`=?,`status`=?,`sort`=?,`updatetime`=NOW()';
                if ($clearCache) {
                    $sql .= ',`cachehost`=\'\',`cacheport`=0,`cacheexp`=NULL';
                }
                $sql .= ' WHERE `id`=? AND `userid`=?';
                $upd = $pdo->prepare($sql);
                $upd->execute(array(
                    $data['title'],
                    $data['mode'],
                    $data['proto'],
                    $data['host'],
                    $data['port'],
                    $data['username'],
                    $password,
                    $data['extract'],
                    $data['extfmt'],
                    $data['jsonhost'],
                    $data['jsonport'],
                    $data['ttlmin'],
                    $data['status'],
                    $data['sort'],
                    $id,
                    $userId,
                ));
            } else {
                $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `userid` = ?');
                $cntStmt->execute(array($userId));
                if ((int) $cntStmt->fetchColumn() >= self::MAX_COUNT) {
                    return array('ok' => false, 'msg' => '每个账号最多保存 ' . self::MAX_COUNT . ' 条出口代理');
                }
                $password = $data['password'] === null ? '' : $data['password'];
                $proxycode = self::generateProxyCode($userId);
                $ins = $pdo->prepare(
                    'INSERT INTO `' . $table . '`'
                    . ' (`userid`,`title`,`mode`,`proto`,`host`,`port`,`username`,`password`,`extract`,`extfmt`,`jsonhost`,`jsonport`,`proxycode`,`ttlmin`,`status`,`sort`,`createtime`)'
                    . ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                );
                $ins->execute(array(
                    $userId,
                    $data['title'],
                    $data['mode'],
                    $data['proto'],
                    $data['host'],
                    $data['port'],
                    $data['username'],
                    $password,
                    $data['extract'],
                    $data['extfmt'],
                    $data['jsonhost'],
                    $data['jsonport'],
                    $proxycode,
                    $data['ttlmin'],
                    $data['status'],
                    $data['sort'],
                ));
                $id = (int) $pdo->lastInsertId();
            }

            $listPack = self::listForUser($userId);
            $rowPub = null;
            if (!empty($listPack['list']) && is_array($listPack['list'])) {
                foreach ($listPack['list'] as $item) {
                    if ((int) $item['id'] === $id) {
                        $rowPub = $item;
                        break;
                    }
                }
            }
            return array(
                'ok'   => true,
                'msg'  => '已保存',
                'row'  => $rowPub,
                'list' => isset($listPack['list']) ? $listPack['list'] : array(),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '保存失败，请稍后重试');
        }
    }

    /**
     * @param int $userId
     * @param int $id
     * @return array{ok:bool,msg:string,list?:array}
     */
    public static function delete($userId, $id)
    {
        $userId = (int) $userId;
        $id = (int) $id;
        if ($userId <= 0 || $id <= 0) {
            return array('ok' => false, 'msg' => '参数无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '出口代理尚未就绪，请联系管理员完成系统升级');
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'DELETE FROM `' . Database::table('ipproxy') . '` WHERE `id` = ? AND `userid` = ?'
            );
            $stmt->execute(array($id, $userId));
            if ($stmt->rowCount() < 1) {
                return array('ok' => false, 'msg' => '记录不存在');
            }
            $listPack = self::listForUser($userId);
            return array(
                'ok'   => true,
                'msg'  => '已删除',
                'list' => isset($listPack['list']) ? $listPack['list'] : array(),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '删除失败，请稍后重试');
        }
    }

    /**
     * @param array $input
     * @param bool  $isUpdate
     * @return array{ok:bool,msg?:string,data?:array}
     */
    private static function parseInput(array $input, $isUpdate)
    {
        $title = isset($input['title']) ? trim((string) $input['title']) : '';
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 60);
        } else {
            $title = substr($title, 0, 60);
        }
        if ($title === '') {
            $title = '代理';
        }

        $mode = isset($input['mode']) ? (int) $input['mode'] : self::MODE_TUNNEL;
        if ($mode !== self::MODE_EXTRACT) {
            $mode = self::MODE_TUNNEL;
        }

        $proto = isset($input['proto']) ? (int) $input['proto'] : self::PROTO_HTTP;
        if ($proto < self::PROTO_HTTP || $proto > self::PROTO_SOCKS4) {
            return array('ok' => false, 'msg' => '代理协议无效');
        }

        $host = isset($input['host']) ? trim((string) $input['host']) : '';
        $host = preg_replace('/\s+/', '', $host);
        if (function_exists('mb_substr')) {
            $host = mb_substr($host, 0, 255);
        } else {
            $host = substr($host, 0, 255);
        }

        $port = isset($input['port']) ? (int) $input['port'] : 0;
        if ($port < 0 || $port > 65535) {
            return array('ok' => false, 'msg' => '端口无效');
        }

        $username = isset($input['username']) ? (string) $input['username'] : '';
        if (function_exists('mb_substr')) {
            $username = mb_substr($username, 0, 200);
        } else {
            $username = substr($username, 0, 200);
        }

        $password = null;
        if (array_key_exists('password', $input)) {
            $password = (string) $input['password'];
            if (function_exists('mb_substr')) {
                $password = mb_substr($password, 0, 200);
            } else {
                $password = substr($password, 0, 200);
            }
            // 更新时空串表示保持原密码
            if ($isUpdate && $password === '') {
                $password = null;
            }
        } elseif (!$isUpdate) {
            $password = '';
        }

        $extract = isset($input['extract']) ? trim((string) $input['extract']) : '';
        if (function_exists('mb_substr')) {
            $extract = mb_substr($extract, 0, 1000);
        } else {
            $extract = substr($extract, 0, 1000);
        }

        $extfmt = isset($input['extfmt']) ? (int) $input['extfmt'] : self::EXTFMT_AUTO;
        if ($extfmt < 0 || $extfmt > 2) {
            $extfmt = self::EXTFMT_AUTO;
        }

        $jsonhostRaw = isset($input['jsonhost']) ? trim((string) $input['jsonhost']) : '';
        $jsonportRaw = isset($input['jsonport']) ? trim((string) $input['jsonport']) : '';
        $jsonhost = '';
        $jsonport = '';
        if ($mode === self::MODE_EXTRACT && $extfmt !== self::EXTFMT_TEXT) {
            if ($jsonhostRaw !== '') {
                $jsonhost = self::sanitizeJsonPath($jsonhostRaw);
                if ($jsonhost === false) {
                    return array('ok' => false, 'msg' => 'JSON 主机字段格式无效（仅字母数字下划线与点路径，如 ip 或 data.0.ip）');
                }
            }
            if ($jsonportRaw !== '') {
                $jsonport = self::sanitizeJsonPath($jsonportRaw);
                if ($jsonport === false) {
                    return array('ok' => false, 'msg' => 'JSON 端口字段格式无效（仅字母数字下划线与点路径，如 port）');
                }
            }
        }

        $status = isset($input['status']) ? (int) $input['status'] : 1;
        $status = $status === 0 ? 0 : 1;

        $sort = isset($input['sort']) ? (int) $input['sort'] : 0;
        if ($sort < -9999) {
            $sort = -9999;
        }
        if ($sort > 9999) {
            $sort = 9999;
        }

        $ttlmin = array_key_exists('ttlmin', $input) ? (int) $input['ttlmin'] : 10;
        if ($ttlmin < 0) {
            $ttlmin = 0;
        }
        if ($ttlmin > 10080) {
            $ttlmin = 10080;
        }
        // 隧道模式可保留 ttlmin，缓存字段不使用

        if ($mode === self::MODE_TUNNEL) {
            if ($host === '' || !self::isValidProxyHost($host)) {
                return array('ok' => false, 'msg' => '请填写有效的代理主机（域名或 IP）');
            }
            if ($port < 1 || $port > 65535) {
                return array('ok' => false, 'msg' => '请填写有效端口（1～65535）');
            }
            if (!self::isAllowedProxyEndpoint($host, $port)) {
                return array('ok' => false, 'msg' => '代理主机须为公网地址，禁止内网/保留地址');
            }
            $extract = '';
            $jsonhost = '';
            $jsonport = '';
        } else {
            if ($extract === '' || !preg_match('#^https?://#i', $extract)) {
                return array('ok' => false, 'msg' => '请填写以 http:// 或 https:// 开头的提取 API 地址');
            }
            if (!class_exists('LinkSiteMeta') || !LinkSiteMeta::isAllowedFetchUrl($extract)) {
                return array('ok' => false, 'msg' => '提取地址不允许指向内网或非公网主机');
            }
            // 提取模式允许预填默认隧道字段为空
            if ($host !== '' && !self::isValidProxyHost($host)) {
                return array('ok' => false, 'msg' => '主机格式无效');
            }
        }

        return array(
            'ok'   => true,
            'data' => array(
                'title'    => $title,
                'mode'     => $mode,
                'proto'    => $proto,
                'host'     => $host,
                'port'     => $port,
                'username' => $username,
                'password' => $password,
                'extract'  => $extract,
                'extfmt'   => $extfmt,
                'jsonhost' => $jsonhost,
                'jsonport' => $jsonport,
                'ttlmin'   => $ttlmin,
                'status'   => $status,
                'sort'     => $sort,
            ),
        );
    }

    /**
     * 校验用户填写的 JSON 字段路径（厂商键名可含下划线；库列名仍无下划线）
     *
     * @param string $raw
     * @return string|false 规范化路径；无效返回 false
     */
    public static function sanitizeJsonPath($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            $raw = mb_substr($raw, 0, 80);
        } else {
            $raw = substr($raw, 0, 80);
        }
        if (!preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/', $raw)) {
            return false;
        }
        $parts = explode('.', $raw);
        if (count($parts) > 8) {
            return false;
        }
        foreach ($parts as $p) {
            if ($p === '' || strcasecmp($p, '__proto__') === 0 || strcasecmp($p, 'constructor') === 0) {
                return false;
            }
        }
        return $raw;
    }

    /**
     * 按点路径读取 JSON 值（支持数字下标，如 data.0.ip）
     *
     * @param mixed  $data
     * @param string $path
     * @return mixed|null
     */
    public static function resolveJsonPath($data, $path)
    {
        $path = trim((string) $path);
        if ($path === '' || !is_array($data)) {
            return null;
        }
        $cur = $data;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($cur)) {
                return null;
            }
            if (array_key_exists($seg, $cur)) {
                $cur = $cur[$seg];
                continue;
            }
            if (ctype_digit($seg) && array_key_exists((int) $seg, $cur)) {
                $cur = $cur[(int) $seg];
                continue;
            }
            return null;
        }
        return $cur;
    }

    /**
     * 主机形态是否合法（含 IP / 域名；不含 scheme）
     *
     * @param string $host
     * @return bool
     */
    public static function isValidProxyHost($host)
    {
        $host = trim((string) $host);
        if ($host === '' || strlen($host) > 255) {
            return false;
        }
        // 禁止 scheme / 路径
        if (preg_match('#[:/\\\\]#', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        // 域名（含 localhost —— 出站还会再拦公网）
        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/i', $host)) {
            return true;
        }
        return false;
    }

    /**
     * IP 是否公网可路由（优先复用 LinkSiteMeta，含 CGNAT/基准网段）
     *
     * @param string $ip
     * @return bool
     */
    private static function isPublicRoutableIp($ip)
    {
        if (class_exists('LinkSiteMeta') && method_exists('LinkSiteMeta', 'isPublicRoutableIp')) {
            return LinkSiteMeta::isPublicRoutableIp($ip);
        }
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * 钉死出口代理节点（校验 + 解析 IP；应用时连该 IP，防 DNS 重绑定且不覆盖上游 CURLOPT_RESOLVE）
     *
     * @param string $host
     * @param int    $port
     * @return array{host:string,port:int,ip:string}|null
     */
    public static function pinProxyEndpoint($host, $port)
    {
        $host = trim((string) $host);
        $port = (int) $port;
        if ($host === '' || $port < 1 || $port > 65535 || !self::isValidProxyHost($host)) {
            return null;
        }
        $lower = strtolower($host);
        if ($lower === 'localhost' || substr($lower, -6) === '.local' || substr($lower, -5) === '.test') {
            return null;
        }

        $ip = '';
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!self::isPublicRoutableIp($host)) {
                return null;
            }
            $ip = $host;
        } else {
            $ips = @gethostbynamel($host);
            if (!is_array($ips) || count($ips) === 0) {
                return null;
            }
            foreach ($ips as $candidate) {
                $candidate = (string) $candidate;
                if (!self::isPublicRoutableIp($candidate)) {
                    return null;
                }
                if ($ip === '') {
                    $ip = $candidate;
                }
            }
            if ($ip === '') {
                return null;
            }
            // AAAA：任一非公网则拒绝（防 curl 走 IPv6 旁路）
            if (function_exists('dns_get_record')) {
                $aaaa = @dns_get_record($host, DNS_AAAA);
                if (is_array($aaaa)) {
                    foreach ($aaaa as $rec) {
                        if (!empty($rec['ipv6']) && !self::isPublicRoutableIp((string) $rec['ipv6'])) {
                            return null;
                        }
                    }
                }
            }
        }

        return array(
            'host' => $lower,
            'port' => $port,
            'ip'   => $ip,
        );
    }

    /**
     * 出口代理节点是否允许（防 SSRF：禁止内网/保留/CGNAT；域名须解析到公网 IP）
     *
     * @param string $host
     * @param int    $port
     * @return bool
     */
    public static function isAllowedProxyEndpoint($host, $port)
    {
        return self::pinProxyEndpoint($host, $port) !== null;
    }

    /**
     * 挑选一条配置并解析为可应用的节点
     *
     * @param int    $userId
     * @param string $forceCode 三位短码；空=按策略轮询
     * @return array{ok:bool,errcode?:int,msg?:string,endpoint?:array,row?:array}
     */
    public static function resolveEndpoint($userId, $forceCode = '')
    {
        $userId = (int) $userId;
        $forceCode = self::normalizeProxyCode($forceCode);
        if ($userId <= 0) {
            return array(
                'ok'      => false,
                'errcode' => ApiError::PROXY_NEED,
                'msg'     => '启用出口代理须提供有效密钥',
            );
        }
        if (!self::tableReady()) {
            return array(
                'ok'      => false,
                'errcode' => ApiError::PROXY_NONE,
                'msg'     => '出口代理尚未就绪',
            );
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');
            if ($forceCode !== '') {
                $stmt = $pdo->prepare(
                    'SELECT * FROM `' . $table . '` WHERE `userid` = ? AND `proxycode` = ? AND `status` = 1 LIMIT 1'
                );
                $stmt->execute(array($userId, $forceCode));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return array(
                        'ok'      => false,
                        'errcode' => ApiError::PROXY_NONE,
                        'msg'     => '指定的出口代理不可用',
                    );
                }
                $rows = array($row);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT * FROM `' . $table . '` WHERE `userid` = ? AND `status` = 1 ORDER BY `sort` ASC, `id` ASC'
                );
                $stmt->execute(array($userId));
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!is_array($rows) || $rows === array()) {
                    return array(
                        'ok'      => false,
                        'errcode' => ApiError::PROXY_NONE,
                        'msg'     => '未配置可用出口代理',
                    );
                }
            }
        } catch (Exception $e) {
            return array(
                'ok'      => false,
                'errcode' => ApiError::PROXY_FAIL,
                'msg'     => '出口代理读取失败',
            );
        }

        $row = self::pickRow($userId, $rows, $forceCode !== '');
        $ep = self::materializeEndpoint($row);
        if (empty($ep['ok'])) {
            return array(
                'ok'      => false,
                'errcode' => isset($ep['errcode']) ? (int) $ep['errcode'] : ApiError::PROXY_FAIL,
                'msg'     => isset($ep['msg']) ? (string) $ep['msg'] : '出口代理不可用',
            );
        }
        return array(
            'ok'       => true,
            'endpoint' => $ep['endpoint'],
            'row'      => $row,
        );
    }

    /**
     * @param int   $userId
     * @param array $rows
     * @param bool  $forced 是否已指定短码
     * @return array
     */
    private static function pickRow($userId, array $rows, $forced = false)
    {
        $n = count($rows);
        if ($n <= 1 || $forced) {
            return $rows[0];
        }
        $strategy = self::requestStrategy($userId);
        if ($strategy === self::STRATEGY_FIRST) {
            return $rows[0];
        }
        if ($strategy === self::STRATEGY_RANDOM) {
            return $rows[mt_rand(0, $n - 1)];
        }
        // 轮询
        $idx = 0;
        if (class_exists('RedisCache') && RedisCache::enabled()) {
            $key = 'ipproxy_rr_' . (int) $userId;
            $cur = RedisCache::get($key);
            $idx = is_int($cur) || (is_string($cur) && ctype_digit((string) $cur))
                ? (int) $cur
                : 0;
            RedisCache::set($key, $idx + 1, 86400);
        } else {
            $idx = (int) (microtime(true) * 1000);
        }
        return $rows[$idx % $n];
    }

    /**
     * 将库行变为实际 host/port（提取模式会拉一次提取 API；支持 TTL 缓存）
     *
     * @param array $row
     * @return array{ok:bool,errcode?:int,msg?:string,endpoint?:array,fromcache?:bool}
     */
    public static function materializeEndpoint(array $row)
    {
        $mode = isset($row['mode']) ? (int) $row['mode'] : self::MODE_TUNNEL;
        $proto = isset($row['proto']) ? (int) $row['proto'] : self::PROTO_HTTP;
        $username = isset($row['username']) ? (string) $row['username'] : '';
        $password = isset($row['password']) ? (string) $row['password'] : '';
        $fromcache = false;
        $host = '';
        $port = 0;

        if ($mode === self::MODE_EXTRACT) {
            $ttlmin = isset($row['ttlmin']) ? (int) $row['ttlmin'] : 10;
            if ($ttlmin < 0) {
                $ttlmin = 0;
            }
            if ($ttlmin > 10080) {
                $ttlmin = 10080;
            }
            $cacheHost = isset($row['cachehost']) ? trim((string) $row['cachehost']) : '';
            $cachePort = isset($row['cacheport']) ? (int) $row['cacheport'] : 0;
            $cacheExp = isset($row['cacheexp']) ? trim((string) $row['cacheexp']) : '';
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;

            $useCache = false;
            if ($ttlmin > 0 && $cacheHost !== '' && $cachePort >= 1 && $cachePort <= 65535 && $cacheExp !== '') {
                $expTs = strtotime($cacheExp);
                if ($expTs !== false && $expTs > time() && self::isAllowedProxyEndpoint($cacheHost, $cachePort)) {
                    $useCache = true;
                    $host = $cacheHost;
                    $port = $cachePort;
                    $fromcache = true;
                }
            }

            if (!$useCache) {
                $pulled = self::pullFromExtract(
                    isset($row['extract']) ? (string) $row['extract'] : '',
                    isset($row['extfmt']) ? (int) $row['extfmt'] : self::EXTFMT_AUTO,
                    isset($row['jsonhost']) ? (string) $row['jsonhost'] : '',
                    isset($row['jsonport']) ? (string) $row['jsonport'] : ''
                );
                if (empty($pulled['ok'])) {
                    return $pulled;
                }
                $host = $pulled['host'];
                $port = $pulled['port'];
                if ($rowId > 0) {
                    if ($ttlmin > 0) {
                        self::writeExtractCache($rowId, $host, $port, $ttlmin);
                    } else {
                        self::clearExtractCache($rowId);
                    }
                }
            }
        } else {
            $host = isset($row['host']) ? (string) $row['host'] : '';
            $port = isset($row['port']) ? (int) $row['port'] : 0;
            if ($host === '' || $port < 1) {
                return array(
                    'ok'      => false,
                    'errcode' => ApiError::PROXY_FAIL,
                    'msg'     => '出口代理配置不完整',
                );
            }
        }

        if (!self::isAllowedProxyEndpoint($host, $port)) {
            return array(
                'ok'      => false,
                'errcode' => ApiError::PROXY_FAIL,
                'msg'     => '出口代理地址不允许',
            );
        }

        return array(
            'ok'        => true,
            'fromcache' => $fromcache,
            'endpoint'  => array(
                'proto'    => $proto,
                'host'     => $host,
                'port'     => $port,
                'username' => $username,
                'password' => $password,
            ),
        );
    }

    /**
     * 写回提取节点缓存
     *
     * @param int    $id
     * @param string $host
     * @param int    $port
     * @param int    $ttlmin
     * @return void
     */
    private static function writeExtractCache($id, $host, $port, $ttlmin)
    {
        $id = (int) $id;
        $ttlmin = (int) $ttlmin;
        $host = trim((string) $host);
        $port = (int) $port;
        if ($id <= 0 || $ttlmin < 1 || $host === '' || $port < 1) {
            return;
        }
        try {
            $pdo = Database::connect();
            $exp = date('Y-m-d H:i:s', time() + ($ttlmin * 60));
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('ipproxy') . '`'
                . ' SET `cachehost`=?,`cacheport`=?,`cacheexp`=? WHERE `id`=?'
            );
            $stmt->execute(array($host, $port, $exp, $id));
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * 清空提取节点缓存
     *
     * @param int $id
     * @return void
     */
    private static function clearExtractCache($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('ipproxy') . '`'
                . ' SET `cachehost`=\'\',`cacheport`=0,`cacheexp`=NULL WHERE `id`=?'
            );
            $stmt->execute(array($id));
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * @param string $url
     * @param int    $extfmt
     * @param string $jsonhost
     * @param string $jsonport
     * @return array{ok:bool,errcode?:int,msg?:string,host?:string,port?:int}
     */
    public static function pullFromExtract($url, $extfmt = 0, $jsonhost = '', $jsonport = '')
    {
        $url = trim((string) $url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '提取地址无效');
        }
        if (!class_exists('LinkSiteMeta') || !LinkSiteMeta::isAllowedFetchUrl($url)) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '提取地址不允许');
        }
        if (!function_exists('curl_init')) {
            return array('ok' => false, 'errcode' => ApiError::SERVER, 'msg' => '服务器未启用 curl');
        }

        $ch = curl_init();
        if ($ch === false) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '无法发起提取请求');
        }
        if (!class_exists('LinkSiteMeta') || !LinkSiteMeta::curlPreparePinnedUrl($ch, $url)) {
            curl_close($ch);
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '提取地址不允许');
        }
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => array('Accept: */*', 'User-Agent: ApiNexus-IpProxy/' . (defined('VS_VERSION') ? VS_VERSION : '1')),
        ));
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno || $http >= 400) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '提取代理失败');
        }

        $vendorFail = self::detectExtractVendorFailure((string) $body);
        if ($vendorFail !== null) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => $vendorFail);
        }

        $parsed = self::parseExtractBody((string) $body, (int) $extfmt, (string) $jsonhost, (string) $jsonport);
        if ($parsed === null) {
            $hint = '';
            if (trim((string) $jsonhost) !== '') {
                $hint = '（请检查 JSON 主机/端口字段是否与返回一致）';
            }
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '无法解析提取结果' . $hint);
        }
        if (!self::isAllowedProxyEndpoint($parsed['host'], $parsed['port'])) {
            return array('ok' => false, 'errcode' => ApiError::PROXY_FAIL, 'msg' => '提取到的代理地址不允许');
        }
        return array('ok' => true, 'host' => $parsed['host'], 'port' => $parsed['port']);
    }

    /**
     * 识别提取 API 明确失败 JSON（如 status!=0），避免误解析
     *
     * @param string $body
     * @return string|null 失败文案；成功或非 JSON 返回 null
     */
    public static function detectExtractVendorFailure($body)
    {
        $body = trim((string) $body);
        if ($body === '' || !isset($body[0]) || ($body[0] !== '{' && $body[0] !== '[')) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        // 列表根不当作失败判定
        if (array_keys($data) === range(0, count($data) - 1)) {
            return null;
        }

        $failed = false;
        if (array_key_exists('status', $data)) {
            $st = $data['status'];
            if (is_bool($st)) {
                $failed = ($st === false);
            } else {
                $s = strtolower(trim((string) $st));
                // 0 / ok / success / true = 成功；其它非空视为失败（含 -13、-14）
                if ($s !== '' && $s !== '0' && $s !== 'ok' && $s !== 'success' && $s !== 'true') {
                    $failed = true;
                }
            }
        }
        if (!$failed && array_key_exists('code', $data) && !isset($data['list']) && !isset($data['data'])) {
            $c = $data['code'];
            if (is_int($c) || (is_string($c) && is_numeric($c))) {
                if ((int) $c !== 0 && (int) $c !== 200) {
                    $failed = true;
                }
            }
        }
        if (!$failed && array_key_exists('success', $data) && $data['success'] === false) {
            $failed = true;
        }
        if (!$failed) {
            return null;
        }

        $info = '';
        foreach (array('info', 'msg', 'message', 'error', 'errmsg') as $ik) {
            if (isset($data[$ik]) && is_string($data[$ik]) && trim($data[$ik]) !== '') {
                $info = trim($data[$ik]);
                break;
            }
        }
        // 对外只给短业务结论，去掉可能含内网细节的过长串
        if ($info !== '') {
            if (function_exists('mb_substr')) {
                $info = mb_substr($info, 0, 80);
            } else {
                $info = substr($info, 0, 80);
            }
            // 脱敏：勿把厂商 key 原样吐出
            $info = preg_replace('/[A-Za-z0-9_\-]{20,}/', '***', $info);
            return '提取失败：' . $info;
        }
        return '提取代理失败';
    }

    /**
     * @param string $body
     * @param int    $extfmt
     * @param string $jsonhost
     * @param string $jsonport
     * @return array{host:string,port:int}|null
     */
    public static function parseExtractBody($body, $extfmt = 0, $jsonhost = '', $jsonport = '')
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $extfmt = (int) $extfmt;
        $jsonhost = trim((string) $jsonhost);
        $jsonport = trim((string) $jsonport);

        if ($extfmt === self::EXTFMT_JSON || ($extfmt === self::EXTFMT_AUTO && isset($body[0]) && ($body[0] === '{' || $body[0] === '['))) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                if ($jsonhost !== '') {
                    $hit = self::endpointFromJsonPaths($data, $jsonhost, $jsonport);
                    if ($hit !== null) {
                        return $hit;
                    }
                    // 已指定字段却取不到：JSON 模式下不回退瞎扫，避免读错键
                    if ($extfmt === self::EXTFMT_JSON) {
                        return null;
                    }
                }
                $hit = self::findIpPortInArray($data);
                if ($hit !== null) {
                    return $hit;
                }
            }
            if ($extfmt === self::EXTFMT_JSON) {
                return null;
            }
        }

        // 纯文本：逐行找 ip:port 或 host:port（兼容 ip:port:user:pass）
        $lines = preg_split('/\r\n|\n|\r/', $body);
        if (!is_array($lines)) {
            $lines = array($body);
        }
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            // user:pass@host:port → 只要 host:port
            if (preg_match('/(?:^|[\s,;"])(?:[^@\s]+@)?(\[?[A-Za-z0-9\.\-:]+\]?):(\d{1,5})(?:\s|$|,|;|"|:)/', $line, $m)
                || preg_match('/^(\[?[A-Za-z0-9\.\-:]+\]?):(\d{1,5})(?::.*)?$/', $line, $m)) {
                $host = trim($m[1], '[]');
                $port = (int) $m[2];
                if ($port >= 1 && $port <= 65535 && self::isValidProxyHost($host)) {
                    return array('host' => $host, 'port' => $port);
                }
            }
        }
        return null;
    }

    /**
     * 按用户指定的 JSON 路径取出 host/port
     *
     * @param array  $data
     * @param string $jsonhost
     * @param string $jsonport
     * @return array{host:string,port:int}|null
     */
    private static function endpointFromJsonPaths(array $data, $jsonhost, $jsonport)
    {
        $jsonhost = trim((string) $jsonhost);
        $jsonport = trim((string) $jsonport);
        if ($jsonhost === '') {
            return null;
        }
        $hv = self::resolveJsonPath($data, $jsonhost);
        if ($hv === null) {
            return null;
        }
        if (is_array($hv)) {
            return self::findIpPortInArray($hv);
        }
        if (!is_string($hv) && !is_numeric($hv)) {
            return null;
        }
        $host = trim((string) $hv);
        $port = 0;
        if ($jsonport !== '') {
            $pv = self::resolveJsonPath($data, $jsonport);
            if ($pv !== null && (is_int($pv) || (is_string($pv) && ctype_digit(trim($pv))))) {
                $port = (int) $pv;
            }
        }
        if ($host !== '' && strpos($host, ':') !== false && $port < 1) {
            if (preg_match('/^(.+):(\d{1,5})$/', $host, $m)) {
                $host = $m[1];
                $port = (int) $m[2];
            }
        }
        if ($host !== '' && $port >= 1 && $port <= 65535 && self::isValidProxyHost($host)) {
            return array('host' => $host, 'port' => $port);
        }
        return null;
    }

    /**
     * @param array $data
     * @param int   $depth
     * @return array{host:string,port:int}|null
     */
    private static function findIpPortInArray(array $data, $depth = 0)
    {
        if ($depth > 6) {
            return null;
        }
        $host = '';
        $port = 0;
        foreach (array('ip', 'host', 'proxy', 'addr', 'address', 'server', 'sever', 'proxyip', 'proxyhost') as $hk) {
            if (!isset($data[$hk])) {
                continue;
            }
            // 山辰等厂商：sever 拼写错误；port 可能是 int
            if (is_string($data[$hk]) && $data[$hk] !== '') {
                $host = trim($data[$hk]);
                break;
            }
            if (is_int($data[$hk]) || (is_float($data[$hk]) && (string) (int) $data[$hk] === (string) $data[$hk])) {
                // 异常形态跳过
                continue;
            }
        }
        foreach (array('port', 'proxy_port', 'proxyport') as $pk) {
            if (isset($data[$pk]) && (is_int($data[$pk]) || (is_string($data[$pk]) && ctype_digit($data[$pk])))) {
                $port = (int) $data[$pk];
                break;
            }
        }
        // host 内嵌 port
        if ($host !== '' && strpos($host, ':') !== false && $port < 1) {
            if (preg_match('/^(.+):(\d{1,5})$/', $host, $m)) {
                $host = $m[1];
                $port = (int) $m[2];
            }
        }
        if ($host !== '' && $port >= 1 && $port <= 65535 && self::isValidProxyHost($host)) {
            return array('host' => $host, 'port' => $port);
        }

        // data / list / proxies 数组
        foreach (array('data', 'list', 'proxies', 'result', 'rows') as $nk) {
            if (!isset($data[$nk]) || !is_array($data[$nk])) {
                continue;
            }
            $child = $data[$nk];
            $isList = array_keys($child) === range(0, count($child) - 1);
            if ($isList) {
                foreach ($child as $item) {
                    if (!is_array($item)) {
                        if (is_string($item)) {
                            $one = self::parseExtractBody($item, self::EXTFMT_TEXT);
                            if ($one !== null) {
                                return $one;
                            }
                        }
                        continue;
                    }
                    $hit = self::findIpPortInArray($item, $depth + 1);
                    if ($hit !== null) {
                        return $hit;
                    }
                }
            } else {
                $hit = self::findIpPortInArray($child, $depth + 1);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        foreach ($data as $v) {
            if (is_array($v)) {
                $hit = self::findIpPortInArray($v, $depth + 1);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }
        return null;
    }

    /**
     * 应用到 curl 句柄
     *
     * @param resource|CurlHandle $ch
     * @param array               $endpoint
     * @return bool
     */
    public static function applyEndpointToCurl($ch, array $endpoint)
    {
        $host = isset($endpoint['host']) ? (string) $endpoint['host'] : '';
        $port = isset($endpoint['port']) ? (int) $endpoint['port'] : 0;
        $pin = self::pinProxyEndpoint($host, $port);
        if ($pin === null) {
            return false;
        }
        $proto = isset($endpoint['proto']) ? (int) $endpoint['proto'] : self::PROTO_HTTP;
        $username = isset($endpoint['username']) ? (string) $endpoint['username'] : '';
        $password = isset($endpoint['password']) ? (string) $endpoint['password'] : '';

        // 使用已校验的公网 IP 作为代理地址，避免 DNS 重绑定；不用 CURLOPT_RESOLVE，以免覆盖上游 URL 钉死
        $proxyAddr = $pin['ip'];
        if (strpos($proxyAddr, ':') !== false) {
            $proxyAddr = '[' . $proxyAddr . ']';
        }
        curl_setopt($ch, CURLOPT_PROXY, $proxyAddr);
        curl_setopt($ch, CURLOPT_PROXYPORT, $pin['port']);

        $type = CURLPROXY_HTTP;
        if ($proto === self::PROTO_SOCKS5) {
            $type = defined('CURLPROXY_SOCKS5_HOSTNAME') ? CURLPROXY_SOCKS5_HOSTNAME : CURLPROXY_SOCKS5;
        } elseif ($proto === self::PROTO_SOCKS4) {
            $type = defined('CURLPROXY_SOCKS4A') ? CURLPROXY_SOCKS4A : CURLPROXY_SOCKS4;
        } elseif ($proto === self::PROTO_HTTPS) {
            $type = defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP;
        }
        curl_setopt($ch, CURLOPT_PROXYTYPE, $type);

        if ($username !== '' || $password !== '') {
            if (defined('CURLOPT_PROXYUSERNAME')) {
                curl_setopt($ch, CURLOPT_PROXYUSERNAME, $username);
                curl_setopt($ch, CURLOPT_PROXYPASSWORD, $password);
            } else {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $username . ':' . $password);
            }
            if (defined('CURLAUTH_ANY')) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
            }
        }
        return true;
    }

    /**
     * 解析并应用到 curl；失败返回业务错误结构
     *
     * @param resource|CurlHandle $ch
     * @param int                 $userId
     * @param string              $forceCode 三位短码；空=按策略
     * @return array{ok:bool,errcode?:int,msg?:string}
     */
    public static function applyToCurl($ch, $userId, $forceCode = '')
    {
        $resolved = self::resolveEndpoint($userId, $forceCode);
        if (empty($resolved['ok'])) {
            return array(
                'ok'      => false,
                'errcode' => isset($resolved['errcode']) ? (int) $resolved['errcode'] : ApiError::PROXY_FAIL,
                'msg'     => isset($resolved['msg']) ? (string) $resolved['msg'] : '出口代理不可用',
            );
        }
        if (!self::applyEndpointToCurl($ch, $resolved['endpoint'])) {
            return array(
                'ok'      => false,
                'errcode' => ApiError::PROXY_FAIL,
                'msg'     => '出口代理配置无效',
            );
        }
        return array('ok' => true);
    }

    /**
     * 切换启用/禁用
     *
     * @param int $userId
     * @param int $id
     * @param int $status 0|1
     * @return array{ok:bool,msg:string,list?:array}
     */
    public static function setStatus($userId, $id, $status)
    {
        $userId = (int) $userId;
        $id = (int) $id;
        $status = ((int) $status === 0) ? 0 : 1;
        if ($userId <= 0 || $id <= 0) {
            return array('ok' => false, 'msg' => '参数无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '出口代理尚未就绪，请联系管理员完成系统升级');
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('ipproxy') . '`'
                . ' SET `status`=?,`updatetime`=NOW() WHERE `id`=? AND `userid`=?'
            );
            $stmt->execute(array($status, $id, $userId));
            if ($stmt->rowCount() < 1) {
                // 可能值未变：确认归属
                $chk = $pdo->prepare(
                    'SELECT 1 FROM `' . Database::table('ipproxy') . '` WHERE `id`=? AND `userid`=? LIMIT 1'
                );
                $chk->execute(array($id, $userId));
                if (!$chk->fetchColumn()) {
                    return array('ok' => false, 'msg' => '记录不存在');
                }
            }
            $listPack = self::listForUser($userId);
            return array(
                'ok'   => true,
                'msg'  => $status === 1 ? '已启用' : '已禁用',
                'list' => isset($listPack['list']) ? $listPack['list'] : array(),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '操作失败，请稍后重试');
        }
    }

    /**
     * 取用户第一条启用密钥（本站个人信息接口探测；勿写入 logs）
     *
     * @param int $userId
     * @return string
     */
    private static function firstEnabledApiKeySecret($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '';
        }
        try {
            if (class_exists('DatabaseMigrator') && !DatabaseMigrator::tableExists('apikey')) {
                return '';
            }
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `secret` FROM `' . Database::table('apikey') . '`'
                . ' WHERE `userid`=? AND `status`=1 ORDER BY `id` ASC LIMIT 1'
            );
            $stmt->execute(array($userId));
            $secret = $stmt->fetchColumn();
            return is_string($secret) ? $secret : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * 追加结构化测试日志
     *
     * @param array  $logs
     * @param string $type info|ok|err|req|res|warn
     * @param string $msg
     * @return void
     */
    private static function pushTestLog(array &$logs, $type, $msg)
    {
        static $allow = array(
            'info' => 1,
            'ok'   => 1,
            'err'  => 1,
            'req'  => 1,
            'res'  => 1,
            'warn' => 1,
        );
        $t = (string) $type;
        if (!isset($allow[$t])) {
            $t = 'info';
        }
        $logs[] = array(
            't'   => $t,
            'msg' => (string) $msg,
        );
    }

    /**
     * 拼本站个人信息接口探测 URL（vsproxy=短码；密钥走 Header）
     *
     * @param string $proxyCode
     * @return string
     */
    private static function buildSiteProbeUrl($proxyCode)
    {
        $proxyCode = self::normalizeProxyCode($proxyCode);
        $base = '';
        if (function_exists('vs_base_url')) {
            $base = rtrim((string) vs_base_url(), '/');
        }
        if ($base === '' && class_exists('Config')) {
            $domain = trim((string) Config::get('site_domain', ''));
            if ($domain !== '') {
                $domain = preg_replace('#^https?://#i', '', $domain);
                $domain = rtrim($domain, '/');
                if ($domain !== '') {
                    $base = 'https://' . $domain;
                }
            }
        }
        if ($base === '') {
            return '';
        }
        $q = 'q=all';
        if ($proxyCode !== '') {
            $q .= '&vsproxy=' . rawurlencode($proxyCode);
        }
        return $base . '/api/index.php?' . $q;
    }

    /**
     * 在线连通性测试（用户中心）
     *
     * @param int $userId
     * @param int $id
     * @return array{ok:bool,msg:string,logs?:array,detail?:array}
     */
    public static function testConnectivity($userId, $id)
    {
        $userId = (int) $userId;
        $id = (int) $id;
        $logs = array();
        if ($userId <= 0 || $id <= 0) {
            self::pushTestLog($logs, 'err', '参数无效');
            return array('ok' => false, 'msg' => '参数无效', 'logs' => $logs);
        }
        if (!self::tableReady()) {
            self::pushTestLog($logs, 'err', '出口代理尚未就绪');
            return array('ok' => false, 'msg' => '出口代理尚未就绪', 'logs' => $logs);
        }
        if (!function_exists('curl_init')) {
            self::pushTestLog($logs, 'err', '服务器未启用 curl，无法测试');
            return array('ok' => false, 'msg' => '服务器未启用 curl，无法测试', 'logs' => $logs);
        }

        self::pushTestLog($logs, 'info', '读取配置…');
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . Database::table('ipproxy') . '` WHERE `id` = ? AND `userid` = ? LIMIT 1'
            );
            $stmt->execute(array($id, $userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                self::pushTestLog($logs, 'err', '配置不存在');
                return array('ok' => false, 'msg' => '记录不存在', 'logs' => $logs);
            }
        } catch (Exception $e) {
            self::pushTestLog($logs, 'err', '读取配置失败');
            return array('ok' => false, 'msg' => '读取失败', 'logs' => $logs);
        }

        $title = isset($row['title']) ? (string) $row['title'] : '';
        $mode = isset($row['mode']) ? (int) $row['mode'] : self::MODE_TUNNEL;
        $proxyCode = self::normalizeProxyCode(isset($row['proxycode']) ? $row['proxycode'] : '');
        self::pushTestLog(
            $logs,
            'ok',
            '已读取「' . ($title !== '' ? $title : ('#' . $id)) . '」（'
            . self::modeLabel($mode) . ' / ' . self::protoLabel(isset($row['proto']) ? (int) $row['proto'] : 0)
            . ($proxyCode !== '' ? ' / 短码 ' . $proxyCode : '')
            . '）'
        );

        if ($mode === self::MODE_EXTRACT) {
            $ttlmin = isset($row['ttlmin']) ? (int) $row['ttlmin'] : 10;
            $cacheHost = isset($row['cachehost']) ? trim((string) $row['cachehost']) : '';
            $cachePort = isset($row['cacheport']) ? (int) $row['cacheport'] : 0;
            $cacheExp = isset($row['cacheexp']) ? trim((string) $row['cacheexp']) : '';
            $expTs = ($cacheExp !== '') ? strtotime($cacheExp) : false;
            if ($ttlmin > 0 && $cacheHost !== '' && $cachePort >= 1 && $expTs !== false && $expTs > time()) {
                self::pushTestLog($logs, 'info', '使用未过期提取缓存 ' . $cacheHost . ':' . $cachePort);
            } else {
                self::pushTestLog(
                    $logs,
                    'warn',
                    $ttlmin > 0 ? '提取缓存无效或已过期，正在拉取提取 API…' : 'TTL=0，每次重新提取…'
                );
            }
        } else {
            self::pushTestLog($logs, 'info', '隧道模式，使用配置主机端口');
        }

        $mat = self::materializeEndpoint($row);
        if (empty($mat['ok'])) {
            self::pushTestLog($logs, 'err', '解析节点失败：' . (isset($mat['msg']) ? (string) $mat['msg'] : '不可用'));
            return array(
                'ok'   => false,
                'msg'  => isset($mat['msg']) ? (string) $mat['msg'] : '无法解析代理节点',
                'logs' => $logs,
            );
        }
        $ep = $mat['endpoint'];
        if (!empty($mat['fromcache'])) {
            self::pushTestLog($logs, 'ok', '节点来自缓存');
        } elseif ($mode === self::MODE_EXTRACT) {
            self::pushTestLog($logs, 'ok', '提取成功');
        }
        self::pushTestLog(
            $logs,
            'info',
            '节点 ' . $ep['host'] . ':' . (int) $ep['port'] . '（' . self::protoLabel($ep['proto']) . '）'
        );

        // —— 公网知名站点连通（百度 → 一言 → 腾讯）——
        self::pushTestLog($logs, 'info', '经代理请求公网站点（百度 / 一言 / 腾讯）…');
        $echoHttp = 0;
        $echoOk = false;
        $echoUrlUsed = '';
        $echoSnippet = '';
        $testUrls = self::publicTestUrls();
        foreach ($testUrls as $tryUrl) {
            if (class_exists('LinkSiteMeta') && !LinkSiteMeta::isAllowedFetchUrl($tryUrl)) {
                self::pushTestLog($logs, 'warn', '跳过不允许的地址：' . $tryUrl);
                continue;
            }
            self::pushTestLog($logs, 'req', 'GET ' . $tryUrl);
            $ch = curl_init();
            if ($ch === false) {
                self::pushTestLog($logs, 'err', '无法初始化公网测试请求');
                return array('ok' => false, 'msg' => '无法初始化测试请求', 'logs' => $logs);
            }
            curl_setopt($ch, CURLOPT_URL, $tryUrl);
            self::applyEndpointToCurl($ch, $ep);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER     => array(
                    'Accept: */*',
                    'User-Agent: ApiNexus-IpProxy-Test/' . (defined('VS_VERSION') ? VS_VERSION : '1'),
                ),
            ));
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $echoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $errno) {
                self::pushTestLog($logs, 'err', '请求失败（无法经代理访问）');
                continue;
            }
            if ($echoHttp >= 400) {
                self::pushTestLog($logs, 'err', 'HTTP ' . $echoHttp);
                continue;
            }
            $echoOk = true;
            $echoUrlUsed = $tryUrl;
            $trim = trim((string) $body);
            // 公网站点多为 HTML：只记长度，避免终端堆整页源码
            $looksJson = ($trim !== '' && ($trim[0] === '{' || $trim[0] === '['));
            if ($looksJson) {
                $echoSnippet = function_exists('mb_substr')
                    ? mb_substr($trim, 0, 240, 'UTF-8')
                    : substr($trim, 0, 240);
            } else {
                $echoSnippet = '正文约 ' . strlen((string) $body) . ' 字节（非 JSON，已省略）';
            }
            self::pushTestLog($logs, 'ok', '公网可达 HTTP ' . $echoHttp . ' ← ' . $tryUrl);
            if ($echoSnippet !== '') {
                self::pushTestLog($logs, 'res', $echoSnippet);
            }
            break;
        }
        if (!$echoOk) {
            self::pushTestLog($logs, 'err', '公网站点均未能经该代理访问');
        }

        // —— 经代理：无密钥探本站可达（密钥不进不可信代理）——
        $reachOk = false;
        $reachHttp = 0;
        $probeUrl = self::buildSiteProbeUrl($proxyCode);
        if ($probeUrl === '') {
            self::pushTestLog($logs, 'warn', '经代理探本站跳过：无法拼站点根 URL');
        } elseif ($proxyCode === '') {
            self::pushTestLog($logs, 'warn', '经代理探本站跳过：缺少调用短码');
        } else {
            self::pushTestLog($logs, 'info', '经代理探本站可达（不携带密钥）…');
            self::pushTestLog($logs, 'req', 'GET ' . $probeUrl);
            $chR = curl_init();
            if ($chR === false) {
                self::pushTestLog($logs, 'err', '经代理探本站失败：无法初始化');
            } else {
                curl_setopt($chR, CURLOPT_URL, $probeUrl);
                self::applyEndpointToCurl($chR, $ep);
                curl_setopt_array($chR, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_HTTPHEADER     => array(
                        'Accept: */*',
                        'User-Agent: ApiNexus-IpProxy-Test/' . (defined('VS_VERSION') ? VS_VERSION : '1'),
                    ),
                ));
                $reachBody = curl_exec($chR);
                $reachErrno = curl_errno($chR);
                $reachHttp = (int) curl_getinfo($chR, CURLINFO_HTTP_CODE);
                curl_close($chR);
                if ($reachBody === false || $reachErrno) {
                    self::pushTestLog($logs, 'err', '经代理无法访问本站');
                } else {
                    $reachOk = ($reachHttp > 0 && $reachHttp < 500);
                    self::pushTestLog(
                        $logs,
                        $reachOk ? 'ok' : 'warn',
                        '经代理本站 HTTP ' . $reachHttp . ($reachOk ? '（可达）' : '（异常）')
                    );
                }
            }
        }

        // —— 直连：带启用密钥测个人信息接口（密钥不经出口代理，防第三方代理盗钥）——
        $siteOk = false;
        $siteHttp = 0;
        $siteMsg = '';
        $siteBodyShow = '';
        $directUrl = self::buildSiteProbeUrl(''); // 无 vsproxy，仅 q=all
        $apiKey = self::firstEnabledApiKeySecret($userId);
        if ($directUrl === '') {
            self::pushTestLog($logs, 'warn', '个人信息接口跳过：无法拼站点根 URL');
            $siteMsg = '未拼出本站 URL';
        } elseif ($apiKey === '') {
            self::pushTestLog($logs, 'warn', '个人信息接口跳过：无启用中的调用密钥');
            $siteMsg = '无启用密钥';
        } else {
            self::pushTestLog($logs, 'info', '直连个人信息接口（Header 鉴权；密钥不经出口代理、不展示）');
            self::pushTestLog($logs, 'req', 'GET ' . $directUrl);
            self::pushTestLog($logs, 'req', 'Header: X-API-Key: ****（已脱敏）');
            $ch2 = curl_init();
            if ($ch2 === false) {
                self::pushTestLog($logs, 'err', '个人信息接口失败：无法初始化请求');
                $siteMsg = '初始化失败';
            } else {
                // 故意不 applyEndpointToCurl：密钥不得进入用户代理信道
                curl_setopt($ch2, CURLOPT_URL, $directUrl);
                curl_setopt_array($ch2, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_HTTPHEADER     => array(
                        'Accept: application/json',
                        'X-API-Key: ' . $apiKey,
                        'User-Agent: ApiNexus-IpProxy-Test/' . (defined('VS_VERSION') ? VS_VERSION : '1'),
                    ),
                ));
                $siteBody = curl_exec($ch2);
                $siteErrno = curl_errno($ch2);
                $siteHttp = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                if ($siteBody === false || $siteErrno) {
                    self::pushTestLog($logs, 'err', '个人信息接口请求失败');
                    $siteMsg = '请求失败';
                } else {
                    $trimSite = trim((string) $siteBody);
                    $decoded = json_decode($trimSite, true);
                    if (is_array($decoded)) {
                        $siteOk = (isset($decoded['code']) && (int) $decoded['code'] === 1);
                        // 终端只展示摘要，避免全文积分等过度暴露到剪贴板
                        $summary = array(
                            'code' => isset($decoded['code']) ? $decoded['code'] : null,
                            'msg'  => isset($decoded['msg']) ? $decoded['msg'] : null,
                        );
                        if (isset($decoded['data']) && is_array($decoded['data'])) {
                            $keys = array_slice(array_keys($decoded['data']), 0, 12);
                            $summary['data_keys'] = $keys;
                            $dataBrief = array();
                            foreach ($keys as $dk) {
                                $dv = $decoded['data'][$dk];
                                if (is_scalar($dv) || $dv === null) {
                                    $dataBrief[$dk] = $dv;
                                } else {
                                    $dataBrief[$dk] = is_array($dv) ? '[object]' : '[…]';
                                }
                            }
                            $summary['data'] = $dataBrief;
                        }
                        $pretty = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                        $siteBodyShow = is_string($pretty) ? $pretty : '';
                    } else {
                        $siteOk = ($siteHttp > 0 && $siteHttp < 500);
                        $siteBodyShow = function_exists('mb_substr')
                            ? mb_substr($trimSite, 0, 400, 'UTF-8')
                            : substr($trimSite, 0, 400);
                    }
                    self::pushTestLog(
                        $logs,
                        $siteOk ? 'ok' : 'warn',
                        '个人信息接口 HTTP ' . $siteHttp . ($siteOk ? '（业务成功）' : '（已收到响应）')
                    );
                    if ($siteBodyShow !== '') {
                        self::pushTestLog($logs, 'res', $siteBodyShow);
                    }
                    $siteMsg = 'HTTP ' . $siteHttp;
                }
            }
        }

        $detail = array(
            'http'      => $echoHttp,
            'puburl'    => $echoUrlUsed,
            'host'      => $ep['host'],
            'port'      => $ep['port'],
            'proto'     => self::protoLabel($ep['proto']),
            'proxycode' => $proxyCode,
            'fromcache' => !empty($mat['fromcache']),
            'reachhttp' => $reachHttp,
            'reachok'   => $reachOk,
            'sitehttp'  => $siteHttp,
            'siteok'    => $siteOk,
        );

        if ($echoOk) {
            $msg = '公网连通正常（' . ($echoUrlUsed !== '' ? $echoUrlUsed : '知名站点') . '）';
            if ($reachOk) {
                $msg .= '；经代理本站可达';
            }
            if ($siteOk) {
                $msg .= '；个人信息接口成功';
            } elseif ($siteMsg !== '') {
                $msg .= '；个人信息接口未完全成功（' . $siteMsg . '）';
            }
            return array('ok' => true, 'msg' => $msg, 'logs' => $logs, 'detail' => $detail);
        }

        $failMsg = '连通失败：无法经该代理访问公网测试站点';
        if ($echoHttp >= 400) {
            $failMsg = '连通异常：公网测试返回 HTTP ' . $echoHttp;
        }
        if ($reachOk || $siteOk) {
            $failMsg .= '；本站侧有响应但不作为成功依据';
        }
        return array('ok' => false, 'msg' => $failMsg, 'logs' => $logs, 'detail' => $detail);
    }
}
