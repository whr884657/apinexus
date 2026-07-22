/**
 * 文件：assets/js/settings.js
 * 作用：系统设置页 AJAX 保存与折叠板块
 * @version 1.4.0
 */

(function () {
    'use strict';

    var flashEl = document.getElementById('settingsFlash');

    function showFlash(text, type) {
        if (window.VsToast) {
            VsToast.show(text, type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
            return;
        }
        if (!flashEl) return;
        flashEl.textContent = text;
        flashEl.className = 'vs-settings-flash vs-alert vs-alert--' + type;
        flashEl.hidden = false;
        flashEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function postForm(form) {
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        return window.VS.postForm(form)
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    function bindAjaxForm(form) {
        if (!form || form.getAttribute('data-ajax') !== '1') return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            postForm(form)
                .then(function (data) {
                    if (data.code === 1) {
                        showFlash(data.msg || '操作成功', 'success');
                    } else {
                        showFlash(data.msg || '操作失败', 'error');
                    }
                })
                .catch(function () {
                    showFlash('网络异常，请稍后重试', 'error');
                });
        });
    }

    function bindAccordions() {
        document.querySelectorAll('[data-accordion]').forEach(function (section) {
            var trigger = section.querySelector('.vs-accordion__trigger');
            if (!trigger) return;

            trigger.addEventListener('click', function () {
                var isOpen = section.classList.contains('is-open');
                section.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
            });
        });
    }

    function bindApilogCron() {
        var genBtn = document.getElementById('apilogGenCronKeyBtn');
        var copyBtn = document.getElementById('apilogCopyCronUrlBtn');
        var keyInput = document.getElementById('apilogCronKey');
        var urlInput = document.getElementById('apilogCronUrl');
        var archiveChk = document.getElementById('apilog_archive_enabled');
        var hotRow = document.getElementById('apilogHotDaysRow');
        var cronBox = document.getElementById('apilogCronBox');

        function syncArchiveUi() {
            var on = !!(archiveChk && archiveChk.checked);
            if (hotRow) hotRow.hidden = !on;
            if (cronBox) cronBox.hidden = !on;
        }
        if (archiveChk) {
            archiveChk.addEventListener('change', syncArchiveUi);
            syncArchiveUi();
        }

        if (genBtn) {
            genBtn.addEventListener('click', function () {
                if (!window.confirm('生成新密钥后，旧的计划任务链接将立即失效，是否继续？')) {
                    return;
                }
                genBtn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'generate_apilog_cron_key');
                window.VS.postForm(fd, window.location.href)
                    .then(function (data) {
                        if (data.code === 1) {
                            if (keyInput) keyInput.value = data.cron_key || '';
                            if (urlInput) urlInput.value = data.cron_url || '';
                            showFlash(data.msg || '密钥已生成', 'success');
                        } else {
                            showFlash((data && data.msg) || '生成失败', 'error');
                        }
                    })
                    .catch(function () {
                        showFlash('网络异常，请稍后重试', 'error');
                    })
                    .finally(function () {
                        genBtn.disabled = false;
                    });
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = urlInput ? String(urlInput.value || '').trim() : '';
                if (!url || url.indexOf('key=') < 0) {
                    showFlash('请先生成密钥', 'error');
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        showFlash('任务链接已复制', 'success');
                    }).catch(function () {
                        showFlash('复制失败，请手动选中复制', 'error');
                    });
                } else if (urlInput) {
                    urlInput.select();
                    try {
                        document.execCommand('copy');
                        showFlash('任务链接已复制', 'success');
                    } catch (e) {
                        showFlash('复制失败，请手动选中复制', 'error');
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAccordions();

        ['siteForm', 'registerForm', 'oauthForm', 'siteExtraForm', 'mailForm', 'testMailForm', 'apilogForm'].forEach(function (id) {
            bindAjaxForm(document.getElementById(id));
        });
        bindApilogCron();
    });
})();
