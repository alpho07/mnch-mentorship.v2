/* Signs signed-in users out after a period of inactivity, with a warning and a "Stay signed in" button.
   Activity = typing, clicking, scrolling, touch or mouse movement in ANY open tab (shared through localStorage).
   Background polling (Livewire, notifications) is not user activity and does not keep the session alive.
   Config comes from data attributes on the script tag: data-timeout, data-warn (seconds), data-keep-alive, data-expire, data-home. */
(function () {
    'use strict';
    var tag = document.currentScript;
    if (!tag || window.__mnchIdle) return;
    window.__mnchIdle = true;

    var timeout = (parseInt(tag.dataset.timeout, 10) || 900) * 1000;
    var warnFor = (parseInt(tag.dataset.warn, 10) || 60) * 1000;
    var keepAliveUrl = tag.dataset.keepAlive, expireUrl = tag.dataset.expire, homeUrl = tag.dataset.home || '/';
    var KEY = 'mnch:lastActivity', OUT = 'mnch:signedOut';

    function store(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    function load(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function token() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }

    var last = Date.now(), lastWrite = 0, warned = false, ended = false, modal, countEl, tickTimer;
    store(KEY, String(last));

    function activity() {
        if (ended) return;
        var now = Date.now();
        // Once the warning is up, only the button counts, so an accidental mouse nudge doesn't hide it unnoticed.
        if (warned) return;
        last = now;
        if (now - lastWrite > 2000) { lastWrite = now; store(KEY, String(now)); }
    }
    ['mousemove', 'mousedown', 'keydown', 'input', 'wheel', 'scroll', 'touchstart', 'click', 'focus'].forEach(function (ev) {
        window.addEventListener(ev, activity, { passive: true, capture: true });
    });

    function build() {
        modal = document.createElement('div');
        modal.setAttribute('role', 'alertdialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'mnch-idle-title');
        modal.setAttribute('aria-describedby', 'mnch-idle-desc');
        modal.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(6,34,42,.62);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';
        modal.innerHTML =
            '<div style="width:100%;max-width:440px;background:#fff;color:#0F2A2E;border-radius:20px;padding:28px;box-shadow:0 30px 60px -20px rgba(0,0,0,.5)">' +
            '<div style="width:48px;height:48px;border-radius:14px;background:#FEF3C7;color:#B45309;display:flex;align-items:center;justify-content:center;margin-bottom:16px"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>' +
            '<h2 id="mnch-idle-title" style="margin:0 0 8px;font-size:21px;font-weight:800;letter-spacing:-.01em">Are you still there?</h2>' +
            '<p id="mnch-idle-desc" style="margin:0 0 20px;font-size:15px;line-height:1.55;color:#3D5558">For your security you will be signed out in <b id="mnch-idle-count" style="font-variant-numeric:tabular-nums">60</b> seconds because there has been no activity. Unsaved changes will be lost.</p>' +
            '<div style="display:flex;gap:10px;flex-wrap:wrap">' +
            '<button type="button" id="mnch-idle-stay" style="flex:1 1 180px;min-height:46px;border:0;border-radius:12px;background:#00838F;color:#fff;font:inherit;font-weight:700;font-size:15px;cursor:pointer">Stay signed in</button>' +
            '<button type="button" id="mnch-idle-out" style="flex:1 1 120px;min-height:46px;border:1px solid #D3DFE0;border-radius:12px;background:#fff;color:#0F2A2E;font:inherit;font-weight:600;font-size:15px;cursor:pointer">Sign out now</button>' +
            '</div></div>';
        document.body.appendChild(modal);
        countEl = modal.querySelector('#mnch-idle-count');
        modal.querySelector('#mnch-idle-stay').addEventListener('click', stay);
        modal.querySelector('#mnch-idle-out').addEventListener('click', function () { signOut(); });
    }

    function stay() {
        warned = false;
        last = Date.now(); lastWrite = last; store(KEY, String(last));
        if (modal) modal.style.display = 'none';
        fetch(keepAliveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' } })
            .then(function (r) { if (r.status === 401 || r.status === 419) signOut(true); })
            .catch(function () {});
    }

    function go() { window.location.replace(homeUrl + (homeUrl.indexOf('?') > -1 ? '&' : '?') + 'signed_out=inactive'); }

    function signOut(already) {
        if (ended) return;
        ended = true; clearInterval(tickTimer);
        store(OUT, String(Date.now()));
        if (already) return go();
        var done = false, finish = function () { if (!done) { done = true; go(); } };
        setTimeout(finish, 4000); // never leave the user stranded on a slow network
        fetch(expireUrl, { method: 'POST', credentials: 'same-origin', redirect: 'manual', headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'text/html' } })
            .then(finish, finish);
    }

    function tick() {
        var shared = parseInt(load(KEY) || '0', 10);
        var seen = Math.max(last, shared || 0);
        if (warned && shared > last) { /* another tab pressed "Stay signed in" */ warned = false; modal.style.display = 'none'; }
        if (!warned) last = Math.max(last, seen);
        var idle = Date.now() - (warned ? last : seen);
        var left = timeout - idle;
        if (left <= 0) return signOut();
        if (left <= warnFor) {
            if (!warned) {
                if (!modal) build();
                warned = true;
                modal.style.display = 'flex';
                modal.querySelector('#mnch-idle-stay').focus();
            }
            countEl.textContent = String(Math.ceil(left / 1000));
        }
    }

    window.addEventListener('storage', function (e) { if (e.key === OUT && !ended) { ended = true; go(); } });
    tickTimer = setInterval(tick, 1000);
})();
