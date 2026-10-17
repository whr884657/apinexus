<?php
/**
 * 文件：core/DatabaseMigrator.php
 * 作用：版本更新时执行 install/migrations 下的增量 SQL（数据库结构更新）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 * 仅处理 ApiNexus 正式库表（admin / user / config / api / category / mailrate / apilog / apikey / orders）。
 */

class DatabaseMigrator
{
    const CONFIG_KEY = 'schema_migrations';

    /**
     * 结构更新脚本目录
     *
     * @return string
     */
    public static function migrationsDir()
    {
        return VS_ROOT . '/install/migrations';
    }

    /**
     * 规范化版本号（去 v 前缀、空白）
     *
     * @param mixed $version
     * @return string
     */
    public static function normalizeVersionToken($version)
    {
        $v = trim((string) $version);
        if ($v === '') {
            return '';
        }
        if (isset($v[0]) && ($v[0] === 'v' || $v[0] === 'V')) {
            $v = substr($v, 1);
        }
        return trim($v);
    }

    /**
     * 写入 schema_migrations（写后清缓存并校验）
     *
     * @param array $versions
     * @return void
     * @throws Exception
     */
    private static function writeAppliedVersions(array $versions)
    {
        $clean = array();
        foreach ($versions as $v) {
            $n = self::normalizeVersionToken($v);
            if ($n === '' || !preg_match('/^\d+\.\d+\.\d+$/', $n)) {
                continue;
            }
            if (!in_array($n, $clean, true)) {
                $clean[] = $n;
            }
        }
        usort($clean, 'version_compare');
        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('schema_migrations 序列化失败');
        }
        Config::set(self::CONFIG_KEY, $json);
        Config::clearCache();

        $verify = self::getAppliedVersions();
        if ($verify !== array_values($clean)) {
            // 二次直写兜底（避免缓存/键冲突导致页面仍见旧值）
            $pdo = Database::connect();
            $table = Database::table('config');
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '` (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            $stmt->execute(array(self::CONFIG_KEY, $json));
            Config::clearCache();
        }
    }

    /**
     * 执行尚未应用的结构更新
     *
     * @return array
     */
    public static function runPending($maxVersion = null)
    {
        if (!InstallChecker::isInstalled()) {
            return array('ok' => true, 'msg' => '系统未安装，跳过数据库结构更新', 'applied' => array());
        }

        if (defined('VS_VERSION')) {
            self::pruneAppliedAboveCodeVersion(VS_VERSION);
        }

        self::reconcileSchemaState();

        // 在线更新必须限制上限；未传时默认不超过当前代码版本（禁止一次跑光磁盘上的未来 .sql）
        if ($maxVersion === null || $maxVersion === '') {
            $maxVersion = defined('VS_VERSION') ? VS_VERSION : null;
        }

        $pending = self::getPendingFiles($maxVersion);
        if (count($pending) === 0) {
            return array('ok' => true, 'msg' => '数据库结构已是最新', 'applied' => array());
        }

        $applied = array();

        try {
            $pdo = Database::connect();
            $prefix = Database::prefix();

            foreach ($pending as $version => $file) {
                // 新装最终结构已满足时跳过会改旧列名的脚本（E201：require_key → needkey）
                if (self::isMigrationObsolete($version)) {
                    self::markApplied($version);
                    $applied[] = $version;
                    continue;
                }
                self::executeFile($pdo, $file, $prefix);
                if (self::hasSchemaProbe($version) && !self::versionSchemaReady($version)) {
                    throw new Exception('版本 v' . $version . ' 结构更新后校验失败');
                }
                self::markApplied($version);
                $applied[] = $version;
                if ($version === '10.12.0' && class_exists('StatDayManager')) {
                    StatDayManager::resetReadyCache();
                    StatDayManager::backfillLastDays(30);
                }
            }
        } catch (Exception $e) {
            return array(
                'ok'      => false,
                'msg'     => '数据库结构更新失败：' . $e->getMessage(),
                'applied' => $applied,
            );
        }

        Config::clearCache();

        return array(
            'ok'      => true,
            'msg'     => count($applied) > 0 ? '已执行 ' . count($applied) . ' 项数据库结构更新' : '数据库结构已是最新',
            'applied' => $applied,
        );
    }

    /**
     * 根据当前表结构同步已完成的更新记录（避免重复 ADD COLUMN 报错）
     *
     * @return void
     */
    public static function reconcileSchemaState()
    {
        if (!InstallChecker::isInstalled()) {
            return;
        }

        self::purgeLegacyArtifacts();

        $applied = self::getAppliedVersions();

        if (!in_array('1.0.20', $applied, true) && self::tableColumnExists('admin', 'avatar')) {
            self::markApplied('1.0.20');
        }

        if (!in_array('1.8.0', $applied, true) && self::tableColumnExists('admin', 'binduid')) {
            self::markApplied('1.8.0');
        }

        if (!in_array('1.9.0', $applied, true)) {
            $all = Config::all();
            if (array_key_exists('frontend_theme', $all)) {
                self::markApplied('1.9.0');
            }
        }

        if (!in_array('2.10.2', $applied, true) && self::tableExists('security_rate_hit')) {
            self::markApplied('2.10.2');
        }

        if (!in_array('2.11.0', $applied, true) && self::tableExists('mailrate')) {
            self::markApplied('2.11.0');
        }

        if (!in_array('2.12.0', $applied, true) && self::tableExists('api')) {
            self::markApplied('2.12.0');
        }

        if (!in_array('2.15.0', $applied, true) && (self::tableExists('api_category') || self::tableExists('category'))) {
            self::markApplied('2.15.0');
        }

        if (!in_array('2.15.1', $applied, true) && self::tableExists('category') && !self::tableExists('api_category')) {
            self::markApplied('2.15.1');
        }

        if (!in_array('2.16.0', $applied, true) && self::tableColumnExists('category', 'icon')) {
            self::markApplied('2.16.0');
        }

        if (!in_array('3.0.0', $applied, true) && self::tableColumnExists('user', 'role')) {
            self::markApplied('3.0.0');
        }

        // 新装已含 3.6.0+ 接口扩展字段时跳过旧 DROP 重建
        if (!in_array('3.6.0', $applied, true) && self::tableColumnExists('api', 'aidoc')) {
            self::markApplied('3.6.0');
        }

        if (!in_array('3.8.0', $applied, true) && self::tableColumnExists('api', 'audit')) {
            self::markApplied('3.8.0');
        }

        // 新装已是 needkey：3.7.1 仍写 require_key，必须跳过（E201）
        if (!in_array('3.7.1', $applied, true) && self::tableColumnExists('api', 'needkey')) {
            self::markApplied('3.7.1');
        }

        // 新装已含 3.9.0（字段去下划线）时跳过迁移
        if (!in_array('3.9.0', $applied, true) && self::tableColumnExists('api', 'needkey')) {
            self::markApplied('3.9.0');
        }

        // 新装已含 3.10.0（rejectreason + 审核三态）时跳过迁移
        if (!in_array('3.10.0', $applied, true) && self::tableColumnExists('api', 'rejectreason')) {
            self::markApplied('3.10.0');
        }

        // 新装已含 3.11.0（代理字段）时跳过迁移
        if (!in_array('3.11.0', $applied, true) && self::tableColumnExists('api', 'proxyslug')) {
            self::markApplied('3.11.0');
        }

        // 新装已含 3.18.0（apilog）时跳过迁移
        if (!in_array('3.18.0', $applied, true) && self::tableExists('apilog')) {
            self::markApplied('3.18.0');
        }

        // 新装已含 3.19.0（iploc）时跳过迁移
        if (!in_array('3.19.0', $applied, true) && self::tableColumnExists('apilog', 'iploc')) {
            self::markApplied('3.19.0');
        }

        // 新装已含 3.29.0（apikey）时跳过迁移；早期误建 token 则重命名
        if (self::tableExists('token') && !self::tableExists('apikey')) {
            try {
                $pdo = Database::connect();
                self::execStatement(
                    $pdo,
                    'RENAME TABLE `' . Database::table('token') . '` TO `' . Database::table('apikey') . '`'
                );
            } catch (Exception $e) {
                // 留待下次结构更新重试
            }
        }
        if (!in_array('3.29.0', $applied, true) && self::tableExists('apikey')) {
            self::markApplied('3.29.0');
        }

        // 新装已含 3.33.0（积分/订单/接口计费）时跳过迁移
        if (!in_array('3.33.0', $applied, true)
            && self::tableExists('orders')
            && self::tableColumnExists('user', 'points')
            && self::tableColumnExists('api', 'charge')) {
            self::markApplied('3.33.0');
        }

        // 新装无历史 /proxy.php?s= 地址时，3.12.0 的 UPDATE 幂等，不强制跳过

        // 新装/已升级含 link.kind 时跳过 5.0.0（E211：旧脚本曾全表 SET kind=0）
        if (!in_array('5.0.0', $applied, true) && self::tableColumnExists('link', 'kind')) {
            self::markApplied('5.0.0');
        }

        // 新装已含 5.5.0（kind 含赞助注释或收款码键）时跳过
        if (!in_array('5.5.0', $applied, true)
            && self::tableColumnExists('link', 'kind')
        ) {
            $allCfg55 = Config::all();
            if (isset($allCfg55['sponsor_qr_alipay']) || isset($allCfg55['sponsor_qr_wechat'])) {
                self::markApplied('5.5.0');
            }
        }

        // 新装已含 5.8.0 apilog 复合索引时跳过
        if (!in_array('5.8.0', $applied, true) && self::tableIndexExists('apilog', 'idx_createtime_id')) {
            self::markApplied('5.8.0');
        }

        // 新装已含 5.9.0 orders 复合索引时跳过
        if (!in_array('5.9.0', $applied, true) && self::tableIndexExists('orders', 'idx_userid_status_id')) {
            self::markApplied('5.9.0');
        }

        // 新装已含 6.0.0 用户资料字段时跳过
        if (!in_array('6.0.0', $applied, true) && self::tableColumnExists('user', 'wallpaper')) {
            self::markApplied('6.0.0');
        }

        // 新装已含 7.0.0 内容表时跳过
        if (!in_array('7.0.0', $applied, true) && self::tableExists('content')) {
            self::markApplied('7.0.0');
        }

        // 新装已含 7.1.0 封面布局字段时跳过
        if (!in_array('7.1.0', $applied, true) && self::tableColumnExists('content', 'coverlayout')) {
            self::markApplied('7.1.0');
        }

        // 新装已含 9.0.0 feedback 表时跳过
        if (!in_array('9.0.0', $applied, true) && self::tableExists('feedback')) {
            self::markApplied('9.0.0');
        }

        // 新装已含 9.2.0 反馈邮件开关时跳过
        if (!in_array('9.2.0', $applied, true)) {
            $allCfg = Config::all();
            if (isset($allCfg['mail_notify_feedback'])) {
                self::markApplied('9.2.0');
            }
        }

        // 新装已含 9.2.2 新反馈通知管理员开关时跳过
        if (!in_array('9.2.2', $applied, true)) {
            $allCfg2 = Config::all();
            if (isset($allCfg2['mail_notify_feedback_admin'])) {
                self::markApplied('9.2.2');
            }
        }

        // 新装已含 10.2.0 评论邮件开关时跳过
        if (!in_array('10.2.0', $applied, true)) {
            $allCfg3 = Config::all();
            if (isset($allCfg3['mail_notify_comment_admin']) && isset($allCfg3['mail_notify_comment'])) {
                self::markApplied('10.2.0');
            }
        }

        // 新装已含 10.4.0 签到能力时跳过（旧：checkin 表；新：user.lastcheckin + 配置）
        if (!in_array('10.4.0', $applied, true)) {
            $allCfg4 = Config::all();
            if (isset($allCfg4['register_gift_enabled']) && isset($allCfg4['checkin_enabled'])
                && (self::tableExists('checkin') || self::tableColumnExists('user', 'lastcheckin'))
            ) {
                self::markApplied('10.4.0');
            }
        }

        // 新装已含 10.5.0 api.qpm 时跳过
        if (!in_array('10.5.0', $applied, true) && self::tableColumnExists('api', 'qpm')) {
            self::markApplied('10.5.0');
        }

        // 新装已含 10.6.0 orders.remark 前缀索引时跳过
        if (!in_array('10.6.0', $applied, true) && self::tableIndexExists('orders', 'idx_remark_prefix')) {
            self::markApplied('10.6.0');
        }

        // 新装已含 10.11.0 content.bindpage 时跳过
        if (!in_array('10.11.0', $applied, true) && self::tableColumnExists('content', 'bindpage')) {
            self::markApplied('10.11.0');
        }

        // 新装已含 10.12.0 statday 时跳过；空表则回填近 30 天
        if (!in_array('10.12.0', $applied, true) && self::tableExists('statday')) {
            self::markApplied('10.12.0');
            if (class_exists('StatDayManager')) {
                StatDayManager::resetReadyCache();
                try {
                    $pdo = Database::connect();
                    $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . Database::table('statday') . '`')->fetchColumn();
                    if ($n === 0) {
                        StatDayManager::backfillLastDays(30);
                    }
                } catch (Exception $e) {
                    // 下次控制台访问再补
                }
            }
        }

        // 新装已含 10.13.0 上游认证字段时跳过
        if (!in_array('10.13.0', $applied, true) && self::tableColumnExists('api', 'upauth')) {
            self::markApplied('10.13.0');
        }

        // 新装已含 13.22.2 用户累计消耗/密钥调用缓存列时跳过（仅 mark，勿再全表回填）
        if (!in_array('13.22.2', $applied, true)
            && self::tableColumnExists('user', 'pointsspent')
            && self::tableColumnExists('user', 'keycalls')) {
            self::markApplied('13.22.2');
        }

        // 新装已含 13.22.5 代理上游请求方式列时跳过
        if (!in_array('13.22.5', $applied, true) && self::tableColumnExists('api', 'upmethod')) {
            self::markApplied('13.22.5');
        }

        // 新装已含 13.26.5 热点查询索引时跳过（配置种子仍靠 INSERT IGNORE 幂等）
        if (!in_array('13.26.5', $applied, true)
            && self::tableIndexExists('api', 'idx_status_audit_id')
            && self::tableIndexExists('apilog', 'idx_createtime_apiname')) {
            self::markApplied('13.26.5');
        }

        // 新装已含 13.26.7：邮件开关 + user.stat7 + apikey.pointsspent 时跳过（勿误重跑回填）
        if (!in_array('13.26.7', $applied, true)) {
            $allCfgPointsMail = Config::all();
            if (isset($allCfgPointsMail['mail_notify_points_zero'])
                && isset($allCfgPointsMail['mail_notify_recharge_success'])
                && self::tableColumnExists('user', 'stat7')
                && self::tableColumnExists('apikey', 'pointsspent')) {
                self::markApplied('13.26.7');
            }
        }

        // 新装已含 13.26.22 密钥数量上限配置时跳过
        if (!in_array('13.26.22', $applied, true)) {
            $allCfgKeyMax = Config::all();
            if (isset($allCfgKeyMax['apikey_max'])) {
                self::markApplied('13.26.22');
            }
        }

        // 新装已含 13.26.28 积分不足调用邮件开关时跳过
        if (!in_array('13.26.28', $applied, true)) {
            $allCfgInsuf = Config::all();
            if (isset($allCfgInsuf['mail_notify_points_insufficient'])) {
                self::markApplied('13.26.28');
            }
        }

        // 新装已含 13.26.29 user.ipallow 时跳过
        if (!in_array('13.26.29', $applied, true)) {
            if (self::tableColumnExists('user', 'ipallow')) {
                self::markApplied('13.26.29');
            }
        }

        // 新装已含 13.26.30 ipproxy / proxystrategy 时跳过
        if (!in_array('13.26.30', $applied, true)) {
            if (self::tableExists('ipproxy') && self::tableColumnExists('user', 'proxystrategy')) {
                self::markApplied('13.26.30');
            }
        }

        // 新装已含 13.26.31 全量列时跳过
        if (!in_array('13.26.31', $applied, true)) {
            if (self::tableColumnExists('ipproxy', 'proxycode')
                && self::tableColumnExists('ipproxy', 'jsonhost')
                && self::tableColumnExists('ipproxy', 'ttlmin')) {
                self::markApplied('13.26.31');
            }
        }

        // 新装已含 13.26.34 apilog.egress 时跳过
        if (!in_array('13.26.34', $applied, true)) {
            if (self::tableColumnExists('apilog', 'egress')) {
                self::markApplied('13.26.34');
            }
        }

        // 新装已是五位 proxycode 且无旧三位残留时跳过 13.26.35
        if (!in_array('13.26.35', $applied, true)) {
            if (self::proxyCodeColumnIsFive() && self::proxyCodesAllFiveReady()) {
                self::markApplied('13.26.35');
            }
        }

        // 新装已含 13.26.40 令牌配额列 + 邮件开关时跳过
        if (!in_array('13.26.40', $applied, true)) {
            if (self::tableColumnExists('apikey', 'quota')
                && self::tableColumnExists('apikey', 'quotaused')
                && self::tableColumnExists('apikey', 'quotafallback')
                && self::tableColumnExists('apikey', 'expiretime')) {
                $allCfg40 = Config::all();
                if (isset($allCfg40['mail_notify_key_quota'])) {
                    self::markApplied('13.26.40');
                }
            }
        }

        // 新装已是 register_enabled 单键 1～4 且无旧子开关时跳过 13.26.41
        if (!in_array('13.26.41', $applied, true)) {
            if (self::registerModeReady41()) {
                self::markApplied('13.26.41');
            }
        }

        // 新装已含 13.26.45：user.lastcheckin + aggmap + 无 checkin 表 + 订单管理员邮件开关 + statday.pointscost
        if (!in_array('13.26.45', $applied, true)) {
            $allCfg45 = Config::all();
            if (self::tableColumnExists('user', 'lastcheckin')
                && self::tableColumnExists('user', 'aggmap')
                && !self::tableExists('checkin')
                && isset($allCfg45['mail_notify_order_admin'])
                && self::tableColumnExists('statday', 'pointscost')
            ) {
                self::markApplied('13.26.45');
            }
        }

        // 新装已含 13.26.46：令牌范围 + 转发头 + feedback.kind，且禁止列/旧 link 申请列已不存在
        if (!in_array('13.26.46', $applied, true)
            && self::apilogExtended46Ready()
            && self::feedbackKind46Ready()
        ) {
            self::markApplied('13.26.46');
        }

        // 5.8.0 重构：热天数 / 计划任务密钥（幂等；兼容已跑过旧版 keep_days 的站点）
        self::ensureApilogArchiveConfig();
        // 13.26.5：热点索引幂等补齐（已应用过 13.26.5 仅含 config 种子的站点）
        self::ensureCompatHotIndexes();
        // 13.26.7：用户七日窗 / 密钥消耗 / 用户日志索引（已 mark 仅含邮件开关的站点须补列）
        self::ensureUserDashStatSchema();
        // 13.26.31：ipproxy 短码 / JSON 路径 / TTL 缓存列幂等补齐 + 短码回填
        self::ensureIpProxySchema31();
        // 13.26.35：proxycode 升为五位并重置旧三位
        self::ensureProxyCodeFive35();
        // 13.26.34：apilog.egress 幂等补齐
        self::ensureApilogEgress34();
        // 13.26.31：安装完成库标记（与 install.lock 双保险）
        self::ensureInstallDoneFlag();
        // 13.26.45：聚合登录 aggmap 列幂等补齐
        self::ensureUserAggmap45();
        // 13.26.45：statday.pointscost 幂等补齐（已 mark 旧版 45 缺列的站点）
        self::ensureStatdayPointscost45();
        // 13.26.45：调用日志单字段搜索索引
        self::ensureApilogSearchIndexes45();
        // 13.26.45：主题设置 MySQL 分桶 → core/data/{id}/theme.db
        self::ensureThemeSettingsLocal45();
        // 13.26.46：令牌接口范围 scopejson 幂等补齐
        self::ensureApikeyScopeSchema();
        // 13.26.46：调用日志转发头列；删耗时/响应正文/低价值头列
        self::ensureApilogExtended46();
        self::dropApilogDurationms46();
        self::dropApilogRespBody46();
        self::dropApilogLowValueHeaders46();
        // 13.26.46：feedback.kind；link.kind=3 → feedback；再 DROP link.userid
        self::ensureFeedbackKind46();
        self::migrateLinkDevApplyToFeedback46();
        self::dropLinkUserid46();
        // 13.26.46：开发者申请邮件开关（已 mark 站点幂等补键）
        self::ensureMailNotifyDevApply46();
        // 13.26.46：曾误建激励任务表则幂等删除（积分赠送不落独立表，只写 orders）
        self::dropIncentiveGiftJobIfExists46();
        // 13.26.46：content.audmode（站内公告受众）
        self::ensureContentAudmode46();
        // 13.26.46：邮件群发批次表 maillog
        self::ensureMaillog46();
        // 13.26.47：邮箱模板中文占位 → 英文（幂等；本版无 migrations/13.26.47.sql、不进账本）
        self::ensureMailPlaceholders47();
    }

    /**
     * 对外：确保出口短码为五位并重置旧三位（用户中心列表/保存前可调用）
     *
     * @return void
     */
    public static function ensureIpProxyCodeFive()
    {
        self::ensureProxyCodeFive35();
    }

    /**
     * 13.26.45：user.aggmap 幂等补齐（已 mark 仅含签到迁移的站点须补列）
     *
     * @return void
     */
    private static function ensureUserAggmap45()
    {
        if (!self::tableExists('user') || self::tableColumnExists('user', 'aggmap')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $pdo->exec(
                'ALTER TABLE `' . $table . '` '
                . 'ADD COLUMN `aggmap` text COMMENT \'聚合登录绑定JSON（键为内部id，值为social_uid）\' AFTER `giteeid`'
            );
        } catch (Exception $e) {
            // 幂等
        }
    }

    /**
     * 13.26.45：apilog 单字段搜索索引幂等补齐
     *
     * @return void
     */
    private static function ensureApilogSearchIndexes45()
    {
        if (!self::tableExists('apilog')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $checks = array(
                array('idx_apiname', 'ADD KEY `idx_apiname` (`apiname`)'),
                array('idx_apikey', 'ADD KEY `idx_apikey` (`apikey`(64))'),
                array('idx_userid_apiid', 'ADD KEY `idx_userid_apiid` (`userid`, `apiid`)'),
                array('idx_userid_ip', 'ADD KEY `idx_userid_ip` (`userid`, `ip`)'),
                array('idx_userid_apiname', 'ADD KEY `idx_userid_apiname` (`userid`, `apiname`)'),
            );
            $table = Database::table('apilog');
            foreach ($checks as $row) {
                if (self::tableIndexExists('apilog', $row[0])) {
                    continue;
                }
                self::execStatement($pdo, 'ALTER TABLE `' . $table . '` ' . $row[1]);
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * 13.26.45：statday.pointscost 幂等补齐（已 mark 旧版 45 缺列的站点）
     *
     * @return void
     */
    private static function ensureStatdayPointscost45()
    {
        if (!self::tableExists('statday') || self::tableColumnExists('statday', 'pointscost')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('statday');
            $pdo->exec(
                'ALTER TABLE `' . $table . '` '
                . 'ADD COLUMN `pointscost` decimal(14,4) NOT NULL DEFAULT 0.0000 '
                . 'COMMENT \'当日积分消耗合计（已扣积分金额）\' AFTER `pointscalls`'
            );
            if (class_exists('StatDayManager')) {
                StatDayManager::resetReadyCache();
                StatDayManager::backfillLastDays(30);
            }
        } catch (Exception $e) {
            // 幂等
        }
    }

    /**
     * 13.26.45：主题设置迁入 core/data/{id}/theme.db（强制覆盖后清空 MySQL 分桶）
     *
     * @return void
     */
    private static function ensureThemeSettingsLocal45()
    {
        if (!class_exists('ThemeSettingsStore')) {
            $path = VS_ROOT . '/core/ThemeSettingsStore.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (!class_exists('ThemeSettingsStore')) {
            return;
        }
        try {
            ThemeSettingsStore::migrateFromMysql(false);
        } catch (Exception $e) {
            // 幂等；无 sqlite 时 migrate 返回字符串，不抛
        }
    }

    /**
     * 确保 config.install_done=1（幂等；已装站点升级补齐）
     *
     * @return void
     */
    private static function ensureInstallDoneFlag()
    {
        if (!self::tableExists('config')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('config');
            $key = class_exists('InstallChecker')
                ? InstallChecker::CONFIG_KEY_DONE
                : 'install_done';
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $table . '` (`key`, `value`) VALUES (?, ?)'
                . ' ON DUPLICATE KEY UPDATE `value` = \'1\''
            );
            $stmt->execute(array($key, '1'));
            if (class_exists('Config')) {
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 幂等兜底失败不阻断升级
        }
    }

    /**
     * 确保出口代理表 13.26.31 列齐全并回填短码（幂等；列宽以 ensureProxyCodeFive35 为准）
     *
     * @return void
     */
    private static function ensureIpProxySchema31()
    {
        if (!self::tableExists('ipproxy')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');
            $cols = array(
                'jsonhost'  => "ADD COLUMN `jsonhost` varchar(80) NOT NULL DEFAULT '' COMMENT 'JSON主机字段名或点路径（空=自动识别常见键）' AFTER `extfmt`",
                'jsonport'  => "ADD COLUMN `jsonport` varchar(80) NOT NULL DEFAULT '' COMMENT 'JSON端口字段名或点路径（空=自动或主机内含端口）' AFTER `jsonhost`",
                'proxycode' => "ADD COLUMN `proxycode` char(5) NOT NULL DEFAULT '' COMMENT '调用短码（五位随机0-9a-z；vsproxy仅认此码）' AFTER `jsonport`",
                'ttlmin'    => "ADD COLUMN `ttlmin` int(11) NOT NULL DEFAULT 10 COMMENT '提取节点缓存分钟（0=每次重新提取）' AFTER `proxycode`",
                'cachehost' => "ADD COLUMN `cachehost` varchar(255) NOT NULL DEFAULT '' COMMENT '提取缓存主机' AFTER `ttlmin`",
                'cacheport' => "ADD COLUMN `cacheport` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '提取缓存端口' AFTER `cachehost`",
                'cacheexp'  => "ADD COLUMN `cacheexp` datetime DEFAULT NULL COMMENT '提取缓存过期时间' AFTER `cacheport`",
            );
            foreach ($cols as $name => $ddl) {
                if (!self::tableColumnExists('ipproxy', $name)) {
                    self::execStatement($pdo, 'ALTER TABLE `' . $table . '` ' . $ddl);
                }
            }
            if (class_exists('UserIpProxy') && method_exists('UserIpProxy', 'backfillMissingProxyCodes')) {
                UserIpProxy::backfillMissingProxyCodes();
            }
            // 短码唯一：先回填再升 UNIQUE；兼容旧 idx_userid_code
            try {
                $hasUk = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'uk_userid_code'");
                if ($hasUk && $hasUk->rowCount() < 1) {
                    try {
                        $old = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_userid_code'");
                        if ($old && $old->rowCount() > 0) {
                            self::execStatement($pdo, 'ALTER TABLE `' . $table . '` DROP INDEX `idx_userid_code`');
                        }
                    } catch (Exception $eDrop) {
                        // ignore
                    }
                    self::execStatement(
                        $pdo,
                        'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uk_userid_code` (`userid`, `proxycode`)'
                    );
                }
            } catch (Exception $eIdx) {
                // ignore（重复短码需人工清理后再升）
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 确保 ipproxy.proxycode 为 char(5)，并将旧三位/非法短码重置为五位（13.26.35 幂等）
     *
     * @return void
     */
    private static function ensureProxyCodeFive35()
    {
        if (!self::tableExists('ipproxy') || !self::tableColumnExists('ipproxy', 'proxycode')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');
            if (!self::proxyCodeColumnIsFive()) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` MODIFY COLUMN `proxycode` char(5) NOT NULL DEFAULT \'\' '
                    . 'COMMENT \'调用短码（五位随机0-9a-z；vsproxy仅认此码，不认数字主键）\''
                );
            }
            // 列已五位且无非法残留时只补空码，避免用户请求反复全表扫描
            $needRegen = !self::proxyCodesAllFiveReady();
            if ($needRegen
                && class_exists('UserIpProxy')
                && method_exists('UserIpProxy', 'regenerateLegacyProxyCodesToFive')) {
                UserIpProxy::regenerateLegacyProxyCodesToFive();
            }
            if (class_exists('UserIpProxy') && method_exists('UserIpProxy', 'backfillMissingProxyCodes')) {
                UserIpProxy::backfillMissingProxyCodes();
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * proxycode 列最大长度是否已 ≥5
     *
     * @return bool
     */
    private static function proxyCodeColumnIsFive()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName === '') {
                return false;
            }
            $stmt = $pdo->prepare(
                'SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute(array($dbName, $table, 'proxycode'));
            $len = $stmt->fetchColumn();
            return $len !== false && (int) $len >= 5;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 是否已无「非空且非合法五位」的短码残留
     *
     * @return bool
     */
    private static function proxyCodesAllFiveReady()
    {
        if (!self::tableExists('ipproxy') || !self::tableColumnExists('ipproxy', 'proxycode')) {
            return false;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('ipproxy');
            // 非空且不是恰好 5 位 0-9a-z
            $sql = 'SELECT 1 FROM `' . $table . '` WHERE `proxycode` <> \'\' AND `proxycode` IS NOT NULL'
                . ' AND `proxycode` NOT REGEXP \'^[0-9a-z]{5}$\' LIMIT 1';
            $stmt = $pdo->query($sql);
            if ($stmt && $stmt->fetch(PDO::FETCH_NUM)) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 确保 apilog.egress 列存在（13.26.34 幂等）
     *
     * @return void
     */
    private static function ensureApilogEgress34()
    {
        if (!self::tableExists('apilog') || self::tableColumnExists('apilog', 'egress')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apilog');
            self::execStatement(
                $pdo,
                'ALTER TABLE `' . $table . '` ADD COLUMN `egress` varchar(64) NOT NULL DEFAULT \'\' '
                . 'COMMENT \'出口节点（IP:端口；空=未走出口代理）\' AFTER `iploc`'
            );
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46 调用日志扩列是否终态就绪（含禁止列已删除）
     *
     * @return bool
     */
    private static function apilogExtended46Ready()
    {
        if (!self::tableColumnExists('apikey', 'scopejson')) {
            return false;
        }
        if (!self::tableExists('apilog')) {
            return false;
        }
        if (!self::tableColumnExists('apilog', 'xfproto')
            || !self::tableColumnExists('apilog', 'xff')
            || !self::tableColumnExists('apilog', 'xri')
        ) {
            return false;
        }
        // 曾误加列必须已 DROP，否则升级页会假绿、库仍膨胀
        $banned = array(
            'durationms', 'resptrunc', 'respbytes', 'respbody', 'resptype',
            'via', 'conn', 'reqctype', 'reqclen',
        );
        foreach ($banned as $col) {
            if (self::tableColumnExists('apilog', $col)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 13.26.46：feedback.kind 是否终态就绪（无残留 link 开发者申请行 / userid 列）
     *
     * @return bool
     */
    private static function feedbackKind46Ready()
    {
        if (!self::tableExists('feedback') || !self::tableColumnExists('feedback', 'kind')) {
            return false;
        }
        if (self::tableExists('link') && self::tableColumnExists('link', 'userid')) {
            return false;
        }
        if (self::tableExists('link')) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->query(
                    'SELECT COUNT(*) FROM `' . Database::table('link') . '` WHERE `kind` = 3'
                );
                if ($stmt && (int) $stmt->fetchColumn() > 0) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * 13.26.46：content.audmode 是否就绪
     *
     * @return bool
     */
    private static function contentAudmode46Ready()
    {
        return self::tableExists('content') && self::tableColumnExists('content', 'audmode');
    }

    /**
     * 13.26.46：content.audmode 幂等补齐（站内公告受众；列默认 0，禁止 MODIFY kind）
     *
     * @return void
     */
    private static function ensureContentAudmode46()
    {
        if (!self::tableExists('content')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('content');
            if (!self::tableColumnExists('content', 'audmode')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` '
                    . 'ADD COLUMN `audmode` tinyint(1) NOT NULL DEFAULT 0 '
                    . 'COMMENT \'受众：0无(非站内公告) 1全站 2开发者 3普通用户\' AFTER `ispopup`'
                );
            }
            if (self::tableColumnExists('content', 'audmode')) {
                self::execStatement(
                    $pdo,
                    'UPDATE `' . $table . '` SET `audmode` = 0 WHERE `kind` <> 2 AND `audmode` <> 0'
                );
            }
            if (self::tableColumnExists('content', 'audmode')
                && !self::tableIndexExists('content', 'idx_kind_status_aud_pin')
            ) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` '
                    . 'ADD KEY `idx_kind_status_aud_pin` (`kind`, `status`, `audmode`, `ispinned`, `id`)'
                );
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.47：邮箱模板中文占位是否已清（无 {{…中文…}}）
     *
     * @return bool
     */
    private static function mailPlaceholders47Ready()
    {
        if (!self::tableExists('content') || !class_exists('MailChannels')) {
            return true;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('content');
            // 仅「可映射」中文占位阻塞；错别字/未知中文键不阻塞（E473）
            $stmt = $pdo->query(
                'SELECT `title`, `summary`, `body` FROM `' . $table . '` WHERE `kind` = 3'
            );
            if (!$stmt) {
                return false;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (array('title', 'summary', 'body') as $col) {
                    $text = isset($row[$col]) ? (string) $row[$col] : '';
                    if ($text !== '' && MailChannels::textHasMappableChinesePlaceholder($text)) {
                        return false;
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 13.26.47：邮箱模板 {{中文}} → {{英文短连写}}（幂等；不做运行时双轨）
     *
     * @return void
     */
    private static function ensureMailPlaceholders47()
    {
        try {
            if (!self::tableExists('content') || !class_exists('MailChannels')) {
                return;
            }
            $pdo = Database::connect();
            $table = Database::table('content');
            $hasBind = self::tableColumnExists('content', 'bindpage');
            $cols = $hasBind
                ? 'SELECT `id`, `title`, `summary`, `body`, `bindpage` FROM `' . $table . '` WHERE `kind` = 3'
                : 'SELECT `id`, `title`, `summary`, `body` FROM `' . $table . '` WHERE `kind` = 3';
            $stmt = $pdo->query($cols);
            if (!$stmt) {
                return;
            }
            $upd = $pdo->prepare(
                'UPDATE `' . $table . '` SET `title` = ?, `summary` = ?, `body` = ? WHERE `id` = ? LIMIT 1'
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $bind = $hasBind && isset($row['bindpage']) ? (int) $row['bindpage'] : 0;
                $title = isset($row['title']) ? (string) $row['title'] : '';
                $summary = isset($row['summary']) ? (string) $row['summary'] : '';
                $body = isset($row['body']) ? (string) $row['body'] : '';
                $nt = MailChannels::migrateChinesePlaceholdersInText($title, $bind);
                $ns = MailChannels::migrateChinesePlaceholdersInText($summary, $bind);
                $nb = MailChannels::migrateChinesePlaceholdersInText($body, $bind);
                if ($nt === $title && $ns === $summary && $nb === $body) {
                    continue;
                }
                $upd->execute(array($nt, $ns, $nb, $id));
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：邮件群发批次表 maillog（一批一行）
     *
     * @return void
     */
    private static function ensureMaillog46()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('maillog');
            if (!self::tableExists('maillog')) {
                self::execStatement(
                    $pdo,
                    'CREATE TABLE IF NOT EXISTS `' . $table . '` (
                      `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT \'主键ID\',
                      `adminid` int unsigned NOT NULL DEFAULT 0 COMMENT \'操作管理员ID\',
                      `tplid` int unsigned NOT NULL DEFAULT 0 COMMENT \'模板content.id\',
                      `tplname` varchar(200) NOT NULL DEFAULT \'\' COMMENT \'模板名称快照\',
                      `subject` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'发信标题快照\',
                      `filtersum` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'筛选条件摘要\',
                      `total` int unsigned NOT NULL DEFAULT 0 COMMENT \'目标人数\',
                      `oknum` int unsigned NOT NULL DEFAULT 0 COMMENT \'成功人数\',
                      `failnum` int unsigned NOT NULL DEFAULT 0 COMMENT \'失败人数\',
                      `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT \'状态：0发送中 1已完成 2已中断\',
                      `uids` mediumtext NOT NULL COMMENT \'目标用户ID JSON数组\',
                      `failjson` mediumtext COMMENT \'失败明细JSON\',
                      `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'开始时间\',
                      `lastactivity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'最后活动时间（tick/进度刷新）\',
                      `finishtime` datetime DEFAULT NULL COMMENT \'结束时间\',
                      PRIMARY KEY (`id`),
                      KEY `idx_createtime` (`createtime`),
                      KEY `idx_adminid_id` (`adminid`, `id`),
                      KEY `idx_status_id` (`status`, `id`),
                      KEY `idx_status_lastactivity` (`status`, `lastactivity`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'邮件群发批次记录（一批一行）\''
                );
            }
            self::ensureMaillogLastactivity46();
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：maillog.lastactivity 幂等补齐（收尸按活动时间，避免误杀长任务）
     *
     * @return void
     */
    private static function ensureMaillogLastactivity46()
    {
        if (!self::tableExists('maillog') || self::tableColumnExists('maillog', 'lastactivity')) {
            if (self::tableExists('maillog')
                && self::tableColumnExists('maillog', 'lastactivity')
                && !self::tableIndexExists('maillog', 'idx_status_lastactivity')
            ) {
                try {
                    $pdo = Database::connect();
                    self::execStatement(
                        $pdo,
                        'ALTER TABLE `' . Database::table('maillog') . '` '
                        . 'ADD KEY `idx_status_lastactivity` (`status`, `lastactivity`)'
                    );
                } catch (Exception $e) {
                }
            }
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('maillog');
            self::execStatement(
                $pdo,
                'ALTER TABLE `' . $table . '` '
                . 'ADD COLUMN `lastactivity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP '
                . 'COMMENT \'最后活动时间（tick/进度刷新）\' AFTER `createtime`'
            );
            self::execStatement(
                $pdo,
                'UPDATE `' . $table . '` SET `lastactivity` = `createtime` '
                . 'WHERE `lastactivity` IS NULL OR `lastactivity` < `createtime`'
            );
            if (!self::tableIndexExists('maillog', 'idx_status_lastactivity')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` ADD KEY `idx_status_lastactivity` (`status`, `lastactivity`)'
                );
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：feedback.kind 幂等补齐（接口反馈=0 / 开发者申请=1）
     *
     * @return void
     */
    private static function ensureFeedbackKind46()
    {
        if (!self::tableExists('feedback')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('feedback');
            if (!self::tableColumnExists('feedback', 'kind')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` '
                    . 'ADD COLUMN `kind` tinyint unsigned NOT NULL DEFAULT 0 '
                    . 'COMMENT \'类型：0接口反馈 1开发者申请\' AFTER `id`'
                );
            }
            if (self::tableColumnExists('feedback', 'kind')
                && !self::tableIndexExists('feedback', 'idx_kind_status')
            ) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` ADD KEY `idx_kind_status` (`kind`, `status`)'
                );
            }
            if (self::tableColumnExists('feedback', 'kind')
                && !self::tableIndexExists('feedback', 'idx_kind_userid_status')
            ) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` '
                    . 'ADD KEY `idx_kind_userid_status` (`kind`, `userid`, `status`)'
                );
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：将 link.kind=3 开发者申请迁入 feedback.kind=1 后删除源行
     * 若库尚无 link.userid（极早半成品），先临时 ADD 再迁（仅服务本迁移）
     *
     * @return void
     */
    private static function migrateLinkDevApplyToFeedback46()
    {
        if (!self::tableExists('link') || !self::tableExists('feedback')) {
            return;
        }
        if (!self::tableColumnExists('feedback', 'kind')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $linkTable = Database::table('link');
            $fbTable = Database::table('feedback');

            // 旧站可能尚无 userid：补列才能读申请人（新装终态无此列，由 dropLinkUserid46 删掉）
            if (!self::tableColumnExists('link', 'userid')) {
                $cntNoCol = $pdo->query(
                    'SELECT COUNT(*) FROM `' . $linkTable . '` WHERE `kind` = 3'
                );
                if ($cntNoCol && (int) $cntNoCol->fetchColumn() > 0) {
                    self::execStatement(
                        $pdo,
                        'ALTER TABLE `' . $linkTable . '` '
                        . 'ADD COLUMN `userid` int unsigned NOT NULL DEFAULT 0 '
                        . 'COMMENT \'迁移临时列\' AFTER `id`'
                    );
                }
            }

            if (!self::tableColumnExists('link', 'userid')) {
                return;
            }

            $sel = $pdo->query(
                'SELECT `id`, `userid`, `description`, `status`, `createtime`, `updatetime`'
                . ' FROM `' . $linkTable . '` WHERE `kind` = 3 ORDER BY `id` ASC'
            );
            $rows = $sel ? $sel->fetchAll(PDO::FETCH_ASSOC) : array();
            if (!is_array($rows) || count($rows) === 0) {
                return;
            }

            $ins = $pdo->prepare(
                'INSERT INTO `' . $fbTable . '`'
                . ' (`kind`, `apiid`, `userid`, `content`, `reply`, `status`, `createtime`, `updatetime`)'
                . ' VALUES (1, 0, ?, ?, \'\', ?, ?, ?)'
            );
            $del = $pdo->prepare(
                'DELETE FROM `' . $linkTable . '` WHERE `id` = ? AND `kind` = 3 LIMIT 1'
            );

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $userid = isset($row['userid']) ? (int) $row['userid'] : 0;
                if ($id <= 0 || $userid <= 0) {
                    // 孤儿行直接删，避免卡死就绪探测
                    if ($id > 0) {
                        $del->execute(array($id));
                    }
                    continue;
                }
                // 幂等：同用户同理由同状态已在 feedback 则只删 link
                $dup = $pdo->prepare(
                    'SELECT `id` FROM `' . $fbTable . '`'
                    . ' WHERE `kind` = 1 AND `userid` = ? AND `content` = ? AND `status` = ?'
                    . ' LIMIT 1'
                );
                $content = isset($row['description']) ? (string) $row['description'] : '';
                $status = isset($row['status']) ? (int) $row['status'] : 0;
                if ($status !== 1 && $status !== 2) {
                    $status = 0;
                }
                $dup->execute(array($userid, $content, $status));
                if (!$dup->fetchColumn()) {
                    $ct = isset($row['createtime']) && $row['createtime'] !== null && $row['createtime'] !== ''
                        ? (string) $row['createtime']
                        : date('Y-m-d H:i:s');
                    $ut = isset($row['updatetime']) && $row['updatetime'] !== null && $row['updatetime'] !== ''
                        ? (string) $row['updatetime']
                        : null;
                    $ins->execute(array($userid, $content, $status, $ct, $ut));
                }
                $del->execute(array($id));
            }
            if (class_exists('LinkManager') && method_exists('LinkManager', 'invalidateCache')) {
                LinkManager::invalidateCache();
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：开发者申请迁出后删除 link.userid 及专用索引（终态与新装一致）
     *
     * @return void
     */
    private static function dropLinkUserid46()
    {
        if (!self::tableExists('link') || !self::tableColumnExists('link', 'userid')) {
            return;
        }
        // 仍有 kind=3 则先不删列，避免丢数据
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query(
                'SELECT COUNT(*) FROM `' . Database::table('link') . '` WHERE `kind` = 3'
            );
            if ($stmt && (int) $stmt->fetchColumn() > 0) {
                return;
            }
            $table = Database::table('link');
            if (self::tableIndexExists('link', 'idx_kind_userid_status')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` DROP INDEX `idx_kind_userid_status`'
                );
            }
            self::execStatement(
                $pdo,
                'ALTER TABLE `' . $table . '` DROP COLUMN `userid`'
            );
            if (class_exists('LinkManager') && method_exists('LinkManager', 'resetUseridColumnCache')) {
                LinkManager::resetUseridColumnCache();
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：开发者申请业务邮件三开关幂等补键（已 mark 站点亦补）
     *
     * @return void
     */
    private static function ensureMailNotifyDevApply46()
    {
        try {
            $pdo = Database::connect();
            $cfg = Database::table('config');
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO `' . $cfg . '` (`key`, `value`) VALUES (?, ?)'
            );
            foreach (array(
                array('mail_notify_dev_apply', '1'),
                array('mail_notify_dev_pass', '1'),
                array('mail_notify_dev_fail', '1'),
            ) as $row) {
                $ins->execute($row);
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：仅配置种子（INSERT IGNORE / 安全 DELETE 旧长键）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    private static function apply132646ConfigSeeds(PDO $pdo, $prefix)
    {
        $cfg = $prefix . 'config';
        $seeds = array(
            array(
                'sysapion',
                '{"apilogarchive":0,"apiquery":0,"apirank":0,"cardkey":0,"checkin":0,"sitestats":0,"userinfo":0}',
            ),
            array('uabrand', 'ApiNexus'),
            array('mailsmtp', '{"mode":0,"list":[]}'),
            array('cfid', ''),
            array('cfkey', ''),
            array('captcha_pepper', ''),
            array(
                'sysapirq',
                '{"apis":{"apilogarchive":{"qpm":0,"qps":0},"apiquery":{"qpm":0,"qps":0},"apirank":{"qpm":0,"qps":0},"cardkey":{"qpm":0,"qps":0},"checkin":{"qpm":0,"qps":0},"sitestats":{"qpm":0,"qps":0},"userinfo":{"qpm":0,"qps":0}}}',
            ),
            array('mail_notify_dev_apply', '1'),
            array('mail_notify_dev_pass', '1'),
            array('mail_notify_dev_fail', '1'),
        );
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO `' . $cfg . '` (`key`, `value`) VALUES (?, ?)'
        );
        foreach ($seeds as $row) {
            try {
                $ins->execute($row);
            } catch (Exception $e) {
                // 幂等
            }
        }
        try {
            self::execStatement(
                $pdo,
                'UPDATE `' . $cfg . '` AS n INNER JOIN `' . $cfg . '` AS o '
                . 'ON o.`key` = \'outbound_ua_brand\' SET n.`value` = o.`value` '
                . 'WHERE n.`key` = \'uabrand\''
            );
        } catch (Exception $e) {
            // 幂等
        }
        $dropKeys = array(
            'apiquery_api_enabled',
            'apirank_api_enabled',
            'cardkey_api_enabled',
            'apilog_api_enabled',
            'outbound_ua_brand',
        );
        $del = $pdo->prepare('DELETE FROM `' . $cfg . '` WHERE `key` = ? LIMIT 1');
        foreach ($dropKeys as $key) {
            try {
                $del->execute(array($key));
            } catch (Exception $e) {
                // 幂等
            }
        }
    }

    /**
     * 确保 apilog 转发链请求头列（13.26.46 幂等；不含耗时/响应正文/via/conn/req*）
     *
     * 说明：只改表结构，不对历史行做 UPDATE。缺哪列加哪列，合并为一条 ALTER，
     * 优先 ALGORITHM=INPLACE, LOCK=NONE，避免大表上连跑三次重建锁死。
     *
     * @return void
     */
    private static function ensureApilogExtended46()
    {
        try {
            if (!self::tableExists('apilog')) {
                return;
            }
            $pdo = Database::connect();
            $table = Database::table('apilog');
            $cols = array(
                'xfproto' => 'varchar(16) NOT NULL DEFAULT \'\' COMMENT \'转发协议（请求头 X-Forwarded-Proto）\'',
                'xff'     => 'varchar(500) NOT NULL DEFAULT \'\' COMMENT \'转发链路IP（请求头 X-Forwarded-For 原文）\'',
                'xri'     => 'varchar(45) NOT NULL DEFAULT \'\' COMMENT \'真实客户端IP头（请求头 X-Real-IP）\'',
            );
            $missing = array();
            foreach ($cols as $name => $decl) {
                if (!self::tableColumnExists('apilog', $name)) {
                    $missing[$name] = $decl;
                }
            }
            if (count($missing) === 0) {
                return;
            }
            $fragParts = array();
            foreach ($missing as $name => $decl) {
                $fragParts[] = 'ADD COLUMN `' . $name . '` ' . $decl;
            }
            $frag = implode(', ', $fragParts);
            // 优先在线 DDL；不支持或半成功撞 1060 时不早退，下面逐列补缺口
            try {
                $pdo->exec(
                    'ALTER TABLE `' . $table . '` ' . $frag . ', ALGORITHM=INPLACE, LOCK=NONE'
                );
            } catch (PDOException $eOnline) {
                if (!self::isIgnorableSqlError($eOnline)) {
                    try {
                        $pdo->exec('ALTER TABLE `' . $table . '` ' . $frag);
                    } catch (PDOException $eMerged) {
                        if (!self::isIgnorableSqlError($eMerged)) {
                            // 逐列兜底
                        }
                    }
                }
            }
            foreach ($cols as $name => $decl) {
                if (self::tableColumnExists('apilog', $name)) {
                    continue;
                }
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $decl
                );
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 删除曾误加的耗时列 durationms（13.26.46 产品纠偏）
     *
     * @return void
     */
    private static function dropApilogDurationms46()
    {
        try {
            if (!self::tableExists('apilog') || !self::tableColumnExists('apilog', 'durationms')) {
                return;
            }
            $pdo = Database::connect();
            $table = Database::table('apilog');
            self::execStatement($pdo, 'ALTER TABLE `' . $table . '` DROP COLUMN `durationms`');
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 删除低价值扩展头列 via/conn/reqctype/reqclen（13.26.46 纠偏）
     *
     * @return void
     */
    private static function dropApilogLowValueHeaders46()
    {
        try {
            if (!self::tableExists('apilog')) {
                return;
            }
            $pdo = Database::connect();
            $table = Database::table('apilog');
            $drops = array('via', 'conn', 'reqctype', 'reqclen');
            foreach ($drops as $col) {
                if (!self::tableColumnExists('apilog', $col)) {
                    continue;
                }
                self::execStatement($pdo, 'ALTER TABLE `' . $table . '` DROP COLUMN `' . $col . '`');
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 删除曾误加的响应正文列与相关配置（13.26.46 产品纠偏）
     *
     * @return void
     */
    private static function dropApilogRespBody46()
    {
        try {
            if (!self::tableExists('apilog')) {
                return;
            }
            $pdo = Database::connect();
            $table = Database::table('apilog');
            $drops = array('resptrunc', 'respbytes', 'respbody', 'resptype');
            foreach ($drops as $col) {
                if (!self::tableColumnExists('apilog', $col)) {
                    continue;
                }
                self::execStatement($pdo, 'ALTER TABLE `' . $table . '` DROP COLUMN `' . $col . '`');
            }
            foreach (array('apilog_store_body', 'apilog_body_max') as $cfgKey) {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM `' . Database::table('config') . '` WHERE `key` = ? LIMIT 1'
                    );
                    $stmt->execute(array($cfgKey));
                } catch (Exception $e) {
                    // 忽略单键删除失败
                }
            }
            if (class_exists('Config') && method_exists('Config', 'clearCache')) {
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 确保兼容性热点查询索引存在（幂等）
     *
     * @return void
     */
    private static function ensureCompatHotIndexes()
    {
        try {
            $pdo = Database::connect();
            $checks = array(
                array('api', 'idx_status_audit_id', 'ADD KEY `idx_status_audit_id` (`status`, `audit`, `id`)'),
                array('api', 'idx_createtime', 'ADD KEY `idx_createtime` (`createtime`)'),
                array('apilog', 'idx_createtime_apiname', 'ADD KEY `idx_createtime_apiname` (`createtime`, `apiname`)'),
                array('apilog', 'idx_createtime_iploc', 'ADD KEY `idx_createtime_iploc` (`createtime`, `iploc`(64))'),
                array('apilog', 'idx_userid_id', 'ADD KEY `idx_userid_id` (`userid`, `id`)'),
                array('apilog', 'idx_userid_createtime', 'ADD KEY `idx_userid_createtime` (`userid`, `createtime`)'),
                array('user', 'idx_createtime', 'ADD KEY `idx_createtime` (`createtime`)'),
            );
            foreach ($checks as $row) {
                $tableShort = $row[0];
                $indexName = $row[1];
                $addSql = $row[2];
                if (self::tableIndexExists($tableShort, $indexName)) {
                    continue;
                }
                if (!self::tableExists($tableShort)) {
                    continue;
                }
                $table = Database::table($tableShort);
                self::execStatement($pdo, 'ALTER TABLE `' . $table . '` ' . $addSql);
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * 确保用户控制台统计列与按用户日志索引存在（幂等；已 mark 13.26.7 仅邮件开关的站点可补齐）
     *
     * @return void
     */
    private static function ensureUserDashStatSchema()
    {
        try {
            $pdo = Database::connect();
            if (self::tableExists('user') && !self::tableColumnExists('user', 'stat7')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . Database::table('user') . '` '
                    . 'ADD COLUMN `stat7` mediumtext NULL COMMENT \'近7日调用聚合JSON（按日分桶，供用户控制台）\' AFTER `keycalls`'
                );
                if (class_exists('UserStat7Manager')) {
                    UserStat7Manager::resetColumnCache();
                }
            }
            if (self::tableExists('apikey') && !self::tableColumnExists('apikey', 'pointsspent')) {
                self::execStatement(
                    $pdo,
                    'ALTER TABLE `' . Database::table('apikey') . '` '
                    . 'ADD COLUMN `pointsspent` decimal(14,4) NOT NULL DEFAULT 0.0000 '
                    . 'COMMENT \'该密钥累计消耗积分（接口扣费合计）\' AFTER `calls`'
                );
                if (class_exists('ApiKeyManager') && method_exists('ApiKeyManager', 'resetPointsspentColumnCache')) {
                    ApiKeyManager::resetPointsspentColumnCache();
                }
                // 仅补列时回填密钥消耗（API 扣减 − 退回；不碰 user.points / pointsspent）
                try {
                    $pdo->exec(
                        'UPDATE `' . Database::table('apikey') . '` k
                         LEFT JOIN (
                            SELECT `keyid` AS kid,
                                   COALESCE(SUM(
                                       CASE
                                           WHEN `direct` = 0 AND `kind` = 0 THEN `amount`
                                           WHEN `direct` = 1 AND `kind` = 4 THEN -`amount`
                                           ELSE 0
                                       END
                                   ), 0) AS spent
                            FROM `' . Database::table('orders') . '`
                            WHERE `status` = 1 AND `keyid` > 0
                            GROUP BY `keyid`
                         ) o ON o.kid = k.`id`
                         SET k.`pointsspent` = GREATEST(0, COALESCE(o.spent, 0))'
                    );
                } catch (Exception $e2) {
                    // 下次结构更新重试
                }
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * 确保调用日志冷热归档配置存在（幂等）；顺带迁移系统密钥
     *
     * @return void
     */
    private static function ensureApilogArchiveConfig()
    {
        try {
            if (class_exists('SystemApiKey')) {
                SystemApiKey::migrateFromLegacy();
            }
            $all = Config::all();
            $hot = isset($all['apilog_hot_days']) ? trim((string) $all['apilog_hot_days']) : '';
            if ($hot === '' || (int) $hot < 1) {
                $fromKeep = isset($all['apilog_keep_days']) ? (int) $all['apilog_keep_days'] : 30;
                if ($fromKeep < 1) {
                    $fromKeep = 30;
                }
                Config::set('apilog_hot_days', (string) $fromKeep);
            }
            if (!array_key_exists('system_api_key', $all) && class_exists('SystemApiKey')) {
                // migrateFromLegacy 已尽量写入；仍缺则补空
                if (SystemApiKey::get() === '' && !array_key_exists('system_api_key', Config::all())) {
                    Config::set('system_api_key', '');
                }
            }
            if (!array_key_exists('apilog_archive_enabled', $all)) {
                Config::set('apilog_archive_enabled', '0');
            }
            if (!array_key_exists('apilog_purge_enabled', $all)) {
                Config::set('apilog_purge_enabled', '0');
            }
            // v13.26.46：系统 API 开关合并为 sysapion JSON；出站 UA 品牌 uabrand；多邮箱 mailsmtp
            if (class_exists('SystemApiKey')) {
                SystemApiKey::migrateGatesFromLegacy();
            }
            if (class_exists('Mailer')) {
                Mailer::ensureMigrated();
            } elseif (!array_key_exists('mailsmtp', $all)) {
                Config::set('mailsmtp', '{"mode":0,"list":[]}');
            }
            if (!array_key_exists('apilog_shard_rows', $all)) {
                Config::set('apilog_shard_rows', '5000');
            }
            if (!array_key_exists('uabrand', $all)) {
                $from = isset($all['outbound_ua_brand']) ? trim((string) $all['outbound_ua_brand']) : 'ApiNexus';
                if ($from === '') {
                    $from = 'ApiNexus';
                }
                Config::set('uabrand', $from);
            }
            // v13.26.46：Cloudflare Turnstile 凭证键（短名 cfid / cfkey）
            if (!array_key_exists('cfid', $all)) {
                Config::set('cfid', '');
            }
            if (!array_key_exists('cfkey', $all)) {
                Config::set('cfkey', '');
            }
            $dropOldCfg = array(
                'apilog_api_enabled',
                'cardkey_api_enabled',
                'apiquery_api_enabled',
                'apirank_api_enabled',
                'outbound_ua_brand',
                'apilogon',
                'cardkeyon',
                'checkinon',
                'apiqueryon',
                'apirankon',
                'userinfoon',
                'sitestatson',
                // 旧单账号 SMTP / 发件人：已迁入 mailsmtp 后清除
                'mail_smtp_host',
                'mail_smtp_port',
                'mail_smtp_user',
                'mail_smtp_pass',
                'mail_smtp_secure',
                'mail_from_email',
                'mail_from_name',
            );
            $allAfter = Config::all();
            $needDrop = false;
            foreach ($dropOldCfg as $oldKey) {
                if (array_key_exists($oldKey, $allAfter)) {
                    $needDrop = true;
                    break;
                }
            }
            if ($needDrop) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                foreach ($dropOldCfg as $oldKey) {
                    $stmt->execute(array($oldKey));
                }
                Config::clearCache();
            }
            if (self::tableExists('cardkey') && !self::tableIndexExists('cardkey', 'idx_status_points')) {
                try {
                    $pdo = Database::connect();
                    self::execStatement(
                        $pdo,
                        'ALTER TABLE `' . Database::table('cardkey') . '` ADD KEY `idx_status_points` (`status`, `points`, `id`)'
                    );
                } catch (Exception $e) {
                    // 留待下次结构更新重试
                }
            }
            if (array_key_exists('apilog_keep_days', $all)) {
                $pdo = Database::connect();
                $table = Database::table('config');
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array('apilog_keep_days'));
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 留待下次结构更新重试
        }
    }

    /**
     * 索引是否已存在
     *
     * @param string $tableShort 不含前缀的表名
     * @param string $indexName
     * @return bool
     */
    public static function tableIndexExists($tableShort, $indexName)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $stmt = $pdo->prepare('SHOW INDEX FROM `' . $table . '` WHERE `Key_name` = ?');
            $stmt->execute(array($indexName));
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 清理从旧「文件管理系统」误迁入的表/配置残留（幂等）
     *
     * @return void
     */
    public static function purgeLegacyArtifacts()
    {
        $legacyTables = array('domain', 'file_folder', 'file_item');
        foreach ($legacyTables as $short) {
            if (!self::tableExists($short)) {
                continue;
            }
            try {
                $pdo = Database::connect();
                self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . Database::table($short) . '`');
            } catch (Exception $e) {
                // 留待下次结构更新重试
            }
        }

        $legacyKeys = array(
            'storage_local_public_slug',
            'bound_domains',
            'primary_domain',
        );
        try {
            $pdo = Database::connect();
            $table = Database::table('config');
            foreach ($legacyKeys as $key) {
                $stmt = $pdo->prepare('DELETE FROM `' . $table . '` WHERE `key` = ?');
                $stmt->execute(array($key));
            }
            Config::clearCache();
        } catch (Exception $e) {
            // ignore
        }

        if (self::tableExists('mailrate') && self::tableExists('mail_code_rate_log')) {
            try {
                $pdo = Database::connect();
                self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . Database::table('mail_code_rate_log') . '`');
            } catch (Exception $e) {
                // 留待下次结构更新重试
            }
        }
    }

    /**
     * 待执行的结构更新脚本（按版本升序）
     *
     * @param string|null $maxVersion 仅返回 <= 该版本的脚本；在线更新必须传入目标版本，禁止一次跑光未来版
     * @return array
     */
    public static function getPendingFiles($maxVersion = null)
    {
        self::reconcileSchemaState();

        $dir = self::migrationsDir();
        if (!is_dir($dir)) {
            return array();
        }

        $applied = self::getAppliedVersions();
        $pending = array();
        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return array();
        }

        $maxVersion = $maxVersion !== null ? self::normalizeVersionToken($maxVersion) : null;

        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if (in_array($base, $applied, true)) {
                continue;
            }
            if ($maxVersion !== null && $maxVersion !== '' && version_compare($base, $maxVersion, '>')) {
                continue;
            }
            $pending[$base] = $file;
        }

        uksort($pending, 'version_compare');
        return $pending;
    }

    /**
     * 是否存在待执行的结构更新
     *
     * @param string|null $maxVersion
     * @return bool
     */
    public static function hasPendingMigrations($maxVersion = null)
    {
        return count(self::getPendingFiles($maxVersion)) > 0;
    }

    /**
     * 指定脚本是否尚未执行
     *
     * @param string $filename 如 1.0.15.sql
     * @return bool
     */
    public static function isMigrationPending($filename)
    {
        $version = preg_replace('/\.sql$/', '', basename($filename));
        $pending = self::getPendingFiles();
        return isset($pending[$version]);
    }

    /**
     * 已应用的结构更新版本
     *
     * @return array
     */
    public static function getAppliedVersions()
    {
        $raw = Config::get(self::CONFIG_KEY, '');
        if ($raw === '' || $raw === null) {
            return array();
        }
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
        }
        if (!is_array($data)) {
            return array();
        }

        $out = array();
        foreach ($data as $v) {
            $n = self::normalizeVersionToken($v);
            if ($n !== '' && preg_match('/^\d+\.\d+\.\d+$/', $n) && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        usort($out, 'version_compare');
        return $out;
    }

    /**
     * 标记结构更新版本已应用
     *
     * @param string $version
     * @return void
     * @throws Exception
     */
    public static function markApplied($version)
    {
        $version = self::normalizeVersionToken($version);
        if ($version === '') {
            return;
        }
        // 代码版本尚未升到该迁移版本时禁止写入（防止回退后 reconcile 再次写回假记录）
        if (defined('VS_VERSION') && version_compare($version, self::normalizeVersionToken(VS_VERSION), '>')) {
            return;
        }
        $applied = self::getAppliedVersions();
        if (!in_array($version, $applied, true)) {
            $applied[] = $version;
            self::writeAppliedVersions($applied);
        }
    }

    /**
     * 取消已应用标记（结构未就绪或代码版本回退时）
     *
     * @param string $version
     * @return void
     */
    public static function unmarkApplied($version)
    {
        $version = self::normalizeVersionToken($version);
        if ($version === '') {
            return;
        }
        $applied = self::getAppliedVersions();
        $next = array();
        foreach ($applied as $v) {
            if (self::normalizeVersionToken($v) !== $version) {
                $next[] = $v;
            }
        }
        if (count($next) === count($applied)) {
            return;
        }
        self::writeAppliedVersions($next);
    }

    /**
     * 代码版本回退时：清除 schema_migrations 中高于当前代码版本的执行记录
     * （避免回退后再次升级时因「假已执行」而跳过 SQL）
     *
     * @param string $codeVersion VS_VERSION
     * @return array 被清除的版本号列表
     */
    public static function pruneAppliedAboveCodeVersion($codeVersion)
    {
        $codeVersion = self::normalizeVersionToken($codeVersion);
        if ($codeVersion === '' || !InstallChecker::isInstalled()) {
            return array();
        }

        // 先用缓存快速判断；确有超出版本再清缓存重读并写入（避免每请求都 reload config）
        $applied = self::getAppliedVersions();
        $maybeRemove = false;
        foreach ($applied as $v) {
            if (version_compare(self::normalizeVersionToken($v), $codeVersion, '>')) {
                $maybeRemove = true;
                break;
            }
        }
        if (!$maybeRemove) {
            return array();
        }

        Config::clearCache();
        $applied = self::getAppliedVersions();
        if (count($applied) === 0) {
            return array();
        }

        $kept = array();
        $removed = array();
        foreach ($applied as $v) {
            $v = self::normalizeVersionToken($v);
            if ($v === '') {
                continue;
            }
            if (version_compare($v, $codeVersion, '>')) {
                $removed[] = $v;
            } else {
                $kept[] = $v;
            }
        }

        if (count($removed) === 0) {
            return array();
        }

        self::writeAppliedVersions($kept);
        return $removed;
    }

    /**
     * 结构未就绪时强制取消标记并执行（含区间内所有有 .sql 的版本）
     *
     * @param string $fromVersion 升级前版本（不含）
     * @param string $toVersion   升级后版本（含）
     * @return array 实际执行成功的版本号
     */
    public static function forceMigrateRange($fromVersion, $toVersion)
    {
        $fromVersion = trim((string) $fromVersion);
        $toVersion = trim((string) $toVersion);
        if ($toVersion === '') {
            return array();
        }

        $dir = self::migrationsDir();
        if (!is_dir($dir)) {
            throw new Exception('缺少 install/migrations 目录，请重新下载更新包');
        }

        $files = glob($dir . '/*.sql');
        if ($files === false) {
            $files = array();
        }

        $candidates = array();
        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if ($fromVersion !== '' && version_compare($base, $fromVersion, '<=')) {
                continue;
            }
            if (version_compare($base, $toVersion, '>')) {
                continue;
            }
            $candidates[$base] = $file;
        }
        uksort($candidates, 'version_compare');

        // 先按表结构补标 / 识别过时脚本，避免 force 路径 unmark 后误跑旧列名 SQL
        self::reconcileSchemaState();

        $appliedNow = array();
        $pdo = Database::connect();
        $prefix = Database::prefix();

        foreach ($candidates as $version => $file) {
            if (self::versionSchemaReady($version) || self::isMigrationObsolete($version)) {
                self::markApplied($version);
                continue;
            }
            // 假「已执行」记录必须先清掉再跑
            self::unmarkApplied($version);
            self::executeFile($pdo, $file, $prefix);
            if (!self::versionSchemaReady($version)) {
                // 无专用探测的旧脚本：执行成功即认
                if (!self::hasSchemaProbe($version)) {
                    self::markApplied($version);
                    $appliedNow[] = $version;
                    continue;
                }
                throw new Exception(
                    '版本 v' . $version . ' 数据库结构更新后校验失败（表/字段未就绪）'
                );
            }
            self::markApplied($version);
            $appliedNow[] = $version;
        }

        Config::clearCache();
        return $appliedNow;
    }

    /**
     * 13.26.48：用户订阅 JSON、站点开关与邮件开关。待发不落库，不建队列表。
     *
     * @return void
     */
    public static function ensureSubscribe48()
    {
        if (!self::tableExists('user')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $user = Database::table('user');
            if (!self::tableColumnExists('user', 'subjson')) {
                $pdo->exec(
                    'ALTER TABLE `' . $user . '` ADD COLUMN `subjson` text NULL '
                    . 'COMMENT \'订阅开关JSON（apistatus：接口上架下架维护）\' AFTER `lastcheckin`'
                );
            }
            $cfg = Database::table('config');
            $pdo->exec(
                'INSERT IGNORE INTO `' . $cfg . '` (`key`, `value`) VALUES '
                . '(\'proxyon\', \'0\'), (\'proxyips\', \'\'), (\'screenhubip\', \'\'), '
                . '(\'checkin_streak_mode\', \'off\'), (\'checkin_streak_value\', \'0\'), '
                . '(\'subon\', \'0\'), (\'mail_notify_apistatus\', \'0\')'
            );
            Config::clearCache();
        } catch (Exception $e) {
            // 下次再试
        }
    }

    /**
     * 13.26.48：user.lastcheckin 改为 TEXT JSON，并把旧的单个日期收成 streak=1。
     * 仅显式传入迁移标志时执行；普通结构探测调用只允许读结构，不改用户数据。
     *
     * @param bool $migration 是否处于版本迁移/数据库维护路径
     * @return void
     */
    public static function ensureLastcheckinJson48($migration = false)
    {
        if (!$migration || !self::tableExists('user') || !self::tableColumnExists('user', 'lastcheckin')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $changed = false;
            $type = self::lastcheckinColumnType();
            if (in_array($type, array('date', 'datetime', 'timestamp'), true)) {
                $pdo->exec(
                    'ALTER TABLE `' . $table . '` MODIFY COLUMN `lastcheckin` text NULL '
                    . 'COMMENT \'最近签到JSON（days最多7个日期，streak为连续天数；空表示从未签到）\''
                );
                $changed = true;
                $type = 'text';
            }
            // 只转换已知的终态 TEXT；未知类型不做整列 MODIFY，留待维护入口报告未就绪。
            if ($type !== 'text') {
                return;
            }
            $updated = $pdo->exec(
                'UPDATE `' . $table . '` SET `lastcheckin` = CONCAT(\'{"days":["\', LEFT(`lastcheckin`, 10), \'"],"streak":1}\') '
                . 'WHERE `lastcheckin` IS NOT NULL AND `lastcheckin` <> \'\' AND `lastcheckin` NOT LIKE \'{%\' '
                . 'AND LEFT(`lastcheckin`, 10) REGEXP \'^[0-9]{4}-[0-9]{2}-[0-9]{2}$\''
            );
            if ((int) $updated > 0) {
                $changed = true;
            }
            if ($changed) {
                Config::clearCache();
            }
        } catch (Exception $e) {
            // 迁移就绪探测会失败，下次结构更新重试
        }
    }

    /**
     * 返回 user.lastcheckin 的数据库类型。
     *
     * @return string
     */
    private static function lastcheckinColumnType()
    {
        try {
            $pdo = Database::connect();
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName === '') {
                return '';
            }
            $stmt = $pdo->prepare(
                'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute(array($dbName, Database::table('user'), 'lastcheckin'));
            return strtolower((string) $stmt->fetchColumn());
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * lastcheckin 为 TEXT 且没有剩余旧日期值。
     *
     * @return bool
     */
    private static function lastcheckinStoresJson()
    {
        return self::lastcheckinColumnType() === 'text'
            && !self::lastcheckinLegacyDatesRemain();
    }

    /**
     * 是否仍有未转换的旧日期值。
     *
     * @return bool
     */
    private static function lastcheckinLegacyDatesRemain()
    {
        try {
            $pdo = Database::connect();
            $table = Database::table('user');
            $stmt = $pdo->query(
                'SELECT 1 FROM `' . $table . '` '
                . 'WHERE `lastcheckin` IS NOT NULL AND `lastcheckin` <> \'\' '
                . 'AND `lastcheckin` NOT LIKE \'{%\' '
                . 'AND LEFT(`lastcheckin`, 10) REGEXP \'^[0-9]{4}-[0-9]{2}-[0-9]{2}$\' '
                . 'LIMIT 1'
            );
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * 该版本是否有可探测的结构就绪条件
     *
     * @param string $version
     * @return bool
     */
    public static function hasSchemaProbe($version)
    {
        $version = trim((string) $version);
        return ($version === '5.0.0'
            || $version === '7.0.0'
            || $version === '7.1.0'
            || $version === '10.12.0'
            || $version === '13.22.2'
            || $version === '13.22.5'
            || $version === '13.26.7'
            || $version === '13.26.22'
            || $version === '13.26.28'
            || $version === '13.26.29'
            || $version === '13.26.30'
            || $version === '13.26.31'
            || $version === '13.26.34'
            || $version === '13.26.35'
            || $version === '13.26.40'
            || $version === '13.26.41'
            || $version === '13.26.45'
            || $version === '13.26.46'
            || $version === '13.26.48');
    }

    /**
     * 迁移是否对当前库已过时（新装最终结构下禁止再跑旧列名脚本）
     *
     * E201：新装 database.sql 已是 needkey/aidoc 终态，但安装未写入 schema_migrations 时，
     * 升级 pending 会误跑 3.6.0（DROP api）/ 3.7.1～3.8.0（require_key）/ 3.9.0（CHANGE 旧列）。
     *
     * @param string $version
     * @return bool
     */
    public static function isMigrationObsolete($version)
    {
        $version = self::normalizeVersionToken($version);
        // 3.6.0 会 DROP 重建旧结构，现代库只要已有 needkey 或 aidoc 即禁止再跑
        if ($version === '3.6.0') {
            return self::tableColumnExists('api', 'needkey')
                || self::tableColumnExists('api', 'aidoc');
        }
        if ($version === '3.7.1' || $version === '3.8.0' || $version === '3.9.0') {
            return self::tableColumnExists('api', 'needkey')
                && !self::tableColumnExists('api', 'require_key');
        }
        // E211：link.kind 已存在时禁止再跑 5.0.0（历史包含全表 SET kind=0）
        if ($version === '5.0.0') {
            return self::tableColumnExists('link', 'kind');
        }
        return false;
    }

    /**
     * 新装完成后：将 <= 当前代码版本的全部迁移标记为已应用
     * （新装走 database.sql 终态，禁止升级时再跑历史增量）
     *
     * @param string $codeVersion
     * @return int 新标记的版本数
     */
    public static function seedAppliedUpTo($codeVersion)
    {
        $codeVersion = self::normalizeVersionToken($codeVersion);
        if ($codeVersion === '' || !InstallChecker::isInstalled()) {
            return 0;
        }

        $dir = self::migrationsDir();
        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . '/*.sql');
        if ($files === false || count($files) === 0) {
            return 0;
        }

        $applied = self::getAppliedVersions();
        $added = 0;
        foreach ($files as $file) {
            $base = basename($file, '.sql');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $base)) {
                continue;
            }
            if (version_compare($base, $codeVersion, '>')) {
                continue;
            }
            if (!in_array($base, $applied, true)) {
                $applied[] = $base;
                $added++;
            }
        }
        if ($added > 0) {
            usort($applied, 'version_compare');
            self::writeAppliedVersions($applied);
        }
        return $added;
    }

    /**
     * 表字段是否已存在
     *
     * @param string $tableShort 不含前缀的表名，如 admin
     * @param string $column
     * @return bool
     */
    public static function tableColumnExists($tableShort, $column)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $column = trim((string) $column);
            if ($column === '') {
                return false;
            }

            // 优先 INFORMATION_SCHEMA（SHOW COLUMNS + 预处理 LIKE 在部分 MySQL 上会误判）
            try {
                $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
                if ($dbName !== '') {
                    $stmt = $pdo->prepare(
                        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                         LIMIT 1'
                    );
                    $stmt->execute(array($dbName, $table, $column));
                    if ($stmt->fetch(PDO::FETCH_NUM)) {
                        return true;
                    }
                    // INFORMATION_SCHEMA 未命中时仍用 SHOW 兜底（权限/延迟）
                }
            } catch (Exception $e) {
                // fall through
            }

            $sql = 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`';
            $stmt = $pdo->query($sql);
            if (!$stmt) {
                return false;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['Field']) && strcasecmp((string) $row['Field'], $column) === 0) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 表是否已存在
     *
     * @param string $tableShort 不含前缀的表名
     * @return bool
     */
    public static function tableExists($tableShort)
    {
        try {
            $pdo = Database::connect();
            $table = Database::table($tableShort);
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
            return ($stmt && (bool) $stmt->fetch(PDO::FETCH_NUM));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 执行单个结构更新脚本
     *
     * @param PDO    $pdo
     * @param string $file
     * @param string $prefix
     * @return void
     * @throws Exception
     */
    public static function executeFile(PDO $pdo, $file, $prefix)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new Exception('结构更新脚本不可读：' . basename($file));
        }

        $version = basename($file, '.sql');
        if ($version === '1.0.20') {
            self::applyAdminAvatarUrlColumn($pdo, $prefix);
            return;
        }

        if ($version === '2.11.0') {
            self::applyMailCodeRateLogMigration($pdo, $prefix);
            return;
        }

        if ($version === '6.0.1') {
            self::backfillOrphanAdminApis($pdo, $prefix);
            return;
        }

        if ($version === '7.0.0') {
            self::applyContentTable($pdo, $prefix);
            return;
        }

        if ($version === '7.1.0') {
            self::applyContentCoverLayoutColumn($pdo, $prefix);
            return;
        }

        // 13.26.46：禁止跑脚本内多列 ADD / link MODIFY（半就绪库会报错或长时间锁表）
        // 配置种子 + 逐列/逐索引 ensure* 均在此完成（forceMigrateRange / ensureVersionSchema 共用）
        if ($version === '13.26.48') {
            self::ensureLastcheckinJson48(true);
            self::ensureSubscribe48();
            return;
        }

        if ($version === '13.26.46') {
            self::apply132646ConfigSeeds($pdo, $prefix);
            self::ensureApikeyScopeSchema();
            self::ensureApilogExtended46();
            self::dropApilogDurationms46();
            self::dropApilogRespBody46();
            self::dropApilogLowValueHeaders46();
            self::ensureFeedbackKind46();
            self::migrateLinkDevApplyToFeedback46();
            self::dropLinkUserid46();
            self::dropIncentiveGiftJobIfExists46();
            self::ensureContentAudmode46();
            self::ensureMaillog46();
            return;
        }

        $sql = file_get_contents($file);
        $sql = str_replace('{prefix}', $prefix, $sql);
        self::assertPrefixedTables($sql, $prefix, basename($file));
        $statements = DatabaseInstaller::parseSqlStatements($sql);
        if (count($statements) === 0) {
            throw new Exception('结构更新脚本无有效 SQL：' . basename($file));
        }

        foreach ($statements as $statement) {
            self::execStatement($pdo, $statement);
        }
    }

    /**
     * 校验迁移脚本是否使用了表前缀占位符（仅检查 ApiNexus 正式表）
     *
     * @param string $sql
     * @param string $prefix
     * @param string $filename
     * @return void
     * @throws Exception
     */
    public static function assertPrefixedTables($sql, $prefix, $filename)
    {
        $shortTables = array(
            'admin',
            'user',
            'config',
            'api',
            'category',
            'mailrate',
        );
        foreach ($shortTables as $table) {
            if (preg_match('/`' . preg_quote($table, '/') . '`/i', $sql)
                && !preg_match('/`' . preg_quote($prefix . $table, '/') . '`/i', $sql)) {
                throw new Exception(
                    '结构更新脚本 ' . $filename . ' 未使用 {prefix} 表前缀（检测到 `' . $table . '`）'
                );
            }
        }
    }

    /**
     * v1.0.20：安全添加 avatar 字段（已存在则跳过）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyAdminAvatarUrlColumn(PDO $pdo, $prefix)
    {
        if (self::tableColumnExists('admin', 'avatar')) {
            return;
        }

        $table = $prefix . 'admin';
        $pdo->exec(
            'ALTER TABLE `' . $table . '` ADD COLUMN `avatar` varchar(500) NOT NULL DEFAULT \'\' '
            . 'COMMENT \'自定义头像链接\' AFTER `email`'
        );
    }

    /**
     * v2.11.0：security_rate_hit → mailrate 表与字段规范化
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyMailCodeRateLogMigration(PDO $pdo, $prefix)
    {
        $oldTable = $prefix . 'security_rate_hit';
        $newTable = $prefix . 'mailrate';

        $create = 'CREATE TABLE IF NOT EXISTS `' . $newTable . '` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'主键 ID\',
            `limitkey` varchar(64) NOT NULL COMMENT \'限流键（SHA256）\',
            `createtime` int unsigned NOT NULL COMMENT \'命中时间（Unix 时间戳）\',
            PRIMARY KEY (`id`),
            KEY `idx_limitkey_createtime` (`limitkey`, `createtime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'邮箱验证码发信频率限制记录\'';
        self::execStatement($pdo, $create);

        $oldExists = false;
        try {
            $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($oldTable));
            $oldExists = (bool) $check->fetch(PDO::FETCH_NUM);
        } catch (Exception $e) {
            $oldExists = false;
        }

        if ($oldExists) {
            $countNew = (int) $pdo->query('SELECT COUNT(*) FROM `' . $newTable . '`')->fetchColumn();
            if ($countNew === 0) {
                $pdo->exec(
                    'INSERT INTO `' . $newTable . '` (`limitkey`, `createtime`)
                     SELECT `bucket`, `hit_at` FROM `' . $oldTable . '`'
                );
            }
            self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . $oldTable . '`');
        }
    }

    /**
     * 执行单条 SQL，忽略「已存在/已删除」类无害错误
     *
     * @param PDO    $pdo
     * @param string $statement
     * @return void
     * @throws Exception
     */
    public static function execStatement(PDO $pdo, $statement)
    {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (self::isIgnorableSqlError($e)) {
                return;
            }
            throw $e;
        }
    }

    /**
     * v6.0.1：历史管理员发布 userid=0 回填到全站唯一绑定身份
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function backfillOrphanAdminApis(PDO $pdo, $prefix)
    {
        $adminTable = $prefix . 'admin';
        $apiTable = $prefix . 'api';

        $stmt = $pdo->query(
            'SELECT DISTINCT `binduid` FROM `' . $adminTable . '`'
            . ' WHERE `binduid` IS NOT NULL AND `binduid` > 0 AND `status` = 1'
        );
        $uids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : array();
        if (!is_array($uids) || count($uids) !== 1) {
            return;
        }

        $uid = (int) $uids[0];
        if ($uid <= 0) {
            return;
        }

        $upd = $pdo->prepare(
            'UPDATE `' . $apiTable . '` SET `userid` = ? WHERE `userid` = 0'
        );
        $upd->execute(array($uid));
    }

    /**
     * v7.0.0：content 共用表（幂等）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyContentTable(PDO $pdo, $prefix)
    {
        if (self::tableExists('content')) {
            return;
        }
        $table = $prefix . 'content';
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT \'主键ID\',
              `kind` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'类型：0公告 1文章\',
              `title` varchar(200) NOT NULL DEFAULT \'\' COMMENT \'标题\',
              `summary` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'摘要\',
              `body` mediumtext NOT NULL COMMENT \'正文Markdown\',
              `cover` varchar(500) NOT NULL DEFAULT \'\' COMMENT \'封面图链接（文章用，公告可空）\',
              `ispinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否置顶：0否 1是\',
              `ispopup` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'是否弹窗：0否 1是（公告）\',
              `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'状态：0草稿 1已发布 2下架\',
              `userid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT \'发布者用户ID\',
              `views` int(10) unsigned NOT NULL DEFAULT 0 COMMENT \'阅读量\',
              `sort` int(11) NOT NULL DEFAULT 0 COMMENT \'排序权重（越小越前）\',
              `createtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
              `updatetime` datetime DEFAULT NULL COMMENT \'最后更新时间\',
              PRIMARY KEY (`id`),
              KEY `idx_kind_status_id` (`kind`, `status`, `id`),
              KEY `idx_kind_pin_id` (`kind`, `ispinned`, `id`),
              KEY `idx_kind_popup` (`kind`, `ispopup`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=\'公告与文章共用内容表\''
        );
    }

    /**
     * v7.1.0：content.coverlayout（幂等）
     *
     * @param PDO    $pdo
     * @param string $prefix
     * @return void
     */
    public static function applyContentCoverLayoutColumn(PDO $pdo, $prefix)
    {
        if (self::tableColumnExists('content', 'coverlayout')) {
            return;
        }
        if (!self::tableExists('content')) {
            throw new Exception('content 表不存在，请先完成 7.0.0 结构更新');
        }
        $table = $prefix . 'content';
        $pdo->exec(
            'ALTER TABLE `' . $table . '` ADD COLUMN `coverlayout` tinyint(1) NOT NULL DEFAULT 0 '
            . 'COMMENT \'封面布局：0左侧 1右侧 2背景（仅文章）\' AFTER `cover`'
        );
    }

    /**
     * 指定版本要求的库结构是否已就绪
     *
     * @param string $version
     * @return bool
     */
    public static function versionSchemaReady($version)
    {
        $version = trim((string) $version);
        if ($version === '10.12.0') {
            return self::tableExists('statday');
        }
        if ($version === '7.1.0') {
            return self::tableExists('content') && self::tableColumnExists('content', 'coverlayout');
        }
        if ($version === '7.0.0') {
            return self::tableExists('content');
        }
        if ($version === '5.0.0') {
            return self::tableColumnExists('link', 'kind');
        }
        if ($version === '13.22.2') {
            return self::tableColumnExists('user', 'pointsspent')
                && self::tableColumnExists('user', 'keycalls');
        }
        if ($version === '13.22.5') {
            return self::tableColumnExists('api', 'upmethod');
        }
        if ($version === '13.26.7') {
            return self::tableColumnExists('user', 'stat7')
                && self::tableColumnExists('apikey', 'pointsspent');
        }
        if ($version === '13.26.22') {
            $all = Config::all();
            return isset($all['apikey_max']);
        }
        if ($version === '13.26.28') {
            $all = Config::all();
            return isset($all['mail_notify_points_insufficient']);
        }
        if ($version === '13.26.29') {
            return self::tableColumnExists('user', 'ipallow');
        }
        if ($version === '13.26.30') {
            return self::tableExists('ipproxy') && self::tableColumnExists('user', 'proxystrategy');
        }
        if ($version === '13.26.31') {
            if (!self::tableColumnExists('ipproxy', 'proxycode')
                || !self::tableColumnExists('ipproxy', 'jsonhost')
                || !self::tableColumnExists('ipproxy', 'ttlmin')) {
                return false;
            }
            // install_done 由 ensureInstallDoneFlag 幂等补；有列即可视为结构就绪
            return true;
        }
        if ($version === '13.26.34') {
            return self::tableColumnExists('apilog', 'egress');
        }
        if ($version === '13.26.35') {
            return self::proxyCodeColumnIsFive() && self::proxyCodesAllFiveReady();
        }
        if ($version === '13.26.40') {
            if (!self::tableColumnExists('apikey', 'quota')
                || !self::tableColumnExists('apikey', 'quotaused')
                || !self::tableColumnExists('apikey', 'quotafallback')
                || !self::tableColumnExists('apikey', 'expiretime')) {
                return false;
            }
            $all = Config::all();
            return isset($all['mail_notify_key_quota']);
        }
        if ($version === '13.26.41') {
            return self::registerModeReady41() && self::apiorderReady41();
        }
        if ($version === '13.26.45') {
            $all = Config::all();
            return self::tableColumnExists('user', 'lastcheckin')
                && self::tableColumnExists('user', 'aggmap')
                && !self::tableExists('checkin')
                && isset($all['mail_notify_order_admin'])
                && self::tableColumnExists('statday', 'pointscost');
        }
        if ($version === '13.26.46') {
            // 令牌范围 + 转发头齐全 + 禁止列已 DROP + feedback.kind + 无残留 link 申请 + content.audmode
            return self::apilogExtended46Ready()
                && self::feedbackKind46Ready()
                && self::contentAudmode46Ready();
        }
        if ($version === '13.26.48') {
            $all = Config::all();
            return isset($all['proxyon']) && isset($all['proxyips'])
                && isset($all['screenhubip'])
                && isset($all['checkin_streak_mode'])
                && isset($all['checkin_streak_value'])
                && isset($all['subon'])
                && isset($all['mail_notify_apistatus'])
                && self::lastcheckinStoresJson()
                && self::tableColumnExists('user', 'subjson');
        }
        $file = self::migrationsDir() . '/' . $version . '.sql';
        if (!is_file($file)) {
            return true;
        }
        return in_array($version, self::getAppliedVersions(), true);
    }

    /**
     * 13.26.41：register_enabled 已是 1～4 且无旧身份子开关
     *
     * @return bool
     */
    private static function registerModeReady41()
    {
        $all = Config::all();
        if (isset($all['register_allow_user']) || isset($all['register_allow_developer'])) {
            return false;
        }
        $mode = isset($all['register_enabled']) ? (string) $all['register_enabled'] : '';
        return $mode === '1' || $mode === '2' || $mode === '3' || $mode === '4';
    }

    /**
     * 13.26.41：apiorder 配置键已落地（0/1）
     *
     * @return bool
     */
    private static function apiorderReady41()
    {
        $all = Config::all();
        if (!isset($all['apiorder'])) {
            return false;
        }
        $v = (string) $all['apiorder'];
        return $v === '0' || $v === '1';
    }

    /**
     * 确保目标版本结构已落地（幂等；在线更新 migrate 步强制对齐）
     *
     * @param string $version
     * @return array{applied:bool,msg:string}
     */
    public static function ensureVersionSchema($version)
    {
        $version = trim((string) $version);
        if ($version === '') {
            return array('applied' => false, 'msg' => '');
        }

        if (self::versionSchemaReady($version)) {
            self::markApplied($version);
            return array('applied' => false, 'msg' => '结构已就绪');
        }

        $file = self::migrationsDir() . '/' . $version . '.sql';
        if (!is_file($file)) {
            throw new Exception(
                '版本 v' . $version . ' 标记含数据库变更，但缺少 install/migrations/'
                . $version . '.sql，请重新下载更新包'
            );
        }

        $pdo = Database::connect();
        $prefix = Database::prefix();
        // 先落结构/种子，校验通过后再 mark，避免半失败却被记为已应用
        self::executeFile($pdo, $file, $prefix);
        Config::clearCache();

        if ($version === '10.12.0' && class_exists('StatDayManager')) {
            StatDayManager::resetReadyCache();
            StatDayManager::backfillLastDays(30);
        }

        // 13.26.45：含 pointscost 时从 apilog 回填近 30 日
        if ($version === '13.26.45' && class_exists('StatDayManager')) {
            StatDayManager::resetReadyCache();
            StatDayManager::backfillLastDays(30);
        }
        if ($version === '13.26.45') {
            self::ensureThemeSettingsLocal45();
        }
        // 13.26.46 结构已在 executeFile 特殊分支内 ensure* 完成

        if (!self::versionSchemaReady($version)) {
            throw new Exception('版本 v' . $version . ' 结构更新后校验失败，请手动执行数据库结构更新');
        }

        self::markApplied($version);
        return array('applied' => true, 'msg' => '已执行 ' . $version);
    }

    /**
     * 13.26.46：删除曾误建的激励任务表（产品纠偏：赠送只写 orders，无独立表）
     *
     * @return void
     */
    private static function dropIncentiveGiftJobIfExists46()
    {
        if (!self::tableExists('incentive_gift_job')) {
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('incentive_gift_job');
            self::execStatement($pdo, 'DROP TABLE IF EXISTS `' . $table . '`');
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 13.26.46：apikey.scopejson 幂等补齐
     *
     * @return void
     */
    private static function ensureApikeyScopeSchema()
    {
        if (!self::tableExists('apikey') || self::tableColumnExists('apikey', 'scopejson')) {
            if (class_exists('ApiKeyManager') && method_exists('ApiKeyManager', 'resetScopeColumnCache')) {
                ApiKeyManager::resetScopeColumnCache();
            }
            return;
        }
        try {
            $pdo = Database::connect();
            $table = Database::table('apikey');
            self::execStatement(
                $pdo,
                'ALTER TABLE `' . $table . '` '
                . 'ADD COLUMN `scopejson` text NULL '
                . 'COMMENT \'接口调用范围JSON：NULL或空串或合法空结构=不限制全部接口；非空必须为{"c":[分类ID...],"a":[接口ID...]}（JSON正整数、去重升序；整类只写c不写该类下a；并集命中即允许）；非法值或超限一律按无权调用处理\' '
                . 'AFTER `expiretime`'
            );
            if (class_exists('ApiKeyManager') && method_exists('ApiKeyManager', 'resetScopeColumnCache')) {
                ApiKeyManager::resetScopeColumnCache();
            }
        } catch (Exception $e) {
            // 下次结构更新重试
        }
    }

    /**
     * 是否为可忽略的结构更新错误（字段/索引已存在等）
     *
     * @param PDOException $e
     * @return bool
     */
    public static function isIgnorableSqlError(PDOException $e)
    {
        if (!is_array($e->errorInfo) || !isset($e->errorInfo[1])) {
            return false;
        }

        $code = (int) $e->errorInfo[1];
        return in_array($code, array(
            1050, // Table already exists
            1060, // Duplicate column name
            1061, // Duplicate key name
            1062, // Duplicate entry
            1091, // Can't DROP; check that column/key exists
        ), true);
    }
}
