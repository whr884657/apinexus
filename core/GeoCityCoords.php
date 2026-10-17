<?php
/**
 * 文件：core/GeoCityCoords.php
 * 作用：数据大屏飞线城市经纬度全量库（国内至县级加强 + 全球城市 + 中英别名）
 * 版本：v13.26.48
 *
 * 坐标与别名数据在 core/geo/*.php，本类负责加载与归属地解析。
 * 解析统一走「长别名 → 省内复合提示 → 市/州/县提取 → 子串」；禁止再为单省写特例分支。
 */

class GeoCityCoords
{
    /**
     * @return array<string,array{0:float,1:float}>
     */
    public static function china()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = self::loadMap('china.php');
        }
        return $cache;
    }

    /**
     * @return array<string,array{0:float,1:float}>
     */
    public static function world()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = self::loadMap('world.php');
        }
        return $cache;
    }

    /**
     * @return array<string,string>
     */
    public static function aliases()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = self::loadMap('aliases.php');
        }
        return $cache;
    }

    /**
     * 省内复合提示：文案同时含省关键词与短区名时落到州府/县城（如「青海 + 海南」→ 共和）
     *
     * @return array<string,array<string,string>>
     */
    public static function regionHints()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = self::loadMap('hints.php');
        }
        return $cache;
    }

    /**
     * @param string $file
     * @return array
     */
    private static function loadMap($file)
    {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'geo' . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            return array();
        }
        $data = include $path;
        return is_array($data) ? $data : array();
    }

    /**
     * 按名称长度降序的匹配名列表（含中英文别名键对应的标准名）
     *
     * @return array<int,string>
     */
    public static function matchNamesSorted()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $names = array_unique(array_merge(
            array_keys(self::china()),
            array_keys(self::world()),
            array_values(self::aliases())
        ));
        usort($names, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        $cache = array_values($names);
        return $cache;
    }

    /**
     * 城市名 → 地图省名（与 china.json 的 name 一致，不带「省」）
     *
     * @param string $city
     * @return string
     */
    public static function provinceOf($city)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            $groups = self::loadMap('provinces.php');
            foreach ($groups as $prov => $cities) {
                if (!is_array($cities)) {
                    continue;
                }
                foreach ($cities as $name) {
                    $map[(string) $name] = (string) $prov;
                }
            }
        }
        $city = trim((string) $city);
        return isset($map[$city]) ? $map[$city] : '';
    }


    public static function mapRegionName($province)
    {
        $province = trim((string) $province);
        if ($province === '') {
            return '';
        }
        $regions = self::mapRegionNames();
        $candidates = array(
            $province,
            preg_replace('/(?:(?:壮族|回族|维吾尔族|蒙古族)自治区|省|市|自治区|特别行政区)$/u', '', $province),
            preg_replace('/(?:壮族|回族|维吾尔族|蒙古族)$/u', '', $province),
        );
        $candidates[] = preg_replace(
            '/(?:省|市|自治区|特别行政区)$/u',
            '',
            preg_replace('/(?:壮族|回族|维吾尔族|蒙古族)自治区$/u', '', $province)
        );
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && isset($regions[$candidate])) {
                return $regions[$candidate];
            }
        }
        return '';
    }

    private static function mapRegionNames()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = array();
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
            . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'echarts'
            . DIRECTORY_SEPARATOR . 'map' . DIRECTORY_SEPARATOR . 'china.json';
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $json = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($json) && isset($json['features']) && is_array($json['features'])) {
                foreach ($json['features'] as $feature) {
                    $name = is_array($feature) && isset($feature['properties']['name'])
                        ? trim((string) $feature['properties']['name']) : '';
                    if ($name !== '') {
                        $cache[$name] = $name;
                    }
                }
            }
        }
        if ($cache === array()) {
            foreach (array(
                '台湾', '河北', '山西', '内蒙古', '辽宁', '吉林', '黑龙江', '江苏', '浙江',
                '安徽', '福建', '江西', '山东', '河南', '湖北', '湖南', '广东', '广西',
                '海南', '四川', '贵州', '云南', '西藏', '陕西', '甘肃', '青海', '宁夏',
                '新疆', '北京', '天津', '上海', '重庆', '香港', '澳门',
            ) as $name) {
                $cache[$name] = $name;
            }
        }
        return $cache;
    }

    public static function resolveCityName($iploc)
    {
        $iploc = trim((string) $iploc);
        if ($iploc === '' || $iploc === '内网' || $iploc === '局域网' || $iploc === '本地') {
            return '';
        }
        $norm = str_replace(array('|', '/', '\\', '·', ',', '，', '-', '_'), ' ', $iploc);
        $norm = preg_replace('/\s*(电信|移动|联通|广电|铁通|教育网|鹏博士|方正宽带).*$/u', '', $norm);
        $norm = trim((string) $norm);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($norm, 'UTF-8') : strtolower($norm);

        // 省份上下文优先：先在该省内找具体市/州/县，避免省名或同名地名抢先命中。
        $province = self::provinceContext($norm);
        if ($province !== '') {
            $hit = self::matchProvinceScopedPlace($norm, $province);
            if ($hit !== '') {
                return $hit;
            }
        }

        // 省份明确但没有具体城市时，再回落到省内提示或省会。
        if ($province !== '') {
            $hit = self::matchRegionHintForProvince($norm, $province);
            if ($hit !== '') {
                return $hit;
            }
        }

        // 长别名优先（海南藏族自治州 → 共和）。
        $hit = self::matchAliasInText($norm, $lower);
        if ($hit !== '') {
            return $hit;
        }

        // 省内复合提示（青海 + 海南 → 共和）。
        $hit = self::matchRegionHint($norm);
        if ($hit !== '') {
            return $hit;
        }

        // 从「市 / 州 / 地区 / 盟 / 县 / 区 / 旗」提取。
        $hit = self::matchExtractedPlaces($norm);
        if ($hit !== '') {
            return $hit;
        }

        // 库内标准名最长子串；省份上下文下跳过同名的省级宽泛别名。
        foreach (self::matchNamesSorted() as $city) {
            if ($city === '') {
                continue;
            }
            if ($province !== '' && self::mapRegionName($city) === $province) {
                continue;
            }
            if (mb_strpos($norm, $city) !== false) {
                return $city;
            }
        }
        return '';
    }


    private static function provinceContext($norm)
    {
        $groups = self::loadMap('provinces.php');
        $keys = array_keys(is_array($groups) ? $groups : array());
        usort($keys, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        // 有明确行政区后缀时优先，避免「青海省海南藏族自治州」被内部的「海南」抢先识别。
        foreach ($keys as $province) {
            $province = (string) $province;
            if ($province !== '' && preg_match(
                '/' . preg_quote($province, '/') . '(省|市|自治区|特别行政区)/u',
                $norm
            )) {
                return $province;
            }
        }
        foreach ($keys as $province) {
            $province = (string) $province;
            if ($province !== '' && mb_strpos($norm, $province) !== false) {
                return $province;
            }
        }
        return '';
    }

    private static function matchProvinceScopedPlace($norm, $province)
    {
        $trimmed = trim((string) $norm);
        if (preg_match('/^' . preg_quote($province, '/') . '(省|市|自治区|特别行政区)?$/u', $trimmed)) {
            return '';
        }
        $china = self::china();
        $allowed = array();
        $groups = self::loadMap('provinces.php');
        if (isset($groups[$province]) && is_array($groups[$province])) {
            foreach ($groups[$province] as $name) {
                $name = trim((string) $name);
                if ($name !== '' && isset($china[$name])) {
                    $allowed[$name] = $name;
                }
            }
        }
        $aliases = self::aliases();
        $keys = array_keys($aliases);
        usort($keys, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        foreach ($keys as $alias) {
            $target = isset($aliases[$alias]) ? (string) $aliases[$alias] : '';
            if ($target === '' || !isset($allowed[$target])) {
                continue;
            }
            if (mb_strpos($norm, (string) $alias) !== false) {
                return $target;
            }
        }
        $names = array_keys($allowed);
        usort($names, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        foreach ($names as $name) {
            if ($name === $province) {
                if (!preg_match('/' . preg_quote($name, '/') . '(市|州|地区|县|区|旗)/u', $norm)) {
                    continue;
                }
            }
            if (mb_strpos($norm, $name) !== false) {
                return $name;
            }
        }
        return '';
    }

    /**
     * @param string $norm
     * @param string $province
     * @return string
     */
    private static function matchRegionHintForProvince($norm, $province)
    {
        $hints = self::regionHints();
        if (!isset($hints[$province]) || !is_array($hints[$province])) {
            return '';
        }
        $keys = array_keys($hints[$province]);
        usort($keys, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        foreach ($keys as $key) {
            if ($key !== '' && mb_strpos($norm, $key) !== false) {
                $city = (string) $hints[$province][$key];
                if ($city !== '' && (isset(self::china()[$city]) || isset(self::world()[$city]))) {
                    return $city;
                }
            }
        }
        return '';
    }

    /**
     * @param string $norm
     * @param string $lower
     * @return string
     */
    private static function matchAliasInText($norm, $lower)
    {
        $aliases = self::aliases();
        $aliasKeys = array_keys($aliases);
        usort($aliasKeys, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        foreach ($aliasKeys as $ak) {
            if ($ak === '') {
                continue;
            }
            $isAscii = (bool) preg_match('/^[a-z0-9 .\'-]+$/i', $ak);
            if ($isAscii && strlen($ak) <= 3) {
                if (!preg_match('/\b' . preg_quote($ak, '/') . '\b/i', $lower)) {
                    continue;
                }
                return $aliases[$ak];
            }
            if (strpos($lower, $ak) !== false || mb_strpos($norm, $ak) !== false) {
                return $aliases[$ak];
            }
        }
        return '';
    }

    /**
     * @param string $norm
     * @return string
     */
    private static function matchRegionHint($norm)
    {
        foreach (self::regionHints() as $provKey => $subs) {
            if ($provKey === '' || mb_strpos($norm, $provKey) === false) {
                continue;
            }
            if (!is_array($subs)) {
                continue;
            }
            $keys = array_keys($subs);
            usort($keys, function ($a, $b) {
                return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
            });
            foreach ($keys as $sub) {
                if ($sub !== '' && mb_strpos($norm, $sub) !== false) {
                    $city = (string) $subs[$sub];
                    if ($city !== '' && (isset(self::china()[$city]) || isset(self::world()[$city]))) {
                        return $city;
                    }
                }
            }
        }
        return '';
    }

    /**
     * @param string $norm
     * @return string
     */
    private static function matchExtractedPlaces($norm)
    {
        if (!preg_match_all('/([\x{4e00}-\x{9fa5}]{2,16})(?:自治州|自治县|地区|盟|市|州|县|区|旗)/u', $norm, $mm)) {
            return '';
        }
        $names = $mm[1];
        usort($names, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        $china = self::china();
        $world = self::world();
        foreach ($names as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            if (isset($china[$c]) || isset($world[$c])) {
                return $c;
            }
            // 整段 +「州」再走别名（海南藏族自治 → 海南藏族自治州）
            $withZhou = $c . '州';
            $aliases = self::aliases();
            if (isset($aliases[$withZhou])) {
                return $aliases[$withZhou];
            }
            if (isset($aliases[$c])) {
                return $aliases[$c];
            }
            $stripped = self::stripEthnicSuffix($c);
            if ($stripped !== '' && $stripped !== $c) {
                if (isset($china[$stripped]) || isset($world[$stripped])) {
                    return $stripped;
                }
                if (isset($aliases[$stripped . '州'])) {
                    return $aliases[$stripped . '州'];
                }
                if (isset($aliases[$stripped])) {
                    return $aliases[$stripped];
                }
            }
            foreach (self::matchNamesSorted() as $city) {
                if ($city !== '' && mb_strpos($c, $city) === 0) {
                    return $city;
                }
                if ($stripped !== '' && $city !== '' && mb_strpos($stripped, $city) === 0) {
                    return $city;
                }
            }
        }
        return '';
    }

    /**
     * 去掉民族自治等中缀，便于「文山壮族苗族」→「文山」
     *
     * @param string $name
     * @return string
     */
    private static function stripEthnicSuffix($name)
    {
        $name = (string) $name;
        $name = preg_replace(
            '/(壮族|回族|藏族|蒙古族|维吾尔族|维吾尔|苗族|彝族|土家族|布依族|侗族|瑶族|白族|哈尼族|傣族|黎族|傈僳族|佤族|畲族|高山族|拉祜族|水族|东乡族|纳西族|景颇族|柯尔克孜族|柯尔克孜|土族|达斡尔族|仫佬族|羌族|布朗族|撒拉族|毛南族|仡佬族|锡伯族|阿昌族|普米族|朝鲜族|塔吉克族|怒族|乌孜别克族|俄罗斯族|鄂温克族|德昂族|保安族|裕固族|京族|塔塔尔族|独龙族|鄂伦春族|赫哲族|门巴族|珞巴族|基诺族|哈萨克族|哈萨克)+/u',
            '',
            $name
        );
        $name = preg_replace('/自治$/u', '', (string) $name);
        return trim((string) $name);
    }
}
