/**
 * 主题 three · 文章评论（提交 / 字数 / 表情 / 引用回复）
 */
(function () {
    'use strict';

    var cfg = window.VS_ARTICLE_COMMENT;
    if (!cfg || !document.getElementById('articleCmtForm')) {
        return;
    }

    var EMOJIS = ['😀', '😂', '😊', '😍', '🤔', '👍', '👏', '🔥', '❤️', '✨', '🎉', '👀', '💪', '🙏', '😅', '😎'];

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
            if (quoteName) quoteName.textContent = '';
            if (quoteText) quoteText.textContent = '';
            return;
        }
        if (quoteName) quoteName.textContent = name || '';
        if (quoteText) quoteText.textContent = excerpt || '';
        quoteBox.hidden = false;
        if (form) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (bodyEl) bodyEl.focus();
    }

    function renderItem(c) {
        var website = c.website || '';
        var name = c.nickname || '用户';
        var avatar = c.avatar_url || '';
        var pin = c.ispinned ? ' is-pinned' : '';
        var nameHtml = website
            ? '<a class="th3-cmt-name" href="' + esc(website) + '" target="_blank" rel="noopener noreferrer">' + esc(name) + '</a>'
            : '<span class="th3-cmt-name">' + esc(name) + '</span>';
        var avatarInner = avatar
            ? '<img class="th3-cmt-avatar" src="' + esc(avatar) + '" alt="' + esc(name.charAt(0) || '?') + '" width="40" height="40" loading="lazy" referrerpolicy="no-referrer" data-ext-icon="1">'
            : '<span class="th3-cmt-avatar th3-cmt-avatar--letter">' + esc(name.charAt(0)) + '</span>';
        var avatarWrap = website
            ? '<a class="th3-cmt-avatar-wrap" href="' + esc(website) + '" target="_blank" rel="noopener noreferrer">' + avatarInner + '</a>'
            : '<div class="th3-cmt-avatar-wrap">' + avatarInner + '</div>';
        var refHtml = '';
        if (c.parent && c.parent.id) {
            refHtml = '<button type="button" class="th3-cmt-ref" data-jump="' + esc(c.parent.id) + '">'
                + '<span class="th3-cmt-ref__name">' + esc(c.parent.nickname || '') + '</span>'
                + '<span class="th3-cmt-ref__text">' + esc(c.parent.excerpt || '') + '</span></button>';
        }
        var replyHtml = c.reply
            ? '<div class="th3-cmt-admin-reply"><span class="th3-cmt-admin-reply__label">管理员回复</span>' + nl2br(c.reply) + '</div>'
            : '';
        var excerpt = (c.body || '').replace(/\s+/g, ' ').slice(0, 80);
        return '<div class="th3-cmt-item' + pin + '" data-cmt-id="' + esc(c.id) + '" id="cmt-' + esc(c.id) + '">'
            + avatarWrap
            + '<div class="th3-cmt-main">'
            + '<div class="th3-cmt-meta">' + nameHtml
            + (c.ispinned ? '<span class="tag hot">置顶</span>' : '')
            + '<span class="th3-cmt-time text-xs text-muted">' + esc(c.createtime_short || '') + '</span></div>'
            + refHtml
            + '<div class="th3-cmt-body">' + nl2br(c.body || '') + '</div>'
            + replyHtml
            + '<div class="th3-cmt-actions"><button type="button" class="btn-ghost text-sm article-cmt-reply-btn"'
            + ' data-reply-id="' + esc(c.id) + '"'
            + ' data-reply-name="' + esc(name) + '"'
            + ' data-reply-excerpt="' + esc(excerpt) + '">引用回复</button></div>'
            + '</div></div>';
    }

    function appendComment(c) {
        if (!listEl || !c) return;
        var empty = document.getElementById('articleCmtEmpty');
        if (empty) empty.remove();
        listEl.insertAdjacentHTML('beforeend', renderItem(c));
        cfg.count = (cfg.count || 0) + 1;
        if (countEl) countEl.textContent = String(cfg.count);
        var el = document.getElementById('cmt-' + c.id);
        if (el) {
            if (window.VS && typeof window.VS.bindExternalImgFallback === 'function') {
                window.VS.bindExternalImgFallback(el);
            }
            el.classList.add('is-flash');
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () { el.classList.remove('is-flash'); }, 1400);
        }
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

    if (emojiPanel) {
        emojiPanel.innerHTML = EMOJIS.map(function (e) {
            return '<button type="button" role="option" data-emoji="' + e + '">' + e + '</button>';
        }).join('');
    }

    if (emojiBtn && emojiPanel) {
        emojiBtn.addEventListener('click', function () {
            var open = emojiPanel.hidden;
            emojiPanel.hidden = !open;
            emojiBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        emojiPanel.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-emoji]');
            if (!btn || !bodyEl) return;
            var emoji = btn.getAttribute('data-emoji') || '';
            var start = bodyEl.selectionStart || 0;
            var end = bodyEl.selectionEnd || 0;
            var val = bodyEl.value || '';
            bodyEl.value = val.slice(0, start) + emoji + val.slice(end);
            var pos = start + emoji.length;
            bodyEl.setSelectionRange(pos, pos);
            bodyEl.focus();
            updateLen();
            emojiPanel.hidden = true;
            emojiBtn.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('click', function (e) {
            if (!emojiPanel.hidden && !e.target.closest('.th3-cmt-emoji-wrap')) {
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
        var jumpBtn = e.target.closest('.th3-cmt-ref[data-jump]');
        if (jumpBtn) {
            var target = document.getElementById('cmt-' + jumpBtn.getAttribute('data-jump'));
            if (target) {
                target.classList.add('is-flash');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () { target.classList.remove('is-flash'); }, 1400);
            }
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var email = (document.getElementById('articleCmtEmail') || {}).value || '';
        var body = (bodyEl && bodyEl.value) || '';
        if (!String(email).trim()) {
            toast('请填写邮箱', 'warning');
            return;
        }
        if (!String(body).trim()) {
            toast('请填写评论内容', 'warning');
            return;
        }
        if (submitBtn) submitBtn.disabled = true;

        var postUrl = cfg.postUrl || form.action;
        var done = function (res) {
            if (!res || !res.code) {
                toast((res && res.msg) || '发送失败', 'error');
                return;
            }
            toast(res.msg || '评论已发布', 'success');
            if (bodyEl) bodyEl.value = '';
            updateLen();
            setQuote(0, '', '');
            var comment = res.comment || (res.data && res.data.comment);
            if (comment) appendComment(comment);
            if (res.csrf) {
                window.VS_CSRF_TOKEN = res.csrf;
                var csrfInput = form.querySelector('input[name="csrf_token"]');
                if (csrfInput) csrfInput.value = res.csrf;
            }
        };
        var fail = function () {
            toast('网络异常，请稍后重试', 'error');
        };
        var always = function () {
            if (submitBtn) submitBtn.disabled = false;
        };

        if (window.VS && typeof window.VS.postForm === 'function') {
            window.VS.postForm(form, postUrl).then(done).catch(fail).then(always);
            return;
        }

        var fd = new FormData(form);
        if (window.VS_CSRF_TOKEN && !fd.has('csrf_token')) {
            fd.append('csrf_token', window.VS_CSRF_TOKEN);
        }
        fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(done)
            .catch(fail)
            .then(always);
    });
})();
