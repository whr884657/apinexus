'use strict';

(function () {
    var cards = document.querySelectorAll('.donate-qr-card, .donate-sponsor-card');
    if (!cards.length || !('IntersectionObserver' in window)) {
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    cards.forEach(function (el) {
        io.observe(el);
    });
})();
