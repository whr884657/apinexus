<?php
/**
 * 文件：core/MailChannels.php
 * 作用：邮箱模板 bindpage 场景 + 全局英文占位符目录（v13.26.47）
 *
 * 占位符铁律：一键一义、全场景可写、无下划线短连写；未赋值替换为空串。
 * UI 按钮中文，插入 {{英文键}}。不做旧中文键运行时兼容。
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
    const API_STATUS_SUB = 27;

    /** 验证码 30～34（无 mail_notify_*，靠 SMTP 总开关 + 入口 send_code） */
    const OTP_USER_REGISTER = 30;
    const OTP_USER_LOGIN = 31;
    const OTP_USER_FORGOT = 32;
    const OTP_ADMIN_LOGIN = 33;
    const OTP_ADMIN_FORGOT = 34;

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
            self::API_STATUS_SUB => array('label' => '接口状态订阅', 'config' => 'mail_notify_apistatus'),
            self::OTP_USER_REGISTER => array('label' => '用户注册验证码', 'config' => ''),
            self::OTP_USER_LOGIN => array('label' => '用户登录验证码', 'config' => ''),
            self::OTP_USER_FORGOT => array('label' => '用户忘记密码验证码', 'config' => ''),
            self::OTP_ADMIN_LOGIN => array('label' => '管理员登录验证码', 'config' => ''),
            self::OTP_ADMIN_FORGOT => array('label' => '管理员忘记密码验证码', 'config' => ''),
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
     * 业务通知 10～27（渲染后可剥站内可点击链；场景 27 保留纯文本详情地址；验证码不剥）
     *
     * @param mixed $bind
     * @return bool
     */
    public static function isBusinessChannel($bind)
    {
        $n = (int) $bind;
        return $n >= self::API_SUBMIT && $n <= self::API_STATUS_SUB;
    }

    /**
     * @param mixed $bind
     * @return bool
     */
    public static function isOtpChannel($bind)
    {
        $n = (int) $bind;
        return $n >= self::OTP_USER_REGISTER && $n <= self::OTP_ADMIN_FORGOT;
    }

    /**
     * 邮箱模板允许的 bindpage：0 / 业务 10～27 / 验证码 30～34
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
            return '自定义（群发可用）';
        }
        $cat = self::catalog();
        if (isset($cat[$n]['label'])) {
            return (string) $cat[$n]['label'];
        }
        return '未知场景';
    }

    /**
     * 下拉选项（含自定义；文案仅中文场景名）
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
     * 全局占位符目录：key=英文短连写，label=按钮中文
     *
     * @return array<int,array{key:string,label:string}>
     */
    public static function placeholderCatalog()
    {
        return array(
            array('key' => 'browsertitle', 'label' => '浏览器标题'),
            array('key' => 'systemname', 'label' => '系统名称'),
            array('key' => 'siteurl', 'label' => '本站链接'),
            array('key' => 'username', 'label' => '用户名'),
            array('key' => 'userid', 'label' => '用户编号'),
            array('key' => 'useremail', 'label' => '用户邮箱'),
            array('key' => 'userrole', 'label' => '用户角色'),
            array('key' => 'useravatar', 'label' => '用户头像'),
            array('key' => 'useravatarurl', 'label' => '用户头像地址'),
            array('key' => 'balance', 'label' => '积分余额'),
            array('key' => 'date', 'label' => '日期'),
            array('key' => 'time', 'label' => '时间'),
            array('key' => 'datetime', 'label' => '日期时间'),
            array('key' => 'year', 'label' => '年'),
            array('key' => 'month', 'label' => '月'),
            array('key' => 'day', 'label' => '日'),
            array('key' => 'weekday', 'label' => '星期'),
            array('key' => 'hour', 'label' => '时'),
            array('key' => 'minute', 'label' => '分'),
            array('key' => 'second', 'label' => '秒'),
            array('key' => 'apiname', 'label' => '接口名称'),
            array('key' => 'apiid', 'label' => '接口编号'),
            array('key' => 'apiurl', 'label' => '接口地址'),
            array('key' => 'apidesc', 'label' => '接口描述'),
            array('key' => 'apidetailurl', 'label' => '接口详情地址'),
            array('key' => 'apistatuslabel', 'label' => '接口变更'),
            array('key' => 'apisublist', 'label' => '订阅变更列表'),
            array('key' => 'submitter', 'label' => '提交者'),
            array('key' => 'rejectreason', 'label' => '拒绝原因'),
            array('key' => 'linkname', 'label' => '友链网站名'),
            array('key' => 'linkurl', 'label' => '友链申请网址'),
            array('key' => 'linkcontact', 'label' => '友链联系方式'),
            array('key' => 'linkid', 'label' => '友链编号'),
            array('key' => 'feedbackid', 'label' => '反馈编号'),
            array('key' => 'feedbackbody', 'label' => '反馈内容'),
            array('key' => 'feedbackreply', 'label' => '处理回复'),
            array('key' => 'commentid', 'label' => '评论编号'),
            array('key' => 'articletitle', 'label' => '文章标题'),
            array('key' => 'articleurl', 'label' => '文章链接'),
            array('key' => 'commenter', 'label' => '评论者'),
            array('key' => 'commentbody', 'label' => '评论内容'),
            array('key' => 'quotebody', 'label' => '引用评论'),
            array('key' => 'isquote', 'label' => '是否引用'),
            array('key' => 'adminreply', 'label' => '管理员回复'),
            array('key' => 'notifytype', 'label' => '通知类型'),
            array('key' => 'keyname', 'label' => '令牌名称'),
            array('key' => 'keyid', 'label' => '令牌编号'),
            array('key' => 'keyquota', 'label' => '分配配额'),
            array('key' => 'keyquotafallback', 'label' => '配额回落'),
            array('key' => 'pointsneed', 'label' => '本次需要积分'),
            array('key' => 'pointscredit', 'label' => '到账积分'),
            array('key' => 'orderno', 'label' => '订单号'),
            array('key' => 'orderincome', 'label' => '本单收入'),
            array('key' => 'orderincometoday', 'label' => '今日累计充值收入'),
            array('key' => 'applyid', 'label' => '申请编号'),
            array('key' => 'applyreason', 'label' => '申请理由'),
            array('key' => 'applicant', 'label' => '申请人'),
            array('key' => 'handlereply', 'label' => '审核回复'),
            array('key' => 'otpcode', 'label' => '验证码'),
            array('key' => 'otpttl', 'label' => '验证码有效分钟'),
            array('key' => 'otpaction', 'label' => '验证码动作'),
        );
    }

    /**
     * @return array<int,string>
     */
    public static function allPlaceholderKeys()
    {
        $keys = array();
        foreach (self::placeholderCatalog() as $row) {
            $keys[] = (string) $row['key'];
        }
        return $keys;
    }

    /**
     * 模板编辑器：固定全表（不随 bind 增减）
     *
     * @return array{items:array<int,array{key:string,label:string}>}
     */
    public static function placeholderCatalogForUi()
    {
        return array(
            'items' => self::placeholderCatalog(),
        );
    }

    /**
     * 渲染前：全表缺省空串，避免留下字面 {{键}}
     *
     * @param mixed                $bind 保留参数以兼容调用方；不再按场景裁剪键
     * @param array<string,string> $vars
     * @return array<string,string>
     */
    public static function prepareVars($bind, array $vars)
    {
        unset($bind);
        $out = array();
        foreach (self::allPlaceholderKeys() as $key) {
            if (array_key_exists($key, $vars)) {
                $out[$key] = (string) $vars[$key];
            } else {
                $out[$key] = '';
            }
        }
        return $out;
    }

    /**
     * @deprecated v13.26.47 起与全表键相同；保留以免第三方 fatal
     * @return array<int,string>
     */
    public static function commonPlaceholderKeys()
    {
        return self::allPlaceholderKeys();
    }

    /**
     * @deprecated v13.26.47 起不再按场景裁剪；保留以免第三方 fatal
     * @param mixed $bind
     * @return array<int,string>
     */
    public static function scenarioPlaceholderKeys($bind = 0)
    {
        unset($bind);
        return self::allPlaceholderKeys();
    }

    /**
     * 正文是否含 {{otpcode}}（验证码场景强制）
     *
     * @param string $body
     * @return bool
     */
    public static function bodyHasOtpCodePlaceholder($body)
    {
        return (bool) preg_match('/\{\{\s*otpcode\s*\}\}/u', (string) $body);
    }

    /**
     * 文本是否仍含「可映射」的旧中文占位（不可映射错别字不阻塞）
     *
     * @param string $text
     * @return bool
     */
    public static function textHasMappableChinesePlaceholder($text)
    {
        $text = (string) $text;
        if ($text === '' || strpos($text, '{{') === false) {
            return false;
        }
        foreach (array_keys(self::legacyChineseKeyMap()) as $old) {
            if (preg_match('/\{\{\s*' . preg_quote($old, '/') . '\s*\}\}/u', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 旧中文占位 → 英文键（仅升级迁移用；运行时不认中文）
     *
     * @return array<string,string> 旧键 => 新键
     */
    public static function legacyChineseKeyMap()
    {
        return array(
            '浏览器标题' => 'browsertitle',
            '站点名称' => 'browsertitle',
            '站点名' => 'browsertitle',
            '系统名称' => 'systemname',
            '系统名' => 'systemname',
            '本站链接' => 'siteurl',
            '站点链接' => 'siteurl',
            '站点地址' => 'siteurl',
            '用户名' => 'username',
            '用户编号' => 'userid',
            '用户邮箱' => 'useremail',
            '用户角色' => 'userrole',
            '用户头像' => 'useravatar',
            '用户头像地址' => 'useravatarurl',
            '积分余额' => 'balance',
            '余额' => 'balance',
            '日期' => 'date',
            '时间' => 'time',
            '日期时间' => 'datetime',
            '年' => 'year',
            '月' => 'month',
            '日' => 'day',
            '星期' => 'weekday',
            '时' => 'hour',
            '分' => 'minute',
            '秒' => 'second',
            '接口名称' => 'apiname',
            '接口名' => 'apiname',
            '关联接口' => 'apiname',
            '接口编号' => 'apiid',
            '接口地址' => 'apiurl',
            '提交者' => 'submitter',
            '拒绝原因' => 'rejectreason',
            '原因说明' => 'rejectreason',
            '友链网站名' => 'linkname',
            '网站名称' => 'linkname',
            '网站名' => 'linkname',
            '友链申请网址' => 'linkurl',
            '网站链接' => 'linkurl',
            '友链联系方式' => 'linkcontact',
            '联系方式' => 'linkcontact',
            '友链编号' => 'linkid',
            '编号' => 'linkid',
            '反馈编号' => 'feedbackid',
            '反馈内容' => 'feedbackbody',
            '处理回复' => 'feedbackreply',
            '审核回复' => 'handlereply',
            '提交用户' => 'username',
            '评论编号' => 'commentid',
            '文章标题' => 'articletitle',
            '标题' => 'articletitle',
            '关联文章' => 'articletitle',
            '文章链接' => 'articleurl',
            '评论者' => 'commenter',
            '评论内容' => 'commentbody',
            '引用评论' => 'quotebody',
            '原评论' => 'quotebody',
            '是否引用' => 'isquote',
            '管理员回复' => 'adminreply',
            '通知类型' => 'notifytype',
            '令牌备注' => 'keyname',
            '令牌名称' => 'keyname',
            '令牌编号' => 'keyid',
            '分配配额' => 'keyquota',
            '配额回落' => 'keyquotafallback',
            '本次需要积分' => 'pointsneed',
            '本次需要' => 'pointsneed',
            '到账积分' => 'pointscredit',
            '订单号' => 'orderno',
            '本单收入' => 'orderincome',
            '今日累计充值收入' => 'orderincometoday',
            '申请编号' => 'applyid',
            '申请理由' => 'applyreason',
            '申请人' => 'applicant',
            '注册邮箱' => 'useremail',
            '验证码' => 'otpcode',
            '验证码有效分钟' => 'otpttl',
            '验证码动作' => 'otpaction',
        );
    }

    /**
     * 文本内 {{旧中文}} → {{英文}}（最长键优先；preg 失败保留原串）
     *
     * @param string $text
     * @param int    $bindpage 模板绑定；开发者 24～26 时「处理回复」→ handlereply
     * @return string
     */
    public static function migrateChinesePlaceholdersInText($text, $bindpage = 0)
    {
        $text = (string) $text;
        if ($text === '' || strpos($text, '{{') === false) {
            return $text;
        }
        $bindpage = (int) $bindpage;
        $map = self::legacyChineseKeyMap();
        if ($bindpage === self::DEV_APPLY || $bindpage === self::DEV_PASS || $bindpage === self::DEV_FAIL) {
            $map['处理回复'] = 'handlereply';
        }
        $keys = array_keys($map);
        usort($keys, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        foreach ($keys as $old) {
            $new = $map[$old];
            $prev = $text;
            $replaced = preg_replace(
                '/\{\{\s*' . preg_quote($old, '/') . '\s*\}\}/u',
                '{{' . $new . '}}',
                $text
            );
            if (!is_string($replaced)) {
                $text = $prev;
                continue;
            }
            $text = $replaced;
        }
        if ($bindpage === self::BIND_CUSTOM || $bindpage === 0) {
            if (strpos($text, '{{applicant}}') !== false || strpos($text, '{{applyid}}') !== false
                || strpos($text, '{{applyreason}}') !== false) {
                $text = str_replace('{{feedbackreply}}', '{{handlereply}}', $text);
            }
        }
        return $text;
    }
}
