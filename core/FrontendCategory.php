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

    /** config.apiorder：0=随机（默认） 1=按分类排序权重 */
    const ORDER_RANDOM = 0;
    const ORDER_BY_CATEGORY = 1;
    const CONFIG_KEY_ORDER = 'apiorder';

    /**
     * @return int
     */
    public static function tagVisibleLimit()
    {
        return self::TAG_VISIBLE_LIMIT;
    }

    /**
     * 接口目录排序模式（0 随机 / 1 按分类权重）
     *
     * @return int
     */
    public static function orderMode()
    {
        $raw = class_exists('Config') ? (string) Config::get(self::CONFIG_KEY_ORDER, '0') : '0';
        return $raw === '1' ? self::ORDER_BY_CATEGORY : self::ORDER_RANDOM;
    }

    /**
     * @return bool
     */
    public static function isRandomOrder()
    {
        return self::orderMode() === self::ORDER_RANDOM;
    }

    /**
     * 已启用分类数量（不含「全部」）
     *
     * @return int
     */
    public static function countEnabled()
    {
        return count(self::listTagsCanonical());
    }

    /**
     * 供主题渲染分类标签的列表（与下属接口数量无关）
     * 缓存存有序底稿；随机模式下每次返回前临时打乱副本
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function listTags()
    {
        $items = self::listTagsCanonical();
        if (self::isRandomOrder() && count($items) > 1) {
            $copy = $items;
            shuffle($copy);
            return $copy;
        }
        return $items;
    }

    /**
     * 有序分类标签（Redis 缓存底稿，不打乱）
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function listTagsCanonical()
    {
        $factory = function () {
            return self::buildTagsFresh();
        };
        if (class_exists('RedisCache')) {
            $cached = RedisCache::remember(RedisCache::KEY_FRONTEND_CATEGORY, RedisCache::TTL_FRONTEND_CATEGORY, $factory);
            return is_array($cached) ? $cached : $factory();
        }
        return $factory();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private static function buildTagsFresh()
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
     * 分类名称 => 排序权重（启用分类；未知归末尾）
     *
     * @return array<string, int>
     */
    public static function nameToSortMap()
    {
        $map = array();
        foreach (ApiCategoryManager::listEnabled() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($name === '') {
                continue;
            }
            if (!isset($map[$name])) {
                $map[$name] = isset($row['sort']) ? (int) $row['sort'] : 0;
            }
        }
        return $map;
    }

    /**
     * id => 名称映射（含 all => 全部，供 JS categoryNames 等）
     *
     * @return array<string, string>
     */
    public static function nameMap()
    {
        $map = array(self::ALL_ID => self::ALL_NAME);
        foreach (self::listTagsCanonical() as $tag) {
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
        foreach (self::listTagsCanonical() as $tag) {
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
