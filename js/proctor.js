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
 * Runs the Constructor Proctor SDK on the launch page and drives the quiz iframe.
 *
 * This is a plain ES module, NOT an AMD module. It is imported by a
 * script type="module" tag in launch.mustache and never goes through Moodle's
 * RequireJS pipeline, which cannot load the remote SDK module.
 *
 * It therefore lives in js/ rather than amd/src/, and that location is
 * load-bearing rather than a convention. Moodle scans amd/src/ itself when it
 * assembles lib/amd/build/first.min.js, so a file placed there is concatenated
 * into that bundle whatever the build configuration says. The dynamic import()
 * below is invalid AMD, so while this lived under amd/src/ the bundle failed to
 * parse on every page of the site -- and because core draws its navigation from
 * that bundle, the primary navigation and user menu vanished site-wide. Keeping
 * it off the Makefile's AMD_MODULES list is not sufficient on its own.
 *
 * The one rule this file exists to honour: never navigate this page. Reloading it
 * costs the student every preparation step again and leaves a gap in the
 * recording, so all state changes happen inside the iframe or via fetch().
 *
 * There is exactly one exception, and it is the end of the flow rather than a
 * state change: once videoUploaded has resolved and the attempt is closed, the
 * student is sent to the review page. By then there are no preparation steps
 * left to lose and no recording left to interrupt.
 *
 * @module     availability_proctor/proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {number} Milliseconds to wait for the token fetch and SDK init before giving up. */
const START_TIMEOUT_MS = 30000;

/** @type {boolean} Whether a session is live, so the page must not be left. */
let sessionRunning = false;

/** @type {boolean} Whether the video upload has finished. */
let videoUploaded = false;

/** @type {boolean} Whether beginning a session is already in progress, to ignore extra clicks. */
let starting = false;

/** @type {boolean} Whether the beforeunload guard has already been registered. */
let unloadGuardAdded = false;

/** @type {boolean} Whether this session must move into Constructor Secure Browser. */
let secureBrowserSession = false;

/**
 * Tell the server what the SDK did.
 *
 * Failures are swallowed on purpose. These calls are a forensic record, not a
 * control: the states that matter for access are set server-side, so a lost
 * report must never interrupt an exam in progress.
 *
 * @param {string} eventUrl Endpoint recording session events.
 * @param {string} event One of examstarted, examfinished, videouploaded, appunmounted.
 * @returns {Promise<object>} The endpoint's reply, or an empty object if it could not be read.
 */
const report = async(eventUrl, event) => {
    try {
        const response = await fetch(`${eventUrl}&event=${encodeURIComponent(event)}`, {
            method: 'POST',
            // Same-origin credentials so the Moodle session cookie is sent; the
            // endpoint requires a logged-in user and a valid sesskey.
            credentials: 'same-origin',
        });

        return await response.json();
    } catch (e) {
        window.console.error(`proctor-sdk: could not report ${event}`, e);

        // An empty reply, so a caller reading a property off this gets undefined
        // rather than throwing. A lost report is not worth breaking anything for.
        return {};
    }
};

/**
 * Show a failure to the student.
 *
 * The lead sentence and the two requirement lines are always shown; the
 * technical cause goes in a collapsed details block so a student sees something
 * actionable while support can still read the real error. Focus moves to the
 * alert so a screen-reader user is told a failure happened.
 *
 * @param {HTMLElement} root The app container.
 * @param {string} message Technical detail, shown collapsed.
 * @param {boolean} showRequirements Whether to show the browser/camera requirement lines.
 */
const showError = (root, message, showRequirements) => {
    const box = root.querySelector('#proctor-sdk-error');
    const lead = box.querySelector('#proctor-sdk-error-lead');
    const requirements = box.querySelector('#proctor-sdk-error-requirements');
    const detail = box.querySelector('#proctor-sdk-error-detail');

    lead.textContent = root.dataset.errorlead;

    requirements.innerHTML = '';
    if (showRequirements) {
        [root.dataset.requirementBrowser, root.dataset.requirementCamera].forEach((text) => {
            if (!text) {
                return;
            }
            const item = document.createElement('li');
            item.textContent = text;
            requirements.appendChild(item);
        });
    }

    if (message) {
        detail.querySelector('summary').textContent = root.dataset.errortechnical;
        detail.querySelector('pre').textContent = message;
        detail.classList.remove('proctor-sdk-hidden');
    } else {
        detail.classList.add('proctor-sdk-hidden');
    }

    box.classList.remove('proctor-sdk-hidden');
    box.focus();
};

/**
 * Fetch the signed JWT and the integration parameters.
 *
 * @param {string} tokenUrl Endpoint returning the proctoring data.
 * @param {AbortSignal} signal Aborts the request past the start timeout.
 * @param {string} serverError Message for a response that carries no error of its own.
 * @returns {Promise<object>} serverOrigin, integrationName, jwt and config.
 */
const getProctoringData = async(tokenUrl, signal, serverError) => {
    const response = await fetch(tokenUrl, {credentials: 'same-origin', signal});
    const body = await response.json();

    // AJAX_SCRIPT endpoints report failures as JSON with an error property, and
    // that message is already written for the student, so it is preferred over a
    // bare status code.
    if (!response.ok || body.error) {
        // The status code is appended rather than translated: it is a number,
        // and it is the one thing that makes an otherwise generic failure
        // diagnosable from a screenshot.
        throw new Error(body.error || `${serverError} (${response.status})`);
    }

    return body;
};

/**
 * Warn the student before they close the page while a session is running.
 *
 * The SDK shows its own confirmation during an active session, but stops once
 * examFinished resolves -- which is exactly when the video may still be
 * uploading. This covers that window: leaving then can discard recorded video
 * that has not reached the server.
 *
 * @param {BeforeUnloadEvent} e The event.
 * @returns {string|undefined}
 */
const confirmLeaving = (e) => {
    if (!sessionRunning || videoUploaded) {
        return undefined;
    }

    // Browsers ignore custom text here and show their own wording, but a value
    // must still be set for the prompt to appear at all.
    e.preventDefault();
    e.returnValue = '';
    return '';
};

/**
 * Start proctoring and wire the session lifecycle.
 *
 * @param {HTMLElement} root The app container, carrying the URLs as data attributes.
 */
export const start = (root) => {
    const {starturl: startUrl, tokenurl: tokenUrl, eventurl: eventUrl, sdksrc: sdkSrc} = root.dataset;

    const intro = root.querySelector('#proctor-sdk-intro');
    const frame = root.querySelector('#proctor-sdk-frame');
    const tail = root.querySelector('#proctor-sdk-tail');
    const errorBox = root.querySelector('#proctor-sdk-error');
    const recover = root.querySelector('#proctor-sdk-recover');
    const retry = root.querySelector('#proctor-sdk-retry');

    /**
     * Start proctoring and wire the session lifecycle.
     *
     * Called on load rather than from a button: core already asked for confirmation
     * on the quiz view page before sending the student here, so a second prompt
     * would be the same question twice.
     *
     * @returns {Promise<void>}
     */
    const begin = async() => {
        // A student who double-clicks Retry, or clicks it again before the first
        // attempt has failed or succeeded, must not mint two sessions at once.
        if (starting) {
            return;
        }
        starting = true;

        recover.classList.add('proctor-sdk-hidden');
        errorBox.classList.add('proctor-sdk-hidden');
        intro.classList.remove('proctor-sdk-hidden');
        intro.setAttribute('aria-busy', 'true');

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), START_TIMEOUT_MS);

        let session;
        try {
            const proctoring = await import(/* webpackIgnore: true */ sdkSrc);
            const {
                serverOrigin,
                integrationName,
                jwt,
                config,
                secureBrowser,
                secureBrowserLaunchUrl,
            } =
                await getProctoringData(tokenUrl, controller.signal, root.dataset.errorserver);

            // Secure Browser deliberately replaces this browser page with the
            // native application. Treating that hand-off as an accidental page
            // close produces Chrome's "Leave site?" prompt and blocks the app
            // launch. The native application owns the exam after the hand-off,
            // so the page-level guard is only appropriate for ordinary SDK
            // sessions that remain in this browser.
            secureBrowserSession = Boolean(secureBrowser);

            // init() is the only part of the SDK that rejects: an invalid or spent
            // JWT, no proctoring capacity, an exam already final, or an
            // unreachable serverOrigin. A blocked CORS origin also surfaces here,
            // and is the most likely cause on a new deployment.
            //
            // config's entries are spread as top-level keys, never passed as a
            // nested config property -- the Developer Guide is explicit that the
            // SDK does not read a nested config object. token.php already strips
            // it down to only the values that differ from the SDK's own
            // defaults, so an unconfigured quiz spreads nothing at all here.
            const initParams = {
                serverOrigin,
                integrationName,
                jwt,
                theme: 'default',
                ...config,
            };

            // AppDownload uses the current browser URL for the native launch
            // unless LDBLaunchUrl is present. The current page cannot work in
            // Secure Browser because its separate cookie store has no Moodle
            // login. Supply the learner- and session-bound one-time entry URL
            // generated by token.php; ordinary SDK sessions keep the SDK's
            // original navigation behavior.
            if (secureBrowserSession && secureBrowserLaunchUrl) {
                initParams.LDBLaunchUrl = secureBrowserLaunchUrl;
            }

            session = await proctoring.init(initParams);
        } catch (e) {
            window.console.error('proctor-sdk: init failed', e);
            const timedout = e && e.name === 'AbortError';
            intro.classList.add('proctor-sdk-hidden');
            intro.removeAttribute('aria-busy');
            showError(root, timedout ? root.dataset.timeoutmessage : (e.message || String(e)), true);
            // A failed start has to leave a way out, or the student is stranded on
            // a page with nothing on it.
            recover.classList.remove('proctor-sdk-hidden');
            starting = false;
            window.clearTimeout(timeout);
            return;
        }
        window.clearTimeout(timeout);

        // The SDK has taken over the screen, so the holding message would only sit
        // behind it.
        intro.classList.add('proctor-sdk-hidden');

        sessionRunning = true;
        starting = false;
        if (!secureBrowserSession && !unloadGuardAdded) {
            window.addEventListener('beforeunload', confirmLeaving);
            unloadGuardAdded = true;
        }

        // From here on the SDK owns the screen. Each promise resolves at most once
        // and none of them ever reject, so no catch handlers are needed -- and
        // there is no in-session error event to subscribe to.

        session.examStarted.then(() => {
            // Hand off to core's startattempt.php only now. It runs the pre-flight
            // chain -- quiz password, Safe Exam Browser -- creates the attempt, and
            // redirects to the questions, all inside this frame. Pointing the frame
            // here earlier would start the attempt, and its timer, before the
            // student had finished the SDK preparation steps.
            frame.src = startUrl;
            frame.classList.remove('proctor-sdk-hidden');
            return report(eventUrl, 'examstarted');
        });

        session.examFinished.then(() => {
            // Recording has stopped, but the attempt may still be open and the
            // student still needs to submit it. The iframe deliberately stays
            // usable: the SDK offers no way to intercept its own Finish exam
            // button, so hiding the quiz here would strand anyone who pressed it
            // early. The banner tells them what changed.
            tail.classList.remove('proctor-sdk-hidden');
            return report(eventUrl, 'examfinished');
        });

        session.videoUploaded.then(async() => {
            // Set before the report, not after: it releases the beforeunload
            // guard, and the redirect below must not prompt the student about
            // leaving a page whose recording is already safely uploaded.
            videoUploaded = true;

            const {returnurl: returnUrl} = await report(eventUrl, 'videouploaded');

            // The one navigation this page is allowed to make, and only here: the
            // exam is over and the recording is on the server, so there is nothing
            // left to lose by leaving. The server sends no URL at all while the
            // attempt is still open, which is the case that must keep the student
            // where they are. See session::return_url().
            if (returnUrl) {
                window.location.assign(returnUrl);
            }
        });

        session.appUnmounted.then(() => {
            // The SDK closed before the normal end of the flow, and does not say
            // whether that was deliberate or a crash. The quiz stays available for
            // the same reason as above; the student is told proctoring has stopped
            // so the silence is not mistaken for everything being fine, and Retry
            // is offered exactly as it would be after an init() failure.
            tail.classList.add('proctor-sdk-hidden');
            showError(root, '', false);
            root.querySelector('#proctor-sdk-error-lead').textContent = root.dataset.abandonedmessage;
            recover.classList.remove('proctor-sdk-hidden');
            sessionRunning = false;
            return report(eventUrl, 'appunmounted');
        });
    };

    retry.addEventListener('click', begin);

    // Wait for the page to finish loading before starting, and not merely for the
    // DOM. The SDK injects its own stylesheet when init() runs, and CSS of equal
    // specificity is decided by document order -- so a stylesheet Moodle is still
    // fetching would land after the SDK's and flatten its controls, leaving the
    // preparation steps with invisible checkboxes and buttons rendered as plain
    // text.
    //
    // Starting from a click used to guarantee this ordering for free, because by
    // then everything had loaded. Auto-starting removed that guarantee, which is
    // what broke the SDK's styling; waiting for the load event restores it without
    // bringing the extra click back.
    if (document.readyState === 'complete') {
        begin();
    } else {
        window.addEventListener('load', begin, {once: true});
    }
};
