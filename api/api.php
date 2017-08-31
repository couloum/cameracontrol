<?php
/* Cameracontrol API
 *
 * (c) Florian Coulmier 2017
 *
 * API that allow to manage a Synology Surveillance Station instance.
 *
 */

require('class/ssapi.php');
$conf = include('config.php');

$verbose   = $conf['verbose'];
$base_url  = $conf['base_url'];
$ss_user   = $conf['ss_user'];
$ss_passwd = $conf['ss_passwd'];


$code = 200;
$msg = "OK";

switch ($_GET['action']) {
case 'activate':
  $ssapi = new ssapi($base_url);
  $ssapi->verbose = $verbose;
  try {
    $ssapi->connect($ss_user, $ss_passwd);
    //$ssapi->enable_alerts();
    //$ssapi->activate_action_rule();
    $ssapi->deactivate_home_mode();
    $msg = "Camera alerts are now active";
  } catch (Exception $e) {
    $code = 500;
    $msg = $e->getMessage();
  }
  break;

case 'deactivate':
  $ssapi = new ssapi($base_url);
  $ssapi->verbose = $verbose;
  try {
    $ssapi->connect($ss_user, $ss_passwd);
    //$ssapi->disable_alerts();
    //$ssapi->deactivate_action_rule();
    $ssapi->activate_home_mode();
    $msg = "Camera alerts are now inactive";
  } catch (Exception $e) {
    $code = 500;
    $msg = $e->getMessage();
  }
  break;

default:
  $code = 400;
  $msg = 'Bad request';
  break;
}

echo json_encode(array('code' => $code, 'message' => $msg));
echo "\n";
?>
