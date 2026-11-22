/* Top Asia — navigation behavior (desktop dropdown/mega + mobile drawer).
 * No dependencies. Spec: topasia-menu-implementation.md §14–16, §19.
 * Mobile redesigned 2026-07 theo mockup "Mobile menu Top Asia":
 * - Main screen + drill-down sub-screens (Sản phẩm / Hỗ trợ kỹ thuật) with back bar.
 * - Inline one-level accordion for small groups (Giới thiệu / Hợp tác).
 * - Own close button (falls back to Flatsome/Magnific off-canvas close).
 * GTM dataLayer events preserved: main_menu_click / menu_dropdown_open /
 * quote_cta_click / guide_card_click / support_request_click.
 */
(function () {
  'use strict';

  /* ----------------------------- analytics ----------------------------- */
  function track(event, params) {
    try {
      if (window.dataLayer && typeof window.dataLayer.push === 'function') {
        window.dataLayer.push(Object.assign({ event: event }, params));
      } else if (typeof window.gtag === 'function') {
        window.gtag('event', event, params);
      }
    } catch (e) {}
  }

  function menuMeta(el, level, device, location) {
    return {
      menu_item: el.getAttribute('data-ta-menu') || (el.textContent || '').trim(),
      destination_url: el.getAttribute('href') || '',
      menu_level: level,
      device_type: device,
      menu_location: location
    };
  }

  /* ----------------------------- desktop ------------------------------- */
  function initDesktop(nav) {
    var items = nav.querySelectorAll('.ta-has-dd');

    function closeItem(li) {
      if (!li.classList.contains('ta-open')) return;
      li.classList.remove('ta-open');
      var t = li.querySelector(':scope > .ta-dd-toggle');
      if (t) t.setAttribute('aria-expanded', 'false');
    }
    function closeAll(except) {
      items.forEach(function (li) { if (li !== except) closeItem(li); });
    }
    function openItem(li) {
      if (li.classList.contains('ta-open')) return;
      closeAll(li);
      li.classList.add('ta-open');
      var t = li.querySelector(':scope > .ta-dd-toggle');
      if (t) t.setAttribute('aria-expanded', 'true');
      // Flip panel to the right edge if it would overflow the viewport (spec §14).
      var panel = li.querySelector(':scope > .ta-dd-panel');
      if (panel) {
        li.classList.remove('ta-dd-flip');
        var r = panel.getBoundingClientRect();
        var vw = document.documentElement.clientWidth;
        if (r.right > vw - 8) li.classList.add('ta-dd-flip');
      }
      var label = t ? (t.textContent || '').trim() : '';
      track('menu_dropdown_open', { menu_item: label, menu_level: 1, device_type: 'desktop', menu_location: 'primary' });
    }

    items.forEach(function (li) {
      var toggle = li.querySelector(':scope > .ta-dd-toggle');
      var panel = li.querySelector(':scope > .ta-dd-panel');
      if (!toggle || !panel) return;
      var closeTimer;

      li.addEventListener('mouseenter', function () { clearTimeout(closeTimer); openItem(li); });
      li.addEventListener('mouseleave', function () {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(function () { closeItem(li); }, 160);
      });

      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        // On hover-capable devices a real mouse click follows a hover-open, so
        // keep it open instead of toggling closed. Keyboard clicks (detail 0)
        // and touch (no hover) still toggle.
        var hoverCapable = window.matchMedia('(hover: hover)').matches;
        if (hoverCapable && e.detail > 0) { openItem(li); return; }
        if (li.classList.contains('ta-open')) { closeItem(li); } else { openItem(li); }
      });

      toggle.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          openItem(li);
          var first = panel.querySelector('a');
          if (first) first.focus();
        } else if (e.key === 'Escape') {
          if (li.classList.contains('ta-open')) { closeItem(li); }
        }
      });

      // Close when keyboard focus leaves the item entirely.
      li.addEventListener('focusout', function (e) {
        if (!li.contains(e.relatedTarget)) closeItem(li);
      });

      panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeItem(li); toggle.focus(); }
      });
    });

    // Click outside closes all.
    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target)) closeAll(null);
    });

    // Analytics on link clicks within desktop nav.
    nav.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-ta-menu],a[data-ta-cta],a[data-ta-guide]');
      if (!a || !nav.contains(a)) return;
      var level = a.closest('.ta-dd-panel') ? 2 : 1;
      if (a.hasAttribute('data-ta-cta')) track('quote_cta_click', menuMeta(a, level, 'desktop', 'primary'));
      else if (a.hasAttribute('data-ta-guide')) track('guide_card_click', menuMeta(a, 2, 'desktop', 'primary'));
      else track('main_menu_click', menuMeta(a, level, 'desktop', 'primary'));
    });
  }

  /* ------------------------------ mobile ------------------------------- */
  function initMobile(nav) {
    var accs = nav.querySelectorAll('.ta-macc');
    var views = nav.querySelectorAll('[data-ta-mview]');

    /* --- one-level accordion (Giới thiệu / Hợp tác) --- */
    function closeAcc(li) {
      var t = li.querySelector('.ta-mtoggle');
      var p = li.querySelector('.ta-mpanel');
      li.classList.remove('ta-open');
      if (t) t.setAttribute('aria-expanded', 'false');
      if (p) p.setAttribute('hidden', '');
    }
    function openAcc(li) {
      accs.forEach(function (o) { if (o !== li) closeAcc(o); });
      var t = li.querySelector('.ta-mtoggle');
      var p = li.querySelector('.ta-mpanel');
      li.classList.add('ta-open');
      if (t) t.setAttribute('aria-expanded', 'true');
      if (p) p.removeAttribute('hidden');
      var label = t ? (t.getAttribute('data-ta-menu') || t.textContent || '').trim() : '';
      track('menu_dropdown_open', { menu_item: label, menu_level: 1, device_type: 'mobile', menu_location: 'primary_mobile' });
    }
    accs.forEach(function (li) {
      var toggle = li.querySelector('.ta-mtoggle');
      if (!toggle) return;
      toggle.addEventListener('click', function () {
        if (li.classList.contains('ta-open')) { closeAcc(li); } else { openAcc(li); }
      });
    });

    /* --- drill-down screens (Sản phẩm / Hỗ trợ kỹ thuật) --- */
    function scrollDrawerTop() {
      var els = [nav.closest('.sidebar-menu'), nav.closest('.mobile-sidebar'), nav.closest('.mfp-content'), nav.closest('.mfp-wrap')];
      els.forEach(function (el) { if (el) { try { el.scrollTop = 0; } catch (e) {} } });
    }
    function showView(key, dir) {
      var target = null;
      views.forEach(function (v) {
        var isTarget = v.getAttribute('data-ta-mview') === key;
        v.classList.remove('ta-anim-fwd', 'ta-anim-back');
        if (isTarget) target = v;
        if (isTarget) { v.removeAttribute('hidden'); } else { v.setAttribute('hidden', ''); }
        v.classList.toggle('is-current', isTarget);
      });
      if (!target) return;
      scrollDrawerTop();
      if (dir) {
        void target.offsetWidth; // restart CSS animation
        target.classList.add(dir === 'back' ? 'ta-anim-back' : 'ta-anim-fwd');
      }
      if (key !== 'main') {
        var back = target.querySelector('.ta-mback');
        if (back) { try { back.focus({ preventScroll: true }); } catch (e) {} }
      }
    }
    function resetDrawer() {
      showView('main');
      accs.forEach(closeAcc);
    }

    function closeOffCanvas() {
      try {
        if (window.jQuery && window.jQuery.magnificPopup && window.jQuery.magnificPopup.instance && window.jQuery.magnificPopup.instance.isOpen) {
          window.jQuery.magnificPopup.close();
          return;
        }
      } catch (e) {}
      var btn = document.querySelector('.mfp-wrap .mfp-close');
      if (btn) btn.click();
    }

    nav.addEventListener('click', function (e) {
      var drill = e.target.closest('[data-ta-view]');
      if (drill && nav.contains(drill)) {
        showView(drill.getAttribute('data-ta-view'), 'fwd');
        track('menu_dropdown_open', {
          menu_item: drill.getAttribute('data-ta-menu') || (drill.textContent || '').trim(),
          menu_level: 1,
          device_type: 'mobile',
          menu_location: 'primary_mobile'
        });
        return;
      }
      var back = e.target.closest('[data-ta-back]');
      if (back && nav.contains(back)) { showView('main', 'back'); return; }
      var close = e.target.closest('[data-ta-close]');
      if (close && nav.contains(close)) { closeOffCanvas(); }
    });

    // Reopen at the main screen (collapsed) whenever the hamburger is used.
    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('a[data-open="#main-menu"]')) resetDrawer();
    });

    /* --- click analytics (event names preserved) --- */
    nav.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-ta-menu],a[data-ta-cta],a[data-ta-guide],a[data-ta-support]');
      if (!a || !nav.contains(a)) return;
      var view = a.closest('.ta-mview');
      var inSub = a.closest('.ta-mpanel') || (view && !view.classList.contains('ta-mview--main'));
      var level = inSub ? 2 : 1;
      if (a.hasAttribute('data-ta-support')) track('support_request_click', menuMeta(a, 2, 'mobile', 'primary_mobile'));
      else if (a.hasAttribute('data-ta-cta')) track('quote_cta_click', menuMeta(a, level, 'mobile', 'primary_mobile'));
      else if (a.hasAttribute('data-ta-guide')) track('guide_card_click', menuMeta(a, 2, 'mobile', 'primary_mobile'));
      else track('main_menu_click', menuMeta(a, level, 'mobile', 'primary_mobile'));
    });
  }

  /* ------------------------------- init -------------------------------- */
  function init() {
    document.querySelectorAll('.ta-nav').forEach(initDesktop);
    document.querySelectorAll('.ta-mnav').forEach(initMobile);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
