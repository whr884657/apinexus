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

    /**
     * @return array
     */
    public static function all()
    {
        return array(
            'url'      => trim((string) Config::get(self::KEY_URL, '')),
            'pid'      => trim((string) Config::get(self::KEY_PID, '')),
            'key'      => (string) Config::get(self::KEY_KEY, ''),
            'channel'  => self::channels(),
            'methods'  => self::methods(),
            'rate'     => self::rate(),
            'packages' => self::packages(),
            'ready'    => self::isReady(),
        );
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
            $out[] = array(
                'id'     => mb_substr($id, 0, 32, 'UTF-8'),
                'name'   => mb_substr($name, 0, 64, 'UTF-8'),
                'money'  => number_format($money, 2, '.', ''),
                'points' => self::fmtPoints($points),
                'hot'    => !empty($row['hot']) ? 1 : 0,
            );
        }
        return $out;
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

        Config::setMany(array(
            self::KEY_URL      => $url,
            self::KEY_PID      => $pid,
            self::KEY_KEY      => $key,
            self::KEY_CHANNEL  => json_encode($channel, JSON_UNESCAPED_UNICODE),
            self::KEY_METHODS  => json_encode(array_values($methods), JSON_UNESCAPED_UNICODE),
            self::KEY_RATE     => (string) self::fmtPoints($rate),
            self::KEY_PACKAGES => json_encode($packages, JSON_UNESCAPED_UNICODE),
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
}
