/**
 * 文件：assets/js/theme-settings.js
 * 作用：后台主题设置 AJAX 保存；「当前使用」仅反映已保存主题
 */
(function () {
    'use strict';

    var form = document.getElementById('themeSettingsForm');
    if (!form || form.getAttribute('data-ajax') !== '1') {
        return;
    }

    function refreshActiveBadges() {
        var activeId = form.getAttribute('data-active-theme') || '';

        form.querySelectorAll('.vs-theme-card').forEach(function (card) {
            var radio = card.querySelector('input[name="frontend_theme"]');
            var isSavedActive = radio && radio.value === activeId;
            card.classList.toggle('is-active', isSavedActive);

            var preview = card.querySelector('.vs-theme-card__preview');
            if (!preview) {
                return;
            }
            var status = preview.querySelector('.vs-theme-card__status');
            if (isSavedActive && !status) {
                status = document.createElement('span');
                status.className = 'vs-theme-card__status';
                status.textContent = '当前使用';
                preview.appendChild(status);
            } else if (!isSavedActive && status) {
                status.remove();
            }
        });
    }

    function syncSelectionHighlight() {
        form.querySelectorAll('.vs-theme-card').forEach(function (card) {
            var radio = card.querySelector('input[name="frontend_theme"]');
            card.classList.toggle('is-selected', !!(radio && radio.checked));
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        window.VS.postForm(form)
            .then(function (data) {
                if (data.code !== 1) {
                    window.VS.showMessage(data.msg || '保存失败', 'error');
                    return;
                }
                window.VS.showMessage(data.msg || '已保存', 'success');
                if (data.data && data.data.theme_id) {
                    form.setAttribute('data-active-theme', data.data.theme_id);
                }
                refreshActiveBadges();
            })
            .catch(function () {
                window.VS.showMessage('网络异常，请稍后重试', 'error');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    });

    form.querySelectorAll('input[name="frontend_theme"]').forEach(function (radio) {
        radio.addEventListener('change', syncSelectionHighlight);
    });

    refreshActiveBadges();
    syncSelectionHighlight();
})();
