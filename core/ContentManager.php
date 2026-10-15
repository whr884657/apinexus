<?php
/**
 * 文件：core/ContentManager.php
 * 作用：公告 / 文章 / 站内公告 / 邮箱模板共用管理（表 content；kind 区分）
 */

class ContentManager
{
    const KIND_ANNOUNCEMENT = 0;
    const KIND_ARTICLE = 1;
    const KIND_MESSAGE = 2;
    /** 邮箱模板（不进前台；bindpage：0 自定义，≥10 系统邮件场景） */
    const KIND_MAILTPL = 3;

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_OFF = 2;

    /** 绑定前台页面：0 无；1 关于页（独占，且不进文章列表） */
    const BIND_NONE = 0;
    const BIND_ABOUT = 1;

    const COVER_LEFT = 0;
    const COVER_RIGHT = 1;
    const COVER_BG = 2;

    /** 受众（仅 kind=2 站内公告）：0 非站内；1 全部；2 开发者；3 普通用户 */
    const AUD_NONE = 0;
    const AUD_ALL = 1;
    const AUD_DEVELOPER = 2;
    const AUD_USER = 3;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('content');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        try {
            if (!DatabaseMigrator::tableExists('content')) {
                return false;
            }
            // 10.11.0+ 需要 bindpage
            return DatabaseMigrator::tableColumnExists('content', 'bindpage');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public static function hasBindPageColumn()
    {
        try {
            return DatabaseMigrator::tableColumnExists('content', 'bindpage');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public static function hasAudModeColumn()
    {
        try {
            return DatabaseMigrator::tableColumnExists('content', 'audmode');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param mixed $kind
     * @return int
     */
    public static function normalizeKind($kind)
    {
        $n = (int) $kind;
        if ($n === self::KIND_ARTICLE) {
            return self::KIND_ARTICLE;
        }
        if ($n === self::KIND_MESSAGE) {
            return self::KIND_MESSAGE;
        }
        if ($n === self::KIND_MAILTPL) {
            return self::KIND_MAILTPL;
        }
        if ($n === self::KIND_ANNOUNCEMENT) {
            return self::KIND_ANNOUNCEMENT;
        }
        // 未知 kind 不吞成公告语义上的「合法 0」；调用方 create/update 须再校验
        return self::KIND_ANNOUNCEMENT;
    }

    /**
     * @param mixed $kind
     * @return bool
     */
    public static function isKnownKind($kind)
    {
        $n = (int) $kind;
        return $n === self::KIND_ANNOUNCEMENT
            || $n === self::KIND_ARTICLE
            || $n === self::KIND_MESSAGE
            || $n === self::KIND_MAILTPL;
    }

    /**
     * @param mixed $audmode
     * @return int
     */
    public static function normalizeAudMode($audmode)
    {
        $n = (int) $audmode;
        if ($n === self::AUD_DEVELOPER || $n === self::AUD_USER || $n === self::AUD_ALL) {
            return $n;
        }
        return self::AUD_NONE;
    }

    /**
     * 站内公告发布用受众：非法则默认全部用户
     *
     * @param mixed $audmode
     * @return int
     */
    public static function normalizeMessageAudMode($audmode)
    {
        $n = self::normalizeAudMode($audmode);
        if ($n === self::AUD_NONE) {
            return self::AUD_ALL;
        }
        return $n;
    }

    /**
     * @param mixed $audmode
     * @return string
     */
    public static function audModeLabel($audmode)
    {
        $n = self::normalizeAudMode($audmode);
        if ($n === self::AUD_ALL) {
            return '全部用户';
        }
        if ($n === self::AUD_DEVELOPER) {
            return '开发者';
        }
        if ($n === self::AUD_USER) {
            return '普通用户';
        }
        return '—';
    }

    /**
     * 会话用户是否命中受众（禁止信任客户端 role）
     *
     * 置顶站内公告对所有登录用户可见（不改写库内 audmode，取消置顶后原受众恢复）
     *
     * @param array|string|null $userOrRole user 行或 role 字符串
     * @param mixed             $audmode
     * @param mixed             $ispinned
     * @return bool
     */
    public static function userMatchesAudMode($userOrRole, $audmode, $ispinned = 0)
    {
        if (self::normalizeFlag($ispinned) === 1) {
            return true;
        }
        $mode = self::normalizeAudMode($audmode);
        if ($mode === self::AUD_ALL) {
            return true;
        }
        if ($mode === self::AUD_NONE) {
            return false;
        }
        $role = '';
        if (is_array($userOrRole)) {
            $role = isset($userOrRole['role']) ? (string) $userOrRole['role'] : '';
        } else {
            $role = (string) $userOrRole;
        }
        $role = class_exists('UserRole') ? UserRole::normalize($role) : $role;
        if ($mode === self::AUD_DEVELOPER) {
            return $role === UserRole::ROLE_DEVELOPER;
        }
        if ($mode === self::AUD_USER) {
            return $role === UserRole::ROLE_USER;
        }
        return false;
    }

    /**
     * @param mixed $status
     * @return int
     */
    public static function normalizeStatus($status)
    {
        $n = (int) $status;
        if ($n === self::STATUS_PUBLISHED || $n === self::STATUS_OFF) {
            return $n;
        }
        return self::STATUS_DRAFT;
    }

    /**
     * @param mixed $flag
     * @return int
     */
    public static function normalizeFlag($flag)
    {
        return ((int) $flag === 1) ? 1 : 0;
    }

    /**
     * @param mixed $kind
     * @return string
     */
    public static function kindLabel($kind)
    {
        $n = self::normalizeKind($kind);
        if ($n === self::KIND_ARTICLE) {
            return '文章';
        }
        if ($n === self::KIND_MESSAGE) {
            return '站内公告';
        }
        if ($n === self::KIND_MAILTPL) {
            return '邮箱模板';
        }
        if ($n === self::KIND_ANNOUNCEMENT) {
            return '站外公告';
        }
        return '公告';
    }

    /**
     * @param mixed $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $n = self::normalizeStatus($status);
        if ($n === self::STATUS_PUBLISHED) {
            return '已发布';
        }
        if ($n === self::STATUS_OFF) {
            return '已隐藏';
        }
        return '草稿';
    }

    /**
     * 文章绑定页：0 无 / 1 关于页。邮箱模板请用 normalizeMailBindPage。
     *
     * @param mixed $bind
     * @return int
     */
    public static function normalizeBindPage($bind)
    {
        $n = (int) $bind;
        if ($n === self::BIND_ABOUT) {
            return self::BIND_ABOUT;
        }
        return self::BIND_NONE;
    }

    /**
     * 邮箱模板 bindpage：0 自定义；≥10 系统场景（见 MailChannels）
     *
     * @param mixed $bind
     * @return int
     */
    public static function normalizeMailBindPage($bind)
    {
        if (class_exists('MailChannels')) {
            return MailChannels::normalizeMailBind($bind);
        }
        return self::BIND_NONE;
    }

    /**
     * @param mixed $bind
     * @param int   $kind
     * @return string
     */
    public static function bindPageLabel($bind, $kind = null)
    {
        if ($kind !== null && self::normalizeKind($kind) === self::KIND_MAILTPL) {
            return class_exists('MailChannels') ? MailChannels::label($bind) : '自定义';
        }
        if (self::normalizeBindPage($bind) === self::BIND_ABOUT) {
            return '关于页';
        }
        return '无';
    }

    /**
     * @param mixed $layout
     * @return int
     */
    public static function normalizeCoverLayout($layout)
    {
        $n = (int) $layout;
        if ($n === self::COVER_RIGHT || $n === self::COVER_BG) {
            return $n;
        }
        return self::COVER_LEFT;
    }

    /**
     * @param mixed $layout
     * @return string
     */
    public static function coverLayoutLabel($layout)
    {
        $n = self::normalizeCoverLayout($layout);
        if ($n === self::COVER_RIGHT) {
            return '封面右侧';
        }
        if ($n === self::COVER_BG) {
            return '封面背景';
        }
        return '封面左侧';
    }

    /**
     * Markdown 正文转纯文本预览（跑马灯等）
     *
     * @param string $md
     * @param int    $maxLen
     * @return string
     */
    public static function plainTextPreview($md, $maxLen = 80)
    {
        $text = (string) $md;
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/```[\s\S]*?```/', ' ', $text);
        $text = preg_replace('/`[^`]*`/', ' ', $text);
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text);
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/^>\s+/m', '', $text);
        $text = preg_replace('/^[-*+]\s+/m', '', $text);
        $text = preg_replace('/^\d+\.\s+/m', '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        $maxLen = max(1, (int) $maxLen);
        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen, 'UTF-8') . '…';
    }

    /**
     * 站内公告详情 HTML 消毒：复用 Markdown DOM 白名单（禁止正则黑名单，E477）
     *
     * @param string $html
     * @return string
     */
    public static function sanitizeUserMessageHtml($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }
        if (class_exists('Markdown') && method_exists('Markdown', 'sanitizeHtml')) {
            return Markdown::sanitizeHtml($html);
        }
        return vs_e(strip_tags($html));
    }

    /**
     * @param array $row
     * @return array
     */
    public static function formatRow(array $row)
    {
        $id = (int) (isset($row['id']) ? $row['id'] : 0);
        $kind = self::normalizeKind(isset($row['kind']) ? $row['kind'] : self::KIND_ANNOUNCEMENT);
        $status = self::normalizeStatus(isset($row['status']) ? $row['status'] : self::STATUS_DRAFT);

        $userid = isset($row['userid']) ? (int) $row['userid'] : 0;
        $username = isset($row['username']) ? trim((string) $row['username']) : '';
        $authorAvatar = '';
        if ($userid > 0) {
            $authorAvatar = UserAvatar::resolve(array(
                'id'    => $userid,
                'email' => isset($row['email']) ? trim((string) $row['email']) : '',
                'avatar'=> isset($row['user_avatar']) ? trim((string) $row['user_avatar']) : '',
            ));
        }

        $audmode = self::AUD_NONE;
        if (array_key_exists('audmode', $row)) {
            $audmode = self::normalizeAudMode($row['audmode']);
        } elseif ($kind === self::KIND_MESSAGE) {
            $audmode = self::AUD_ALL;
        }

        return array(
            'id'            => $id,
            'kind'          => $kind,
            'kind_label'    => self::kindLabel($kind),
            'title'         => isset($row['title']) ? trim((string) $row['title']) : '',
            'summary'       => isset($row['summary']) ? trim((string) $row['summary']) : '',
            'body'          => isset($row['body'])
                ? vs_ensure_plaintext_field($row['body'])
                : '',
            'cover'         => isset($row['cover']) ? trim((string) $row['cover']) : '',
            'coverlayout'   => self::normalizeCoverLayout(isset($row['coverlayout']) ? $row['coverlayout'] : self::COVER_LEFT),
            'coverlayout_label' => self::coverLayoutLabel(isset($row['coverlayout']) ? $row['coverlayout'] : self::COVER_LEFT),
            'ispinned'      => self::normalizeFlag(isset($row['ispinned']) ? $row['ispinned'] : 0),
            'ispopup'       => self::normalizeFlag(isset($row['ispopup']) ? $row['ispopup'] : 0),
            'audmode'       => $audmode,
            'audmode_label' => self::audModeLabel($audmode),
            'status'        => $status,
            'status_label'  => ($kind === self::KIND_MAILTPL && $status === self::STATUS_OFF)
                ? '已停用'
                : self::statusLabel($status),
            'bindpage'      => ($kind === self::KIND_MAILTPL)
                ? self::normalizeMailBindPage(isset($row['bindpage']) ? $row['bindpage'] : self::BIND_NONE)
                : self::normalizeBindPage(isset($row['bindpage']) ? $row['bindpage'] : self::BIND_NONE),
            'bindpage_label'=> self::bindPageLabel(
                isset($row['bindpage']) ? $row['bindpage'] : self::BIND_NONE,
                $kind
            ),
            'userid'        => $userid,
            'username'      => $username,
            'author_avatar' => $authorAvatar,
            'views'         => isset($row['views']) ? (int) $row['views'] : 0,
            'sort'          => isset($row['sort']) ? (int) $row['sort'] : 0,
            'createtime'    => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'    => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
        );
    }

    /**
     * @return string
     */
    private static function selectWithUserSql()
    {
        $contentTable = self::table();
        $userTable = Database::table('user');

        return 'SELECT c.*, u.`username`, u.`email`, u.`avatar` AS `user_avatar`'
            . ' FROM `' . $contentTable . '` c'
            . ' LEFT JOIN `' . $userTable . '` u ON u.`id` = c.`userid`';
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
            $stmt = $pdo->prepare(self::selectWithUserSql() . ' WHERE c.`id` = ? LIMIT 1');
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int      $kind
     * @param int|null $status
     * @return array<int, array>
     */
    public static function listAll($kind, $status = null)
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = self::selectWithUserSql() . ' WHERE c.`kind` = ?';
            $params = array(self::normalizeKind($kind));
            if ($status !== null) {
                $sql .= ' AND c.`status` = ?';
                $params[] = self::normalizeStatus($status);
            }
            $sql .= ' ORDER BY c.`ispinned` DESC, c.`sort` ASC, c.`id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $out[] = self::formatRow($row);
                    }
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 后台真分页：独立 COUNT + LIMIT OFFSET（pagesize 1–50）
     *
     * @param array $opts kind, q?, page, pagesize
     * @return array{list:array,total:int,page:int,pagesize:int}
     */
    public static function listAdminPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(50, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $kinds = array();
        if (isset($opts['kinds']) && is_array($opts['kinds'])) {
            foreach ($opts['kinds'] as $k) {
                if (!self::isKnownKind($k)) {
                    continue;
                }
                $nk = self::normalizeKind($k);
                if (!in_array($nk, $kinds, true)) {
                    $kinds[] = $nk;
                }
            }
        }
        if (empty($kinds)) {
            $kinds[] = self::normalizeKind(isset($opts['kind']) ? $opts['kind'] : self::KIND_ARTICLE);
        }
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        if (function_exists('mb_substr') && mb_strlen($q, 'UTF-8') > 64) {
            $q = mb_substr($q, 0, 64, 'UTF-8');
        } elseif (strlen($q) > 64) {
            $q = substr($q, 0, 64);
        }
        $empty = array('list' => array(), 'total' => 0, 'page' => $page, 'pagesize' => $pagesize);
        if (!self::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $userTable = Database::table('user');
            $contentTable = self::table();
            $where = array();
            $bind = array();
            if (count($kinds) === 1) {
                $where[] = 'c.`kind` = ?';
                $bind[] = $kinds[0];
            } else {
                $ph = implode(',', array_fill(0, count($kinds), '?'));
                $where[] = 'c.`kind` IN (' . $ph . ')';
                foreach ($kinds as $k) {
                    $bind[] = $k;
                }
            }
            if ($q !== '') {
                $like = function_exists('vs_sql_like_contains')
                    ? vs_sql_like_contains($q)
                    : ('%' . addcslashes($q, "\\%_") . '%');
                $where[] = '(c.`title` LIKE ? ESCAPE \'\\\\\' OR u.`username` LIKE ? ESCAPE \'\\\\\')';
                $bind[] = $like;
                $bind[] = $like;
            }
            $whereSql = implode(' AND ', $where);
            $from = ' FROM `' . $contentTable . '` c LEFT JOIN `' . $userTable . '` u ON u.`id` = c.`userid` WHERE ' . $whereSql;
            $cnt = $pdo->prepare('SELECT COUNT(*)' . $from);
            $cnt->execute($bind);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            if ($total === 0) {
                return array('list' => array(), 'total' => 0, 'page' => 1, 'pagesize' => $pagesize);
            }
            $offset = (int) (($page - 1) * $pagesize);
            $sql = self::selectWithUserSql() . ' WHERE ' . $whereSql
                . ' ORDER BY c.`ispinned` DESC, c.`sort` ASC, c.`id` DESC LIMIT ' . $pagesize . ' OFFSET ' . $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $list[] = self::formatRow($row);
                    }
                }
            }
            return array(
                'list'     => $list,
                'total'    => $total,
                'page'     => $page,
                'pagesize' => $pagesize,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 分页列表：keyset（before_id），禁止 COUNT
     *
     * @param array $opts kind?, status?, pagesize, before_id?, page?
     * @return array{list:array,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool}
     */
    public static function listPaged(array $opts = array())
    {
        $page = max(1, (int) (isset($opts['page']) ? $opts['page'] : 1));
        $pagesize = max(1, min(100, (int) (isset($opts['pagesize']) ? $opts['pagesize'] : 20)));
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $empty = array(
            'list'           => array(),
            'page'           => $page,
            'pagesize'       => $pagesize,
            'before_id'      => $beforeId,
            'next_before_id' => 0,
            'has_more'       => false,
        );
        if (!self::tableReady()) {
            return $empty;
        }

        try {
            $pdo = Database::connect();
            $where = array('1=1');
            $bind = array();

            if (array_key_exists('kind', $opts) && $opts['kind'] !== null && $opts['kind'] !== '') {
                $where[] = '`kind` = ?';
                $bind[] = self::normalizeKind($opts['kind']);
            }
            if (array_key_exists('status', $opts) && $opts['status'] !== null && $opts['status'] !== '') {
                $where[] = '`status` = ?';
                $bind[] = self::normalizeStatus($opts['status']);
            }
            if (!empty($opts['exclude_bound'])) {
                $where[] = '`bindpage` = ?';
                $bind[] = self::BIND_NONE;
            }
            if ($beforeId > 0) {
                $where[] = '`id` < ?';
                $bind[] = $beforeId;
            }

            $sql = 'SELECT * FROM `' . self::table() . '` WHERE ' . implode(' AND ', $where)
                . ' ORDER BY `ispinned` DESC, `id` DESC LIMIT ' . ((int) $pagesize + 1);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hasMore = is_array($rows) && count($rows) > $pagesize;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $pagesize);
            }

            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $list[] = self::formatRow($row);
                    }
                }
            }

            $nextBefore = 0;
            if (!empty($list)) {
                $last = $list[count($list) - 1];
                $nextBefore = isset($last['id']) ? (int) $last['id'] : 0;
            }

            return array(
                'list'           => $list,
                'page'           => $page,
                'pagesize'       => $pagesize,
                'before_id'      => $beforeId,
                'next_before_id' => $nextBefore,
                'has_more'       => $hasMore,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * @param array $data
     * @return array|string
     */
    public static function create(array $data)
    {
        if (!self::tableReady()) {
            return '功能尚未就绪，请先执行数据库结构更新';
        }

        $rawKind = isset($data['kind']) ? (int) $data['kind'] : self::KIND_ANNOUNCEMENT;
        if (!self::isKnownKind($rawKind)) {
            return '无效的内容类型';
        }
        $kind = self::normalizeKind($rawKind);
        $title = trim((string) (isset($data['title']) ? $data['title'] : ''));
        $summary = trim((string) (isset($data['summary']) ? $data['summary'] : ''));
        $body = isset($data['body']) ? (string) $data['body'] : '';
        $cover = trim((string) (isset($data['cover']) ? $data['cover'] : ''));
        $coverlayout = self::normalizeCoverLayout(isset($data['coverlayout']) ? $data['coverlayout'] : self::COVER_LEFT);
        $ispinned = self::normalizeFlag(isset($data['ispinned']) ? $data['ispinned'] : 0);
        $ispopup = self::normalizeFlag(isset($data['ispopup']) ? $data['ispopup'] : 0);
        $status = self::normalizeStatus(isset($data['status']) ? $data['status'] : self::STATUS_DRAFT);
        $userid = isset($data['userid']) ? (int) $data['userid'] : 0;
        $sort = isset($data['sort']) ? (int) $data['sort'] : 0;
        $bindpage = self::BIND_NONE;
        if ($kind === self::KIND_ARTICLE) {
            $bindpage = self::normalizeBindPage(isset($data['bindpage']) ? $data['bindpage'] : self::BIND_NONE);
        } elseif ($kind === self::KIND_MAILTPL) {
            $bindpage = self::normalizeMailBindPage(isset($data['bindpage']) ? $data['bindpage'] : self::BIND_NONE);
        }

        $audmode = self::AUD_NONE;
        $hasAud = self::hasAudModeColumn();
        if ($kind === self::KIND_MESSAGE) {
            if (!$hasAud) {
                return '站内公告尚未就绪，请先执行数据库结构更新';
            }
            // 置顶不改写 audmode；用户可见性在查询/匹配层对置顶放行
            $audmode = self::normalizeMessageAudMode(isset($data['audmode']) ? $data['audmode'] : self::AUD_ALL);
            $cover = '';
            $coverlayout = self::COVER_LEFT;
            $summary = '';
        }
        if ($kind === self::KIND_MAILTPL) {
            $cover = '';
            $coverlayout = self::COVER_LEFT;
            $ispinned = 0;
            $ispopup = 0;
            $audmode = self::AUD_NONE;
            if ($summary === '') {
                return '请填写发信标题';
            }
            if (trim($body) === '') {
                return '请填写邮件正文';
            }
            if (class_exists('MailChannels') && MailChannels::isOtpChannel($bindpage)
                && !MailChannels::bodyHasOtpCodePlaceholder($body)) {
                return '验证码场景正文必须包含 {{otpcode}}';
            }
            if (class_exists('MailNotify')) {
                $body = MailNotify::sanitizeMailHtml($body);
                // 业务通知 10～26：入库即剥站内 URL（E432）；验证码/自定义不剥
                if (class_exists('MailChannels') && MailChannels::isBusinessChannel($bindpage)) {
                    $summary = MailNotify::stripSiteUrls($summary);
                    $body = MailNotify::stripSiteUrls($body);
                }
            }
        }

        $err = self::validateFields($title, $summary, $body, $cover);
        if ($err !== true) {
            return $err;
        }
        if ($kind === self::KIND_ARTICLE && $bindpage === self::BIND_ABOUT) {
            $occupied = self::findBoundAboutId();
            if ($occupied > 0) {
                return '关于页已被其他文章绑定';
            }
        }
        if ($kind === self::KIND_MAILTPL && $status === self::STATUS_PUBLISHED
            && class_exists('MailChannels') && MailChannels::isSystemChannel($bindpage)) {
            $occupied = self::findBoundMailChannelId($bindpage);
            if ($occupied > 0) {
                return '该邮件场景已有已发布模板，请先停用或删除后再绑定';
            }
        }

        try {
            $pdo = Database::connect();
            if ($hasAud) {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                    (`kind`, `title`, `summary`, `body`, `cover`, `coverlayout`, `ispinned`, `ispopup`, `audmode`, `status`, `bindpage`, `userid`, `sort`, `createtime`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $kind, $title, $summary, $body, $cover, $coverlayout,
                    $ispinned, $ispopup, $audmode, $status, $bindpage, $userid, $sort,
                ));
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . self::table() . '`
                    (`kind`, `title`, `summary`, `body`, `cover`, `coverlayout`, `ispinned`, `ispopup`, `status`, `bindpage`, `userid`, `sort`, `createtime`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute(array(
                    $kind, $title, $summary, $body, $cover, $coverlayout,
                    $ispinned, $ispopup, $status, $bindpage, $userid, $sort,
                ));
            }
            $id = (int) $pdo->lastInsertId();
            $row = self::findById($id);
            if (class_exists('RedisCache') && $kind !== self::KIND_MESSAGE && $kind !== self::KIND_MAILTPL) {
                RedisCache::invalidateFrontend();
            }
            return is_array($row) ? self::formatRow($row) : '保存失败';
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int   $id
     * @param array $data
     * @return true|string
     */
    public static function update($id, array $data)
    {
        $id = (int) $id;
        $existing = self::findById($id);
        if (!is_array($existing)) {
            return '记录不存在';
        }

        if (array_key_exists('kind', $data) && !self::isKnownKind($data['kind'])) {
            return '无效的内容类型';
        }
        $kind = array_key_exists('kind', $data)
            ? self::normalizeKind($data['kind'])
            : self::normalizeKind(isset($existing['kind']) ? $existing['kind'] : self::KIND_ANNOUNCEMENT);
        $title = trim((string) (isset($data['title']) ? $data['title'] : $existing['title']));
        $summary = array_key_exists('summary', $data)
            ? trim((string) $data['summary'])
            : trim((string) (isset($existing['summary']) ? $existing['summary'] : ''));
        $body = array_key_exists('body', $data)
            ? (string) $data['body']
            : (string) (isset($existing['body']) ? $existing['body'] : '');
        $cover = array_key_exists('cover', $data)
            ? trim((string) $data['cover'])
            : trim((string) (isset($existing['cover']) ? $existing['cover'] : ''));
        $coverlayout = array_key_exists('coverlayout', $data)
            ? self::normalizeCoverLayout($data['coverlayout'])
            : self::normalizeCoverLayout(isset($existing['coverlayout']) ? $existing['coverlayout'] : self::COVER_LEFT);
        $ispinned = isset($data['ispinned'])
            ? self::normalizeFlag($data['ispinned'])
            : self::normalizeFlag(isset($existing['ispinned']) ? $existing['ispinned'] : 0);
        $ispopup = isset($data['ispopup'])
            ? self::normalizeFlag($data['ispopup'])
            : self::normalizeFlag(isset($existing['ispopup']) ? $existing['ispopup'] : 0);
        $status = isset($data['status'])
            ? self::normalizeStatus($data['status'])
            : self::normalizeStatus(isset($existing['status']) ? $existing['status'] : self::STATUS_DRAFT);
        $userid = isset($data['userid'])
            ? (int) $data['userid']
            : (int) (isset($existing['userid']) ? $existing['userid'] : 0);
        $sort = isset($data['sort'])
            ? (int) $data['sort']
            : (int) (isset($existing['sort']) ? $existing['sort'] : 0);
        $existKind = self::normalizeKind(isset($existing['kind']) ? $existing['kind'] : self::KIND_ANNOUNCEMENT);
        $bindpage = self::BIND_NONE;
        if ($kind === self::KIND_ARTICLE) {
            $bindpage = array_key_exists('bindpage', $data)
                ? self::normalizeBindPage($data['bindpage'])
                : self::normalizeBindPage(isset($existing['bindpage']) ? $existing['bindpage'] : self::BIND_NONE);
        } elseif ($kind === self::KIND_MAILTPL) {
            $bindpage = array_key_exists('bindpage', $data)
                ? self::normalizeMailBindPage($data['bindpage'])
                : self::normalizeMailBindPage(isset($existing['bindpage']) ? $existing['bindpage'] : self::BIND_NONE);
        }

        $hasAud = self::hasAudModeColumn();
        $audmode = self::AUD_NONE;
        if ($hasAud) {
            if ($kind === self::KIND_MESSAGE) {
                // 置顶不改写 audmode；取消置顶后原受众自动恢复
                $audmode = array_key_exists('audmode', $data)
                    ? self::normalizeMessageAudMode($data['audmode'])
                    : self::normalizeMessageAudMode(isset($existing['audmode']) ? $existing['audmode'] : self::AUD_ALL);
                $cover = '';
                $coverlayout = self::COVER_LEFT;
                $summary = '';
            } else {
                $audmode = self::AUD_NONE;
            }
        } elseif ($kind === self::KIND_MESSAGE) {
            return '站内公告尚未就绪，请先执行数据库结构更新';
        }
        if ($kind === self::KIND_MAILTPL) {
            $cover = '';
            $coverlayout = self::COVER_LEFT;
            $ispinned = 0;
            $ispopup = 0;
            $audmode = self::AUD_NONE;
            if ($summary === '') {
                return '请填写发信标题';
            }
            if (trim($body) === '') {
                return '请填写邮件正文';
            }
            if (class_exists('MailChannels') && MailChannels::isOtpChannel($bindpage)
                && !MailChannels::bodyHasOtpCodePlaceholder($body)) {
                return '验证码场景正文必须包含 {{otpcode}}';
            }
            if (class_exists('MailNotify')) {
                $body = MailNotify::sanitizeMailHtml($body);
                if (class_exists('MailChannels') && MailChannels::isBusinessChannel($bindpage)) {
                    $summary = MailNotify::stripSiteUrls($summary);
                    $body = MailNotify::stripSiteUrls($body);
                }
            }
        }

        $err = self::validateFields($title, $summary, $body, $cover);
        if ($err !== true) {
            return $err;
        }
        if ($kind === self::KIND_ARTICLE && $bindpage === self::BIND_ABOUT) {
            $occupied = self::findBoundAboutId();
            if ($occupied > 0 && $occupied !== $id) {
                return '关于页已被其他文章绑定';
            }
        }
        if ($kind === self::KIND_MAILTPL && $status === self::STATUS_PUBLISHED
            && class_exists('MailChannels') && MailChannels::isSystemChannel($bindpage)) {
            $occupied = self::findBoundMailChannelId($bindpage, $id);
            if ($occupied > 0) {
                return '该邮件场景已有已发布模板，请先停用或删除后再绑定';
            }
        }
        unset($existKind);

        try {
            $pdo = Database::connect();
            if ($hasAud) {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                    `kind` = ?, `title` = ?, `summary` = ?, `body` = ?, `cover` = ?, `coverlayout` = ?,
                    `ispinned` = ?, `ispopup` = ?, `audmode` = ?, `status` = ?, `bindpage` = ?, `userid` = ?, `sort` = ?,
                    `updatetime` = NOW()
                    WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array(
                    $kind, $title, $summary, $body, $cover, $coverlayout,
                    $ispinned, $ispopup, $audmode, $status, $bindpage, $userid, $sort, $id,
                ));
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET
                    `kind` = ?, `title` = ?, `summary` = ?, `body` = ?, `cover` = ?, `coverlayout` = ?,
                    `ispinned` = ?, `ispopup` = ?, `status` = ?, `bindpage` = ?, `userid` = ?, `sort` = ?,
                    `updatetime` = NOW()
                    WHERE `id` = ? LIMIT 1'
                );
                $stmt->execute(array(
                    $kind, $title, $summary, $body, $cover, $coverlayout,
                    $ispinned, $ispopup, $status, $bindpage, $userid, $sort, $id,
                ));
            }
            if (class_exists('RedisCache') && $kind !== self::KIND_MESSAGE && $kind !== self::KIND_MAILTPL) {
                RedisCache::invalidateFrontend();
            }
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * @param int $id
     * @return true|string
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if (!is_array(self::findById($id))) {
            return '记录不存在';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            if (class_exists('RedisCache')) {
                RedisCache::invalidateFrontend();
            }
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * 当前绑定关于页的文章 ID（0=未绑定）
     *
     * @param bool $publishedOnly 前台取内容时仅已发布；占用检测含隐藏稿
     * @return int
     */
    public static function findBoundAboutId($publishedOnly = false)
    {
        if (!self::tableReady()) {
            return 0;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT `id` FROM `' . self::table() . '`
                 WHERE `kind` = ? AND `bindpage` = ?';
            $params = array(self::KIND_ARTICLE, self::BIND_ABOUT);
            if ($publishedOnly) {
                $sql .= ' AND `status` = ?';
                $params[] = self::STATUS_PUBLISHED;
            }
            $sql .= ' ORDER BY `id` DESC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
            return $id > 0 ? $id : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 关于页是否已被占用（可排除当前编辑文章）
     *
     * @param int $exceptId
     * @return bool
     */
    public static function isAboutBound($exceptId = 0)
    {
        $id = self::findBoundAboutId(false);
        if ($id <= 0) {
            return false;
        }
        $exceptId = (int) $exceptId;
        if ($exceptId > 0 && $id === $exceptId) {
            return false;
        }
        return true;
    }

    /**
     * 绑定关于页的已发布文章行（前台）
     *
     * @return array|null
     */
    public static function findBoundAboutRow()
    {
        $id = self::findBoundAboutId(true);
        if ($id <= 0) {
            return null;
        }
        return self::findById($id);
    }

    /**
     * @param int $id
     * @param int $status
     * @return true|string
     */
    public static function setStatus($id, $status)
    {
        $id = (int) $id;
        $row = self::findById($id);
        if (!is_array($row)) {
            return '记录不存在';
        }
        $status = self::normalizeStatus($status);
        $kind = self::normalizeKind(isset($row['kind']) ? $row['kind'] : self::KIND_ARTICLE);
        // 邮箱模板启用/发布：系统场景仍保证每场景仅一条已发布
        if ($kind === self::KIND_MAILTPL && $status === self::STATUS_PUBLISHED) {
            $bindpage = self::normalizeMailBindPage(isset($row['bindpage']) ? $row['bindpage'] : self::BIND_NONE);
            if (class_exists('MailChannels') && MailChannels::isSystemChannel($bindpage)) {
                $occupied = self::findBoundMailChannelId($bindpage, $id);
                if ($occupied > 0) {
                    return '该邮件场景已有已发布模板，请先停用或删除后再启用';
                }
            }
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($status, $id));
            return true;
        } catch (Exception $e) {
            return '操作失败';
        }
    }

    /**
     * @param int $id
     * @param int $ispinned
     * @return true|string
     */
    public static function setPinned($id, $ispinned)
    {
        $id = (int) $id;
        $row = self::findById($id);
        if (!is_array($row)) {
            return '记录不存在';
        }
        $ispinned = self::normalizeFlag($ispinned);
        $kind = self::normalizeKind(isset($row['kind']) ? $row['kind'] : self::KIND_ANNOUNCEMENT);
        try {
            $pdo = Database::connect();
            // 站内公告置顶不改写 audmode：可见性层对置顶放行；取消置顶后原受众自动恢复
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `ispinned` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($ispinned, $id));
            if (class_exists('RedisCache') && $kind !== self::KIND_MESSAGE) {
                RedisCache::invalidateFrontend();
            }
            return true;
        } catch (Exception $e) {
            return '操作失败';
        }
    }

    /**
     * @param int $id
     * @param int $ispopup
     * @return true|string
     */
    public static function setPopup($id, $ispopup)
    {
        $id = (int) $id;
        if (!is_array(self::findById($id))) {
            return '记录不存在';
        }
        $ispopup = self::normalizeFlag($ispopup);
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `ispopup` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($ispopup, $id));
            if (class_exists('RedisCache')) {
                RedisCache::invalidateFrontend();
            }
            return true;
        } catch (Exception $e) {
            return '操作失败';
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function incrementViews($id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `views` = `views` + 1 WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($id));
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 按用户 ID 取会话角色（仅服务端）
     *
     * @param int $userId
     * @return array{id:int,role:string}|null
     */
    private static function loadUserForMessage($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`, `role` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            return array(
                'id'   => (int) $row['id'],
                'role' => class_exists('UserRole')
                    ? UserRole::normalize(isset($row['role']) ? $row['role'] : UserRole::ROLE_USER)
                    : (string) (isset($row['role']) ? $row['role'] : 'user'),
            );
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 站内公告可见范围 WHERE + 绑定（置顶全员可见；角色匹配 audmode）
     *
     * @param string $role
     * @param string $q 标题搜索（已截断）
     * @return array{0:string,1:array}
     */
    private static function userMessageVisibleWhere($role, $q = '')
    {
        $sql = 'c.`kind` = ?
                  AND c.`status` = ?
                  AND (
                        c.`ispinned` = 1
                     OR c.`audmode` = ?
                     OR (c.`audmode` = ? AND ? = ?)
                     OR (c.`audmode` = ? AND ? = ?)
                  )';
        $params = array(
            self::KIND_MESSAGE,
            self::STATUS_PUBLISHED,
            self::AUD_ALL,
            self::AUD_DEVELOPER,
            $role,
            UserRole::ROLE_DEVELOPER,
            self::AUD_USER,
            $role,
            UserRole::ROLE_USER,
        );
        $q = trim((string) $q);
        if ($q !== '') {
            $sql .= ' AND c.`title` LIKE ? ESCAPE \'\\\\\'';
            $like = function_exists('vs_sql_like_contains')
                ? vs_sql_like_contains($q)
                : ('%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q) . '%');
            $params[] = $like;
        }
        return array($sql, $params);
    }

    /**
     * 格式化用户站内公告列表行（不拉全文）
     *
     * @param array $row
     * @return array|null
     */
    private static function formatUserMessageListItem(array $row)
    {
        $item = self::formatRow($row);
        if (!is_array($item)) {
            return null;
        }
        $body = isset($item['body']) ? (string) $item['body'] : '';
        return array(
            'id'         => (int) $item['id'],
            'title'      => (string) $item['title'],
            'preview'    => self::plainTextPreview($body, 80),
            'ispinned'   => (int) $item['ispinned'],
            'ispopup'    => (int) $item['ispopup'],
            'createtime' => (string) $item['createtime'],
        );
    }

    /**
     * 用户可见站内公告列表（只读；默认 20，最大 100；不拉全文 body；控制台/铃铛用）
     *
     * @param int    $userId
     * @param int    $limit
     * @param string $q
     * @return array<int, array>
     */
    public static function userListMessages($userId, $limit = 20, $q = '')
    {
        $limit = max(1, min(100, (int) $limit));
        $q = trim((string) $q);
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 80, 'UTF-8');
        } else {
            $q = substr($q, 0, 80);
        }
        if (!self::tableReady() || !self::hasAudModeColumn()) {
            return array();
        }
        $user = self::loadUserForMessage($userId);
        if ($user === null) {
            return array();
        }
        $role = $user['role'];
        try {
            $pdo = Database::connect();
            list($whereSql, $params) = self::userMessageVisibleWhere($role, $q);
            $sql = 'SELECT c.`id`, c.`title`, LEFT(c.`body`, 2000) AS `body`, c.`ispinned`, c.`ispopup`,
                           c.`audmode`, c.`status`, c.`kind`, c.`createtime`
                FROM `' . self::table() . '` c
                WHERE ' . $whereSql
                . ' ORDER BY c.`ispinned` DESC, c.`id` DESC LIMIT ' . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $one = self::formatUserMessageListItem($row);
                    if ($one !== null) {
                        $out[] = $one;
                    }
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 用户全部站内公告真分页（OFFSET + COUNT；pagesize 1～50）
     *
     * @param int    $userId
     * @param int    $page
     * @param int    $pagesize
     * @param string $q
     * @return array{items:array,total:int,page:int,pagesize:int}
     */
    public static function userListMessagesPaged($userId, $page = 1, $pagesize = 20, $q = '')
    {
        $page = max(1, (int) $page);
        $pagesize = max(1, min(50, (int) $pagesize));
        $q = trim((string) $q);
        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 80, 'UTF-8');
        } else {
            $q = substr($q, 0, 80);
        }
        $empty = array(
            'items'    => array(),
            'total'    => 0,
            'page'     => $page,
            'pagesize' => $pagesize,
        );
        if (!self::tableReady() || !self::hasAudModeColumn()) {
            return $empty;
        }
        $user = self::loadUserForMessage($userId);
        if ($user === null) {
            return $empty;
        }
        $role = $user['role'];
        try {
            $pdo = Database::connect();
            list($whereSql, $params) = self::userMessageVisibleWhere($role, $q);
            $from = ' FROM `' . self::table() . '` c WHERE ' . $whereSql;
            $cnt = $pdo->prepare('SELECT COUNT(*)' . $from);
            $cnt->execute($params);
            $total = (int) $cnt->fetchColumn();
            $maxPage = max(1, (int) ceil(($total > 0 ? $total : 1) / $pagesize));
            if ($page > $maxPage) {
                $page = $maxPage;
            }
            $offset = ($page - 1) * $pagesize;
            $sql = 'SELECT c.`id`, c.`title`, LEFT(c.`body`, 2000) AS `body`, c.`ispinned`, c.`ispopup`,
                           c.`audmode`, c.`status`, c.`kind`, c.`createtime`'
                . $from
                . ' ORDER BY c.`ispinned` DESC, c.`id` DESC LIMIT ' . (int) $pagesize
                . ' OFFSET ' . (int) $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $items = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $one = self::formatUserMessageListItem($row);
                    if ($one !== null) {
                        $items[] = $one;
                    }
                }
            }
            return array(
                'items'    => $items,
                'total'    => $total,
                'page'     => $page,
                'pagesize' => $pagesize,
            );
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 可自动弹出的可见站内公告（ispopup=1；仅 id 最大一条；独立查询，不被置顶列表挤出）
     *
     * @param int $userId
     * @return array<int, array> 0 或 1 条
     */
    public static function userListPopups($userId)
    {
        if (!self::tableReady() || !self::hasAudModeColumn()) {
            return array();
        }
        $user = self::loadUserForMessage($userId);
        if ($user === null) {
            return array();
        }
        $role = $user['role'];
        try {
            $pdo = Database::connect();
            $sql = 'SELECT c.`id`, c.`title`, LEFT(c.`body`, 2000) AS `body`, c.`ispinned`, c.`ispopup`,
                           c.`audmode`, c.`status`, c.`kind`, c.`createtime`
                FROM `' . self::table() . '` c
                WHERE c.`kind` = ?
                  AND c.`status` = ?
                  AND c.`ispopup` = 1
                  AND (
                        c.`ispinned` = 1
                     OR c.`audmode` = ?
                     OR (c.`audmode` = ? AND ? = ?)
                     OR (c.`audmode` = ? AND ? = ?)
                  )
                ORDER BY c.`id` DESC
                LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                self::KIND_MESSAGE,
                self::STATUS_PUBLISHED,
                self::AUD_ALL,
                self::AUD_DEVELOPER,
                $role,
                UserRole::ROLE_DEVELOPER,
                self::AUD_USER,
                $role,
                UserRole::ROLE_USER,
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return array();
            }
            $item = self::formatRow($row);
            return array(array(
                'id'         => (int) $item['id'],
                'title'      => (string) $item['title'],
                'preview'    => self::plainTextPreview(isset($item['body']) ? (string) $item['body'] : '', 80),
                'ispinned'   => (int) $item['ispinned'],
                'ispopup'    => (int) $item['ispopup'],
                'createtime' => (string) $item['createtime'],
            ));
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 用户可见站内公告详情（只读；隐藏/草稿当不存在；不含 audmode）
     *
     * @param int $userId
     * @param int $id
     * @return array|null
     */
    public static function userMessageDetail($userId, $id)
    {
        $id = (int) $id;
        if ($id <= 0 || !self::tableReady() || !self::hasAudModeColumn()) {
            return null;
        }
        $user = self::loadUserForMessage($userId);
        if ($user === null) {
            return null;
        }
        $row = self::findById($id);
        if (!is_array($row)) {
            return null;
        }
        $kind = self::normalizeKind(isset($row['kind']) ? $row['kind'] : -1);
        $status = self::normalizeStatus(isset($row['status']) ? $row['status'] : self::STATUS_DRAFT);
        if ($kind !== self::KIND_MESSAGE || $status !== self::STATUS_PUBLISHED) {
            return null;
        }
        $audmode = self::normalizeAudMode(isset($row['audmode']) ? $row['audmode'] : self::AUD_NONE);
        $ispinned = self::normalizeFlag(isset($row['ispinned']) ? $row['ispinned'] : 0);
        if (!self::userMatchesAudMode($user, $audmode, $ispinned)) {
            return null;
        }
        $item = self::formatRow($row);
        // 用户侧详情不返回 audmode；body 仅供入口渲染后剥离
        return array(
            'id'         => (int) $item['id'],
            'title'      => (string) $item['title'],
            'body'       => (string) $item['body'],
            'ispinned'   => (int) $item['ispinned'],
            'ispopup'    => (int) $item['ispopup'],
            'createtime' => (string) $item['createtime'],
        );
    }

    /**
     * @param string $title
     * @param string $summary
     * @param string $body
     * @param string $cover
     * @return true|string
     */
    private static function validateFields($title, $summary, $body, $cover)
    {
        if ($title === '') {
            return '请填写标题';
        }
        if (mb_strlen($title, 'UTF-8') > 200) {
            return '标题不超过 200 字';
        }
        if (mb_strlen($summary, 'UTF-8') > 500) {
            return '摘要不超过 500 字';
        }
        if (mb_strlen($cover, 'UTF-8') > 500) {
            return '封面链接不超过 500 字';
        }
        // 与接口文档上限同量级，防止超大正文拖垮渲染/内存
        if (strlen($body) > 200000) {
            return '正文过长（请控制在 20 万字节以内）';
        }
        return true;
    }

    /**
     * 某系统邮件场景是否已有已发布模板（可排除当前 id）
     *
     * @param int $bindpage
     * @param int $exceptId
     * @return int 占用的 content.id，无则 0
     */
    public static function findBoundMailChannelId($bindpage, $exceptId = 0)
    {
        $bindpage = self::normalizeMailBindPage($bindpage);
        if (!class_exists('MailChannels') || !MailChannels::isSystemChannel($bindpage) || !self::tableReady()) {
            return 0;
        }
        $exceptId = (int) $exceptId;
        try {
            $pdo = Database::connect();
            $sql = 'SELECT `id` FROM `' . self::table() . '`
                    WHERE `kind` = ? AND `bindpage` = ? AND `status` = ?';
            $params = array(self::KIND_MAILTPL, $bindpage, self::STATUS_PUBLISHED);
            if ($exceptId > 0) {
                $sql .= ' AND `id` <> ?';
                $params[] = $exceptId;
            }
            $sql .= ' ORDER BY `id` DESC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
            return $id > 0 ? $id : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 已发布的系统场景模板（发信用）
     *
     * @param int $bindpage
     * @return array|null formatRow
     */
    public static function findPublishedMailTemplate($bindpage)
    {
        $bindpage = self::normalizeMailBindPage($bindpage);
        if (!class_exists('MailChannels') || !MailChannels::isSystemChannel($bindpage) || !self::tableReady()) {
            return null;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '`
                 WHERE `kind` = ? AND `bindpage` = ? AND `status` = ?
                 ORDER BY `id` DESC LIMIT 1'
            );
            $stmt->execute(array(self::KIND_MAILTPL, $bindpage, self::STATUS_PUBLISHED));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? self::formatRow($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 群发可选：自定义且已发布
     *
     * @return array formatRow[]
     */
    public static function listCustomMailTemplates()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '`
                 WHERE `kind` = ? AND `bindpage` = ? AND `status` = ?
                 ORDER BY `id` DESC LIMIT 200'
            );
            $stmt->execute(array(
                self::KIND_MAILTPL,
                class_exists('MailChannels') ? MailChannels::BIND_CUSTOM : 0,
                self::STATUS_PUBLISHED,
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $list[] = self::formatRow($row);
                    }
                }
            }
            return $list;
        } catch (Exception $e) {
            return array();
        }
    }
}
