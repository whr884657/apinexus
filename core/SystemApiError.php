<?php
/**
 * 文件：core/SystemApiError.php
 * 作用：系统级外部 API 业务失败码（12xxx；与公开网关 11xxx 分离）
 *
 * 对外 JSON：{ "code": 0, "msg": "…", "errcode": 12001 }
 * HTTP 传输层固定 200，业务成败看 code / errcode（见《失败代码规范》《系统级外部API规范》）
 *
 * 范围：12001～12015（扩码时同步规范、README、Catalog 响应示例）
 */

if (!defined('VS_ROOT')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

class SystemApiError
{
    /** 系统密钥无效或未提供 */
    const UNAUTH = 12001;
    /**
     * 该系统 API 未启用（开关关闭）
     * 入口闸关已与 UNAUTH 统一（E412），本码保留兼容/内部业务路径，勿再用于「先鉴权再报闸关」
     */
    const API_OFF = 12002;
    /** 参数错误（缺少/非法） */
    const BAD_PARAM = 12003;
    /** 参数重复且冲突（Query/POST 与 JSON 同名不同值） */
    const PARAM_CONFLICT = 12004;
    /** 请求过于频繁 */
    const RATE_LIMIT = 12005;
    /** 请求体过大 */
    const BODY_TOO_LARGE = 12006;
    /** 不支持 JSONP */
    const JSONP_FORBIDDEN = 12007;
    /** 系统未安装 */
    const NOT_INSTALLED = 12008;
    /** 依赖未就绪（表/能力） */
    const NOT_READY = 12009;
    /** 目标不存在或不可用 */
    const NOT_FOUND = 12010;
    /** 通用业务失败 */
    const BUSINESS = 12011;
    /** 用户未注册 */
    const USER_NOT_REGISTERED = 12012;
    /** 账户已封禁 */
    const USER_BANNED = 12013;
    /** 业务功能未开启（如每日签到功能） */
    const FEATURE_OFF = 12014;
    /** 已完成 / 重复操作（如今日已签到） */
    const ALREADY_DONE = 12015;

    /**
     * @return array<int,string>
     */
    public static function businessLabelMap()
    {
        return array(
            self::UNAUTH               => '系统密钥无效或未提供（含开关未开）',
            self::API_OFF              => '该系统 API 未启用（入口已并入 UNAUTH）',
            self::BAD_PARAM            => '参数错误',
            self::PARAM_CONFLICT       => '参数重复且冲突',
            self::RATE_LIMIT           => '请求过于频繁',
            self::BODY_TOO_LARGE       => '请求体过大',
            self::JSONP_FORBIDDEN      => '不支持 JSONP',
            self::NOT_INSTALLED        => '系统未安装',
            self::NOT_READY            => '依赖未就绪',
            self::NOT_FOUND            => '目标不存在或不可用',
            self::BUSINESS             => '业务处理失败',
            self::USER_NOT_REGISTERED  => '用户未注册',
            self::USER_BANNED          => '账户已封禁',
            self::FEATURE_OFF          => '业务功能未开启',
            self::ALREADY_DONE         => '已完成或请勿重复操作',
        );
    }

    /**
     * @param int $errcode
     * @return string
     */
    public static function label($errcode)
    {
        $map = self::businessLabelMap();
        $code = (int) $errcode;
        return isset($map[$code]) ? $map[$code] : '系统级业务错误';
    }

    /**
     * @param int $errcode
     * @return bool
     */
    public static function isKnown($errcode)
    {
        $code = (int) $errcode;
        return $code >= 12001 && $code <= 12015;
    }

    /**
     * @param int $errcode
     * @return bool
     */
    public static function isBusinessFailure($errcode)
    {
        return self::isKnown($errcode);
    }
}
