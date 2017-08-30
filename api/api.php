<?php
/* Cameracontrol API
 *
 * (c) Florian Coulmier 2017
 *
 * API that allow to manage a Synology Surveillance Station instance.
 *
 */

require('class/ssapi.php');
require('config.php');

$verbose   = $conf_verbose;
$base_url  = $conf_base_url;
$ss_user   = $conf_ss_user;
$ss_passwd = $conf_ss_passwd;


$code = 200;
$msg = "OK";

switch ($_GET['action']) {
case 'activate':
  $ssapi = new ssapi($base_url);
  try {
    $ssapi->connect($ss_user, $ss_passwd);
    $ssapi->enable_alerts();
    $ssapi->activate_action_rule();
    $msg = "Camera alerts are now active";
  } catch (Exception $e) {
    $code = 500;
    $msg = $e->getMessage();
  }
  break;

case 'deactivate':
  $ssapi = new ssapi($base_url);
  try {
    $ssapi->connect($ss_user, $ss_passwd);
    $ssapi->disable_alerts();
    $ssapi->deactivate_action_rule();
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
?>
