// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hides Moodle navigation chrome (drawer, header, breadcrumb) during a
 * proctored attempt. Runs on load and again after short delays, plus a
 * MutationObserver, to override any Moodle JS that reopens the drawer.
 *
 * The equivalent immediate-paint CSS lives in utils::get_hide_chrome_css()
 * (a <style> block, injected separately) — this module only handles the
 * JS-driven re-hide behaviour that CSS alone can't cover.
 *
 * @module     availability_proctor/hidechrome
 * @copyright  2019-2026 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var HIDE = [
        '#nav-drawer', '[data-region="drawer"]', '[data-region="fixed-drawer"]',
        '.drawer', '.drawer-left', '.drawer-left-toggle', '.drawer-toggles',
        'button[data-toggler="drawers"]', '.drawercontent',
        '#page-header', 'header#page-header', 'header.navbar', 'nav.navbar', '.navbar',
        '#page-navbar', '.secondary-navigation', '.tertiary-navigation',
        '.activity-navigation', '[data-region="blocks-column"]'
    ];

    var FIX = ['#page', '#page-wrapper', '#page-content', '.main-inner'];

    /**
     * Hide the chrome elements and reset the drawer-open body classes.
     */
    var hide = function() {
        HIDE.forEach(function(selector) {
            try {
                document.querySelectorAll(selector).forEach(function(el) {
                    el.style.setProperty('display', 'none', 'important');
                    el.style.setProperty('visibility', 'hidden', 'important');
                });
            } catch (e) {
                // Selector unsupported in this browser; skip it.
            }
        });

        if (document.body) {
            document.body.classList.remove('drawer-open-left', 'drawer-open-right');
        }

        FIX.forEach(function(selector) {
            try {
                var el = document.querySelector(selector);
                if (el) {
                    el.style.setProperty('margin-left', '0', 'important');
                    el.style.setProperty('padding-left', '0', 'important');
                    el.style.setProperty('margin-top', '0', 'important');
                    el.style.setProperty('padding-top', '0', 'important');
                    el.style.setProperty('width', '100%', 'important');
                    el.style.setProperty('max-width', '100%', 'important');
                }
            } catch (e) {
                // Selector unsupported in this browser; skip it.
            }
        });
    };

    return {
        /**
         * Initialise chrome-hiding: hide immediately, again after short
         * delays to beat any Moodle JS that reopens the drawer, and keep
         * watching the DOM for further changes.
         */
        init: function() {
            hide();

            var start = function() {
                hide();
                setTimeout(hide, 300);
                setTimeout(hide, 800);
                setTimeout(hide, 2000);

                var observer = new MutationObserver(function() {
                    hide();
                });
                observer.observe(document.documentElement, {childList: true, subtree: true});
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start);
            } else {
                start();
            }
        }
    };
});
