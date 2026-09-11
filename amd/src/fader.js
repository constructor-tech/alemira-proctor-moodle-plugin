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
 * Hide quiz/scorm/assign content behind an overlay until it's proven the
 * page is being proctored.
 *
 * Firstly, hide content with an overlay element. Then send a request to the
 * parent window, and wait for the answer. When got a proper answer, reveal
 * the content.
 *
 * We expect Proctor by Constructor to work only on fresh browsers, so we use
 * modern javascript here, without any regret or fear. Even if some old
 * browser breaks parsing or executing this, no other scripts are affected.
 *
 * @module     availability_proctor/fader
 * @copyright  2019-2026 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var TAG = 'proctoring fader';
    var EXPECTED_DATA = 'proctoringReady_n6EY';

    /**
     * Promise which resolves when we got a message proving the page is being proctored.
     *
     * @return {Promise}
     */
    var waitForProof = function() {
        return new Promise(function(resolve) {
            var messageHandler = function(e) {
                window.console.debug(TAG, 'got some message', e.data);

                if (EXPECTED_DATA === e.data) {
                    resolve();
                    window.console.debug(TAG, 'got proving message', e.data);
                    window.removeEventListener('message', messageHandler);
                }
            };

            window.addEventListener('message', messageHandler);
        });
    };

    /**
     * Prepare the element to cover the page content.
     *
     * @param {String} html
     * @return {HTMLElement}
     */
    var createFader = function(html) {
        var fader = document.createElement('div');

        fader.innerHTML = html;

        Object.assign(fader.style, {
            position: 'fixed',
            zIndex: 1000,
            fontSize: '2em',
            width: '100%',
            height: '100%',
            background: '#fff',
            top: 0,
            left: 0,
            textAlign: 'center',
            display: 'flex',
            justifyContent: 'center',
            alignContent: 'center',
            flexDirection: 'column',
        });

        document.body.appendChild(fader);

        return fader;
    };

    /**
     * Auto-submit a hidden form to redirect the learner into Proctor.
     *
     * @param {Object|null} formData {action, method, token}
     */
    var redirectToProctor = function(formData) {
        if (!formData) {
            return;
        }
        var form = document.createElement('form');
        var input = document.createElement('input');
        form.appendChild(input);
        document.body.appendChild(form);

        form.method = formData.method;
        form.action = formData.action;
        input.name = 'token';
        input.value = formData.token;
        form.submit();
    };

    /**
     * Config (strings/formData/reset) travels via a hidden element's
     * data-attribute rather than as init() arguments — combined with the
     * JWT in formData it can exceed js_call_amd()'s argument-size guidance.
     * See classes/utils.php::fader_amd_markup().
     *
     * @return {Object|null} {strings, formdata, reset}
     */
    var readConfig = function() {
        var el = document.getElementById('availability-proctor-fader-config');
        if (!el) {
            return null;
        }
        var config = JSON.parse(el.getAttribute('data-config'));
        el.remove();
        return config;
    };

    return {
        /**
         * Initialise the fader overlay.
         */
        init: function() {
            var config = readConfig();
            if (!config) {
                return;
            }
            var strings = config.strings;
            var formData = config.formdata;
            var reset = config.reset;
            var faderHTML = strings.awaitingProctoring + strings.instructions;

            // Prepare to catch the message early.
            var proved = waitForProof();

            var start = function() {
                var fader = createFader(faderHTML);

                var redirectTimeout = setTimeout(function() {
                    redirectToProctor(formData);
                }, 15000);

                proved.then(function() {
                    if (reset) {
                        fader.innerHTML = strings.reset;
                    } else {
                        fader.remove();
                    }
                    clearTimeout(redirectTimeout);
                });
            };

            // AMD modules can initialise after DOMContentLoaded has already
            // fired (RequireJS fetch/execution is asynchronous), in which case
            // an event listener for it would never run. Start immediately if
            // the DOM is already past the loading stage; only wait for the
            // event when the document is still loading.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start);
            } else {
                start();
            }
        }
    };
});
