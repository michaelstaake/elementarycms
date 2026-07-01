/**
 * Mobile navigation toggle.
 */
(function () {
    'use strict';

    var toggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.site-nav');

    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('is-open');
    });

    // Close mobile nav when a link inside is clicked
    nav.addEventListener('click', function (e) {
        if (e.target.tagName === 'A') {
            toggle.setAttribute('aria-expanded', 'false');
            nav.classList.remove('is-open');
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('is-open')) {
            toggle.setAttribute('aria-expanded', 'false');
            nav.classList.remove('is-open');
            toggle.focus();
        }
    });
})();
