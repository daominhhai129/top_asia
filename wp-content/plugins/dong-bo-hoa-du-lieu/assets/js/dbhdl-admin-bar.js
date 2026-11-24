/* Admin Bar Mobile Sheet Controller */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const node = document.getElementById('wp-admin-bar-dbhdl-sync-hub');
        const sheet = document.getElementById('dbhdl-mobile-sheet');
        const backdrop = document.getElementById('dbhdl-sheet-backdrop');
        const closeBtn = document.getElementById('dbhdl-sheet-close');

        if (!node || !sheet || !backdrop) return;

        const open = () => {
            sheet.classList.add('is-open');
            backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };
        const close = () => {
            sheet.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
        };

        node.addEventListener('click', (e) => {
            if (window.innerWidth <= 782) {
                e.preventDefault();
                e.stopPropagation();
                open();
            }
        });
        backdrop.addEventListener('click', close);
        if (closeBtn) closeBtn.addEventListener('click', close);
    });
})();
