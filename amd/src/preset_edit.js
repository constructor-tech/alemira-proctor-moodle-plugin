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
 * Live-uncheck the auto-suppressed warning when its corresponding allow-rule
 * checkbox is toggled on, on the preset edit admin page.
 *
 * @module     availability_proctor/preset_edit
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    // Moodle's advcheckbox renders TWO inputs sharing the same name — a hidden
    // fallback (value=0) and the visible checkbox. We must target the visible
    // one or .checked reads/writes apply to the hidden input and do nothing.
    var findCheckbox = function(name) {
        return document.querySelector('input[type="checkbox"][name="' + name + '"]');
    };

    return {
        /**
         * @param {Object} map rule-checkbox-name -> warning-checkbox-name pairs
         */
        init: function(map) {
            var sync = function() {
                Object.keys(map).forEach(function(rname) {
                    var rule = findCheckbox(rname);
                    var warn = findCheckbox(map[rname]);
                    if (!rule || !warn) {
                        return;
                    }
                    if (rule.checked) {
                        warn.checked = false;
                    }
                });
            };

            Object.keys(map).forEach(function(rname) {
                var rule = findCheckbox(rname);
                if (rule) {
                    rule.addEventListener('change', sync);
                }
            });

            sync();
        }
    };
});
