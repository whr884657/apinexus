<?php
/**
 * 文件：core/AiConfig.php
 * 作用：站点 AI 对接配置（仅管理员后台使用）
 *
 * 配置键：ai_enabled / ai_provider / ai_baseurl / ai_apikey / ai_model / ai_timeout / ai_doc_maxlen / ai_api_mode
 *         / ai_code_mode / ai_code_concurrency
 * 协议：auto（先 Chat 再 Responses）/ chat / responses
 * 代码示例：前端按「鉴权×语言」分片请求；ai_code_mode=sequential|parallel
 */

class AiConfig
{
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_DEEPSEEK = 'deepseek';
    const PROVIDER_ZHIPU = 'zhipu';
    const PROVIDER_LONGCAT = 'longcat';
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_CUSTOM = 'custom';

    /**
     * @return array<string,string>
     */
    public static function providerPresets()
    {
        return array(
            self::PROVIDER_OPENAI   => 'https://api.openai.com/v1',
            self::PROVIDER_DEEPSEEK => 'https://api.deepseek.com/v1',
            self::PROVIDER_ZHIPU    => 'https://open.bigmodel.cn/api/paas/v4',
            self::PROVIDER_LONGCAT  => 'https://api.longcat.chat/openai/v1',
            // Gemini OpenAI 兼容层；Claude 等请用自定义根地址（OpenRouter / 中转等）
            self::PROVIDER_GOOGLE   => 'https://generativelanguage.googleapis.com/v1beta/openai',
            self::PROVIDER_CUSTOM   => '',
        );
    }

    /**
     * @return array{enabled:bool,provider:string,baseurl:string,apikey:string,model:string,timeout:int,doc_maxlen:int,api_mode:string,code_mode:string,code_concurrency:int}
     */
    public static function get()
    {
        $provider = strtolower(trim((string) Config::get('ai_provider', self::PROVIDER_OPENAI)));
        $presets = self::providerPresets();
        if (!isset($presets[$provider])) {
            $provider = self::PROVIDER_OPENAI;
        }
        $base = trim((string) Config::get('ai_baseurl', ''));
        if ($base === '' && $provider !== self::PROVIDER_CUSTOM) {
            $base = $presets[$provider];
        }
        $timeout = (int) Config::get('ai_timeout', '120');
        if ($timeout < 10) {
            $timeout = 10;
        }
        // 单片生成超时上限（整包由前端分片，不再一次拖满）
        if ($timeout > 300) {
            $timeout = 300;
        }
        $maxLen = (int) Config::get('ai_doc_maxlen', '8000');
        if ($maxLen < 1000) {
            $maxLen = 1000;
        }
        if ($maxLen > 30000) {
            $maxLen = 30000;
        }
        return array(
            'enabled'          => Config::get('ai_enabled', '0') === '1',
            'provider'         => $provider,
            'baseurl'          => rtrim($base, '/'),
            'apikey'           => (string) Config::get('ai_apikey', ''),
            'model'            => trim((string) Config::get('ai_model', '')),
            'timeout'          => $timeout,
            'doc_maxlen'       => $maxLen,
            'api_mode'         => self::apiMode(),
            'code_mode'        => self::codeMode(),
            'code_concurrency' => self::codeConcurrency(),
        );
    }

    /**
     * 代码示例生成调度：sequential 单线程逐片 / parallel 浏览器并发多片
     *
     * @return string sequential|parallel
     */
    public static function codeMode()
    {
        $mode = strtolower(trim((string) Config::get('ai_code_mode', 'sequential')));
        return $mode === 'parallel' ? 'parallel' : 'sequential';
    }

    /**
     * 并行时的最大并发请求数（1～6）
     *
     * @return int
     */
    public static function codeConcurrency()
    {
        $n = (int) Config::get('ai_code_concurrency', '3');
        if ($n < 1) {
            $n = 1;
        }
        if ($n > 6) {
            $n = 6;
        }
        return $n;
    }

    /**
     * 供接口列表页前端读取（不含密钥）
     *
     * @return array{mode:string,concurrency:int,timeout:int,ready:bool}
     */
    public static function codeClientOptions()
    {
        $cfg = self::get();
        return array(
            'mode'         => $cfg['code_mode'],
            'concurrency'  => $cfg['code_concurrency'],
            'timeout'      => $cfg['timeout'],
            'ready'        => self::isReady(),
        );
    }

    /**
     * @return string auto|chat|responses
     */
    public static function apiMode()
    {
        $mode = strtolower(trim((string) Config::get('ai_api_mode', 'auto')));
        if ($mode === 'chat' || $mode === 'completions') {
            return 'chat';
        }
        if ($mode === 'responses' || $mode === 'response') {
            return 'responses';
        }
        return 'auto';
    }

    /**
     * @return bool
     */
    public static function isReady()
    {
        $cfg = self::get();
        return $cfg['enabled'] && $cfg['baseurl'] !== '' && $cfg['apikey'] !== '' && $cfg['model'] !== '';
    }

    /**
     * @param bool $maskKey
     * @return array
     */
    public static function forAdminForm($maskKey = false)
    {
        $cfg = self::get();
        if ($maskKey && $cfg['apikey'] !== '') {
            $len = strlen($cfg['apikey']);
            $cfg['apikey_masked'] = $len <= 8
                ? str_repeat('*', $len)
                : (substr($cfg['apikey'], 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($cfg['apikey'], -4));
        }
        return $cfg;
    }
}
