<?php
/**
 * 文件：core/UserManager.php
 * 作用：管理员用户列表，以及用户订阅开关（user.subjson）
 */

class UserManager
{
    /**
     * @return array
     */
    public static function all()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $pointsCol = class_exists('PointsManager') && PointsManager::hasPointsColumn()
                ? ', `points`'
                : '';
            $stmt = $pdo->query(
                'SELECT `id`, `username`, `email`, `avatar`, `qqopenid`, `giteeid`, `aggmap`,
                        `status`, `role`' . $pointsCol . ', `createtime`, `lastlogin`
                 FROM `' . $table . '`
                 ORDER BY `id` DESC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return array();
            }
            return self::attachListStats($rows);
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 列表附加：发布接口数（开发者用）+ 本人累计调用次数（apilog.userid）
     *
     * @param array $rows
     * @return array
     */
    public static function attachListStats(array $rows)
    {
        if ($rows === array()) {
            return $rows;
        }
        $ids = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === array()) {
            return $rows;
        }

        $apiCounts = array();
        $callCounts = array();
        try {
            $pdo = Database::connect();
            $in = implode(',', array_fill(0, count($ids), '?'));

            if (class_exists('ApiManager') && ApiManager::tableReady()) {
                $apiTable = Database::table('api');
                $stmt = $pdo->prepare(
                    'SELECT `userid`, COUNT(*) AS `cnt` FROM `' . $apiTable . '`
                     WHERE `userid` IN (' . $in . ') GROUP BY `userid`'
                );
                $stmt->execute($ids);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $apiCounts[(int) $r['userid']] = (int) $r['cnt'];
                }
            }

            if (class_exists('ApiLogManager') && ApiLogManager::tableReady()) {
                $logTable = Database::table('apilog');
                $stmt = $pdo->prepare(
                    'SELECT `userid`, COUNT(*) AS `cnt` FROM `' . $logTable . '`
                     WHERE `userid` IN (' . $in . ') GROUP BY `userid`'
                );
                $stmt->execute($ids);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $callCounts[(int) $r['userid']] = (int) $r['cnt'];
                }
            }
        } catch (Exception $e) {
            // 统计失败不阻断列表
        }

        foreach ($rows as $i => $row) {
            $uid = isset($row['id']) ? (int) $row['id'] : 0;
            $rows[$i]['api_count'] = isset($apiCounts[$uid]) ? $apiCounts[$uid] : 0;
            $rows[$i]['call_count'] = isset($callCounts[$uid]) ? $callCounts[$uid] : 0;
        }
        return $rows;
    }

    /**
     * @param string $account 用户名或邮箱
     * @return array|null
     */
    public static function findByAccount($account)
    {
        $account = trim((string) $account);
        if ($account === '') {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `status`, `role`, `createtime`
                 FROM `' . $table . '`
                 WHERE `username` = ? OR `email` = ?
                 LIMIT 1'
            );
            $stmt->execute(array($account, $account));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array|null
     */
    public static function findById($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `status`, `role`, `createtime`
                 FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return int
     */
    public static function count()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            return (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $userId
     * @param int $status 1=正常 0=封禁
     * @return true|string
     */
    public static function setStatus($userId, $status)
    {
        $userId = (int) $userId;
        $status = (int) $status;
        if ($userId <= 0) {
            return '无效用户';
        }
        if ($status !== 0 && $status !== 1) {
            return '无效状态';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `status` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($status, $userId));
            if ($stmt->rowCount() === 0 && !self::exists($userId)) {
                return '用户不存在';
            }
            if (class_exists('UserAuth')) {
                UserAuth::forgetStatusCache($userId);
            }
            if (class_exists('RedisCache')) {
                RedisCache::invalidateApiStatusSubscribers();
            }
            return true;
        } catch (Exception $e) {
            return '操作失败：' . $e->getMessage();
        }
    }

    /**
     * @param int    $userId
     * @param string $role user|developer
     * @return true|string
     */
    public static function setRole($userId, $role)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '无效用户';
        }

        $role = UserRole::normalize($role);

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            // 读旧角色：降级时结案开发者申请（E424）
            $prevRole = '';
            if (class_exists('UserRole') && $role === UserRole::ROLE_USER) {
                $prev = $pdo->prepare(
                    'SELECT `role` FROM `' . $table . '` WHERE `id` = ? LIMIT 1'
                );
                $prev->execute(array($userId));
                $prevRow = $prev->fetch(PDO::FETCH_ASSOC);
                $prevRole = $prevRow && isset($prevRow['role'])
                    ? UserRole::normalize($prevRow['role'])
                    : '';
            }
            $stmt = $pdo->prepare('UPDATE `' . $table . '` SET `role` = ? WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($role, $userId));
            if ($stmt->rowCount() === 0 && !self::exists($userId)) {
                return '用户不存在';
            }
            if ($prevRole === UserRole::ROLE_DEVELOPER
                && $role === UserRole::ROLE_USER
                && class_exists('ApiFeedbackManager')
                && method_exists('ApiFeedbackManager', 'closeDevAppliesOnDemote')
            ) {
                ApiFeedbackManager::closeDevAppliesOnDemote($userId);
            }
            return true;
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('UserManager::setRole: ' . $e->getMessage());
            }
            return '设置身份失败';
        }
    }

    /**
     * @param int $userId
     * @return true|string
     */
    public static function delete($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '无效用户';
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId));
            if ($stmt->rowCount() === 0) {
                return '用户不存在';
            }
            if (class_exists('ApiFeedbackManager')
                && method_exists('ApiFeedbackManager', 'deleteDevAppliesForUser')
            ) {
                ApiFeedbackManager::deleteDevAppliesForUser($userId);
            }
            if (class_exists('UserAuth')) {
                UserAuth::forgetStatusCache($userId);
            }
            if (class_exists('RedisCache')) {
                RedisCache::invalidateApiStatusSubscribers();
            }
            return true;
        } catch (Exception $e) {
            return '删除失败：' . $e->getMessage();
        }
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function exists($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare('SELECT `id` FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($userId));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 站点是否开放订阅
     *
     * @return bool
     */
    public static function subscribeSiteOn()
    {
        return Config::get('subon', '0') === '1';
    }

    /**
     * @param int $userId
     * @return bool
     */
    public static function wantsApiStatus($userId)
    {
        $bag = self::readSubscribeBag((int) $userId);
        return !empty($bag['apistatus']);
    }

    /**
     * @param int $userId
     * @param int $on 0|1
     * @return true|string
     */
    public static function setApiStatus($userId, $on)
    {
        $userId = (int) $userId;
        $on = ((int) $on) === 1 ? 1 : 0;
        if ($userId <= 0) {
            return '请先登录';
        }
        if ($on === 1 && !self::subscribeSiteOn()) {
            return '站点尚未开放订阅';
        }
        if (!class_exists('DatabaseMigrator') || !DatabaseMigrator::tableColumnExists('user', 'subjson')) {
            return '订阅功能尚未就绪，请先完成系统升级';
        }
        $bag = self::readSubscribeBag($userId);
        $bag['apistatus'] = $on;
        $json = json_encode($bag, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return '保存失败，请稍后重试';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `subjson` = ? WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($json, $userId));
            if (class_exists('RedisCache')) {
                RedisCache::invalidateApiStatusSubscribers();
            }
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * 已打开接口状态订阅、且邮箱可用的正常用户。
     * 查询失败与成功但无订阅者必须由结果类型区分。
     *
     * @return array{ok:bool,users:array<int,array>,error:string}
     */
    public static function apiStatusSubscribers()
    {
        $snapshot = array('available' => false, 'generation' => 0, 'users' => null);
        if (class_exists('RedisCache')) {
            $snapshot = RedisCache::apiStatusSubscribersCacheSnapshot();
            if (!empty($snapshot['available']) && is_array($snapshot['users'])) {
                return array('ok' => true, 'users' => $snapshot['users'], 'error' => '');
            }
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `subjson` FROM `' . Database::table('user') . '` '
                . 'WHERE `status` = 1 AND `id` > ? AND `subjson` IS NOT NULL AND `subjson` <> \'\' '
                . 'ORDER BY `id` ASC LIMIT 200'
            );
            $out = array();
            $lastId = 0;
            do {
                $stmt->execute(array($lastId));
                $batchCount = 0;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $batchCount++;
                    $lastId = (int) $row['id'];
                    $raw = isset($row['subjson']) ? (string) $row['subjson'] : '';
                    $bag = json_decode($raw, true);
                    if (!is_array($bag) || (int) (isset($bag['apistatus']) ? $bag['apistatus'] : 0) !== 1) {
                        continue;
                    }
                    $email = isset($row['email']) ? trim((string) $row['email']) : '';
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    unset($row['subjson']);
                    $out[] = $row;
                }
            } while ($batchCount === 200);
        } catch (Exception $e) {
            return array('ok' => false, 'users' => array(), 'error' => 'database_query_failed');
        }
        if (class_exists('RedisCache')) {
            RedisCache::putApiStatusSubscribersIfCurrent(
                isset($snapshot['generation']) ? (int) $snapshot['generation'] : 0,
                $out
            );
        }
        return array('ok' => true, 'users' => $out, 'error' => '');
    }

    /**
     * @param int $userId
     * @return array
     */
    private static function readSubscribeBag($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array('apistatus' => 0);
        }
        try {
            if (!class_exists('DatabaseMigrator') || !DatabaseMigrator::tableColumnExists('user', 'subjson')) {
                return array('apistatus' => 0);
            }
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `subjson` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $raw = $stmt->fetchColumn();
            $bag = array();
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $bag = $decoded;
                }
            }
            $bag['apistatus'] = (!empty($bag['apistatus']) && (int) $bag['apistatus'] === 1) ? 1 : 0;
            return $bag;
        } catch (Exception $e) {
            return array('apistatus' => 0);
        }
    }
}
