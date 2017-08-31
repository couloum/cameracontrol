<?php
/* Cameracontrol SSAPI Class
 *
 * (c) Florian Coulmier 2017
 *
 */

class ssapi {

  protected $_is_authenticated = false;
  protected $_ch;
  protected $_base_url;
  protected $_sid;
  protected $_api_info = array();

  public    $verbose = false;

  public function __construct($url) {
    $this->_base_url = $url;

    $this->_ch = curl_init();
    curl_setopt($this->_ch, CURLOPT_USERAGENT, 'PHP CURL');
    curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->_ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($this->_ch, CURLOPT_TIMEOUT, 2);
  }

  public function __destruct() {
    $this->disconnect();

    curl_close($this->_ch);
  }

  public function connect($login, $password) {
    if(!isset($this->_api_auth_info)) {
      $this->_get_api_info(array('SYNO.API.Auth', 'SYNO.SurveillanceStation.Notification.Filter', 'SYNO.SurveillanceStation.ActionRule', 'SYNO.SurveillanceStation.HomeMode'));
    }

    $data = $this->_get('SYNO.API.Auth', 'Login', array('account' => $login, 'passwd' => $password, 'session' => 'SurveillanceStation', 'format' => 'id'));

    // Get auth id
    if (!isset($data['sid'])) {
      $this->_throw_exception("No authentication SID provided after Login API call");
    }

    $this->_sid = $data['sid'];
    $this->_is_authenticated = true;
    $this->_log("[INFO] Authentication ID: " . $this->_sid);

    return true;
  }

  public function disconnect() {
    if (!$this->_is_authenticated) {
      return true;
    }

    $this->_log("[INFO] Disconnecting session");

    try {
      $this->_get('SYNO.API.Auth', 'Logout');
    } catch (Exception $e) {
      $this->_log("[ERROR] Could not logout properly");
      return false;
    }

    $this->_is_authenticated = false;

    return true;
  }

  private function _throw_exception($msg) {
      $this->_log("[ERROR] $msg");
      throw new Exception($msg);
  }

  /*
  public function get_notification_filters() {
    $this->_ensure_authenticated();
    $data = $this->_get('SYNO.SurveillanceStation.Notification.Filter', 'Get');

    return $data;
  }
   */

  private function _ensure_authenticated() {
    if(!$this->_is_authenticated) {
      $this->_throwException("You must be authenticated first");
    }
  }

  public function enable_alerts() {
    $this->_ensure_authenticated();

    // Issued from Synology documentation:
    //
    // Optional parameters of 'Set' method:
    //   - [X] = [value]
    // X is a number equivalent to a event type and the
    // value means the setting of filter.
    // [X]: refer to "eventType" in filter_element
    // [value]: refer to "filter" in filter_element
    //
    // eventType 5 = Motion detected
    // filter 4 = Mobile

    $this->_get('SYNO.SurveillanceStation.Notification.Filter', 'Set', array('5' => '4'));
    return true;
  }

  public function disable_alerts() {
    $this->_ensure_authenticated();

    // eventType 5 = Motion detected
    // filter 0 = No notifiation
    $this->_get('SYNO.SurveillanceStation.Notification.Filter', 'Set', array('5' => '0'));
    return true;
  }

  public function activate_action_rule() {
    $this->_ensure_authenticated();

    // TODO: should be improved to identify Action Rule Id using IP
    $this->_get('SYNO.SurveillanceStation.ActionRule', 'Enable', array('idList' => '3'));
    return true;
  }

  public function deactivate_action_rule() {
    $this->_ensure_authenticated();

    // TODO: should be improved to identify Action Rule Id using IP
    $this->_get('SYNO.SurveillanceStation.ActionRule', 'Disable', array('idList' => '3'));
    return true;
  }

  public function activate_home_mode() {
    $this->_ensure_authenticated();
    $this->_get('SYNO.SurveillanceStation.HomeMode', 'Switch', array('on' => 1));
    return true;
  }

  public function deactivate_home_mode() {
    $this->_ensure_authenticated();
    $this->_get('SYNO.SurveillanceStation.HomeMode', 'Switch', array('on' => 0));
    return true;
  }

  public function call($api, $method, $params = array()) {
    $this->_ensure_authenticated();
    $data = $this->_get($api, $method, $params);
    return $data;
  }

  private function _get_api_url($api, $fail_if_not_found = false) {
    if (isset($this->_api_info[$api])) {
      return $this->_api_info[$api]['path'];
    } elseif ($api == "SYNO.API.Info") {
      return "query.cgi";
    } elseif($fail_if_not_found) {
      $this->_throw_exception("Could not find API information for API $api");
    } else {
      $this->_get_api_info(array($api));
      return $this->_get_api_url($api, true);
    }
  }

  private function _get_api_version($api) {
    if (isset($this->_api_info[$api])) {
      return $this->_api_info[$api]['maxVersion'];
    } else {
      return 1;
    }
  }

  private function _log($msg) {
    if ($this->verbose) {
      echo "$msg\n";
    }
  }

  private function _get($api, $method, $params = array()) {
    $full_url = sprintf('%s%s?api=%s&method=%s&version=%s',
      $this->_base_url,
      $this->_get_api_url($api),
      $api,
      $method,
      $this->_get_api_version($api)
    );
    foreach($params as $name => $value) {
      $full_url .= sprintf("&%s=%s", $name, urlencode($value));
    }

    //Insert sid if one set.
    if($this->_is_authenticated) {
      $full_url .= '&_sid=' . $this->_sid;
    }

    $redacted_full_url = preg_replace('/passwd=[^&]+/', 'passwd=*****', $full_url);

    // Cal API
    $this->_log("[INFO] Calling URL: $redacted_full_url");

    curl_setopt($this->_ch, CURLOPT_URL, $full_url);
    $cr = curl_exec($this->_ch);

    // Failure if request cannot be made
    if(curl_errno($this->_ch)) {
      $this->_throw_exception("Curl returned an error: " . curl_error($this->_ch));
    }

    $this->_log("[DEBUG] Data received:\n" . $cr);

    $data = json_decode($cr, true);

    // Failure if return code is not success
    if ($data['success'] === false) {
      $this->_throw_exception("API '$api' with method '$method' returned a failure with error code " . $data['error']['code']);
    }

    if (isset($data['data'])) {
      return $data['data'];
    } else {
      return true;
    }
  }

  private function _get_api_info($api_list = array()) {
    $_api_list = implode(',', $api_list);
    $data = $this->_get('SYNO.API.Info', 'Query', array('query' => $_api_list));

    foreach($api_list as $api) {
      // Check that we retrieved all the wanted URL
      if (!isset($data[$api])) {
        $this->_throw_exception("$api URL has not been returned by Synology API");
      }
    }

    $this->_api_info = array_merge($this->_api_info, $data);
  }
}
?>
