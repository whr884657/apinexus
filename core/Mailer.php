<?php
/**
 * 文件：core/Mailer.php
 * 作用：系统邮件发送（SMTP）
 *
 * 说明：系统版本以 core/version.php 中 VS_VERSION 为准。
 */

class Mailer
{
    /**
     * 发送邮件
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     * @throws Exception
     */
    public static function send($to, $subject, $body)
    {
        if (!Config::isMailEnabled()) {
            throw new Exception('邮箱发信功能未配置，请先在后台系统设置中配置');
        }

        $host = Config::get('mail_smtp_host');
        $port = (int) Config::get('mail_smtp_port', 465);
        $user = Config::get('mail_smtp_user');
        $pass = Config::get('mail_smtp_pass');
        $secure = Config::get('mail_smtp_secure', 'ssl');
        $fromEmail = Config::get('mail_from_email');
        $fromName = Config::get('mail_from_name', 'ApiNexus');

        return self::sendSmtp($host, $port, $user, $pass, $secure, $fromEmail, $fromName, $to, $subject, $body);
    }

    /**
     * 验证码邮件 HTML 正文（登录 / 重置 / 注册等）
     *
     * @param string $displayName 称呼（用户名）
     * @param string $brandName   站点名或系统名
     * @param string $actionDesc  用途说明，如「登录」「申请重置管理员密码」
     * @param string $code        6 位验证码
     * @param int    $ttlSeconds  有效期秒数
     * @return string
     */
    public static function otpMailBody($displayName, $brandName, $actionDesc, $code, $ttlSeconds)
    {
        $mins = max(1, (int) ($ttlSeconds / 60));
        $name = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars((string) $brandName, ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars((string) $actionDesc, ENT_QUOTES, 'UTF-8');
        $codeEsc = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:sans-serif;line-height:1.8;">'
            . '<p>您好，' . $name . '：</p>'
            . '<p>您正在' . $action . ' ' . $brand . '，验证码为：</p>'
            . '<p style="font-size:24px;font-weight:bold;margin:16px 0;">' . $codeEsc . '</p>'
            . '<p>验证码 ' . $mins . ' 分钟内有效，请勿泄露给他人。</p>'
            . '<p>如非本人操作，请忽略此邮件。</p></div>';
    }

    /**
     * SMTP 发送
     *
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     * @param string $secure
     * @param string $fromEmail
     * @param string $fromName
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     * @throws Exception
     */
    private static function sendSmtp($host, $port, $user, $pass, $secure, $fromEmail, $fromName, $to, $subject, $body)
    {
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 15);

        if (!$socket) {
            throw new Exception('无法连接 SMTP 服务器：' . $errstr);
        }

        stream_set_timeout($socket, 15);
        self::readResponse($socket);

        $ehloHost = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        self::smtpCommand($socket, 'EHLO ' . $ehloHost);

        if ($secure === 'tls') {
            self::smtpCommand($socket, 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('STARTTLS 握手失败');
            }
            self::smtpCommand($socket, 'EHLO ' . $ehloHost);
        }

        self::smtpCommand($socket, 'AUTH LOGIN');
        self::smtpCommand($socket, base64_encode($user));
        self::smtpCommand($socket, base64_encode($pass));
        self::smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>');
        self::smtpCommand($socket, 'RCPT TO:<' . $to . '>');
        self::smtpCommand($socket, 'DATA');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $message = "From: {$encodedFromName} <{$fromEmail}>\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.";

        fwrite($socket, $message . "\r\n");
        self::readResponse($socket);
        self::smtpCommand($socket, 'QUIT');
        fclose($socket);

        return true;
    }

    /**
     * 发送 SMTP 命令
     *
     * @param resource $socket
     * @param string   $command
     * @return void
     * @throws Exception
     */
    private static function smtpCommand($socket, $command)
    {
        fwrite($socket, $command . "\r\n");
        self::readResponse($socket);
    }

    /**
     * 读取 SMTP 响应
     *
     * @param resource $socket
     * @return string
     * @throws Exception
     */
    private static function readResponse($socket)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception('SMTP 错误：' . trim($response));
        }

        return $response;
    }
}
