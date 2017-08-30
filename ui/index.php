<?php
/* Cameracontrol Web Interface
 *
 * (c) Florian Coulmier 2017
 *
 * Web interface that allowto manage a Synology Surveillance Station instance.
 *
 */

$conf = include('config.php');


function api_call($action) {
  global $conf;

  $api_url = $conf['api_url'];
  $full_url = sprintf("%s?action=%s", $api_url, $action);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_URL, $full_url);
  $ret = curl_exec($ch);

  if (curl_errno($ch)) {
    $ret = json_encode(array('code' => 503, 'message' => 'Connection error to remote server: ' . curl_error($ch)));
  } elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
    $ret = json_encode(array('code' => 503, 'message' => 'Bad HTTP return code from remote server: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE)));
  }

  $json_data = json_decode($ret);
  if ($json_data === NULL) {
    $json_data = json_decode(json_encode(array('code' => 503, 'message' => 'Invalid response from remote server (not in JSON format): ' . substr($ret, 0, 20))));
  }

  curl_close($ch);

  return $json_data;
}

function _update_status($req_src, $req_action) {
  global $conf;

  switch($req_action) {
    case 'activate':
      $state = 'active';
      break;
    case 'deactivate':
      $state = 'inactive';
      break;
    default:
      $state = 'unknown';
      break;
  }
  $status = json_encode(array(
    'date'   => date('Y-m-d H:i:s'),
    'source' => $req_src,
    'ip'     => $_SERVER['REMOTE_ADDR'],
    'state'  => $state,
  ));
  $f = fopen($conf['data_file'], 'w+');
  $r = fwrite($f, $status);
  fclose($f);
}

function _get_status() {
  global $conf;

  $f = fopen($conf['data_file'], 'r+');
  $r = fgets($f);
  fclose($f);

  return json_decode($r);
}

function _log($req_src, $req_action, $ret_code, $ret_msg) {
  global $conf;

  $log = sprintf("%s %-15s %s %-10s %s \"%s\"",
    date("r"),
    $_SERVER['REMOTE_ADDR'],
    $req_src,
    $req_action,
    $ret_code,
    $ret_msg
  );
  $f = fopen($conf['log_file'], 'a+');
  $r = fwrite($f, "$log\n");
  fclose($f);

  if ($ret_code == 200) {
    _update_status($req_src, $req_action);
  }
}

function _get_last_logs($nblines) {
  global $conf;

  exec("tail -$nblines " . $conf['log_file'], $output);
  return implode("\n", array_reverse($output));
}


$req_content = file_get_contents("php://input");

if (preg_match('/"source":"API"/', $req_content)) {
  // Called by IFTTT
  $req = json_decode($req_content);
  if ($req == NULL || !isset($req->action)) {
    _log('API', '-', 400, 'Bad request');
    http_response_code(400);
    echo "Bad request\n";
    exit(0);
  }

  switch($req->action) {
  case 'activate':
    $ret = api_call('activate');
    break;
  case 'deactivate':
    $ret = api_call('deactivate');
    break;
  }

  _log('API', $req->action, $ret->code, $ret->message);
  http_response_code($ret->code);
  printf("{\"message\":\"%s\"}\n", $ret->message);
  exit(0);

} elseif (preg_match('/source=WEB/', $req_content)) {
  // Called manually
  switch($_POST['action']) {
  case 'activate':
    $ret = api_call('activate');
    break;
  case 'deactivate':
    $ret = api_call('deactivate');
    break;
  }
  if (isset($ret)) {
    _log('WEB', $_POST['action'], $ret->code, $ret->message);
  }
}

$current_status = _get_status();

?>
<!DOCTYPE html>
<html lang="en">
  <header>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="css/bootstrap-3.3.7.min.css" />

    <!-- Optional theme -->
    <link rel="stylesheet" href="css/bootstrap-theme-3.3.7.min.css" />

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="js/jquery-1.12.4.min.js"></script>

    <!-- Latest compiled and minified JavaScript -->
    <script src="js/bootstrap-3.3.7.min.js"></script>
  </header>
  <body>
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1>Camera alerts control center</h1>
          <p><strong>Current status:</strong> <?php
$label_color = $current_status->state == "active" ? 'success' : 'default';
printf('<span class="label label-%s">%s</span>',
  $label_color,
  $current_status->state
);
?></p>
          <form method='POST' style="margin-bottom:1em;">
            <input type="hidden" name="source" value="WEB" />
            <button type="submit" class="btn btn-success" name='action' value='activate'>Enable alerts</button>
            <button type="submit" class="btn btn-danger" name='action' value='deactivate'>Disable alerts</button>
          </form>
<?php
if (isset($ret)) {
  $bg_color = $ret->code == 200 ? 'success' : 'danger';
  $label = $ret->code == 200 ? 'Success' : 'Error';
  printf('<div class="alert alert-%s" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <strong>%s:</strong> %s</div>', $bg_color, $label, $ret->message);
}
?>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
        <h3>Last logs:</h3>
        <pre><?php echo _get_last_logs(10); ?></pre>
        </div>
      </div>
    </div>
  </body>
</html>

