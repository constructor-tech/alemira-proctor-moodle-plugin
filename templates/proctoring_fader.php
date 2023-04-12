<?php
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
 * Availability plugin for integration with Alemira proctoring system.
 *
 * @package    availability_alemira
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
?>
<script type="text/javascript">
/**
 * Hide quiz questions unless it's being proctored.
 *
 * Firstly, hide questions with an overlay element.
 * Then send request to the parent window,
 * and wait for the answer.
 *
 * When got a proper answer, then reveal the quiz content.
 *
 * We expect Alemira to work only on fresh browsers,
 * so we use modern javascript here, without any regret or fear.
 * Even if some old browser breaks parsing or executing this,
 * no other scripts will be affected.
 */
(function(){

const strAwaitingProctoring = <?php echo json_encode(get_string('fader_awaiting_proctoring', 'availability_alemira')) ?>;
const strInstructions = <?php echo json_encode(get_string('fader_instructions', 'availability_alemira')) ?>;
const strReset = <?php echo json_encode(get_string('fader_reset', 'availability_alemira')) ?>;
const faderHTML = strAwaitingProctoring + strInstructions;
const formData = <?php echo json_encode(isset($formdata) ? $formdata : null); ?>;
const noProtection = <?php echo $condition->noprotection ? 'true' : 'false' ?>;
const reset = <?php echo $entryreset ? 'true' : 'false' ?>;

const TAG = 'proctoring fader';
const expectedData = 'proctoringReady_n6EY';

/**
 * Promise, which resolves when got a message proving the page is being proctored.
 */
const waitForProof = () => new Promise(resolve => {
  const messageHandler = e => {
    console.debug(TAG, 'got some message', e.data);

    if (expectedData === e.data) {
      resolve();
      console.debug(TAG, 'got proving message', e.data);
      window.removeEventListener('message', messageHandler);
    }
  }

  window.addEventListener("message", messageHandler);
});

/**
 * Prepare the element to cover quiz contents.
 */
const createFader = (html) => {
  const fader = document.createElement("div");

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

const redirectToAlemira = () => {
  if (!formData) {
    return;
  }
  const form = document.createElement("form");
  const input = document.createElement("input")
  form.appendChild(input);
  document.body.appendChild(form);

  form.method = formData['method'];
  form.action = formData['action'];
  input.name = "token";
  input.value = formData['token'];
  form.submit();
}

/**
 * Run.
 */

/* Prepare to catch the message early. */
const proved = waitForProof();

window.addEventListener("DOMContentLoaded", () => {
    const fader = noProtection ? null : createFader(faderHTML);

    redirectTimeout = setTimeout(() => {
         redirectToAlemira();
    }, 15000);

    proved.then(() => {
        if (reset) {
            if (fader) {
                fader.innerHTML = strReset;
            } else {
                createFader(strReset);
            }
        } else {
            if (fader) {
                fader.remove();
            }
        }
        clearTimeout(redirectTimeout)
    });


});

})();
</script>
