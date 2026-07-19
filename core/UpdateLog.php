<?php
/**
 * 文件：core/UpdateLog.php
 * 作用：读取版本更新记录（优先从云端 update-log.json）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class UpdateLog
{
    const LOCAL_FILE = 'update-log.json';

    /** @var array|null 单次请求内缓存：array('data' => array|null, 'source' => string) */
    private static $resolvedPayload = null;

    /**
     * 本地更新记录文件路径
     *
     * @return string
     */
    public static function localPath()
    {
        return VS_ROOT . '/' . self::LOCAL_FILE;
    }

    /**
     * 读取本地 update-log.json（仅作云端不可用时的回退）
     *
     * @return array|null
     */
    public static function loadLocal()
    {
        $path = self::localPath();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /**
     * 构建云端 raw 地址（默认 Gitee 主源；实际拉取见 fetchRemote 多源兜底）
     *
     * @param string|null $repo
     * @param string|null $branch
     * @return string
     */
    public static function remoteUrl($repo = null, $branch = null)
    {
        if ($repo === null || $branch === null) {
            $resolved = self::resolveRepoBranch();
            if ($repo === null) {
                $repo = $resolved['repo'];
            }
            if ($branch === null) {
                $branch = $resolved['branch'];
            }
        }

        return 'https://gitee.com/' . $repo . '/raw/' . $branch . '/' . self::LOCAL_FILE;
    }

    /**
     * 各镜像 update-log.json 地址（Gitee → GitCode → GitHub）
     *
     * @return array
     */
    public static function remoteUrls()
    {
        $urls = array();
        foreach (Updater::updateMirrors() as $mirror) {
            if (!empty($mirror['update_log_url'])) {
                $urls[] = $mirror['update_log_url'];
            }
        }
        return $urls;
    }

    /**
     * 解析仓库与分支（本地文件 → 远程 manifest → 默认值）
     *
     * @return array{repo: string, branch: string}
     */
    public static function resolveRepoBranch()
    {
        $repo = Updater::DEFAULT_REPO;
        $branch = Updater::DEFAULT_BRANCH;

        $local = self::loadLocal();
        if ($local !== null) {
            if (!empty($local['repo'])) {
                $repo = $local['repo'];
            }
            if (!empty($local['branch'])) {
                $branch = $local['branch'];
            }
            return array('repo' => $repo, 'branch' => $branch);
        }

        $manifest = Updater::fetchRemoteManifest();
        if (is_array($manifest)) {
            if (!empty($manifest['repo'])) {
                $repo = $manifest['repo'];
            }
            if (!empty($manifest['branch'])) {
                $branch = $manifest['branch'];
            }
        }

        return array('repo' => $repo, 'branch' => $branch);
    }

    /**
     * 从云端拉取 update-log.json（Gitee → GitCode → GitHub）
     *
     * @param string|null $repo
     * @param string|null $branch
     * @return array|null
     */
    public static function fetchRemote($repo = null, $branch = null)
    {
        $urls = self::remoteUrls();
        if ($repo !== null || $branch !== null) {
            array_unshift($urls, self::remoteUrl($repo, $branch));
        }

        $seen = array();
        foreach ($urls as $url) {
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $body = Updater::httpGet($url, 15);
            if ($body === false || $body === '') {
                continue;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['versions']) || !is_array($data['versions'])) {
                continue;
            }
            return $data;
        }

        return null;
    }

    /**
     * 加载更新记录（优先云端，失败时回退本地）
     *
     * @return array{data: array|null, source: string}
     */
    public static function loadData()
    {
        if (self::$resolvedPayload !== null) {
            return self::$resolvedPayload;
        }

        $resolved = self::resolveRepoBranch();
        $remote = self::fetchRemote($resolved['repo'], $resolved['branch']);
        if ($remote !== null) {
            self::$resolvedPayload = array('data' => $remote, 'source' => 'remote');
            return self::$resolvedPayload;
        }

        $local = self::loadLocal();
        if ($local !== null && !empty($local['versions']) && is_array($local['versions'])) {
            self::$resolvedPayload = array('data' => $local, 'source' => 'local');
            return self::$resolvedPayload;
        }

        self::$resolvedPayload = array('data' => null, 'source' => '');
        return self::$resolvedPayload;
    }

    /**
     * 当前数据来源：remote / local / 空
     *
     * @return string
     */
    public static function getSource()
    {
        return self::loadData()['source'];
    }

    /**
     * 将 versions 节点规范为带 version 字段的记录列表
     *
     * 支持两种 JSON 结构：
     * - 对象：{"1.1.0": {"title": "...", ...}}
     * - 数组：[{"version": "1.1.0", "title": "...", ...}]
     *
     * @param array $versionsNode
     * @return array
     */
    private static function normalizeVersionRows(array $versionsNode)
    {
        $rows = array();

        foreach ($versionsNode as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $ver = isset($row['version']) ? trim((string) $row['version']) : '';
            if ($ver === '' && is_string($key) && preg_match('/^\d+\.\d+\.\d+/', $key)) {
                $ver = trim($key);
            }
            if ($ver === '') {
                continue;
            }

            $row['version'] = $ver;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * 全部版本记录（新→旧）
     *
     * @return array
     */
    public static function allVersions()
    {
        $payload = self::loadData();
        $data = $payload['data'];
        if ($data === null || empty($data['versions']) || !is_array($data['versions'])) {
            return array();
        }

        $versions = self::normalizeVersionRows($data['versions']);

        usort($versions, function ($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        return $versions;
    }

    /**
     * 获取指定版本记录
     *
     * @param string $version
     * @return array|null
     */
    public static function getVersion($version)
    {
        foreach (self::allVersions() as $row) {
            if (isset($row['version']) && $row['version'] === $version) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 本地版本之后的下一个待升级版本（按版本号递增，不跳版）
     *
     * @param string $localVersion
     * @return string
     */
    public static function nextVersionAfter($localVersion)
    {
        $candidates = array();
        $payload = self::loadData();
        $data = $payload['data'];
        if ($data === null || empty($data['versions']) || !is_array($data['versions'])) {
            return '';
        }

        foreach (self::normalizeVersionRows($data['versions']) as $row) {
            if (version_compare($row['version'], $localVersion, '>')) {
                $candidates[] = $row['version'];
            }
        }

        if (empty($candidates)) {
            return '';
        }

        usort($candidates, 'version_compare');
        return $candidates[0];
    }

    /**
     * 本地版本之后尚待升级的版本数量
     *
     * @param string $localVersion
     * @return int
     */
    public static function countVersionsAfter($localVersion)
    {
        $count = 0;
        $payload = self::loadData();
        $data = $payload['data'];
        if ($data === null || empty($data['versions']) || !is_array($data['versions'])) {
            return 0;
        }

        foreach (self::normalizeVersionRows($data['versions']) as $row) {
            if (version_compare($row['version'], $localVersion, '>')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 目标版本是否包含数据库结构变更
     *
     * @param string $version
     * @return bool
     */
    public static function versionHasDbChanges($version)
    {
        $row = self::getVersion($version);
        if ($row === null) {
            return DatabaseMigrator::hasPendingMigrations();
        }

        if (!empty($row['db_changes'])) {
            return true;
        }

        if (!empty($row['migration'])) {
            return DatabaseMigrator::isMigrationPending($row['migration']);
        }

        return false;
    }

    /**
     * 供 API / 页面输出的版本列表
     *
     * @return array
     */
    public static function payloadForApi()
    {
        $list = array();
        foreach (self::allVersions() as $row) {
            $list[] = array(
                'version'    => isset($row['version']) ? $row['version'] : '',
                'date'       => isset($row['date']) ? $row['date'] : '',
                'title'      => isset($row['title']) ? $row['title'] : '',
                'db_changes' => !empty($row['db_changes']),
                'changes'    => isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array(),
            );
        }
        return $list;
    }

    /**
     * 从本地到目标版本之间是否存在数据库变更
     *
     * @param string $localVersion
     * @param string $remoteVersion
     * @return bool
     */
    public static function rangeHasDbChanges($localVersion, $remoteVersion)
    {
        if ($remoteVersion === '') {
            return false;
        }

        foreach (self::allVersions() as $row) {
            if (empty($row['version']) || empty($row['db_changes'])) {
                continue;
            }
            $v = $row['version'];
            if (version_compare($v, $localVersion, '>') && version_compare($v, $remoteVersion, '<=')) {
                return true;
            }
        }

        return DatabaseMigrator::hasPendingMigrations();
    }
}
