/* Top Asia homepage — interactivity (icons, product tabs, testimonial carousel, quote dialog). */
(function () {
  'use strict';
  function ready(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();

    /* Product series tabs */
    document.querySelectorAll('.taf-tabs').forEach(function (tabs) {
      var sec = tabs.closest('section');
      var panels = sec ? sec.querySelectorAll('.taf-tabpanel') : [];
      tabs.querySelectorAll('.taf-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
          tabs.querySelectorAll('.taf-tab').forEach(function (b) { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
          btn.classList.add('is-active'); btn.setAttribute('aria-selected', 'true');
          var id = btn.getAttribute('data-tab');
          panels.forEach(function (p) { p.style.display = (p.getAttribute('data-panel') === id) ? '' : 'none'; });
        });
      });
    });

    /* Testimonial carousel */
    document.querySelectorAll('.taf-reviews').forEach(function (box) {
      var reviews = box.querySelectorAll('.taf-review'), dots = box.querySelectorAll('.taf-rev-dot'), total = reviews.length, cur = 0;
      function show(n) {
        cur = (n + total) % total;
        reviews.forEach(function (r, i) { r.style.display = (i === cur) ? '' : 'none'; });
        dots.forEach(function (d, i) { d.style.width = (i === cur) ? '24px' : '8px'; d.style.background = (i === cur) ? 'var(--red-600)' : 'var(--gray-300)'; });
      }
      box.addEventListener('click', function (e) {
        if (e.target.closest('.taf-rev-next')) show(cur + 1);
        else if (e.target.closest('.taf-rev-prev')) show(cur - 1);
        else { var dot = e.target.closest('.taf-rev-dot'); if (dot) show(parseInt(dot.getAttribute('data-i'), 10)); }
      });
    });

    /* Hero slider — autoplay + kéo/vuốt + dots + arrows */
    document.querySelectorAll('[data-taf-hero]').forEach(function (hero) {
      var track = hero.querySelector('.taf-hero-track');
      var slides = hero.querySelectorAll('.taf-hero-slide');
      var dots = hero.querySelectorAll('.taf-hero-dot');
      var total = slides.length, cur = 0, timer = null, AUTO = 6000;
      if (!track || total < 2) return;
      function go(n) {
        cur = (n + total) % total;
        track.style.transform = 'translateX(' + (-cur * 100) + '%)';
        dots.forEach(function (d, i) { d.classList.toggle('is-active', i === cur); });
      }
      function stop() { if (timer) { clearInterval(timer); timer = null; } }
      function start() { stop(); timer = setInterval(function () { go(cur + 1); }, AUTO); }
      hero.addEventListener('click', function (e) {
        if (e.target.closest('.taf-hero-next')) { go(cur + 1); start(); }
        else if (e.target.closest('.taf-hero-prev')) { go(cur - 1); start(); }
        else { var dot = e.target.closest('.taf-hero-dot'); if (dot) { go(parseInt(dot.getAttribute('data-i'), 10)); start(); } }
      });
      hero.addEventListener('mouseenter', stop);
      hero.addEventListener('mouseleave', start);
      /* kéo / vuốt — bỏ qua khi bấm nút/link để không cản CTA */
      var down = false, sx = 0, dx = 0, w = 0;
      function pd(e) {
        if (e.target.closest('.taf-hero-arrow,.taf-hero-dot,a,button')) return;
        down = true; sx = (e.touches ? e.touches[0].clientX : e.clientX); dx = 0;
        w = hero.offsetWidth || 1; hero.classList.add('is-dragging'); stop();
      }
      function pm(e) {
        if (!down) return;
        var x = (e.touches ? e.touches[0].clientX : e.clientX); dx = x - sx;
        track.style.transform = 'translateX(calc(' + (-cur * 100) + '% + ' + dx + 'px))';
      }
      function pu() {
        if (!down) return;
        down = false; hero.classList.remove('is-dragging');
        if (Math.abs(dx) > w * 0.12) go(cur + (dx < 0 ? 1 : -1)); else go(cur);
        dx = 0; start();
      }
      hero.addEventListener('mousedown', pd);
      window.addEventListener('mousemove', pm);
      window.addEventListener('mouseup', pu);
      hero.addEventListener('touchstart', pd, { passive: true });
      hero.addEventListener('touchmove', pm, { passive: true });
      hero.addEventListener('touchend', pu);
      go(0); start();
    });

    /* Toast */
    function showToast(msg) {
      var t = document.getElementById('taf-toast'); if (!t) return;
      t.querySelector('.taf-toast-msg').textContent = msg;
      t.style.display = 'flex';
      clearTimeout(t._timer); t._timer = setTimeout(function () { t.style.display = 'none'; }, 3200);
    }

    /* Quote dialog */
    var dlg = document.getElementById('taf-quote');
    function openDlg() { if (dlg) { dlg.style.display = 'flex'; document.body.style.overflow = 'hidden'; var inp = dlg.querySelector('input'); if (inp) inp.focus(); } }
    function closeDlg() { if (dlg) { dlg.style.display = 'none'; document.body.style.overflow = ''; } }
    document.querySelectorAll('[data-taf-quote]').forEach(function (b) { b.addEventListener('click', openDlg); });
    if (dlg) {
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-taf-close]')) closeDlg(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dlg.style.display === 'flex') closeDlg(); });
      var submit = dlg.querySelector('[data-taf-submit]');
      if (submit) submit.addEventListener('click', function () { closeDlg(); showToast('Đã gửi yêu cầu — Top Asia sẽ liên hệ trong 24h'); });
    }

  });
})();
