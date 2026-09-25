<?php
/**
 * 文件：core/PayConfig.php
 * 作用：码支付与积分充值相关系统配置读写
 */

class PayConfig
{
    const KEY_URL = 'pay_url';
    const KEY_PID = 'pay_pid';
    const KEY_KEY = 'pay_key';
    const KEY_CHANNEL = 'pay_channel';
    const KEY_METHODS = 'pay_methods';
    const KEY_RATE = 'pay_rate';
    const KEY_PACKAGES = 'pay_packages';
    /** 自定义金额阶梯优惠 JSON：[{min,max,percent},…]；max=0 表示不封顶 */
    const KEY_CUSTOM_BONUS = 'pay_custom_bonus';
    /** 用户充值页说明（Markdown 明文，空则前端不展示） */
    const KEY_TIP_PACKAGE = 'pay_tip_package';
    const KEY_TIP_CUSTOM = 'pay_tip_custom';
    const KEY_TIP_CARDKEY = 'pay_tip_cardkey';
    const TIP_MAX_LEN = 50000;
    const BONUS_MAX_TIERS = 20;
    const BONUS_PERCENT_MAX = 1000;

    /**
     * @return array
     */
    public static function all()
    {
        return array(
            'url'          => trim((string) Config::get(self::KEY_URL, '')),
            'pid'          => trim((string) Config::get(self::KEY_PID, '')),
            'key'          => (string) Config::get(self::KEY_KEY, ''),
            'channel'      => self::channels(),
            'methods'      => self::methods(),
            'rate'          => self::rate(),
            'packages'      => self::packages(),
            'custom_bonus'  => self::customBonus(),
            'tip_package'   => self::tip(self::KEY_TIP_PACKAGE),
            'tip_custom'    => self::tip(self::KEY_TIP_CUSTOM),
            'tip_cardkey'   => self::tip(self::KEY_TIP_CARDKEY),
            'ready'         => self::isReady(),
        );
    }

    /**
     * 读取单条充值说明（Markdown 明文）
     *
     * @param string $key
     * @return string
     */
    public static function tip($key)
    {
        $raw = Config::get($key, '');
        if (function_exists('vs_ensure_plaintext_field')) {
            $raw = vs_ensure_plaintext_field($raw);
        }
        return trim((string) $raw);
    }

    /**
     * 套餐相对兑换比例的赠送百分比（向下取整；小于 1 视为 0）
     *
     * @param float|string $money
     * @param float|string $points
     * @param float|null   $rate  为 null 时用当前配置比例
     * @return int
     */
    public static function giftPercent($money, $points, $rate = null)
    {
        $rate = $rate === null ? self::rate() : (float) $rate;
        $money = (float) $money;
        $points = (float) $points;
        if ($rate <= 0 || $money <= 0 || $points <= 0) {
            return 0;
        }
        $base = $money * $rate;
        if ($base <= 0 || $points <= $base) {
            return 0;
        }
        $pct = (int) floor(($points - $base) / $base * 100);
        return $pct >= 1 ? $pct : 0;
    }

    /**
     * @return bool
     */
    public static function isReady()
    {
        $url = trim((string) Config::get(self::KEY_URL, ''));
        $pid = trim((string) Config::get(self::KEY_PID, ''));
        $key = (string) Config::get(self::KEY_KEY, '');
        return $url !== '' && $pid !== '' && $key !== '' && count(self::methods()) > 0;
    }

    /**
     * 1 元兑换积分数
     *
     * @return float
     */
    public static function rate()
    {
        $n = (float) Config::get(self::KEY_RATE, '1000');
        if ($n <= 0) {
            $n = 1000;
        }
        return round($n, 4);
    }

    /**
     * @return array{alipay:string,wxpay:string,qqpay:string}
     */
    public static function channels()
    {
        $raw = Config::get(self::KEY_CHANNEL, '{}');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            $data = array();
        }
        return array(
            'alipay' => isset($data['alipay']) ? trim((string) $data['alipay']) : '',
            'wxpay'  => isset($data['wxpay']) ? trim((string) $data['wxpay']) : '',
            'qqpay'  => isset($data['qqpay']) ? trim((string) $data['qqpay']) : '',
        );
    }

    /**
     * @return array<int,string>
     */
    public static function methods()
    {
        $raw = Config::get(self::KEY_METHODS, '[]');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return array();
        }
        $allow = array('alipay' => true, 'wxpay' => true, 'qqpay' => true);
        $out = array();
        foreach ($data as $m) {
            $m = strtolower(trim((string) $m));
            if (isset($allow[$m])) {
                $out[$m] = $m;
            }
        }
        return array_values($out);
    }

    /**
     * 自定义充值优惠档位（已规范化、按 min 升序）
     *
     * @return array<int,array{min:string,max:string,percent:int}>
     */
    public static function customBonus()
    {
        $raw = Config::get(self::KEY_CUSTOM_BONUS, '[]');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return array();
        }
        $parsed = self::parseCustomBonusList($data);
        return is_array($parsed) ? $parsed : array();
    }

    /**
     * 按实付金额匹配一档自定义优惠（≥min 且 &lt;max；max=0 不封顶）
     *
     * @param float|string $money
     * @return array{min:string,max:string,percent:int}|null
     */
    public static function matchCustomBonus($money)
    {
        $money = round((float) $money, 2);
        if ($money < 0.01) {
            return null;
        }
        foreach (self::customBonus() as $tier) {
            $min = (float) $tier['min'];
            $max = (float) $tier['max'];
            if ($money < $min) {
                continue;
            }
            if ($max > 0 && $money >= $max) {
                continue;
            }
            return $tier;
        }
        return null;
    }

    /**
     * 自定义金额应到账积分（含阶梯赠送；服务端权威）
     *
     * @param float|string $money
     * @param float|null   $rate
     * @return array{points:float,percent:int,base:float}
     */
    public static function customPoints($money, $rate = null)
    {
        $rate = $rate === null ? self::rate() : (float) $rate;
        $money = round((float) $money, 2);
        $base = round($money * $rate, 4);
        $percent = 0;
        $tier = self::matchCustomBonus($money);
        if ($tier !== null) {
            $percent = (int) $tier['percent'];
        }
        $points = $percent > 0
            ? round($base * (1 + $percent / 100), 4)
            : $base;
        return array(
            'points'  => $points,
            'percent' => $percent,
            'base'    => $base,
        );
    }

    /**
     * 解析并校验自定义优惠列表；失败返回错误文案
     *
     * @param mixed $data
     * @return array<int,array{min:string,max:string,percent:int}>|string
     */
    public static function parseCustomBonusList($data)
    {
        if (!is_array($data)) {
            return '自定义充值优惠 JSON 无效';
        }
        if (count($data) > self::BONUS_MAX_TIERS) {
            return '自定义充值优惠最多 ' . self::BONUS_MAX_TIERS . ' 档';
        }
        $tiers = array();
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $min = isset($row['min']) ? round((float) $row['min'], 2) : 0;
            $max = isset($row['max']) ? round((float) $row['max'], 2) : 0;
            $percent = isset($row['percent']) ? (int) $row['percent'] : 0;
            if ($min < 0.01) {
                return '优惠档位起始金额须至少 0.01 元';
            }
            if ($max < 0) {
                return '优惠档位上限不能为负数';
            }
            if ($max > 0 && $max <= $min) {
                return '优惠档位上限须大于起始金额（或不设上限填 0）';
            }
            if ($percent < 1 || $percent > self::BONUS_PERCENT_MAX) {
                return '优惠赠送比例须为 1～' . self::BONUS_PERCENT_MAX . ' 的整数';
            }
            $tiers[] = array(
                'min'     => number_format($min, 2, '.', ''),
                'max'     => number_format($max, 2, '.', ''),
                'percent' => $percent,
            );
        }
        usort($tiers, function ($a, $b) {
            $cmp = (float) $a['min'] - (float) $b['min'];
            if ($cmp < 0) {
                return -1;
            }
            if ($cmp > 0) {
                return 1;
            }
            return 0;
        });
        $prevMax = null;
        foreach ($tiers as $tier) {
            $min = (float) $tier['min'];
            $max = (float) $tier['max'];
            if ($prevMax !== null) {
                if ($prevMax <= 0) {
                    return '不封顶档位之后不能再配置其它档位';
                }
                if ($min < $prevMax) {
                    return '优惠档位金额区间不能重叠';
                }
            }
            $prevMax = $max > 0 ? $max : 0.0;
            if ($max <= 0) {
                $prevMax = 0.0;
            }
        }
        return $tiers;
    }

    /**
     * @return array<int,array>
     */
    public static function packages()
    {
        $raw = Config::get(self::KEY_PACKAGES, '[]');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return array();
        }
        $out = array();
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? trim((string) $row['id']) : '';
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $money = isset($row['money']) ? (float) $row['money'] : 0;
            $points = isset($row['points']) ? (float) $row['points'] : 0;
            if ($id === '' || $name === '' || $money <= 0 || $points <= 0) {
                continue;
            }
            $moneyFmt = number_format($money, 2, '.', '');
            $pointsFmt = self::fmtPoints($points);
            $out[] = array(
                'id'     => mb_substr($id, 0, 32, 'UTF-8'),
                'name'   => mb_substr($name, 0, 64, 'UTF-8'),
                'money'  => $moneyFmt,
                'points' => $pointsFmt,
                'hot'    => !empty($row['hot']) ? 1 : 0,
                'gift'   => self::giftPercent($moneyFmt, $pointsFmt),
            );
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function normalizeTipInput($raw)
    {
        if (function_exists('vs_ensure_plaintext_field')) {
            $raw = vs_ensure_plaintext_field($raw);
        }
        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, self::TIP_MAX_LEN, 'UTF-8');
        }
        return substr($text, 0, self::TIP_MAX_LEN);
    }

    /**
     * @param array $input
     * @return array|string 成功返回规范化配置，失败返回错误文案
     */
    public static function save(array $input)
    {
        $url = trim((string) (isset($input['url']) ? $input['url'] : ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            return '码支付接口地址须以 http:// 或 https:// 开头';
        }
        $url = rtrim($url, '/');

        $pid = trim((string) (isset($input['pid']) ? $input['pid'] : ''));
        $key = (string) (isset($input['key']) ? $input['key'] : '');

        $channel = array(
            'alipay' => trim((string) (isset($input['channel_alipay']) ? $input['channel_alipay'] : '')),
            'wxpay'  => trim((string) (isset($input['channel_wxpay']) ? $input['channel_wxpay'] : '')),
            'qqpay'  => trim((string) (isset($input['channel_qqpay']) ? $input['channel_qqpay'] : '')),
        );

        $methods = array();
        if (isset($input['methods']) && is_array($input['methods'])) {
            foreach ($input['methods'] as $m) {
                $m = strtolower(trim((string) $m));
                if ($m === 'alipay' || $m === 'wxpay' || $m === 'qqpay') {
                    $methods[$m] = $m;
                }
            }
        } elseif (isset($input['methods']) && is_string($input['methods'])) {
            $decoded = json_decode($input['methods'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $m) {
                    $m = strtolower(trim((string) $m));
                    if ($m === 'alipay' || $m === 'wxpay' || $m === 'qqpay') {
                        $methods[$m] = $m;
                    }
                }
            }
        }

        $rate = (float) (isset($input['rate']) ? $input['rate'] : 1000);
        if ($rate <= 0 || $rate > 100000000) {
            return '积分兑换比例须大于 0';
        }

        $packages = array();
        if (isset($input['packages']) && is_string($input['packages'])) {
            $decoded = json_decode($input['packages'], true);
            if (!is_array($decoded)) {
                return '充值套餐 JSON 无效';
            }
            $input['packages'] = $decoded;
        }
        if (isset($input['packages']) && is_array($input['packages'])) {
            foreach ($input['packages'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) ? trim((string) $row['id']) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $money = isset($row['money']) ? (float) $row['money'] : 0;
                $points = isset($row['points']) ? (float) $row['points'] : 0;
                if ($id === '' || $name === '' || $money <= 0 || $points <= 0) {
                    continue;
                }
                $packages[] = array(
                    'id'     => mb_substr($id, 0, 32, 'UTF-8'),
                    'name'   => mb_substr($name, 0, 64, 'UTF-8'),
                    'money'  => number_format($money, 2, '.', ''),
                    'points' => self::fmtPoints($points),
                    'hot'    => !empty($row['hot']) ? 1 : 0,
                );
            }
        }

        $bonusRaw = array();
        if (isset($input['custom_bonus']) && is_string($input['custom_bonus'])) {
            $decoded = json_decode($input['custom_bonus'], true);
            if (!is_array($decoded)) {
                return '自定义充值优惠 JSON 无效';
            }
            $bonusRaw = $decoded;
        } elseif (isset($input['custom_bonus']) && is_array($input['custom_bonus'])) {
            $bonusRaw = $input['custom_bonus'];
        }
        $customBonus = self::parseCustomBonusList($bonusRaw);
        if (!is_array($customBonus)) {
            return $customBonus;
        }

        $tipPackage = self::normalizeTipInput(isset($input['tip_package']) ? $input['tip_package'] : '');
        $tipCustom = self::normalizeTipInput(isset($input['tip_custom']) ? $input['tip_custom'] : '');
        $tipCardkey = self::normalizeTipInput(isset($input['tip_cardkey']) ? $input['tip_cardkey'] : '');

        Config::setMany(array(
            self::KEY_URL           => $url,
            self::KEY_PID           => $pid,
            self::KEY_KEY           => $key,
            self::KEY_CHANNEL       => json_encode($channel, JSON_UNESCAPED_UNICODE),
            self::KEY_METHODS       => json_encode(array_values($methods), JSON_UNESCAPED_UNICODE),
            self::KEY_RATE          => (string) self::fmtPoints($rate),
            self::KEY_PACKAGES      => json_encode($packages, JSON_UNESCAPED_UNICODE),
            self::KEY_CUSTOM_BONUS  => json_encode($customBonus, JSON_UNESCAPED_UNICODE),
            self::KEY_TIP_PACKAGE   => $tipPackage,
            self::KEY_TIP_CUSTOM    => $tipCustom,
            self::KEY_TIP_CARDKEY   => $tipCardkey,
        ));

        return self::all();
    }

    /**
     * @param float|string $n
     * @return string
     */
    public static function fmtPoints($n)
    {
        $n = round((float) $n, 4);
        $s = number_format($n, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }

    /**
     * @param string $type
     * @return string
     */
    public static function methodLabel($type)
    {
        $map = array(
            'alipay' => '支付宝',
            'wxpay'  => '微信支付',
            'qqpay'  => 'QQ 钱包',
        );
        $type = strtolower((string) $type);
        return isset($map[$type]) ? $map[$type] : $type;
    }

    /**
     * 支付方式图标相对站点根路径
     *
     * @param string $type alipay|wxpay|qqpay
     * @return string
     */
    public static function iconPath($type)
    {
        $map = array(
            'alipay' => 'zhfubao.svg',
            'wxpay'  => 'weixinzhifu.svg',
            'qqpay'  => 'QQ.svg',
        );
        $type = strtolower((string) $type);
        if (!isset($map[$type])) {
            return '';
        }
        if (class_exists('SiteMedia')) {
            return SiteMedia::imgWebPath($map[$type]);
        }
        return '/assets/img/' . $map[$type];
    }

    /**
     * 支付方式图标完整 URL
     *
     * @param string $type
     * @return string
     */
    public static function iconUrl($type)
    {
        $path = self::iconPath($type);
        if ($path === '') {
            return '';
        }
        if (class_exists('SiteMedia')) {
            return SiteMedia::resolve($path);
        }
        return vs_site_path($path);
    }

    /**
     * 支付方式图标 HTML（img）
     *
     * @param string $type
     * @param string $class
     * @return string
     */
    public static function iconHtml($type, $class = 'vs-pay-ico')
    {
        $url = self::iconUrl($type);
        if ($url === '') {
            return '';
        }
        $label = self::methodLabel($type);
        return '<img class="' . vs_e($class) . '" src="' . vs_e($url) . '" alt="' . vs_e($label) . '" width="22" height="22" loading="lazy" decoding="async">';
    }
}
