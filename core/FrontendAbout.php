<?php
/**
 * 文件：core/FrontendAbout.php
 * 作用：前台主题 · 关于页内容（由绑定文章驱动；主题禁止直读库）
 */

class FrontendAbout
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
     * 已发布且绑定关于页的文章；无绑定时返回 null（主题可显示占位）
     *
     * @return array|null
     */
    public static function getBoundArticle()
    {
        if (!class_exists('ContentManager') || !ContentManager::tableReady()) {
            return null;
        }
        $row = ContentManager::findBoundAboutRow();
        if (!is_array($row)) {
            return null;
        }
        $status = ContentManager::normalizeStatus(isset($row['status']) ? $row['status'] : 0);
        $bind = ContentManager::normalizeBindPage(isset($row['bindpage']) ? $row['bindpage'] : 0);
        if ($status !== ContentManager::STATUS_PUBLISHED || $bind !== ContentManager::BIND_ABOUT) {
            return null;
        }
        $title = trim((string) (isset($row['title']) ? $row['title'] : ''));
        $body = isset($row['body']) ? (string) $row['body'] : '';
        if ($title === '' && $body === '') {
            return null;
        }
        return array(
            'id'          => (int) (isset($row['id']) ? $row['id'] : 0),
            'title'       => $title !== '' ? $title : '关于我们',
            'summary'     => trim((string) (isset($row['summary']) ? $row['summary'] : '')),
            'body'        => $body,
            'body_html'   => self::bodyHtml($body),
            'createtime'  => isset($row['createtime']) ? (string) $row['createtime'] : '',
        );
    }
}
