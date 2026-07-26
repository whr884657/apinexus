/**
 * 默认主题 · API 详情「快速上手」多语言示例
 */
(function () {
    'use strict';

    var tabsEl = document.getElementById('detailQsTabs');
    var codeWrap = document.getElementById('detailQsCode');
    var copyBtn = document.getElementById('detailQsCopy');
    var api = window.detailApiData;
    if (!tabsEl || !codeWrap || !api || !api.endpoint) {
        return;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function escSq(s) {
        return String(s == null ? '' : s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    var endpoint = String(api.endpoint || '').trim();
    var method = String(api.method || 'GET').toUpperCase();
    var params = Array.isArray(api.params_list) ? api.params_list : [];
    var needKey = parseInt(api.needkey, 10) || 0;
    var isGet = method === 'GET' || method === 'HEAD';

    function sampleVal(p) {
        if (p && p.example) {
            return String(p.example);
        }
        var t = String((p && p.type) || '').toLowerCase();
        if (t === 'number' || t === 'int' || t === 'integer' || t === 'float') {
            return '1';
        }
        if (t === 'boolean' || t === 'bool') {
            return 'true';
        }
        var name = String((p && p.name) || '');
        if (needKey && /^(key|api_key|apikey)$/i.test(name)) {
            return 'YOUR_API_KEY';
        }
        return name || 'value';
    }

    var pairs = [];
    params.forEach(function (p) {
        if (!p || !p.name) {
            return;
        }
        if (String(p.type || '').toLowerCase() === 'file') {
            return;
        }
        pairs.push({ name: String(p.name), value: sampleVal(p) });
    });
    if (needKey && !pairs.some(function (x) { return /^(key|api_key|apikey)$/i.test(x.name); })) {
        pairs.push({ name: 'key', value: 'YOUR_API_KEY' });
    }

    function queryOf(list) {
        return list.map(function (p) {
            return encodeURIComponent(p.name) + '=' + encodeURIComponent(p.value);
        }).join('&');
    }

    function urlWithQuery(list) {
        var q = queryOf(list);
        if (!q) {
            return endpoint;
        }
        return endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + q;
    }

    function asObj(list) {
        var o = {};
        list.forEach(function (p) { o[p.name] = p.value; });
        return o;
    }

    var fullUrl = isGet ? urlWithQuery(pairs) : endpoint;
    var obj = asObj(pairs);
    var phpArr = '[' + pairs.map(function (p) {
        return "'" + escSq(p.name) + "' => '" + escSq(p.value) + "'";
    }).join(', ') + ']';
    var rustForm = '[' + pairs.map(function (p) {
        return '("' + esc(p.name) + '", "' + esc(p.value) + '")';
    }).join(', ') + ']';
    var curlData = pairs.map(function (p) {
        return '  --data-urlencode ' + JSON.stringify(p.name + '=' + p.value);
    }).join(' \\\n');

    var langs = [
        {
            label: 'cURL',
            code: isGet
                ? 'curl -X ' + method + ' ' + JSON.stringify(fullUrl)
                : 'curl -X ' + method + ' ' + JSON.stringify(endpoint) + ' \\\n'
                    + '  -H "Content-Type: application/x-www-form-urlencoded" \\\n'
                    + (curlData || '  --data ""')
        },
        {
            label: 'TypeScript',
            code: isGet
                ? "const res = await fetch('" + escSq(fullUrl) + "', {\n  method: '" + method + "'\n});\n"
                    + "const data = await res.json();\nconsole.log(data);"
                : "const body = new URLSearchParams(" + JSON.stringify(obj, null, 2) + ");\n"
                    + "const res = await fetch('" + escSq(endpoint) + "', {\n"
                    + "  method: '" + method + "',\n"
                    + "  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },\n"
                    + "  body\n});\nconst data = await res.json();\nconsole.log(data);"
        },
        {
            label: 'Browser',
            code: isGet
                ? "fetch('" + escSq(fullUrl) + "')\n  .then(r => r.json())\n  .then(console.log)\n  .catch(console.error);"
                : "fetch('" + escSq(endpoint) + "', {\n"
                    + "  method: '" + method + "',\n"
                    + "  headers: { 'Content-Type': 'application/json' },\n"
                    + "  body: JSON.stringify(" + JSON.stringify(obj) + ")\n"
                    + "})\n  .then(r => r.json())\n  .then(console.log)\n  .catch(console.error);"
        },
        {
            label: 'Python',
            code: isGet
                ? "import requests\n\nresp = requests." + method.lower() + "('" + escSq(fullUrl) + "')\nprint(resp.json())"
                : "import requests\n\nresp = requests." + method.lower() + "(\n"
                    + "    '" + escSq(endpoint) + "',\n"
                    + "    data=" + JSON.stringify(obj, null, 4) + "\n)\nprint(resp.json())"
        },
        {
            label: 'Go',
            code: isGet
                ? "package main\n\nimport (\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n)\n\n"
                    + "func main() {\n\tres, err := http.Get(\"" + esc(fullUrl) + "\")\n"
                    + "\tif err != nil { panic(err) }\n\tdefer res.Body.Close()\n"
                    + "\tb, _ := io.ReadAll(res.Body)\n\tfmt.Println(string(b))\n}"
                : "package main\n\nimport (\n\t\"fmt\"\n\t\"io\"\n\t\"net/http\"\n\t\"net/url\"\n\t\"strings\"\n)\n\n"
                    + "func main() {\n\tform := url.Values{}\n"
                    + pairs.map(function (p) {
                        return "\tform.Set(\"" + esc(p.name) + "\", \"" + esc(p.value) + "\")";
                    }).join('\n') + "\n"
                    + "\treq, _ := http.NewRequest(\"" + method + "\", \"" + esc(endpoint) + "\", strings.NewReader(form.Encode()))\n"
                    + "\treq.Header.Set(\"Content-Type\", \"application/x-www-form-urlencoded\")\n"
                    + "\tres, err := http.DefaultClient.Do(req)\n\tif err != nil { panic(err) }\n"
                    + "\tdefer res.Body.Close()\n\tb, _ := io.ReadAll(res.Body)\n\tfmt.Println(string(b))\n}"
        },
        {
            label: 'Java',
            code: "import java.net.URI;\nimport java.net.http.*;\n\n"
                + "HttpClient client = HttpClient.newHttpClient();\n"
                + (isGet
                    ? "HttpRequest request = HttpRequest.newBuilder()\n"
                        + "    .uri(URI.create(\"" + esc(fullUrl) + "\"))\n    .GET()\n    .build();\n"
                    : "HttpRequest request = HttpRequest.newBuilder()\n"
                        + "    .uri(URI.create(\"" + esc(endpoint) + "\"))\n"
                        + "    .header(\"Content-Type\", \"application/json\")\n"
                        + "    .method(\"" + method + "\", HttpRequest.BodyPublishers.ofString("
                        + JSON.stringify(JSON.stringify(obj)) + "))\n    .build();\n")
                + "HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());\n"
                + "System.out.println(response.body());"
        },
        {
            label: 'PHP',
            code: isGet
                ? "<?php\n$ch = curl_init('" + escSq(fullUrl) + "');\n"
                    + "curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n"
                    + "$res = curl_exec($ch);\ncurl_close($ch);\necho $res;"
                : "<?php\n$ch = curl_init('" + escSq(endpoint) + "');\n"
                    + "curl_setopt_array($ch, [\n"
                    + "    CURLOPT_RETURNTRANSFER => true,\n"
                    + "    CURLOPT_CUSTOMREQUEST => '" + method + "',\n"
                    + "    CURLOPT_POSTFIELDS => http_build_query(" + phpArr + "),\n"
                    + "]);\n$res = curl_exec($ch);\ncurl_close($ch);\necho $res;"
        },
        {
            label: 'C++',
            code: "// 需链接 libcurl\n#include <curl/curl.h>\n#include <iostream>\n\n"
                + "int main() {\n  CURL *curl = curl_easy_init();\n  if (!curl) return 1;\n"
                + "  curl_easy_setopt(curl, CURLOPT_URL, \"" + esc(isGet ? fullUrl : endpoint) + "\");\n"
                + (isGet ? '' : "  curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, \"" + method + "\");\n"
                    + "  curl_easy_setopt(curl, CURLOPT_POSTFIELDS, \"" + esc(queryOf(pairs)) + "\");\n")
                + "  CURLcode res = curl_easy_perform(curl);\n"
                + "  if (res != CURLE_OK) std::cerr << curl_easy_strerror(res);\n"
                + "  curl_easy_cleanup(curl);\n  return 0;\n}"
        },
        {
            label: 'C',
            code: "/* 需链接 libcurl */\n#include <stdio.h>\n#include <curl/curl.h>\n\n"
                + "int main(void) {\n  CURL *curl = curl_easy_init();\n  if (!curl) return 1;\n"
                + "  curl_easy_setopt(curl, CURLOPT_URL, \"" + esc(isGet ? fullUrl : endpoint) + "\");\n"
                + (isGet ? '' : "  curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, \"" + method + "\");\n"
                    + "  curl_easy_setopt(curl, CURLOPT_POSTFIELDS, \"" + esc(queryOf(pairs)) + "\");\n")
                + "  CURLcode res = curl_easy_perform(curl);\n"
                + "  if (res != CURLE_OK) fprintf(stderr, \"%s\\n\", curl_easy_strerror(res));\n"
                + "  curl_easy_cleanup(curl);\n  return 0;\n}"
        },
        {
            label: 'Rust',
            code: isGet
                ? "// Cargo: reqwest = { version = \"0.12\", features = [\"blocking\"] }\n"
                    + "fn main() -> Result<(), Box<dyn std::error::Error>> {\n"
                    + "    let body = reqwest::blocking::get(\"" + esc(fullUrl) + "\")?.text()?;\n"
                    + "    println!(\"{}\", body);\n    Ok(())\n}"
                : "// Cargo: reqwest = { version = \"0.12\", features = [\"blocking\"] }\n"
                    + "fn main() -> Result<(), Box<dyn std::error::Error>> {\n"
                    + "    let client = reqwest::blocking::Client::new();\n"
                    + "    let res = client\n"
                    + "        .request(reqwest::Method::from_bytes(b\"" + method + "\")?, \"" + esc(endpoint) + "\")\n"
                    + "        .form(&" + rustForm + ")\n        .send()?;\n"
                    + "    println!(\"{}\", res.text()?);\n    Ok(())\n}"
        }
    ];

    var codeNode = codeWrap.querySelector('code') || codeWrap;
    var active = 0;

    function render() {
        var item = langs[active];
        if (!item) {
            return;
        }
        codeNode.textContent = item.code;
        if (window.VsSyntax && typeof window.VsSyntax.highlightElement === 'function') {
            codeNode.removeAttribute('data-vs-syn-done');
            window.VsSyntax.highlightElement(codeNode);
        }
        Array.prototype.forEach.call(tabsEl.querySelectorAll('.detail-quickstart__tab'), function (btn, i) {
            var on = i === active;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    langs.forEach(function (item, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'detail-quickstart__tab' + (i === 0 ? ' is-active' : '');
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
        btn.textContent = item.label;
        btn.addEventListener('click', function () {
            active = i;
            render();
        });
        tabsEl.appendChild(btn);
    });
    render();

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var text = codeNode.textContent || '';
            var done = function () {
                copyBtn.textContent = '已复制';
                copyBtn.classList.add('is-copied');
                if (window.VsToast && typeof window.VsToast.show === 'function') {
                    window.VsToast.show('已复制', 'success');
                }
                setTimeout(function () {
                    copyBtn.textContent = '复制';
                    copyBtn.classList.remove('is-copied');
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {});
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                done();
            } catch (e) { /* ignore */ }
            document.body.removeChild(ta);
        });
    }
})();
