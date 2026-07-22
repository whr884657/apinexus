<?php
/**
 * 文件：core/FrontendArticle.php
 * 作用：前台主题 · 已发布文章列表与详情（主题只调用本类）
 */

class FrontendArticle
{
    /**
     * @param string $body
     * @return string
     */
    private static function bodyHtml($body)
    {
        $body = (string) $body;
        if (class_exists('Markdown') && method_exists('Markdown', 'render')) {
            return Markdown::render($body);
        }
        return $body;
    }

    /**
     * @param array $row
     * @param bool  $withBody
     * @return array|null
     */
    public static function formatForTheme(array $row, $withBody = false)
    {
        if (!isset($row['status_label']) && isset($row['id'])) {
            $row = ContentManager::formatRow($row);
        }

        $kind = ContentManager::normalizeKind(isset($row['kind']) ? $row['kind'] : ContentManager::KIND_ARTICLE);
        $status = ContentManager::normalizeStatus(isset($row['status']) ? $row['status'] : ContentManager::STATUS_DRAFT);
        if ($kind !== ContentManager::KIND_ARTICLE || $status !== ContentManager::STATUS_PUBLISHED) {
            return null;
        }

        $title = trim((string) (isset($row['title']) ? $row['title'] : ''));
        if ($title === '') {
            return null;
        }

        $body = isset($row['body']) ? (string) $row['body'] : '';
        $views = isset($row['views']) ? (int) $row['views'] : 0;

        $item = array(
            'id'          => (int) (isset($row['id']) ? $row['id'] : 0),
            'title'       => $title,
            'summary'     => trim((string) (isset($row['summary']) ? $row['summary'] : '')),
            'cover'       => trim((string) (isset($row['cover']) ? $row['cover'] : '')),
            'views'       => $views,
            'views_label' => number_format($views),
            'createtime'  => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );

        if ($withBody) {
            $item['body'] = $body;
            $item['body_html'] = self::bodyHtml($body);
        }

        return $item;
    }

    /**
     * 首页摘要：已发布文章，按 sort 升序、id 降序
     *
     * @param int $limit
     * @return array<int, array>
     */
    public static function listForTheme($limit = 10)
    {
        $limit = max(1, min(50, (int) $limit));
        if (!ContentManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . ContentManager::table() . '`
                WHERE `kind` = ? AND `status` = ?
                ORDER BY `sort` ASC, `id` DESC
                LIMIT ' . (int) $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ContentManager::KIND_ARTICLE,
                ContentManager::STATUS_PUBLISHED,
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = self::formatForTheme($row, false);
                    if ($item !== null) {
                        $out[] = $item;
                    }
                }
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 列表页分页（keyset：before_id）
     *
     * @param int $page
     * @param int $pageSize
     * @param int $beforeId
     * @return array{list:array,page:int,pagesize:int,before_id:int,next_before_id:int,has_more:bool}
     */
    public static function listPaged($page = 1, $pageSize = 10, $beforeId = 0)
    {
        $pageSize = max(1, min(50, (int) $pageSize));
        $data = ContentManager::listPaged(array(
            'kind'       => ContentManager::KIND_ARTICLE,
            'status'     => ContentManager::STATUS_PUBLISHED,
            'page'       => max(1, (int) $page),
            'pagesize'   => $pageSize,
            'before_id'  => (int) $beforeId,
        ));

        $list = array();
        foreach ($data['list'] as $row) {
            $item = self::formatForTheme($row, false);
            if ($item !== null) {
                $list[] = $item;
            }
        }
        $data['list'] = $list;

        if (!empty($list)) {
            $last = $list[count($list) - 1];
            $data['next_before_id'] = isset($last['id']) ? (int) $last['id'] : 0;
        } else {
            $data['next_before_id'] = 0;
        }

        return $data;
    }

    /**
     * @param int  $id
     * @param bool $incrementViews
     * @return array|null
     */
    public static function findById($id, $incrementViews = true)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $row = ContentManager::findById($id);
        if (!is_array($row)) {
            return null;
        }
        $item = self::formatForTheme($row, true);
        if ($item === null) {
            return null;
        }
        if ($incrementViews) {
            ContentManager::incrementViews($id);
            $item['views'] = $item['views'] + 1;
            $item['views_label'] = number_format($item['views']);
        }
        return $item;
    }
}
