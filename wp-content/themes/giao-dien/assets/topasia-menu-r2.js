/* Top Asia — Navigation behavior (desktop dropdown/mega + mobile accordion drawer).
 * No dependencies. GTM dataLayer events preserved:
 * main_menu_click / menu_dropdown_open / quote_cta_click / guide_card_click / support_request_click.
 */
(function () {
  'use strict';

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

    function positionPanel(li) {
      var panel = li.querySelector(':scope > .ta-dd-panel');
      if (!panel) return;
      li.classList.remove('ta-dd-flip');
      panel.style.left = '';
      var r = panel.getBoundingClientRect();
      var vw = document.documentElement.clientWidth;
      if (r.right > vw - 10) {
        li.classList.add('ta-dd-flip');
        r = panel.getBoundingClientRect();
        if (r.left < 10) {
          li.classList.remove('ta-dd-flip');
          var shift = Math.max(10 - r.left, vw - 10 - panel.offsetWidth - li.getBoundingClientRect().left);
          panel.style.left = shift + 'px';
        }
      }
    }

    function openItem(li) {
      if (li.classList.contains('ta-open')) return;
      closeAll(li);
      li.classList.add('ta-open');
      var t = li.querySelector(':scope > .ta-dd-toggle');
      if (t) t.setAttribute('aria-expanded', 'true');
      positionPanel(li);
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
        var isLink = toggle.tagName === 'A' && toggle.getAttribute('href');
        var hoverCapable = window.matchMedia && window.matchMedia('(hover: hover)').matches;
        if (isLink) {
          if (!hoverCapable && !li.classList.contains('ta-open')) {
            e.preventDefault();
            openItem(li);
          }
          return;
        }
        e.preventDefault();
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
          if (li.classList.contains('ta-open')) closeItem(li);
        }
      });

      li.addEventListener('focusout', function (e) {
        if (!li.contains(e.relatedTarget)) closeItem(li);
      });

      panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeItem(li); toggle.focus(); }
      });
    });

    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target)) closeAll(null);
    });

    nav.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-ta-menu],a[data-ta-cta],a[data-ta-guide]');
      if (!a || !nav.contains(a)) return;
      var level = a.closest('.ta-subs') ? 3 : (a.closest('.ta-dd-panel') ? 2 : 1);
      if (a.hasAttribute('data-ta-cta')) track('quote_cta_click', menuMeta(a, level, 'desktop', 'primary'));
      else if (a.hasAttribute('data-ta-guide')) track('guide_card_click', menuMeta(a, 2, 'desktop', 'primary'));
      else track('main_menu_click', menuMeta(a, level, 'desktop', 'primary'));
    });
  }

  function initMobile(nav) {
    var accs = nav.querySelectorAll('.ta-macc');

    function closeAcc(li) {
      var t = li.querySelector('.ta-mtoggle');
      var p = li.querySelector('.ta-mpanel');
      li.classList.remove('ta-open');
      if (t) t.setAttribute('aria-expanded', 'false');
      if (p) p.setAttribute('hidden', '');
    }

    function openAcc(li, silent) {
      accs.forEach(function (o) {
        if (o !== li && !o.hasAttribute('data-ta-default-open')) {
          closeAcc(o);
        }
      });
      var t = li.querySelector('.ta-mtoggle');
      var p = li.querySelector('.ta-mpanel');
      li.classList.add('ta-open');
      if (t) t.setAttribute('aria-expanded', 'true');
      if (p) p.removeAttribute('hidden');
      if (!silent) {
        var label = t ? (t.getAttribute('data-ta-menu') || t.textContent || '').trim() : '';
        track('menu_dropdown_open', { menu_item: label, menu_level: 1, device_type: 'mobile', menu_location: 'primary_mobile' });
      }
    }

    accs.forEach(function (li) {
      var toggle = li.querySelector('.ta-mtoggle');
      if (!toggle) return;
      toggle.addEventListener('click', function () {
        if (li.classList.contains('ta-open')) { closeAcc(li); } else { openAcc(li, false); }
      });
    });

    function scrollDrawerTop() {
      var els = [nav.closest('.sidebar-menu'), nav.closest('.mobile-sidebar'), nav.closest('.mfp-content'), nav.closest('.mfp-wrap')];
      els.forEach(function (el) { if (el) { try { el.scrollTop = 0; } catch (e) {} } });
    }

    function resetDrawer() {
      scrollDrawerTop();
      accs.forEach(function (li) {
        if (li.hasAttribute('data-ta-default-open') || li.classList.contains('is-active')) {
          openAcc(li, true);
        } else {
          closeAcc(li);
        }
      });
    }

    function closeOffCanvas() {
      var drawer = document.getElementById('ta-cmm-drawer');
      var backdrop = document.getElementById('ta-cmm-backdrop');
      var openBtn = document.getElementById('ta-cmm-open');
      if (drawer && !drawer.hidden) {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        if (backdrop) backdrop.hidden = true;
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        document.documentElement.style.overflow = '';
        return;
      }
      try {
        if (window.jQuery && window.jQuery.magnificPopup && window.jQuery.magnificPopup.instance && window.jQuery.magnificPopup.instance.isOpen) {
          window.jQuery.magnificPopup.close();
          return;
        }
      } catch (e) {}
      var btn = document.querySelector('.mfp-wrap .mfp-close');
      if (btn) btn.click();
    }

    function openCustomDrawer() {
      var drawer = document.getElementById('ta-cmm-drawer');
      var backdrop = document.getElementById('ta-cmm-backdrop');
      var openBtn = document.getElementById('ta-cmm-open');
      if (!drawer || !backdrop) return;
      resetDrawer();
      backdrop.hidden = false;
      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
      document.documentElement.style.overflow = 'hidden';
    }

    nav.addEventListener('click', function (e) {
      var close = e.target.closest('[data-ta-close]');
      if (close && nav.contains(close)) { closeOffCanvas(); }
    });

    var cmmOpen = document.getElementById('ta-cmm-open');
    var cmmBackdrop = document.getElementById('ta-cmm-backdrop');
    if (cmmOpen && nav.closest('#ta-cmm-drawer')) {
      cmmOpen.addEventListener('click', openCustomDrawer);
      if (cmmBackdrop) cmmBackdrop.addEventListener('click', closeOffCanvas);
      document.addEventListener('keydown', function (e) {
        var drawer = document.getElementById('ta-cmm-drawer');
        if (e.key === 'Escape' && drawer && !drawer.hidden) closeOffCanvas();
      });
      nav.querySelectorAll('a[href*="#"]').forEach(function (a) {
        a.addEventListener('click', closeOffCanvas);
      });
    }

    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('a[data-open="#main-menu"]')) resetDrawer();
    });

    nav.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-ta-menu],a[data-ta-cta],a[data-ta-guide],a[data-ta-support]');
      if (!a || !nav.contains(a)) return;
      var inSub = !!a.closest('.ta-mpanel');
      var level = a.closest('.ta-msubs') ? 3 : (inSub ? 2 : 1);
      if (a.hasAttribute('data-ta-support')) track('support_request_click', menuMeta(a, 2, 'mobile', 'primary_mobile'));
      else if (a.hasAttribute('data-ta-cta')) track('quote_cta_click', menuMeta(a, level, 'mobile', 'primary_mobile'));
      else if (a.hasAttribute('data-ta-guide')) track('guide_card_click', menuMeta(a, 2, 'mobile', 'primary_mobile'));
      else track('main_menu_click', menuMeta(a, level, 'mobile', 'primary_mobile'));
    });
  }

  function normalizeStagingLinks(root) {
    if (!/^\/staging(?:\/|$)/.test(window.location.pathname)) return;
    var scope = root || document;
    var links = [];
    if (scope.nodeType === 1 && scope.matches && scope.matches('a[href]')) links.push(scope);
    if (scope.querySelectorAll) {
      Array.prototype.forEach.call(scope.querySelectorAll('a[href]'), function (a) { links.push(a); });
    }
    links.forEach(function (a) {
      var url;
      try { url = new URL(a.getAttribute('href'), window.location.href); } catch (e) { return; }
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
      var host = url.hostname.toLowerCase().replace(/^www\./, '');
      var currentHost = window.location.hostname.toLowerCase().replace(/^www\./, '');
      if (host !== currentHost && host !== 'topasia.vn') return;
      if (/^\/staging(?:\/|$)/.test(url.pathname)) return;
      if (/^\/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:\/|$)/i.test(url.pathname)) return;
      url.pathname = '/staging' + (url.pathname === '/' ? '/' : url.pathname);
      a.setAttribute('href', url.pathname + url.search + url.hash);
    });
  }

  function init() {
    document.querySelectorAll('.ta-nav').forEach(initDesktop);
    document.querySelectorAll('.ta-mnav').forEach(initMobile);
    normalizeStagingLinks(document);
    if (window.MutationObserver && document.body) {
      var observer = new MutationObserver(function (records) {
        records.forEach(function (record) {
          Array.prototype.forEach.call(record.addedNodes, function (node) {
            if (node.nodeType === 1) {
              normalizeStagingLinks(node);
            }
          });
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
