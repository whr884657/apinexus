/**
 * 文件：assets/js/theme-settings.js
 * 作用：后台主题设置 AJAX 保存
 */
(function () {
    'use strict';

    var form = document.getElementById('themeSettingsForm');
    if (!form || form.getAttribute('data-ajax') !== '1') {
        return;
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
                form.querySelectorAll('.vs-theme-picker-card').forEach(function (card) {
                    card.classList.remove('is-active');
                    var badge = card.querySelector('.vs-theme-picker-card__badge');
                    if (badge) {
                        badge.remove();
                    }
                });
                var checked = form.querySelector('input[name="frontend_theme"]:checked');
                if (checked) {
                    var activeCard = checked.closest('.vs-theme-picker-card');
                    if (activeCard) {
                        activeCard.classList.add('is-active');
                        var span = document.createElement('span');
                        span.className = 'vs-theme-picker-card__badge';
                        span.textContent = '当前使用';
                        activeCard.appendChild(span);
                    }
                }
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
        radio.addEventListener('change', function () {
            form.querySelectorAll('.vs-theme-picker-card').forEach(function (card) {
                card.classList.toggle('is-active', card.contains(radio) && radio.checked);
            });
        });
    });
})();
