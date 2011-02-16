<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 *
 * Session Model
 *
 * @category Model
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */

class Session {
  public static function get_id($override = false) {
    $id = '';
    if (!$override) {
      if (isset($_COOKIE['si'])) {
        $id = $_COOKIE['si'];
      } else {
        $cookies = array();
        if (function_exists('apache_request_headers')) {
          $headers = apache_request_headers();
        } else {
          $headers = array();
          foreach($_SERVER as $key => $value) {
            if (substr($key,0,5) == "HTTP_") {
              $key = str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
              $headers[$key] = $value;
            }
          }
        }

        $headers = explode(';', $headers['Cookie']);
        foreach ($headers as $header) {
          list($key, $value) = explode('=', trim($header));
          $cookies[$key] = $value;
        }

        if (isset($cookies['si'])) {
          $id = $cookies['si'];
        }
      }
    }

    if ($id == '') {
      do {
        $id = substr(md5(microtime(true)), 0, 7);
      } while (apc_fetch('handle_' . $id) !== false);

      apc_store('handle_' . $id, true);
      $_COOKIE['si'] = $id;
      setcookie('si', $id);
    }

    return $id;
  }
}