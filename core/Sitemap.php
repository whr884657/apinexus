<?php
/**
 * 文件：core/Sitemap.php
 * 作用：生成前台 SEO 用 sitemap.xml（静态页 + 公开接口详情 + 已发布文章）
 */

class Sitemap
{
    /** 单次输出 URL 上限（防内存打爆） */
    const MAX_URLS = 5000;

    /**
     * 输出 application/xml 并结束
     *
     * @return void
     */
    public static function emit()
    {
        $xml = self::buildXml();
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Content-Type-Options: nosniff');
        }
        echo $xml;
        exit;
    }

    /**
     * @return string
     */
    public static function buildXml()
    {
        $urls = self::collectUrls();
        $buf = array();
        $buf[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $buf[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $row) {
            if (!is_array($row) || empty($row['loc'])) {
                continue;
            }
            $loc = self::xmlEscape((string) $row['loc']);
            $buf[] = '  <url>';
            $buf[] = '    <loc>' . $loc . '</loc>';
            if (!empty($row['lastmod'])) {
                $buf[] = '    <lastmod>' . self::xmlEscape((string) $row['lastmod']) . '</lastmod>';
            }
            if (!empty($row['changefreq'])) {
                $buf[] = '    <changefreq>' . self::xmlEscape((string) $row['changefreq']) . '</changefreq>';
            }
            if (isset($row['priority']) && $row['priority'] !== '') {
                $buf[] = '    <priority>' . self::xmlEscape((string) $row['priority']) . '</priority>';
            }
            $buf[] = '  </url>';
        }
        $buf[] = '</urlset>';
        return implode("\n", $buf) . "\n";
    }

    /**
     * @return array<int, array{loc:string,lastmod?:string,changefreq?:string,priority?:string}>
     */
    public static function collectUrls()
    {
        $out = array();
        $today = date('Y-m-d');

        foreach (self::staticPages() as $path => $meta) {
            $out[] = array(
                'loc'        => vs_seo_abs_url($path),
                'lastmod'    => $today,
                'changefreq' => isset($meta['changefreq']) ? (string) $meta['changefreq'] : 'weekly',
                'priority'   => isset($meta['priority']) ? (string) $meta['priority'] : '0.5',
            );
        }

        foreach (self::apiDetailUrls() as $row) {
            $out[] = $row;
            if (count($out) >= self::MAX_URLS) {
                return $out;
            }
        }

        foreach (self::articleUrls() as $row) {
            $out[] = $row;
            if (count($out) >= self::MAX_URLS) {
                return $out;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{changefreq:string,priority:string}>
     */
    private static function staticPages()
    {
        return array(
            '/'             => array('changefreq' => 'daily', 'priority' => '1.0'),
            '/apis'         => array('changefreq' => 'daily', 'priority' => '0.9'),
            '/articles'     => array('changefreq' => 'daily', 'priority' => '0.8'),
            '/about'        => array('changefreq' => 'monthly', 'priority' => '0.5'),
            '/links'        => array('changefreq' => 'weekly', 'priority' => '0.4'),
            '/sponsor'      => array('changefreq' => 'monthly', 'priority' => '0.3'),
            '/contributors' => array('changefreq' => 'monthly', 'priority' => '0.3'),
        );
    }

    /**
     * @return array<int, array{loc:string,lastmod?:string,changefreq:string,priority:string}>
     */
    private static function apiDetailUrls()
    {
        $out = array();
        if (!class_exists('ApiManager') || !ApiManager::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT `id`, `updatetime`, `createtime` FROM `' . ApiManager::table() . '`
                    WHERE `status` IN (' . (int) ApiManager::STATUS_NORMAL . ', ' . (int) ApiManager::STATUS_MAINTENANCE . ')';
            if (ApiManager::hasAuditColumn()) {
                $sql .= ' AND `audit` = ' . (int) ApiManager::AUDIT_APPROVED;
            }
            $sql .= ' ORDER BY `id` DESC LIMIT ' . (int) (self::MAX_URLS - 50);
            $stmt = $pdo->query($sql);
            if (!$stmt) {
                return $out;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $last = '';
                if (!empty($row['updatetime'])) {
                    $last = self::toDate((string) $row['updatetime']);
                } elseif (!empty($row['createtime'])) {
                    $last = self::toDate((string) $row['createtime']);
                }
                $item = array(
                    'loc'        => vs_seo_abs_url('/detail/' . $id),
                    'changefreq' => 'weekly',
                    'priority'   => '0.7',
                );
                if ($last !== '') {
                    $item['lastmod'] = $last;
                }
                $out[] = $item;
            }
        } catch (Exception $e) {
            return array();
        }
        return $out;
    }

    /**
     * @return array<int, array{loc:string,lastmod?:string,changefreq:string,priority:string}>
     */
    private static function articleUrls()
    {
        $out = array();
        if (!class_exists('ContentManager') || !ContentManager::tableReady()) {
            return $out;
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT `id`, `updatetime`, `createtime` FROM `' . ContentManager::table() . '`
                    WHERE `kind` = ? AND `status` = ? AND `bindpage` = ?
                    ORDER BY `id` DESC
                    LIMIT ' . (int) (self::MAX_URLS - 50);
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ContentManager::KIND_ARTICLE,
                ContentManager::STATUS_PUBLISHED,
                ContentManager::BIND_NONE,
            ));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $last = '';
                if (!empty($row['updatetime'])) {
                    $last = self::toDate((string) $row['updatetime']);
                } elseif (!empty($row['createtime'])) {
                    $last = self::toDate((string) $row['createtime']);
                }
                $item = array(
                    'loc'        => vs_seo_abs_url('/articles/' . $id),
                    'changefreq' => 'weekly',
                    'priority'   => '0.6',
                );
                if ($last !== '') {
                    $item['lastmod'] = $last;
                }
                $out[] = $item;
            }
        } catch (Exception $e) {
            return array();
        }
        return $out;
    }

    /**
     * @param string $datetime
     * @return string Y-m-d
     */
    private static function toDate($datetime)
    {
        $t = strtotime(trim((string) $datetime));
        if ($t === false) {
            return '';
        }
        return date('Y-m-d', $t);
    }

    /**
     * @param string $s
     * @return string
     */
    private static function xmlEscape($s)
    {
        return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
