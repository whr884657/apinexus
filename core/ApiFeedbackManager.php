<?php
/**
 * 文件：core/ApiFeedbackManager.php
 * 作用：接口反馈 / 开发者申请 CRUD（表 feedback；kind 区分）
 */

class ApiFeedbackManager
{
    /** kind：接口反馈 */
    const KIND_API = 0;
    /** kind：开发者申请 */
    const KIND_DEVAPPLY = 1;

    /** 状态：待处理 / 待审核 */
    const STATUS_PENDING = 0;
    /** 状态：已处理（接口反馈）/ 已通过（开发者申请） */
    const STATUS_DONE = 1;
    /** 状态：已通过（开发者申请，同 STATUS_DONE） */
    const STATUS_APPROVED = 1;
    /** 状态：已拒绝（仅开发者申请） */
    const STATUS_REJECTED = 2;

    /**
     * @return bool
     */
    public static function tableReady()
    {
        try {
            return DatabaseMigrator::tableExists('feedback');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * feedback.kind 列是否就绪（开发者申请依赖）
     *
     * @return bool
     */
    public static function hasKindColumn()
    {
        try {
            return DatabaseMigrator::tableColumnExists('feedback', 'kind');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int $status
     * @param int $kind
     * @return string
     */
    public static function statusLabel($status, $kind = self::KIND_API)
    {
        $status = (int) $status;
        $kind = (int) $kind;
        if ($kind === self::KIND_DEVAPPLY) {
            if ($status === self::STATUS_APPROVED) {
                return '已通过';
            }
            if ($status === self::STATUS_REJECTED) {
                return '已拒绝';
            }
            return '待审核';
        }
        return ($status === self::STATUS_DONE) ? '已处理' : '待处理';
    }

    /**
     * 待处理接口反馈数量（侧边栏红点；不含开发者申请）
     *
     * @return int
     */
    public static function countPending()
    {
        if (!self::tableReady()) {
            return 0;
        }
        try {
            $sql = 'SELECT COUNT(*) FROM `' . Database::table('feedback') . '` WHERE `status` = ?';
            $bind = array(self::STATUS_PENDING);
            if (self::hasKindColumn()) {
                $sql = 'SELECT COUNT(*) FROM `' . Database::table('feedback') . '`'
                    . ' WHERE `kind` = ? AND `status` = ?';
                $bind = array(self::KIND_API, self::STATUS_PENDING);
            }
            $stmt = Database::connect()->prepare($sql);
            $stmt->execute($bind);
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @deprecated v13.26.39 顶栏铃铛仅摘要，不再调用（E301）
     *
     * @param int $limit
     * @return array<int,array{api_name:string,snippet:string}>
     */
    public static function listPendingBrief($limit = 3)
    {
        $limit = max(1, min(10, (int) $limit));
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $apiTable = Database::table('api');
            $kindSql = self::hasKindColumn() ? 'f.`kind` = ? AND ' : '';
            $stmt = $pdo->prepare(
                'SELECT f.`content`, a.`name` AS `api_name`
                 FROM `' . $fbTable . '` f
                 LEFT JOIN `' . $apiTable . '` a ON a.`id` = f.`apiid`
                 WHERE ' . $kindSql . 'f.`status` = ?
                 ORDER BY f.`id` DESC
                 LIMIT ' . $limit
            );
            $bind = self::hasKindColumn()
                ? array(self::KIND_API, self::STATUS_PENDING)
                : array(self::STATUS_PENDING);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (!is_array($rows)) {
                return $out;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $content = isset($row['content']) ? trim((string) $row['content']) : '';
                $snippet = $content;
                if (function_exists('mb_substr') && mb_strlen($snippet, 'UTF-8') > 36) {
                    $snippet = mb_substr($snippet, 0, 36, 'UTF-8') . '…';
                } elseif (strlen($snippet) > 36) {
                    $snippet = substr($snippet, 0, 36) . '…';
                }
                $out[] = array(
                    'api_name' => isset($row['api_name']) ? trim((string) $row['api_name']) : '',
                    'snippet'  => $snippet,
                );
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 管理员列表：含用户名与接口名（仅接口反馈）
     *
     * @return array
     */
    public static function listAll()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $apiTable = Database::table('api');
            $kindSql = self::hasKindColumn() ? ' WHERE f.`kind` = ' . (int) self::KIND_API : '';
            $sql = 'SELECT f.`id`, f.`apiid`, f.`userid`, f.`content`, f.`reply`, f.`status`,
                           f.`createtime`, f.`updatetime`,
                           u.`username` AS `username`,
                           u.`email` AS `email`,
                           a.`name` AS `api_name`
                    FROM `' . $fbTable . '` f
                    LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                    LEFT JOIN `' . $apiTable . '` a ON a.`id` = f.`apiid`'
                . $kindSql . '
                    ORDER BY f.`id` DESC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 后台真分页：独立 COUNT + LIMIT OFFSET（pagesize 1–50；仅接口反馈）
     *
     * @param array $opts q?, status?, page, pagesize
     * @return array{list:array,total:int,page:int,pagesize:int,counts:array}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        if (function_exists('mb_substr') && mb_strlen($q, 'UTF-8') > 64) {
            $q = mb_substr($q, 0, 64, 'UTF-8');
        } elseif (strlen($q) > 64) {
            $q = substr($q, 0, 64);
        }
        $status = array_key_exists('status', $opts) && $opts['status'] !== '' && $opts['status'] !== null
            ? (int) $opts['status']
            : self::STATUS_PENDING;
        if ($status !== self::STATUS_DONE) {
            $status = self::STATUS_PENDING;
        }
        $counts = array('0' => 0, '1' => 0);
        $empty = array(
            'list'     => array(),
            'total'    => 0,
            'page'     => $page,
            'pagesize' => $pagesize,
            'counts'   => $counts,
        );
        if (!self::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $apiTable = Database::table('api');
            $from = ' FROM `' . $fbTable . '` f
                    LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                    LEFT JOIN `' . $apiTable . '` a ON a.`id` = f.`apiid`';
            $kindFilter = self::hasKindColumn() ? ' AND `kind` = ' . (int) self::KIND_API : '';
            $cntPending = $pdo->query(
                'SELECT COUNT(*) FROM `' . $fbTable . '` WHERE `status` = ' . self::STATUS_PENDING . $kindFilter
            );
            $cntDone = $pdo->query(
                'SELECT COUNT(*) FROM `' . $fbTable . '` WHERE `status` = ' . self::STATUS_DONE . $kindFilter
            );
            $counts['0'] = $cntPending ? (int) $cntPending->fetchColumn() : 0;
            $counts['1'] = $cntDone ? (int) $cntDone->fetchColumn() : 0;
            $where = array('f.`status` = ?');
            $bind = array($status);
            if (self::hasKindColumn()) {
                $where[] = 'f.`kind` = ?';
                $bind[] = self::KIND_API;
            }
            if ($q !== '') {
                $like = function_exists('vs_sql_like_contains')
                    ? vs_sql_like_contains($q)
                    : ('%' . addcslashes($q, "\\%_") . '%');
                $where[] = '(f.`content` LIKE ? ESCAPE \'\\\\\' OR a.`name` LIKE ? ESCAPE \'\\\\\' OR u.`username` LIKE ? ESCAPE \'\\\\\' OR u.`email` LIKE ? ESCAPE \'\\\\\')';
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
            }
            $whereSql = implode(' AND ', $where);
            $cnt = $pdo->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . $whereSql);
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            if ($total === 0) {
                return array(
                    'list'     => array(),
                    'total'    => 0,
                    'page'     => 1,
                    'pagesize' => $pagesize,
                    'counts'   => $counts,
                );
            }
            $offset = (int) (($page - 1) * $pagesize);
            $sql = 'SELECT f.`id`, f.`apiid`, f.`userid`, f.`content`, f.`reply`, f.`status`,
                           f.`createtime`, f.`updatetime`,
                           u.`username` AS `username`,
                           u.`email` AS `email`,
                           a.`name` AS `api_name`
                    ' . $from . '
                    WHERE ' . $whereSql . '
                    ORDER BY f.`id` DESC
                    LIMIT ' . $pagesize . ' OFFSET ' . $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = is_array($rows) ? $rows : array();
            return array(
                'list'     => $list,
                'total'    => $total,
                'page'     => $page,
                'pagesize' => $pagesize,
                'counts'   => $counts,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $apiTable = Database::table('api');
            $kindCol = self::hasKindColumn() ? 'f.`kind`,' : '';
            $stmt = $pdo->prepare(
                'SELECT f.`id`, ' . $kindCol . ' f.`apiid`, f.`userid`, f.`content`, f.`reply`, f.`status`,
                        f.`createtime`, f.`updatetime`,
                        u.`username` AS `username`,
                        u.`email` AS `email`,
                        u.`avatar` AS `avatar`,
                        a.`name` AS `api_name`
                 FROM `' . $fbTable . '` f
                 LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                 LEFT JOIN `' . $apiTable . '` a ON a.`id` = f.`apiid`
                 WHERE f.`id` = ? LIMIT 1'
            );
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array|null $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row) || !isset($row['id'])) {
            return null;
        }
        $kind = self::hasKindColumn() && isset($row['kind'])
            ? (int) $row['kind']
            : self::KIND_API;
        if ($kind === self::KIND_DEVAPPLY) {
            return self::formatDevApplyRow($row);
        }
        $status = ((int) $row['status'] === self::STATUS_DONE)
            ? self::STATUS_DONE
            : self::STATUS_PENDING;
        return array(
            'id'           => (int) $row['id'],
            'kind'         => self::KIND_API,
            'apiid'        => isset($row['apiid']) ? (int) $row['apiid'] : 0,
            'userid'       => isset($row['userid']) ? (int) $row['userid'] : 0,
            'content'      => isset($row['content']) ? (string) $row['content'] : '',
            'reply'        => isset($row['reply']) ? (string) $row['reply'] : '',
            'status'       => $status,
            'status_label' => self::statusLabel($status, self::KIND_API),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'   => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
            'username'     => isset($row['username']) ? (string) $row['username'] : '',
            'email'        => isset($row['email']) ? trim((string) $row['email']) : '',
            'api_name'     => isset($row['api_name']) ? (string) $row['api_name'] : '',
        );
    }

    /**
     * 开发者申请行（含主题/旧 UI 别名 description、name、contact）
     *
     * @param array $row
     * @return array
     */
    public static function formatDevApplyRow(array $row)
    {
        $status = (int) (isset($row['status']) ? $row['status'] : self::STATUS_PENDING);
        if ($status !== self::STATUS_APPROVED && $status !== self::STATUS_REJECTED) {
            $status = self::STATUS_PENDING;
        }
        $content = isset($row['content']) ? (string) $row['content'] : '';
        $username = isset($row['username']) ? (string) $row['username'] : '';
        $email = isset($row['email']) ? trim((string) $row['email']) : '';
        $userid = isset($row['userid']) ? (int) $row['userid'] : 0;
        $avatarUrl = '';
        if (class_exists('UserAvatar')) {
            $avatarUrl = UserAvatar::resolve(array(
                'id'     => $userid > 0 ? $userid : 1,
                'email'  => $email,
                'avatar' => isset($row['avatar']) ? (string) $row['avatar'] : '',
            ));
        }
        return array(
            'id'           => (int) $row['id'],
            'kind'         => self::KIND_DEVAPPLY,
            'apiid'        => 0,
            'userid'       => $userid,
            'content'      => $content,
            'description'  => $content,
            'reply'        => isset($row['reply']) ? (string) $row['reply'] : '',
            'status'       => $status,
            'status_label' => self::statusLabel($status, self::KIND_DEVAPPLY),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'   => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
            'username'     => $username,
            'name'         => $username,
            'email'        => $email,
            'contact'      => $email,
            'avatar'       => isset($row['avatar']) ? (string) $row['avatar'] : '',
            'avatar_url'   => $avatarUrl,
            'api_name'     => '',
        );
    }

    /**
     * @param int $id
     * @param int $status
     * @return true|string
     */
    public static function setStatus($id, $status)
    {
        $id = (int) $id;
        $status = ((int) $status === self::STATUS_DONE)
            ? self::STATUS_DONE
            : self::STATUS_PENDING;
        $row = self::findById($id);
        if (!$row) {
            return '反馈不存在';
        }
        $kind = isset($row['kind']) ? (int) $row['kind'] : self::KIND_API;
        if ($kind === self::KIND_DEVAPPLY) {
            return '开发者申请请使用审核接口';
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $kindSql = self::hasKindColumn() ? ' AND `kind` = ' . (int) self::KIND_API : '';
            $stmt = $pdo->prepare(
                'UPDATE `' . $table . '` SET `status` = ?, `updatetime` = NOW()'
                . ' WHERE `id` = ?' . $kindSql . ' LIMIT 1'
            );
            $stmt->execute(array($status, $id));
            return true;
        } catch (Exception $e) {
            return '状态更新失败';
        }
    }

    /**
     * @param int    $id
     * @param string $reply
     * @return true|string
     */
    public static function setReply($id, $reply)
    {
        $id = (int) $id;
        $reply = trim((string) $reply);
        if (function_exists('mb_substr')) {
            $reply = mb_substr($reply, 0, 5000, 'UTF-8');
        } else {
            $reply = substr($reply, 0, 5000);
        }
        $row = self::findById($id);
        if (!$row) {
            return '反馈不存在';
        }
        $kind = isset($row['kind']) ? (int) $row['kind'] : self::KIND_API;
        if ($kind === self::KIND_DEVAPPLY) {
            return '开发者申请请使用审核接口';
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $kindSql = self::hasKindColumn() ? ' AND `kind` = ' . (int) self::KIND_API : '';
            $stmt = $pdo->prepare(
                'UPDATE `' . $table . '` SET `reply` = ?, `updatetime` = NOW()'
                . ' WHERE `id` = ?' . $kindSql . ' LIMIT 1'
            );
            $stmt->execute(array($reply, $id));
            return true;
        } catch (Exception $e) {
            return '保存回复失败';
        }
    }

    /**
     * @param int $id
     * @return true|string
     */
    public static function delete($id)
    {
        $id = (int) $id;
        $row = self::findById($id);
        if (!$row) {
            return '反馈不存在';
        }
        $kind = isset($row['kind']) ? (int) $row['kind'] : self::KIND_API;
        if ($kind === self::KIND_DEVAPPLY) {
            // 种隔离：开发者申请仅允许开发审核页删除（对齐 setStatus/setReply）
            return '开发者申请请使用审核接口';
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $kindSql = self::hasKindColumn() ? ' AND `kind` = ' . (int) self::KIND_API : '';
            $stmt = $pdo->prepare(
                'DELETE FROM `' . $table . '` WHERE `id` = ?' . $kindSql . ' LIMIT 1'
            );
            $stmt->execute(array($id));
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * 登录用户提交接口反馈
     *
     * @param int    $apiid
     * @param int    $userid
     * @param string $content
     * @return array|string 成功返回 formatRow，失败返回错误文案
     */
    public static function create($apiid, $userid, $content)
    {
        if (!self::tableReady()) {
            return '反馈功能暂未就绪，请稍后再试';
        }

        $apiid = (int) $apiid;
        $userid = (int) $userid;
        $content = trim((string) $content);

        if ($apiid <= 0) {
            return '无效的接口';
        }
        if ($userid <= 0) {
            return '请登录后提交反馈问题';
        }
        if ($content === '') {
            return '请填写反馈内容';
        }

        $len = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ($len < 5) {
            return '反馈内容至少 5 个字';
        }
        if ($len > 500) {
            return '反馈内容不能超过 500 字';
        }

        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 500, 'UTF-8');
        } else {
            $content = substr($content, 0, 500);
        }

        $api = class_exists('FrontendApi') ? FrontendApi::findForThemeById($apiid) : null;
        if ($api === null) {
            return '接口不存在或不可用';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');

            $kindSql = self::hasKindColumn() ? ' AND `kind` = ' . (int) self::KIND_API : '';
            $chk = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '`
                 WHERE `userid` = ? AND `apiid` = ?' . $kindSql . '
                   AND `createtime` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                 ORDER BY `id` DESC LIMIT 1'
            );
            $chk->execute(array($userid, $apiid));
            if ($chk->fetch()) {
                return '提交过于频繁，请稍后再试';
            }

            if (self::hasKindColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . $table . '`
                     (`kind`, `apiid`, `userid`, `content`, `reply`, `status`, `createtime`)
                     VALUES (?, ?, ?, ?, \'\', ?, NOW())'
                );
                $stmt->execute(array(self::KIND_API, $apiid, $userid, $content, self::STATUS_PENDING));
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . $table . '`
                     (`apiid`, `userid`, `content`, `reply`, `status`, `createtime`)
                     VALUES (?, ?, ?, \'\', ?, NOW())'
                );
                $stmt->execute(array($apiid, $userid, $content, self::STATUS_PENDING));
            }
            $id = (int) $pdo->lastInsertId();
            if ($id <= 0) {
                return '提交失败，请稍后重试';
            }
            $row = self::findById($id);
            $formatted = self::formatRow($row);
            return $formatted ? $formatted : '提交失败，请稍后重试';
        } catch (Exception $e) {
            return '提交失败，请稍后重试';
        }
    }

    // ---------- 开发者申请（feedback.kind=1）----------

    /**
     * @return int
     */
    public static function countPendingDevApply()
    {
        if (!self::tableReady() || !self::hasKindColumn()) {
            return 0;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) FROM `' . Database::table('feedback') . '`'
                . ' WHERE `kind` = ? AND `status` = ?'
            );
            $stmt->execute(array(self::KIND_DEVAPPLY, self::STATUS_PENDING));
            return max(0, (int) $stmt->fetchColumn());
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public static function pendingDevApplyForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady() || !self::hasKindColumn()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT f.*, u.`username`, u.`email`
                 FROM `' . $fbTable . '` f
                 LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                 WHERE f.`kind` = ? AND f.`userid` = ? AND f.`status` = ?
                 LIMIT 1'
            );
            $stmt->execute(array(self::KIND_DEVAPPLY, $userId, self::STATUS_PENDING));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? self::formatDevApplyRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public static function latestDevApplyForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady() || !self::hasKindColumn()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT f.*, u.`username`, u.`email`
                 FROM `' . $fbTable . '` f
                 LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                 WHERE f.`kind` = ? AND f.`userid` = ?
                 ORDER BY f.`id` DESC LIMIT 1'
            );
            $stmt->execute(array(self::KIND_DEVAPPLY, $userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? self::formatDevApplyRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array $opts tab, page, pagesize
     * @return array{list:array,total:int,page:int,pagesize:int,counts:array}
     */
    public static function listDevApplyPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $tab = isset($opts['tab']) ? trim((string) $opts['tab']) : 'pending';
        if ($tab !== 'pending' && $tab !== 'approved' && $tab !== 'rejected') {
            $tab = 'pending';
        }
        $counts = array('pending' => 0, 'approved' => 0, 'rejected' => 0);
        $empty = array(
            'list'     => array(),
            'total'    => 0,
            'page'     => $page,
            'pagesize' => $pagesize,
            'counts'   => $counts,
        );
        if (!self::tableReady() || !self::hasKindColumn()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            foreach (array(
                'pending'  => self::STATUS_PENDING,
                'approved' => self::STATUS_APPROVED,
                'rejected' => self::STATUS_REJECTED,
            ) as $key => $st) {
                $c = $pdo->prepare(
                    'SELECT COUNT(*) FROM `' . $fbTable . '` WHERE `kind` = ? AND `status` = ?'
                );
                $c->execute(array(self::KIND_DEVAPPLY, $st));
                $counts[$key] = (int) $c->fetchColumn();
            }
            $status = self::STATUS_PENDING;
            if ($tab === 'approved') {
                $status = self::STATUS_APPROVED;
            } elseif ($tab === 'rejected') {
                $status = self::STATUS_REJECTED;
            }
            $total = isset($counts[$tab]) ? (int) $counts[$tab] : 0;
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            if ($total === 0) {
                return array(
                    'list'     => array(),
                    'total'    => 0,
                    'page'     => 1,
                    'pagesize' => $pagesize,
                    'counts'   => $counts,
                );
            }
            $offset = (int) (($page - 1) * $pagesize);
            $stmt = $pdo->prepare(
                'SELECT f.*, u.`username`, u.`email`, u.`avatar`
                 FROM `' . $fbTable . '` f
                 LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                 WHERE f.`kind` = ? AND f.`status` = ?
                 ORDER BY f.`id` DESC
                 LIMIT ' . $pagesize . ' OFFSET ' . $offset
            );
            $stmt->execute(array(self::KIND_DEVAPPLY, $status));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $list[] = self::formatDevApplyRow($row);
                    }
                }
            }
            return array(
                'list'     => $list,
                'total'    => $total,
                'page'     => $page,
                'pagesize' => $pagesize,
                'counts'   => $counts,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 普通用户申请开发者身份（每人仅一条待审；拒/降级后可复用行）
     *
     * @param int    $userId
     * @param string $reason
     * @return array|string
     */
    public static function applyDeveloper($userId, $reason)
    {
        $userId = (int) $userId;
        $reason = trim((string) $reason);
        if ($userId <= 0) {
            return '请先登录';
        }
        if (!self::tableReady() || !self::hasKindColumn()) {
            return '功能尚未就绪，请先执行数据库结构更新';
        }
        if (!class_exists('UserManager') || !class_exists('UserRole')) {
            return '用户模块未就绪';
        }
        if ($reason === '') {
            return '请填写申请理由';
        }
        if (mb_strlen($reason, 'UTF-8') > 200) {
            return '申请理由不超过 200 字';
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();
            $lock = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `role`, `status`'
                . ' FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $lock->execute(array($userId));
            $user = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return '用户不存在';
            }
            $role = isset($user['role']) ? $user['role'] : UserRole::ROLE_USER;
            if (UserRole::normalize($role) !== UserRole::ROLE_USER) {
                $pdo->rollBack();
                return '仅普通用户可申请开发者';
            }
            if ((int) (isset($user['status']) ? $user['status'] : 0) !== 1) {
                $pdo->rollBack();
                return '账号已禁用，无法申请';
            }

            $table = Database::table('feedback');
            $chk = $pdo->prepare(
                'SELECT `id`, `status` FROM `' . $table . '`'
                . ' WHERE `kind` = ? AND `userid` = ? ORDER BY `id` DESC FOR UPDATE'
            );
            $chk->execute(array(self::KIND_DEVAPPLY, $userId));
            $rows = $chk->fetchAll(PDO::FETCH_ASSOC);
            $pendingId = 0;
            $reuseId = 0;
            $reuseFromStatus = -1;
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $st = (int) (isset($r['status']) ? $r['status'] : -1);
                    $rid = (int) (isset($r['id']) ? $r['id'] : 0);
                    if ($st === self::STATUS_PENDING && $pendingId <= 0) {
                        $pendingId = $rid;
                    }
                    if (($st === self::STATUS_REJECTED || $st === self::STATUS_APPROVED)
                        && $reuseId <= 0
                    ) {
                        $reuseId = $rid;
                        $reuseFromStatus = $st;
                    }
                }
            }
            if ($pendingId > 0) {
                $pdo->rollBack();
                return '您已有待审核的开发者申请，请勿重复提交';
            }

            // 对标接口反馈 create()：同用户 60 秒内不可反复提交（防拒后刷管理员邮件）
            $rate = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '`'
                . ' WHERE `kind` = ? AND `userid` = ?'
                . ' AND (`createtime` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
                . '  OR (`updatetime` IS NOT NULL AND `updatetime` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)))'
                . ' LIMIT 1'
            );
            $rate->execute(array(self::KIND_DEVAPPLY, $userId));
            if ($rate->fetch()) {
                $pdo->rollBack();
                return '提交过于频繁，请稍后再试';
            }

            $id = 0;
            if ($reuseId > 0 && $reuseFromStatus >= 0) {
                $upd = $pdo->prepare(
                    'UPDATE `' . $table . '` SET'
                    . ' `content` = ?, `reply` = \'\', `status` = ?, `apiid` = 0, `updatetime` = NOW()'
                    . ' WHERE `id` = ? AND `kind` = ? AND `userid` = ? AND `status` = ? LIMIT 1'
                );
                $upd->execute(array(
                    $reason,
                    self::STATUS_PENDING,
                    $reuseId,
                    self::KIND_DEVAPPLY,
                    $userId,
                    $reuseFromStatus,
                ));
                if ($upd->rowCount() === 0) {
                    $pdo->rollBack();
                    return '提交失败，请稍后重试';
                }
                $id = $reuseId;
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO `' . $table . '`
                    (`kind`, `apiid`, `userid`, `content`, `reply`, `status`, `createtime`)
                    VALUES (?, 0, ?, ?, \'\', ?, NOW())'
                );
                $ins->execute(array(
                    self::KIND_DEVAPPLY,
                    $userId,
                    $reason,
                    self::STATUS_PENDING,
                ));
                $id = (int) $pdo->lastInsertId();
                if ($id <= 0) {
                    $pdo->rollBack();
                    return '提交失败，请稍后重试';
                }
            }

            $pdo->commit();
            $raw = self::findById($id);
            if (!is_array($raw)) {
                return '提交失败';
            }
            $formatted = self::formatDevApplyRow($raw);
            // 发信失败不得回滚申请（E431 / 《邮箱发信规范》业务通知）
            try {
                if (class_exists('MailNotify')) {
                    MailNotify::notifyAdminsDevApplyPending($formatted);
                }
            } catch (Exception $ignore) {
            }
            return $formatted;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Exception $ignore) {
                }
            }
            return '提交失败，请稍后重试';
        }
    }

    /**
     * @param int         $id
     * @param int         $status STATUS_APPROVED|STATUS_REJECTED
     * @param string|null $reply  非 null 时一并写入处理回复（选填，≤5000）
     * @return true|string
     */
    public static function setDevApplyStatus($id, $status, $reply = null)
    {
        if (!class_exists('Auth') || !Auth::check() || Auth::id() <= 0) {
            return '需要管理员登录后才能审核';
        }
        $id = (int) $id;
        $status = (int) $status;
        if ($status !== self::STATUS_APPROVED && $status !== self::STATUS_REJECTED) {
            return '无效审核状态';
        }
        if (!self::tableReady() || !self::hasKindColumn()) {
            return '功能尚未就绪，请先执行数据库结构更新';
        }
        if ($status === self::STATUS_APPROVED && !class_exists('UserManager')) {
            return '用户模块未就绪';
        }

        $saveReply = ($reply !== null);
        $replyText = '';
        if ($saveReply) {
            $replyText = trim((string) $reply);
            if (function_exists('mb_substr')) {
                $replyText = mb_substr($replyText, 0, 5000, 'UTF-8');
            } else {
                $replyText = substr($replyText, 0, 5000);
            }
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();
            $table = Database::table('feedback');
            $sel = $pdo->prepare(
                'SELECT * FROM `' . $table . '`'
                . ' WHERE `id` = ? AND `kind` = ? LIMIT 1 FOR UPDATE'
            );
            $sel->execute(array($id, self::KIND_DEVAPPLY));
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $pdo->rollBack();
                return '申请不存在';
            }
            $cur = (int) (isset($row['status']) ? $row['status'] : -1);
            if ($cur !== self::STATUS_PENDING) {
                $pdo->rollBack();
                return '仅待审核申请可操作';
            }
            $userId = isset($row['userid']) ? (int) $row['userid'] : 0;
            if ($userId <= 0) {
                $pdo->rollBack();
                return '申请人无效';
            }

            $userTable = Database::table('user');
            $uSel = $pdo->prepare(
                'SELECT `id`, `status`, `role`, `email`, `avatar`, `username`'
                . ' FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $uSel->execute(array($userId));
            $userRow = $uSel->fetch(PDO::FETCH_ASSOC);
            if (!is_array($userRow)) {
                $pdo->rollBack();
                return '申请人不存在';
            }
            $userStatus = (int) (isset($userRow['status']) ? $userRow['status'] : 0);
            $userRole = class_exists('UserRole')
                ? UserRole::normalize(isset($userRow['role']) ? $userRow['role'] : UserRole::ROLE_USER)
                : 'user';
            if ($status === self::STATUS_APPROVED) {
                if ($userStatus !== 1) {
                    $pdo->rollBack();
                    return '申请人账号已禁用，无法通过审核';
                }
                if ($userRole !== UserRole::ROLE_USER && $userRole !== UserRole::ROLE_DEVELOPER) {
                    $pdo->rollBack();
                    return '申请人身份异常，无法通过审核';
                }
            }

            if ($saveReply) {
                $upd = $pdo->prepare(
                    'UPDATE `' . $table . '` SET `status` = ?, `reply` = ?, `updatetime` = NOW()'
                    . ' WHERE `id` = ? AND `kind` = ? AND `status` = ? LIMIT 1'
                );
                $upd->execute(array($status, $replyText, $id, self::KIND_DEVAPPLY, self::STATUS_PENDING));
            } else {
                $upd = $pdo->prepare(
                    'UPDATE `' . $table . '` SET `status` = ?, `updatetime` = NOW()'
                    . ' WHERE `id` = ? AND `kind` = ? AND `status` = ? LIMIT 1'
                );
                $upd->execute(array($status, $id, self::KIND_DEVAPPLY, self::STATUS_PENDING));
            }
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                return '申请状态已变更，请刷新后重试';
            }

            if ($status === self::STATUS_APPROVED && $userRole !== UserRole::ROLE_DEVELOPER) {
                $roleOk = UserManager::setRole($userId, UserRole::ROLE_DEVELOPER);
                if ($roleOk !== true) {
                    $pdo->rollBack();
                    return is_string($roleOk) ? $roleOk : '设置开发者身份失败';
                }
            }

            $pdo->commit();
            // 发信失败不得回滚审核结果（E431）
            try {
                if (class_exists('MailNotify')) {
                    $raw = self::findById($id);
                    if (is_array($raw)) {
                        if (!isset($raw['username']) && isset($userRow['username'])) {
                            $raw['username'] = $userRow['username'];
                        }
                        if (!isset($raw['email']) && isset($userRow['email'])) {
                            $raw['email'] = $userRow['email'];
                        }
                        if (!isset($raw['avatar']) && isset($userRow['avatar'])) {
                            $raw['avatar'] = $userRow['avatar'];
                        }
                        MailNotify::notifyUserDevApplyResult(
                            self::formatDevApplyRow($raw),
                            $status === self::STATUS_APPROVED
                        );
                    }
                }
            } catch (Exception $ignore) {
            }
            return true;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Exception $ignore) {
                }
            }
            return '操作失败';
        }
    }

    /**
     * @param int $id
     * @return true|string
     */
    public static function deleteDevApply($id)
    {
        unset($id);
        if (!class_exists('Auth') || !Auth::check() || Auth::id() <= 0) {
            return '需要管理员登录后才能操作';
        }
        // 产品：开发者申请不支持删除（保留记录；降角色请走用户管理）
        return '不支持删除开发者申请';
    }

    /**
     * 管理员将开发者降为普通用户时：结案该用户待审/已通过申请
     *
     * @param int $userId
     * @return void
     */
    public static function closeDevAppliesOnDemote($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady() || !self::hasKindColumn()) {
            return;
        }
        try {
            $stmt = Database::connect()->prepare(
                'UPDATE `' . Database::table('feedback') . '` SET `status` = ?, `updatetime` = NOW()'
                . ' WHERE `kind` = ? AND `userid` = ? AND `status` IN (?, ?)'
            );
            $stmt->execute(array(
                self::STATUS_REJECTED,
                self::KIND_DEVAPPLY,
                $userId,
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
            ));
        } catch (Exception $e) {
            // 降级主流程不因归档失败而中断
        }
    }

    /**
     * 删除用户时清理其开发者申请行
     *
     * @param int $userId
     * @return void
     */
    public static function deleteDevAppliesForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !self::tableReady() || !self::hasKindColumn()) {
            return;
        }
        try {
            $stmt = Database::connect()->prepare(
                'DELETE FROM `' . Database::table('feedback') . '`'
                . ' WHERE `kind` = ? AND `userid` = ?'
            );
            $stmt->execute(array(self::KIND_DEVAPPLY, $userId));
        } catch (Exception $e) {
            // 删用户主流程不因清理失败而中断
        }
    }
}
