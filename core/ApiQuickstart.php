<?php
/**
 * 文件：core/ApiQuickstart.php
 * 作用：默认主题 API 详情「快速上手」多语言调用示例（文案与图标）
 *
 * 图标目录：assets/img/lang/（灰版 *.svg + 彩版 *-color.svg；cURL 仅一份）
 */

class ApiQuickstart
{
    /**
     * @return array<int,array{id:string,label:string,icon:string}>
     */
    public static function langMeta()
    {
        return array(
            array('id' => 'curl', 'label' => 'cURL', 'icon' => 'curl'),
            array('id' => 'typescript', 'label' => 'TypeScript', 'icon' => 'typescript'),
            array('id' => 'browser', 'label' => 'Browser', 'icon' => 'browser'),
            array('id' => 'python', 'label' => 'Python', 'icon' => 'python'),
            array('id' => 'go', 'label' => 'Go', 'icon' => 'go'),
            array('id' => 'java', 'label' => 'Java', 'icon' => 'java'),
            array('id' => 'php', 'label' => 'PHP', 'icon' => 'php'),
            array('id' => 'cpp', 'label' => 'C++', 'icon' => 'cpp'),
            array('id' => 'rust', 'label' => 'Rust', 'icon' => 'rust'),
        );
    }

    /**
     * @param string $icon 如 curl / python
     * @param bool   $color
     * @return string
     */
    public static function iconUrl($icon, $color = false)
    {
        $icon = preg_replace('/[^a-z0-9_-]/i', '', (string) $icon);
        if ($icon === '') {
            return '';
        }
        $base = rtrim(vs_base_url(), '/') . '/assets/img/lang/';
        if ($icon === 'curl') {
            return $base . 'curl.svg';
        }
        return $base . $icon . ($color ? '-color.svg' : '.svg');
    }

    /**
     * @param string               $endpoint
     * @param string               $method
     * @param array<int,array>     $paramsList
     * @param int                  $needkey
     * @return array<int,array{id:string,label:string,icon:string,code:string,icon_gray:string,icon_color:string}>
     */
    public static function buildSamples($endpoint, $method, array $paramsList, $needkey = 0)
    {
        $endpoint = trim((string) $endpoint);
        $method = strtoupper(trim((string) $method));
        if ($method === '') {
            $method = 'GET';
        }
        $needkey = (int) $needkey;
        $isGet = ($method === 'GET' || $method === 'HEAD');

        $pairs = array();
        foreach ($paramsList as $p) {
            if (!is_array($p) || empty($p['name'])) {
                continue;
            }
            if (strtolower((string) (isset($p['type']) ? $p['type'] : '')) === 'file') {
                continue;
            }
            $pairs[] = array(
                'name'  => (string) $p['name'],
                'value' => self::sampleVal($p, $needkey),
            );
        }
        $hasKey = false;
        foreach ($pairs as $row) {
            if (preg_match('/^(key|api_key|apikey)$/i', $row['name'])) {
                $hasKey = true;
                break;
            }
        }
        if ($needkey > 0 && !$hasKey) {
            $pairs[] = array('name' => 'key', 'value' => 'YOUR_API_KEY');
        }

        $fullUrl = $isGet ? self::urlWithQuery($endpoint, $pairs) : $endpoint;
        $obj = array();
        foreach ($pairs as $row) {
            $obj[$row['name']] = $row['value'];
        }
        $query = self::queryOf($pairs);
        $phpArr = self::phpArrayLiteral($obj);
        $rustForm = self::rustFormLiteral($pairs);
        $curlData = array();
        foreach ($pairs as $row) {
            $curlData[] = '  --data-urlencode ' . json_encode($row['name'] . '=' . $row['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $curlDataStr = $curlData !== array() ? implode(" \\\n", $curlData) : '  --data ""';
        $jsonBody = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonPretty = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $codes = array(
            'curl' => $isGet
                ? 'curl -X ' . $method . ' ' . json_encode($fullUrl, JSON_UNESCAPED_SLASHES)
                : 'curl -X ' . $method . ' ' . json_encode($endpoint, JSON_UNESCAPED_SLASHES) . " \\\n"
                    . '  -H "Content-Type: application/x-www-form-urlencoded" \\\n'
                    . $curlDataStr,
            'typescript' => $isGet
                ? "const res = await fetch('" . self::sq($fullUrl) . "', {\n  method: '" . $method . "'\n});\n"
                    . "const data = await res.json();\nconsole.log(data);"
                : "const body = new URLSearchParams(" . ($jsonPretty !== false ? $jsonPretty : '{}') . ");\n"
                    . "const res = await fetch('" . self::sq($endpoint) . "', {\n"
                    . "  method: '" . $method . "',\n"
                    . "  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },\n"
                    . "  body\n});\nconst data = await res.json();\nconsole.log(data);",
            'browser' => $isGet
                ? "fetch('" . self::sq($fullUrl) . "')\n  .then(r => r.json())\n  .then(console.log)\n  .catch(console.error);"
                : "fetch('" . self::sq($endpoint) . "', {\n"
                    . "  method: '" . $method . "',\n"
                    . "  headers: { 'Content-Type': 'application/json' },\n"
                    . "  body: JSON.stringify(" . ($jsonBody !== false ? $jsonBody : '{}') . ")\n"
                    . "})\n  .then(r => r.json())\n  .then(console.log)\n  .catch(console.error);",
            'python' => $isGet
                ? "import requests\n\nresp = requests." . strtolower($method) . "('" . self::sq($fullUrl) . "')\nprint(resp.json())"
                : "import requests\n\nresp = requests." . strtolower($method) . "(\n"
                    . "    '" . self::sq($endpoint) . "',\n"
                    . "    data=" . ($jsonPretty !== false ? $jsonPretty : '{}') . "\n)\nprint(resp.json())",
            'go' => $isGet
                ? "package main\n\nimport (\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n)\n\n"
                    . "func main() {\n\tres, err := http.Get(\"" . self::dq($fullUrl) . "\")\n"
                    . "\tif err != nil { panic(err) }\n\tdefer res.Body.Close()\n"
                    . "\tb, _ := io.ReadAll(res.Body)\n\tfmt.Println(string(b))\n}"
                : "package main\n\nimport (\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n\t\"net/url\"\n\t\"strings\"\n)\n\n"
                    . "func main() {\n\tform := url.Values{}\n"
                    . self::goFormSets($pairs)
                    . "\treq, _ := http.NewRequest(\"" . $method . "\", \"" . self::dq($endpoint) . "\", strings.NewReader(form.Encode()))\n"
                    . "\treq.Header.Set(\"Content-Type\", \"application/x-www-form-urlencoded\")\n"
                    . "\tres, err := http.DefaultClient.Do(req)\n\tif err != nil { panic(err) }\n"
                    . "\tdefer res.Body.Close()\n\tb, _ := io.ReadAll(res.Body)\n\tfmt.Println(string(b))\n}",
            'java' => "import java.net.URI;\nimport java.net.http.*;\n\n"
                . "HttpClient client = HttpClient.newHttpClient();\n"
                . ($isGet
                    ? "HttpRequest request = HttpRequest.newBuilder()\n"
                        . "    .uri(URI.create(\"" . self::dq($fullUrl) . "\"))\n    .GET()\n    .build();\n"
                    : "HttpRequest request = HttpRequest.newBuilder()\n"
                        . "    .uri(URI.create(\"" . self::dq($endpoint) . "\"))\n"
                        . "    .header(\"Content-Type\", \"application/json\")\n"
                        . "    .method(\"" . $method . "\", HttpRequest.BodyPublishers.ofString("
                        . json_encode($jsonBody !== false ? $jsonBody : '{}', JSON_UNESCAPED_SLASHES)
                        . "))\n    .build();\n")
                . "HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());\n"
                . "System.out.println(response.body());",
            'php' => $isGet
                ? "<?php\n\$ch = curl_init('" . self::sq($fullUrl) . "');\n"
                    . "curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n"
                    . "\$res = curl_exec(\$ch);\ncurl_close(\$ch);\necho \$res;"
                : "<?php\n\$ch = curl_init('" . self::sq($endpoint) . "');\n"
                    . "curl_setopt_array(\$ch, [\n"
                    . "    CURLOPT_RETURNTRANSFER => true,\n"
                    . "    CURLOPT_CUSTOMREQUEST => '" . $method . "',\n"
                    . "    CURLOPT_POSTFIELDS => http_build_query(" . $phpArr . "),\n"
                    . "]);\n\$res = curl_exec(\$ch);\ncurl_close(\$ch);\necho \$res;",
            'cpp' => "// 需链接 libcurl\n#include <curl/curl.h>\n#include <iostream>\n\n"
                . "int main() {\n  CURL *curl = curl_easy_init();\n  if (!curl) return 1;\n"
                . "  curl_easy_setopt(curl, CURLOPT_URL, \"" . self::dq($isGet ? $fullUrl : $endpoint) . "\");\n"
                . ($isGet ? '' : "  curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, \"" . $method . "\");\n"
                    . "  curl_easy_setopt(curl, CURLOPT_POSTFIELDS, \"" . self::dq($query) . "\");\n")
                . "  CURLcode res = curl_easy_perform(curl);\n"
                . "  if (res != CURLE_OK) std::cerr << curl_easy_strerror(res);\n"
                . "  curl_easy_cleanup(curl);\n  return 0;\n}",
            'rust' => $isGet
                ? "// Cargo: reqwest = { version = \"0.12\", features = [\"blocking\"] }\n"
                    . "fn main() -> Result<(), Box<dyn std::error::Error>> {\n"
                    . "    let body = reqwest::blocking::get(\"" . self::dq($fullUrl) . "\")?.text()?;\n"
                    . "    println!(\"{}\", body);\n    Ok(())\n}"
                : "// Cargo: reqwest = { version = \"0.12\", features = [\"blocking\"] }\n"
                    . "fn main() -> Result<(), Box<dyn std::error::Error>> {\n"
                    . "    let client = reqwest::blocking::Client::new();\n"
                    . "    let res = client\n"
                    . "        .request(reqwest::Method::from_bytes(b\"" . $method . "\")?, \"" . self::dq($endpoint) . "\")\n"
                    . "        .form(&" . $rustForm . ")\n        .send()?;\n"
                    . "    println!(\"{}\", res.text()?);\n    Ok(())\n}",
        );

        $out = array();
        foreach (self::langMeta() as $meta) {
            $id = $meta['id'];
            $icon = $meta['icon'];
            $out[] = array(
                'id'         => $id,
                'label'      => $meta['label'],
                'icon'       => $icon,
                'code'       => isset($codes[$id]) ? $codes[$id] : '',
                'icon_gray'  => self::iconUrl($icon, false),
                'icon_color' => self::iconUrl($icon, true),
                'single_icon'=> ($icon === 'curl') ? 1 : 0,
            );
        }
        return $out;
    }

    /**
     * @param array $p
     * @param int   $needkey
     * @return string
     */
    private static function sampleVal(array $p, $needkey)
    {
        if (!empty($p['example'])) {
            return (string) $p['example'];
        }
        $t = strtolower((string) (isset($p['type']) ? $p['type'] : ''));
        if ($t === 'number' || $t === 'int' || $t === 'integer' || $t === 'float') {
            return '1';
        }
        if ($t === 'boolean' || $t === 'bool') {
            return 'true';
        }
        $name = (string) (isset($p['name']) ? $p['name'] : '');
        if ($needkey > 0 && preg_match('/^(key|api_key|apikey)$/i', $name)) {
            return 'YOUR_API_KEY';
        }
        return $name !== '' ? $name : 'value';
    }

    /**
     * @param array<int,array{name:string,value:string}> $pairs
     * @return string
     */
    private static function queryOf(array $pairs)
    {
        $parts = array();
        foreach ($pairs as $row) {
            $parts[] = rawurlencode($row['name']) . '=' . rawurlencode($row['value']);
        }
        return implode('&', $parts);
    }

    /**
     * @param string $endpoint
     * @param array  $pairs
     * @return string
     */
    private static function urlWithQuery($endpoint, array $pairs)
    {
        $q = self::queryOf($pairs);
        if ($q === '') {
            return $endpoint;
        }
        return $endpoint . (strpos($endpoint, '?') !== false ? '&' : '?') . $q;
    }

    /**
     * @param array<string,string> $obj
     * @return string
     */
    private static function phpArrayLiteral(array $obj)
    {
        $parts = array();
        foreach ($obj as $k => $v) {
            $parts[] = "'" . self::sq((string) $k) . "' => '" . self::sq((string) $v) . "'";
        }
        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<int,array{name:string,value:string}> $pairs
     * @return string
     */
    private static function rustFormLiteral(array $pairs)
    {
        $parts = array();
        foreach ($pairs as $row) {
            $parts[] = '("' . self::dq($row['name']) . '", "' . self::dq($row['value']) . '")';
        }
        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<int,array{name:string,value:string}> $pairs
     * @return string
     */
    private static function goFormSets(array $pairs)
    {
        $s = '';
        foreach ($pairs as $row) {
            $s .= "\tform.Set(\"" . self::dq($row['name']) . "\", \"" . self::dq($row['value']) . "\")\n";
        }
        return $s;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function sq($s)
    {
        return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s);
    }

    /**
     * @param string $s
     * @return string
     */
    private static function dq($s)
    {
        return str_replace(array('\\', '"'), array('\\\\', '\\"'), (string) $s);
    }
}
