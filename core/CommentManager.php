<?php
/**
 * 文件：core/CommentManager.php
 * 作用：文章评论 CRUD（管理员处理；邮箱必填；头像按邮箱解析）
 */

class CommentManager
{
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('comment');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        try {
            return DatabaseMigrator::tableExists('comment');
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param mixed $status
     * @return int
     */
    public static function normalizeStatus($status)
    {
        $n = (int) $status;
        if ($n === self::STATUS_APPROVED || $n === self::STATUS_REJECTED) {
            return $n;
        }
        return self::STATUS_PENDING;
    }

    /**
     * @param mixed $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $n = self::normalizeStatus($status);
        if ($n === self::STATUS_APPROVED) {
            return '已通过';
        }
        if ($n === self::STATUS_REJECTED) {
            return '已拒绝';
        }
        return '待审核';
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
     * @param array|null $row
     * @return array|null
     */
    public static function formatRow($row)
    {
        if (!is_array($row) || !isset($row['id'])) {
            return null;
        }
        $status = self::normalizeStatus(isset($row['status']) ? $row['status'] : self::STATUS_PENDING);
        $email = isset($row['email']) ? trim((string) $row['email']) : '';
        $userid = isset($row['userid']) ? (int) $row['userid'] : 0;
        $nickname = isset($row['nickname']) ? trim((string) $row['nickname']) : '';
        if ($nickname === '') {
            $nickname = $email !== '' ? preg_replace('/@.*$/', '', $email) : ('用户#' . max(0, $userid));
        }

        $avatar = '';
        if ($userid > 0 && class_exists('UserAvatar')) {
            $avatar = UserAvatar::resolve(array(
                'id'     => $userid,
                'email'  => $email,
                'avatar' => isset($row['user_avatar']) ? (string) $row['user_avatar'] : '',
            ));
        } elseif (class_exists('UserAvatar')) {
            $avatar = UserAvatar::resolveByEmail($email, (int) $row['id']);
        }

        $time = isset($row['createtime']) ? (string) $row['createtime'] : '';
        if ($time !== '' && strlen($time) >= 16) {
            $timeShort = substr($time, 0, 16);
        } else {
            $timeShort = $time;
        }

        return array(
            'id'            => (int) $row['id'],
            'contentid'     => isset($row['contentid']) ? (int) $row['contentid'] : 0,
            'content_title' => isset($row['content_title']) ? trim((string) $row['content_title']) : '',
            'userid'        => $userid,
            'nickname'      => $nickname,
            'email'         => $email,
            'body'          => isset($row['body']) ? (string) $row['body'] : '',
            'reply'         => isset($row['reply']) ? (string) $row['reply'] : '',
            'ispinned'      => self::normalizeFlag(isset($row['ispinned']) ? $row['ispinned'] : 0),
            'status'        => $status,
            'status_label'  => self::statusLabel($status),
            'avatar_url'    => $avatar,
            'createtime'    => $time,
            'createtime_short' => $timeShort,
            'updatetime'    => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
        );
    }

    /**
     * @return array
     */
    public static function listAll()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $cmt = self::table();
            $content = Database::table('content');
            $sql = 'SELECT c.*, ct.`title` AS `content_title`
                    FROM `' . $cmt . '` c
                    LEFT JOIN `' . $content . '` ct ON ct.`id` = c.`contentid`
                    ORDER BY c.`ispinned` DESC, c.`id` DESC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $fmt = self::formatRow($row);
                    if ($fmt) {
                        $out[] = $fmt;
                    }
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
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
            $cmt = self::table();
            $content = Database::table('content');
            $stmt = $pdo->prepare(
                'SELECT c.*, ct.`title` AS `content_title`
                 FROM `' . $cmt . '` c
                 LEFT JOIN `' . $content . '` ct ON ct.`id` = c.`contentid`
                 WHERE c.`id` = ? LIMIT 1'
            );
            $stmt->execute(array($id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param array $payload
     * @return array|string
     */
    public static function create(array $payload)
    {
        if (!self::tableReady()) {
            return '评论功能尚未就绪';
        }
        $contentid = isset($payload['contentid']) ? (int) $payload['contentid'] : 0;
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $nickname = isset($payload['nickname']) ? trim((string) $payload['nickname']) : '';
        $body = isset($payload['body']) ? trim((string) $payload['body']) : '';
        $userid = isset($payload['userid']) ? (int) $payload['userid'] : 0;
        $status = isset($payload['status'])
            ? self::normalizeStatus($payload['status'])
            : self::STATUS_APPROVED;

        if ($contentid <= 0) {
            return '请选择关联文章';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '请填写有效的邮箱';
        }
        if (function_exists('mb_strlen')) {
            if (mb_strlen($email, 'UTF-8') > 100) {
                return '邮箱过长';
            }
            if (mb_strlen($nickname, 'UTF-8') > 50) {
                return '昵称过长';
            }
            $bodyLen = mb_strlen($body, 'UTF-8');
        } else {
            if (strlen($email) > 100) {
                return '邮箱过长';
            }
            if (strlen($nickname) > 50) {
                return '昵称过长';
            }
            $bodyLen = strlen($body);
        }
        if ($bodyLen < 2) {
            return '评论内容至少 2 个字';
        }
        if ($bodyLen > 2000) {
            return '评论内容过长';
        }

        if (class_exists('ContentManager') && ContentManager::tableReady()) {
            $art = ContentManager::findById($contentid);
            if (!$art || ContentManager::normalizeKind($art['kind']) !== ContentManager::KIND_ARTICLE) {
                return '关联文章不存在';
            }
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                 (`contentid`, `userid`, `nickname`, `email`, `body`, `reply`, `ispinned`, `status`, `createtime`)
                 VALUES (?, ?, ?, ?, ?, \'\', 0, ?, NOW())'
            );
            $stmt->execute(array($contentid, $userid, $nickname, $email, $body, $status));
            $id = (int) $pdo->lastInsertId();
            $row = self::findById($id);
            $fmt = self::formatRow($row);
            return $fmt ? $fmt : '创建失败';
        } catch (Exception $e) {
            return '创建评论失败';
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
        if (function_exists('mb_strlen') && mb_strlen($reply, 'UTF-8') > 2000) {
            $reply = mb_substr($reply, 0, 2000, 'UTF-8');
        } elseif (strlen($reply) > 2000) {
            $reply = substr($reply, 0, 2000);
        }
        if (!self::findById($id)) {
            return '评论不存在';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `reply` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($reply, $id));
            return true;
        } catch (Exception $e) {
            return '保存回复失败';
        }
    }

    /**
     * @param int $id
     * @param int $flag
     * @return true|string
     */
    public static function setPinned($id, $flag)
    {
        $id = (int) $id;
        $flag = self::normalizeFlag($flag);
        if (!self::findById($id)) {
            return '评论不存在';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `ispinned` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($flag, $id));
            return true;
        } catch (Exception $e) {
            return '操作失败';
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
        $status = self::normalizeStatus($status);
        if (!self::findById($id)) {
            return '评论不存在';
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
     * @return true|string
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if (!self::findById($id)) {
            return '评论不存在';
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
     * 某篇文章已通过评论（前台）
     *
     * @param int $contentid
     * @return array
     */
    public static function listApprovedByContent($contentid)
    {
        $contentid = (int) $contentid;
        if ($contentid <= 0 || !self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '`
                 WHERE `contentid` = ? AND `status` = ?
                 ORDER BY `ispinned` DESC, `id` DESC'
            );
            $stmt->execute(array($contentid, self::STATUS_APPROVED));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $fmt = self::formatRow($row);
                    if ($fmt) {
                        $out[] = $fmt;
                    }
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }
}
