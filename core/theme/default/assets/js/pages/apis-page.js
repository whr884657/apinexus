// API列表页 - 前端筛选/搜索/分页（目录一次拉取后本地过滤，不改变 URL）
(function() {
    var container = document.getElementById('apiCardContainer');
    var pagination = document.getElementById('apiPagination');
    var searchInput = document.getElementById('apiSearchInput');
    var resetBtn = document.getElementById('apiResetBtn');
    var totalCountEl = document.getElementById('apiTotalCount');
    if (!container) return;

    var apiData = [];
    var currentCategory = 'all';
    var currentPage = 1;
    var pageSize = 20;
    var catalogReady = false;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function splitMethods(methods) {
        var clean = [];
        (Array.isArray(methods) ? methods : []).forEach(function (m) {
            m = String(m || '').toUpperCase().trim();
            if (m) clean.push(m);
        });
        if (!clean.length) clean = ['GET'];
        var extra = clean.length > 2 ? clean.length - 2 : 0;
        return { show: clean.slice(0, 2), extra: extra };
    }

    function billingLabel(api) {
        if (api.billing_label) return String(api.billing_label);
        var points = Number(api.points || 0);
        if (points > 0) {
            var s = String(points);
            if (s.indexOf('.') >= 0) {
                s = s.replace(/0+$/, '').replace(/\.$/, '');
            }
            return s + '积分/次';
        }
        return '免费';
    }

    function buildCardHtml(api) {
        var name = String(api.name || '').trim();
        if (!name) return '';
        var desc = String(api.desc || '').trim();
        var cat = String(api.category || '');
        var methods = splitMethods(api.methods);
        var endpoint = String(api.endpoint || '').trim();
        var nameKey = name.toLowerCase();
        var descKey = desc.toLowerCase();
        var maintenance = !!(api.maintenance == 1 || api.maintenance === '1' || api.maintenance === true);
        var apiId = Number(api.id || 0);
        var detailUrl = String(api.detail_url || '').trim();
        if (!detailUrl) {
            detailUrl = apiId > 0
                ? ((window.VS_BASE_URL || '') + '/detail/' + apiId)
                : ((window.VS_BASE_URL || '') + '/apis');
        }
        // 仅允许站内路径或 http(s)，拦截 javascript: 等
        if (!/^https?:\/\//i.test(detailUrl) && !(detailUrl.charAt(0) === '/' && detailUrl.charAt(1) !== '/')) {
            detailUrl = (window.VS_BASE_URL || '') + '/apis';
        }
        var points = Number(api.points || 0);
        var needkey = Number(api.needkey || 0);
        var chips = '';
        if (!maintenance) {
            chips = '<div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end;">'
                + (points > 0
                    ? '<span class="api-chip api-chip--points">' + escapeHtml(billingLabel(api)) + '</span>'
                    : '<span class="api-chip api-chip--free">免费</span>')
                + (needkey === 1 ? '<span class="api-chip api-chip--key">KEY必填</span>' : '')
                + (needkey === 2 ? '<span class="api-chip api-chip--key">KEY可选</span>' : '')
                + '</div>';
        }
        var methodHtml = methods.show.map(function (m) {
            return '<span class="method-badge ' + escapeHtml(m.toLowerCase()) + '">' + escapeHtml(m) + '</span>';
        }).join('');
        if (methods.extra > 0) {
            methodHtml += '<span class="api-item-more">+' + methods.extra + '</span>';
        }
        if (maintenance) {
            methodHtml += '<span class="api-chip api-chip--maintenance" style="margin-left: auto;">维护中</span>';
        }
        return '<div class="api-card" data-category="' + escapeHtml(cat) + '" data-name="' + escapeHtml(nameKey) + '" data-desc="' + escapeHtml(descKey) + '" style="position: relative;">'
            + chips
            + '<div class="flex justify-start items-start mb-2 flex-wrap gap-1">' + methodHtml + '</div>'
            + '<h3 class="font-bold text-sm mb-1">' + escapeHtml(name) + '</h3>'
            + (desc ? '<p class="text-xs mb-2" style="color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">' + escapeHtml(desc) + '</p>' : '')
            + (endpoint ? '<div class="endpoint-box font-mono">' + escapeHtml(endpoint) + '</div>' : '')
            + '<a href="' + escapeHtml(detailUrl) + '" class="btn-geek w-full mt-2 text-center text-xs block">查看详情</a>'
            + '</div>';
    }

    window.selectCategory = function(el, catId) {
        currentCategory = catId;
        currentPage = 1;
        document.querySelectorAll('.category-tag').forEach(function(t) { t.classList.remove('active'); });
        el.classList.add('active');
        applyFilter();
    };

    window.filterApis = function() {
        currentPage = 1;
        applyFilter();
    };

    window.resetApis = function() {
        if (searchInput) searchInput.value = '';
        currentCategory = 'all';
        currentPage = 1;
        document.querySelectorAll('.category-tag').forEach(function(t) { t.classList.remove('active'); });
        var allTag = document.querySelector('.category-tag[data-category="all"]');
        if (allTag) allTag.classList.add('active');
        applyFilter();
    };

    function applyFilter() {
        if (!catalogReady) return;
        var keyword = ((searchInput && searchInput.value) || '').toLowerCase().trim();
        var filtered = apiData.filter(function(api) {
            if (currentCategory !== 'all' && String(api.category) !== String(currentCategory)) return false;
            if (keyword) {
                var name = String(api.name || '').toLowerCase();
                var desc = String(api.desc || '').toLowerCase();
                if (name.indexOf(keyword) === -1 && desc.indexOf(keyword) === -1) return false;
            }
            return true;
        });

        if (totalCountEl) totalCountEl.textContent = String(filtered.length);
        if (resetBtn) resetBtn.style.display = (keyword || currentCategory !== 'all') ? '' : 'none';

        var totalPages = Math.ceil(filtered.length / pageSize) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        var start = (currentPage - 1) * pageSize;
        var pageItems = filtered.slice(start, start + pageSize);

        if (pageItems.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--text-muted);">没有找到相关接口</div>';
        } else {
            container.innerHTML = pageItems.map(buildCardHtml).join('');
        }
        container.setAttribute('aria-busy', 'false');
        renderPagination(Math.ceil(filtered.length / pageSize));
    }

    function renderPagination(totalPages) {
        if (!pagination) return;
        if (totalPages <= 1) {
            pagination.style.display = 'none';
            return;
        }
        pagination.style.display = '';
        var html = '';
        if (currentPage > 1) {
            html += '<a href="javascript:void(0)" onclick="goPage(1)">首页</a>';
            html += '<a href="javascript:void(0)" onclick="goPage(' + (currentPage - 1) + ')">上一页</a>';
        }
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);
        for (var i = start; i <= end; i++) {
            if (i === currentPage) {
                html += '<span class="active">' + i + '</span>';
            } else {
                html += '<a href="javascript:void(0)" onclick="goPage(' + i + ')">' + i + '</a>';
            }
        }
        if (currentPage < totalPages) {
            html += '<a href="javascript:void(0)" onclick="goPage(' + (currentPage + 1) + ')">下一页</a>';
        }
        pagination.innerHTML = html;
    }

    window.goPage = function(p) {
        currentPage = p;
        applyFilter();
        window.scrollTo({ top: container.offsetTop - 80, behavior: 'smooth' });
    };

    function bootCatalog() {
        if (!window.VS || typeof VS.fetchFrontCatalog !== 'function') {
            container.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--text-muted);">目录加载失败，请刷新重试</div>';
            return;
        }
        if (window.VS.setLoading) {
            VS.setLoading(container, '正在加载接口');
        }
        VS.fetchFrontCatalog({ shuffle: true }).then(function (data) {
            apiData = Array.isArray(data.apiData) ? data.apiData : [];
            catalogReady = true;
            if (totalCountEl && typeof data.apiCount !== 'undefined') {
                totalCountEl.textContent = String(data.apiCount);
            }
            applyFilter();
        }).catch(function () {
            container.innerHTML = '<div class="col-span-full text-center py-8" style="color: var(--text-muted);">目录加载失败，请刷新重试</div>';
        });
    }

    bootCatalog();
})();

function toggleMoreCategories() {
    var hiddenCats = document.querySelectorAll('.category-hidden');
    var btn = document.getElementById('catMoreBtn');
    if (!btn) return;
    var expandIcon = btn.querySelector('.expand-icon');
    var btnText = btn.querySelector('span');
    var isExpanded = btn.getAttribute('data-expanded') === '1';
    hiddenCats.forEach(function(cat) { cat.classList.toggle('show', !isExpanded); });
    if (isExpanded) {
        if (btnText) btnText.textContent = '更多';
        if (expandIcon) expandIcon.style.transform = 'rotate(0deg)';
    } else {
        if (btnText) btnText.textContent = '收起';
        if (expandIcon) expandIcon.style.transform = 'rotate(90deg)';
    }
    btn.setAttribute('data-expanded', isExpanded ? '0' : '1');
}
