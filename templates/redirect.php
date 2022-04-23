<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $pagetitle ?></title>
  </head>
  <body>
      <div style="text-align: center">
          <form action="<?= $formdata['action'] ?>" method="POST" id="availability_examus_redirect_form">
              <input type="hidden" value="<?= $formdata['token']?>" name="token">
              <button type="submit">Go to Examus</button>
          </form>
      </div>
      <script type="text/javascript">
          function redirect() {
              document.getElementById('availability_examus_redirect_form').submit();
          }
          try { redirect() } catch (e) { console.error(e) };
          setTimeout(redirect, 5000);
          setTimeout(redirect, 10000);
      </script>
  </body>
</html>
