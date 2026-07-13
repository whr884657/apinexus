<?php
if (!defined('VS_THEME_RENDER')) {
    exit;
}

/**
 * 构建前台 JS 使用的 apiData / categoryNames
 *
 * @return array{apiData: array, categoryNames: array}
 */
function vs_theme_api_payload()
{
    $rows = ApiManager::listPublic();
    $apiData = array();
    $categoryNames = array('all' => '全部');
    $catIndex = 1;
    $nameToKey = array();

    if (class_exists('ApiCategoryManager') && ApiCategoryManager::tableReady()) {
        foreach (ApiCategoryManager::listEnabled() as $catRow) {
            if (!is_array($catRow)) {
                continue;
            }
            $label = trim((string) $catRow['name']);
            if ($label === '' || isset($nameToKey[$label])) {
                continue;
            }
            $nameToKey[$label] = (string) $catIndex;
            $categoryNames[(string) $catIndex] = $label;
            $catIndex++;
        }
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($name === '') {
            continue;
        }
        $catLabel = trim((string) (isset($row['category']) ? $row['category'] : ''));
        if ($catLabel === '') {
            $catLabel = '未分类';
        }
        if (!isset($nameToKey[$catLabel])) {
            $nameToKey[$catLabel] = (string) $catIndex;
            $categoryNames[(string) $catIndex] = $catLabel;
            $catIndex++;
        }
        $catKey = $nameToKey[$catLabel];

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
            'backup_url'  => '',
            'params'      => '',
            'maintenance' => 0,
            'require_api_key' => 0,
            'points_cost' => 0,
        );
    }

    return array(
        'apiData'         => $apiData,
        'categoryNames'   => $categoryNames,
    );
}
