<?php
/**
 * 文件：core/MailChannels.php
 * 作用：邮箱模板 bindpage 场景码与文案对照（群发仅 bindpage=0）
 */

class MailChannels
{
    /** 自定义 / 群发可选 */
    const BIND_CUSTOM = 0;

    /** 业务通知 10～26 */
    const API_SUBMIT = 10;
    const API_PASS = 11;
    const API_FAIL = 12;
    const LINK_APPLY = 13;
    const LINK_PASS = 14;
    const FEEDBACK_ADMIN = 15;
    const FEEDBACK_USER = 16;
    const COMMENT_ADMIN = 17;
    const COMMENT_USER = 18;
    const POINTS_ZERO = 19;
    const POINTS_INSUFFICIENT = 20;
    const KEY_QUOTA = 21;
    const RECHARGE_SUCCESS = 22;
    const ORDER_ADMIN = 23;
    const DEV_APPLY = 24;
    const DEV_PASS = 25;
    const DEV_FAIL = 26;

    /**
     * @return array<int,array{label:string,config:string}>
     */
    public static function catalog()
    {
        return array(
            self::API_SUBMIT => array('label' => '接口投稿通知管理员', 'config' => 'mail_notify_submit'),
            self::API_PASS => array('label' => '接口审核通过', 'config' => 'mail_notify_pass'),
            self::API_FAIL => array('label' => '接口审核拒绝', 'config' => 'mail_notify_fail'),
            self::LINK_APPLY => array('label' => '友链申请通知管理员', 'config' => 'mail_notify_link_apply'),
            self::LINK_PASS => array('label' => '友链审核通过', 'config' => 'mail_notify_link_pass'),
            self::FEEDBACK_ADMIN => array('label' => '新反馈通知', 'config' => 'mail_notify_feedback_admin'),
            self::FEEDBACK_USER => array('label' => '反馈已处理', 'config' => 'mail_notify_feedback'),
            self::COMMENT_ADMIN => array('label' => '新评论通知管理员', 'config' => 'mail_notify_comment_admin'),
            self::COMMENT_USER => array('label' => '评论被引用/回复', 'config' => 'mail_notify_comment'),
            self::POINTS_ZERO => array('label' => '积分归零', 'config' => 'mail_notify_points_zero'),
            self::POINTS_INSUFFICIENT => array('label' => '积分不足', 'config' => 'mail_notify_points_insufficient'),
            self::KEY_QUOTA => array('label' => '令牌配额用尽', 'config' => 'mail_notify_key_quota'),
            self::RECHARGE_SUCCESS => array('label' => '充值成功通知用户', 'config' => 'mail_notify_recharge_success'),
            self::ORDER_ADMIN => array('label' => '充值成功通知管理员', 'config' => 'mail_notify_order_admin'),
            self::DEV_APPLY => array('label' => '开发者申请通知管理员', 'config' => 'mail_notify_dev_apply'),
            self::DEV_PASS => array('label' => '开发者申请通过', 'config' => 'mail_notify_dev_pass'),
            self::DEV_FAIL => array('label' => '开发者申请拒绝', 'config' => 'mail_notify_dev_fail'),
        );
    }

    /**
     * @param mixed $bind
     * @return bool
     */
    public static function isSystemChannel($bind)
    {
        $n = (int) $bind;
        $cat = self::catalog();
        return isset($cat[$n]);
    }

    /**
     * 邮箱模板允许的 bindpage：0 或业务 10～26
     *
     * @param mixed $bind
     * @return bool
     */
    public static function isMailBindAllowed($bind)
    {
        $n = (int) $bind;
        if ($n === self::BIND_CUSTOM) {
            return true;
        }
        return self::isSystemChannel($n);
    }

    /**
     * @param mixed $bind
     * @return int
     */
    public static function normalizeMailBind($bind)
    {
        $n = (int) $bind;
        if ($n === self::BIND_CUSTOM || self::isSystemChannel($n)) {
            return $n;
        }
        return self::BIND_CUSTOM;
    }

    /**
     * @param mixed $bind
     * @return string
     */
    public static function label($bind)
    {
        $n = (int) $bind;
        if ($n === self::BIND_CUSTOM) {
            return '自定义';
        }
        $cat = self::catalog();
        if (isset($cat[$n]['label'])) {
            return (string) $cat[$n]['label'];
        }
        return '未知场景';
    }

    /**
     * 下拉选项（含自定义）
     *
     * @return array<int,string> bindpage => label
     */
    public static function optionsForSelect()
    {
        $out = array(self::BIND_CUSTOM => '自定义（群发可用）');
        foreach (self::catalog() as $code => $row) {
            $out[(int) $code] = (string) $row['label'];
        }
        return $out;
    }

    /**
     * 公共占位符键（群发 + 任意模板；不含场景专属）
     *
     * @return array<int,string>
     */
    public static function commonPlaceholderKeys()
    {
        return array(
            '用户名', '站点名', '站点名称', '站点链接', '站点地址', '系统名',
            '用户编号', '用户邮箱', '用户角色', '用户头像', '用户头像地址',
            '余额', '日期', '时间', '日期时间',
        );
    }

    /**
     * 某绑定场景的专属占位符键（含常用别名，便于模板书写）
     *
     * @param mixed $bind
     * @return array<int,string>
     */
    public static function scenarioPlaceholderKeys($bind)
    {
        $n = (int) $bind;
        switch ($n) {
            case self::API_SUBMIT:
                return array('接口名', '接口地址', '接口编号', '提交者');
            case self::API_PASS:
                return array('接口名', '接口编号');
            case self::API_FAIL:
                return array('接口名', '接口编号', '拒绝原因', '原因说明');
            case self::LINK_APPLY:
                return array('网站名称', '网站名', '网站链接', '联系方式', '编号');
            case self::LINK_PASS:
                return array('网站名称', '网站名', '网站链接');
            case self::FEEDBACK_ADMIN:
                return array('反馈编号', '关联接口', '接口名', '接口编号', '提交用户', '反馈内容');
            case self::FEEDBACK_USER:
                return array('关联接口', '接口名', '反馈内容', '处理回复');
            case self::COMMENT_ADMIN:
                return array('评论编号', '关联文章', '标题', '评论者', '是否引用', '引用评论', '评论内容');
            case self::COMMENT_USER:
                return array('关联文章', '标题', '评论者', '原评论', '评论内容', '管理员回复', '通知类型');
            case self::POINTS_ZERO:
                return array('余额');
            case self::POINTS_INSUFFICIENT:
                return array('余额', '本次需要');
            case self::KEY_QUOTA:
                return array('令牌备注', '令牌名称', '令牌编号', '分配配额', '配额回落');
            case self::RECHARGE_SUCCESS:
                return array('到账积分', '余额', '订单号');
            case self::ORDER_ADMIN:
                return array('订单号', '本单收入', '到账积分', '今日累计充值收入');
            case self::DEV_APPLY:
                return array('申请编号', '申请人', '注册邮箱', '申请理由');
            case self::DEV_PASS:
            case self::DEV_FAIL:
                return array('申请人', '处理回复');
            default:
                return array();
        }
    }

    /**
     * 模板编辑器：公共 + 各场景专属（供 JSON 注入前端）
     *
     * @return array{common:array<int,string>,byBind:array<string,array<int,string>>}
     */
    public static function placeholderCatalogForUi()
    {
        $byBind = array('0' => array());
        foreach (array_keys(self::catalog()) as $code) {
            $byBind[(string) (int) $code] = self::scenarioPlaceholderKeys($code);
        }
        return array(
            'common' => self::commonPlaceholderKeys(),
            'byBind' => $byBind,
        );
    }

    /**
     * 渲染前：补齐场景键为空串 + 双向别名，避免模板留下字面 {{键}}
     *
     * @param mixed                $bind
     * @param array<string,string> $vars
     * @return array<string,string>
     */
    public static function prepareVars($bind, array $vars)
    {
        $scenarioKeys = self::scenarioPlaceholderKeys($bind);
        $inScenario = array();
        foreach ($scenarioKeys as $key) {
            $inScenario[$key] = true;
            if (!array_key_exists($key, $vars)) {
                $vars[$key] = '';
            } else {
                $vars[$key] = (string) $vars[$key];
            }
        }

        // 公共别名始终互拷；场景别名仅当本 bind 登记了任一端键时才同步（防误注入）
        $pairs = array(
            array('站点名称', '站点名', false),
            array('站点地址', '站点链接', false),
            array('接口名', '关联接口', true),
            array('标题', '关联文章', true),
            array('网站名', '网站名称', true),
            array('原因说明', '拒绝原因', true),
            array('令牌名称', '令牌备注', true),
            array('申请人', '用户名', true),
        );
        foreach ($pairs as $pair) {
            $a = $pair[0];
            $b = $pair[1];
            $sceneOnly = !empty($pair[2]);
            if ($sceneOnly && empty($inScenario[$a]) && empty($inScenario[$b])) {
                continue;
            }
            $va = isset($vars[$a]) ? trim((string) $vars[$a]) : '';
            $vb = isset($vars[$b]) ? trim((string) $vars[$b]) : '';
            if ($va === '' && $vb !== '') {
                $vars[$a] = $vb;
            } elseif ($vb === '' && $va !== '') {
                $vars[$b] = $va;
            }
        }

        return $vars;
    }
}
