<?php
/**
 * 文件：core/Updater.php
 * 作用：ApiNexus 在线更新（云端版本检测与更新包应用）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class Updater
{
    /** 默认清单 / 版本地址（Gitee 主源；实际拉取按 updateMirrors 顺序兜底） */
    const MANIFEST_URL = 'https://gitee.com/xunjinlu/apinexus/raw/main/update.json';
    const VERSION_URL  = 'https://gitee.com/xunjinlu/apinexus/raw/main/core/version.php';
    const DEFAULT_REPO = 'xunjinlu/apinexus';
    const DEFAULT_BRANCH = 'main';
    /** GitHub 镜像仓库（owner 与国内仓不同） */
    const GITHUB_REPO = 'whr884657/apinexus';
    /** GitCode 镜像仓库 */
    const GITCODE_REPO = 'xunjinlu/apinexus';

    /** 云端更新可信域名（直连 HTTPS，不依赖本地 CA 证书包） */
    const TRUSTED_UPDATE_HOSTS = array(
        'gitee.com',
        'www.gitee.com',
        'foruda.gitee.com',
        'gitcode.com',
        'www.gitcode.com',
        'raw.gitcode.com',
        'github.com',
        'www.github.com',
        'raw.githubusercontent.com',
        'objects.githubusercontent.com',
        'codeload.github.com',
    );

    /** @var string 最近一次下载/网络错误说明 */
    private static $lastError = '';

    /**
     * 本地版本号
     *
     * @return string
     */
    public static function localVersion()
    {
        return defined('VS_VERSION') ? VS_VERSION : '0.0.0';
    }

    /**
     * 更新临时目录
     *
     * @return string
     */
    public static function updateDir()
    {
        $root = VS_ROOT . '/data';
        $dir = $root . '/update';

        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
            self::writeDenyHtaccess($root);
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        self::writeDenyHtaccess($dir);

        return $dir;
    }

    /**
     * 写入禁止 Web 访问的 .htaccess（Apache）
     *
     * @param string $dir
     * @return void
     */
    public static function writeDenyHtaccess($dir)
    {
        $file = rtrim($dir, '/\\') . '/.htaccess';
        if (is_file($file)) {
            return;
        }
        @file_put_contents($file, "Deny from all\n");
    }

    /**
     * 检测是否有可用更新
     *
     * @return array
     */
    public static function checkForUpdate()
    {
        $local = self::localVersion();
        $manifest = self::fetchRemoteManifest();

        if ($manifest === null) {
            return array(
                'ok'               => false,
                'local_version'    => $local,
                'remote_version'   => '',
                'update_available' => false,
                'ahead_of_remote'  => false,
                'error'            => '无法连接云端获取版本信息，请稍后重试',
            );
        }

        $latestRemote = isset($manifest['version']) ? trim($manifest['version']) : '';
        $nextVersion = UpdateLog::nextVersionAfter($local);
        if ($nextVersion === '' && $latestRemote !== '') {
            $nextVersion = $latestRemote;
        }

        $remote = $nextVersion;
        $cmp = version_compare($local, $remote);

        $logRow = UpdateLog::getVersion($remote);
        if ($logRow !== null) {
            if (!empty($logRow['title'])) {
                $manifest['title'] = $logRow['title'];
            }
            if (!empty($logRow['date'])) {
                $manifest['release_date'] = $logRow['date'];
            }
            if (!empty($logRow['changes']) && is_array($logRow['changes'])) {
                $manifest['changes'] = $logRow['changes'];
            }
        }

        $pendingUpdates = UpdateLog::countVersionsAfter($local);

        return array(
            'ok'                   => true,
            'local_version'        => $local,
            'remote_version'       => $remote,
            'latest_remote_version'=> $latestRemote,
            'pending_updates'      => $pendingUpdates,
            'update_available'     => ($remote !== '' && $cmp < 0),
            'ahead_of_remote'      => ($latestRemote !== '' && version_compare($local, $latestRemote, '>')),
            'title'                => isset($manifest['title']) ? $manifest['title'] : '',
            'release_date'         => isset($manifest['release_date']) ? $manifest['release_date'] : '',
            'changes'              => isset($manifest['changes']) && is_array($manifest['changes']) ? $manifest['changes'] : array(),
            'has_db_changes'       => UpdateLog::versionHasDbChanges($remote),
            'repo'                 => isset($manifest['repo']) ? $manifest['repo'] : self::DEFAULT_REPO,
            'branch'               => isset($manifest['branch']) ? $manifest['branch'] : self::DEFAULT_BRANCH,
            'error'                => '',
        );
    }

    /**
     * 分步执行在线更新（download → extract → deploy → migrate）
     *
     * @param string $step
     * @return array
     */
    public static function applyUpdateStep($step)
    {
        $step = strtolower(trim((string) $step));
        $prepared = self::prepareUpdateContext($step);
        if (empty($prepared['ok'])) {
            return $prepared;
        }

        switch ($step) {
            case 'download':
                return self::updateStepDownload($prepared);
            case 'extract':
                return self::updateStepExtract($prepared);
            case 'deploy':
                return self::updateStepDeploy($prepared);
            case 'migrate':
                return self::updateStepMigrate($prepared);
            default:
                return array('ok' => false, 'msg' => '无效的更新步骤');
        }
    }

    /**
     * 更新前校验与上下文
     *
     * @return array
     */
    private static function prepareUpdateContext($step = '')
    {
        if (!class_exists('ZipArchive')) {
            return array('ok' => false, 'msg' => '服务器未启用 ZipArchive 扩展，无法解压更新包');
        }

        $check = self::checkForUpdate();
        if (!$check['ok']) {
            return array('ok' => false, 'msg' => $check['error']);
        }

        $step = strtolower(trim((string) $step));
        $work = self::getUpdateWork();
        $isPostDeployMigrate = ($step === 'migrate' && !empty($work['deployed']));

        if ($isPostDeployMigrate) {
            $ver = !empty($work['version']) ? (string) $work['version'] : self::localVersion();
            $check['remote_version'] = $ver;
            $check['local_version'] = $ver;
        } elseif (!$check['update_available']) {
            return array('ok' => false, 'msg' => '当前已是最新版本，无需更新');
        }

        $manifest = self::fetchRemoteManifest();
        if (!is_array($manifest)) {
            $manifest = array();
        }

        return array(
            'ok'      => true,
            'check'   => $check,
            'manifest'=> $manifest,
            'updateDir' => self::updateDir(),
            'zipPath'   => self::updateDir() . '/apinexus-update.zip',
            'extractDir'=> self::updateDir() . '/extract',
        );
    }

    /**
     * @param array $ctx
     * @return array
     */
    private static function updateStepDownload(array $ctx)
    {
        self::clearUpdateWork();
        self::cleanupUpdateWorkspace($ctx['updateDir']);

        $check = $ctx['check'];
        $triedUrls = array();
        $downloadOk = false;

        // 释放 session 锁，避免长时间下载阻塞其它请求
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        foreach (self::buildUpdatePackageUrls(
            $check['repo'],
            $check['branch'],
            $check['remote_version'],
            $ctx['manifest']
        ) as $item) {
            @unlink($ctx['zipPath']);
            $triedUrls[] = $item['label'];
            if (!self::downloadFile($item['url'], $ctx['zipPath'])) {
                continue;
            }
            if (!self::isValidZipFile($ctx['zipPath'])) {
                self::$lastError = '下载内容不是有效的 ZIP 更新包（' . $item['label'] . '）';
                @unlink($ctx['zipPath']);
                continue;
            }
            $downloadOk = true;
            break;
        }

        if (!$downloadOk) {
            $detail = self::$lastError !== '' ? self::$lastError : '未知错误';
            $sources = implode('、', $triedUrls);
            return array(
                'ok'  => false,
                'msg' => '云端资源包下载失败（已尝试：' . $sources . '）。' . $detail,
            );
        }

        self::ensureSessionStarted();
        self::setUpdateWork(array(
            'version' => $check['remote_version'],
            'downloaded' => true,
        ));

        return array(
            'ok'  => true,
            'msg' => '云端资源包下载完成',
            'step'=> 'download',
        );
    }

    /**
     * @param array $ctx
     * @return array
     */
    private static function updateStepExtract(array $ctx)
    {
        $work = self::getUpdateWork();
        if (empty($work['downloaded']) || !is_file($ctx['zipPath'])) {
            return array('ok' => false, 'msg' => '请先完成云端资源下载');
        }

        $zip = new ZipArchive();
        $zipOpen = $zip->open($ctx['zipPath']);
        if ($zipOpen !== true) {
            $size = (int) filesize($ctx['zipPath']);
            return array('ok' => false, 'msg' => '解压失败：无法打开 ZIP（' . $size . ' 字节）');
        }

        if (is_dir($ctx['extractDir'])) {
            self::removeDir($ctx['extractDir']);
        }
        @mkdir($ctx['extractDir'], 0755, true);

        if (!$zip->extractTo($ctx['extractDir'])) {
            $zip->close();
            return array('ok' => false, 'msg' => '解压更新包失败');
        }
        $zip->close();

        $sourceRoot = self::detectExtractRoot($ctx['extractDir']);
        if ($sourceRoot === null) {
            return array('ok' => false, 'msg' => '更新包结构异常，未找到有效目录');
        }

        $work['extracted'] = true;
        $work['source_root'] = $sourceRoot;
        self::setUpdateWork($work);

        return array(
            'ok'  => true,
            'msg' => '更新包解压完成',
            'step'=> 'extract',
        );
    }

    /**
     * @param array $ctx
     * @return array
     */
    private static function updateStepDeploy(array $ctx)
    {
        $work = self::getUpdateWork();
        if (empty($work['extracted']) || empty($work['source_root']) || !is_dir($work['source_root'])) {
            return array('ok' => false, 'msg' => '请先完成解压步骤');
        }

        $dbConfigHash = self::databaseConfigFingerprint();

        try {
            self::copyTree($work['source_root'], VS_ROOT, self::protectedRelativePaths());
            $removed = self::removeObsoleteFiles($work['source_root'], VS_ROOT, self::protectedRelativePaths());
            self::assertDatabaseConfigUnchanged($dbConfigHash);
        } catch (Exception $e) {
            return array('ok' => false, 'msg' => '文件覆盖失败：' . $e->getMessage());
        }

        self::cleanupUpdateWorkspace($ctx['updateDir']);
        $work['deployed'] = true;
        $work['db_hash'] = $dbConfigHash;
        unset($work['source_root'], $work['downloaded'], $work['extracted']);
        self::setUpdateWork($work);

        $msg = '文件覆盖完成';
        if ($removed > 0) {
            $msg .= '，已清理 ' . $removed . ' 个废弃文件';
        }

        return array(
            'ok'  => true,
            'msg' => $msg,
            'step'=> 'deploy',
        );
    }

    /**
     * @param array $ctx
     * @return array
     */
    private static function updateStepMigrate(array $ctx)
    {
        $work = self::getUpdateWork();
        if (empty($work['deployed'])) {
            return array('ok' => false, 'msg' => '请先完成文件覆盖');
        }

        $check = $ctx['check'];
        $migration = array('ok' => true, 'applied' => array(), 'msg' => '无数据库结构变更，已跳过');
        if (DatabaseMigrator::hasPendingMigrations()) {
            $migration = DatabaseMigrator::runPending();
            if (empty($migration['ok'])) {
                return array(
                    'ok'      => false,
                    'msg'     => '文件已更新，但' . $migration['msg'],
                    'version' => $check['remote_version'],
                );
            }
        }

        self::clearUpdateWork();

        $msg = '更新完成，当前版本 v' . $check['remote_version'];
        if (!empty($migration['applied'])) {
            $msg .= '，已同步数据库结构（' . implode('、', $migration['applied']) . '）';
        } else {
            $msg .= '（本次无数据库结构变更）';
        }

        $remaining = UpdateLog::countVersionsAfter($check['remote_version']);
        if ($remaining > 0) {
            $msg .= '。尚有 ' . $remaining . ' 个版本待升级，请刷新页面后继续执行更新';
        } else {
            $msg .= '。请刷新页面以加载新版本';
        }

        return array(
            'ok'      => true,
            'msg'     => $msg,
            'version' => $check['remote_version'],
            'step'    => 'migrate',
        );
    }

    /**
     * @return void
     */
    private static function ensureSessionStarted()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * @return array
     */
    private static function getUpdateWork()
    {
        self::ensureSessionStarted();
        if (!isset($_SESSION['vs_update_work']) || !is_array($_SESSION['vs_update_work'])) {
            return array();
        }
        return $_SESSION['vs_update_work'];
    }

    /**
     * @param array $work
     * @return void
     */
    private static function setUpdateWork(array $work)
    {
        self::ensureSessionStarted();
        $_SESSION['vs_update_work'] = $work;
    }

    /**
     * @return void
     */
    private static function clearUpdateWork()
    {
        self::ensureSessionStarted();
        unset($_SESSION['vs_update_work']);
    }

    /**
     * 下载并应用更新
     *
     * @return array
     */
    public static function applyUpdate()
    {
        $prepared = self::prepareUpdateContext();
        if (empty($prepared['ok'])) {
            return array('ok' => false, 'msg' => $prepared['msg']);
        }

        $download = self::updateStepDownload($prepared);
        if (empty($download['ok'])) {
            return $download;
        }

        $extract = self::updateStepExtract($prepared);
        if (empty($extract['ok'])) {
            self::cleanupUpdateWorkspace($prepared['updateDir']);
            self::clearUpdateWork();
            return $extract;
        }

        $deploy = self::updateStepDeploy($prepared);
        if (empty($deploy['ok'])) {
            self::cleanupUpdateWorkspace($prepared['updateDir']);
            self::clearUpdateWork();
            return $deploy;
        }

        $migrate = self::updateStepMigrate($prepared);
        if (empty($migrate['ok'])) {
            return $migrate;
        }

        unset($_SESSION['vs_update_dismiss']);

        return array(
            'ok'      => true,
            'msg'     => $migrate['msg'],
            'version' => $migrate['version'],
        );
    }

    /**
     * 数据库配置文件路径
     *
     * @return string
     */
    public static function databaseConfigPath()
    {
        return VS_ROOT . '/config/database.php';
    }

    /**
     * 更新前记录数据库配置指纹
     *
     * @return string|null
     */
    public static function databaseConfigFingerprint()
    {
        $path = self::databaseConfigPath();
        if (!is_file($path)) {
            return null;
        }
        return md5_file($path);
    }

    /**
     * 确认数据库配置未被覆盖
     *
     * @param string|null $beforeHash
     * @return void
     * @throws Exception
     */
    public static function assertDatabaseConfigUnchanged($beforeHash)
    {
        $path = self::databaseConfigPath();
        if (!is_file($path)) {
            throw new Exception('数据库配置文件不存在，更新已中止以保护连接信息');
        }
        if ($beforeHash === null) {
            return;
        }
        $afterHash = md5_file($path);
        if ($afterHash !== $beforeHash) {
            throw new Exception('数据库配置文件已被意外修改，更新已中止');
        }
    }

    /**
     * 更新源镜像列表（顺序：Gitee → GitCode → GitHub）
     *
     * @return array
     */
    public static function updateMirrors()
    {
        return array(
            array(
                'id'             => 'gitee',
                'label'          => 'Gitee',
                'repo'           => self::DEFAULT_REPO,
                'manifest_url'   => 'https://gitee.com/' . self::DEFAULT_REPO . '/raw/' . self::DEFAULT_BRANCH . '/update.json',
                'version_url'    => 'https://gitee.com/' . self::DEFAULT_REPO . '/raw/' . self::DEFAULT_BRANCH . '/core/version.php',
                'update_log_url' => 'https://gitee.com/' . self::DEFAULT_REPO . '/raw/' . self::DEFAULT_BRANCH . '/update-log.json',
            ),
            array(
                'id'             => 'gitcode',
                'label'          => 'GitCode',
                'repo'           => self::GITCODE_REPO,
                'manifest_url'   => 'https://raw.gitcode.com/' . self::GITCODE_REPO . '/raw/' . self::DEFAULT_BRANCH . '/update.json',
                'version_url'    => 'https://raw.gitcode.com/' . self::GITCODE_REPO . '/raw/' . self::DEFAULT_BRANCH . '/core/version.php',
                'update_log_url' => 'https://raw.gitcode.com/' . self::GITCODE_REPO . '/raw/' . self::DEFAULT_BRANCH . '/update-log.json',
            ),
            array(
                'id'             => 'github',
                'label'          => 'GitHub',
                'repo'           => self::GITHUB_REPO,
                'manifest_url'   => 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::DEFAULT_BRANCH . '/update.json',
                'version_url'    => 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::DEFAULT_BRANCH . '/core/version.php',
                'update_log_url' => 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::DEFAULT_BRANCH . '/update-log.json',
            ),
        );
    }

    /**
     * 拉取远程 update.json（Gitee → GitCode → GitHub；失败再试各源 version.php）
     *
     * @return array|null
     */
    public static function fetchRemoteManifest()
    {
        foreach (self::updateMirrors() as $mirror) {
            $body = self::httpGet($mirror['manifest_url'], 15);
            if ($body === false || $body === '') {
                continue;
            }
            $data = json_decode($body, true);
            if (is_array($data) && !empty($data['version'])) {
                if (empty($data['repo'])) {
                    $data['repo'] = $mirror['repo'];
                }
                if (empty($data['branch'])) {
                    $data['branch'] = self::DEFAULT_BRANCH;
                }
                return $data;
            }
        }

        foreach (self::updateMirrors() as $mirror) {
            $versionBody = self::httpGet($mirror['version_url'], 15);
            if ($versionBody === false || $versionBody === '') {
                continue;
            }
            if (preg_match("/define\s*\(\s*'VS_VERSION'\s*,\s*'([^']+)'\s*\)/", $versionBody, $matches)) {
                return array(
                    'version'      => $matches[1],
                    'title'        => '版本更新',
                    'release_date' => '',
                    'changes'      => array('检测到新版本，建议立即更新'),
                    'repo'         => $mirror['repo'],
                    'branch'       => self::DEFAULT_BRANCH,
                );
            }
        }

        return null;
    }

    /**
     * 构建更新包下载地址（Gitee 发行包优先，再 GitCode / GitHub）
     *
     * @param string $repo
     * @param string $branch
     * @param string $version
     * @param array  $manifest
     * @return array
     */
    public static function buildUpdatePackageUrls($repo, $branch, $version, array $manifest = array())
    {
        $urls = array();
        if (!empty($manifest['package_url']) && self::isTrustedUpdateUrl($manifest['package_url'])) {
            $urls[] = array('label' => '自定义更新包', 'url' => $manifest['package_url']);
        }

        $ver = ltrim(trim($version), 'vV');
        if ($ver === '') {
            return $urls;
        }

        $tag = 'v' . $ver;
        $fileName = 'apinexus' . $ver . '.zip';
        $giteeRepo = self::DEFAULT_REPO;
        if (is_string($repo) && $repo !== '' && strpos($repo, '/') !== false
            && stripos($repo, 'github.com') === false
            && stripos($repo, 'gitcode') === false
            && stripos($repo, 'whr884657') === false
        ) {
            $giteeRepo = $repo;
        }

        $urls[] = array(
            'label' => 'Gitee 发行包',
            'url'   => self::buildReleasePackageUrl($giteeRepo, $ver),
        );
        $urls[] = array(
            'label' => 'GitCode 标签包',
            'url'   => 'https://raw.gitcode.com/' . self::GITCODE_REPO
                . '/archive/refs/tags/' . rawurlencode($tag) . '.zip',
        );
        $urls[] = array(
            'label' => 'GitCode 分支包',
            'url'   => 'https://raw.gitcode.com/' . self::GITCODE_REPO
                . '/archive/refs/heads/' . rawurlencode($tag) . '.zip',
        );
        $urls[] = array(
            'label' => 'GitHub 发行包',
            'url'   => 'https://github.com/' . self::GITHUB_REPO . '/releases/download/'
                . rawurlencode($tag) . '/' . rawurlencode($fileName),
        );
        $urls[] = array(
            'label' => 'GitHub 标签包',
            'url'   => 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/tags/'
                . rawurlencode($tag) . '.zip',
        );

        return $urls;
    }

    /**
     * Gitee 发行版压缩包直链（默认主源）
     *
     * @param string $repo  如 xunjinlu/apinexus
     * @param string $version 如 1.0.22
     * @return string
     */
    public static function buildReleasePackageUrl($repo, $version)
    {
        $ver = ltrim(trim($version), 'vV');
        $tag = 'v' . $ver;
        $fileName = 'apinexus' . $ver . '.zip';
        if ($repo === '' || !is_string($repo)) {
            $repo = self::DEFAULT_REPO;
        }
        return 'https://gitee.com/' . $repo . '/releases/download/'
            . rawurlencode($tag) . '/' . rawurlencode($fileName);
    }

    /**
     * 是否为 ApiNexus 更新可信 HTTPS 地址（仅白名单域名）
     *
     * @param string $url
     * @return bool
     */
    public static function isTrustedUpdateUrl($url)
    {
        if ($url === '' || !is_string($url)) {
            return false;
        }

        $parts = parse_url($url);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        return in_array($host, self::TRUSTED_UPDATE_HOSTS, true);
    }

    /**
     * 是否为有效 ZIP 文件（PK 头）
     *
     * @param string $path
     * @return bool
     */
    public static function isValidZipFile($path)
    {
        if (!is_file($path) || filesize($path) < 22) {
            return false;
        }
        $h = @fopen($path, 'rb');
        if (!$h) {
            return false;
        }
        $magic = fread($h, 4);
        fclose($h);
        return $magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08";
    }

    /**
     * 获取最近一次错误说明
     *
     * @return string
     */
    public static function getLastError()
    {
        return self::$lastError;
    }

    /**
     * 为 cURL 配置 SSL
     *
     * 云端发行源使用 HTTPS 直连下载，不绑定本地 cacert.pem：
     * - 站点 HTTPS 证书与「出站访问云端更新源」无关
     * - 本地 CA 根证书包会随时间过时，且受 open_basedir 限制
     * - 仅对白名单域名放宽链校验，下载后仍校验 ZIP 文件头
     *
     * @param resource|\CurlHandle $ch
     * @param string               $url
     * @return void
     */
    public static function configureCurlSsl($ch, $url = '')
    {
        if ($url !== '' && self::isTrustedUpdateUrl($url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            return;
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    /**
     * HTTP GET
     *
     * @param string $url
     * @param int    $timeout
     * @return string|false
     */
    public static function httpGet($url, $timeout = 30)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS        => 10,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'ApiNexus-Updater/' . self::localVersion(),
            ));
            self::configureCurlSsl($ch, $url);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return $body;
            }
            if ($error !== '') {
                self::$lastError = $error;
            }
            return false;
        }

        $sslOptions = array(
            'verify_peer'      => !self::isTrustedUpdateUrl($url),
            'verify_peer_name' => !self::isTrustedUpdateUrl($url),
        );

        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => "User-Agent: ApiNexus-Updater/" . self::localVersion() . "\r\n",
            ),
            'ssl' => $sslOptions,
        ));

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            self::$lastError = 'HTTP 请求失败';
        }
        return $body;
    }

    /**
     * 下载文件到本地
     *
     * @param string $url
     * @param string $dest
     * @return bool
     */
    public static function downloadFile($url, $dest)
    {
        self::$lastError = '';

        if (function_exists('curl_init')) {
            $fp = @fopen($dest, 'wb');
            if (!$fp) {
                self::$lastError = '无法写入临时文件：' . basename($dest);
                return false;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT        => 300,
                CURLOPT_USERAGENT      => 'ApiNexus-Updater/' . self::localVersion(),
            ));
            self::configureCurlSsl($ch, $url);
            $ok = curl_exec($ch) !== false;
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if (!$ok || $code < 200 || $code >= 300) {
                if ($error !== '') {
                    self::$lastError = $error;
                } elseif ($code > 0) {
                    self::$lastError = 'HTTP 状态码 ' . $code;
                } else {
                    self::$lastError = '网络连接失败';
                }
                @unlink($dest);
                return false;
            }
            if (!is_file($dest) || filesize($dest) <= 0) {
                self::$lastError = '下载文件为空';
                @unlink($dest);
                return false;
            }
            return true;
        }

        $body = self::httpGet($url, 300);
        if ($body === false || $body === '') {
            if (self::$lastError === '') {
                self::$lastError = 'HTTP 请求失败';
            }
            return false;
        }
        if (file_put_contents($dest, $body) === false) {
            self::$lastError = '无法写入临时文件';
            return false;
        }
        return true;
    }

    /**
     * 更新时保留的相对路径
     *
     * @return array
     */
    public static function protectedRelativePaths()
    {
        return array(
            'config/database.php',
            'config/install.lock',
            'data',
        );
    }

    /**
     * 读取废弃文件清单（优先更新包内 install/obsolete-files.json，缺省则用内置兜底）
     *
     * @param string $sourceRoot 解压后的源码根目录
     * @return array<int, string> 相对路径列表
     */
    public static function loadObsoleteRelativePaths($sourceRoot)
    {
        $list = array();
        $path = rtrim(str_replace('\\', '/', (string) $sourceRoot), '/') . '/install/obsolete-files.json';
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $json = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($json) && isset($json['files']) && is_array($json['files'])) {
                foreach ($json['files'] as $item) {
                    if (is_string($item) && $item !== '') {
                        $list[] = $item;
                    }
                }
            }
        }
        if (count($list) === 0) {
            $list = array(
                'proxy.php',
                'api-proxy.php',
            );
        }
        return self::sanitizeObsoletePaths($list);
    }

    /**
     * 规范化并过滤危险路径
     *
     * @param array $paths
     * @return array<int, string>
     */
    public static function sanitizeObsoletePaths(array $paths)
    {
        $out = array();
        foreach ($paths as $relative) {
            $relative = str_replace('\\', '/', trim((string) $relative));
            $relative = ltrim($relative, '/');
            if ($relative === '' || strpos($relative, '..') !== false) {
                continue;
            }
            if (strpos($relative, ':') !== false) {
                continue;
            }
            // 仅允许删项目根下明确列出的普通文件（禁止根目录通配 / 目录爆破）
            if (!preg_match('#^[A-Za-z0-9_./%-]+$#', $relative)) {
                continue;
            }
            if (substr($relative, -1) === '/') {
                continue;
            }
            $out[$relative] = $relative;
        }
        return array_values($out);
    }

    /**
     * 覆盖后删除发行包声明的废弃文件（跳过受保护路径）
     *
     * @param string $sourceRoot
     * @param string $targetRoot
     * @param array  $protected
     * @return int 实际删除的文件数
     */
    public static function removeObsoleteFiles($sourceRoot, $targetRoot, array $protected)
    {
        $targetRoot = rtrim(str_replace('\\', '/', (string) $targetRoot), '/');
        if ($targetRoot === '' || !is_dir($targetRoot)) {
            return 0;
        }

        $removed = 0;
        foreach (self::loadObsoleteRelativePaths($sourceRoot) as $relative) {
            if (self::isImmutablePath($relative) || self::isProtectedPath($relative, $protected)) {
                continue;
            }
            $full = $targetRoot . '/' . $relative;
            if (!is_file($full)) {
                continue;
            }
            // 防止路径逃逸：解析后必须仍在目标根下
            $realFile = realpath($full);
            $realRoot = realpath($targetRoot);
            if ($realFile === false || $realRoot === false) {
                continue;
            }
            $realFile = str_replace('\\', '/', $realFile);
            $realRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
            if (strpos($realFile, $realRoot . '/') !== 0) {
                continue;
            }
            if (@unlink($realFile)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * 是否为绝不可覆盖的路径（硬编码安全规则）
     *
     * @param string $relative
     * @return bool
     */
    public static function isImmutablePath($relative)
    {
        $relative = strtolower(str_replace('\\', '/', $relative));
        $immutable = array(
            'config/database.php',
        );
        return in_array($relative, $immutable, true);
    }

    /**
     * 识别解压后的项目根目录
     *
     * @param string $extractDir
     * @return string|null
     */
    public static function detectExtractRoot($extractDir)
    {
        if (!is_dir($extractDir)) {
            return null;
        }

        if (self::looksLikeProjectRoot($extractDir)) {
            return $extractDir;
        }

        $items = scandir($extractDir);
        if ($items === false) {
            return null;
        }

        $dirs = array();
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $extractDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        foreach ($dirs as $path) {
            if (self::looksLikeProjectRoot($path)) {
                return $path;
            }
        }

        if (count($dirs) === 1) {
            return $dirs[0];
        }

        return null;
    }

    /**
     * 目录是否像 ApiNexus 项目根
     *
     * @param string $dir
     * @return bool
     */
    public static function looksLikeProjectRoot($dir)
    {
        return is_file($dir . '/core/version.php')
            || is_file($dir . '/index.php')
            || is_file($dir . '/update.json');
    }

    /**
     * 递归复制目录（跳过受保护路径）
     *
     * @param string $src
     * @param string $dst
     * @param array  $protected
     * @return void
     */
    public static function copyTree($src, $dst, array $protected)
    {
        $src = rtrim(str_replace('\\', '/', realpath($src)), '/');
        $dst = rtrim(str_replace('\\', '/', realpath($dst)), '/');

        if ($src === false || $dst === false) {
            throw new Exception('路径无效');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $fullPath = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($fullPath, strlen($src)), '/');

            if (self::isImmutablePath($relative) || self::isProtectedPath($relative, $protected)) {
                continue;
            }

            $target = $dst . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    if (!@mkdir($target, 0755, true)) {
                        throw new Exception('无法创建目录：' . $relative);
                    }
                }
            } else {
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    if (!@mkdir($targetDir, 0755, true)) {
                        throw new Exception('无法创建目录：' . dirname($relative));
                    }
                }
                if (!@copy($fullPath, $target)) {
                    throw new Exception('无法写入文件：' . $relative);
                }
            }
        }
    }

    /**
     * 是否受保护路径
     *
     * @param string $relative
     * @param array  $protected
     * @return bool
     */
    public static function isProtectedPath($relative, array $protected)
    {
        $relative = str_replace('\\', '/', $relative);
        foreach ($protected as $rule) {
            $rule = str_replace('\\', '/', $rule);
            if ($relative === $rule) {
                return true;
            }
            if (substr($rule, -1) !== '/' && strpos($relative, $rule . '/') === 0) {
                return true;
            }
            if (substr($rule, -1) === '/' && strpos($relative, rtrim($rule, '/')) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 清空更新临时目录（zip、解压目录及残留文件）
     *
     * @param string|null $updateDir
     * @return void
     */
    public static function cleanupUpdateWorkspace($updateDir = null)
    {
        if ($updateDir === null) {
            $updateDir = VS_ROOT . '/data/update';
        }
        if (!is_dir($updateDir)) {
            return;
        }

        $items = scandir($updateDir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if ($item === '.htaccess') {
                continue;
            }
            $path = $updateDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * 清理路径（文件或目录）
     *
     * @param array $paths
     * @return void
     */
    public static function cleanupPaths(array $paths)
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                self::removeDir($path);
            }
        }
    }

    /**
     * 递归删除目录
     *
     * @param string $dir
     * @return void
     */
    public static function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
