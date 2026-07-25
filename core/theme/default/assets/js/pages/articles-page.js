/**
 * 默认主题 · 文章页评论（文字 + 基础表情 + 引用回复）
 */
(function () {
    'use strict';

    var cfg = window.VS_ARTICLE_COMMENT;
    if (!cfg || !document.getElementById('articleCmtForm')) {
        return;
    }

    var EMOJIS = [
        '😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😜',
        '🤔', '😮', '😢', '😭', '😡', '😎', '🤗', '😴',
        '👍', '👎', '👏', '🙏', '🎉', '🔥', '❤️', '✨',
        '🌟', '💯', '✅', '❌', '👀', '💪', '🤝', '😄',
        '😅', '🙄', '😏', '🥺', '👻', '🐱', '🐶', '🌸',
        '🍀', '☕', '🍕', '🌈', '⭐', '💫', '🎯', '📌'
    ];

    var form = document.getElementById('articleCmtForm');
    var bodyEl = document.getElementById('articleCmtBody');
    var lenEl = document.getElementById('articleCmtLen');
    var parentEl = document.getElementById('articleCmtParentId');
    var quoteBox = document.getElementById('articleCmtQuote');
    var quoteName = document.getElementById('articleCmtQuoteName');
    var quoteText = document.getElementById('articleCmtQuoteText');
    var quoteClear = document.getElementById('articleCmtQuoteClear');
    var listEl = document.getElementById('articleCmtList');
    var countEl = document.getElementById('articleCmtCount');
    var submitBtn = document.getElementById('articleCmtSubmit');
    var emojiBtn = document.getElementById('articleCmtEmojiBtn');
    var emojiPanel = document.getElementById('articleCmtEmojiPanel');

    function toast(msg, type) {
        if (window.VS && typeof window.VS.showMessage === 'function') {
            window.VS.showMessage(msg, type || 'info');
            return;
        }
        window.alert(msg);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function nl2br(s) {
        return esc(s).replace(/\n/g, '<br>');
    }

    function updateLen() {
        if (lenEl && bodyEl) {
            lenEl.textContent = String(bodyEl.value.length);
        }
    }

    function setQuote(id, name, excerpt) {
        if (!parentEl || !quoteBox) {
            return;
        }
        parentEl.value = String(id || 0);
        if (!id) {
            quoteBox.hidden = true;
            quoteName.textContent = '';
            quoteText.textContent = '';
            return;
        }
        quoteName.textContent = name || '';
        quoteText.textContent = excerpt || '';
        quoteBox.hidden = false;
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (bodyEl) {
            bodyEl.focus();
        }
    }

    function renderItem(c) {
        var website = c.website || '';
        var name = c.nickname || '用户';
        var avatar = c.avatar_url || '';
        var pin = c.ispinned ? ' is-pinned' : '';
        var nameHtml = website
            ? '<a class="article-cmt-name" href="' + esc(website) + '" target="_blank" rel="noopener noreferrer">' + esc(name) + '</a>'
            : '<span class="article-cmt-name">' + esc(name) + '</span>';
        var avatarInner = avatar
            ? '<img class="article-cmt-avatar" src="' + esc(avatar) + '" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">'
            : '<span class="article-cmt-avatar article-cmt-avatar--letter">' + esc(name.charAt(0)) + '</span>';
        var avatarWrap = website
            ? '<a class="article-cmt-avatar-wrap" href="' + esc(website) + '" target="_blank" rel="noopener noreferrer">' + avatarInner + '</a>'
            : '<div class="article-cmt-avatar-wrap">' + avatarInner + '</div>';
        var refHtml = '';
        if (c.parent && c.parent.id) {
            refHtml = '<button type="button" class="article-cmt-ref" data-jump="' + esc(c.parent.id) + '">'
                + '<span class="article-cmt-ref__name">' + esc(c.parent.nickname || '') + '</span>'
                + '<span class="article-cmt-ref__text">' + esc(c.parent.excerpt || '') + '</span></button>';
        }
        var replyHtml = c.reply
            ? '<div class="article-cmt-admin-reply"><span class="article-cmt-admin-reply__label">管理员回复</span>' + nl2br(c.reply) + '</div>'
            : '';
        var excerpt = (c.body || '').replace(/\s+/g, ' ').slice(0, 80);
        return '<div class="article-cmt-item' + pin + '" data-cmt-id="' + esc(c.id) + '" id="cmt-' + esc(c.id) + '">'
            + avatarWrap
            + '<div class="article-cmt-main">'
            + '<div class="article-cmt-meta">' + nameHtml
            + (c.ispinned ? '<span class="article-cmt-pin">置顶</span>' : '')
            + '<span class="article-cmt-time">' + esc(c.createtime_short || '') + '</span></div>'
            + refHtml
            + '<div class="article-cmt-body">' + nl2br(c.body || '') + '</div>'
            + replyHtml
            + '<div class="article-cmt-actions"><button type="button" class="article-cmt-reply-btn"'
            + ' data-reply-id="' + esc(c.id) + '"'
            + ' data-reply-name="' + esc(name) + '"'
            + ' data-reply-excerpt="' + esc(excerpt) + '">引用回复</button></div>'
            + '</div></div>';
    }

    function appendComment(c) {
        if (!listEl || !c) {
            return;
        }
        var empty = document.getElementById('articleCmtEmpty');
        if (empty) {
            empty.remove();
        }
        listEl.insertAdjacentHTML('beforeend', renderItem(c));
        cfg.count = (cfg.count || 0) + 1;
        if (countEl) {
            countEl.textContent = String(cfg.count);
        }
        var el = document.getElementById('cmt-' + c.id);
        if (el) {
            el.classList.add('is-flash');
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () { el.classList.remove('is-flash'); }, 1600);
        }
    }

    function buildEmojiPanel() {
        if (!emojiPanel) {
            return;
        }
        var html = '';
        EMOJIS.forEach(function (e) {
            html += '<button type="button" role="option" data-emoji="' + e + '">' + e + '</button>';
        });
        emojiPanel.innerHTML = html;
    }

    function insertEmoji(emoji) {
        if (!bodyEl) {
            return;
        }
        var start = bodyEl.selectionStart || 0;
        var end = bodyEl.selectionEnd || 0;
        var val = bodyEl.value || '';
        bodyEl.value = val.slice(0, start) + emoji + val.slice(end);
        var pos = start + emoji.length;
        bodyEl.setSelectionRange(pos, pos);
        bodyEl.focus();
        updateLen();
    }

    if (bodyEl) {
        bodyEl.addEventListener('input', updateLen);
        updateLen();
    }

    if (quoteClear) {
        quoteClear.addEventListener('click', function () {
            setQuote(0, '', '');
        });
    }

    buildEmojiPanel();
    if (emojiBtn && emojiPanel) {
        emojiBtn.addEventListener('click', function () {
            var open = emojiPanel.hidden;
            emojiPanel.hidden = !open;
            emojiBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        emojiPanel.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-emoji]');
            if (!btn) {
                return;
            }
            insertEmoji(btn.getAttribute('data-emoji') || '');
            emojiPanel.hidden = true;
            emojiBtn.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('click', function (e) {
            if (!emojiPanel.hidden && !e.target.closest('.article-cmt-emoji-wrap')) {
                emojiPanel.hidden = true;
                emojiBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function (e) {
        var replyBtn = e.target.closest('.article-cmt-reply-btn');
        if (replyBtn) {
            setQuote(
                replyBtn.getAttribute('data-reply-id'),
                replyBtn.getAttribute('data-reply-name'),
                replyBtn.getAttribute('data-reply-excerpt')
            );
            return;
        }
        var jumpBtn = e.target.closest('.article-cmt-ref[data-jump]');
        if (jumpBtn) {
            var target = document.getElementById('cmt-' + jumpBtn.getAttribute('data-jump'));
            if (target) {
                target.classList.add('is-flash');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () { target.classList.remove('is-flash'); }, 1600);
            }
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!window.VS || typeof window.VS.postForm !== 'function') {
            toast('页面脚本未就绪，请刷新后重试', 'error');
            return;
        }
        var email = (document.getElementById('articleCmtEmail') || {}).value || '';
        var body = (bodyEl && bodyEl.value) || '';
        if (!email.trim()) {
            toast('请填写邮箱', 'warning');
            return;
        }
        if (!body.trim()) {
            toast('请填写评论内容', 'warning');
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        window.VS.postForm(form, cfg.postUrl || form.action).then(function (res) {
            if (!res || !res.code) {
                toast((res && res.msg) || '发送失败', 'error');
                return;
            }
            toast(res.msg || '评论已发布', 'success');
            if (bodyEl) {
                bodyEl.value = '';
            }
            updateLen();
            setQuote(0, '', '');
            if (res.comment) {
                appendComment(res.comment);
            } else if (res.data && res.data.comment) {
                appendComment(res.data.comment);
            }
            if (res.csrf) {
                window.VS_CSRF_TOKEN = res.csrf;
                var csrfInput = form.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfInput.value = res.csrf;
                }
            }
        }).catch(function () {
            toast('网络异常，请稍后重试', 'error');
        }).then(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        });
    });
})();
