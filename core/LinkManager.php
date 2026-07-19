<?php
/**
 * 文件：core/LinkManager.php
 * 作用：友情链接管理（后台 CRUD / 审核；前台申请入口）
 */

class LinkManager
{
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('link');
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
     * @param string $url
     * @return string
     */
    public static function normalizeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        return $url;
    }

    /**
     * @param string $icon
     * @return string
     */
    public static function normalizeIcon($icon)
    {
        $icon = trim((string) $icon);
        if ($icon === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $icon)) {
            return $icon;
        }
        if (isset($icon[0]) && $icon[0] === '/') {
            return rtrim(vs_base_url(), '/') . $icon;
        }
        return '';
    }

    /**
     * @param array $row
     * @return array
     */
    public static function formatRow(array $row)
    {
        $id = (int) (isset($row['id']) ? $row['id'] : 0);
        $status = self::normalizeStatus(isset($row['status']) ? $row['status'] : self::STATUS_PENDING);
        $icon = isset($row['icon']) ? trim((string) $row['icon']) : '';
        $siteurl = isset($row['siteurl']) ? trim((string) $row['siteurl']) : '';

        return array(
            'id'          => $id,
            'name'        => isset($row['name']) ? trim((string) $row['name']) : '',
            'siteurl'     => $siteurl,
            'icon'        => $icon,
            'icon_url'    => self::normalizeIcon($icon),
            'description' => isset($row['description']) ? trim((string) $row['description']) : '',
            'contact'     => isset($row['contact']) ? trim((string) $row['contact']) : '',
            'status'      => $status,
            'status_label'=> self::statusLabel($status),
            'sort'        => isset($row['sort']) ? (int) $row['sort'] : 0,
            'createtime'  => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'  => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
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
     * @param int|null $status null=全部
     * @return array<int, array>
     */
    public static function listAll($status = null)
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . self::table() . '`';
            $params = array();
            if ($status !== null) {
                $sql .= ' WHERE `status` = ?';
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
     * 前台展示用：已通过
     *
     * @return array<int, array>
     */
    public static function listApproved()
    {
        return self::listAll(self::STATUS_APPROVED);
    }

    /**
     * @param string $siteurl
     * @return bool
     */
    public static function urlExists($siteurl)
    {
        $siteurl = self::normalizeUrl($siteurl);
        if ($siteurl === '' || !self::tableReady()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . self::table() . '` WHERE `siteurl` = ? AND `status` IN (?, ?) LIMIT 1'
            );
            $stmt->execute(array($siteurl, self::STATUS_PENDING, self::STATUS_APPROVED));
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param array $data
     * @param int   $defaultStatus
     * @return array|string 成功返回 formatRow，失败返回错误文案
     */
    public static function create(array $data, $defaultStatus = self::STATUS_PENDING)
    {
        if (!self::tableReady()) {
            return '友情链接功能尚未就绪，请先执行数据库结构更新';
        }

        $name = trim((string) (isset($data['name']) ? $data['name'] : ''));
        $siteurl = self::normalizeUrl(isset($data['siteurl']) ? $data['siteurl'] : '');
        $icon = trim((string) (isset($data['icon']) ? $data['icon'] : ''));
        $description = trim((string) (isset($data['description']) ? $data['description'] : ''));
        $contact = trim((string) (isset($data['contact']) ? $data['contact'] : ''));
        $sort = isset($data['sort']) ? (int) $data['sort'] : 0;
        $status = self::normalizeStatus(
            isset($data['status']) ? $data['status'] : $defaultStatus
        );

        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            return '请填写网站名称（不超过 50 字）';
        }
        if ($siteurl === '') {
            return '请填写有效的网站链接';
        }
        if (mb_strlen($description, 'UTF-8') > 200) {
            return '网站简介不超过 200 字';
        }
        if (mb_strlen($contact, 'UTF-8') > 100) {
            return '联系方式不超过 100 字';
        }
        if ($icon !== '' && self::normalizeIcon($icon) === '' && !preg_match('#^https?://#i', $icon)) {
            return '头像链接格式无效';
        }
        if (self::urlExists($siteurl)) {
            return '该链接已申请或已通过，请勿重复提交';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                (`name`, `siteurl`, `icon`, `description`, `contact`, `status`, `sort`, `createtime`)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute(array($name, $siteurl, $icon, $description, $contact, $status, $sort));
            $id = (int) $pdo->lastInsertId();
            self::invalidateCache();
            $row = self::findById($id);
            return is_array($row) ? self::formatRow($row) : '保存失败';
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * 前台申请（固定待审）
     *
     * @param array $data
     * @return array|string
     */
    public static function apply(array $data)
    {
        $data['status'] = self::STATUS_PENDING;
        return self::create($data, self::STATUS_PENDING);
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
            return '友链不存在';
        }

        $name = trim((string) (isset($data['name']) ? $data['name'] : $existing['name']));
        $siteurl = self::normalizeUrl(isset($data['siteurl']) ? $data['siteurl'] : $existing['siteurl']);
        $icon = array_key_exists('icon', $data)
            ? trim((string) $data['icon'])
            : (isset($existing['icon']) ? (string) $existing['icon'] : '');
        $description = array_key_exists('description', $data)
            ? trim((string) $data['description'])
            : (isset($existing['description']) ? (string) $existing['description'] : '');
        $contact = array_key_exists('contact', $data)
            ? trim((string) $data['contact'])
            : (isset($existing['contact']) ? (string) $existing['contact'] : '');
        $sort = isset($data['sort']) ? (int) $data['sort'] : (int) (isset($existing['sort']) ? $existing['sort'] : 0);
        $status = isset($data['status'])
            ? self::normalizeStatus($data['status'])
            : self::normalizeStatus(isset($existing['status']) ? $existing['status'] : self::STATUS_PENDING);

        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            return '请填写网站名称（不超过 50 字）';
        }
        if ($siteurl === '') {
            return '请填写有效的网站链接';
        }

        try {
            $pdo = Database::connect();
            $dup = $pdo->prepare(
                'SELECT `id` FROM `' . self::table() . '` WHERE `siteurl` = ? AND `id` <> ? AND `status` IN (?, ?) LIMIT 1'
            );
            $dup->execute(array($siteurl, $id, self::STATUS_PENDING, self::STATUS_APPROVED));
            if ($dup->fetchColumn()) {
                return '该链接已存在于其它记录中';
            }

            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET
                `name` = ?, `siteurl` = ?, `icon` = ?, `description` = ?, `contact` = ?,
                `status` = ?, `sort` = ?, `updatetime` = NOW()
                WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($name, $siteurl, $icon, $description, $contact, $status, $sort, $id));
            self::invalidateCache();
            return true;
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
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
            return '友链不存在';
        }
        $status = self::normalizeStatus($status);
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($status, $id));
            self::invalidateCache();
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
        if (!is_array(self::findById($id))) {
            return '友链不存在';
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('DELETE FROM `' . self::table() . '` WHERE `id` = ? LIMIT 1');
            $stmt->execute(array($id));
            self::invalidateCache();
            return true;
        } catch (Exception $e) {
            return '删除失败';
        }
    }

    /**
     * @return void
     */
    public static function invalidateCache()
    {
        if (class_exists('RedisCache')) {
            RedisCache::forget(RedisCache::KEY_FRONTEND_LINK);
        }
    }
}
