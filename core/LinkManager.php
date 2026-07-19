<?php
/**
 * 文件：core/LinkManager.php
 * 作用：友情链接 / 合作伙伴共用管理（表 link；kind 区分）
 */

class LinkManager
{
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    const KIND_FRIEND = 0;
    const KIND_PARTNER = 1;

    const ENABLED_OFF = 0;
    const ENABLED_ON = 1;

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
     * @param mixed $kind
     * @return int
     */
    public static function normalizeKind($kind)
    {
        return ((int) $kind === self::KIND_PARTNER) ? self::KIND_PARTNER : self::KIND_FRIEND;
    }

    /**
     * @param mixed $enabled
     * @return int
     */
    public static function normalizeEnabled($enabled)
    {
        return ((int) $enabled === self::ENABLED_OFF) ? self::ENABLED_OFF : self::ENABLED_ON;
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
     * @param mixed $enabled
     * @return string
     */
    public static function enabledLabel($enabled)
    {
        return self::normalizeEnabled($enabled) === self::ENABLED_ON ? '启用' : '禁用';
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
        $kind = self::normalizeKind(isset($row['kind']) ? $row['kind'] : self::KIND_FRIEND);
        $enabled = self::normalizeEnabled(isset($row['enabled']) ? $row['enabled'] : self::ENABLED_ON);
        $icon = isset($row['icon']) ? trim((string) $row['icon']) : '';
        $siteurl = isset($row['siteurl']) ? trim((string) $row['siteurl']) : '';

        return array(
            'id'           => $id,
            'name'         => isset($row['name']) ? trim((string) $row['name']) : '',
            'siteurl'      => $siteurl,
            'icon'         => $icon,
            'icon_url'     => self::normalizeIcon($icon),
            'description'  => isset($row['description']) ? trim((string) $row['description']) : '',
            'contact'      => isset($row['contact']) ? trim((string) $row['contact']) : '',
            'kind'         => $kind,
            'enabled'      => $enabled,
            'enabled_label'=> self::enabledLabel($enabled),
            'status'       => $status,
            'status_label' => self::statusLabel($status),
            'sort'         => isset($row['sort']) ? (int) $row['sort'] : 0,
            'createtime'   => isset($row['createtime']) ? (string) $row['createtime'] : '',
            'updatetime'   => isset($row['updatetime']) ? (string) $row['updatetime'] : '',
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
     * @param int|null $status null=全部（审核态）
     * @param int|null $kind   null=全部类型
     * @return array<int, array>
     */
    public static function listAll($status = null, $kind = null)
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . self::table() . '` WHERE 1=1';
            $params = array();
            if ($kind !== null) {
                $sql .= ' AND `kind` = ?';
                $params[] = self::normalizeKind($kind);
            }
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
     * 前台友链：已通过且启用
     *
     * @return array<int, array>
     */
    public static function listApproved()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . self::table() . '`
                WHERE `kind` = ? AND `status` = ? AND `enabled` = ?
                ORDER BY `sort` ASC, `id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(self::KIND_FRIEND, self::STATUS_APPROVED, self::ENABLED_ON));
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
     * 前台合作伙伴：启用
     *
     * @return array<int, array>
     */
    public static function listPartnersEnabled()
    {
        if (!self::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . self::table() . '`
                WHERE `kind` = ? AND `enabled` = ?
                ORDER BY `sort` ASC, `id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(self::KIND_PARTNER, self::ENABLED_ON));
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
     * @param string $siteurl
     * @param int    $kind
     * @param int    $excludeId
     * @return bool
     */
    public static function urlExists($siteurl, $kind = self::KIND_FRIEND, $excludeId = 0)
    {
        $siteurl = self::normalizeUrl($siteurl);
        $kind = self::normalizeKind($kind);
        $excludeId = (int) $excludeId;
        if ($siteurl === '' || !self::tableReady()) {
            return false;
        }
        try {
            $pdo = Database::connect();
            if ($kind === self::KIND_PARTNER) {
                $sql = 'SELECT `id` FROM `' . self::table() . '` WHERE `siteurl` = ? AND `kind` = ?';
                $params = array($siteurl, $kind);
            } else {
                $sql = 'SELECT `id` FROM `' . self::table() . '`
                    WHERE `siteurl` = ? AND `kind` = ? AND `status` IN (?, ?)';
                $params = array($siteurl, $kind, self::STATUS_PENDING, self::STATUS_APPROVED);
            }
            if ($excludeId > 0) {
                $sql .= ' AND `id` <> ?';
                $params[] = $excludeId;
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param array $data
     * @param int   $defaultStatus
     * @return array|string
     */
    public static function create(array $data, $defaultStatus = self::STATUS_PENDING)
    {
        if (!self::tableReady()) {
            return '功能尚未就绪，请先执行数据库结构更新';
        }

        $kind = self::normalizeKind(isset($data['kind']) ? $data['kind'] : self::KIND_FRIEND);
        $name = trim((string) (isset($data['name']) ? $data['name'] : ''));
        $siteurl = self::normalizeUrl(isset($data['siteurl']) ? $data['siteurl'] : '');
        $icon = trim((string) (isset($data['icon']) ? $data['icon'] : ''));
        $description = ($kind === self::KIND_PARTNER)
            ? ''
            : trim((string) (isset($data['description']) ? $data['description'] : ''));
        $contact = ($kind === self::KIND_PARTNER)
            ? ''
            : trim((string) (isset($data['contact']) ? $data['contact'] : ''));
        $sort = isset($data['sort']) ? (int) $data['sort'] : 0;
        $enabled = self::normalizeEnabled(
            isset($data['enabled']) ? $data['enabled'] : self::ENABLED_ON
        );

        if ($kind === self::KIND_PARTNER) {
            $status = self::STATUS_APPROVED;
        } else {
            $status = self::normalizeStatus(
                isset($data['status']) ? $data['status'] : $defaultStatus
            );
        }

        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            return '请填写名称（不超过 50 字）';
        }
        if ($siteurl === '') {
            return '请填写有效的跳转链接';
        }
        if (mb_strlen($description, 'UTF-8') > 200) {
            return '简介不超过 200 字';
        }
        if (mb_strlen($contact, 'UTF-8') > 100) {
            return '联系方式不超过 100 字';
        }
        if ($icon !== '' && self::normalizeIcon($icon) === '' && !preg_match('#^https?://#i', $icon)) {
            return '图标链接格式无效';
        }
        if (self::urlExists($siteurl, $kind)) {
            return $kind === self::KIND_PARTNER
                ? '该跳转链接已存在'
                : '该链接已申请或已通过，请勿重复提交';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                (`name`, `siteurl`, `icon`, `description`, `contact`, `kind`, `enabled`, `status`, `sort`, `createtime`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute(array(
                $name, $siteurl, $icon, $description, $contact,
                $kind, $enabled, $status, $sort,
            ));
            $id = (int) $pdo->lastInsertId();
            self::invalidateCache();
            $row = self::findById($id);
            return is_array($row) ? self::formatRow($row) : '保存失败';
        } catch (Exception $e) {
            return '保存失败，请稍后重试';
        }
    }

    /**
     * 前台申请友链（固定待审）
     *
     * @param array $data
     * @return array|string
     */
    public static function apply(array $data)
    {
        $data['kind'] = self::KIND_FRIEND;
        $data['status'] = self::STATUS_PENDING;
        $data['enabled'] = self::ENABLED_ON;
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
            return '记录不存在';
        }

        $kind = self::normalizeKind(isset($existing['kind']) ? $existing['kind'] : self::KIND_FRIEND);
        $name = trim((string) (isset($data['name']) ? $data['name'] : $existing['name']));
        $siteurl = self::normalizeUrl(isset($data['siteurl']) ? $data['siteurl'] : $existing['siteurl']);
        $icon = array_key_exists('icon', $data)
            ? trim((string) $data['icon'])
            : (isset($existing['icon']) ? (string) $existing['icon'] : '');
        $sort = isset($data['sort']) ? (int) $data['sort'] : (int) (isset($existing['sort']) ? $existing['sort'] : 0);
        $enabled = isset($data['enabled'])
            ? self::normalizeEnabled($data['enabled'])
            : self::normalizeEnabled(isset($existing['enabled']) ? $existing['enabled'] : self::ENABLED_ON);

        if ($kind === self::KIND_PARTNER) {
            $description = '';
            $contact = '';
            $status = self::STATUS_APPROVED;
        } else {
            $description = array_key_exists('description', $data)
                ? trim((string) $data['description'])
                : (isset($existing['description']) ? (string) $existing['description'] : '');
            $contact = array_key_exists('contact', $data)
                ? trim((string) $data['contact'])
                : (isset($existing['contact']) ? (string) $existing['contact'] : '');
            $status = isset($data['status'])
                ? self::normalizeStatus($data['status'])
                : self::normalizeStatus(isset($existing['status']) ? $existing['status'] : self::STATUS_PENDING);
        }

        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            return '请填写名称（不超过 50 字）';
        }
        if ($siteurl === '') {
            return '请填写有效的跳转链接';
        }
        if (self::urlExists($siteurl, $kind, $id)) {
            return '该跳转链接已存在于其它记录中';
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET
                `name` = ?, `siteurl` = ?, `icon` = ?, `description` = ?, `contact` = ?,
                `enabled` = ?, `status` = ?, `sort` = ?, `updatetime` = NOW()
                WHERE `id` = ? AND `kind` = ? LIMIT 1'
            );
            $stmt->execute(array(
                $name, $siteurl, $icon, $description, $contact,
                $enabled, $status, $sort, $id, $kind,
            ));
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
        $row = self::findById($id);
        if (!is_array($row)) {
            return '友链不存在';
        }
        if (self::normalizeKind(isset($row['kind']) ? $row['kind'] : 0) !== self::KIND_FRIEND) {
            return '合作伙伴无需审核';
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
     * 启用 / 禁用（不改变审核状态）
     *
     * @param int $id
     * @param int $enabled
     * @return true|string
     */
    public static function setEnabled($id, $enabled)
    {
        $id = (int) $id;
        if (!is_array(self::findById($id))) {
            return '记录不存在';
        }
        $enabled = self::normalizeEnabled($enabled);
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `enabled` = ?, `updatetime` = NOW() WHERE `id` = ? LIMIT 1'
            );
            $stmt->execute(array($enabled, $id));
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
            return '记录不存在';
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
            RedisCache::forget(RedisCache::KEY_FRONTEND_PARTNER);
        }
    }
}
