<?php
/**
 * 文件：core/FrontendContributor.php
 * 作用：前台贡献者列表与公开个人主页（主题只调本类，禁止直读库）
 *
 * 归属规则：api.userid = 用户，或（userid=0 且该用户为管理员当前绑定身份）——
 * 管理员后台发布与绑定用户前台发布视为同一作者。
 */

class FrontendContributor
{
    /**
     * 已发布公开接口的开发者列表
     *
     * @return array<int, array>
     */
    public static function listForTheme()
    {
        try {
            $pdo = Database::connect();
            $userTable = Database::table('user');
            $apiTable = Database::table('api');
            $statusNormal = (int) ApiManager::STATUS_NORMAL;
            $statusMaint = (int) ApiManager::STATUS_MAINTENANCE;
            $auditOk = (int) ApiManager::AUDIT_APPROVED;
            $roleDev = UserRole::ROLE_DEVELOPER;

            $sql = self::listSql($userTable, $apiTable, $statusNormal, $statusMaint, $auditOk);
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($roleDev));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $list = array();
            foreach ($rows as $row) {
                $item = self::formatCard($row);
                if ($item !== null) {
                    $list[] = $item;
                }
            }
            return $list;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * @param string $userTable
     * @param string $apiTable
     * @param int    $statusNormal
     * @param int    $statusMaint
     * @param int    $auditOk
     * @return string
     */
    private static function listSql($userTable, $apiTable, $statusNormal, $statusMaint, $auditOk)
    {
        $adminTable = Database::table('admin');
        // userid 直接挂用户；或历史 userid=0 且全站仅一个有效绑定身份且即为该用户
        $own = '(a.`userid` = u.`id` OR (a.`userid` = 0 AND EXISTS ('
            . 'SELECT 1 FROM `' . $adminTable . '` ad WHERE ad.`binduid` = u.`id` AND ad.`status` = 1 LIMIT 1'
            . ') AND (SELECT COUNT(DISTINCT ad2.`binduid`) FROM `' . $adminTable . '` ad2'
            . ' WHERE ad2.`binduid` IS NOT NULL AND ad2.`binduid` > 0 AND ad2.`status` = 1) = 1))';

        $sql = 'SELECT u.`id`, u.`username`, u.`email`, u.`avatar`, u.`bio`, u.`blog`, u.`wallpaper`,'
            . ' u.`role`, u.`createtime`,'
            . ' COUNT(a.`id`) AS apicount,'
            . ' COALESCE(SUM(a.`calls`), 0) AS callsum'
            . ' FROM `' . $userTable . '` u'
            . ' INNER JOIN `' . $apiTable . '` a ON ' . $own
            . ' AND a.`status` IN (' . (int) $statusNormal . ', ' . (int) $statusMaint . ')';
        if (ApiManager::hasAuditColumn()) {
            $sql .= ' AND a.`audit` = ' . (int) $auditOk;
        }
        $sql .= ' WHERE u.`status` = 1 AND u.`role` = ?'
            . ' GROUP BY u.`id`, u.`username`, u.`email`, u.`avatar`, u.`bio`, u.`blog`, u.`wallpaper`, u.`role`, u.`createtime`'
            . ' HAVING apicount > 0'
            . ' ORDER BY callsum DESC, apicount DESC, u.`id` DESC';
        return $sql;
    }

    /**
     * 公开个人主页（仅开发者且账号启用）
     *
     * @param int $userId
     * @return array|null
     */
    public static function findProfile($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        try {
            $pdo = Database::connect();
            $userTable = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id`, `username`, `email`, `avatar`, `bio`, `blog`, `wallpaper`, `role`, `createtime`, `status`'
                . ' FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($userId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['status'] !== 1) {
                return null;
            }
            if (UserRole::normalize(isset($row['role']) ? $row['role'] : '') !== UserRole::ROLE_DEVELOPER) {
                return null;
            }

            $apis = self::listApisForUser($userId);
            $apicount = count($apis);
            $callsum = 0;
            foreach ($apis as $api) {
                $callsum += isset($api['calls']) ? (int) $api['calls'] : 0;
            }

            $card = self::formatCard(array_merge($row, array(
                'apicount' => $apicount,
                'callsum'  => $callsum,
            )));
            if ($card === null) {
                return null;
            }
            $card['apis'] = $apis;
            return $card;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function listApisForUser($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return array();
        }

        try {
            $pdo = Database::connect();
            $apiTable = Database::table('api');
            $ownSql = AdminUserBinding::sqlApiOwnedByUser('a');
            $sql = 'SELECT a.* FROM `' . $apiTable . '` a WHERE ' . $ownSql
                . ' AND a.`status` IN (?, ?)';
            $params = array($userId, $userId, ApiManager::STATUS_NORMAL, ApiManager::STATUS_MAINTENANCE);
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND a.`audit` = ?';
                $params[] = ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY a.`id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }

        $list = array();
        foreach ($rows as $row) {
            $item = FrontendApi::formatForTheme($row);
            if ($item === null) {
                continue;
            }
            $endpoint = isset($item['endpoint']) ? (string) $item['endpoint'] : '';
            $item['domain'] = self::hostFromEndpoint($endpoint);
            $list[] = $item;
        }
        return $list;
    }

    /**
     * 解析个人主页背景：用户自定义优先，否则全站默认
     *
     * @param array $profile
     * @return string
     */
    public static function wallpaperUrl(array $profile)
    {
        $custom = isset($profile['wallpaper']) ? trim((string) $profile['wallpaper']) : '';
        if ($custom !== '') {
            return $custom;
        }
        $site = '';
        try {
            $site = trim((string) Config::get('profile_wallpaper', ''));
        } catch (Exception $e) {
            $site = '';
        }
        return $site;
    }

    /**
     * @param string $createtime
     * @return string 如 2024.03
     */
    public static function joinLabel($createtime)
    {
        $createtime = trim((string) $createtime);
        if ($createtime === '') {
            return '—';
        }
        $ts = strtotime($createtime);
        if ($ts === false) {
            return '—';
        }
        return date('Y.m', $ts);
    }

    /**
     * @param string $endpoint
     * @return string
     */
    public static function hostFromEndpoint($endpoint)
    {
        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $endpoint)) {
            $host = parse_url($endpoint, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : '';
        }
        $base = vs_base_url();
        $host = parse_url($base, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * @param array $row
     * @return array|null
     */
    private static function formatCard(array $row)
    {
        $id = (int) (isset($row['id']) ? $row['id'] : 0);
        $username = trim((string) (isset($row['username']) ? $row['username'] : ''));
        if ($id <= 0 || $username === '') {
            return null;
        }

        $bio = isset($row['bio']) ? trim((string) $row['bio']) : '';
        if ($bio === '') {
            $bio = '独立开发者 / 接口贡献者';
        }

        $letter = '';
        if (function_exists('mb_substr')) {
            $letter = mb_substr($username, 0, 1, 'UTF-8');
        } else {
            $letter = substr($username, 0, 1);
        }

        return array(
            'id'           => $id,
            'username'     => $username,
            'avatar'       => UserAvatar::resolve($row),
            'letter'       => $letter !== '' ? $letter : 'U',
            'bio'          => $bio,
            'blog'         => isset($row['blog']) ? trim((string) $row['blog']) : '',
            'wallpaper'    => isset($row['wallpaper']) ? trim((string) $row['wallpaper']) : '',
            'apicount'     => (int) (isset($row['apicount']) ? $row['apicount'] : 0),
            'calls'        => (int) (isset($row['callsum']) ? $row['callsum'] : 0),
            'calls_label'  => number_format((int) (isset($row['callsum']) ? $row['callsum'] : 0)),
            'join_label'   => self::joinLabel(isset($row['createtime']) ? $row['createtime'] : ''),
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'profile_url'  => vs_profile_url($id),
            'role_label'   => UserRole::label(isset($row['role']) ? $row['role'] : UserRole::ROLE_DEVELOPER),
        );
    }
}
