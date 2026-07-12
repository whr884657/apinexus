(function () {
    var mount = document.getElementById('hero-terminal-mount');
    if (!mount) {
        return;
    }

    var logoText = (mount.getAttribute('data-logo-text') || 'feerapi').toLowerCase();
    var safeLogo = logoText.replace(/[^a-z0-9-]/g, '') || 'feerapi';
    var curlUrl = 'https://api.' + safeLogo + '.dev/users';

    mount.innerHTML = [
        '<div class="terminal-window lg:mt-8 mt-8 w-full">',
            '<div class="terminal-header">',
                '<div class="flex gap-2">',
                    '<div class="dot bg-red-500"></div>',
                    '<div class="dot bg-yellow-500"></div>',
                    '<div class="dot bg-green-500"></div>',
                '</div>',
                '<span class="ml-4 text-xs" style="color: var(--text-muted)">bash ~ 快速开始</span>',
            '</div>',
            '<div class="terminal-body font-mono">',
                '<div class="mb-2"><span class="syntax-c"># 一行代码获取数据</span></div>',
                '<div><span style="color: var(--accent-primary)">$</span> curl ' + curlUrl + '</div>',
                '<div class="mt-2" style="color: var(--accent-primary)">{ "status": 200, "data": [ ... ] }<span class="typing-cursor"></span></div>',
            '</div>',
        '</div>'
    ].join('');
})();
