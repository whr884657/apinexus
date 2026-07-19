/**
 * 友情链接页轻交互（头像点击轻微摆动）
 */
(function () {
    'use strict';

    var avatars = document.querySelectorAll('.links-page .link-avatar');
    if (!avatars.length) {
        return;
    }

    if (!document.getElementById('linkAvatarSwingStyle')) {
        var style = document.createElement('style');
        style.id = 'linkAvatarSwingStyle';
        style.textContent = '@keyframes linkSwing{0%{transform:rotate(0)}20%{transform:rotate(-12deg)}40%{transform:rotate(10deg)}60%{transform:rotate(-6deg)}80%{transform:rotate(3deg)}100%{transform:rotate(0)}}.link-avatar-swing{animation:linkSwing .9s ease-in-out;transform-origin:center}';
        document.head.appendChild(style);
    }

    avatars.forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            el.classList.remove('link-avatar-swing');
            void el.offsetWidth;
            el.classList.add('link-avatar-swing');
        });
    });
})();
