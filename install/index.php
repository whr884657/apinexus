<?php
/**
 * 文件：install/index.php
 * 作用：ApiNexus Web 六步安装向导（伪静态 → 环境 → 数据库 → 建表 → 管理员 → 完成）
 * @version 13.26.18
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireNotInstalled();

$error   = '';
$success = '';
$step    = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$step    = max(1, min(6, $step));

/**
 * 安装向导展示的 Nginx 伪静态（情况 A 全文，与 nginx伪静态配置.md 一致）
 *
 * @return string
 */
function vs_install_nginx_rewrite_snippet()
{
    return "location ~ ^/(config|data)/ {\n"
        . "    deny all;\n"
        . "    return 403;\n"
        . "}\n"
        . "location ~ ^/apis/([a-z0-9]+)/?$ {\n"
        . "    rewrite ^/apis/([a-z0-9]+)/?$ /apis.php?_vs_slug=\$1 last;\n"
        . "}\n"
        . "location = /sitemap.xml {\n"
        . "    rewrite ^ /sitemap.php last;\n"
        . "}\n"
        . "location ~ ^/([a-z0-9_-]+)/([0-9]+)/?$ {\n"
        . "    rewrite ^/([a-z0-9_-]+)/([0-9]+)/?$ /\$1.php?id=\$2 last;\n"
        . "}\n"
        . "location / {\n"
        . "    try_files \$uri \$uri/ \$uri.php\$is_args\$args;\n"
        . "}";
}

// ── POST：同意开源许可（安装门禁）────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_license') {
    $agree = isset($_POST['agree']) ? (string) $_POST['agree'] : '';
    if ($agree !== '1') {
        AjaxResponse::error('请勾选同意开源许可与部署使用条款');
    }
    $_SESSION['vs_license_accepted'] = 1;
    $_SESSION['vs_license_accepted_at'] = time();
    AjaxResponse::success('已确认开源许可');
}

$licenseAccepted = vs_install_license_accepted();

// 未同意许可：禁止进入后续步骤与写库操作
if (!$licenseAccepted) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = isset($_POST['action']) ? (string) $_POST['action'] : '';
        if ($postAction !== 'accept_license') {
            if ($postAction === 'test_db') {
                AjaxResponse::error('请先阅读并同意开源许可协议');
            }
            $error = '请先阅读并同意开源许可协议';
            $step = 1;
            $_POST['action'] = '';
        }
    } elseif ($step > 1) {
        vs_redirect(vs_base_url() . '/install/?step=1');
    }
}

// ── POST：测试数据库（AJAX，不刷新页面）────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_db') {
    $dbConfig = array(
        'host'     => trim(isset($_POST['host']) ? $_POST['host'] : 'localhost'),
        'port'     => trim(isset($_POST['port']) ? $_POST['port'] : '3306'),
        'username' => trim(isset($_POST['username']) ? $_POST['username'] : ''),
        'password' => isset($_POST['password']) ? $_POST['password'] : '',
        'dbname'   => trim(isset($_POST['dbname']) ? $_POST['dbname'] : ''),
        'prefix'   => Database::TABLE_PREFIX,
        'charset'  => 'utf8mb4',
    );

    if ($dbConfig['username'] === '' || $dbConfig['dbname'] === '') {
        AjaxResponse::error('请填写数据库用户名和数据库名');
    }

    $prefixRaw = isset($_POST['redis_prefix']) ? (string) $_POST['redis_prefix'] : '';
    if (trim($prefixRaw) === '') {
        $_SESSION['vs_install_redis_prefix'] = RedisService::DEFAULT_PREFIX;
    } else {
        $norm = RedisService::normalizePrefix($prefixRaw, true);
        if ($norm === false) {
            AjaxResponse::error('缓存键前缀格式无效：仅允许字母、数字、下划线、连字符，并以冒号结尾');
        }
        $_SESSION['vs_install_redis_prefix'] = $norm;
    }

    try {
        Database::testConnection($dbConfig);
        $_SESSION['vs_install_db'] = $dbConfig;
        $_SESSION['vs_db_tested'] = true;
        AjaxResponse::success('数据库连接成功！');
    } catch (Exception $e) {
        $_SESSION['vs_db_tested'] = false;
        AjaxResponse::error($e->getMessage());
    }
}

// ── POST 处理 ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $postStep = isset($_POST['step']) ? (int) $_POST['step'] : $step;

    if ($action === 'next_step' && $postStep === 1) {
        $_SESSION['vs_nginx_ack'] = 1;
        vs_redirect(vs_base_url() . '/install/?step=2');
    }

    if ($action === 'next_step' && $postStep === 3) {
        if (!empty($_SESSION['vs_db_tested'])) {
            vs_redirect(vs_base_url() . '/install/?step=4');
        } else {
            $error = '请先测试数据库连接';
            $step = 3;
        }
    }

    if (($action === 'create_tables' || $action === 'clear_and_create') && $postStep === 4) {
        if (empty($_SESSION['vs_db_tested']) || empty($_SESSION['vs_install_db'])) {
            vs_redirect(vs_base_url() . '/install/?step=3');
        }

        $dbConfig = $_SESSION['vs_install_db'];
        $prefix = Database::TABLE_PREFIX;

        try {
            $pdo = Database::testConnection($dbConfig);
            $dbname = $dbConfig['dbname'];

            DatabaseInstaller::install(
                $pdo,
                $prefix,
                $dbname,
                $action === 'clear_and_create'
            );
            $_SESSION['vs_tables_created'] = true;
            vs_redirect(vs_base_url() . '/install/?step=5');
        } catch (Exception $e) {
            $error = '创建数据表失败：' . $e->getMessage();
            $step = 4;
        }
    }

    if ($action === 'create_admin' && $postStep === 5) {
        if (empty($_SESSION['vs_tables_created']) || empty($_SESSION['vs_install_db'])) {
            vs_redirect(vs_base_url() . '/install/?step=4');
        }

        $username = trim(isset($_POST['admin_username']) ? $_POST['admin_username'] : '');
        $password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
        $password2 = isset($_POST['admin_password2']) ? $_POST['admin_password2'] : '';
        $email = trim(isset($_POST['admin_email']) ? $_POST['admin_email'] : '');

        if ($username === '' || $password === '' || $email === '') {
            $error = '请填写完整的管理员信息';
            $step = 5;
        } elseif (strlen($username) < 3) {
            $error = '管理员用户名至少 3 个字符';
            $step = 5;
        } elseif (strlen($password) < 6) {
            $error = '管理员密码至少 6 个字符';
            $step = 5;
        } elseif ($password !== $password2) {
            $error = '两次输入的密码不一致';
            $step = 5;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
            $step = 5;
        } else {
            try {
                $dbConfig = $_SESSION['vs_install_db'];
                $pdo = Database::testConnection($dbConfig);
                $prefix = Database::TABLE_PREFIX;
                $table = $prefix . 'admin';

                $stmt = $pdo->prepare('INSERT INTO `' . $table . '` (`username`, `password`, `email`, `status`, `createtime`) VALUES (?, ?, ?, 1, NOW())');
                $stmt->execute(array($username, vs_password_hash($password), $email));

                writeDatabaseConfig($dbConfig);
                writeInstallLock();

                try {
                    if (class_exists('Config')) {
                        Config::clearCache();
                    }
                    $redisPrefix = isset($_SESSION['vs_install_redis_prefix'])
                        ? (string) $_SESSION['vs_install_redis_prefix']
                        : RedisService::DEFAULT_PREFIX;
                    $normPrefix = RedisService::normalizePrefix($redisPrefix, true);
                    if ($normPrefix === false) {
                        $normPrefix = RedisService::DEFAULT_PREFIX;
                    }
                    Config::set(RedisService::CONFIG_PREFIX, $normPrefix);

                    if (class_exists('DatabaseMigrator') && defined('VS_VERSION')) {
                        DatabaseMigrator::seedAppliedUpTo(VS_VERSION);
                    }
                } catch (Exception $seedEx) {
                    // 播种失败不阻断安装
                }

                unset(
                    $_SESSION['vs_install_db'],
                    $_SESSION['vs_db_tested'],
                    $_SESSION['vs_tables_created'],
                    $_SESSION['vs_install_redis_prefix'],
                    $_SESSION['vs_nginx_ack']
                );

                vs_redirect(vs_base_url() . '/install/?step=6');
            } catch (Exception $e) {
                $error = '安装失败：' . $e->getMessage();
                $step = 5;
            }
        }
    }
}

// ── 步骤访问控制 ──────────────────────────────────────────
if ($step >= 2 && $step < 6 && empty($_SESSION['vs_nginx_ack']) && $licenseAccepted) {
    vs_redirect(vs_base_url() . '/install/?step=1');
}
if ($step === 4 && empty($_SESSION['vs_db_tested'])) {
    vs_redirect(vs_base_url() . '/install/?step=3');
}
if ($step === 5 && empty($_SESSION['vs_tables_created'])) {
    vs_redirect(vs_base_url() . '/install/?step=4');
}
if ($step === 6 && !InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/?step=1');
}

/**
 * @param PDO    $pdo
 * @param string $prefix
 * @return array
 */
function getExistingTables(PDO $pdo, $prefix)
{
    $dbConfig = isset($_SESSION['vs_install_db']) ? $_SESSION['vs_install_db'] : array();
    $dbname = isset($dbConfig['dbname']) ? $dbConfig['dbname'] : '';
    return DatabaseInstaller::getExistingTables($pdo, $prefix, $dbname);
}

/**
 * @param array $config
 * @return void
 * @throws Exception
 */
function writeDatabaseConfig(array $config)
{
    $file = InstallChecker::configFile();
    $content = "<?php\n/**\n * 文件：config/database.php\n * 作用：MySQL 数据库连接配置（安装向导自动生成）\n * @version " . VS_VERSION . "\n */\n\nreturn " . var_export(array(
        'host'     => $config['host'],
        'port'     => $config['port'],
        'username' => $config['username'],
        'password' => $config['password'],
        'dbname'   => $config['dbname'],
        'prefix'   => Database::TABLE_PREFIX,
        'charset'  => 'utf8mb4',
    ), true) . ";\n";

    if (file_put_contents($file, $content) === false) {
        throw new Exception('无法写入 config/database.php，请检查 config 目录权限');
    }
}

/**
 * @return void
 * @throws Exception
 */
function writeInstallLock()
{
    $file = InstallChecker::lockFile();
    $content = date('Y-m-d H:i:s') . ' | ApiNexus v' . VS_VERSION . "\n";
    if (file_put_contents($file, $content) === false) {
        throw new Exception('无法写入 install.lock，请检查 config 目录权限');
    }
}

/**
 * 环境检测（合并同类项，减少检测页纵向长度）
 *
 * @return array 每项含 name/need/value/pass，可选 tags（子项标签）
 */
function runEnvironmentCheck()
{
    $checks = array();

    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
    $checks[] = array(
        'name'  => 'PHP 版本',
        'need'  => '>= 7.4（兼容 8.0 / 8.2）',
        'value' => PHP_VERSION,
        'pass'  => $phpOk,
    );

    $extensions = array(
        array('name' => 'pdo', 'tag' => 'pdo', 'label' => 'PDO'),
        array('name' => 'pdo_mysql', 'tag' => 'pdo_mysql', 'label' => 'PDO MySQL'),
        array('name' => 'redis', 'tag' => 'redis', 'label' => 'Redis'),
        array('name' => 'mbstring', 'tag' => 'mbstring', 'label' => 'mbstring'),
        array('name' => 'json', 'tag' => 'json', 'label' => 'json'),
        array('name' => 'session', 'tag' => 'session', 'label' => 'session'),
        array('name' => 'curl', 'tag' => 'curl', 'label' => 'curl'),
        array('name' => 'openssl', 'tag' => 'openssl', 'label' => 'openssl'),
        array('name' => 'zip', 'tag' => 'zip', 'label' => 'zip'),
        array('name' => 'gd', 'tag' => 'gd', 'label' => 'GD'),
    );
    $extTags = array();
    $extMissing = array();
    foreach ($extensions as $ext) {
        $loaded = extension_loaded($ext['name']);
        $extTags[] = array('label' => $ext['tag'], 'pass' => $loaded);
        if (!$loaded) {
            $extMissing[] = $ext['label'];
        }
    }
    $gdUsable = extension_loaded('gd') && function_exists('imagecreatetruecolor');
    $extTags[] = array('label' => 'GD绘图', 'pass' => $gdUsable);
    if (!$gdUsable) {
        $extMissing[] = 'GD 绘图（imagecreatetruecolor）';
    }
    $extPass = count($extMissing) === 0;
    $checks[] = array(
        'name'  => 'PHP 扩展（必选）',
        'need'  => 'pdo / pdo_mysql / redis / mbstring / json / session / curl / openssl / zip / gd（含绘图）',
        'value' => $extPass ? '全部已安装' : ('缺少：' . implode('、', $extMissing)),
        'pass'  => $extPass,
        'tags'  => $extTags,
    );

    $writableDirs = array('config', 'data');
    $writeTags = array();
    $writeFail = array();
    foreach ($writableDirs as $dir) {
        $path = VS_ROOT . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $writable = is_dir($path) && is_writable($path);
        $writeTags[] = array('label' => $dir . '/', 'pass' => $writable);
        if (!$writable) {
            $writeFail[] = $dir . '/';
        }
    }
    $writePass = count($writeFail) === 0;
    $checks[] = array(
        'name'  => '目录可写（部署必选）',
        'need'  => 'config/、data/ 可写',
        'value' => $writePass ? '全部可写' : ('不可写：' . implode('、', $writeFail)),
        'pass'  => $writePass,
        'tags'  => $writeTags,
    );

    $structureItems = array(
        array('path' => 'core', 'tag' => 'core/', 'kind' => 'dir'),
        array('path' => 'assets/css', 'tag' => 'assets/css/', 'kind' => 'dir'),
        array('path' => 'assets/js', 'tag' => 'assets/js/', 'kind' => 'dir'),
        array('path' => 'assets/img', 'tag' => 'assets/img/', 'kind' => 'dir'),
        array('path' => 'install/database.sql', 'tag' => 'database.sql', 'kind' => 'file'),
    );
    $structTags = array();
    $structFail = array();
    foreach ($structureItems as $item) {
        $full = VS_ROOT . '/' . $item['path'];
        if ($item['kind'] === 'dir') {
            $ok = is_dir($full) && is_readable($full);
        } else {
            $ok = is_file($full) && is_readable($full);
        }
        $structTags[] = array('label' => $item['tag'], 'pass' => $ok);
        if (!$ok) {
            $structFail[] = $item['tag'];
        }
    }
    $structPass = count($structFail) === 0;
    $checks[] = array(
        'name'  => '目录与安装文件',
        'need'  => 'core/、assets/* 可读；install/database.sql 可读',
        'value' => $structPass ? '结构正常' : ('异常：' . implode('、', $structFail)),
        'pass'  => $structPass,
        'tags'  => $structTags,
    );

    return $checks;
}

$dbConfig = isset($_SESSION['vs_install_db']) ? $_SESSION['vs_install_db'] : array(
    'host' => 'localhost', 'port' => '3306', 'username' => '', 'password' => '', 'dbname' => '', 'prefix' => Database::TABLE_PREFIX,
);
$dbConfig['prefix'] = Database::TABLE_PREFIX;
$dbTested = !empty($_SESSION['vs_db_tested']);
$redisPrefixValue = isset($_SESSION['vs_install_redis_prefix'])
    ? (string) $_SESSION['vs_install_redis_prefix']
    : '';
$envChecks = ($step === 2) ? runEnvironmentCheck() : array();
$envAllPass = true;
foreach ($envChecks as $c) {
    if (!$c['pass']) {
        $envAllPass = false;
    }
}

$dbHasTables = false;
$existingTables = array();
if ($step === 4 && $dbTested) {
    try {
        $pdo = Database::testConnection($dbConfig);
        $existingTables = getExistingTables($pdo, Database::TABLE_PREFIX);
        $dbHasTables = count($existingTables) > 0;
    } catch (Exception $e) {
        $error = $error ?: $e->getMessage();
    }
}

$nginxSnippet = vs_install_nginx_rewrite_snippet();
$stepTitles = array(
    1 => '伪静态配置',
    2 => '环境检测',
    3 => '数据库配置',
    4 => '创建数据表',
    5 => '管理员配置',
    6 => '安装完成',
);
$base = vs_base_url();

vs_render_head('安装向导 - 第' . $step . '步', array('install.css'));
?>

<div class="vs-page vs-install-page">
    <div class="vs-container">
        <div class="vs-install-header">
            <h1 class="vs-install-title">ApiNexus 安装向导</h1>
            <p class="vs-install-subtitle">版本 v<?php echo vs_e(VS_VERSION); ?></p>
        </div>

        <div class="vs-steps">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <?php if ($i > 1): ?>
                    <div class="vs-step__line<?php echo $i <= $step ? ' is-finished' : ''; ?>"></div>
                <?php endif; ?>
                <div class="vs-step<?php echo $i < $step ? ' is-finished' : ($i === $step ? ' is-active' : ''); ?>">
                    <div class="vs-step__circle"><span class="vs-step__num"><?php echo $i; ?></span></div>
                    <div class="vs-step__title"><?php echo vs_e($stepTitles[$i]); ?></div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="vs-card vs-install-card">
            <?php if ($error): ?>
                <div class="vs-alert vs-alert--error"><?php echo vs_e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="vs-alert vs-alert--success"><?php echo vs_e($success); ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h2 class="vs-card-title">第一步：配置 Nginx 伪静态</h2>
                <p class="vs-card-desc">请先在服务器（如宝塔：网站 → 设置 → 伪静态）粘贴下方规则并保存、重载 Nginx。未配置时，代理短链、详情路径与 <code>/sitemap.xml</code> 站点地图可能无法访问。</p>
                <?php if (!$licenseAccepted): ?>
                    <div class="vs-alert vs-alert--warning" id="licenseGateHint">请先完成弹窗中的开源许可阅读与确认，方可继续安装。</div>
                <?php endif; ?>
                <div id="installNginxPanel"<?php echo $licenseAccepted ? '' : ' hidden'; ?>>
                    <div class="vs-alert vs-alert--info">复制整段规则到站点伪静态配置中（推荐整站使用本段）。</div>
                    <div class="vs-nginx-toolbar">
                        <button type="button" class="vs-btn vs-btn--primary" id="nginxCopyBtn">一键复制</button>
                    </div>
                    <pre class="vs-nginx-code" id="nginxSnippetPre"><?php echo vs_e($nginxSnippet); ?></pre>
                    <form method="post" action="" class="vs-form" id="nginxAckForm">
                        <input type="hidden" name="step" value="1">
                        <input type="hidden" name="action" value="next_step">
                        <div class="vs-form-actions">
                            <button type="submit" class="vs-btn vs-btn--primary">我已配置，下一步</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($step === 2): ?>
                <h2 class="vs-card-title">第二步：环境检测</h2>
                <p class="vs-card-desc">检测服务器环境是否满足运行要求。须安装 <strong>MySQL（pdo_mysql）</strong>、<strong>Redis</strong>、<strong>GD</strong>（本地验证码）扩展，且 <code>config/</code>、<code>data/</code> 目录可写。同类项已合并展示，缺项见标签红色。</p>
                <div class="vs-check-list" id="installEnvChecks">
                    <?php foreach ($envChecks as $check): ?>
                        <div class="vs-check-item<?php echo $check['pass'] ? ' is-pass' : ' is-fail'; ?><?php echo !empty($check['tags']) ? ' vs-check-item--group' : ''; ?>">
                            <span class="vs-check-icon"><?php echo $check['pass'] ? '&#10003;' : '&#10007;'; ?></span>
                            <div class="vs-check-info">
                                <strong><?php echo vs_e($check['name']); ?></strong>
                                <span>要求：<?php echo vs_e($check['need']); ?> | 当前：<?php echo vs_e($check['value']); ?></span>
                                <?php if (!empty($check['tags']) && is_array($check['tags'])): ?>
                                    <div class="vs-check-tags" aria-label="分项状态">
                                        <?php foreach ($check['tags'] as $tag): ?>
                                            <span class="vs-check-tag<?php echo !empty($tag['pass']) ? ' is-pass' : ' is-fail'; ?>"><?php echo vs_e($tag['label']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($envAllPass): ?>
                    <div class="vs-form-actions" id="installEnvNext">
                        <a href="?step=3" class="vs-btn vs-btn--primary">下一步</a>
                    </div>
                <?php else: ?>
                    <div class="vs-form-actions" id="installEnvNext">
                        <span class="vs-btn vs-btn--disabled">请先解决以上问题</span>
                    </div>
                <?php endif; ?>

            <?php elseif ($step === 3): ?>
                <h2 class="vs-card-title">第三步：数据库配置</h2>
                <p class="vs-card-desc">请填写 MySQL 数据库连接信息，然后测试连接。数据表前缀固定为 <code>vs_</code>，无需配置。</p>
                <form method="post" action="" class="vs-form" id="dbForm">
                    <input type="hidden" name="step" value="3">
                    <div class="vs-form-grid">
                        <div class="vs-form-row">
                            <label class="vs-label">数据库主机</label>
                            <input type="text" name="host" class="vs-input" value="<?php echo vs_e($dbConfig['host']); ?>" placeholder="localhost">
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">端口</label>
                            <input type="text" name="port" class="vs-input" value="<?php echo vs_e($dbConfig['port']); ?>" placeholder="3306">
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">数据库用户名</label>
                            <input type="text" name="username" class="vs-input" value="<?php echo vs_e($dbConfig['username']); ?>" placeholder="root" required>
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">数据库密码</label>
                            <input type="password" name="password" class="vs-input" value="<?php echo vs_e($dbConfig['password']); ?>" placeholder="数据库密码">
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">数据库名</label>
                            <input type="text" name="dbname" class="vs-input" value="<?php echo vs_e($dbConfig['dbname']); ?>" placeholder="apinexus" required>
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label" for="redis_prefix">缓存键前缀（可选）</label>
                            <input type="text" name="redis_prefix" id="redis_prefix" class="vs-input"
                                   value="<?php echo vs_e($redisPrefixValue === RedisService::DEFAULT_PREFIX ? '' : $redisPrefixValue); ?>"
                                   placeholder="默认 apinexus: ，例 site_a:">
                            <p class="vs-form-hint">同一台服务器部署<strong>多套</strong>本系统且共用 Redis 时<strong>必须</strong>填写互不相同的前缀，否则站点数据会串缓存。仅部署一套时可留空使用默认值。</p>
                        </div>
                    </div>
                    <div id="dbTestMessage" class="vs-alert" role="alert" hidden></div>
                    <div class="vs-form-actions" id="dbFormActions">
                        <button type="button" class="vs-btn vs-btn--primary" id="testDbBtn">测试数据库连接</button>
                        <a href="?step=4" class="vs-btn vs-btn--primary" id="dbNextBtn" style="<?php echo $dbTested ? '' : 'display:none;'; ?>">下一步</a>
                    </div>
                </form>

            <?php elseif ($step === 4): ?>
                <h2 class="vs-card-title">第四步：创建数据表</h2>
                <?php if ($dbHasTables): ?>
                    <div class="vs-alert vs-alert--warning">
                        检测到数据库中已有 <?php echo count($existingTables); ?> 张相关数据表：
                        <?php echo vs_e(implode(', ', $existingTables)); ?>
                    </div>
                    <p class="vs-card-desc">如需全新安装，请先清空现有数据表。普通「创建数据表」按钮已禁用。</p>
                    <form method="post" action="" class="vs-form" id="clearDbForm">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="action" value="clear_and_create">
                        <div class="vs-form-actions">
                            <button type="button" class="vs-btn vs-btn--disabled" disabled>创建数据表</button>
                            <button type="button" class="vs-btn vs-btn--danger" id="clearDbBtn">清空数据库并重新创建</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="vs-card-desc">数据库为空，可以直接创建 ApiNexus 所需的数据表。</p>
                    <form method="post" action="" class="vs-form">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="action" value="create_tables">
                        <div class="vs-form-actions">
                            <button type="submit" class="vs-btn vs-btn--primary">创建数据表</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php elseif ($step === 5): ?>
                <h2 class="vs-card-title">第五步：管理员配置</h2>
                <p class="vs-card-desc">请设置系统管理员账号，密码将加密存储。</p>
                <form method="post" action="" class="vs-form" id="adminForm">
                    <input type="hidden" name="step" value="5">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="vs-form-grid">
                        <div class="vs-form-row">
                            <label class="vs-label">管理员用户名</label>
                            <input type="text" name="admin_username" class="vs-input" placeholder="至少 3 个字符" required minlength="3">
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">管理员邮箱</label>
                            <input type="email" name="admin_email" class="vs-input" placeholder="admin@example.com" required>
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">管理员密码</label>
                            <input type="password" name="admin_password" class="vs-input" placeholder="至少 6 个字符" required minlength="6">
                        </div>
                        <div class="vs-form-row">
                            <label class="vs-label">确认密码</label>
                            <input type="password" name="admin_password2" class="vs-input" placeholder="再次输入密码" required minlength="6">
                        </div>
                    </div>
                    <div class="vs-form-actions">
                        <button type="submit" class="vs-btn vs-btn--primary">完成配置</button>
                    </div>
                </form>

            <?php elseif ($step === 6): ?>
                <div class="vs-success-block">
                    <div class="vs-success-icon">&#10003;</div>
                    <h2 class="vs-card-title">安装完成！</h2>
                    <p class="vs-card-desc">ApiNexus v<?php echo vs_e(VS_VERSION); ?> 已成功安装，您可以开始使用了。</p>
                    <div class="vs-form-actions vs-form-actions--center">
                        <a href="<?php echo vs_e($base); ?>/" class="vs-btn vs-btn--default">进入首页</a>
                        <a href="<?php echo vs_e($base); ?>/admin/login.php" class="vs-btn vs-btn--primary">进入后台</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="vs-install-footer">
            <span>ApiNexus &copy; <?php echo date('Y'); ?> v<?php echo vs_e(VS_VERSION); ?></span>
        </div>
    </div>
</div>

<?php
$licenseBoot = array(
    'accepted' => $licenseAccepted ? 1 : 0,
    'html'     => vs_license_zh_html(),
);
?>
<script>window.VS_INSTALL_LICENSE = <?php echo json_encode($licenseBoot, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php vs_render_foot(array('install.js')); ?>
