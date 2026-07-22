<?php
/**
 * 文件：core/FrontendAnnouncement.php
 * 作用：前台主题 · 已发布公告与弹窗（主题只调用本类）
 */

class FrontendAnnouncement
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
     * @return array|null
     */
    public static function formatForTheme(array $row)
    {
        if (!isset($row['status_label']) && isset($row['id'])) {
            $row = ContentManager::formatRow($row);
        }

        $kind = ContentManager::normalizeKind(isset($row['kind']) ? $row['kind'] : ContentManager::KIND_ANNOUNCEMENT);
        $status = ContentManager::normalizeStatus(isset($row['status']) ? $row['status'] : ContentManager::STATUS_DRAFT);
        if ($kind !== ContentManager::KIND_ANNOUNCEMENT || $status !== ContentManager::STATUS_PUBLISHED) {
            return null;
        }

        $title = trim((string) (isset($row['title']) ? $row['title'] : ''));
        if ($title === '') {
            return null;
        }

        $body = isset($row['body']) ? (string) $row['body'] : '';
        $preview = ContentManager::plainTextPreview($body, 80);

        return array(
            'id'         => (int) (isset($row['id']) ? $row['id'] : 0),
            'title'      => $title,
            'summary'    => trim((string) (isset($row['summary']) ? $row['summary'] : '')),
            'body'       => $body,
            'body_html'  => self::bodyHtml($body),
            'preview'    => $preview !== '' ? $preview : $title,
            'ispinned'   => ContentManager::normalizeFlag(isset($row['ispinned']) ? $row['ispinned'] : 0),
            'ispopup'    => ContentManager::normalizeFlag(isset($row['ispopup']) ? $row['ispopup'] : 0),
            'createtime' => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );
    }

    /**
     * 已发布公告：置顶优先，再按 id 降序
     *
     * @return array<int, array>
     */
    public static function listForTheme()
    {
        if (!ContentManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . ContentManager::table() . '`
                WHERE `kind` = ? AND `status` = ?
                ORDER BY `ispinned` DESC, `id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ContentManager::KIND_ANNOUNCEMENT,
                ContentManager::STATUS_PUBLISHED,
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = self::formatForTheme($row);
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
     * 弹窗公告：已发布且 ispopup=1
     *
     * @return array<int, array>
     */
    public static function listPopups()
    {
        if (!ContentManager::tableReady()) {
            return array();
        }
        try {
            $pdo = Database::connect();
            $sql = 'SELECT * FROM `' . ContentManager::table() . '`
                WHERE `kind` = ? AND `status` = ? AND `ispopup` = 1
                ORDER BY `ispinned` DESC, `id` DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ContentManager::KIND_ANNOUNCEMENT,
                ContentManager::STATUS_PUBLISHED,
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = self::formatForTheme($row);
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
     * @param int $id
     * @return array|null
     */
    public static function findById($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $row = ContentManager::findById($id);
        if (!is_array($row)) {
            return null;
        }
        return self::formatForTheme($row);
    }
}
