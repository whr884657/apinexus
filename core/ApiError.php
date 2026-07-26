<?php
/**
 * 文件：core/ApiError.php
 * 作用：公开 API 业务错误码（与 HTTP 网络状态码分离，避免 401/403/503 等重合）
 *
 * 对外 JSON：{ "code": 0, "msg": "…", "errcode": 11001 }
 * HTTP 传输层固定 200，业务成败看 code / errcode。
 */

class ApiError
{
    /** 未提供调用密钥 */
    const NO_KEY = 11001;
    /** 密钥错误（值不对） */
    const BAD_KEY = 11002;
    /** 密钥已禁用 */
    const KEY_DISABLED = 11003;
    /** 积分余额不足 */
    const NO_POINTS = 11004;
    /** 请求过于频繁（QPM） */
    const QPM = 11005;
    /** 接口维护中 */
    const MAINTENANCE = 11006;
    /** 接口已禁用 */
    const DISABLED = 11007;
    /** 接口不可用（审核未通过等） */
    const UNAVAILABLE = 11008;
    /** 密钥校验暂不可用 */
    const KEY_SYSTEM = 11009;
    /** 积分系统暂不可用 */
    const POINTS_SYSTEM = 11010;
    /** 收费接口须提供有效密钥 */
    const CHARGE_NEED_KEY = 11011;
    /** 鉴权方式错误（密钥未按本接口支持的方式传递） */
    const AUTH_WAY = 11012;
    /** 接口不存在 */
    const NOT_FOUND = 11013;
    /** 上游地址无效 / 未配置 */
    const UPSTREAM_BAD = 11014;
    /** 上游地址不允许（内网等） */
    const UPSTREAM_BLOCKED = 11015;
    /** 上游请求失败 */
    const UPSTREAM_FAIL = 11016;
    /** 服务端能力不足（如未启用 curl） */
    const SERVER = 11017;

    /**
     * @param int $errcode
     * @return string
     */
    public static function label($errcode)
    {
        $map = array(
            self::NO_KEY           => '未提供调用密钥',
            self::BAD_KEY          => '密钥错误',
            self::KEY_DISABLED     => '密钥已禁用',
            self::NO_POINTS        => '积分余额不足',
            self::QPM              => '请求过于频繁（QPM 限制）',
            self::MAINTENANCE      => '接口维护中',
            self::DISABLED         => '接口已禁用',
            self::UNAVAILABLE      => '接口不可用',
            self::KEY_SYSTEM       => '密钥校验暂不可用',
            self::POINTS_SYSTEM    => '积分系统暂不可用',
            self::CHARGE_NEED_KEY  => '收费接口须提供有效密钥',
            self::AUTH_WAY         => '鉴权方式错误',
            self::NOT_FOUND        => '接口不存在',
            self::UPSTREAM_BAD     => '上游地址无效',
            self::UPSTREAM_BLOCKED => '上游地址不允许',
            self::UPSTREAM_FAIL    => '上游请求失败',
            self::SERVER           => '服务暂不可用',
            200                    => '成功',
            302                    => '代理跳转成功',
        );
        $code = (int) $errcode;
        return isset($map[$code]) ? $map[$code] : '业务错误';
    }

    /**
     * 是否为已知业务错误码
     *
     * @param int $errcode
     * @return bool
     */
    public static function isKnown($errcode)
    {
        $code = (int) $errcode;
        return $code === 200 || $code === 302 || ($code >= 11001 && $code <= 11017);
    }
}
