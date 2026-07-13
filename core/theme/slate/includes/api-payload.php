<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

/**
 * 主题二 · 前台页面数据（分类与公开接口，本主题独立维护）
 */

/** @var int */
const SLATE_THEME_CATEGORY_VISIBLE_LIMIT = 15;

/**
 * @return int
 */
function slate_theme_category_visible_limit()
{
    return SLATE_THEME_CATEGORY_VISIBLE_LIMIT;
}

/**
 * @return array{categoryNames: array<string, string>, apiData: array}
 */
function slate_theme_page_payload()
{
    $categoryNames = ApiCategoryManager::frontendCategoryNames();
    $nameToId = ApiCategoryManager::categoryNameToIdMap();
    $apiData = array();

    foreach (ApiManager::listPublic() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            continue;
        }
        $catLabel = trim((string) (isset($row['category']) ? $row['category'] : ''));
        $catKey = ($catLabel !== '' && isset($nameToId[$catLabel])) ? $nameToId[$catLabel] : '';

        $method = strtoupper(trim((string) (isset($row['method']) ? $row['method'] : 'GET')));
        if ($method === '') {
            $method = 'GET';
        }
        $methods = array_values(array_filter(array_map('trim', explode(',', $method))));
        if ($methods === array()) {
            $methods = array('GET');
        }
        $endpoint = trim((string) (isset($row['endpoint']) ? $row['endpoint'] : ''));

        $apiData[] = array(
            'id'          => (int) $row['id'],
            'name'        => $name,
            'desc'        => trim((string) (isset($row['description']) ? $row['description'] : '')),
            'category'    => $catKey,
            'method'      => $methods[0],
            'methods'     => $methods,
            'endpoint'    => $endpoint,
            'full_url'    => $endpoint,
        );
    }

    return array(
        'categoryNames' => $categoryNames,
        'apiData'       => $apiData,
    );
}
