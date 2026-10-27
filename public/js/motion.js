/* Shared motion: scroll reveal with stagger, and number count-up.
   Safe without JS (content is never hidden until this script runs), and a no-op for reduced motion. */
(function () {
    'use strict';
    if (window.__moInit) return;
    window.__moInit = true;

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hasIO = 'IntersectionObserver' in window;

    // Elements that fade/slide in when scrolled into view
    var REVEAL = [
        'main section', 'main .card', '.wrap > section', '.wrap > .card',
        '[data-reveal]',
        '.fi-section', '.fi-wi-chart', '.fi-wi-table'
    ].join(',');

    // Elements whose whole text is a number and should count up
    var COUNT = '.num, [data-mo-count], .fi-wi-stats-overview-stat-value';
    var NUM = /^(\D{0,2})(\d{1,3}(?:,\d{3})+|\d+)(\.\d+)?(\s?(?:%|\+|k|K|M)?)$/;

    function countUp(el) {
        if (el.dataset.moDone) return;
        var raw = (el.textContent || '').trim();
        var m = NUM.exec(raw);
        if (!m || el.children.length) return;
        el.dataset.moDone = '1';
        var prefix = m[1], int = m[2], dec = m[3] || '', suffix = m[4] || '';
        var hasComma = int.indexOf(',') > -1;
        var target = parseFloat(int.replace(/,/g, '') + dec);
        if (!isFinite(target) || target <= 0) return;
        var decimals = dec ? dec.length - 1 : 0;
        var dur = Math.min(1400, 500 + Math.log10(target + 1) * 260);
        var start = null;
        function fmt(v) {
            var s = v.toFixed(decimals);
            if (hasComma) s = Number(s).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            return prefix + s + suffix;
        }
        el.textContent = fmt(0);
        function step(ts) {
            if (start === null) start = ts;
            var t = Math.min(1, (ts - start) / dur);
            var e = 1 - Math.pow(1 - t, 3);
            el.textContent = fmt(target * e);
            if (t < 1) requestAnimationFrame(step); else el.textContent = raw;
        }
        requestAnimationFrame(step);
    }

    function init(root) {
        root = root || document;
        if (reduce || !hasIO) return;

        var vh = window.innerHeight || 800;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) return;
                var el = en.target;
                el.classList.add('mo-in');
                io.unobserve(el);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

        // Reveal only what starts below the fold, so the first screen never flashes
        var seen = 0;
        root.querySelectorAll(REVEAL).forEach(function (el) {
            if (el.dataset.moSeen || el.closest('.mo-pre')) return;
            el.dataset.moSeen = '1';
            var r = el.getBoundingClientRect();
            if (r.top < vh * 0.92) return;
            // stagger siblings that enter together
            var idx = Array.prototype.indexOf.call(el.parentNode.children, el);
            el.style.setProperty('--mo-delay', Math.min(idx, 6) * 60 + 'ms');
            el.classList.add('mo-pre');
            io.observe(el);
        });

        // Count up when a number scrolls into view (or immediately if already visible)
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { countUp(en.target); cio.unobserve(en.target); }
            });
        }, { threshold: 0.4 });
        root.querySelectorAll(COUNT).forEach(function (el) {
            if (el.dataset.moDone || el.dataset.moWatch) return;
            if (!NUM.test((el.textContent || '').trim()) || el.children.length) return;
            el.dataset.moWatch = '1';
            cio.observe(el);
        });
    }

    function boot() { init(document); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
    // Livewire / Filament re-renders and SPA-style navigation
    document.addEventListener('livewire:navigated', function () { window.__moInit = true; boot(); });
})();
