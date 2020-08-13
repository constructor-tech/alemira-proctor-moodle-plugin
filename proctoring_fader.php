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
 * We expect Examus to work only on fresh browsers,
 * so we use modern javascript here, without any regret or fear.
 * Even if some old browser breaks parsing or executing this,
 * no other scripts will be affected.
 */
(function(){

var str_awaiting_proctoring = <?= json_encode(get_string('fader_awaiting_proctoring', 'availability_examus')) ?>;
var str_instructions = <?= json_encode(get_string('fader_instructions', 'availability_examus')) ?>;
//msg queue, inited ASAP, so we don't miss anything
var examus_q = [];

/*
 * Origin of sender, that is of the proctoring application.
 * We cache it in `sessionStorage`, so that if it occasionally disappears from the url,
 * then we've got a previously known value.
 */

let {sessionStorage, location} = window

let TAG = 'proctoring fader'

let key = 'examus-client-origin'
let from_storage = () => sessionStorage.get(key)
let to_storage = x => sessionStorage.set(key, x)
let from_url = () =>
  new URL(location.href)
  .searchParams
  .get('examus-client-origin')

/* We prefer the value stored in sessionStorage,
 * to resist against spoofing of the query param. */

/* Read value from url only when there is no value stored yet. */
if (!from_storage()) to_storage(from_url())

let expected_origin = from_storage()

if (!expected_orgin) {
  console.error(TAG, 'missing `expected_origin`')
}

      window.addEventListener("message", function(e){
        console.debug(TAG, 'got some message', e.origin, expected_origin);
        if(e.origin === expected_origin){
          examus_q.push(e.data); console.debug(TAG, 'got proved message', e.data);
        }
        check();
      });

      var examusFader;
      window.addEventListener("DOMContentLoaded", function(){
        console.log("loaded");
        examusFader = document.createElement("DIV");
        examusFader.innerHTML = str_awaiting_proctoring + str_instructions;
        examusFader.style="position: fixed; z-index: 1000; font-size: 2em; width: 100%; height: 100%; background: #fff; top: 0; left: 0;text-align: center;display: flex;justify-content: center;align-content: center;flex-direction: column;";
        document.body.appendChild(examusFader);
        if(!check()){
          if(window.parent && window.parent != window){
            window.parent.postMessage('proctoringRequest', expected_origin);
          }
        }
      });
      function check(){
        if(examus_q && examus_q[0]){
          unlock();
          return true;
        }
      }
      function unlock(){ if(examusFader) examusFader.remove(); examusFader = null; }
    })();
</script>
