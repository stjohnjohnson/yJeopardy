<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Game Model */
require_once '../models/Game.php';

/**
 * Json Class
 *
 * @category Model
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */
abstract class Json {
  protected $validMethods = array();
  protected $Game;

  public function __construct() {    
    // Reset the Exception Handler
    set_exception_handler(array('Json', 'exception_handler'));

    // Create the Game Object
    $this->Game = new Game();

    // Check for valid Method and Run it
    $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : '';
    if (!in_array($method, $this->validMethods)) {
      throw new Exception('Method not supported');
    } else {
      unset($_REQUEST['method']);
      self::output($this->$method($_REQUEST));
    }
  }

  public static function exception_handler($ex) {
    self::output($ex->getMessage(), 'error');
  }

  public static function output($array, $status = 'ok') {
    header('Content-type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    echo json_encode(array('status' => $status, 'data' => $array));
    exit(1);
  }
}
