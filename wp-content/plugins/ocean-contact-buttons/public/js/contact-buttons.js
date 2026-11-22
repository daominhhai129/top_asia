(function () {
    'use strict';
    function init(root) {
        if (!root || root.dataset.ocbReady === '1') return;
        root.dataset.ocbReady = '1';
        var overlay = root.querySelector('[data-ocb-overlay]');
        var dialog = root.querySelector('.ocb-dialog');
        var opener = root.querySelector('[data-ocb-open]');
        var closer = root.querySelector('[data-ocb-close]');
        var previousFocus = null;

        function openDialog() {
            if (!overlay || !dialog) return;
            previousFocus = document.activeElement;
            overlay.hidden = false;
            requestAnimationFrame(function () {
                overlay.classList.add('is-open');
                dialog.focus();
            });
            document.addEventListener('keydown', onKeydown);
        }
        function closeDialog() {
            if (!overlay || overlay.hidden) return;
            overlay.classList.remove('is-open');
            document.removeEventListener('keydown', onKeydown);
            window.setTimeout(function () {
                overlay.hidden = true;
                if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
            }, 220);
        }
        function onKeydown(event) {
            if (event.key === 'Escape') closeDialog();
            if (event.key === 'Tab' && dialog) {
                var focusable = dialog.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])');
                if (!focusable.length) return;
                var first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        }
        if (opener) opener.addEventListener('click', openDialog);
        if (closer) closer.addEventListener('click', closeDialog);
        if (overlay) overlay.addEventListener('click', function (event) {
            if (event.target === overlay) closeDialog();
        });
    }
    function trackContactClicks() {
        if (document.documentElement.dataset.ocbTrackingReady === '1') return;
        document.documentElement.dataset.ocbTrackingReady = '1';
        document.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest('[data-ocb-track]') : null;
            if (!target || !window.OCB_TRACKING || !window.OCB_TRACKING.ajaxUrl) return;
            var channel = target.getAttribute('data-ocb-track');
            if (['call', 'call_north', 'call_central', 'call_south', 'zalo', 'messenger'].indexOf(channel) === -1) return;
            var body = new URLSearchParams();
            body.set('action', 'ocb_track_click');
            body.set('nonce', window.OCB_TRACKING.nonce || '');
            body.set('channel', channel);
            // keepalive lets the click be recorded even when the browser leaves the page.
            fetch(window.OCB_TRACKING.ajaxUrl, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).catch(function () {});
        }, true);
    }
    function boot() {
        trackContactClicks();
        document.querySelectorAll('.ocb-root').forEach(init);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
    // Shortcodes inserted later by page builders can initialize safely.
    if ('MutationObserver' in window) {
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('.ocb-root')) init(node);
                    if (node.querySelectorAll) node.querySelectorAll('.ocb-root').forEach(init);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
}());
