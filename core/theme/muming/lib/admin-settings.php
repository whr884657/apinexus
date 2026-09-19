<?php
/**
 * 主题5 后台设置面板（分组布局）
 */

if (!defined('VS_IN_ADMIN') && !defined('VS_ROOT')) {
    exit;
}

/**
 * @param array<int, array<string, mixed>> $schema
 * @param array<string, mixed> $values
 * @return void
 */
function vs_theme_admin_render_settings_muming($schema, $values)
{
    $fields = array();
    foreach ($schema as $field) {
        if (!empty($field['key'])) {
            $fields[(string) $field['key']] = $field;
        }
    }

    $val = function ($key) use ($fields, $values) {
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        if (isset($fields[$key]['default'])) {
            return $fields[$key]['default'];
        }
        return '';
    };

    $renderSelect = function ($key) use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = isset($field['label']) ? (string) $field['label'] : $key;
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" data-vs-pick>';
        $options = !empty($field['options']) && is_array($field['options']) ? $field['options'] : array();
        foreach ($options as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ((string) $current === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select>';
        if ($key === 'stats_num_format') {
            echo '<p class="vs-form-hint">完整数字：实时有多少显示多少；单位转换：达到千/万后显示为 K+、W+</p>';
        }
        echo '</div>';
    };

    $renderCheckbox = function ($key) use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = isset($field['label']) ? (string) $field['label'] : $key;
        $checked = $current === true || $current === 1 || $current === '1' || $current === 'true';
        echo '<div class="vs-theme-config-field vs-theme-config-field--check">';
        echo '<label class="vs-theme-config-check">';
        echo '<input type="checkbox" name="settings[' . vs_e($key) . ']" value="1"' . ($checked ? ' checked' : '') . '>';
        echo '<span>' . vs_e($label) . '</span></label>';
        echo '</div>';
    };

    $renderText = function ($key, $shortLabel = '') use ($fields, $val) {
        if (!isset($fields[$key])) {
            return;
        }
        $field = $fields[$key];
        $current = $val($key);
        $label = $shortLabel !== '' ? $shortLabel : (isset($field['label']) ? (string) $field['label'] : $key);
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($key) . '">' . vs_e($label) . '</label>';
        echo '<input type="text" class="vs-input" id="ts_' . vs_e($key) . '" name="settings[' . vs_e($key) . ']" value="' . vs_e($current === null ? '' : (string) $current) . '" placeholder="' . vs_e($placeholder) . '"';
        if (preg_match('/^footer_social_\d+_value$/', $key)) {
            echo ' maxlength="512"';
        }
        echo '>';
        if (preg_match('/^footer_social_\d+_value$/', $key)) {
            echo '<p class="vs-form-hint">最多 512 个字符（链接、邮箱或二维码图片地址）</p>';
        }
        echo '</div>';
    };

    $socialTypes = isset($fields['footer_social_1_type']['options']) ? $fields['footer_social_1_type']['options'] : array();
    $qqModes = isset($fields['footer_social_1_qq_mode']['options']) ? $fields['footer_social_1_qq_mode']['options'] : array();

    echo '<div class="th5-admin-settings">';

    echo '<section class="th5-admin-settings__section">';
    echo '<h3 class="th5-admin-settings__title">首页展示</h3>';
    echo '<div class="th5-admin-settings__grid th5-admin-settings__grid--2">';
    $renderSelect('stats_num_format');
    $renderSelect('home_preview_limit');
    echo '</div>';
    echo '</section>';

    echo '<section class="th5-admin-settings__section">';
    echo '<h3 class="th5-admin-settings__title">功能开关</h3>';
    echo '<div class="th5-admin-settings__checks">';
    $renderCheckbox('show_home_announce');
    $renderCheckbox('show_runtime');
    echo '</div>';
    echo '</section>';

        echo '<section class="th5-admin-settings__section">';
    echo '<h3 class="th5-admin-settings__title">首页广告位</h3>';
    echo '<p class="th5-admin-settings__hint">最多配置 4 条广告，多条时在首页自动左右平移轮播（无缝衔接）。图片广告需上传/填写图片地址；纯文字广告只需填写文案；未启用或留空的广告位自动跳过。</p>';
    echo '<div class="th5-admin-settings__grid th5-admin-settings__grid--2">';
    $renderCheckbox('home_ad_enabled');
    $renderSelect('home_ad_interval');
    echo '</div>';

    $slotVal = function ($key, $slot) use ($fields, $values) {
        $slotKey = 'home_ad_' . $slot . '_' . $key;
        if (array_key_exists($slotKey, $values) && $values[$slotKey] !== null && $values[$slotKey] !== '') {
            return $values[$slotKey];
        }
        if ($slot === 1 && array_key_exists('home_ad_' . $key, $values) && $values['home_ad_' . $key] !== null && $values['home_ad_' . $key] !== '') {
            return $values['home_ad_' . $key];
        }
        if (isset($fields[$slotKey]['default'])) {
            return $fields[$slotKey]['default'];
        }
        return '';
    };

    $renderSlotText = function ($key, $label, $slot) use ($fields, $slotVal) {
        $field = isset($fields['home_ad_' . $slot . '_' . $key]) ? $fields['home_ad_' . $slot . '_' . $key] : array();
        $placeholder = isset($field['placeholder']) ? (string) $field['placeholder'] : '';
        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_home_ad_' . $slot . '_' . $key . '">' . vs_e($label) . '</label>';
        echo '<input type="text" class="vs-input" id="ts_home_ad_' . $slot . '_' . $key . '" name="settings[home_ad_' . $slot . '_' . $key . ']" value="' . vs_e((string) $slotVal($key, $slot)) . '" placeholder="' . vs_e($placeholder) . '">';
        echo '</div>';
    };

    $adVsBase = function_exists('vs_site_base_path') ? rtrim((string) vs_site_base_path(), '/') : '';
    $adUploadUrl = $adVsBase . '/core/theme/muming/api/ad-upload.php';

    for ($slot = 1; $slot <= 4; $slot++) {
        $sEnabled = $slotVal('enabled', $slot);
        $sEnabledChecked = ($sEnabled === true || $sEnabled === 1 || $sEnabled === '1' || $sEnabled === 'true');
        $sTypeVal = (string) $slotVal('type', $slot);
        $sImageVal = (string) $slotVal('image', $slot);
        $sTypeField = isset($fields['home_ad_' . $slot . '_type']) ? $fields['home_ad_' . $slot . '_type'] : array();
        $sImgField = isset($fields['home_ad_' . $slot . '_image']) ? $fields['home_ad_' . $slot . '_image'] : array();
        $sImgPlaceholder = isset($sImgField['placeholder']) ? (string) $sImgField['placeholder'] : '';

        echo '<div class="th5-admin-ad-slot" data-th5-ad-field="' . $slot . '">';
        echo '<div class="th5-admin-ad-slot__head">广告 ' . $slot . '</div>';
        echo '<div class="th5-admin-ad-slot__body">';

        echo '<div class="th5-admin-ad-slot__top">';
        echo '<label class="vs-theme-config-check">';
        echo '<input type="checkbox" name="settings[home_ad_' . $slot . '_enabled]" value="1"' . ($sEnabledChecked ? ' checked' : '') . '>';
        echo '<span>启用此广告</span></label>';
        echo '<div class="vs-theme-config-field" style="margin-bottom:0;min-width:200px;">';
        echo '<label class="vs-label" for="ts_home_ad_' . $slot . '_type">类型</label>';
        echo '<select class="vs-input vs-select" id="ts_home_ad_' . $slot . '_type" name="settings[home_ad_' . $slot . '_type]" data-vs-pick>';
        $sTypeOptions = !empty($sTypeField['options']) && is_array($sTypeField['options']) ? $sTypeField['options'] : array();
        foreach ($sTypeOptions as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ($sTypeVal === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select></div>';
        echo '</div>';

        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_home_ad_' . $slot . '_image">图片地址</label>';
        echo '<div class="th5-ad-upload-row">';
        echo '<input type="text" class="vs-input" id="ts_home_ad_' . $slot . '_image" name="settings[home_ad_' . $slot . '_image]" value="' . vs_e($sImageVal) . '" placeholder="' . vs_e($sImgPlaceholder) . '" maxlength="512" data-th5-ad-url="1">';
        echo '<label class="th5-ad-upload-btn" for="th5_ad_file_input_' . $slot . '" data-th5-ad-trigger="1">上传图片</label>';
        echo '<input type="file" id="th5_ad_file_input_' . $slot . '" accept="image/jpeg,image/png,image/gif,image/webp" data-th5-ad-file="1" hidden>';
        echo '</div>';
        echo '<div class="th5-ad-upload-preview"' . ($sImageVal !== '' ? '' : ' hidden') . ' data-th5-ad-preview="1"><img src="' . vs_e($sImageVal) . '" alt="广告 ' . $slot . ' 图片预览" data-th5-ad-preview-img="1"></div>';
        echo '<p class="vs-form-hint">支持 jpg / png / gif / webp，单张不超过 2MB；上传后自动填入地址，也可直接粘贴外部图片地址。</p>';
        echo '</div>';

        $renderSlotText('badge', '标识文案', $slot);
        $renderSlotText('title', '标题', $slot);
        $renderSlotText('desc', '描述', $slot);
        $renderSlotText('link', '跳转链接', $slot);
        $renderSlotText('button_text', '按钮文字', $slot);

        echo '</div></div>';
    }

    echo '</section>';

    echo '<style>' . "\n"
        . '.th5-ad-upload-row{display:flex;gap:8px;align-items:center;}' . "\n"
        . '.th5-ad-upload-row .vs-input{flex:1;min-width:0;}' . "\n"
        . '.th5-ad-upload-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:8px;background:var(--accent,#1B6BFF);color:#fff;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;user-select:none;flex-shrink:0;}' . "\n"
        . '.th5-ad-upload-btn.is-busy{opacity:.6;pointer-events:none;}' . "\n"
        . '.th5-ad-upload-preview{margin-top:10px;}' . "\n"
        . '.th5-ad-upload-preview img{max-width:220px;max-height:120px;border-radius:8px;border:1px solid var(--border,#e3e8f0);display:block;}' . "\n"
        . '.th5-admin-ad-slot{border:1px solid var(--border,#e3e8f0);border-radius:12px;padding:16px 18px;margin-bottom:16px;}' . "\n"
        . '.th5-admin-ad-slot__head{font-size:13px;font-weight:700;margin:0 0 12px;}' . "\n"
        . '.th5-admin-ad-slot__top{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:8px;}' . "\n"
        . '.th5-admin-ad-slot__top .vs-theme-config-check{display:inline-flex;align-items:center;gap:8px;font-size:13.5px;cursor:pointer;padding:9px 0;}' . "\n"
        . '.th5-admin-ad-slot__top .vs-theme-config-check input{width:15px;height:15px;}' . "\n"
        . '</style>' . "\n";

    echo '<script>' . "\n"
        . '(function(){' . "\n"
        . 'var uploadUrl=' . json_encode($adUploadUrl, JSON_UNESCAPED_UNICODE) . ';' . "\n"
        . 'function findCsrf(){' . "\n"
        . 'var names=["VS_CSRF_TOKEN","CSRF_TOKEN","_csrf","csrfToken","csrf"];' . "\n"
        . 'for(var i=0;i<names.length;i++){if(typeof window[names[i]]==="string"&&window[names[i]]){return window[names[i]];}}' . "\n"
        . 'var metas=document.querySelectorAll("meta");' . "\n"
        . 'for(var j=0;j<metas.length;j++){var mn=(metas[j].getAttribute("name")||"").toLowerCase();var mc=metas[j].getAttribute("content")||"";if(mc&&(/csrf|token/.test(mn))){return mc;}}' . "\n"
        . 'var ins=document.querySelectorAll("input");' . "\n"
        . 'for(var k=0;k<ins.length;k++){var nm=(ins[k].getAttribute("name")||"").toLowerCase();if(ins[k].value&&(/csrf|token/.test(nm))){return ins[k].value;}}' . "\n"
        . 'var forms=document.querySelectorAll("form");' . "\n"
        . 'for(var m=0;m<forms.length;m++){var dv=forms[m].getAttribute("data-csrf")||forms[m].getAttribute("data-csrf-token")||"";if(dv){return dv;}}' . "\n"
        . 'return "";' . "\n"
        . '}' . "\n"
        . 'function isCredFail(data){' . "\n"
        . 'if(!data||typeof data!=="object"||Number(data.code)===1){return false;}' . "\n"
        . 'if(data.csrf){return true;}' . "\n"
        . 'return /凭证|csrf|刷新页面|来源无效/i.test(String(data.msg||""));' . "\n"
        . '}' . "\n"
        . 'function toast(msg,ok){' . "\n"
        . 'if(window.VS&&typeof VS.showMessage==="function"){VS.showMessage(msg,ok?"success":"error");return;}' . "\n"
        . 'window.alert(msg);' . "\n"
        . '}' . "\n"
        . 'var fields=document.querySelectorAll("[data-th5-ad-field]");' . "\n"
        . 'if(!fields.length){return;}' . "\n"
        . 'Array.prototype.forEach.call(fields,function(field){' . "\n"
        . 'var urlInput=field.querySelector("[data-th5-ad-url]");' . "\n"
        . 'var fileInput=field.querySelector("[data-th5-ad-file]");' . "\n"
        . 'var trigger=field.querySelector("[data-th5-ad-trigger]");' . "\n"
        . 'var preview=field.querySelector("[data-th5-ad-preview]");' . "\n"
        . 'var previewImg=field.querySelector("[data-th5-ad-preview-img]");' . "\n"
        . 'var busy=false;' . "\n"
        . 'function refreshPreview(){' . "\n"
        . 'var v=(urlInput.value||"").trim();' . "\n"
        . 'if(v){previewImg.src=v;preview.hidden=false;}else{preview.hidden=true;previewImg.removeAttribute("src");}' . "\n"
        . '}' . "\n"
        . 'if(urlInput){urlInput.addEventListener("input",refreshPreview);}' . "\n"
        . 'function setBusy(on){' . "\n"
        . 'if(!trigger){return;}' . "\n"
        . 'if(on){trigger.classList.add("is-busy");trigger.textContent="上传中…";}else{trigger.classList.remove("is-busy");trigger.textContent="上传图片";}' . "\n"
        . '}' . "\n"
        . 'function finish(){busy=false;setBusy(false);if(fileInput){fileInput.value="";}}' . "\n"
        . 'function doUpload(file,attempt){' . "\n"
        . 'if(attempt===0){busy=true;setBusy(true);}' . "\n"
        . 'var csrf=findCsrf();' . "\n"
        . 'var fd=new FormData();' . "\n"
        . 'fd.append("file",file);' . "\n"
        . 'if(csrf){fd.append("csrf_token",csrf);}' . "\n"
        . 'var headers={"Accept":"application/json"};' . "\n"
        . 'if(csrf){headers["X-CSRF-Token"]=csrf;}' . "\n"
        . 'fetch(uploadUrl,{method:"POST",credentials:"same-origin",headers:headers,body:fd})' . "\n"
        . '.then(function(res){return res.text();})' . "\n"
        . '.then(function(text){' . "\n"
        . 'var raw=(text==null)?"":String(text).trim();' . "\n"
        . 'var data=null;' . "\n"
        . 'try{data=JSON.parse(raw||"{}");}catch(e){data=null;}' . "\n"
        . 'if(data&&data.csrf){window.VS_CSRF_TOKEN=data.csrf;}' . "\n"
        . 'if(data&&Number(data.code)===1&&data.url){' . "\n"
        . 'if(urlInput){urlInput.value=data.url;}' . "\n"
        . 'refreshPreview();' . "\n"
        . 'toast(data.msg||"上传成功",true);' . "\n"
        . 'finish();' . "\n"
        . 'return;' . "\n"
        . '}' . "\n"
        . 'if(attempt<1&&isCredFail(data)){doUpload(file,attempt+1);return;}' . "\n"
        . 'toast((data&&data.msg)?data.msg:"上传失败，请重试",false);' . "\n"
        . 'finish();' . "\n"
        . '})' . "\n"
        . '.catch(function(){' . "\n"
        . 'if(attempt<1){doUpload(file,attempt+1);return;}' . "\n"
        . 'toast("上传失败，请检查网络后重试",false);' . "\n"
        . 'finish();' . "\n"
        . '});' . "\n"
        . '}' . "\n"
        . 'if(fileInput&&uploadUrl){' . "\n"
        . 'fileInput.addEventListener("change",function(){' . "\n"
        . 'var file=fileInput.files&&fileInput.files[0];' . "\n"
        . 'if(!file){return;}' . "\n"
        . 'if(busy){return;}' . "\n"
        . 'if(!/^image\\/(jpeg|png|gif|webp)$/i.test(file.type)){toast("仅支持 jpg / png / gif / webp 图片",false);fileInput.value="";return;}' . "\n"
        . 'if(file.size>2*1024*1024){toast("图片不能超过 2MB",false);fileInput.value="";return;}' . "\n"
        . 'doUpload(file,0);' . "\n"
        . '});' . "\n"
        . '}' . "\n"
        . '});' . "\n"
        . '})();' . "\n"
        . '</script>' . "\n";


    echo '<section class="th5-admin-settings__section">';
    echo '<h3 class="th5-admin-settings__title">页脚社交图标</h3>';
    echo '<p class="th5-admin-settings__hint">最多配置 3 个图标。微信填二维码图片地址；邮箱填地址；其它类型填跳转链接。QQ 类型可选链接跳转或二维码悬停。</p>';
    echo '<div class="th5-admin-settings__slots">';

    for ($slot = 1; $slot <= 3; $slot++) {
        $typeKey = 'footer_social_' . $slot . '_type';
        $modeKey = 'footer_social_' . $slot . '_qq_mode';
        $valueKey = 'footer_social_' . $slot . '_value';
        if (!isset($fields[$typeKey])) {
            continue;
        }

        $typeVal = (string) $val($typeKey);
        $modeVal = (string) $val($modeKey);
        $contentVal = $val($valueKey);

        echo '<div class="th5-admin-settings__slot">';
        echo '<div class="th5-admin-settings__slot-head">图标 ' . $slot . '</div>';
        echo '<div class="th5-admin-settings__slot-body">';

        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($typeKey) . '">类型</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($typeKey) . '" name="settings[' . vs_e($typeKey) . ']" data-vs-pick>';
        foreach ($socialTypes as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ($typeVal === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="vs-theme-config-field">';
        echo '<label class="vs-label" for="ts_' . vs_e($modeKey) . '">QQ 方式</label>';
        echo '<select class="vs-input vs-select" id="ts_' . vs_e($modeKey) . '" name="settings[' . vs_e($modeKey) . ']" data-vs-pick>';
        foreach ($qqModes as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $optVal = (string) $opt['value'];
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : $optVal;
            $selected = ($modeVal === $optVal) ? ' selected' : '';
            echo '<option value="' . vs_e($optVal) . '"' . $selected . '>' . vs_e($optLabel) . '</option>';
        }
        echo '</select>';
        echo '<p class="vs-form-hint">仅在选择 QQ 类型时生效</p>';
        echo '</div>';

        $renderText($valueKey, '内容');

        echo '</div></div>';
    }

    echo '</div></section>';
    echo '</div>';
}
