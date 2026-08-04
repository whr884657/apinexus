<?php
/**
 * 文件：admin/settings.php
 * 作用：ApiNexus 后台系统设置（站点信息、用户注册、OAuth、邮箱发信）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vs_require_secure_post();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_apilog') {
        try {
            $hotDays = isset($_POST['apilog_hot_days']) ? (int) $_POST['apilog_hot_days'] : ApiLogArchive::DEFAULT_HOT_DAYS;
            $shardRows = isset($_POST['apilog_shard_rows']) ? (int) $_POST['apilog_shard_rows'] : ApiLogArchive::DEFAULT_SHARD_ROWS;
            if ($hotDays < 1) {
                $hotDays = ApiLogArchive::DEFAULT_HOT_DAYS;
            }
            if ($hotDays > ApiLogArchive::MAX_HOT_DAYS) {
                $hotDays = ApiLogArchive::MAX_HOT_DAYS;
            }
            $shardRows = ApiLogArchive::clampShardRows($shardRows);
            Config::setMany(array(
                'apilog_detail'           => isset($_POST['apilog_detail']) ? '1' : '0',
                'apilog_archive_enabled'  => isset($_POST['apilog_archive_enabled']) ? '1' : '0',
                'apilog_hot_days'         => (string) $hotDays,
                'apilog_shard_rows'       => (string) $shardRows,
            ));
            AjaxResponse::success('日志设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'save_dashboard') {
        try {
            $interval = isset($_POST['dashboard_live_interval'])
                ? (int) $_POST['dashboard_live_interval']
                : (int) DashboardStats::LIVE_INTERVAL_DEFAULT;
            $provider = isset($_POST['panelmonitor_provider']) ? $_POST['panelmonitor_provider'] : '';
            $baseUrl = isset($_POST['panelmonitor_baseurl']) ? (string) $_POST['panelmonitor_baseurl'] : '';
            $apiKey = isset($_POST['panelmonitor_apikey']) ? (string) $_POST['panelmonitor_apikey'] : '';
            $enabledRaw = isset($_POST['panelmonitor_enabled']) ? strtolower(trim((string) $_POST['panelmonitor_enabled'])) : '';
            $enabled = ($enabledRaw === '1' || $enabledRaw === 'on' || $enabledRaw === 'true' || $enabledRaw === 'yes');
            $saved = PanelMonitor::persistConfig($provider, $baseUrl, $apiKey, $enabled, $interval);
            if ($saved !== true) {
                AjaxResponse::error(is_string($saved) ? $saved : '保存失败');
            }
            AjaxResponse::success('控制台设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'check_redis_prefix') {
        try {
            $raw = isset($_POST['redis_prefix']) ? (string) $_POST['redis_prefix'] : '';
            $result = RedisService::detectPrefixConflict($raw);
            AjaxResponse::success(
                $result['message'] !== '' ? $result['message'] : '此前缀可用',
                array(
                    'conflict' => !empty($result['conflict']) ? 1 : 0,
                    'prefix' => $result['prefix'],
                    'count' => (int) $result['count'],
                )
            );
        } catch (Exception $e) {
            AjaxResponse::error('检测失败，请稍后重试');
        }
    }

    if ($action === 'save_redis_prefix') {
        try {
            $raw = isset($_POST['redis_prefix']) ? (string) $_POST['redis_prefix'] : '';
            $force = isset($_POST['force']) && (string) $_POST['force'] === '1';
            $result = RedisService::savePrefixConfig($raw, $force);
            if (!empty($result['need_confirm'])) {
                AjaxResponse::json(array(
                    'code' => 0,
                    'msg' => $result['msg'],
                    'need_confirm' => 1,
                    'prefix' => $result['prefix'],
                ));
            }
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? $result['msg'] : '保存失败');
            }
            AjaxResponse::success($result['msg'], array(
                'prefix' => $result['prefix'],
                'deleted' => (int) $result['deleted'],
            ));
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'test_panelmonitor') {
        // E193：测试连接限流，降低管理员会话被滥用时的内网探测频率
        $admin = Auth::user();
        $aid = ($admin && isset($admin['id'])) ? (int) $admin['id'] : 0;
        if (class_exists('RateLimitStore')
            && !RateLimitStore::allow('admin:panelmonitor:test:' . $aid, 60, 8, true)
        ) {
            AjaxResponse::error('操作过于频繁，请稍后再试');
        }
        $provider = PanelMonitor::normalizeProvider(
            isset($_POST['panelmonitor_provider']) ? $_POST['panelmonitor_provider'] : ''
        );
        $baseUrl = trim(isset($_POST['panelmonitor_baseurl']) ? (string) $_POST['panelmonitor_baseurl'] : '');
        if ($baseUrl === '') {
            $baseUrl = (string) Config::get('panelmonitor_baseurl', '');
        }
        $apiKey = trim(isset($_POST['panelmonitor_apikey']) ? (string) $_POST['panelmonitor_apikey'] : '');
        if ($apiKey === '') {
            $apiKey = (string) Config::get('panelmonitor_apikey', '');
        }
        if ($baseUrl !== '') {
            $urlOk = PanelMonitor::assertSafePanelUrl($baseUrl);
            if ($urlOk !== true) {
                AjaxResponse::error($urlOk);
            }
        }
        $result = PanelMonitor::testConnection($provider, $baseUrl, $apiKey);
        if (empty($result['ok'])) {
            AjaxResponse::error(isset($result['msg']) ? (string) $result['msg'] : '连接失败');
        }
        // 测试成功后写入配置，避免「测通但未保存 / 密码框被清空」导致控制台误判未配置
        $persist = !isset($_POST['persist']) || (string) $_POST['persist'] !== '0';
        if ($persist) {
            $interval = isset($_POST['dashboard_live_interval']) ? (int) $_POST['dashboard_live_interval'] : null;
            // 测试成功即写入并启用，避免「测通未保存」或密码框被浏览器清空导致控制台误判
            $saved = PanelMonitor::persistConfig($provider, $baseUrl, $apiKey, true, $interval);
            if ($saved !== true) {
                AjaxResponse::error(is_string($saved) ? $saved : '连接成功但保存失败');
            }
            // persist 会清缓存：用本次测通快照预热，控制台打开即可命中成功态（E195）
            if (!empty($result['snapshot']) && is_array($result['snapshot'])) {
                PanelMonitor::publishSuccessSnapshot($result['snapshot']);
            }
            $msg = isset($result['msg']) ? (string) $result['msg'] : '连接成功';
            AjaxResponse::success($msg . '（已保存并启用）');
        }
        AjaxResponse::success(isset($result['msg']) ? (string) $result['msg'] : '连接成功');
    }

    if ($action === 'generate_apilog_cron_key') {
        try {
            $key = ApiLogArchive::generateCronKey();
            Config::set('apilog_cron_key', $key);
            AjaxResponse::success('计划任务密钥已生成', array(
                'cron_key' => $key,
                'cron_url' => ApiLogArchive::cronUrl(),
            ));
        } catch (Exception $e) {
            AjaxResponse::error('生成失败，请稍后重试');
        }
    }

    if ($action === 'save_site') {
        try {
            $siteName = trim(isset($_POST['site_name']) ? $_POST['site_name'] : '');
            $systemName = trim(isset($_POST['system_name']) ? $_POST['system_name'] : '');
            $navName = trim(isset($_POST['nav_name']) ? $_POST['nav_name'] : '');
            $copyrightName = trim(isset($_POST['copyright_name']) ? $_POST['copyright_name'] : '');
            $copyrightUrl = trim(isset($_POST['copyright_url']) ? $_POST['copyright_url'] : '');
            if ($siteName === '') {
                AjaxResponse::error('请填写浏览器标题名称');
            }
            if ($systemName === '') {
                $systemName = $siteName;
            }
            if ($navName === '') {
                $navName = $siteName;
            }
            if ($copyrightName === '') {
                $copyrightName = $systemName;
            }
            if ($copyrightUrl !== '' && !preg_match('#^https?://#i', $copyrightUrl)) {
                AjaxResponse::error('版权链接须以 http:// 或 https:// 开头，也可留空');
            }
            Config::setMany(array(
                'site_name'        => $siteName,
                'system_name'      => $systemName,
                'nav_name'         => $navName,
                'copyright_name'   => $copyrightName,
                'copyright_url'    => $copyrightUrl,
                'site_description' => trim(isset($_POST['site_description']) ? $_POST['site_description'] : ''),
                'site_keywords'    => trim(isset($_POST['site_keywords']) ? $_POST['site_keywords'] : ''),
                'site_favicon'     => trim(isset($_POST['site_favicon']) ? $_POST['site_favicon'] : ''),
                'site_logo'        => trim(isset($_POST['site_logo']) ? $_POST['site_logo'] : ''),
                'site_icp'         => trim(isset($_POST['site_icp']) ? $_POST['site_icp'] : ''),
                'site_gongan'      => trim(isset($_POST['site_gongan']) ? $_POST['site_gongan'] : ''),
            ));
            SiteContext::clearCache();
            AjaxResponse::success('站点设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('操作失败，请稍后重试');
        }
    }

    if ($action === 'save_ai') {
        try {
            $provider = strtolower(trim(isset($_POST['ai_provider']) ? (string) $_POST['ai_provider'] : 'openai'));
            $presets = AiConfig::providerPresets();
            if (!isset($presets[$provider])) {
                $provider = 'openai';
            }
            $baseurl = trim(isset($_POST['ai_baseurl']) ? (string) $_POST['ai_baseurl'] : '');
            if ($baseurl === '' && $provider !== 'custom') {
                $baseurl = $presets[$provider];
            }
            if ($baseurl !== '') {
                $ssrf = AiClient::assertSafeBaseUrl($baseurl);
                if ($ssrf !== true) {
                    AjaxResponse::error($ssrf);
                }
            }
            $timeout = isset($_POST['ai_timeout']) ? (int) $_POST['ai_timeout'] : 120;
            if ($timeout < 10) {
                $timeout = 10;
            }
            if ($timeout > 600) {
                $timeout = 600;
            }
            $maxLen = isset($_POST['ai_doc_maxlen']) ? (int) $_POST['ai_doc_maxlen'] : 8000;
            if ($maxLen < 1000) {
                $maxLen = 1000;
            }
            if ($maxLen > 30000) {
                $maxLen = 30000;
            }
            $codeMode = strtolower(trim(isset($_POST['ai_code_mode']) ? (string) $_POST['ai_code_mode'] : 'sequential'));
            if ($codeMode !== 'parallel') {
                $codeMode = 'sequential';
            }
            $codeConc = isset($_POST['ai_code_concurrency']) ? (int) $_POST['ai_code_concurrency'] : 3;
            if ($codeConc < 1) {
                $codeConc = 1;
            }
            if ($codeConc > 6) {
                $codeConc = 6;
            }
            Config::setMany(array(
                'ai_enabled'           => isset($_POST['ai_enabled']) ? '1' : '0',
                'ai_provider'          => $provider,
                'ai_baseurl'           => rtrim($baseurl, '/'),
                'ai_apikey'            => trim(isset($_POST['ai_apikey']) ? (string) $_POST['ai_apikey'] : ''),
                'ai_model'             => trim(isset($_POST['ai_model']) ? (string) $_POST['ai_model'] : ''),
                'ai_timeout'           => (string) $timeout,
                'ai_doc_maxlen'        => (string) $maxLen,
                'ai_api_mode'          => AiClient::normalizeApiMode(isset($_POST['ai_api_mode']) ? $_POST['ai_api_mode'] : 'auto'),
                'ai_code_mode'         => $codeMode,
                'ai_code_concurrency'  => (string) $codeConc,
            ));
            AjaxResponse::success('AI 设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'test_ai' || $action === 'list_ai_models') {
        // 立刻释放 Session：上游探测可能长达数十秒，持锁会导致整站后台 AJAX/刷新假死
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $provider = strtolower(trim(isset($_POST['ai_provider']) ? (string) $_POST['ai_provider'] : 'openai'));
            $presets = AiConfig::providerPresets();
            if (!isset($presets[$provider])) {
                $provider = 'openai';
            }
            $baseurl = trim(isset($_POST['ai_baseurl']) ? (string) $_POST['ai_baseurl'] : '');
            if ($baseurl === '' && $provider !== 'custom') {
                $baseurl = $presets[$provider];
            }
            if ($baseurl !== '') {
                $ssrf = AiClient::assertSafeBaseUrl($baseurl);
                if ($ssrf !== true) {
                    AjaxResponse::error($ssrf);
                }
            }
            $apikey = trim(isset($_POST['ai_apikey']) ? (string) $_POST['ai_apikey'] : '');
            if ($apikey === '') {
                $apikey = (string) Config::get('ai_apikey', '');
            }
            $model = trim(isset($_POST['ai_model']) ? (string) $_POST['ai_model'] : '');
            // 探测超时单独封顶，避免设置页「单片超时」把测试拖到数分钟
            $timeout = isset($_POST['ai_timeout']) ? (int) $_POST['ai_timeout'] : 30;
            if ($timeout < 10) {
                $timeout = 10;
            }
            if ($timeout > 45) {
                $timeout = 45;
            }
            $apiMode = AiClient::normalizeApiMode(isset($_POST['ai_api_mode']) ? $_POST['ai_api_mode'] : 'auto');
            $probeCfg = array(
                'baseurl'  => $baseurl,
                'apikey'   => $apikey,
                'model'    => $model,
                'timeout'  => $timeout,
                'api_mode' => $apiMode,
            );

            if ($action === 'list_ai_models') {
                $listed = AiClient::listModels($probeCfg);
                if (empty($listed['ok'])) {
                    AjaxResponse::error(isset($listed['msg']) ? (string) $listed['msg'] : '拉取失败');
                }
                AjaxResponse::success(
                    isset($listed['msg']) ? (string) $listed['msg'] : 'ok',
                    array('models' => isset($listed['models']) ? $listed['models'] : array())
                );
            }

            $result = AiClient::testConnection($probeCfg);
            if (empty($result['ok'])) {
                AjaxResponse::error(isset($result['msg']) ? (string) $result['msg'] : '连接失败');
            }
            AjaxResponse::success(
                isset($result['msg']) ? (string) $result['msg'] : '连接成功',
                array(
                    'via'   => isset($result['via']) ? $result['via'] : '',
                    'reply' => isset($result['reply']) ? $result['reply'] : '',
                )
            );
        } catch (Exception $e) {
            AjaxResponse::error('连接测试失败，请检查上游地址与密钥后重试');
        } catch (Throwable $e) {
            AjaxResponse::error('连接测试失败，请检查上游地址与密钥后重试');
        }
    }

    if ($action === 'save_register') {
        try {
            $input = isset($_POST['register_email_suffixes']) ? $_POST['register_email_suffixes'] : '';
            $suffixes = RegisterPolicy::parseSuffixInput($input);
            RegisterPolicy::saveEmailSuffixes($suffixes);
            $giftPoints = isset($_POST['register_gift_points']) ? (int) $_POST['register_gift_points'] : 0;
            if ($giftPoints < 0) {
                $giftPoints = 0;
            }
            if ($giftPoints > 1000000) {
                $giftPoints = 1000000;
            }
            Config::setMany(array(
                'register_gift_enabled' => isset($_POST['register_gift_enabled']) ? '1' : '0',
                'register_gift_points'  => (string) $giftPoints,
            ));
            AjaxResponse::success('注册设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'save_checkin') {
        try {
            $min = isset($_POST['checkin_points_min']) ? (int) $_POST['checkin_points_min'] : 10;
            $max = isset($_POST['checkin_points_max']) ? (int) $_POST['checkin_points_max'] : 30;
            if ($min < 1) {
                $min = 1;
            }
            if ($max < $min) {
                $max = $min;
            }
            if ($max > 1000000) {
                $max = 1000000;
            }
            Config::setMany(array(
                'checkin_enabled'    => isset($_POST['checkin_enabled']) ? '1' : '0',
                'checkin_points_min' => (string) $min,
                'checkin_points_max' => (string) $max,
            ));
            AjaxResponse::success('签到设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'save_oauth') {
        try {
            OAuthConfig::save(
                array(
                    'enabled' => isset($_POST['qq_enabled']) ? '1' : '',
                    'app_id'  => isset($_POST['qq_app_id']) ? $_POST['qq_app_id'] : '',
                    'app_key' => isset($_POST['qq_app_key']) ? $_POST['qq_app_key'] : '',
                ),
                array(
                    'enabled'       => isset($_POST['gitee_enabled']) ? '1' : '',
                    'client_id'     => isset($_POST['gitee_client_id']) ? $_POST['gitee_client_id'] : '',
                    'client_secret' => isset($_POST['gitee_client_secret']) ? $_POST['gitee_client_secret'] : '',
                )
            );
            AjaxResponse::success('OAuth 设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'save_site_extra') {
        try {
            Config::setMany(array(
                'site_runtime_start' => trim(isset($_POST['site_runtime_start']) ? $_POST['site_runtime_start'] : ''),
                'profile_wallpaper'  => trim(isset($_POST['profile_wallpaper']) ? $_POST['profile_wallpaper'] : ''),
                'footer_html_left'   => isset($_POST['footer_html_left']) ? (string) $_POST['footer_html_left'] : '',
                'footer_html_center' => isset($_POST['footer_html_center']) ? (string) $_POST['footer_html_center'] : '',
                'footer_html_right'  => isset($_POST['footer_html_right']) ? (string) $_POST['footer_html_right'] : '',
                'footer_qr1_enabled' => isset($_POST['footer_qr1_enabled']) ? '1' : '0',
                'footer_qr1_name'    => trim(isset($_POST['footer_qr1_name']) ? $_POST['footer_qr1_name'] : ''),
                'footer_qr1_url'     => trim(isset($_POST['footer_qr1_url']) ? $_POST['footer_qr1_url'] : ''),
                'footer_qr2_enabled' => isset($_POST['footer_qr2_enabled']) ? '1' : '0',
                'footer_qr2_name'    => trim(isset($_POST['footer_qr2_name']) ? $_POST['footer_qr2_name'] : ''),
                'footer_qr2_url'     => trim(isset($_POST['footer_qr2_url']) ? $_POST['footer_qr2_url'] : ''),
                'sponsor_qr_alipay'  => trim(isset($_POST['sponsor_qr_alipay']) ? $_POST['sponsor_qr_alipay'] : ''),
                'sponsor_qr_wechat'  => trim(isset($_POST['sponsor_qr_wechat']) ? $_POST['sponsor_qr_wechat'] : ''),
                'sponsor_qr_qq'      => trim(isset($_POST['sponsor_qr_qq']) ? $_POST['sponsor_qr_qq'] : ''),
                'home_footer_links'  => isset($_POST['home_footer_links']) ? '1' : '0',
                'api_disclaimer_on'  => isset($_POST['api_disclaimer_on']) ? '1' : '0',
                'api_disclaimer'     => isset($_POST['api_disclaimer'])
                    ? vs_decode_transport_field((string) $_POST['api_disclaimer'])
                    : '',
            ));
            SiteContext::clearCache();
            AjaxResponse::success('站点扩展设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('操作失败，请稍后重试');
        }
    }

    if ($action === 'save_iploc') {
        try {
            $auth = isset($_POST['ip_loc_auth']) ? (int) $_POST['ip_loc_auth'] : 0;
            if ($auth < 0 || $auth > 3) {
                $auth = 0;
            }
            $extrasRaw = isset($_POST['ip_loc_extras']) ? (string) $_POST['ip_loc_extras'] : '[]';
            $extras = IpLocator::parseExtras($extrasRaw);
            $url = trim(isset($_POST['ip_loc_url']) ? $_POST['ip_loc_url'] : '');
            $enabled = isset($_POST['ip_loc_enabled']) ? '1' : '0';
            $mode = isset($_POST['ip_loc_mode']) ? trim((string) $_POST['ip_loc_mode']) : 'builtin';
            if ($mode !== 'custom') {
                $mode = 'builtin';
            }
            // 内置模式只改开关与模式，保留已填自定义参数，避免切回时配置被清空
            if ($mode === 'builtin') {
                Config::setMany(array(
                    'ip_loc_enabled' => $enabled,
                    'ip_loc_mode'    => 'builtin',
                ));
                AjaxResponse::success('IP 归属地设置已保存');
            }
            if ($enabled === '1') {
                if ($url === '') {
                    AjaxResponse::error('自定义模式下请填写查询接口地址');
                }
                if (!IpLocator::assertPublicHttpUrl($url)) {
                    AjaxResponse::error('IP 解析 API 地址无效或指向内网，请使用公网 http(s) 地址');
                }
            }
            $reqMethod = isset($_POST['ip_loc_method']) ? strtolower(trim((string) $_POST['ip_loc_method'])) : 'get';
            if ($reqMethod !== 'post') {
                $reqMethod = 'get';
            }
            Config::setMany(array(
                'ip_loc_enabled'   => $enabled,
                'ip_loc_mode'      => 'custom',
                'ip_loc_url'       => $url,
                'ip_loc_method'    => $reqMethod,
                'ip_loc_ip_param'  => trim(isset($_POST['ip_loc_ip_param']) ? $_POST['ip_loc_ip_param'] : 'ip'),
                'ip_loc_auth'      => (string) $auth,
                'ip_loc_auth_name' => trim(isset($_POST['ip_loc_auth_name']) ? $_POST['ip_loc_auth_name'] : ''),
                'ip_loc_auth_value'=> trim(isset($_POST['ip_loc_auth_value']) ? $_POST['ip_loc_auth_value'] : ''),
                'ip_loc_field'     => trim(isset($_POST['ip_loc_field']) ? $_POST['ip_loc_field'] : ''),
                'ip_loc_extras'    => json_encode($extras, JSON_UNESCAPED_UNICODE),
            ));
            AjaxResponse::success('IP 归属地设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'test_iploc') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $ip = trim(isset($_POST['test_ip']) ? $_POST['test_ip'] : '');
            if ($ip === '') {
                $ip = AuthSecurity::clientIp();
            }
            // 允许用表单草稿探测（不必先保存）；未传草稿时才要求已启用并落库
            $hasDraft = isset($_POST['ip_loc_mode']) || isset($_POST['ip_loc_url']) || isset($_POST['ip_loc_enabled']);
            if (!$hasDraft && !IpLocator::enabled()) {
                AjaxResponse::error('请先启用 IP 归属地解析，或在上方填写配置后直接测试');
            }
            $draft = array();
            if ($hasDraft) {
                $mode = isset($_POST['ip_loc_mode']) ? trim((string) $_POST['ip_loc_mode']) : 'builtin';
                if ($mode !== 'custom') {
                    $mode = 'builtin';
                }
                // checkbox 未勾选时可能不传；前端会显式带上当前勾选状态
                if (isset($_POST['ip_loc_enabled'])) {
                    $enabled = ($_POST['ip_loc_enabled'] === '1' || $_POST['ip_loc_enabled'] === 'on') ? '1' : '0';
                } else {
                    $enabled = '1';
                }
                if ($enabled !== '1') {
                    AjaxResponse::error('请先勾选「启用 IP 归属地解析」再测试');
                }
                $auth = isset($_POST['ip_loc_auth']) ? (int) $_POST['ip_loc_auth'] : 0;
                if ($auth < 0 || $auth > 3) {
                    $auth = 0;
                }
                $reqMethod = isset($_POST['ip_loc_method']) ? strtolower(trim((string) $_POST['ip_loc_method'])) : 'get';
                if ($reqMethod !== 'post') {
                    $reqMethod = 'get';
                }
                $draft = array(
                    'enabled'   => '1',
                    'mode'      => $mode,
                    'url'       => trim(isset($_POST['ip_loc_url']) ? (string) $_POST['ip_loc_url'] : ''),
                    'method'    => $reqMethod,
                    'ip_param'  => trim(isset($_POST['ip_loc_ip_param']) ? (string) $_POST['ip_loc_ip_param'] : 'ip'),
                    'auth'      => $auth,
                    'auth_name' => trim(isset($_POST['ip_loc_auth_name']) ? (string) $_POST['ip_loc_auth_name'] : ''),
                    'auth_value'=> trim(isset($_POST['ip_loc_auth_value']) ? (string) $_POST['ip_loc_auth_value'] : ''),
                    'field'     => trim(isset($_POST['ip_loc_field']) ? (string) $_POST['ip_loc_field'] : ''),
                    'extras'    => isset($_POST['ip_loc_extras']) ? (string) $_POST['ip_loc_extras'] : '[]',
                );
                if ($mode === 'custom' && $draft['url'] === '') {
                    AjaxResponse::error('自定义模式下请填写查询接口地址');
                }
                if ($mode === 'custom' && !IpLocator::assertPublicHttpUrl($draft['url'])) {
                    AjaxResponse::error('IP 解析 API 地址无效或指向内网，请使用公网 http(s) 地址');
                }
            }
            $probed = IpLocator::probe($ip, $draft);
            if (empty($probed['ok'])) {
                AjaxResponse::error(isset($probed['msg']) ? (string) $probed['msg'] : ('解析失败（IP：' . $ip . '）'));
            }
            AjaxResponse::success(
                isset($probed['msg']) ? (string) $probed['msg'] : '解析成功',
                array('ip' => $ip, 'iploc' => isset($probed['iploc']) ? $probed['iploc'] : '')
            );
        } catch (Exception $e) {
            AjaxResponse::error('解析测试失败，请稍后重试');
        } catch (Throwable $e) {
            AjaxResponse::error('解析测试失败，请稍后重试');
        }
    }

    if ($action === 'save_captcha') {
        try {
            $modeAdmin = Captcha::normalizeMode(isset($_POST['captcha_mode_admin']) ? (string) $_POST['captcha_mode_admin'] : 'local');
            $modeUser = Captcha::normalizeMode(isset($_POST['captcha_mode_user']) ? (string) $_POST['captcha_mode_user'] : 'local');
            $gt4ApiIn = trim(isset($_POST['gt4_api']) ? (string) $_POST['gt4_api'] : '');
            if ($gt4ApiIn !== '') {
                $gt4ApiNorm = Geetest4Login::normalizeApiServer($gt4ApiIn);
                if ($gt4ApiNorm === '') {
                    AjaxResponse::error('第四代二次校验地址无效，仅允许官方域名且须 HTTPS');
                }
                $gt4ApiIn = $gt4ApiNorm;
            }
            $payload = array(
                'captcha_mode_admin'        => $modeAdmin,
                'captcha_mode_user'         => $modeUser,
                'captcha_mode'              => $modeUser,
                'gt3_id'                    => trim(isset($_POST['gt3_id']) ? (string) $_POST['gt3_id'] : ''),
                'gt3_key'                   => trim(isset($_POST['gt3_key']) ? (string) $_POST['gt3_key'] : ''),
                'gt4_id'                    => trim(isset($_POST['gt4_id']) ? (string) $_POST['gt4_id'] : ''),
                'gt4_key'                   => trim(isset($_POST['gt4_key']) ? (string) $_POST['gt4_key'] : ''),
                'gt4_api'                   => $gt4ApiIn,
                'captcha_on_admin_login'    => isset($_POST['captcha_on_admin_login']) ? '1' : '0',
                'captcha_on_admin_forgot'   => isset($_POST['captcha_on_admin_forgot']) ? '1' : '0',
                'captcha_on_user_login'     => isset($_POST['captcha_on_user_login']) ? '1' : '0',
                'captcha_on_user_register'  => isset($_POST['captcha_on_user_register']) ? '1' : '0',
                'captcha_on_user_forgot'    => isset($_POST['captcha_on_user_forgot']) ? '1' : '0',
            );
            Config::setMany($payload);
            AjaxResponse::success('验证码设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'save_mail') {
        try {
            Config::setMany(array(
                'mail_enabled'       => isset($_POST['mail_enabled']) ? '1' : '0',
                'mail_smtp_host'     => trim(isset($_POST['mail_smtp_host']) ? $_POST['mail_smtp_host'] : ''),
                'mail_smtp_port'     => trim(isset($_POST['mail_smtp_port']) ? $_POST['mail_smtp_port'] : '465'),
                'mail_smtp_user'     => trim(isset($_POST['mail_smtp_user']) ? $_POST['mail_smtp_user'] : ''),
                'mail_smtp_pass'     => trim(isset($_POST['mail_smtp_pass']) ? $_POST['mail_smtp_pass'] : ''),
                'mail_smtp_secure'   => trim(isset($_POST['mail_smtp_secure']) ? $_POST['mail_smtp_secure'] : 'ssl'),
                'mail_from_email'    => trim(isset($_POST['mail_from_email']) ? $_POST['mail_from_email'] : ''),
                'mail_from_name'     => trim(isset($_POST['mail_from_name']) ? $_POST['mail_from_name'] : SiteContext::siteName()),
                'mail_notify_submit'     => isset($_POST['mail_notify_submit']) ? '1' : '0',
                'mail_notify_pass'       => isset($_POST['mail_notify_pass']) ? '1' : '0',
                'mail_notify_fail'       => isset($_POST['mail_notify_fail']) ? '1' : '0',
                'mail_notify_link_apply' => isset($_POST['mail_notify_link_apply']) ? '1' : '0',
                'mail_notify_link_pass'  => isset($_POST['mail_notify_link_pass']) ? '1' : '0',
                'mail_notify_feedback'         => isset($_POST['mail_notify_feedback']) ? '1' : '0',
                'mail_notify_feedback_admin'   => isset($_POST['mail_notify_feedback_admin']) ? '1' : '0',
                'mail_notify_comment_admin'    => isset($_POST['mail_notify_comment_admin']) ? '1' : '0',
                'mail_notify_comment'          => isset($_POST['mail_notify_comment']) ? '1' : '0',
            ));

            AjaxResponse::success('邮箱设置已保存');
        } catch (Exception $e) {
            AjaxResponse::error('保存失败，请稍后重试');
        }
    }

    if ($action === 'test_mail') {
        $testEmail = trim(isset($_POST['test_email']) ? $_POST['test_email'] : '');
        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            AjaxResponse::error('请输入有效的测试邮箱地址');
        }
        try {
            Mailer::send(
                $testEmail,
                SiteContext::siteName() . ' 邮箱测试',
                '<p>这是一封来自 ' . htmlspecialchars(SiteContext::siteName()) . ' 的测试邮件。</p>'
            );
            AjaxResponse::success('测试邮件已发送，请查收');
        } catch (Exception $e) {
            AjaxResponse::error('发送失败，请检查 SMTP 配置后重试');
        }
    }

    AjaxResponse::error('未知操作', 400);
}

Config::clearCache();
$vsCfg = Config::all();
$registerSuffixes = RegisterPolicy::formatSuffixInput(RegisterPolicy::getPolicy()['email_suffixes']);
$oauthCfg = OAuthConfig::getAll();
$oauthQqCallback = OAuthConfig::callbackUrl('qq');
$oauthGiteeCallback = OAuthConfig::callbackUrl('gitee');
$captchaCfg = Captcha::forAdminForm();

vs_admin_layout_start('系统设置', 'settings');
?>

<div id="settingsFlash" class="vs-settings-flash" role="alert" hidden></div>

<?php
vs_admin_accordion_start(
    'settings-site',
    '站点信息',
    '系统名称、浏览器标题、顶栏、版权、图标与备案'
);
?>
    <form method="post" action="" class="vs-form" id="siteForm" data-ajax="1">
        <input type="hidden" name="action" value="save_site">
        <div class="vs-form-row">
            <label class="vs-label">系统名称</label>
            <input type="text" name="system_name" class="vs-input" maxlength="50"
                   value="<?php echo vs_e(Config::get('system_name', '')); ?>"
                   placeholder="留空则与浏览器标题相同">
            <?php vs_render_notice('tip', '', '用于管理后台侧栏/顶栏、关于页、管理员登录与忘记密码等产品名展示。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">浏览器标题</label>
            <input type="text" name="site_name" class="vs-input" required maxlength="80"
                   value="<?php echo vs_e(Config::get('site_name', '')); ?>">
            <?php vs_render_notice('tip', '', '用于浏览器标签页标题、SEO / Open Graph 站点名等；可填较长文案。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">顶部栏名称</label>
            <input type="text" name="nav_name" class="vs-input" maxlength="40"
                   value="<?php echo vs_e(Config::get('nav_name', '')); ?>"
                   placeholder="留空则与浏览器标题相同">
            <?php vs_render_notice('tip', '', '前台各主题顶栏品牌文字；建议短名，避免顶栏挤占。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">版权名称</label>
                <input type="text" name="copyright_name" class="vs-input" maxlength="80"
                       value="<?php echo vs_e(Config::get('copyright_name', '')); ?>"
                       placeholder="留空则与系统名称相同">
                <?php vs_render_notice('tip', '', '页脚「名称 © 年份」中的名称；与浏览器标题、顶栏名称相互独立。', array('field' => true, 'compact' => true)); ?>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">版权链接（可选）</label>
                <input type="url" name="copyright_url" class="vs-input"
                       value="<?php echo vs_e(Config::get('copyright_url', '')); ?>"
                       placeholder="https:// 留空则名称不可点击">
                <?php vs_render_notice('tip', '', '填写后点击版权名称跳转；仅填名称不填链接则不跳转。', array('field' => true, 'compact' => true)); ?>
            </div>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">站点图标（Favicon）</label>
                <input type="text" name="site_favicon" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_favicon', '')); ?>"
                       placeholder="/assets/img/favicon.ico 或 https://...">
                <?php vs_render_notice('tip', '', '浏览器标签页图标。建议同时配置下方 Logo（PNG≥300px），微信/QQ 分享图优先用 Logo。', array('field' => true, 'compact' => true)); ?>
            </div>
            <div class="vs-form-row">
                <label class="vs-label">站点 Logo</label>
                <input type="text" name="site_logo" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_logo', '')); ?>"
                       placeholder="/assets/img/logo.png 或 https://...">
                <?php vs_render_notice('tip', '', '页眉展示；同时作为社交分享图（og:image），建议正方形 PNG/JPG，勿仅用 .ico', array('field' => true, 'compact' => true)); ?>
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">系统描述</label>
            <textarea name="site_description" class="vs-textarea" rows="3"><?php echo vs_e(Config::get('site_description', '')); ?></textarea>
            <?php vs_render_notice('tip', '', '搜索引擎与微信/QQ 分享摘要只认此处「系统描述」，不会用主题 Hero 标签或首页营销文案。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">关键词</label>
            <input type="text" name="site_keywords" class="vs-input"
                   value="<?php echo vs_e(Config::get('site_keywords', '')); ?>">
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">ICP 备案号</label>
                <input type="text" name="site_icp" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_icp', '')); ?>"
                       placeholder="例如 京ICP备12345678号">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">公安备案号</label>
                <input type="text" name="site_gongan" class="vs-input"
                       value="<?php echo vs_e(Config::get('site_gongan', '')); ?>"
                       placeholder="例如 京公网安备11010802012345号">
            </div>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存站点设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-register',
    '用户注册',
    '限制可注册邮箱后缀，减少临时邮箱滥用'
);
?>
    <form method="post" action="" class="vs-form" id="registerForm" data-ajax="1">
        <input type="hidden" name="action" value="save_register">
        <?php
        vs_render_notice(
            'info',
            '邮箱后缀白名单',
            '<p>每行填写一个邮箱后缀（如 <code>qq.com</code> 或 <code>@163.com</code>）。</p><p>留空表示不限制，所有邮箱均可注册。</p>',
            array('allow_html' => true, 'compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">允许的邮箱后缀</label>
            <textarea name="register_email_suffixes" class="vs-textarea" rows="5"
                      placeholder="qq.com&#10;163.com&#10;gmail.com"><?php echo vs_e($registerSuffixes); ?></textarea>
        </div>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="register_gift_enabled" value="1" <?php echo Config::get('register_gift_enabled', '0') === '1' ? 'checked' : ''; ?>>
                <span>新用户注册成功后赠送积分</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">注册赠送积分数量</label>
            <input type="number" name="register_gift_points" class="vs-input" min="0" max="1000000" step="1"
                   value="<?php echo vs_e(Config::get('register_gift_points', '100')); ?>">
            <p class="vs-form-hint">仅在上方开关开启时生效；填写 0 表示不赠送。</p>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存注册设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-captcha',
    '登录验证',
    '管理员与用户可分端选择本站图形或行为验证（三代 / 四代）'
);
?>
    <form method="post" action="" class="vs-form" id="captchaForm" data-ajax="1">
        <input type="hidden" name="action" value="save_captcha">
        <?php
        vs_render_notice(
            'info',
            '配置说明',
            '<p>管理员后台与用户端可各自选择验证方式。例如用户侧用行为验证，管理员改用本站图形，可避免第三方服务异常时管理员无法登录。</p><p>三代与四代的验证 ID / 密钥可同时保存；登录在提交时校验，注册与忘记密码在发送邮箱验证码时校验。</p>',
            array('allow_html' => true, 'compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">管理员验证方式</label>
            <select name="captcha_mode_admin" class="vs-input">
                <option value="local" <?php echo $captchaCfg['mode_admin'] === 'local' ? 'selected' : ''; ?>>本站图形验证码</option>
                <option value="gt4" <?php echo $captchaCfg['mode_admin'] === 'gt4' ? 'selected' : ''; ?>>行为验证第四代</option>
                <option value="gt3" <?php echo $captchaCfg['mode_admin'] === 'gt3' ? 'selected' : ''; ?>>行为验证第三代</option>
            </select>
            <p class="vs-form-hint">作用于管理员登录、管理员忘记密码。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">用户验证方式</label>
            <select name="captcha_mode_user" class="vs-input">
                <option value="local" <?php echo $captchaCfg['mode_user'] === 'local' ? 'selected' : ''; ?>>本站图形验证码</option>
                <option value="gt4" <?php echo $captchaCfg['mode_user'] === 'gt4' ? 'selected' : ''; ?>>行为验证第四代</option>
                <option value="gt3" <?php echo $captchaCfg['mode_user'] === 'gt3' ? 'selected' : ''; ?>>行为验证第三代</option>
            </select>
            <p class="vs-form-hint">作用于用户登录、注册、忘记密码。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">第三代 · 验证 ID</label>
            <input type="text" name="gt3_id" class="vs-input" autocomplete="off"
                   value="<?php echo vs_e($captchaCfg['gt3_id']); ?>" placeholder="第三代 gt / 验证 ID">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">第三代 · 验证密钥</label>
            <input type="text" name="gt3_key" class="vs-input" autocomplete="off"
                   value="<?php echo vs_e($captchaCfg['gt3_key']); ?>" placeholder="第三代私钥">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">第四代 · 验证 ID</label>
            <input type="text" name="gt4_id" class="vs-input" autocomplete="off"
                   value="<?php echo vs_e($captchaCfg['gt4_id']); ?>" placeholder="第四代 captcha_id">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">第四代 · 验证密钥</label>
            <input type="text" name="gt4_key" class="vs-input" autocomplete="off"
                   value="<?php echo vs_e($captchaCfg['gt4_key']); ?>" placeholder="第四代私钥">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">第四代 · 二次校验地址（可选）</label>
            <input type="text" name="gt4_api" class="vs-input"
                   value="<?php echo vs_e($captchaCfg['gt4_api']); ?>"
                   placeholder="默认 https://gcaptcha4.geetest.com（仅官方域名）">
            <p class="vs-form-hint">一般无需填写；仅允许官方域名且强制 HTTPS。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">启用场景</label>
            <p class="vs-form-hint" style="margin-top:0;">可单开、多开或全开；关闭的场景不展示验证控件。</p>
            <label class="vs-checkbox">
                <input type="checkbox" name="captcha_on_admin_login" value="1" <?php echo $captchaCfg['admin_login'] ? 'checked' : ''; ?>>
                <span>管理员登录</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="captcha_on_admin_forgot" value="1" <?php echo $captchaCfg['admin_forgot'] ? 'checked' : ''; ?>>
                <span>管理员忘记密码（发送邮箱验证码时）</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="captcha_on_user_login" value="1" <?php echo $captchaCfg['user_login'] ? 'checked' : ''; ?>>
                <span>用户登录</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="captcha_on_user_register" value="1" <?php echo $captchaCfg['user_register'] ? 'checked' : ''; ?>>
                <span>用户注册（发送邮箱验证码时）</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="captcha_on_user_forgot" value="1" <?php echo $captchaCfg['user_forgot'] ? 'checked' : ''; ?>>
                <span>用户忘记密码（发送邮箱验证码时）</span>
            </label>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存验证码设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-checkin',
    '每日签到',
    '开启后用户中心可签到领取随机积分'
);
?>
    <form method="post" action="" class="vs-form" id="checkinForm" data-ajax="1">
        <input type="hidden" name="action" value="save_checkin">
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="checkin_enabled" value="1" <?php echo Config::get('checkin_enabled', '0') === '1' ? 'checked' : ''; ?>>
                <span>启用每日签到赠送积分</span>
            </label>
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label">最低赠送积分</label>
                <input type="number" name="checkin_points_min" class="vs-input" min="1" max="1000000" step="1"
                       value="<?php echo vs_e(Config::get('checkin_points_min', '10')); ?>">
            </div>
            <div class="vs-form-col">
                <label class="vs-label">最高赠送积分</label>
                <input type="number" name="checkin_points_max" class="vs-input" min="1" max="1000000" step="1"
                       value="<?php echo vs_e(Config::get('checkin_points_max', '30')); ?>">
            </div>
        </div>
        <p class="vs-form-hint">每次签到在最低与最高之间随机赠送；每位用户每天仅可签到一次。</p>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存签到设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-oauth',
    '第三方登录',
    'QQ / Gitee 聚合登录（仅用户端）'
);
?>
    <form method="post" action="" class="vs-form vs-settings-form" id="oauthForm" data-ajax="1">
        <input type="hidden" name="action" value="save_oauth">
        <?php
        vs_render_notice(
            'info',
            '使用说明',
            '用户须先完成邮箱注册，首次使用第三方登录时需验证已有账号密码完成绑定。请将下方回调地址原样填入 QQ 互联 / Gitee 应用配置。',
            array('compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label" for="oauthQqCallback">QQ 回调地址</label>
            <div class="vs-copy-row">
                <input type="text" class="vs-input" id="oauthQqCallback" readonly
                       value="<?php echo vs_e($oauthQqCallback); ?>">
                <button type="button" class="vs-btn vs-btn--default" data-copy-from="oauthQqCallback">复制</button>
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="oauthGiteeCallback">Gitee 回调地址</label>
            <div class="vs-copy-row">
                <input type="text" class="vs-input" id="oauthGiteeCallback" readonly
                       value="<?php echo vs_e($oauthGiteeCallback); ?>">
                <button type="button" class="vs-btn vs-btn--default" data-copy-from="oauthGiteeCallback">复制</button>
            </div>
        </div>

        <hr class="vs-divider">
        <h4 class="vs-form-subtitle">QQ 互联</h4>
        <div class="vs-form-row vs-form-row--check">
            <label class="vs-checkbox">
                <input type="checkbox" name="qq_enabled" value="1" <?php echo !empty($oauthCfg['qq']['enabled']) ? 'checked' : ''; ?>>
                <span>启用 QQ 登录</span>
            </label>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">App ID</label>
                <input type="text" name="qq_app_id" class="vs-input" value="<?php echo vs_e($oauthCfg['qq']['app_id']); ?>">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">App Key</label>
                <input type="text" name="qq_app_key" class="vs-input" value="<?php echo vs_e($oauthCfg['qq']['app_key']); ?>">
            </div>
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">Gitee OAuth</h4>
        <div class="vs-form-row vs-form-row--check">
            <label class="vs-checkbox">
                <input type="checkbox" name="gitee_enabled" value="1" <?php echo !empty($oauthCfg['gitee']['enabled']) ? 'checked' : ''; ?>>
                <span>启用 Gitee 登录</span>
            </label>
        </div>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-label">Client ID</label>
                <input type="text" name="gitee_client_id" class="vs-input" value="<?php echo vs_e($oauthCfg['gitee']['client_id']); ?>">
            </div>
            <div class="vs-form-row">
                <label class="vs-label">Client Secret</label>
                <input type="text" name="gitee_client_secret" class="vs-input" value="<?php echo vs_e($oauthCfg['gitee']['client_secret']); ?>">
            </div>
        </div>

        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存 OAuth 设置</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-site-extra',
    '页脚与展示',
    '运行时间、页脚自定义栏、二维码、赞助收款码与免责声明'
);
?>
    <form method="post" action="" class="vs-form" id="siteExtraForm" data-ajax="1">
        <input type="hidden" name="action" value="save_site_extra">

        <h4 class="vs-form-subtitle">网站运行时间</h4>
        <?php
        vs_render_notice(
            'tip',
            '',
            '填写站点上线时间后，启用「显示运行时间」的前台主题会在页脚展示已运行时长。',
            array('compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">运行时间起点</label>
            <input type="text" name="site_runtime_start" class="vs-input"
                   value="<?php echo vs_e(Config::get('site_runtime_start', '')); ?>"
                   placeholder="YYYY-MM-DD HH:MM:SS">
            <?php vs_render_notice('tip', '', '格式示例：2024-01-01 00:00:00', array('field' => true, 'compact' => true)); ?>
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">个人主页默认背景</h4>
        <?php
        vs_render_notice(
            'tip',
            '',
            '所有未自定义背景的开发者主页共用此图。可填固定图片地址，也可填支持随机出图的图片接口地址。用户在账号设置里填写自己的背景后，将优先使用用户自定义。',
            array('compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">默认背景图地址</label>
            <input type="text" name="profile_wallpaper" class="vs-input"
                   value="<?php echo vs_e(Config::get('profile_wallpaper', '')); ?>"
                   placeholder="https://example.com/wallpaper.jpg" maxlength="500">
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">底部自定义栏</h4>
        <?php
        vs_render_notice(
            'tip',
            '',
            '支持 HTML / JavaScript，由管理员配置，前台原样输出。友链徽章图会自动限制高度（约 24px），避免忽大忽小。电脑端按左/中/右显示，手机端统一居中。留空则不显示对应栏位。',
            array('compact' => true)
        );
        ?>
        <div class="vs-form-row">
            <label class="vs-label">左侧内容</label>
            <textarea name="footer_html_left" class="vs-textarea" rows="4"><?php echo vs_e(Config::get('footer_html_left', '')); ?></textarea>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">中间内容</label>
            <textarea name="footer_html_center" class="vs-textarea" rows="4"><?php echo vs_e(Config::get('footer_html_center', '')); ?></textarea>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">右侧内容</label>
            <textarea name="footer_html_right" class="vs-textarea" rows="4"><?php echo vs_e(Config::get('footer_html_right', '')); ?></textarea>
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">页脚二维码</h4>
        <div class="vs-form-grid">
            <div class="vs-form-row">
                <label class="vs-checkbox">
                    <input type="checkbox" name="footer_qr1_enabled" value="1" <?php echo Config::get('footer_qr1_enabled', '') === '1' ? 'checked' : ''; ?>>
                    <span>启用二维码 1</span>
                </label>
                <label class="vs-label">名称</label>
                <input type="text" name="footer_qr1_name" class="vs-input"
                       value="<?php echo vs_e(Config::get('footer_qr1_name', '')); ?>" placeholder="例如：公众号">
                <label class="vs-label">图片 URL</label>
                <input type="text" name="footer_qr1_url" class="vs-input"
                       value="<?php echo vs_e(Config::get('footer_qr1_url', '')); ?>"
                       placeholder="/upload/qr.png 或 https://...">
            </div>
            <div class="vs-form-row">
                <label class="vs-checkbox">
                    <input type="checkbox" name="footer_qr2_enabled" value="1" <?php echo Config::get('footer_qr2_enabled', '') === '1' ? 'checked' : ''; ?>>
                    <span>启用二维码 2</span>
                </label>
                <label class="vs-label">名称</label>
                <input type="text" name="footer_qr2_name" class="vs-input"
                       value="<?php echo vs_e(Config::get('footer_qr2_name', '')); ?>" placeholder="例如：交流群">
                <label class="vs-label">图片 URL</label>
                <input type="text" name="footer_qr2_url" class="vs-input"
                       value="<?php echo vs_e(Config::get('footer_qr2_url', '')); ?>"
                       placeholder="/upload/qr2.png 或 https://...">
            </div>
        </div>
        <?php vs_render_notice('tip', '', '需同时启用主题设置中的「显示页脚二维码」才会在前台展示。', array('compact' => true)); ?>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">赞助收款码</h4>
        <?php vs_render_notice('tip', '', '填写图片 URL 后，默认主题「赞助」页会按已配置项展示（支付宝 / 微信 / QQ）。留空则不显示对应平台。', array('compact' => true)); ?>
        <div class="vs-form-row">
            <label class="vs-label" for="sponsorQrAlipay">支付宝收款码</label>
            <input type="text" name="sponsor_qr_alipay" id="sponsorQrAlipay" class="vs-input"
                   value="<?php echo vs_e(Config::get('sponsor_qr_alipay', '')); ?>"
                   placeholder="/upload/alipay.png 或 https://…">
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="sponsorQrWechat">微信收款码</label>
            <input type="text" name="sponsor_qr_wechat" id="sponsorQrWechat" class="vs-input"
                   value="<?php echo vs_e(Config::get('sponsor_qr_wechat', '')); ?>"
                   placeholder="/upload/wechat.png 或 https://…">
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="sponsorQrQq">QQ 收款码</label>
            <input type="text" name="sponsor_qr_qq" id="sponsorQrQq" class="vs-input"
                   value="<?php echo vs_e(Config::get('sponsor_qr_qq', '')); ?>"
                   placeholder="/upload/qq.png 或 https://…">
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">默认主题 · 首页页脚</h4>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="home_footer_links" value="1" <?php echo Config::get('home_footer_links', '1') !== '0' ? 'checked' : ''; ?>>
                <span>显示友情链接板块（默认开启；关闭后仍可在侧栏进入友链页）</span>
            </label>
        </div>

        <hr class="vs-divider">

        <h4 class="vs-form-subtitle">接口详情 · 免声明</h4>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="api_disclaimer_on" value="1" <?php echo Config::get('api_disclaimer_on', '0') === '1' ? 'checked' : ''; ?>>
                <span>启用免责声明内容（主题设置里可关闭展示）</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="apiDisclaimer">免责声明正文（支持 Markdown）</label>
            <textarea class="vs-input vs-textarea" name="api_disclaimer" id="apiDisclaimer" rows="5" data-vs-md="off"
                      placeholder="本站接口由第三方或平台用户提供，调用后果由调用方自行承担……"><?php echo vs_e(Config::get('api_disclaimer', '')); ?></textarea>
        </div>

        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存站点扩展</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
$ipLocExtras = IpLocator::parseExtras(Config::get('ip_loc_extras', '[]'));
$ipLocAuth = (int) Config::get('ip_loc_auth', '0');
$ipLocMode = IpLocator::provider();
$ipLocMethod = IpLocator::requestMethod();
vs_admin_accordion_start(
    'settings-iploc',
    'IP 归属地',
    '解析访客归属地，供调用日志与数据大屏飞线使用'
);
?>
    <form method="post" action="" class="vs-form" id="iplocForm" data-ajax="1">
        <input type="hidden" name="action" value="save_iploc">
        <input type="hidden" name="ip_loc_extras" id="ipLocExtrasJson" value="<?php echo vs_e(json_encode($ipLocExtras, JSON_UNESCAPED_UNICODE)); ?>">
        <?php vs_render_notice('tip', '', '可选用系统内置归属地解析，也可自行配置第三方查询接口。启用后写入调用日志，数据大屏飞线依赖此归属地。选择「自定义」时将只走你填写的接口，不再使用内置解析。', array('field' => true)); ?>
        <div class="vs-form-row vs-form-row--check">
            <label class="vs-checkbox">
                <input type="checkbox" name="ip_loc_enabled" id="ipLocEnabled" value="1" <?php echo Config::get('ip_loc_enabled', '0') === '1' ? 'checked' : ''; ?>>
                <span>启用 IP 归属地解析（写入调用日志）</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="ipLocMode">解析方式</label>
            <select class="vs-input" name="ip_loc_mode" id="ipLocMode" data-vs-pick>
                <option value="builtin"<?php echo $ipLocMode === 'builtin' ? ' selected' : ''; ?>>系统内置（推荐，无需填写下方接口）</option>
                <option value="custom"<?php echo $ipLocMode === 'custom' ? ' selected' : ''; ?>>自定义接口</option>
            </select>
            <?php vs_render_notice('tip', '', '内置方式开箱即用；若你已有稳定的归属地 API，可选自定义并填写下方参数。', array('field' => true, 'compact' => true)); ?>
            <?php vs_render_notice('tip', '', '系统内置解析仅支持 IPv4。若需要查询 IPv6 地址，需配置自定义归属地接口。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div id="ipLocCustomFields">
        <div class="vs-form-row vs-form-row--2">
            <div>
                <label class="vs-label" for="ipLocUrl">查询接口 URL</label>
                <input type="url" class="vs-input" name="ip_loc_url" id="ipLocUrl"
                       value="<?php echo vs_e(Config::get('ip_loc_url', '')); ?>"
                       placeholder="https://example.com/ip/query">
            </div>
            <div>
                <label class="vs-label" for="ipLocMethod">请求方式</label>
                <select class="vs-input" name="ip_loc_method" id="ipLocMethod" data-vs-pick>
                    <option value="get"<?php echo $ipLocMethod === 'get' ? ' selected' : ''; ?>>GET（默认）</option>
                    <option value="post"<?php echo $ipLocMethod === 'post' ? ' selected' : ''; ?>>POST</option>
                </select>
            </div>
        </div>
        <?php vs_render_notice('tip', '', '仅自定义模式需要。GET：IP 与其它参数拼到查询串；POST：以表单字段提交到正文。按上游文档选择。', array('field' => true, 'compact' => true)); ?>
        <div class="vs-form-row">
            <label class="vs-label" for="ipLocIpParam">IP 参数名</label>
            <input type="text" class="vs-input" name="ip_loc_ip_param" id="ipLocIpParam"
                   value="<?php echo vs_e(Config::get('ip_loc_ip_param', 'ip')); ?>" placeholder="ip">
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="ipLocAuth">认证方式</label>
            <select class="vs-input" name="ip_loc_auth" id="ipLocAuth" data-vs-pick>
                <option value="0"<?php echo $ipLocAuth === 0 ? ' selected' : ''; ?>>0 无需密钥</option>
                <option value="1"<?php echo $ipLocAuth === 1 ? ' selected' : ''; ?>>1 Bearer Token（Authorization: Bearer …）</option>
                <option value="2"<?php echo $ipLocAuth === 2 ? ' selected' : ''; ?>>2 Header API Key（自定义请求头，如 X-API-Key）</option>
                <option value="3"<?php echo $ipLocAuth === 3 ? ' selected' : ''; ?>>3 Query API Key（URL 查询参数携带密钥）</option>
            </select>
            <p class="vs-form-hint">按上游 API 文档选择认证方式；选 0 时无需填写下方密钥字段。</p>
        </div>
        <div class="vs-form-row" id="ipLocAuthNameRow">
            <label class="vs-label" for="ipLocAuthName">密钥参数名 / Header 名</label>
            <input type="text" class="vs-input" name="ip_loc_auth_name" id="ipLocAuthName"
                   value="<?php echo vs_e(Config::get('ip_loc_auth_name', '')); ?>" placeholder="如 X-API-Key 或 key">
        </div>
        <div class="vs-form-row" id="ipLocAuthValueRow">
            <label class="vs-label" for="ipLocAuthValue">密钥内容</label>
            <input type="text" class="vs-input" name="ip_loc_auth_value" id="ipLocAuthValue"
                   value="<?php echo vs_e(Config::get('ip_loc_auth_value', '')); ?>" placeholder="Token 或 Key">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">额外参数</label>
            <div id="ipLocExtraList" class="vs-iploc-extras"></div>
            <button type="button" class="vs-btn vs-btn--ghost" id="ipLocExtraAdd">添加额外参数</button>
            <?php vs_render_notice('tip', '', '除 IP 与认证外，还可附加查询串或请求头参数。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="ipLocField">归属地提取字段（JSON 路径）</label>
            <input type="text" class="vs-input" name="ip_loc_field" id="ipLocField"
                   value="<?php echo vs_e(Config::get('ip_loc_field', '')); ?>"
                   placeholder="如 data.city 或 result.ad_info.city">
            <?php vs_render_notice('tip', '', '按点分路径取字符串；留空则尝试常见字段名。', array('field' => true, 'compact' => true)); ?>
        </div>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存 IP 归属地设置</button>
        </div>
    </form>
    <form method="post" action="" class="vs-form" id="iplocTestForm" data-ajax="1" style="margin-top:1rem;">
        <input type="hidden" name="action" value="test_iploc">
        <div class="vs-form-row">
            <label class="vs-label" for="iplocTestIp">测试 IP</label>
            <input type="text" class="vs-input" name="test_ip" id="iplocTestIp" placeholder="留空则用当前访问 IP">
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn">测试解析</button>
        </div>
    </form>
<script>
(function () {
    var modeEl = document.getElementById('ipLocMode');
    var box = document.getElementById('ipLocCustomFields');
    function sync() {
        if (!modeEl || !box) return;
        // 仅显隐，勿 disabled：避免切回自定义时丢失未提交字段；内置保存也不覆盖这些配置
        box.style.display = modeEl.value === 'custom' ? '' : 'none';
    }
    if (modeEl) {
        modeEl.addEventListener('change', sync);
        sync();
    }
})();
</script>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-redis-prefix',
    '缓存键前缀',
    '同机多站共用 Redis 时隔离缓存，避免站点数据串读'
);
$redisPrefixCfg = RedisService::connectionConfig()['prefix'];
?>
    <form method="post" action="" class="vs-form vs-settings-form" id="redisPrefixForm" data-ajax="1">
        <input type="hidden" name="action" value="save_redis_prefix">
        <input type="hidden" name="force" id="redisPrefixForce" value="0">
        <?php vs_render_notice(
            'tip',
            '',
            '同一台服务器部署多套本系统并共用 Redis 时，必须为每套填写互不相同的键前缀。仅部署一套时可使用默认值。保存时会清空本站旧缓存，随后按新前缀重新写入。',
            array('field' => true, 'compact' => true)
        ); ?>
        <div class="vs-form-row">
            <label class="vs-label" for="settings_redis_prefix">键前缀</label>
            <input type="text" class="vs-input" id="settings_redis_prefix" name="redis_prefix"
                   value="<?php echo vs_e($redisPrefixCfg); ?>"
                   placeholder="例 site_a: 或 apinexus:"
                   autocomplete="off">
            <p class="vs-form-hint" id="redisPrefixHint">仅允许字母、数字、下划线、连字符；保存时自动补齐末尾冒号。留空则使用默认前缀。</p>
            <p class="vs-form-hint" id="redisPrefixConflict" hidden></p>
        </div>
        <div class="vs-form-actions">
            <button type="button" class="vs-btn vs-btn--default" id="redisPrefixCheckBtn">检测冲突</button>
            <button type="submit" class="vs-btn vs-btn--primary">保存键前缀</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-dashboard',
    '控制台与监控',
    '实时刷新间隔，以及宝塔 / 1Panel 服务器监控'
);
$dashLive = isset($vsCfg['dashboard_live_interval'])
    ? (int) $vsCfg['dashboard_live_interval']
    : (int) DashboardStats::LIVE_INTERVAL_DEFAULT;
if ($dashLive < (int) DashboardStats::LIVE_INTERVAL_MIN) {
    $dashLive = (int) DashboardStats::LIVE_INTERVAL_MIN;
}
if ($dashLive > (int) DashboardStats::LIVE_INTERVAL_MAX) {
    $dashLive = (int) DashboardStats::LIVE_INTERVAL_MAX;
}
$dashLiveChoices = DashboardStats::liveIntervalChoices();
if (!in_array($dashLive, $dashLiveChoices, true)) {
    // 非档位值：就近落到可选档
    $nearest = (int) DashboardStats::LIVE_INTERVAL_DEFAULT;
    $best = PHP_INT_MAX;
    foreach ($dashLiveChoices as $c) {
        $d = abs((int) $c - $dashLive);
        if ($d < $best) {
            $best = $d;
            $nearest = (int) $c;
        }
    }
    $dashLive = $nearest;
}
$pmEnabled = PanelMonitor::isEnabled();
$pmProvider = PanelMonitor::normalizeProvider(
    isset($vsCfg['panelmonitor_provider']) ? $vsCfg['panelmonitor_provider'] : ''
);
$pmBaseUrl = isset($vsCfg['panelmonitor_baseurl']) ? (string) $vsCfg['panelmonitor_baseurl'] : '';
$pmApiKey = isset($vsCfg['panelmonitor_apikey']) ? (string) $vsCfg['panelmonitor_apikey'] : '';
$pmHasKey = $pmApiKey !== '';
?>
    <form method="post" action="" class="vs-form vs-settings-form" id="dashboardForm" data-ajax="1">
        <input type="hidden" name="action" value="save_dashboard">
        <div class="vs-form-row">
            <label class="vs-label" for="dashboard_live_interval">实时刷新间隔（秒）</label>
            <select class="vs-input" id="dashboard_live_interval" name="dashboard_live_interval" data-vs-pick>
                <?php foreach ($dashLiveChoices as $i): ?>
                    <option value="<?php echo (int) $i; ?>"<?php echo $dashLive === (int) $i ? ' selected' : ''; ?>><?php echo (int) $i; ?> 秒</option>
                <?php endforeach; ?>
            </select>
            <?php vs_render_notice('tip', '', '控制台与数据大屏共用此间隔：可选 1～5 秒（高性能/无防火墙）或 10/15/20/30 秒（默认 10，更稳）。时钟本地每秒走动不发请求；KPI/最近调用/服务器/飞线按间隔刷新；趋势与 TOP 约每 6 次 live 软刷一次。切到后台标签页会自动暂停轮询（E202）。', array('field' => true, 'compact' => true)); ?>
        </div>

        <hr class="vs-divider">
        <h4 class="vs-form-subtitle">服务器监控</h4>
        <?php vs_render_notice('tip', '', '对接宝塔或 1Panel 面板接口，在控制台右侧展示 CPU、负载、内存、网络与面板版本。面板地址一般为本机或内网面板入口；请在面板侧开启 API 并放行本站服务器 IP。测试连接成功后会自动保存并启用。', array('field' => true, 'compact' => true)); ?>
        <div class="vs-form-row vs-form-row--check">
            <label class="vs-checkbox">
                <input type="hidden" name="panelmonitor_enabled" value="0">
                <input type="checkbox" name="panelmonitor_enabled" value="1" <?php echo $pmEnabled ? 'checked' : ''; ?>>
                <span>启用服务器监控</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="panelmonitor_provider">面板类型</label>
            <input type="hidden" name="panelmonitor_provider" id="panelmonitor_provider_hidden"
                   value="<?php echo vs_e($pmProvider); ?>">
            <select class="vs-input" id="panelmonitor_provider" data-vs-pick
                    aria-label="面板类型">
                <option value=""<?php echo $pmProvider === '' ? ' selected' : ''; ?>>请选择</option>
                <option value="baota"<?php echo $pmProvider === 'baota' ? ' selected' : ''; ?>>宝塔面板</option>
                <option value="onepanel"<?php echo $pmProvider === 'onepanel' ? ' selected' : ''; ?>>1Panel</option>
            </select>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="panelmonitor_baseurl">面板地址</label>
            <input type="text" class="vs-input" id="panelmonitor_baseurl" name="panelmonitor_baseurl"
                   value="<?php echo vs_e($pmBaseUrl); ?>"
                   placeholder="例如 https://127.0.0.1:8888 或 https://面板域名:端口"
                   autocomplete="off">
            <?php vs_render_notice('tip', '', '填写面板根地址（含协议与端口），不要带具体接口路径。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="panelmonitor_apikey">接口密钥</label>
            <input type="password" class="vs-input" id="panelmonitor_apikey" name="panelmonitor_apikey"
                   value=""
                   placeholder="<?php echo $pmHasKey ? '已保存，留空则保持不变' : '宝塔 API 密钥 / 1Panel API Key'; ?>"
                   autocomplete="new-password">
            <?php
            if ($pmHasKey) {
                vs_render_notice('success', '', '接口密钥已保存。更换时请填写新密钥后保存或重新测试连接。', array('field' => true, 'compact' => true));
            } else {
                vs_render_notice('tip', '', '填写后可点「测试连接」：成功将自动保存并启用监控。', array('field' => true, 'compact' => true));
            }
            ?>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存控制台设置</button>
            <button type="button" class="vs-btn vs-btn--default" id="panelMonitorTestBtn">测试连接</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-apilog',
    '调用日志',
    '详细记录、冷热归档与计划任务'
);
    $cfgHotDays = isset($vsCfg['apilog_hot_days']) ? (int) $vsCfg['apilog_hot_days'] : ApiLogArchive::DEFAULT_HOT_DAYS;
    if ($cfgHotDays < 1) {
        $cfgHotDays = ApiLogArchive::DEFAULT_HOT_DAYS;
    }
    $cfgShardRows = isset($vsCfg['apilog_shard_rows']) ? (int) $vsCfg['apilog_shard_rows'] : ApiLogArchive::DEFAULT_SHARD_ROWS;
    $cfgShardRows = ApiLogArchive::clampShardRows($cfgShardRows);
    $archiveOn = !isset($vsCfg['apilog_archive_enabled']) || $vsCfg['apilog_archive_enabled'] !== '0';
    $cronKey = isset($vsCfg['apilog_cron_key']) ? (string) $vsCfg['apilog_cron_key'] : '';
    $cronUrl = ApiLogArchive::cronUrl();
    $sqliteOk = ApiLogArchive::sqliteAvailable();
    ?>
    <form method="post" action="" class="vs-form" id="apilogForm" data-ajax="1">
        <input type="hidden" name="action" value="save_apilog">
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="apilog_detail" value="1" <?php echo (!isset($vsCfg['apilog_detail']) || $vsCfg['apilog_detail'] !== '0') ? 'checked' : ''; ?>>
                <span>记录详细调用日志（IP、UA、来源等）</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="apilog_archive_enabled" id="apilog_archive_enabled" value="1" <?php echo $archiveOn ? 'checked' : ''; ?>>
                <span>启用调用日志冷热归档</span>
            </label>
            <?php vs_render_notice('tip', '', '低配或日志量很大的站点建议开启，把超过热数据天数的日志归档到本机，减轻在线库压力且日志全部保留。若服务器性能足够强（大核数、大内存、磁盘充足），自认可长期扛住全量日志在线查询，可以关闭本项，不必做冷热分离。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row" id="apilogHotDaysRow"<?php echo $archiveOn ? '' : ' hidden'; ?>>
            <label class="vs-label" for="apilog_hot_days">热数据天数</label>
            <input type="number" class="vs-input" id="apilog_hot_days" name="apilog_hot_days" min="1" max="<?php echo (int) ApiLogArchive::MAX_HOT_DAYS; ?>"
                   value="<?php echo (int) $cfgHotDays; ?>">
            <?php vs_render_notice('tip', '', '超过该天数的日志由计划任务归档到本机，不会丢弃。', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row" id="apilogShardRowsRow"<?php echo $archiveOn ? '' : ' hidden'; ?>>
            <label class="vs-label" for="apilog_shard_rows">每个分片条数</label>
            <input type="number" class="vs-input" id="apilog_shard_rows" name="apilog_shard_rows"
                   min="<?php echo (int) ApiLogArchive::MIN_SHARD_ROWS; ?>"
                   max="<?php echo (int) ApiLogArchive::MAX_SHARD_ROWS; ?>"
                   value="<?php echo (int) $cfgShardRows; ?>">
            <?php vs_render_notice('tip', '', '每个本机分片文件写入多少条日志。默认 5000；机器性能更好可适当调大，磁盘更省文件数。', array('field' => true, 'compact' => true)); ?>
        </div>
        <?php if (!$sqliteOk): ?>
            <?php vs_render_notice('warning', '', '当前 PHP 未启用 PDO SQLite。开启冷热归档前请先安装并启用该扩展，否则计划任务无法写入冷库。', array('compact' => true)); ?>
        <?php endif; ?>
        <?php vs_render_notice('tip', '', '关闭详细日志后仍会计入各接口与密钥调用次数，适合带宽或性能有限的小站点。', array('compact' => true)); ?>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存日志设置</button>
        </div>
    </form>
    <div class="vs-form" style="margin-top:16px" id="apilogCronBox"<?php echo $archiveOn ? '' : ' hidden'; ?>>
        <div class="vs-form-row">
            <label class="vs-label">冷热归档计划任务</label>
            <input type="text" class="vs-input" id="apilogCronUrl" readonly value="<?php echo vs_e($cronUrl); ?>" placeholder="请先生成密钥">
            <?php vs_render_notice('tip', '', '启用冷热归档后，请在服务器计划任务中配置每日凌晨调用（须带密钥）。示例：0 2 * * * curl -fsS 「上方链接」', array('field' => true, 'compact' => true)); ?>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">任务密钥</label>
            <input type="text" class="vs-input" id="apilogCronKey" readonly value="<?php echo vs_e($cronKey); ?>" placeholder="尚未生成">
        </div>
        <div class="vs-form-actions">
            <button type="button" class="vs-btn vs-btn--default" id="apilogGenCronKeyBtn">生成 / 重置密钥</button>
            <button type="button" class="vs-btn vs-btn--default" id="apilogCopyCronUrlBtn">复制任务链接</button>
        </div>
    </div>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-mail',
    '邮箱发信',
    '配置 SMTP 发信参数，并发送测试邮件验证'
);
?>
    <form method="post" action="" class="vs-form" id="mailForm" data-ajax="1">
        <input type="hidden" name="action" value="save_mail">
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="mail_enabled" value="1" <?php echo (isset($vsCfg['mail_enabled']) && $vsCfg['mail_enabled'] === '1') ? 'checked' : ''; ?>>
                <span>启用邮箱发信</span>
            </label>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 服务器</label>
            <input type="text" name="mail_smtp_host" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_host']) ? $vsCfg['mail_smtp_host'] : ''); ?>">
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label">SMTP 端口</label>
                <input type="text" name="mail_smtp_port" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_port']) ? $vsCfg['mail_smtp_port'] : '465'); ?>">
            </div>
            <div class="vs-form-col">
                <label class="vs-label">加密方式</label>
                <select name="mail_smtp_secure" class="vs-input">
                    <option value="ssl" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="none" <?php echo (isset($vsCfg['mail_smtp_secure']) && $vsCfg['mail_smtp_secure'] === 'none') ? 'selected' : ''; ?>>无</option>
                </select>
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 用户名</label>
            <input type="text" name="mail_smtp_user" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_smtp_user']) ? $vsCfg['mail_smtp_user'] : ''); ?>">
        </div>
        <div class="vs-form-row">
            <label class="vs-label">SMTP 密码</label>
            <input type="text" name="mail_smtp_pass" class="vs-input"
                   value="<?php echo vs_e(isset($vsCfg['mail_smtp_pass']) ? $vsCfg['mail_smtp_pass'] : ''); ?>">
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label">发件人邮箱</label>
                <input type="email" name="mail_from_email" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_from_email']) ? $vsCfg['mail_from_email'] : ''); ?>">
            </div>
            <div class="vs-form-col">
                <label class="vs-label">发件人名称</label>
                <input type="text" name="mail_from_name" class="vs-input" value="<?php echo vs_e(isset($vsCfg['mail_from_name']) ? $vsCfg['mail_from_name'] : ''); ?>">
            </div>
        </div>
        <div class="vs-form-row">
            <label class="vs-label">业务邮件通知</label>
            <p class="vs-form-hint" style="margin-top:0;">总开关开启后，可分别控制下列通知是否发送（关闭则跳过对应邮件）。</p>
            <label class="vs-checkbox">
                <input type="checkbox" name="mail_notify_submit" value="1" <?php echo (!isset($vsCfg['mail_notify_submit']) || $vsCfg['mail_notify_submit'] === '1') ? 'checked' : ''; ?>>
                <span>开发者投稿 / 重新提交时，通知管理员</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_pass" value="1" <?php echo (!isset($vsCfg['mail_notify_pass']) || $vsCfg['mail_notify_pass'] === '1') ? 'checked' : ''; ?>>
                <span>审核通过时，通知投稿用户</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_fail" value="1" <?php echo (!isset($vsCfg['mail_notify_fail']) || $vsCfg['mail_notify_fail'] === '1') ? 'checked' : ''; ?>>
                <span>审核不通过时，通知投稿用户</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_link_apply" value="1" <?php echo (!isset($vsCfg['mail_notify_link_apply']) || $vsCfg['mail_notify_link_apply'] === '1') ? 'checked' : ''; ?>>
                <span>有新的友情链接申请时，通知管理员</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_link_pass" value="1" <?php echo (!isset($vsCfg['mail_notify_link_pass']) || $vsCfg['mail_notify_link_pass'] === '1') ? 'checked' : ''; ?>>
                <span>友情链接审核通过时，通知申请人（联系方式需含邮箱）</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_feedback_admin" value="1" <?php echo (!isset($vsCfg['mail_notify_feedback_admin']) || $vsCfg['mail_notify_feedback_admin'] === '1') ? 'checked' : ''; ?>>
                <span>有新的接口反馈时，通知管理员与接口发布者</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_feedback" value="1" <?php echo (!isset($vsCfg['mail_notify_feedback']) || $vsCfg['mail_notify_feedback'] === '1') ? 'checked' : ''; ?>>
                <span>接口反馈标记已处理时，通知提交用户</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_comment_admin" value="1" <?php echo (!isset($vsCfg['mail_notify_comment_admin']) || $vsCfg['mail_notify_comment_admin'] === '1') ? 'checked' : ''; ?>>
                <span>有新的文章评论（含引用）时，通知管理员</span>
            </label>
            <label class="vs-checkbox" style="margin-top:8px;display:flex;">
                <input type="checkbox" name="mail_notify_comment" value="1" <?php echo (!isset($vsCfg['mail_notify_comment']) || $vsCfg['mail_notify_comment'] === '1') ? 'checked' : ''; ?>>
                <span>评论被引用或被管理员回复时，通知评论者</span>
            </label>
        </div>
        <div class="vs-form-actions">
            <button type="submit" class="vs-btn vs-btn--primary">保存邮箱设置</button>
        </div>
    </form>

    <hr class="vs-divider">

    <form method="post" action="" class="vs-form vs-form--test-mail" id="testMailForm" data-ajax="1">
        <input type="hidden" name="action" value="test_mail">
        <h4 class="vs-form-subtitle">发送测试邮件</h4>
        <div class="vs-form-row vs-form-row--test-mail">
            <input type="email" name="test_email" class="vs-input" placeholder="测试邮箱地址" required>
            <button type="submit" class="vs-btn vs-btn--default">发送测试</button>
        </div>
    </form>
<?php vs_admin_accordion_end(); ?>

<?php
vs_admin_accordion_start(
    'settings-ai',
    'AI 助手',
    '对接大模型，生成接口文档与多语言代码示例'
);
$aiCfg = AiConfig::forAdminForm(false);
$aiPresets = AiConfig::providerPresets();
?>
    <form method="post" action="" class="vs-form" id="aiForm" data-ajax="1">
        <input type="hidden" name="action" value="save_ai">
        <div class="vs-form-row">
            <label class="vs-checkbox">
                <input type="checkbox" name="ai_enabled" value="1" <?php echo !empty($aiCfg['enabled']) ? 'checked' : ''; ?>>
                <span>启用 AI 生成</span>
            </label>
            <p class="vs-form-hint">关闭后，接口列表中的 AI 生成按钮将不可用。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="aiProvider">服务商</label>
            <select class="vs-input" name="ai_provider" id="aiProvider">
                <option value="openai" <?php echo $aiCfg['provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI 兼容</option>
                <option value="deepseek" <?php echo $aiCfg['provider'] === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                <option value="zhipu" <?php echo $aiCfg['provider'] === 'zhipu' ? 'selected' : ''; ?>>智谱清言</option>
                <option value="longcat" <?php echo $aiCfg['provider'] === 'longcat' ? 'selected' : ''; ?>>美团 LongCat</option>
                <option value="google" <?php echo $aiCfg['provider'] === 'google' ? 'selected' : ''; ?>>Google Gemini（OpenAI 兼容层）</option>
                <option value="custom" <?php echo $aiCfg['provider'] === 'custom' ? 'selected' : ''; ?>>自定义（Claude / 中转 / 其它）</option>
            </select>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="aiApiMode">接口协议</label>
            <select class="vs-input" name="ai_api_mode" id="aiApiMode">
                <option value="auto" <?php echo $aiCfg['api_mode'] === 'auto' ? 'selected' : ''; ?>>自动（Chat Completions → Responses）</option>
                <option value="chat" <?php echo $aiCfg['api_mode'] === 'chat' ? 'selected' : ''; ?>>仅 Chat Completions（/chat/completions）</option>
                <option value="responses" <?php echo $aiCfg['api_mode'] === 'responses' ? 'selected' : ''; ?>>仅 Responses API（/responses）</option>
            </select>
            <p class="vs-form-hint">多数厂商用 Chat；新 OpenAI / 部分网关用 Responses。选「自动」会按序尝试。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="aiBaseurl">接口根地址</label>
            <input type="text" name="ai_baseurl" id="aiBaseurl" class="vs-input"
                   value="<?php echo vs_e($aiCfg['baseurl']); ?>"
                   placeholder="例如 https://api.deepseek.com/v1"
                   data-presets="<?php echo vs_e(json_encode($aiPresets, JSON_UNESCAPED_SLASHES)); ?>">
            <p class="vs-form-hint">填到 /v1 或等价根路径即可（不要带 /chat/completions）。Claude 等请填其中转的 OpenAI 兼容根地址。</p>
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="aiApikey">API Key</label>
            <input type="password" name="ai_apikey" id="aiApikey" class="vs-input" autocomplete="off"
                   value="<?php echo vs_e($aiCfg['apikey']); ?>" placeholder="Bearer 密钥">
        </div>
        <div class="vs-form-row">
            <label class="vs-label" for="aiModel">模型名</label>
            <div class="vs-form-row--inline" style="gap:8px;align-items:stretch;">
                <div class="vs-form-col vs-form-col--grow">
                    <input type="text" name="ai_model" id="aiModel" class="vs-input" list="aiModelList"
                           value="<?php echo vs_e($aiCfg['model']); ?>"
                           placeholder="例如 deepseek-chat / glm-4 / gpt-4o-mini">
                    <datalist id="aiModelList"></datalist>
                </div>
                <div class="vs-form-col vs-form-col--btn">
                    <button type="button" class="vs-btn vs-btn--default" id="aiListModelsBtn">拉取模型</button>
                </div>
            </div>
            <div class="vs-ai-model-picker" id="aiModelPicker" hidden>
                <p class="vs-ai-model-picker__title" id="aiModelPickerTitle">可用模型</p>
                <div class="vs-ai-model-picker__list" id="aiModelPickerList"></div>
            </div>
            <p class="vs-form-hint">填写根地址与密钥后可「拉取模型」：仅 1 个会自动填入；多个则显示下方列表供点选。</p>
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label" for="aiTimeout">单次超时（秒）</label>
                <input type="number" name="ai_timeout" id="aiTimeout" class="vs-input" min="10" max="600"
                       value="<?php echo (int) $aiCfg['timeout']; ?>">
                <p class="vs-form-hint">单次请求超时（详细文档每一章 / 代码示例每一片），最长 600 秒；建议 60～180</p>
            </div>
            <div class="vs-form-col">
                <label class="vs-label" for="aiDocMaxlen">详细文档字数上限</label>
                <input type="number" name="ai_doc_maxlen" id="aiDocMaxlen" class="vs-input" min="1000" max="30000"
                       value="<?php echo (int) $aiCfg['doc_maxlen']; ?>">
            </div>
        </div>
        <div class="vs-form-row vs-form-row--inline">
            <div class="vs-form-col">
                <label class="vs-label" for="aiCodeMode">代码示例调度</label>
                <select class="vs-input" name="ai_code_mode" id="aiCodeMode">
                    <option value="sequential" <?php echo (isset($aiCfg['code_mode']) ? $aiCfg['code_mode'] : 'sequential') === 'sequential' ? 'selected' : ''; ?>>单线程（写完一片再写下一片）</option>
                    <option value="parallel" <?php echo (isset($aiCfg['code_mode']) ? $aiCfg['code_mode'] : '') === 'parallel' ? 'selected' : ''; ?>>多线程（浏览器并发多片）</option>
                </select>
                <p class="vs-form-hint">详细文档按章顺序生成。代码示例最多 3 鉴权 × 9 语言 = 27 片，亦可按鉴权单独生成 9 片。经 CDN 时建议「单线程」。</p>
            </div>
            <div class="vs-form-col">
                <label class="vs-label" for="aiCodeConcurrency">并行并发数</label>
                <input type="number" name="ai_code_concurrency" id="aiCodeConcurrency" class="vs-input" min="1" max="6"
                       value="<?php echo (int) (isset($aiCfg['code_concurrency']) ? $aiCfg['code_concurrency'] : 3); ?>">
                <p class="vs-form-hint">仅「多线程」生效，范围 1～6；CDN 环境建议不超过 2</p>
            </div>
        </div>
        <div class="vs-form-actions">
            <button type="button" class="vs-btn vs-btn--default" id="aiTestBtn">测试连接</button>
            <button type="submit" class="vs-btn vs-btn--primary">保存 AI 设置</button>
        </div>
        <p class="vs-form-hint" id="aiTestHint">测试会向 AI 发一条探测消息；上游 HTTP 成功即判定连通（正文为空也算成功）。可不先保存、可不勾选启用。</p>
    </form>
<?php vs_admin_accordion_end(); ?>

<script>window.VS_SETTINGS_BASE = <?php echo json_encode($vsBase, JSON_UNESCAPED_UNICODE); ?>;</script>

<?php vs_admin_layout_end(array('settings.js')); ?>

