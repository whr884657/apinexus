/**
 * 文件：assets/js/user-checkin.js
 * 作用：用户中心每日签到（默认主题横幅）
 */
(function () {
    'use strict';

    var banner = document.getElementById('ucCheckinBanner');
    var btn = document.getElementById('ucCheckinBtn');
    if (!banner || !btn || !window.VS || typeof VS.postForm !== 'function') {
        return;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'checkin');
        VS.postForm(fd).then(function (res) {
            if (!res || Number(res.code) !== 1) {
                throw new Error((res && res.msg) || '签到失败');
            }
            VS.showMessage(res.msg || '签到成功', 'success');
            if (res.points != null || (res.stats && res.stats.points != null)) {
                var pts = res.points != null ? res.points : res.stats.points;
                document.querySelectorAll('#ucDashboard [data-field="points"], #ucDashboard [data-field="points_kpi"]').forEach(function (el) {
                    el.textContent = String(pts);
                });
            }
            if (banner && banner.parentNode) {
                banner.parentNode.removeChild(banner);
            }
        }).catch(function (err) {
            VS.showMessage(err.message || '签到失败', 'error');
            btn.disabled = false;
        });
    });
})();
