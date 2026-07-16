<?php
/**
 * 一言API接口 - v1.php
 * 本地随机一言接口，支持分类请求和随机请求
 *
 * @author 尋鯨錄
 * @version 1.0.0
 * @date 2026-05-02
 *
 * @param int c 分类序号（1-12），不传则随机
 * @return JSON 一言数据
 */

// —— 调用统计（3 行；任意深度；详见 api/统计代码使用说明.md）——
for ($d = __DIR__; !is_file($d . '/core/bootstrap.php') && $d !== dirname($d); $d = dirname($d)) {}
require_once $d . '/core/bootstrap.php';
ApiStats::hit();

// ==================== 跨域请求配置 ====================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== 配置常量 ====================
define('API_DEVELOPER', '尋鯨錄');
define('API_BLOG', 'https://www.xunjinlu.fun');
define('API_PLATFORM', 'https://api.xunjinlu.fun');
define('DATA_DIR', __DIR__ . '/data/');

/**
 * 分类映射表
 * 序号 => [文件名, 分类名称]
 */
$categories = [
    1 => ['动画.txt', '动画'],
    2 => ['漫画.txt', '漫画'],
    3 => ['游戏.txt', '游戏'],
    4 => ['文学.txt', '文学'],
    5 => ['原创.txt', '原创'],
    6 => ['来自网络.txt', '来自网络'],
    7 => ['其他.txt', '其他'],
    8 => ['影视.txt', '影视'],
    9 => ['诗词.txt', '诗词'],
    10 => ['网易云.txt', '网易云'],
    11 => ['哲学.txt', '哲学'],
    12 => ['抖机灵.txt', '抖机灵'],
];

// ==================== 响应函数 ====================

/**
 * 统一JSON响应函数
 * 
 * @description 输出标准化的JSON响应
 * 
 * @param int $code 状态码
 * @param string $msg 状态描述
 * @param mixed $data 返回数据
 * @return void 直接输出JSON
 */
function jsonResponse($code, $msg, $data = null) {
    $response = [
        'code' => $code,
        'msg' => $msg,
        'data' => $data,
        'api_info' => [
            'developer' => API_DEVELOPER,
            'blog' => API_BLOG,
            'api_platform' => API_PLATFORM
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * 获取随机一言
 * 
 * @description 从所有分类中随机获取一条一言
 * 
 * @return array|null 一言数据或null
 */
function getRandomYiyan() {
    global $categories;
    
    // 获取所有可用的分类文件
    $availableFiles = [];
    foreach ($categories as $id => $info) {
        $filePath = DATA_DIR . $info[0];
        if (file_exists($filePath)) {
            $availableFiles[] = $id;
        }
    }
    
    if (empty($availableFiles)) {
        return null;
    }
    
    // 随机选择一个分类
    $randomCategoryId = $availableFiles[array_rand($availableFiles)];
    return getYiyanByCategory($randomCategoryId);
}

/**
 * 根据分类获取随机一言
 * 
 * @description 从指定分类文件中随机获取一条一言
 * 
 * @param int $categoryId 分类序号（1-12）
 * @return array|null 一言数据或null
 */
function getYiyanByCategory($categoryId) {
    global $categories;
    
    // 检查分类是否存在
    if (!isset($categories[$categoryId])) {
        return null;
    }
    
    $fileName = $categories[$categoryId][0];
    $categoryName = $categories[$categoryId][1];
    $filePath = DATA_DIR . $fileName;
    
    // 检查文件是否存在
    if (!file_exists($filePath)) {
        return null;
    }
    
    // 读取文件内容
    $content = file_get_contents($filePath);
    if ($content === false) {
        return null;
    }
    
    // 按行分割
    $lines = explode("\n", $content);
    $validLines = [];
    
    // 过滤空行和无效行
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '|||') !== false) {
            $validLines[] = $line;
        }
    }
    
    if (empty($validLines)) {
        return null;
    }
    
    // 随机选择一行
    $randomLine = $validLines[array_rand($validLines)];
    
    // 解析数据格式：ID|||内容|||来源
    $parts = explode('|||', $randomLine);
    if (count($parts) < 3) {
        return null;
    }
    
    return [
        'id' => trim($parts[0]),
        'content' => trim($parts[1]),
        'source' => trim($parts[2]),
        'category_id' => $categoryId,
        'category_name' => $categoryName
    ];
}

/**
 * 获取所有分类列表
 * 
 * @description 获取所有可用的分类信息
 * 
 * @return array 分类列表
 */
function getCategoryList() {
    global $categories;
    
    $list = [];
    foreach ($categories as $id => $info) {
        $filePath = DATA_DIR . $info[0];
        $count = 0;
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (trim($line) !== '' && strpos($line, '|||') !== false) {
                    $count++;
                }
            }
        }
        
        $list[] = [
            'id' => $id,
            'name' => $info[1],
            'file' => $info[0],
            'count' => $count
        ];
    }
    
    return $list;
}

// ==================== 主业务逻辑 ====================

try {
    // 获取请求参数
    $method = $_SERVER['REQUEST_METHOD'];
    $categoryId = null;
    
    if ($method === 'GET') {
        $categoryId = isset($_GET['c']) ? intval($_GET['c']) : 0;
    } else {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            $categoryId = isset($input['c']) ? intval($input['c']) : 0;
        } else {
            $categoryId = isset($_POST['c']) ? intval($_POST['c']) : 0;
        }
    }
    
    // 获取一言数据
    if ($categoryId > 0) {
        // 指定分类
        if (!isset($categories[$categoryId])) {
            jsonResponse(400, '分类序号无效，有效范围为1-12', [
                'categories' => getCategoryList()
            ]);
            exit;
        }
        
        $yiyan = getYiyanByCategory($categoryId);
        
        if ($yiyan === null) {
            jsonResponse(500, '获取一言数据失败，分类文件可能不存在或为空', null);
            exit;
        }
        
        jsonResponse(200, 'success', $yiyan);
    } else {
        // 随机获取
        $yiyan = getRandomYiyan();
        
        if ($yiyan === null) {
            jsonResponse(500, '获取一言数据失败，数据文件可能不存在或为空', null);
            exit;
        }
        
        jsonResponse(200, 'success (random)', $yiyan);
    }
    
} catch (Throwable $e) {
    jsonResponse(500, '服务器异常: ' . $e->getMessage(), null);
}
