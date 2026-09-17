<?php
/**
 * 文件：core/CardKeyManager.php
 * 作用：积分卡密生成、列表、兑换（事务 + 条件更新防并发双兑）
 */

class CardKeyManager
{
    const STATUS_UNUSED = 1;
    const STATUS_USED = 2;
    const STATUS_VOID = 3;
    /** 已由对接 API 从库存取出（待用户兑换；不可再次 take） */
    const STATUS_ISSUED = 4;

    const CODE_LEN = 20;
    const MAX_GENERATE = 100;
    const MAX_POINTS = 1000000;
    const REDEEM_FAIL_MSG = '卡密无效或已使用';

    /**
     * 可兑换 / 可作废的库存态（未兑）
     *
     * @param int $status
     * @return bool
     */
    public static function isRedeemableStatus($status)
    {
        $status = (int) $status;
        return $status === self::STATUS_UNUSED || $status === self::STATUS_ISSUED;
    }

    /**
     * @return string
     */
    public static function table()
    {
        return Database::table('cardkey');
    }

    /**
     * @return bool
     */
    public static function tableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::table()));
            $ready = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $ready = false;
        }
        return $ready;
    }

    /**
     * @param int $status
     * @return string
     */
    public static function statusLabel($status)
    {
        $map = array(
            self::STATUS_UNUSED => '未使用',
            self::STATUS_USED   => '已使用',
            self::STATUS_VOID   => '已作废',
            self::STATUS_ISSUED => '已发放',
        );
        $status = (int) $status;
        return isset($map[$status]) ? $map[$status] : '未知';
    }

    /**
     * @param string $code
     * @return bool
     */
    public static function isValidCodeFormat($code)
    {
        return (bool) preg_match('/^[A-Za-z0-9]{' . self::CODE_LEN . '}$/', (string) $code);
    }

    /**
     * @param array $row
     * @param array $userMap id => username
     * @return array|null
     */
    public static function formatRow($row, array $userMap = array())
    {
        if (!is_array($row)) {
            return null;
        }
        $status = (int) $row['status'];
        $userId = (int) $row['userid'];
        $username = '';
        if ($userId > 0) {
            $username = isset($userMap[$userId]) ? (string) $userMap[$userId] : '';
        }
        return array(
            'id'          => (int) $row['id'],
            'code'        => (string) $row['code'],
            'points'      => (int) $row['points'],
            'status'      => $status,
            'status_label'=> self::statusLabel($status),
            'userid'      => $userId,
            'username'    => $username,
            'adminid'     => (int) $row['adminid'],
            'createtime'  => (string) $row['createtime'],
            'usetime'     => isset($row['usetime']) && $row['usetime'] !== null && $row['usetime'] !== ''
                ? (string) $row['usetime'] : '',
        );
    }

    /**
     * 生成卡密
     *
     * @param int $count
     * @param int $points
     * @param int $adminId
     * @return array{ok:bool,msg:string,list?:array,count?:int}
     */
    public static function generate($count, $points, $adminId)
    {
        $count = (int) $count;
        $points = (int) $points;
        $adminId = (int) $adminId;
        if ($count < 1 || $count > self::MAX_GENERATE) {
            return array('ok' => false, 'msg' => '生成数量须为 1～' . self::MAX_GENERATE);
        }
        if ($points < 1 || $points > self::MAX_POINTS) {
            return array('ok' => false, 'msg' => '积分须为 1～' . self::MAX_POINTS);
        }
        if ($adminId < 0) {
            return array('ok' => false, 'msg' => '管理员无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }

        try {
            $pdo = Database::connect();
            $codes = array();
            $attempts = 0;
            $maxAttempts = $count * 20 + 50;
            while (count($codes) < $count && $attempts < $maxAttempts) {
                $attempts++;
                $code = self::randomCode();
                if (isset($codes[$code])) {
                    continue;
                }
                $codes[$code] = true;
            }
            if (count($codes) < $count) {
                return array('ok' => false, 'msg' => '卡密生成失败，请重试');
            }

            $list = array();
            $ins = $pdo->prepare(
                'INSERT INTO `' . self::table() . '`
                 (`code`, `points`, `status`, `userid`, `adminid`, `createtime`)
                 VALUES (?, ?, ?, 0, ?, NOW())'
            );
            $pdo->beginTransaction();
            try {
                foreach (array_keys($codes) as $code) {
                    $inserted = false;
                    $finalCode = $code;
                    try {
                        $ins->execute(array($code, $points, self::STATUS_UNUSED, $adminId));
                        $inserted = true;
                    } catch (Exception $e) {
                        // uk_code 冲突：换码重试
                        $retry = 0;
                        while ($retry < 5 && !$inserted) {
                            $retry++;
                            $finalCode = self::randomCode();
                            try {
                                $ins->execute(array($finalCode, $points, self::STATUS_UNUSED, $adminId));
                                $inserted = true;
                            } catch (Exception $e2) {
                                // continue
                            }
                        }
                    }
                    if (!$inserted) {
                        $pdo->rollBack();
                        return array('ok' => false, 'msg' => '卡密写入失败，请重试');
                    }
                    $id = (int) $pdo->lastInsertId();
                    $list[] = array(
                        'id'           => $id,
                        'code'         => $finalCode,
                        'points'       => $points,
                        'status'       => self::STATUS_UNUSED,
                        'status_label' => self::statusLabel(self::STATUS_UNUSED),
                        'userid'       => 0,
                        'username'     => '',
                        'adminid'      => $adminId,
                        'createtime'   => date('Y-m-d H:i:s'),
                        'usetime'      => '',
                    );
                }
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return array('ok' => false, 'msg' => '生成失败');
            }

            return array(
                'ok'    => true,
                'msg'   => '已生成 ' . count($list) . ' 张卡密',
                'list'  => $list,
                'count' => count($list),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '生成失败');
        }
    }

    /**
     * @return array{unused:int,used:int,voided:int,unused_points:int}
     */
    public static function stats()
    {
        $empty = array(
            'unused'        => 0,
            'used'          => 0,
            'voided'        => 0,
            'issued'        => 0,
            'unused_points' => 0,
            'issued_points' => 0,
        );
        if (!self::tableReady()) {
            return $empty;
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT `status`, COUNT(*) AS c, COALESCE(SUM(`points`),0) AS p
                 FROM `' . self::table() . '` GROUP BY `status`'
            );
            $out = $empty;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $st = (int) $row['status'];
                $c = (int) $row['c'];
                $p = (int) $row['p'];
                if ($st === self::STATUS_UNUSED) {
                    $out['unused'] = $c;
                    $out['unused_points'] = $p;
                } elseif ($st === self::STATUS_USED) {
                    $out['used'] = $c;
                } elseif ($st === self::STATUS_VOID) {
                    $out['voided'] = $c;
                } elseif ($st === self::STATUS_ISSUED) {
                    $out['issued'] = $c;
                    $out['issued_points'] = $p;
                }
            }
            return $out;
        } catch (Exception $e) {
            return $empty;
        }
    }

    /**
     * 库存查询（仅未使用、可 take 的卡密；不含已使用/已作废/已发放）
     *
     * @param int $points 0=全部面额；>0 只统计该积分
     * @return array{ok:bool,msg:string,unused?:int,unused_points?:int,by_points?:array<int,int>}
     */
    public static function stock($points = 0)
    {
        $points = (int) $points;
        if ($points < 0 || $points > self::MAX_POINTS) {
            return array('ok' => false, 'msg' => '积分参数无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }
        try {
            $pdo = Database::connect();
            $byPoints = array();
            $unused = 0;
            $unusedPoints = 0;
            if ($points > 0) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS c, COALESCE(SUM(`points`),0) AS p
                     FROM `' . self::table() . '`
                     WHERE `status` = ? AND `points` = ?'
                );
                $stmt->execute(array(self::STATUS_UNUSED, $points));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $unused = $row ? (int) $row['c'] : 0;
                $unusedPoints = $row ? (int) $row['p'] : 0;
                if ($unused > 0) {
                    $byPoints[(string) $points] = $unused;
                }
            } else {
                $stmt = $pdo->prepare(
                    'SELECT `points`, COUNT(*) AS c, COALESCE(SUM(`points`),0) AS p
                     FROM `' . self::table() . '`
                     WHERE `status` = ?
                     GROUP BY `points`
                     ORDER BY `points` ASC'
                );
                $stmt->execute(array(self::STATUS_UNUSED));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $pt = (int) $row['points'];
                    $c = (int) $row['c'];
                    $byPoints[(string) $pt] = $c;
                    $unused += $c;
                    $unusedPoints += (int) $row['p'];
                }
            }
            return array(
                'ok'            => true,
                'msg'           => 'ok',
                'unused'        => $unused,
                'unused_points' => $unusedPoints,
                'by_points'     => $byPoints,
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '库存查询失败');
        }
    }

    /**
     * 从库存出库（随机取未使用卡密，标为已发放，防同一张被 take 两次）
     *
     * @param int $count
     * @param int $points 0=任意面额随机；>0=只取该积分
     * @return array{ok:bool,msg:string,list?:array,count?:int,points?:int}
     */
    public static function takeFromStock($count, $points = 0)
    {
        $count = (int) $count;
        $points = (int) $points;
        if ($count < 1 || $count > self::MAX_GENERATE) {
            return array('ok' => false, 'msg' => '取出数量须为 1～' . self::MAX_GENERATE);
        }
        if ($points < 0 || $points > self::MAX_POINTS) {
            return array('ok' => false, 'msg' => '积分参数无效');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }
        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $sql = 'SELECT `id`, `code`, `points`, `adminid`, `createtime`
                    FROM `' . self::table() . '`
                    WHERE `status` = ?';
            $bind = array(self::STATUS_UNUSED);
            if ($points > 0) {
                $sql .= ' AND `points` = ?';
                $bind[] = $points;
            }
            $sql .= ' ORDER BY RAND() LIMIT ' . (int) $count . ' FOR UPDATE';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) < $count) {
                $pdo->rollBack();
                $have = count($rows);
                if ($points > 0) {
                    return array(
                        'ok'  => false,
                        'msg' => '库存不足：积分 ' . $points . ' 的未使用卡密仅 ' . $have . ' 张，需要 ' . $count . ' 张',
                    );
                }
                return array(
                    'ok'  => false,
                    'msg' => '库存不足：未使用卡密仅 ' . $have . ' 张，需要 ' . $count . ' 张',
                );
            }

            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int) $row['id'];
            }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $updBind = $ids;
            array_unshift($updBind, self::STATUS_ISSUED);
            $updBind[] = self::STATUS_UNUSED;
            $upd = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?
                 WHERE `id` IN (' . $ph . ') AND `status` = ?'
            );
            $upd->execute($updBind);
            if ((int) $upd->rowCount() !== count($ids)) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => '出库冲突，请重试');
            }

            $list = array();
            foreach ($rows as $row) {
                $list[] = array(
                    'id'           => (int) $row['id'],
                    'code'         => (string) $row['code'],
                    'points'       => (int) $row['points'],
                    'status'       => self::STATUS_ISSUED,
                    'status_label' => self::statusLabel(self::STATUS_ISSUED),
                    'userid'       => 0,
                    'username'     => '',
                    'adminid'      => (int) $row['adminid'],
                    'createtime'   => (string) $row['createtime'],
                    'usetime'      => '',
                );
            }
            $pdo->commit();
            return array(
                'ok'    => true,
                'msg'   => '已从库存取出 ' . count($list) . ' 张卡密',
                'list'  => $list,
                'count' => count($list),
            );
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
            }
            return array('ok' => false, 'msg' => '出库失败');
        }
    }

    /**
     * 列表（keyset）
     *
     * @param array $opts status|all|unused|used|void|issued, q, before_id, pagesize, page
     * @return array
     */
    public static function listPaged(array $opts)
    {
        $pagesize = isset($opts['pagesize']) ? (int) $opts['pagesize'] : 20;
        if ($pagesize < 10) {
            $pagesize = 10;
        }
        if ($pagesize > 50) {
            $pagesize = 50;
        }
        $page = isset($opts['page']) ? (int) $opts['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        $beforeId = isset($opts['before_id']) ? (int) $opts['before_id'] : 0;
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $bucket = isset($opts['status']) ? trim((string) $opts['status']) : 'all';

        $empty = array(
            'list'           => array(),
            'total'          => 0,
            'page'           => $page,
            'pagesize'       => $pagesize,
            'before_id'      => $beforeId,
            'next_before_id' => 0,
            'has_more'       => false,
        );
        if (!self::tableReady()) {
            return $empty;
        }

        $where = array('1=1');
        $bind = array();
        if ($bucket === 'unused') {
            $where[] = 'c.`status` = ?';
            $bind[] = self::STATUS_UNUSED;
        } elseif ($bucket === 'used') {
            $where[] = 'c.`status` = ?';
            $bind[] = self::STATUS_USED;
        } elseif ($bucket === 'void') {
            $where[] = 'c.`status` = ?';
            $bind[] = self::STATUS_VOID;
        } elseif ($bucket === 'issued') {
            $where[] = 'c.`status` = ?';
            $bind[] = self::STATUS_ISSUED;
        }

        $search = self::buildSearchFilter($q);
        if ($search['sql'] !== '') {
            $where[] = $search['sql'];
            foreach ($search['bind'] as $b) {
                $bind[] = $b;
            }
        }

        if ($beforeId > 0) {
            $where[] = 'c.`id` < ?';
            $bind[] = $beforeId;
        }

        $whereSql = implode(' AND ', $where);
        try {
            $pdo = Database::connect();
            $total = 0;
            // 总数不含 keyset 游标，避免第 2 页起 total 被 before_id 削短
            $countWhere = array('1=1');
            $countBind = array();
            if ($bucket === 'unused') {
                $countWhere[] = 'c.`status` = ?';
                $countBind[] = self::STATUS_UNUSED;
            } elseif ($bucket === 'used') {
                $countWhere[] = 'c.`status` = ?';
                $countBind[] = self::STATUS_USED;
            } elseif ($bucket === 'void') {
                $countWhere[] = 'c.`status` = ?';
                $countBind[] = self::STATUS_VOID;
            } elseif ($bucket === 'issued') {
                $countWhere[] = 'c.`status` = ?';
                $countBind[] = self::STATUS_ISSUED;
            }
            if ($search['sql'] !== '') {
                $countWhere[] = $search['sql'];
                foreach ($search['bind'] as $b) {
                    $countBind[] = $b;
                }
            }
            try {
                $cntStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM `' . self::table() . '` c WHERE ' . implode(' AND ', $countWhere)
                );
                $cntStmt->execute($countBind);
                $total = (int) $cntStmt->fetchColumn();
            } catch (Exception $e) {
                $total = 0;
            }

            // 管理端页码跳转：无 before_id 时统一 OFFSET；有 before_id 仍走 keyset
            if ($beforeId <= 0) {
                $offset = ($page - 1) * $pagesize;
                if ($offset < 0) {
                    $offset = 0;
                }
                $sql = 'SELECT c.* FROM `' . self::table() . '` c
                        WHERE ' . implode(' AND ', $countWhere) . '
                        ORDER BY c.`id` DESC LIMIT ' . (int) $pagesize . ' OFFSET ' . (int) $offset;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($countBind);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $hasMore = ($offset + count($rows)) < $total;
                $nextBefore = 0;
                if ($rows !== array()) {
                    $last = $rows[count($rows) - 1];
                    $nextBefore = (int) $last['id'];
                }
            } else {
                $sql = 'SELECT c.* FROM `' . self::table() . '` c
                        WHERE ' . $whereSql . '
                        ORDER BY c.`id` DESC LIMIT ' . (int) ($pagesize + 1);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $hasMore = count($rows) > $pagesize;
                if ($hasMore) {
                    $rows = array_slice($rows, 0, $pagesize);
                }
                $nextBefore = 0;
                if ($rows !== array()) {
                    $last = $rows[count($rows) - 1];
                    $nextBefore = (int) $last['id'];
                }
            }

            $userIds = array();
            foreach ($rows as $row) {
                $uid = (int) $row['userid'];
                if ($uid > 0) {
                    $userIds[$uid] = true;
                }
            }
            $userMap = self::loadUsernames(array_keys($userIds));
            $list = array();
            foreach ($rows as $row) {
                $fmt = self::formatRow($row, $userMap);
                if ($fmt) {
                    $list[] = $fmt;
                }
            }

            return array(
                'list'           => $list,
                'total'          => $total,
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
     * 兑换卡密（单事务：锁卡密 → 条件更新 → 锁用户加积分写流水）
     *
     * @param int    $userId
     * @param string $code
     * @return array{ok:bool,msg:string,points?:int,balance?:float}
     */
    public static function redeem($userId, $code)
    {
        $userId = (int) $userId;
        $code = trim((string) $code);
        if ($userId <= 0) {
            return array('ok' => false, 'msg' => '请先登录');
        }
        if (!self::isValidCodeFormat($code)) {
            return array('ok' => false, 'msg' => self::REDEEM_FAIL_MSG);
        }
        if (!self::tableReady() || !OrderManager::tableReady() || !PointsManager::hasPointsColumn()) {
            return array('ok' => false, 'msg' => '积分系统未就绪');
        }

        if (class_exists('RateLimitStore')) {
            $bucket = 'cardkey:redeem:' . $userId;
            if (!RateLimitStore::allow($bucket, 60, 10, true)) {
                return array('ok' => false, 'msg' => '操作过于频繁，请稍后再试');
            }
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT * FROM `' . self::table() . '` WHERE `code` = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(array($code));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $curStatus = $row ? (int) $row['status'] : 0;
            if (!$row || !self::isRedeemableStatus($curStatus)) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => self::REDEEM_FAIL_MSG);
            }

            $cardId = (int) $row['id'];
            $points = (int) $row['points'];
            if ($points < 1) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => self::REDEEM_FAIL_MSG);
            }

            $upd = $pdo->prepare(
                'UPDATE `' . self::table() . '`
                 SET `status` = ?, `userid` = ?, `usetime` = NOW()
                 WHERE `id` = ? AND `status` IN (?, ?)'
            );
            $upd->execute(array(
                self::STATUS_USED,
                $userId,
                $cardId,
                self::STATUS_UNUSED,
                self::STATUS_ISSUED,
            ));
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => self::REDEEM_FAIL_MSG);
            }

            $uStmt = $pdo->prepare(
                'SELECT `points` FROM `' . Database::table('user') . '` WHERE `id` = ? LIMIT 1 FOR UPDATE'
            );
            $uStmt->execute(array($userId));
            $cur = $uStmt->fetchColumn();
            if ($cur === false) {
                $pdo->rollBack();
                return array('ok' => false, 'msg' => '用户不存在');
            }
            $amount = (float) $points;
            $newBal = round((float) $cur + $amount, 4);
            $pdo->prepare(
                'UPDATE `' . Database::table('user') . '` SET `points` = ? WHERE `id` = ?'
            )->execute(array($newBal, $userId));

            $orderno = OrderManager::genOrderNo('CK');
            $ins = $pdo->prepare(
                'INSERT INTO `' . OrderManager::table() . '` (
                    `orderno`, `userid`, `direct`, `kind`, `amount`, `balance`, `money`,
                    `apiid`, `keyid`, `paytype`, `tradeno`, `status`, `remark`, `createtime`, `paytime`
                ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, \'\', \'\', ?, ?, NOW(), NOW())'
            );
            $ins->execute(array(
                $orderno,
                $userId,
                OrderManager::DIRECT_INC,
                OrderManager::KIND_CARDKEY,
                $amount,
                $newBal,
                OrderManager::STATUS_DONE,
                '卡密兑换',
            ));

            $pdo->commit();
            if (class_exists('RedisCache')) {
                RedisCache::invalidateOrders();
            }
            return array(
                'ok'      => true,
                'msg'     => '兑换成功，获得 ' . $points . ' 积分',
                'points'  => $points,
                'balance' => $newBal,
            );
        } catch (Exception $e) {
            try {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $e2) {
            }
            return array('ok' => false, 'msg' => '兑换失败，请稍后重试');
        }
    }

    /**
     * 按卡密码查询（对接 API）
     * 格式非法与「不存在」对外统一模糊（防存在性预言）
     *
     * @param string $code
     * @return array{ok:bool,msg:string,card?:array,exists?:bool}
     */
    public static function findByCode($code)
    {
        $code = trim((string) $code);
        $blur = '卡密无效或不可用';
        if (!self::isValidCodeFormat($code)) {
            return array('ok' => false, 'msg' => $blur, 'exists' => false);
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                'SELECT `id`, `code`, `points`, `status`, `userid`, `adminid`, `createtime`, `usetime`
                 FROM `' . self::table() . '` WHERE `code` = ? LIMIT 1'
            );
            $stmt->execute(array($code));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return array('ok' => false, 'msg' => $blur, 'exists' => false);
            }
            $formatted = self::formatRow($row);
            return array(
                'ok'     => true,
                'msg'    => 'ok',
                'exists' => true,
                'card'   => array(
                    'code'          => $formatted['code'],
                    'points'        => $formatted['points'],
                    'status'        => $formatted['status'],
                    'status_label'  => $formatted['status_label'],
                    'createtime'    => $formatted['createtime'],
                    'usetime'       => $formatted['usetime'],
                    'used'          => ((int) $formatted['status'] === self::STATUS_USED),
                    'voided'        => ((int) $formatted['status'] === self::STATUS_VOID),
                    'issued'        => ((int) $formatted['status'] === self::STATUS_ISSUED),
                    'available'     => self::isRedeemableStatus($formatted['status']),
                ),
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '查询失败');
        }
    }

    /**
     * 按卡密码作废未使用卡密（对接 API：商城取消订单）
     *
     * @param string[] $codes
     * @return array{ok:bool,msg:string,count?:int,voided?:string[],skipped?:array}
     */
    public static function voidUnusedByCodes(array $codes)
    {
        $normalized = array();
        foreach ($codes as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            if (!self::isValidCodeFormat($c)) {
                continue;
            }
            $normalized[$c] = true;
        }
        $list = array_keys($normalized);
        if ($list === array()) {
            return array('ok' => false, 'msg' => '请提供有效卡密');
        }
        if (count($list) > 200) {
            return array('ok' => false, 'msg' => '单次最多作废 200 张');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($list), '?'));
            $sel = $pdo->prepare(
                'SELECT `code`, `status` FROM `' . self::table() . '` WHERE `code` IN (' . $ph . ')'
            );
            $sel->execute($list);
            $found = array();
            while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                $found[(string) $row['code']] = (int) $row['status'];
            }

            $toVoid = array();
            $skipped = array();
            foreach ($list as $code) {
                if (!isset($found[$code])) {
                    $skipped[] = array('code' => $code, 'reason' => '不存在');
                    continue;
                }
                $st = $found[$code];
                if (self::isRedeemableStatus($st)) {
                    $toVoid[] = $code;
                } elseif ($st === self::STATUS_USED) {
                    $skipped[] = array('code' => $code, 'reason' => '已使用');
                } elseif ($st === self::STATUS_VOID) {
                    $skipped[] = array('code' => $code, 'reason' => '已作废');
                } else {
                    $skipped[] = array('code' => $code, 'reason' => '状态不可作废');
                }
            }

            $n = 0;
            $voided = array();
            if ($toVoid !== array()) {
                $ph2 = implode(',', array_fill(0, count($toVoid), '?'));
                $bind = array(self::STATUS_VOID);
                foreach ($toVoid as $c) {
                    $bind[] = $c;
                }
                $bind[] = self::STATUS_UNUSED;
                $bind[] = self::STATUS_ISSUED;
                $upd = $pdo->prepare(
                    'UPDATE `' . self::table() . '` SET `status` = ?
                     WHERE `code` IN (' . $ph2 . ') AND `status` IN (?, ?)'
                );
                $upd->execute($bind);

                // 回查真实状态：voided 只含确认已作废的码（防并发兑换后对商城撒谎）
                $chk = $pdo->prepare(
                    'SELECT `code`, `status` FROM `' . self::table() . '` WHERE `code` IN (' . $ph2 . ')'
                );
                $chk->execute($toVoid);
                $after = array();
                while ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
                    $after[(string) $row['code']] = (int) $row['status'];
                }
                foreach ($toVoid as $code) {
                    $st = isset($after[$code]) ? $after[$code] : null;
                    if ($st === self::STATUS_VOID) {
                        $voided[] = $code;
                    } elseif ($st === self::STATUS_USED) {
                        $skipped[] = array('code' => $code, 'reason' => '已使用');
                    } elseif (self::isRedeemableStatus($st)) {
                        $skipped[] = array('code' => $code, 'reason' => '并发未作废');
                    } else {
                        $skipped[] = array('code' => $code, 'reason' => '并发变更');
                    }
                }
                $n = count($voided);
            }

            return array(
                'ok'      => true,
                'msg'     => $n > 0 ? ('已作废 ' . $n . ' 张卡密') : '没有可作废的卡密',
                'count'   => $n,
                'voided'  => $voided,
                'skipped' => $skipped,
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '作废失败');
        }
    }

    /**
     * 作废未使用卡密
     *
     * @param int[] $ids
     * @return array{ok:bool,msg:string,count?:int}
     */
    public static function voidUnused(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === array()) {
            return array('ok' => false, 'msg' => '请选择卡密');
        }
        if (count($ids) > 200) {
            return array('ok' => false, 'msg' => '单次最多作废 200 条');
        }
        if (!self::tableReady()) {
            return array('ok' => false, 'msg' => '卡密表未就绪');
        }
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $bind = array(self::STATUS_VOID);
            foreach ($ids as $id) {
                $bind[] = $id;
            }
            $bind[] = self::STATUS_UNUSED;
            $bind[] = self::STATUS_ISSUED;
            $stmt = $pdo->prepare(
                'UPDATE `' . self::table() . '` SET `status` = ?
                 WHERE `id` IN (' . $ph . ') AND `status` IN (?, ?)'
            );
            $stmt->execute($bind);
            $n = (int) $stmt->rowCount();
            return array(
                'ok'    => true,
                'msg'   => $n > 0 ? ('已作废 ' . $n . ' 张卡密') : '没有可作废的卡密',
                'count' => $n,
            );
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '作废失败');
        }
    }

    /**
     * @return string
     */
    private static function randomCode()
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $len = 62;
        // 256 非 62 倍数：拒绝 >= 248 的字节，避免 ord%62 偏置
        $limit = 248;
        $out = '';
        while (strlen($out) < self::CODE_LEN) {
            $need = self::CODE_LEN - strlen($out);
            $bytes = random_bytes($need + 8);
            $n = strlen($bytes);
            for ($i = 0; $i < $n && strlen($out) < self::CODE_LEN; $i++) {
                $b = ord($bytes[$i]);
                if ($b >= $limit) {
                    continue;
                }
                $out .= $alphabet[$b % $len];
            }
        }
        return $out;
    }

    /**
     * @param string $q
     * @return array{sql:string,bind:array}
     */
    private static function buildSearchFilter($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array('sql' => '', 'bind' => array());
        }

        $or = array();
        $bind = array();

        // 完整卡密或前缀（走 uk_code / 前缀），禁止 '%x%'
        if (preg_match('/^[A-Za-z0-9]{4,20}$/', $q)) {
            if (strlen($q) === self::CODE_LEN) {
                $or[] = 'c.`code` = ?';
                $bind[] = $q;
            } else {
                $or[] = 'c.`code` LIKE ?';
                $bind[] = $q . '%';
            }
        }

        if (ctype_digit($q)) {
            $or[] = 'c.`userid` = ?';
            $bind[] = (int) $q;
            $or[] = 'c.`id` = ?';
            $bind[] = (int) $q;
        } else {
            $userIds = self::resolveSearchUserIds($q);
            if ($userIds !== array()) {
                $ph = implode(',', array_fill(0, count($userIds), '?'));
                $or[] = 'c.`userid` IN (' . $ph . ')';
                foreach ($userIds as $uid) {
                    $bind[] = (int) $uid;
                }
            }
        }

        if ($or === array()) {
            return array('sql' => '0=1', 'bind' => array());
        }
        return array(
            'sql'  => '(' . implode(' OR ', $or) . ')',
            'bind' => $bind,
        );
    }

    /**
     * @param string $q
     * @return int[]
     */
    private static function resolveSearchUserIds($q)
    {
        $q = trim((string) $q);
        if ($q === '' || ctype_digit($q)) {
            return array();
        }
        $ids = array();
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->prepare(
                'SELECT `id` FROM `' . $table . '` WHERE `username` = ? OR `email` = ? LIMIT 30'
            );
            $stmt->execute(array($q, $q));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int) $id;
            }
            if ($ids === array()) {
                $prefix = $q . '%';
                $stmt = $pdo->prepare(
                    'SELECT `id` FROM `' . $table . '` WHERE `username` LIKE ? OR `email` LIKE ? ORDER BY `id` DESC LIMIT 50'
                );
                $stmt->execute(array($prefix, $prefix));
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                    $ids[] = (int) $id;
                }
            }
        } catch (Exception $e) {
            return array();
        }
        return $ids;
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function loadUsernames(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === array()) {
            return array();
        }
        $map = array();
        try {
            $pdo = Database::connect();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                'SELECT `id`, `username` FROM `' . Database::table('user') . '` WHERE `id` IN (' . $ph . ')'
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id']] = (string) $row['username'];
            }
        } catch (Exception $e) {
            return array();
        }
        return $map;
    }
}
