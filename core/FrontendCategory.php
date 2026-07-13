<?php
/**
 * 文件：core/FrontendCategory.php
 * 作用：前台主题 · 接口分类数据（统一调度，主题只调用本类，不直接访问数据库表/字段）
 *
 * 用法（任意主题 pages/*.php）：
 *   $tags = FrontendCategory::listTags();
 *   $names = FrontendCategory::nameMap();
 *   $limit = FrontendCategory::tagVisibleLimit();
 */

class FrontendCategory
{
    /** 前台「全部」分类键（与 JS 筛选一致） */
    const ALL_ID = 'all';

    /** 前台「全部」显示名 */
    const ALL_NAME = '全部';

    /** 分类标签默认可见数量（不含「全部」） */
    const TAG_VISIBLE_LIMIT = 15;

    /**
     * @return int
     */
    public static function tagVisibleLimit()
    {
        return self::TAG_VISIBLE_LIMIT;
    }

    /**
     * 已启用分类数量（不含「全部」）
     *
     * @return int
     */
    public static function countEnabled()
    {
        return count(self::listTags());
    }

    /**
     * 供主题渲染分类标签的列表（与下属接口数量无关）
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function listTags()
    {
        $items = array();
        foreach (ApiCategoryManager::listEnabled() as $row) {
            $formatted = ApiCategoryManager::formatRow($row);
            if ($formatted === null) {
                continue;
            }
            $id = (int) $formatted['id'];
            $name = trim((string) $formatted['name']);
            if ($id <= 0 || $name === '') {
                continue;
            }
            $items[] = array(
                'id'   => (string) $id,
                'name' => $name,
            );
        }
        return $items;
    }

    /**
     * id => 名称映射（含 all => 全部，供 JS categoryNames 等）
     *
     * @return array<string, string>
     */
    public static function nameMap()
    {
        $map = array(self::ALL_ID => self::ALL_NAME);
        foreach (self::listTags() as $tag) {
            $map[$tag['id']] = $tag['name'];
        }
        return $map;
    }

    /**
     * 分类名称 => id（接口 category 字段为名称时使用）
     *
     * @return array<string, string>
     */
    public static function nameToIdMap()
    {
        $map = array();
        foreach (self::listTags() as $tag) {
            if (!isset($map[$tag['name']])) {
                $map[$tag['name']] = $tag['id'];
            }
        }
        return $map;
    }

    /**
     * 将接口行的分类名称解析为前台统一 id；未知则返回空字符串（仅「全部」可见）
     *
     * @param string $categoryName
     * @return string
     */
    public static function resolveIdByName($categoryName)
    {
        $categoryName = trim((string) $categoryName);
        if ($categoryName === '') {
            return '';
        }
        $map = self::nameToIdMap();
        return isset($map[$categoryName]) ? $map[$categoryName] : '';
    }
}
