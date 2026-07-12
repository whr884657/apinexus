// index.js - extracted from index.php

// PHP 数据注入

// 友链展开/收起
let linksExpanded = false;
function toggleMoreLinks() {
    const hiddenLinks = document.querySelectorAll('.link-hidden');
    const btn = document.getElementById('toggleLinksBtn');

    if (linksExpanded) {
        hiddenLinks.forEach(link => link.classList.add('hidden'));
    } else {
        hiddenLinks.forEach(link => link.classList.remove('hidden'));
        btn.textContent = '收起';
    }
    linksExpanded = !linksExpanded;
}

// ===== COUNTER ANIMATION =====
const statsSection = document.getElementById('stats-section');
const counters = document.querySelectorAll('.counter');
let statsAnimated = false;

/**
 * 数字递增动画函数 - 模式一（完整显示）
 * 功能说明：数字从0开始逐步递增，可以看到每个数字的变化
 *         使用分段动画，让大数字的递增也有明显的视觉效果
 * 参数说明：
 *   el - DOM元素，要执行动画的span元素
 *   suffixEl - 后缀元素，用于显示单位
 *   target - 目标数字
 * 返回值：无返回值，直接修改DOM元素内容
 */
const animateCounterMode1 = (el, suffixEl, target) => {
    const totalDuration = 2500; // 总动画时间2.5秒
    const startTime = performance.now();

    // 计算分段数量，让数字递增更有节奏感
    const segmentCount = Math.min(Math.floor(target / 1000), 20) + 5;
    const segmentDuration = totalDuration / segmentCount;

    // 生成分段点
    const segments = [];
    for (let i = 0; i <= segmentCount; i++) {
        const progress = i / segmentCount;
        // 使用缓动函数，开始慢，中间快，结束慢
        const easedProgress = progress < 0.5 
            ? 2 * progress * progress 
            : 1 - Math.pow(-2 * progress + 2, 2) / 2;
        segments.push(Math.floor(target * easedProgress));
    }

    let currentSegment = 0;
    let segmentStartTime = startTime;

    const step = (currentTime) => {
        const segmentElapsed = currentTime - segmentStartTime;

        if (segmentElapsed >= segmentDuration && currentSegment < segmentCount) {
            currentSegment++;
            segmentStartTime = currentTime;
        }

        // 在当前分段内插值
        const segmentProgress = Math.min(segmentElapsed / segmentDuration, 1);
        const startVal = segments[currentSegment];
        const endVal = segments[Math.min(currentSegment + 1, segmentCount)];
        const currentValue = Math.floor(startVal + (endVal - startVal) * segmentProgress);

        el.textContent = currentValue.toLocaleString();
        if (suffixEl) suffixEl.textContent = '+';

        if (currentSegment < segmentCount || currentValue < target) {
            window.requestAnimationFrame(step);
        } else {
            el.textContent = target.toLocaleString();
            if (suffixEl) suffixEl.textContent = '+';
        }
    };

    window.requestAnimationFrame(step);
};

/**
 * 数字递增动画函数 - 模式二（单位折算显示）
 * 功能说明：实现从0开始分阶段递增，自动转换单位
 *         - 阶段1: 0-999，显示整数，单位+
 *         - 阶段2: 1000-9999，显示整数，单位K+
 *         - 阶段3: 10000-目标值，显示小数，单位W+
 * 参数说明：
 *   el - DOM元素，要执行动画的span元素
 *   suffixEl - 后缀元素，用于显示单位
 *   target - 目标数字
 * 返回值：无返回值，直接修改DOM元素内容
 */
const animateCounterMode2 = (el, suffixEl, target) => {
    const startTime = performance.now();
    const stageDuration = 1000; // 每个阶段1秒

    // 计算需要经过哪些阶段
    let stagesToRun = [];
    if (target >= 10000) {
        stagesToRun = [
            { start: 0, end: 999, duration: stageDuration },
            { start: 1000, end: 9999, duration: stageDuration },
            { start: 10000, end: target, duration: stageDuration }
        ];
    } else if (target >= 1000) {
        stagesToRun = [
            { start: 0, end: 999, duration: stageDuration },
            { start: 1000, end: target, duration: stageDuration }
        ];
    } else {
        stagesToRun = [
            { start: 0, end: target, duration: stageDuration }
        ];
    }

    const totalDuration = stagesToRun.reduce((sum, s) => sum + s.duration, 0);

    /**
     * 根据当前数字值格式化显示（单位折算模式）
     * 参数说明：num - 当前数字值
     * 返回值：包含displayValue（显示值）、suffix（单位）的对象
     */
    const formatCurrentNumber = (num) => {
        if (num >= 10000) {
            return { displayValue: (num / 10000).toFixed(1), suffix: 'W+' };
        } else if (num >= 1000) {
            return { displayValue: Math.floor(num / 1000).toString(), suffix: 'K+' };
        } else {
            return { displayValue: Math.floor(num).toString(), suffix: '+' };
        }
    };

    /**
     * 动画帧更新函数
     * 参数说明：currentTime - 当前时间戳
     */
    const step = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / totalDuration, 1);

        // 计算当前应该在哪个阶段
        let currentValue = 0;
        let accumulatedTime = 0;

        for (let i = 0; i < stagesToRun.length; i++) {
            const stage = stagesToRun[i];
            const stageStart = accumulatedTime;
            const stageEnd = accumulatedTime + stage.duration;

            if (elapsed <= stageEnd || i === stagesToRun.length - 1) {
                const stageProgress = Math.min(Math.max((elapsed - stageStart) / stage.duration, 0), 1);
                currentValue = stage.start + (stage.end - stage.start) * stageProgress;
                break;
            }

            accumulatedTime = stageEnd;
        }

        // 格式化显示
        const formatted = formatCurrentNumber(currentValue);
        el.textContent = formatted.displayValue;
        if (suffixEl) suffixEl.textContent = formatted.suffix;

        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            const finalFormatted = formatCurrentNumber(target);
            el.textContent = finalFormatted.displayValue;
            if (suffixEl) suffixEl.textContent = finalFormatted.suffix;
        }
    };

    window.requestAnimationFrame(step);
};

/**
 * 统一动画入口函数
 * 功能说明：根据显示模式选择对应的动画函数
 * 参数说明：
 *   el - DOM元素，要执行动画的span元素
 *   suffixEl - 后缀元素，用于显示单位
 * 返回值：无返回值
 */
const animateCounter = (el, suffixEl) => {
    const target = +el.getAttribute('data-target');
    const originalSuffix = suffixEl ? suffixEl.textContent.trim() : '';
    const useDynamicSuffix = (originalSuffix === '');

    // 只对总调用次数使用模式判断，其他统计项使用简单动画
    if (useDynamicSuffix) {
        if (statsDisplayMode === 0) {
            animateCounterMode1(el, suffixEl, target);
        } else {
            animateCounterMode2(el, suffixEl, target);
        }
    } else {
        // 其他统计项（延迟、接口数量等）使用简单递增动画
        const totalDuration = 1500;
        const startTime = performance.now();

        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / totalDuration, 1);
            const currentValue = Math.floor(target * progress);

            el.textContent = currentValue;

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target;
            }
        };

        window.requestAnimationFrame(step);
    }
};

const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !statsAnimated) {
            counters.forEach(counter => {
                const parent = counter.parentElement;
                const suffixEl = parent.querySelector('.counter-suffix') || null;
                animateCounter(counter, suffixEl);
            });
            statsAnimated = true;
        }
    });
}, { threshold: 0.5 });
statsObserver.observe(statsSection);

// ===== HERO TYPEWRITER（文案由 index.php 注入 window.homeHeroConfig）=====
const titleEl = document.getElementById('hero-title');
const DEFAULT_HOME_HERO = {
    glitchLine: 'FEER API',
    startDelayMs: 500,
    glitchPauseMs: 1500,
    line2Plain: '开发者的',
    line2Accent: '免费API',
    line2Rest: '接口宝库',
};
const heroCfg = Object.assign({}, DEFAULT_HOME_HERO, typeof window.homeHeroConfig === 'object' && window.homeHeroConfig !== null ? window.homeHeroConfig : {});
const textSequence = [
    { type: 'text', value: heroCfg.glitchLine, glitch: true, delayAfter: heroCfg.glitchPauseMs },
    { type: 'delete', speed: 80 },
    { type: 'html' },
];
let sequenceIndex = 0, charIndex = 0, currentHtml = '';

function executeSequence() {
    if (!titleEl || sequenceIndex >= textSequence.length) return;
    const step = textSequence[sequenceIndex];
    switch (step.type) {
        case 'text':
            if (step.glitch) titleEl.classList.add('is-glitching');
            if (charIndex < step.value.length) {
                currentHtml += step.value[charIndex];
                titleEl.innerHTML = currentHtml + '<span class="typing-cursor"></span>';
                titleEl.setAttribute('data-text', currentHtml);
                charIndex++;
                setTimeout(executeSequence, 120 + Math.random() * 50);
            } else {
                titleEl.innerHTML = currentHtml;
                charIndex = 0; sequenceIndex++;
                setTimeout(executeSequence, step.delayAfter);
            }
            break;
        case 'html':
            titleEl.classList.remove('is-glitching');
            var plainText = heroCfg.line2Plain, coloredText = heroCfg.line2Accent, endText = heroCfg.line2Rest;
            if (charIndex < plainText.length) {
                currentHtml += plainText[charIndex];
                titleEl.innerHTML = currentHtml + '<span class="typing-cursor"></span>';
                charIndex++;
                setTimeout(executeSequence, 100);
            } else if (currentHtml.indexOf('<br>') === -1) {
                currentHtml += '<br>';
                titleEl.innerHTML = currentHtml + '<span class="typing-cursor"></span>';
                setTimeout(executeSequence, 200);
            } else {
                if (!step.currentPart) step.currentPart = '';
                var fullPart = coloredText + endText;
                if (step.currentPart.length < fullPart.length) {
                    step.currentPart += fullPart[step.currentPart.length];
                    var coloredIdx = Math.min(step.currentPart.length, coloredText.length);
                    var endIdx = Math.max(0, step.currentPart.length - coloredText.length);
                    titleEl.innerHTML = plainText + '<br><span style="color: var(--accent-primary)">' + coloredText.substring(0, coloredIdx) + '</span>' + endText.substring(0, endIdx) + '<span class="typing-cursor"></span>';
                    setTimeout(executeSequence, 100);
                } else {
                    titleEl.innerHTML = plainText + '<br><span style="color: var(--accent-primary)">' + coloredText + '</span>' + endText;
                    sequenceIndex++;
                }
            }
            break;
        case 'delete':
            if (currentHtml.length > 0) {
                currentHtml = currentHtml.slice(0, -1);
                titleEl.innerHTML = currentHtml + '<span class="typing-cursor"></span>';
                titleEl.setAttribute('data-text', currentHtml);
                setTimeout(executeSequence, step.speed);
            } else { sequenceIndex++; charIndex = 0; setTimeout(executeSequence, 300); }
            break;
    }
}
setTimeout(executeSequence, heroCfg.startDelayMs);

// MOBILE MENU + SHADER：见 assets/js/shell.js

// ===== API LIST - 只显示8个 =====
let currentCategory = 'all';
let currentSearch = '';

/**
 * 请求方式徽标 HTML（与弹窗、卡片共用；父级用 gap 排版，勿给子项加 flex:1）
 */
function buildMethodBadgesInnerHtml(api) {
    let h = '';
    if (api.methods && api.methods.length > 1) {
        h = api.methods.slice(0, 2).map(m =>
            `<span class="method-badge ${String(m).toLowerCase()}">${m}</span>`
        ).join('');
        if (api.methods.length > 2) {
            h += `<span class="api-item-more">+${api.methods.length - 2}</span>`;
        }
    } else {
        const methodStr = api.method || 'GET';
        const methodArr = methodStr.split(',').map(m => m.trim()).filter(m => m);
        h = methodArr.slice(0, 2).map(m =>
            `<span class="method-badge ${m.toLowerCase()}">${m}</span>`
        ).join('');
        if (methodArr.length > 2) {
            h += `<span class="api-item-more">+${methodArr.length - 2}</span>`;
        }
    }
    return h;
}

/**
 * 与 method-badge 同形：免费 / 积分·次 / KEY（维护中接口不返回）
 */
function buildApiTagSpans(api) {
    const spans = [];
    const isMaintenance = (api.maintenance == 1 || api.maintenance === '1');
    if (isMaintenance) return spans;
    if ((api.points_cost || 0) <= 0) {
        spans.push('<span class="api-chip api-chip--free">免费</span>');
    } else {
        spans.push(`<span class="api-chip api-chip--points">${api.points_cost}积分/次</span>`);
    }
    var keyMode = parseInt(api.require_api_key, 10) || 0;
    if (keyMode === 1) {
        spans.push('<span class="api-chip api-chip--key">KEY必填</span>');
    } else if (keyMode === 2) {
        spans.push('<span class="api-chip api-chip--key">KEY可选</span>');
    }
    return spans;
}

function escapeApiModalText(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderAPI(data) {
    const container = document.getElementById('api-list');
    const displayData = data.slice(0, 8); // 只显示8个

    if (displayData.length === 0) {
        container.innerHTML = `<div class="col-span-full text-center py-12" style="color: var(--text-muted);">没有找到相关接口</div>`;
        return;
    }

    container.innerHTML = displayData.map(api => {
        const methodBadges = buildMethodBadgesInnerHtml(api);

        // 维护中标签（与 api-chip 同形，卡片行内靠右）
        const maintenanceTag = (api.maintenance == 1 || api.maintenance === "1")
            ? '<span class="api-chip api-chip--maintenance" style="margin-left: auto;">维护中</span>'
            : '';

        // 接口标签（免费/KEY/积分）- 右上角显示
        let apiTags = '';
        const tags = buildApiTagSpans(api);

        if (tags.length > 0) {
            apiTags = `<div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end;">${tags.join('')}</div>`;
        }

        return `
        <div class="api-card api-card-compact" data-category="${api.category}" style="position: relative;">
            ${apiTags}
            <div class="flex justify-start items-start mb-2 flex-wrap gap-1">
                ${methodBadges}
                ${maintenanceTag}
            </div>
            <h3 class="font-bold">${api.name}</h3>
            <p style="color: var(--text-muted);">${api.desc}</p>
            <div class="endpoint-box font-mono" style="background: var(--endpoint-bg); border: 1px solid var(--endpoint-border); color: var(--accent-primary);">
                ${api.full_url || api.endpoint}
            </div>
            <a href="${(window.VS_BASE_URL || '') + '/apis'}" class="btn-geek w-full mt-2 text-center text-xs block">查看详情</a>
        </div>
    `}).join('');
}

// 分类展开/收起功能
let categoryExpanded = false;

/**
 * 切换分类展开/收起状态
 * 功能说明：点击"更多分类"按钮时，展开或收起隐藏的分类按钮
 * 参数说明：无参数
 * 返回值：无返回值
 */
function toggleCategoryExpand() {
    const hiddenBtns = document.querySelectorAll('#category-btns .cat-btn-hidden');
    const moreBtn = document.getElementById('catMoreBtn');
    const expandIcon = moreBtn.querySelector('.expand-icon');
    const btnText = moreBtn.querySelector('span');

    categoryExpanded = !categoryExpanded;

    hiddenBtns.forEach(btn => {
        if (categoryExpanded) {
            btn.classList.add('show');
        } else {
            btn.classList.remove('show');
        }
    });

    if (categoryExpanded) {
        btnText.textContent = '收起分类';
        expandIcon.style.transform = 'rotate(90deg)';
    } else {
        btnText.textContent = '更多分类';
        expandIcon.style.transform = 'rotate(0deg)';
    }
}

function filterAPI(category, btnElement) {
    currentCategory = category;
    document.querySelectorAll('#category-btns .cat-btn').forEach(btn => btn.classList.remove('active'));
    if(btnElement) btnElement.classList.add('active');
    applyFilters();
}

function applyFilters() {
    let filtered = apiData;
    if (currentCategory !== 'all') filtered = filtered.filter(a => a.category === currentCategory);
    if (currentSearch) {
        const s = currentSearch.toLowerCase();
        filtered = filtered.filter(a => a.name.toLowerCase().includes(s) || a.desc.toLowerCase().includes(s));
    }
    renderAPI(filtered);
}

document.getElementById('search-input').addEventListener('input', (e) => { currentSearch = e.target.value; applyFilters(); });

renderAPI(apiData);

// ===== API SELECT MODAL =====
const apiModal = document.getElementById('api-modal');
const modalSearchInput = document.getElementById('modal-search');
const modalListContainer = document.getElementById('modal-list');
const selectedApiText = document.getElementById('selected-api-text');
const hiddenApiSelect = document.getElementById('api-select');
const paramsContainer = document.getElementById('params-container');

let selectedApiId = null;

function openSelectModal() {
    apiModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    modalSearchInput.value = '';
    // 使用 requestAnimationFrame 延迟渲染，避免阻塞动画
    requestAnimationFrame(() => {
        renderModalList();
    });
}

function closeSelectModal(e) {
    if (e && e.target !== apiModal) return;
    apiModal.classList.remove('open');
    document.body.style.overflow = '';
}

function selectApi(id) {
    selectedApiId = id;
    // 使用宽松相等，因为ID可能是数字或字符串
    const api = apiData.find(a => a.id == id);
    if (api) {
        selectedApiText.textContent = `${api.method} ${api.full_url || api.endpoint}`;
        hiddenApiSelect.value = id;
        const cfgTitle = document.getElementById('playground-config-title-text');
        if (cfgTitle) {
            const label = '请求配置-' + String(api.name != null ? api.name : '').trim();
            cfgTitle.textContent = label;
            cfgTitle.setAttribute('title', label);
        }
        // 渲染请求方式选择器
        renderMethodSelector(api);
        // 渲染参数输入框
        renderParams(api);
    }
    closeSelectModal();
}

// 渲染请求方式选择器
function renderMethodSelector(api) {
    const container = document.getElementById('method-selector-container');
    const selector = document.getElementById('method-selector');

    if (!api.methods || api.methods.length <= 1) {
        container.style.display = 'none';
        selector.innerHTML = '';
        return;
    }

    container.style.display = 'block';
    selector.innerHTML = api.methods.map((method, index) => `
        <label class="method-option ${index === 0 ? 'active' : ''}" data-method="${method}">
            <input type="radio" name="request_method" value="${method}" ${index === 0 ? 'checked' : ''} style="display: none;">
            ${method}
        </label>
    `).join('');

    // 添加点击事件
    selector.querySelectorAll('.method-option').forEach(option => {
        option.addEventListener('click', function() {
            selector.querySelectorAll('.method-option').forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
}

// 获取当前选中的请求方式
function getSelectedMethod() {
    const selector = document.getElementById('method-selector');
    const container = document.getElementById('method-selector-container');

    // 如果选择器显示且有选中的选项
    if (selector && container && container.style.display !== 'none') {
        const activeOption = selector.querySelector('.method-option.active');
        if (activeOption) return activeOption.dataset.method;

        const checkedRadio = selector.querySelector('input[type="radio"]:checked');
        if (checkedRadio) return checkedRadio.value;
    }

    // 如果选择器被隐藏（只有一个方法），使用当前选中API的方法
    const apiId = document.getElementById('api-select').value;
    if (apiId && typeof apiData !== 'undefined') {
        const api = apiData.find(a => a.id == apiId);
        if (api) {
            // 优先使用 methods 数组的第一个，否则使用 method 字段
            if (api.methods && api.methods.length > 0) {
                return api.methods[0];
            } else if (api.method) {
                return api.method;
            }
        }
    }

    return 'GET';
}

function renderParams(api) {
    // 解析参数
    let params = [];
    if (api.params) {
        try {
            params = typeof api.params === 'string' ? JSON.parse(api.params) : api.params;
        } catch(e) {}
    }

    const keyMode = parseInt(api.require_api_key, 10) || 0;
    if (keyMode === 1 || keyMode === 2) {
        const hasKey = params.some(function (p) {
            const n = String(p && p.name ? p.name : '').toLowerCase();
            return n === 'key' || n === 'api_key' || n === 'apikey';
        });
        if (!hasKey) {
            params = params.concat([{
                name: 'key',
                type: 'string',
                required: keyMode === 1,
                description: keyMode === 1
                    ? '平台 API 访问密钥（必填）'
                    : '平台 API 访问密钥（选填）',
                placeholder: 'sk_...'
            }]);
        }
    }

    if (params.length > 0) {
        paramsContainer.classList.add('show');
        paramsContainer.innerHTML = `
            <div class="text-xs font-mono mb-2" style="color: var(--accent-secondary);">// 请求参数</div>
            ${params.map(p => {
                let inputHtml = '';
                const inputType = p.type || 'text';

                if (inputType === 'file') {
                    // 文件上传类型
                    inputHtml = `
                        <div class="param-item">
                            <label class="param-label">
                                ${p.name}
                                ${p.required ? '<span class="param-required">*</span>' : ''}
                                ${p.description ? `<span style="color: var(--text-muted);">- ${p.description}</span>` : ''}
                            </label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="file" 
                                       class="param-input" 
                                       data-param="${p.name}"
                                       data-type="file"
                                       accept="${p.accept || '*/*'}"
                                       style="flex: 1;"
                                       ${p.required ? 'required' : ''}>
                                <span class="file-name" style="font-size: 0.7rem; color: var(--text-muted);">未选择文件</span>
                            </div>
                        </div>
                    `;
                } else {
                    // 普通输入类型
                    inputHtml = `
                        <div class="param-item">
                            <label class="param-label">
                                ${p.name}
                                ${p.required ? '<span class="param-required">*</span>' : ''}
                                ${p.description ? `<span style="color: var(--text-muted);">- ${p.description}</span>` : ''}
                            </label>
                            <input type="${inputType === 'number' ? 'number' : 'text'}" 
                                   class="param-input" 
                                   data-param="${p.name}"
                                   data-type="${inputType}"
                                   placeholder="${p.placeholder || p.name}"
                                   ${p.required ? 'required' : ''}>
                        </div>
                    `;
                }
                return inputHtml;
            }).join('')}
        `;

        // 为文件输入添加事件监听
        paramsContainer.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files.length > 0 ? this.files[0].name : '未选择文件';
                this.parentElement.querySelector('.file-name').textContent = fileName;
            });
        });
        applyPlaygroundSessionApiKey(api, paramsContainer);
    } else {
        paramsContainer.classList.remove('show');
        paramsContainer.innerHTML = '';
    }
}

function renderModalList() {
    const searchTerm = modalSearchInput.value.toLowerCase();
    let filteredData = apiData;
    if (searchTerm) filteredData = filteredData.filter(a => a.name.toLowerCase().includes(searchTerm) || (a.full_url || a.endpoint).toLowerCase().includes(searchTerm));

    // 使用 DocumentFragment 减少重排
    const fragment = document.createDocumentFragment();

    if (filteredData.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'text-center py-8';
        emptyDiv.style.cssText = 'color: var(--text-muted); grid-column: 1 / -1;';
        emptyDiv.textContent = '没有找到相关接口';
        fragment.appendChild(emptyDiv);
    } else {
        const groups = {};
        filteredData.forEach(api => {
            if (!groups[api.category]) groups[api.category] = [];
            groups[api.category].push(api);
        });

        Object.keys(groups).sort().forEach(catKey => {
            const titleDiv = document.createElement('div');
            titleDiv.className = 'api-group-title';
            titleDiv.textContent = categoryNames[catKey] || catKey;
            fragment.appendChild(titleDiv);

            groups[catKey].forEach(api => {
                const itemDiv = document.createElement('div');
                itemDiv.className = `api-item ${selectedApiId == api.id ? 'selected' : ''}`;
                itemDiv.onclick = () => selectApi(api.id);

                const methodsHtml = buildMethodBadgesInnerHtml(api);
                const metaParts = buildApiTagSpans(api);
                const maintHtml = (api.maintenance == 1 || api.maintenance === '1')
                    ? '<span class="api-chip api-chip--maintenance">维护中</span>'
                    : '';
                const tagsRowHtml = `${methodsHtml}${maintHtml}${metaParts.join('')}`;
                const pathRaw = api.full_url || api.endpoint || '';
                const nameRaw = api.name || '';

                itemDiv.innerHTML = `
                    <div class="api-item-body">
                        <div class="api-item-tags">${tagsRowHtml}</div>
                        <div class="api-item-path">${escapeApiModalText(pathRaw)}</div>
                        <div class="api-item-name">${escapeApiModalText(nameRaw)}</div>
                    </div>
                `;
                fragment.appendChild(itemDiv);
            });
        });
    }

    modalListContainer.innerHTML = '';
    modalListContainer.appendChild(fragment);
}

function getPlaygroundUserApiKey() {
    if (typeof window.playgroundUserApiKey !== 'string' || !window.playgroundUserApiKey.trim()) {
        return '';
    }
    return window.playgroundUserApiKey.trim();
}

function getPlaygroundKeyContext() {
    const d = { loggedIn: false, apiKeyCount: 0, userCenterUrl: '/user/index.php', loginUrl: '/user/login.php' };
    if (typeof window.playgroundKeyContext === 'object' && window.playgroundKeyContext !== null) {
        return Object.assign(d, window.playgroundKeyContext);
    }
    return d;
}

function removePlaygroundKeyHint(container) {
    if (!container) return;
    const el = container.querySelector('#playground-key-autofill-hint');
    if (el) el.remove();
}

function findKeyParamInput(container) {
    if (!container) return null;
    const inputs = container.querySelectorAll('.param-input[data-param]');
    for (let i = 0; i < inputs.length; i++) {
        const n = String(inputs[i].dataset.param || '').toLowerCase();
        if (n === 'key' || n === 'api_key' || n === 'apikey') return inputs[i];
    }
    return null;
}

function mountPlaygroundKeyHint(container, html, tone) {
    const hint = document.createElement('div');
    hint.id = 'playground-key-autofill-hint';
    hint.className = 'text-xs mt-2 playground-key-status-hint playground-key-status-hint--' + (tone || 'info');
    hint.innerHTML = html;
    const firstLabel = container.querySelector('.text-xs.font-mono.mb-2');
    if (firstLabel && firstLabel.parentNode === container) {
        firstLabel.insertAdjacentElement('afterend', hint);
    } else {
        container.insertBefore(hint, container.firstChild);
    }
}

/**
 * 需密钥接口：已登录有密钥则自动填入并提示；已登录无密钥 / 未登录分别提示
 */
function applyPlaygroundSessionApiKey(api, container) {
    removePlaygroundKeyHint(container);
    if (!api || !container) return;
    var keyMode = parseInt(api.require_api_key, 10) || 0;
    if (keyMode !== 1 && keyMode !== 2) return;

    const ctx = getPlaygroundKeyContext();
    const loggedIn = !!ctx.loggedIn;
    const keyCount = parseInt(String(ctx.apiKeyCount != null ? ctx.apiKeyCount : 0), 10) || 0;
    const userCenter = String(ctx.userCenterUrl || '/user/index.php');
    const loginUrl = String(ctx.loginUrl || '/user/login.php');
    const keyVal = getPlaygroundUserApiKey();
    const keyInput = findKeyParamInput(container);

    if (loggedIn && keyVal && keyInput && keyInput.type !== 'file') {
        if (!String(keyInput.value || '').trim()) {
            keyInput.value = keyVal;
        }
        mountPlaygroundKeyHint(
            container,
            keyMode === 2
                ? '已填入可用密钥，可直接测试。密钥管理见 <a class="playground-key-hint-link" href="' + userCenter + '">用户中心</a>。'
                : '已随机填入一条可用密钥，可直接测试。密钥管理见 <a class="playground-key-hint-link" href="' + userCenter + '">用户中心</a>。',
            'info'
        );
        return;
    }

    if (loggedIn && keyCount === 0) {
        if (keyMode === 2) {
            mountPlaygroundKeyHint(
                container,
                '当前账户暂无密钥，可在 <a class="playground-key-hint-link" href="' + userCenter + '">用户中心</a> 创建后填入测试。',
                'info'
            );
            return;
        }
        if (keyMode === 1) {
            mountPlaygroundKeyHint(
                container,
                '账户下暂无密钥，无法自动填入。请至 <a class="playground-key-hint-link" href="' + userCenter + '">用户中心</a> 创建后再测。',
                'warn'
            );
            return;
        }
    }

    if (loggedIn && keyCount > 0 && !keyVal) {
        mountPlaygroundKeyHint(
            container,
            '未能自动加载密钥，请手填或刷新；也可在 <a class="playground-key-hint-link" href="' + userCenter + '">用户中心</a> 复制。',
            'warn'
        );
        return;
    }

    if (!loggedIn) {
        mountPlaygroundKeyHint(
            container,
            keyMode === 2
                ? '如需填写 key，请先 <a class="playground-key-hint-link" href="' + loginUrl + '">登录</a> 后在用户中心创建。'
                : '需密钥：<a class="playground-key-hint-link" href="' + loginUrl + '">登录</a> 后在用户中心创建并填入 key。',
            keyMode === 2 ? 'info' : 'guest'
        );
    }
}

// ===== SEND REQUEST =====
async function sendRequest() {
    const apiId = hiddenApiSelect.value;
    if (!apiId) {
        selectedApiText.textContent = "请先选择接口！";
        selectedApiText.style.color = "var(--accent-secondary)";
        return;
    }
    selectedApiText.style.color = "";

    // 使用宽松相等，因为ID可能是数字或字符串
    const api = apiData.find(a => a.id == apiId);
    if (!api) return;

    const output = document.getElementById('response-body');
    const status = document.getElementById('status-badge');

    // 获取当前选中的请求方式
    const currentMethod = getSelectedMethod();

    output.innerHTML = "<span style='color: var(--accent-secondary)'>// 正在发送请求...</span>";
    status.textContent = "处理中";
    status.className = "text-xs px-2 py-1 rounded bg-yellow-900 text-yellow-400 font-mono";

    // 检查是否有文件上传
    const fileInputs = paramsContainer.querySelectorAll('input[type="file"]');
    const hasFiles = Array.from(fileInputs).some(input => input.files.length > 0);

    // 构建完整的请求URL
    let url = api.full_url || api.endpoint;

    // 收集参数
    const params = {};
    const paramInputs = paramsContainer.querySelectorAll('.param-input');
    paramInputs.forEach(input => {
        if (input.type !== 'file' && input.value) params[input.dataset.param] = input.value;
    });

    // 对于GET请求，构建带参数的URL
    if ((currentMethod === 'GET' || currentMethod === 'HEAD' || currentMethod === 'OPTIONS') && Object.keys(params).length > 0) {
        url += (url.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();
    }

    // 对于视频/音频/图片类型且是GET请求，直接使用原始URL（流式加载，避免代理下载延迟）
    const isMediaRequest = (api.returnType === 'video' || api.returnType === 'audio' || api.returnType === 'image');
    const isGetMethod = (currentMethod === 'GET' || currentMethod === 'HEAD');

    if (isMediaRequest && isGetMethod && !hasFiles) {
        // 直接使用原始URL，浏览器会流式加载
        status.textContent = "200 OK";
        status.className = "text-xs px-2 py-1 rounded bg-green-900 text-green-400 font-mono";

        if (api.returnType === 'video') {
            output.innerHTML = `<div style="text-align: center; padding: 1rem;"><video controls style="max-width: 100%; max-height: 300px; border-radius: 4px; border: 1px solid var(--border-color); background: #000;" preload="metadata"><source src="${url}" type="video/mp4"><source src="${url}" type="video/webm"><source src="${url}" type="video/ogg">您的浏览器不支持视频播放</video><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">视频类型: 流式加载</div><div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">提示: 视频直接从源站加载，无需等待下载完成</div></div>`;
        } else if (api.returnType === 'audio') {
            output.innerHTML = `<div style="text-align: center; padding: 1rem;"><svg class="w-12 h-12" style="color: #ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg><audio controls autoplay style="width: 100%; max-width: 400px; margin-top: 1rem;" preload="metadata"><source src="${url}" type="audio/mpeg"><source src="${url}" type="audio/wav"><source src="${url}" type="audio/ogg">您的浏览器不支持音频播放</audio><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">音频类型: 流式加载</div><div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">提示: 音频直接从源站加载，无需等待下载完成</div></div>`;
        } else if (api.returnType === 'image') {
            output.innerHTML = `<div style="text-align: center; padding: 1rem;"><img src="${url}" style="max-width: 100%; max-height: 300px; border-radius: 4px; border: 1px solid var(--border-color);" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><div style="display: none; color: #ef4444;">图片加载失败</div><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">图片类型: 直接加载</div></div>`;
        }
        return;
    }

    // 其他情况使用代理请求
    try {
        let proxyBody;
        let proxyContentType = 'application/json';

        if (hasFiles && (currentMethod === 'POST' || currentMethod === 'PUT' || currentMethod === 'PATCH')) {
            const formData = new FormData();
            formData.append('url', url);
            formData.append('method', currentMethod);
            paramInputs.forEach(input => {
                if (input.type === 'file') {
                    if (input.files.length > 0) formData.append(input.dataset.param, input.files[0]);
                } else if (input.value) {
                    formData.append(input.dataset.param, input.value);
                }
            });
            proxyBody = formData;
            proxyContentType = null; // 让浏览器自动设置 multipart boundary
        } else {
            const bodyStr = (currentMethod === 'POST' || currentMethod === 'PUT' || currentMethod === 'PATCH') && Object.keys(params).length > 0
                ? JSON.stringify(params)
                : '';
            proxyBody = JSON.stringify({ url, method: currentMethod, body: bodyStr, contentType: 'application/json' });
        }

        const proxyOpts = { method: 'POST', body: proxyBody };
        if (proxyContentType) proxyOpts.headers = { 'Content-Type': proxyContentType };

        let json;
        try {
            const response = await fetch(API_PROXY_URL, proxyOpts);
            if (!response.ok) {
                output.innerHTML = `<span style="color: #ef4444">// 网络错误: ${response.status} ${response.statusText}</span>`;
                status.textContent = 'Error';
                status.className = "text-xs px-2 py-1 rounded bg-red-900 text-red-400 font-mono";
                return;
            }
            const text = await response.text();
            try {
                json = JSON.parse(text);
            } catch (e) {
                output.innerHTML = `<span style="color: #ef4444">// 响应解析失败: ${text.substring(0, 200)}</span>`;
                status.textContent = 'Error';
                status.className = "text-xs px-2 py-1 rounded bg-red-900 text-red-400 font-mono";
                return;
            }
        } catch (error) {
            output.innerHTML = `<span style="color: #ef4444">// 请求失败: ${error.message}</span>`;
            status.textContent = "Error";
            status.className = "text-xs px-2 py-1 rounded bg-red-900 text-red-400 font-mono";
            return;
        }

        if (json.error) {
            output.innerHTML = `<span style="color: #ef4444">// ${json.message || json.error}</span>`;
            status.textContent = 'Error';
            status.className = "text-xs px-2 py-1 rounded bg-red-900 text-red-400 font-mono";
            return;
        }

        const contentType = json.contentType || '';
        const httpStatus = json.status || 200;
        const ok = json.ok || (httpStatus >= 200 && httpStatus < 300);
        const statusText = ok ? `${httpStatus} OK` : `${httpStatus} Error`;
        const statusStyle = ok ? 'bg-green-900 text-green-400' : 'bg-red-900 text-red-400';
        const requestTime = json.time ? `${json.time}ms` : '';

        // 处理二进制内容（图片、视频、音频）
        if (json.isBinary && json.binaryType) {
            if (json.binaryType === 'image') {
                if (!json.data || json.data.length === 0) {
                    output.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--text-muted);"><svg class="w-12 h-12" style="color: #ef4444; margin-bottom: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg><p>图片数据为空或加载失败</p><p style="font-size: 0.7rem; margin-top: 0.5rem;">content-type: ${contentType || 'unknown'}</p></div>`;
                } else {
                    const imageUrl = `data:${contentType || 'image/png'};base64,${json.data}`;
                    output.innerHTML = `<div style="text-align: center; padding: 1rem;"><img src="${imageUrl}" style="max-width: 100%; max-height: 300px; border-radius: 4px; border: 1px solid var(--border-color);" onerror="this.parentElement.innerHTML='<div style=\\'text-align:center;padding:2rem;color:#ef4444;\\'><svg class=\\'w-12 h-12\\' style=\\'color:#ef4444;margin-bottom:1rem;\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'2\\' d=\\'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z\\'/></svg><p>图片解码失败</p></div>'"><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">图片类型: ${contentType || 'image/png'}</div><div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">数据大小: ${Math.round(json.data.length * 0.75 / 1024)}KB</div></div>`;
                }
            } else if (json.binaryType === 'video') {
                if (!json.data || json.data.length === 0) {
                    output.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--text-muted);"><p>视频数据为空</p></div>`;
                } else {
                    const videoUrl = `data:${contentType || 'video/mp4'};base64,${json.data}`;
                    output.innerHTML = `<div style="text-align: center; padding: 1rem;"><video controls style="max-width: 100%; max-height: 300px; border-radius: 4px; border: 1px solid var(--border-color); background: #000;" preload="metadata"><source src="${videoUrl}" type="${contentType || 'video/mp4'}">您的浏览器不支持视频播放</video><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">视频类型: ${contentType || 'video/mp4'}</div></div>`;
                }
            } else if (json.binaryType === 'audio') {
                if (!json.data || json.data.length === 0) {
                    output.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--text-muted);"><p>音频数据为空</p></div>`;
                } else {
                    const audioUrl = `data:${contentType || 'audio/mpeg'};base64,${json.data}`;
                    output.innerHTML = `<div style="text-align: center; padding: 1rem;"><svg class="w-12 h-12" style="color: #ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg><audio controls autoplay style="width: 100%; max-width: 400px; margin-top: 1rem;" preload="metadata"><source src="${audioUrl}" type="${contentType || 'audio/mpeg'}">您的浏览器不支持音频播放</audio><div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted);">音频类型: ${contentType || 'audio/mpeg'}</div></div>`;
                }
            }
            status.textContent = `${statusText} ${requestTime}`;
            status.className = `text-xs px-2 py-1 rounded font-mono ${statusStyle}`;
            return;
        }

        // 处理非二进制内容
        const rawText = json.body || '';

        if (contentType.includes('text/html')) {
            output.innerHTML = `<div style="padding: 0.5rem;"><div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.5rem;">// HTML响应</div><iframe style="width: 100%; height: 200px; border: 1px solid var(--border-color); border-radius: 4px; background: #fff;" srcdoc="${rawText.replace(/"/g, '&quot;')}"></iframe></div>`;
        } else {
            try {
                const result = JSON.parse(rawText);
                output.innerHTML = syntaxHighlight(JSON.stringify(result, null, 2));
            } catch (e) {
                output.innerHTML = rawText ? `<pre style="white-space: pre-wrap; word-break: break-all;">${rawText.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>` : '<span style="color: var(--text-muted)">// 无返回内容</span>';
            }
        }
        status.textContent = `${statusText} ${requestTime}`;
        status.className = `text-xs px-2 py-1 rounded font-mono ${statusStyle}`;
    } catch (error) {
        output.innerHTML = `<span style="color: #ef4444">// 请求失败: ${error.message}</span>`;
        status.textContent = "Error";
        status.className = "text-xs px-2 py-1 rounded bg-red-900 text-red-400 font-mono";
    }
}

function syntaxHighlight(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'syntax-c';
        if (/^"/.test(match)) { cls = /:$/.test(match) ? 'syntax-k' : 'syntax-s'; }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}
