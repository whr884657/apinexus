<?php
/**
 * 文件：core/ContentManager.php
 * 作用：公告与文章共用管理（表 content；kind 区分）
 */

class ContentManager
{
    const KIND_ANNOUNCEMENT = 0;
    const KIND_ARTICLE = 1;

    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_OFF = 2;

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
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            return $stmt && $stmt->fetchColumn();
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
        return self::KIND_ANNOUNCEMENT;
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
        return self::normalizeKind($kind) === self::KIND_ARTICLE ? '文章' : '公告';
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
            return '已下架';
        }
        return '草稿';
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

        return array(
            'id'            => $id,
            'kind'          => $kind,
            'kind_label'    => self::kindLabel($kind),
            'title'         => isset($row['title']) ? trim((string) $row['title']) : '',
            'summary'       => isset($row['summary']) ? trim((string) $row['summary']) : '',
            'body'          => isset($row['body']) ? (string) $row['body'] : '',
            'cover'         => isset($row['cover']) ? trim((string) $row['cover']) : '',
            'ispinned'      => self::normalizeFlag(isset($row['ispinned']) ? $row['ispinned'] : 0),
            'ispopup'       => self::normalizeFlag(isset($row['ispopup']) ? $row['ispopup'] : 0),
            'status'        => $status,
            'status_label'  => self::statusLabel($status),
            'userid'        => isset($row['userid']) ? (int) $row['userid'] : 0,
            'views'         => isset($row['views']) ? (int) $row['views'] : 0,
            'sort'          => isset($row['sort']) ? (int) $row['sort'] : 0,
            'createtime'    => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'    => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
        );
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
            $stmt = $pdo->prepare('SELECT * FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1');
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
            $sql = 'SELECT * FROM `' . self::table() . '` WHERE `kind` = ?';
            $params = array(self::normalizeKind($kind));
            if ($status !== null) {
                $sql .= ' AND `status` = ?';
                $params[] = self::normalizeStatus($status);
            }
            $sql .= ' ORDER BY `sort` ASC, `id` DESC';
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
            if ($beforeId > 0) {
                $where[] = '`id` < ?';
                $bind[] = $beforeId;
            }

            $sql = 'SELECT * FROM `' . self::table() . '` WHERE ' . implode(' AND ', $where)
                . ' ORDER BY `id` DESC LIMIT ' . ((int) $pagesize + 1);
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

        $kind = self::normalizeKind(isset($data['kind']) ? $data['kind'] : self::KIND_ANNOUNCEMENT);
        $title = trim((string) (isset($data['title']) ? $data['title'] : ''));
        $summary = trim((string) (isset($data['summary']) ? $data['summary'] : ''));
        $body = isset($data['body']) ? (string) $data['body'] : '';
        $cover = trim((string) (isset($data['cover']) ? $data['cover'] : ''));
        $ispinned = self::normalizeFlag(isset($data['ispinned']) ? $data['ispinned'] : 0);
        $ispopup = self::normalizeFlag(isset($data['ispopup']) ? $data['ispopup'] : 0);
        $status = self::normalizeStatus(isset($data['status']) ? $data['status'] : self::STATUS_DRAFT);
        $userid = isset($data['userid']) ? (int) $data['userid'] : 0;
        $sort = isset($data['sort']) ? (int) $data['sort'] : 0;

        $err = self::validateFields($title, $summary, $body, $cover);
        if ($err !== true) {
            return $err;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                (`kind`, `title`, `summary`, `body`, `cover`, `ispinned`, `ispopup`, `status`, `userid`, `sort`, `createtime`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute(array(
                $kind, $title, $summary, $body, $cover,
                $ispinned, $ispopup, $status, $userid, $sort,
            ));
            $id = (int) $pdo->lastInsertId();
            $row = self::findById($id);
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

        $err = self::validateFields($title, $summary, $body, $cover);
        if ($err !== true) {
            return $err;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET
                `kind` = ?, `title` = ?, `summary` = ?, `body` = ?, `cover` = ?,
                `ispinned` = ?, `ispopup` = ?, `status` = ?, `userid` = ?, `sort` = ?,
                `updatetime` = NOW()
                WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array(
                $kind, $title, $summary, $body, $cover,
                $ispinned, $ispopup, $status, $userid, $sort, $id,
            ));
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
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * @param int $id
     * @param int $status
     * @return true|string
     */
    public static function setStatus($id, $status)
    {
        $id = (int) $id;
        if (!is_array(self::findById($id))) {
            return '记录不存在';
        }
        $status = self::normalizeStatus($status);
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
        if (!is_array(self::findById($id))) {
            return '记录不存在';
        }
        $ispinned = self::normalizeFlag($ispinned);
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `ispinned` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($ispinned, $id));
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
        return true;
    }
}
