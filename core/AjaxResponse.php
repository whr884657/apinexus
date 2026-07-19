<?php
/**
 * 文件：core/AjaxResponse.php
 * 作用：后台/安装 AJAX JSON 响应
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class AjaxResponse
{
    /**
     * @param array $data
     * @param int   $httpCode
     * @return void
     */
    public static function json(array $data, $httpCode = 200)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $encoded = json_encode($data, $flags);
        if ($encoded === false) {
            $encoded = json_encode(array(
                'code' => 0,
                'msg'  => '响应编码失败，请缩小返回体或检查上游 Content-Type',
            ), JSON_UNESCAPED_UNICODE);
        }
        echo $encoded !== false ? $encoded : '{"code":0,"msg":"响应编码失败"}';
        exit;
    }

    /**
     * @param string $msg
     * @param array  $extra
     * @return void
     */
    public static function success($msg, array $extra = array())
    {
        self::json(array_merge(array('code' => 1, 'msg' => $msg), $extra));
    }

    /**
     * @param string $msg
     * @param int    $httpCode
     * @return void
     */
    public static function error($msg, $httpCode = 200)
    {
        self::json(array('code' => 0, 'msg' => $msg), $httpCode);
    }
}
