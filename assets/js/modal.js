/**
 * 文件：assets/js/modal.js
 * 作用：ApiNexus 统一弹窗（替代 alert / confirm）
 * @version 1.0.0
 */

(function () {
    'use strict';

    var root, overlay, titleEl, bodyEl, footEl;
    var resolveFn = null;
    var allowOverlayClose = true;
    var allowEscapeClose = true;

    function init() {
        root = document.getElementById('vsModalRoot');
        if (!root) {
            return;
        }
        overlay = document.getElementById('vsModalOverlay');
        titleEl = document.getElementById('vsModalTitle');
        bodyEl = document.getElementById('vsModalBody');
        footEl = document.getElementById('vsModalFoot');

        if (overlay) {
            overlay.addEventListener('click', function () {
                if (allowOverlayClose) {
                    close(false);
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root && !root.hidden && allowEscapeClose) {
                close(false);
            }
        });
    }

    function close(result) {
        if (!root) {
            return;
        }
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('vs-modal-open');
        allowOverlayClose = true;
        allowEscapeClose = true;
        if (resolveFn) {
            var fn = resolveFn;
            resolveFn = null;
            fn(result);
        }
    }

    function setBodyContent(message, html) {
        if (!bodyEl) {
            return;
        }
        if (html) {
            bodyEl.innerHTML = html;
        } else {
            bodyEl.textContent = message || '';
        }
    }

    function openDialog(title, message, buttons, options) {
        options = options || {};
        if (!root || !titleEl || !bodyEl || !footEl) {
            return;
        }

        allowOverlayClose = options.closeOnOverlay !== false;
        allowEscapeClose = options.closeOnEscape !== false;

        titleEl.textContent = title || '提示';
        setBodyContent(message, options.html || '');
        footEl.innerHTML = '';

        (buttons || []).forEach(function (btn) {
            var el = document.createElement('button');
            el.type = 'button';
            el.textContent = btn.text;
            el.className = 'vs-btn';

            if (btn.primary) {
                el.className += ' vs-btn--primary';
            } else {
                el.className += ' vs-btn--default';
            }
            if (btn.danger) {
                el.className += ' vs-btn--danger';
            }

            el.addEventListener('click', function () {
                if (btn.action) {
                    btn.action();
                }
            });
            footEl.appendChild(el);
        });

        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('vs-modal-open');
    }

    window.VsModal = {
        close: close,

        open: function (options) {
            options = options || {};
            openDialog(
                options.title || '提示',
                options.message || '',
                options.buttons || [],
                options
            );
        },

        alert: function (message, title) {
            title = title || '提示';
            return new Promise(function (resolve) {
                resolveFn = function () {
                    resolve();
                };
                openDialog(title, message, [{
                    text: '知道了',
                    primary: true,
                    action: function () {
                        close(true);
                    },
                }]);
            });
        },

        confirm: function (message, title, options) {
            options = options || {};
            title = title || '操作确认';
            return new Promise(function (resolve) {
                resolveFn = resolve;
                openDialog(title, message, [
                    {
                        text: options.cancelText || '取消',
                        action: function () {
                            close(false);
                        },
                    },
                    {
                        text: options.confirmText || '确认',
                        primary: true,
                        danger: options.danger,
                        action: function () {
                            close(true);
                        },
                    },
                ], options);
            });
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
