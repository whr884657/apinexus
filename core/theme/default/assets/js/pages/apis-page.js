// API列表页 - 前端筛选/搜索/分页（不改变URL）
(function() {
    var container = document.getElementById('apiCardContainer');
    var pagination = document.getElementById('apiPagination');
    var searchInput = document.getElementById('apiSearchInput');
    var resetBtn = document.getElementById('apiResetBtn');
    var totalCountEl = document.getElementById('apiTotalCount');
    if (!container) return;

    var allCards = Array.from(container.querySelectorAll('.api-card'));
    var currentCategory = '';
    var currentPage = 1;
    var pageSize = 20;

    // 分类筛选
    window.selectCategory = function(el, catId) {
        currentCategory = catId;
        currentPage = 1;
        // 更新样式
        document.querySelectorAll('.category-tag').forEach(function(t) { t.classList.remove('active'); });
        el.classList.add('active');
        applyFilter();
    };

    // 搜索
    window.filterApis = function() {
        currentPage = 1;
        applyFilter();
    };

    // 重置
    window.resetApis = function() {
        searchInput.value = '';
        currentCategory = '';
        currentPage = 1;
        document.querySelectorAll('.category-tag').forEach(function(t) { t.classList.remove('active'); });
        var allTag = document.querySelector('.category-tag[data-category=""]');
        if (allTag) allTag.classList.add('active');
        applyFilter();
    };

    function applyFilter() {
        var keyword = (searchInput.value || '').toLowerCase().trim();

        // 筛选
        var filtered = allCards.filter(function(card) {
            // 分类
            if (currentCategory && card.getAttribute('data-category') !== currentCategory) return false;
            // 搜索
            if (keyword) {
                var name = card.getAttribute('data-name') || '';
                var desc = card.getAttribute('data-desc') || '';
                if (name.indexOf(keyword) === -1 && desc.indexOf(keyword) === -1) return false;
            }
            return true;
        });

        // 更新计数
        if (totalCountEl) totalCountEl.textContent = filtered.length;

        // 显示/隐藏重置按钮
        if (resetBtn) resetBtn.style.display = (keyword || currentCategory) ? '' : 'none';

        // 分页
        var totalPages = Math.ceil(filtered.length / pageSize);
        if (currentPage > totalPages) currentPage = totalPages || 1;
        var start = (currentPage - 1) * pageSize;
        var pageCards = filtered.slice(start, start + pageSize);

        // 渲染卡片
        allCards.forEach(function(card) { card.style.display = 'none'; });
        pageCards.forEach(function(card) { card.style.display = ''; });

        // 空状态
        var emptyEl = container.querySelector('.col-span-full');
        if (pageCards.length === 0) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'col-span-full text-center py-8';
                emptyEl.style.color = 'var(--text-muted)';
                emptyEl.textContent = '没有找到相关接口';
                container.appendChild(emptyEl);
            }
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }

        // 渲染分页
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
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

    // 初始化
    applyFilter();
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
        if (btnText) btnText.textContent = '更多分类';
        if (expandIcon) expandIcon.style.transform = 'rotate(0deg)';
    } else {
        if (btnText) btnText.textContent = '收起分类';
        if (expandIcon) expandIcon.style.transform = 'rotate(90deg)';
    }
    btn.setAttribute('data-expanded', isExpanded ? '0' : '1');
}
