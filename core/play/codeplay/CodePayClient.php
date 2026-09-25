<?php
/**
 * 文件：core/play/codeplay/CodePayClient.php
 * 作用：码支付（易支付协议）签名、下单、验签
 */

class CodePayClient
{
    /**
     * MD5 签名（小写）
     *
     * @param array  $params
     * @param string $key
     * @return string
     */
    public static function sign(array $params, $key)
    {
        unset($params['sign'], $params['sign_type']);
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) {
                unset($params[$k]);
            }
        }
        ksort($params);
        $parts = array();
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        return md5(implode('&', $parts) . (string) $key);
    }

    /**
     * @param array  $request
     * @param string $key
     * @return bool
     */
    public static function verify(array $request, $key)
    {
        $sign = isset($request['sign']) ? (string) $request['sign'] : '';
        $signType = strtoupper(isset($request['sign_type']) ? (string) $request['sign_type'] : 'MD5');
        if ($sign === '' || $signType !== 'MD5') {
            return false;
        }
        return hash_equals(self::sign($request, $key), $sign);
    }

    /**
     * 调用 mapi.php 创建支付
     *
     * @param array $opts url,pid,key,type,out_trade_no,notify_url,return_url,name,money,clientip,param,channel_id
     * @return array{ok:bool,msg:string,data?:array}
     */
    public static function create(array $opts)
    {
        $url = rtrim((string) (isset($opts['url']) ? $opts['url'] : ''), '/');
        $pid = (string) (isset($opts['pid']) ? $opts['pid'] : '');
        $key = (string) (isset($opts['key']) ? $opts['key'] : '');
        if ($url === '' || $pid === '' || $key === '') {
            return array('ok' => false, 'msg' => '支付尚未配置完成');
        }

        $money = number_format((float) (isset($opts['money']) ? $opts['money'] : 0), 2, '.', '');
        if ((float) $money <= 0) {
            return array('ok' => false, 'msg' => '支付金额无效');
        }

        $params = array(
            'pid'          => $pid,
            'type'         => (string) (isset($opts['type']) ? $opts['type'] : 'alipay'),
            'out_trade_no' => (string) (isset($opts['out_trade_no']) ? $opts['out_trade_no'] : ''),
            'notify_url'   => (string) (isset($opts['notify_url']) ? $opts['notify_url'] : ''),
            'return_url'   => (string) (isset($opts['return_url']) ? $opts['return_url'] : ''),
            'name'         => mb_substr((string) (isset($opts['name']) ? $opts['name'] : '积分充值'), 0, 60, 'UTF-8'),
            'money'        => $money,
            'clientip'     => (string) (isset($opts['clientip']) ? $opts['clientip'] : ''),
            'device'       => 'jump',
        );
        if (isset($opts['param']) && (string) $opts['param'] !== '') {
            $params['param'] = (string) $opts['param'];
        }
        if (isset($opts['channel_id']) && (string) $opts['channel_id'] !== '') {
            $params['channel_id'] = (string) $opts['channel_id'];
        }
        $params['sign'] = self::sign($params, $key);
        $params['sign_type'] = 'MD5';

        $endpoint = $url . '/mapi.php';
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return array('ok' => false, 'msg' => '无法初始化支付请求');
        }
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
        ));
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        vs_curl_close($ch);

        if ($raw === false || $raw === '') {
            return array('ok' => false, 'msg' => '支付网关无响应' . ($err !== '' ? ('：' . $err) : ''));
        }

        $resp = json_decode((string) $raw, true);
        if (!is_array($resp) || (int) (isset($resp['code']) ? $resp['code'] : 0) !== 1) {
            $msg = is_array($resp) && isset($resp['msg']) ? (string) $resp['msg'] : '拉起支付失败';
            return array('ok' => false, 'msg' => $msg);
        }

        $qrcode = isset($resp['qrcode']) ? (string) $resp['qrcode'] : '';
        $payurl = isset($resp['payurl']) ? (string) $resp['payurl'] : '';
        $urlscheme = isset($resp['urlscheme']) ? (string) $resp['urlscheme'] : '';
        $qrRaw = $qrcode !== '' ? $qrcode : $payurl;
        $normalized = self::normalizeQrPayload($qrRaw);
        $actualMoney = isset($resp['money']) && is_numeric($resp['money'])
            ? number_format((float) $resp['money'], 2, '.', '')
            : $money;

        return array(
            'ok'   => true,
            'msg'  => 'ok',
            'data' => array(
                'trade_no'  => isset($resp['trade_no']) ? (string) $resp['trade_no'] : '',
                'money'     => $actualMoney,
                'qrcode'    => $normalized['qrcode'],
                'qr_kind'   => $normalized['qr_kind'],
                'payurl'    => $payurl,
                'urlscheme' => $urlscheme,
                'raw'       => $resp,
            ),
        );
    }

    /**
     * 归一化上游二维码字段：区分「已是图片」与「待编码内容」
     * - JSON pretty / 压缩均可（json_decode 之后再处理字符串）
     * - data URI 或裸 B64 中的空白/换行（美化折行）一律去掉
     *
     * @param string $raw
     * @return array{qrcode:string,qr_kind:string} qr_kind = image|content
     */
    public static function normalizeQrPayload($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return array('qrcode' => '', 'qr_kind' => 'content');
        }

        // data:image/...;base64,...（payload 可能含换行/空格）
        if (stripos($s, 'data:image/') === 0) {
            $comma = strpos($s, ',');
            if ($comma !== false) {
                $header = trim(substr($s, 0, $comma));
                $payload = preg_replace('/\s+/', '', substr($s, $comma + 1));
                if ($payload !== '' && $payload !== null
                    && preg_match('/^data:image\/[a-z0-9.+-]+;base64$/i', $header)
                ) {
                    return array(
                        'qrcode'  => $header . ',' . $payload,
                        'qr_kind' => 'image',
                    );
                }
            }
        }

        // 裸 base64：去空白后按魔数推断 MIME（无魔数则不当图片，避免误伤长串）
        $compact = preg_replace('/\s+/', '', $s);
        if ($compact !== '' && $compact !== null
            && strlen($compact) >= 64
            && preg_match('/^[A-Za-z0-9+\/]+=*$/', $compact)
            && strpos($compact, '://') === false
        ) {
            $mime = '';
            if (strpos($compact, '/9j/') === 0) {
                $mime = 'jpeg';
            } elseif (strpos($compact, 'iVBOR') === 0) {
                $mime = 'png';
            } elseif (strpos($compact, 'R0lGOD') === 0) {
                $mime = 'gif';
            } elseif (strpos($compact, 'UklGR') === 0) {
                $mime = 'webp';
            }
            if ($mime !== '') {
                return array(
                    'qrcode'  => 'data:image/' . $mime . ';base64,' . $compact,
                    'qr_kind' => 'image',
                );
            }
        }

        // 少数网关直接返回图片文件 URL
        if (preg_match('/^https?:\/\/.+\.(png|jpe?g|gif|webp)(\?|#|$)/i', $s)) {
            return array('qrcode' => $s, 'qr_kind' => 'image');
        }

        return array('qrcode' => $s, 'qr_kind' => 'content');
    }
}
