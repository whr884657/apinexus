<?php
/**
 * 文件：core/AiConfig.php
 * 作用：站点 AI 对接配置（仅管理员后台使用）
 *
 * 配置键：ai_enabled / ai_provider / ai_baseurl / ai_apikey / ai_model / ai_timeout / ai_doc_maxlen / ai_api_mode
 * 协议：auto（先 Chat 再 Responses）/ chat / responses
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
     * @return array{enabled:bool,provider:string,baseurl:string,apikey:string,model:string,timeout:int,doc_maxlen:int,api_mode:string}
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
        $timeout = (int) Config::get('ai_timeout', '60');
        if ($timeout < 10) {
            $timeout = 10;
        }
        if ($timeout > 180) {
            $timeout = 180;
        }
        $maxLen = (int) Config::get('ai_doc_maxlen', '8000');
        if ($maxLen < 1000) {
            $maxLen = 1000;
        }
        if ($maxLen > 30000) {
            $maxLen = 30000;
        }
        return array(
            'enabled'    => Config::get('ai_enabled', '0') === '1',
            'provider'   => $provider,
            'baseurl'    => rtrim($base, '/'),
            'apikey'     => (string) Config::get('ai_apikey', ''),
            'model'      => trim((string) Config::get('ai_model', '')),
            'timeout'    => $timeout,
            'doc_maxlen' => $maxLen,
            'api_mode'   => self::apiMode(),
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
