<?php

/**
 * 文件：core/SiteMedia.php
 * 作用：站点内置图片（分类图标、语言图标、头像、支付/备案图标等）统一经此类解析出站 URL
 *
 * 约定：物理文件仍在 assets/img/；主题与业务代码禁止手写拼 /assets/img/…，须调用本类。
 */

if (!class_exists('SiteMedia')) {

class SiteMedia
{
    /**
     * 相对 assets/img/ 的路径 → 完整 URL（文件不存在返回空串）
     *
     * @param string $relative 如 QQ.svg、avatar/a.png、category-icons/1.svg、lang/php.svg
     * @return string
     */
    public static function imgUrl($relative)
    {
        $rel = self::normalizeRel($relative);
        if ($rel === '') {
            return '';
        }
        $full = VS_ROOT . '/assets/img/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($full)) {
            return '';
        }
        return rtrim(vs_base_url(), '/') . '/assets/img/' . self::encodeRel($rel);
    }

    /**
     * 相对路径 → 站内路径 /assets/img/...（供入库或前端拼 base；不做文件存在性强制）
     *
     * @param string $relative
     * @return string
     */
    public static function imgWebPath($relative)
    {
        $rel = self::normalizeRel($relative);
        if ($rel === '') {
            return '';
        }
        return '/assets/img/' . $rel;
    }

    /**
     * 把已入库或历史硬编码的路径/URL 规范成可展示的完整 URL
     *
     * @param string $stored
     * @return string
     */
    public static function resolve($stored)
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }
        // 仅允许 http(s) 外链图；拒绝 javascript: / data: / 协议相对 //evil
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        if (strpos($stored, '//') === 0 || strpos($stored, ':') !== false) {
            return '';
        }
        // /assets/img/xxx 或 assets/img/xxx
        if (preg_match('#^/?assets/img/(.+)$#i', $stored, $m)) {
            return self::imgUrl($m[1]);
        }
        // 已是相对 img 下（须真实存在文件）
        if (strpos($stored, '..') === false) {
            $try = self::imgUrl(ltrim($stored, '/'));
            if ($try !== '') {
                return $try;
            }
        }
        // 禁止把任意站内绝对路径拼进媒体 URL（防绕过到非 img 资源）
        return '';
    }

    /**
     * @param string $relative
     * @return string
     */
    private static function normalizeRel($relative)
    {
        $rel = str_replace('\\', '/', trim((string) $relative));
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        if (stripos($rel, 'assets/img/') === 0) {
            $rel = substr($rel, strlen('assets/img/'));
        }
        if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $rel)) {
            return '';
        }
        return $rel;
    }

    /**
     * @param string $rel
     * @return string
     */
    private static function encodeRel($rel)
    {
        $parts = explode('/', $rel);
        $out = array();
        foreach ($parts as $p) {
            $out[] = rawurlencode($p);
        }
        return implode('/', $out);
    }
}

}
