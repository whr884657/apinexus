<?php
/**
 * 文件：install/index.php
 * 作用：ApiNexus Web 五步安装向导（单文件实现全部安装逻辑）
 * @version 1.0.5
 */

define('VS_ROOT', dirname(__DIR__));
require_once VS_ROOT . '/core/bootstrap.php';

InstallChecker::requireNotInstalled();

$error   = '';
$success = '';
$step    = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$step    = max(1, min(5, $step));

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

    // Step 2: 进入下一步（已测试连接）
    if ($action === 'next_step' && $postStep === 2) {
        if (!empty($_SESSION['vs_db_tested'])) {
            vs_redirect(vs_base_url() . '/install/?step=3');
        } else {
            $error = '请先测试数据库连接';
            $step = 2;
        }
    }

    // Step 3: 创建数据表
    if (($action === 'create_tables' || $action === 'clear_and_create') && $postStep === 3) {
        if (empty($_SESSION['vs_db_tested']) || empty($_SESSION['vs_install_db'])) {
            vs_redirect(vs_base_url() . '/install/?step=2');
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
            vs_redirect(vs_base_url() . '/install/?step=4');
        } catch (Exception $e) {
            $error = '创建数据表失败：' . $e->getMessage();
            $step = 3;
        }
    }

    // Step 4: 创建管理员并完成安装
    if ($action === 'create_admin' && $postStep === 4) {
        if (empty($_SESSION['vs_tables_created']) || empty($_SESSION['vs_install_db'])) {
            vs_redirect(vs_base_url() . '/install/?step=3');
        }

        $username = trim(isset($_POST['admin_username']) ? $_POST['admin_username'] : '');
        $password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
        $password2 = isset($_POST['admin_password2']) ? $_POST['admin_password2'] : '';
        $email = trim(isset($_POST['admin_email']) ? $_POST['admin_email'] : '');

        if ($username === '' || $password === '' || $email === '') {
            $error = '请填写完整的管理员信息';
            $step = 4;
        } elseif (strlen($username) < 3) {
            $error = '管理员用户名至少 3 个字符';
            $step = 4;
        } elseif (strlen($password) < 6) {
            $error = '管理员密码至少 6 个字符';
            $step = 4;
        } elseif ($password !== $password2) {
            $error = '两次输入的密码不一致';
            $step = 4;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
            $step = 4;
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

                unset($_SESSION['vs_install_db'], $_SESSION['vs_db_tested'], $_SESSION['vs_tables_created']);

                vs_redirect(vs_base_url() . '/install/?step=5');
            } catch (Exception $e) {
                $error = '安装失败：' . $e->getMessage();
                $step = 4;
            }
        }
    }
}

// ── 步骤访问控制 ──────────────────────────────────────────
if ($step === 3 && empty($_SESSION['vs_db_tested'])) {
    vs_redirect(vs_base_url() . '/install/?step=2');
}
if ($step === 4 && empty($_SESSION['vs_tables_created'])) {
    vs_redirect(vs_base_url() . '/install/?step=3');
}
if ($step === 5 && !InstallChecker::isInstalled()) {
    vs_redirect(vs_base_url() . '/install/?step=1');
}

// ── 辅助函数 ──────────────────────────────────────────────

/**
 * 获取已有数据表
 *
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
 * 写入数据库配置文件
 *
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
 * 写入安装锁
 *
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
 * 环境检测
 *
 * @return array
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
        array('name' => 'pdo', 'label' => 'PDO', 'required' => true),
        array('name' => 'pdo_mysql', 'label' => 'PDO MySQL（MySQL 数据库）', 'required' => true),
        array('name' => 'redis', 'label' => 'Redis（缓存，必选）', 'required' => true),
        array('name' => 'mbstring', 'label' => 'mbstring', 'required' => true),
        array('name' => 'json', 'label' => 'json', 'required' => true),
        array('name' => 'session', 'label' => 'session', 'required' => true),
        array('name' => 'curl', 'label' => 'curl', 'required' => true),
        array('name' => 'openssl', 'label' => 'openssl', 'required' => true),
        array('name' => 'zip', 'label' => 'zip', 'required' => true),
    );
    foreach ($extensions as $ext) {
        $loaded = extension_loaded($ext['name']);
        $checks[] = array(
            'name'  => 'PHP 扩展：' . $ext['label'],
            'need'  => '已安装（必选）',
            'value' => $loaded ? '已安装' : '未安装',
            'pass'  => $loaded,
            'required' => $ext['required'],
        );
    }

    $writableDirs = array('config', 'data');
    foreach ($writableDirs as $dir) {
        $path = VS_ROOT . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $writable = is_dir($path) && is_writable($path);
        $checks[] = array(
            'name'  => '目录可写：' . $dir . '/',
            'need'  => '可写（部署必选）',
            'value' => $writable ? '可写' : '不可写',
            'pass'  => $writable,
        );
    }

    $readableDirs = array('core', 'assets/css', 'assets/js', 'assets/img');
    foreach ($readableDirs as $dir) {
        $path = VS_ROOT . '/' . $dir;
        $readable = is_dir($path) && is_readable($path);
        $checks[] = array(
            'name'  => '目录可读：' . $dir . '/',
            'need'  => '可读',
            'value' => $readable ? '可读' : '不可读',
            'pass'  => $readable,
        );
    }

    $readableFiles = array('install/database.sql');
    foreach ($readableFiles as $file) {
        $path = VS_ROOT . '/' . $file;
        $readable = is_file($path) && is_readable($path);
        $checks[] = array(
            'name'  => '安装文件：' . $file,
            'need'  => '可读',
            'value' => $readable ? '可读' : '不可读',
            'pass'  => $readable,
        );
    }

    return $checks;
}

// ── 页面数据准备 ──────────────────────────────────────────
$dbConfig = isset($_SESSION['vs_install_db']) ? $_SESSION['vs_install_db'] : array(
    'host' => 'localhost', 'port' => '3306', 'username' => '', 'password' => '', 'dbname' => '', 'prefix' => Database::TABLE_PREFIX,
);
$dbConfig['prefix'] = Database::TABLE_PREFIX;
$dbTested = !empty($_SESSION['vs_db_tested']);
$envChecks = ($step === 1) ? runEnvironmentCheck() : array();
$envAllPass = true;
foreach ($envChecks as $c) {
    if (!$c['pass']) {
        $envAllPass = false;
    }
}

$dbHasTables = false;
$existingTables = array();
if ($step === 3 && $dbTested) {
    try {
        $pdo = Database::testConnection($dbConfig);
        $existingTables = getExistingTables($pdo, Database::TABLE_PREFIX);
        $dbHasTables = count($existingTables) > 0;
    } catch (Exception $e) {
        $error = $error ?: $e->getMessage();
    }
}

$stepTitles = array(1 => '环境检测', 2 => '数据库配置', 3 => '创建数据表', 4 => '管理员配置', 5 => '安装完成');
$base = vs_base_url();

vs_render_head('安装向导 - 第' . $step . '步', array('install.css'));
?>

<div class="vs-page vs-install-page">
    <div class="vs-container">
        <div class="vs-install-header">
            <h1 class="vs-install-title">ApiNexus 安装向导</h1>
            <p class="vs-install-subtitle">版本 v<?php echo vs_e(VS_VERSION); ?></p>
        </div>

        <!-- 步骤条 -->
        <div class="vs-steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
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
                <h2 class="vs-card-title">第一步：环境检测</h2>
                <p class="vs-card-desc">系统将检测服务器环境是否满足 ApiNexus 运行要求。须安装 <strong>MySQL（pdo_mysql）</strong> 与 <strong>Redis</strong> 扩展，且 <code>config/</code>、<code>data/</code> 目录可写。</p>
                <div class="vs-check-list">
                    <?php foreach ($envChecks as $check): ?>
                        <div class="vs-check-item<?php echo $check['pass'] ? ' is-pass' : ' is-fail'; ?>">
                            <span class="vs-check-icon"><?php echo $check['pass'] ? '&#10003;' : '&#10007;'; ?></span>
                            <div class="vs-check-info">
                                <strong><?php echo vs_e($check['name']); ?></strong>
                                <span>要求：<?php echo vs_e($check['need']); ?> | 当前：<?php echo vs_e($check['value']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($envAllPass): ?>
                    <div class="vs-form-actions">
                        <a href="?step=2" class="vs-btn vs-btn--primary">下一步</a>
                    </div>
                <?php else: ?>
                    <div class="vs-form-actions">
                        <span class="vs-btn vs-btn--disabled">请先解决以上问题</span>
                    </div>
                <?php endif; ?>

            <?php elseif ($step === 2): ?>
                <h2 class="vs-card-title">第二步：数据库配置</h2>
                <p class="vs-card-desc">请填写 MySQL 数据库连接信息，然后测试连接。数据表前缀固定为 <code>vs_</code>，无需配置。</p>
                <form method="post" action="" class="vs-form" id="dbForm">
                    <input type="hidden" name="step" value="2">
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
                    </div>
                    <div id="dbTestMessage" class="vs-alert" role="alert" hidden></div>
                    <div class="vs-form-actions" id="dbFormActions">
                        <button type="button" class="vs-btn vs-btn--primary" id="testDbBtn">测试数据库连接</button>
                        <a href="?step=3" class="vs-btn vs-btn--primary" id="dbNextBtn" style="<?php echo $dbTested ? '' : 'display:none;'; ?>">下一步</a>
                    </div>
                </form>

            <?php elseif ($step === 3): ?>
                <h2 class="vs-card-title">第三步：创建数据表</h2>
                <?php if ($dbHasTables): ?>
                    <div class="vs-alert vs-alert--warning">
                        检测到数据库中已有 <?php echo count($existingTables); ?> 张相关数据表：
                        <?php echo vs_e(implode(', ', $existingTables)); ?>
                    </div>
                    <p class="vs-card-desc">如需全新安装，请先清空现有数据表。普通「创建数据表」按钮已禁用。</p>
                    <form method="post" action="" class="vs-form" id="clearDbForm">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="action" value="clear_and_create">
                        <div class="vs-form-actions">
                            <button type="button" class="vs-btn vs-btn--disabled" disabled>创建数据表</button>
                            <button type="button" class="vs-btn vs-btn--danger" id="clearDbBtn">清空数据库并重新创建</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="vs-card-desc">数据库为空，可以直接创建 ApiNexus 所需的数据表。</p>
                    <form method="post" action="" class="vs-form">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="action" value="create_tables">
                        <div class="vs-form-actions">
                            <button type="submit" class="vs-btn vs-btn--primary">创建数据表</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php elseif ($step === 4): ?>
                <h2 class="vs-card-title">第四步：管理员配置</h2>
                <p class="vs-card-desc">请设置系统管理员账号，密码将加密存储。</p>
                <form method="post" action="" class="vs-form" id="adminForm">
                    <input type="hidden" name="step" value="4">
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

            <?php elseif ($step === 5): ?>
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

<?php vs_render_foot(array('install.js')); ?>
