<?php
/**
 * 文件：core/ApiFeedbackManager.php
 * 作用：接口反馈 CRUD（管理员处理 / 列表）
 */

class ApiFeedbackManager
{
    /** 状态：待处理 */
    const STATUS_PENDING = 0;
    /** 状态：已处理 */
    const STATUS_DONE = 1;

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
     * @param int $status
     * @return string
     */
    public static function statusLabel($status)
    {
        return ((int) $status === self::STATUS_DONE) ? '已处理' : '待处理';
    }

    /**
     * 管理员列表：含用户名与接口名
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
            $sql = 'SELECT f.`id`, f.`apiid`, f.`userid`, f.`content`, f.`reply`, f.`status`,
                           f.`createtime`, f.`updatetime`,
                           u.`username` AS `username`,
                           a.`name` AS `api_name`
                    FROM `' . $fbTable . '` f
                    LEFT JOIN `' . $userTable . '` u ON u.`id` = f.`userid`
                    LEFT JOIN `' . $apiTable . '` a ON a.`id` = f.`apiid`
                    ORDER BY f.`id` DESC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
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
            $fbTable = Database::table('feedback');
            $userTable = Database::table('user');
            $apiTable = Database::table('api');
            $stmt = $pdo->prepare(
                'SELECT f.`id`, f.`apiid`, f.`userid`, f.`content`, f.`reply`, f.`status`,
                        f.`createtime`, f.`updatetime`,
                        u.`username` AS `username`,
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
        $status = ((int) $row['status'] === self::STATUS_DONE)
            ? self::STATUS_DONE
            : self::STATUS_PENDING;
        return array(
            'id'           => (int) $row['id'],
            'apiid'        => isset($row['apiid']) ? (int) $row['apiid'] : 0,
            'userid'       => isset($row['userid']) ? (int) $row['userid'] : 0,
            'content'      => isset($row['content']) ? (string) $row['content'] : '',
            'reply'        => isset($row['reply']) ? (string) $row['reply'] : '',
            'status'       => $status,
            'status_label' => self::statusLabel($status),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'   => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
            'username'     => isset($row['username']) ? (string) $row['username'] : '',
            'api_name'     => isset($row['api_name']) ? (string) $row['api_name'] : '',
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
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $stmt = $pdo->prepare(
                'UPDATE `' . $table . '` SET `status` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
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
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $stmt = $pdo->prepare(
                'UPDATE `' . $table . '` SET `reply` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
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
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * 登录用户提交反馈
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
        if ($len > 2000) {
            return '反馈内容不能超过 2000 字';
        }

        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 2000, 'UTF-8');
        } else {
            $content = substr($content, 0, 2000);
        }

        $api = class_exists('FrontendApi') ? FrontendApi::findForThemeById($apiid) : null;
        if ($api === null) {
            return '接口不存在或不可用';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');

            // 同一用户同一接口 60 秒内防刷
            $chk = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '`
                 WHERE `userid` = ? AND `apiid` = ?
                   AND `createtime` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                 ORDER BY `id` DESC LIMIT 1'
            );
            $chk->execute(array($userid, $apiid));
            if ($chk->fetch()) {
                return '提交过于频繁，请稍后再试';
            }

            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '`
                 (`apiid`, `userid`, `content`, `reply`, `status`, `createtime`)
                 VALUES (?, ?, ?, \'\', ?, NOW())'
            );
            $stmt->execute(array($apiid, $userid, $content, self::STATUS_PENDING));
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
}
