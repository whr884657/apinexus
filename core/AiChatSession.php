<?php
/**
 * 文件：core/AiChatSession.php
 * 作用：AI 短时效多轮对话（Redis；无 Redis 则本进程无跨请求历史）
 *
 * 内置 OpenAI / DeepSeek / 智谱 / LongCat / Gemini 兼容层 / 自定义 OpenAI 兼容
 * 均支持 messages 多轮；流式由 AiClient 负责。
 * 厂商不提供「中途断流原生续传」——断点靠保存 partial + 下一轮「请继续」。
 */

class AiChatSession
{
    /** 短时效：10 分钟无活动即丢弃（保存接口时也会整批清空，见 clearAllForActor） */
    const TTL = 600;

    /** 除 system 外最多保留的 user/assistant 条数（成对约 4 轮） */
    const MAX_TURNS = 10;

    /**
     * @param string $actor admin|user
     * @param int    $actorId
     * @param string $scope doc|code
     * @param string $topicKey 接口 id 或草稿指纹
     * @return string
     */
    public static function key($actor, $actorId, $scope, $topicKey)
    {
        $actor = preg_replace('/[^a-z]/', '', strtolower((string) $actor));
        $scope = preg_replace('/[^a-z]/', '', strtolower((string) $scope));
        $topic = preg_replace('/[^a-zA-Z0-9_\-.:]/', '', (string) $topicKey);
        if ($topic === '') {
            $topic = 'draft';
        }
        return 'ai:chat:' . $actor . ':' . (int) $actorId . ':' . $scope . ':' . $topic;
    }

    /**
     * 从接口上下文生成 topic（有 id 用 id；否则用名称摘要）
     *
     * @param array $api
     * @return string
     */
    public static function topicFromApi(array $api)
    {
        $id = isset($api['id']) ? (int) $api['id'] : 0;
        if ($id > 0) {
            return 'api' . $id;
        }
        $name = isset($api['name']) ? trim((string) $api['name']) : '';
        if ($name === '') {
            return 'draft';
        }
        return 'draft_' . substr(sha1($name), 0, 12);
    }

    /**
     * @param string $key
     * @return array{messages:array,partial:string,updated:int}
     */
    public static function load($key)
    {
        $empty = array('messages' => array(), 'partial' => '', 'updated' => 0);
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return $empty;
        }
        $hit = RedisCache::get($key);
        if (!is_array($hit)) {
            return $empty;
        }
        if (!isset($hit['messages']) || !is_array($hit['messages'])) {
            $hit['messages'] = array();
        }
        if (!isset($hit['partial'])) {
            $hit['partial'] = '';
        }
        if (!isset($hit['updated'])) {
            $hit['updated'] = 0;
        }
        return $hit;
    }

    /**
     * @param string $key
     * @param array  $state
     * @return void
     */
    public static function save($key, array $state)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        $state['updated'] = time();
        if (isset($state['messages']) && is_array($state['messages'])) {
            $state['messages'] = self::trimMessages($state['messages']);
        }
        RedisCache::put($key, $state, self::TTL);
    }

    /**
     * @param string $key
     * @return void
     */
    public static function clear($key)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return;
        }
        RedisCache::forget($key);
    }

    /**
     * 清空某操作者名下全部 AI 对话缓存（doc/code × 各 topic）
     * 用于接口「保存/提交」成功后立刻释放，避免连续生成多个接口时 Redis 堆积。
     *
     * @param string $actor admin|user
     * @param int    $actorId
     * @return int 删除键数（失败或不可用时为 0）
     */
    public static function clearAllForActor($actor, $actorId)
    {
        if (!class_exists('RedisCache') || !RedisCache::enabled()) {
            return 0;
        }
        if (!class_exists('RedisService')) {
            return 0;
        }
        $actor = preg_replace('/[^a-z]/', '', strtolower((string) $actor));
        $actorId = (int) $actorId;
        if ($actor === '' || $actorId <= 0) {
            return 0;
        }
        $logicalPrefix = 'ai:chat:' . $actor . ':' . $actorId . ':';
        $deleted = 0;
        try {
            RedisService::withClient(function ($redis) use ($logicalPrefix, &$deleted) {
                $fullPrefix = RedisService::buildKey($logicalPrefix);
                $pattern = $fullPrefix . '*';
                $it = null;
                do {
                    $keys = $redis->scan($it, $pattern, 80);
                    if ($keys === false) {
                        break;
                    }
                    if (!empty($keys)) {
                        $batch = array();
                        foreach ($keys as $key) {
                            // 与 flushKeyspace 一致：二次确认前缀，防 SCAN 异常误删
                            if (strpos((string) $key, $fullPrefix) === 0) {
                                $batch[] = $key;
                            }
                        }
                        if (!empty($batch)) {
                            $n = $redis->del($batch);
                            $deleted += is_int($n) ? $n : count($batch);
                        }
                    }
                } while ($it !== 0 && $it !== null);
            });
        } catch (Exception $e) {
            return $deleted;
        }
        return $deleted;
    }

    /**
     * 组装发给上游的 messages：system + 历史 + 本轮 user
     *
     * @param string $system
     * @param array  $history messages（可含旧 system，会被丢弃）
     * @param string $user
     * @param bool   $continue 若有 partial，追加续写指令
     * @param string $partial
     * @return array<int,array{role:string,content:string}>
     */
    public static function buildMessages($system, array $history, $user, $continue = false, $partial = '')
    {
        $out = array();
        $out[] = array('role' => 'system', 'content' => (string) $system);
        foreach ($history as $m) {
            if (!is_array($m) || !isset($m['role'], $m['content'])) {
                continue;
            }
            $role = strtolower((string) $m['role']);
            if ($role === 'system') {
                continue;
            }
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $content = trim((string) $m['content']);
            if ($content === '') {
                continue;
            }
            $out[] = array('role' => $role, 'content' => $content);
        }
        $userText = (string) $user;
        $partial = trim((string) $partial);
        if ($continue && $partial !== '') {
            $out[] = array('role' => 'assistant', 'content' => $partial);
            $userText = "上文生成在传输中断处停下了。请从断点继续写完，不要重复已写出的内容，直接续写后续 Markdown。\n"
                . '（若上文已基本完整，仅作必要收尾。）';
        }
        $out[] = array('role' => 'user', 'content' => $userText);
        return $out;
    }

    /**
     * 一轮结束后写入历史，并清空 partial
     *
     * @param string $key
     * @param string $user
     * @param string $assistant
     * @return void
     */
    public static function appendTurn($key, $user, $assistant)
    {
        $state = self::load($key);
        $state['messages'][] = array('role' => 'user', 'content' => (string) $user);
        $state['messages'][] = array('role' => 'assistant', 'content' => (string) $assistant);
        $state['partial'] = '';
        self::save($key, $state);
    }

    /**
     * 流式中途保存 partial（断点续写用）
     *
     * @param string $key
     * @param string $partial
     * @return void
     */
    public static function savePartial($key, $partial)
    {
        $state = self::load($key);
        $state['partial'] = (string) $partial;
        self::save($key, $state);
    }

    /**
     * @param array $messages
     * @return array
     */
    private static function trimMessages(array $messages)
    {
        $nonSystem = array();
        foreach ($messages as $m) {
            if (!is_array($m) || !isset($m['role'])) {
                continue;
            }
            if (strtolower((string) $m['role']) === 'system') {
                continue;
            }
            $nonSystem[] = $m;
        }
        if (count($nonSystem) > self::MAX_TURNS) {
            $nonSystem = array_slice($nonSystem, -self::MAX_TURNS);
        }
        return array_values($nonSystem);
    }

    /**
     * @return bool
     */
    public static function historyAvailable()
    {
        return class_exists('RedisCache') && RedisCache::enabled();
    }
}
