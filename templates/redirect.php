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
 * Availability plugin for integration with Proctor by Constructor.
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $pagetitle ?></title>
  </head>
  <body>
      <div style="text-align: center">
          <form action="<?php echo $formdata['action'] ?>"
                method="<?php echo $formdata['method'] ?>"
                id="availability_proctor_redirect_form"
          >
              <?php if(isset($formdata['token'])): ?>
                  <input type="hidden" value="<?php echo $formdata['token'] ?>" name="token">
              <?php endif ?>
              <button type="submit">Go to Proctoring system</button>
          </form>
      </div>
      <script type="text/javascript">
          function redirect() {
              document.getElementById('availability_proctor_redirect_form').submit();
          }
          try { redirect() } catch (e) { console.error(e) };
          setTimeout(redirect, 5000);
          setTimeout(redirect, 10000);
      </script>
  </body>
</html>
