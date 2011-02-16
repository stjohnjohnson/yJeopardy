<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Session Model */
require_once '../models/Session.php';

 /**
  * Player Class
  *
  * @category Model
  * @package yJeopardy
  * @author Suresh Jayanty <jayantys@yahoo-inc.com>
  * @author St. John Johnson <stjohn@yahoo-inc.com>
  */
class Player {
  public $id = '';
  public $name = '';

  /**
   * Returns List of Player Ids
   * @return array
   */
  public static function get_player_list() {
    $players = apc_fetch('player_list');

    if ($players === false) {
      return array();
    } else {
      return $players;
    }
  }

  /**
   * Returns List of Players
   * @return Array of Player Objects
   */
  public static function get_players() {
    $players = self::get_player_list();

    foreach ($players as $id => $name) {
      $players[$id] = new Player($id, $name);
    }
    
    return $players;
  }

  /**
   * Adds a Player to the Game
   * @throws Exception if Player Already Exists
   * @param string $id
   */
  public static function add_player($id, $name) {
    // Strip invalid characters and prevent length of > 15
    $id = strtolower(trim(preg_replace('/[^\w\s]/i', '', $id)));
    $name = substr(trim(preg_replace('/[^\w\s]/i', '', $name)), 0, 15);

    // Check Empty Data
    if (strlen($id) == 0) {
      throw new Exception('Player handle empty');
    } elseif (strlen($name) == 0) {
      throw new Exception('Player name empty');
    }
    // Check Player isn't already in
    $players = self::get_player_list();
    foreach ($players as $key => $pname) {
      $players[$key] = strtolower($pname);
    }
    if (in_array(strtolower($name), $players)) {
      throw new Exception('Player name taken');
    }

    // Clear points
    apc_store('points_' . $id, 0);
    
    for (;;) {
      // Add player to list
      $players = self::get_player_list();
      $players[$id] = $name;
      apc_store('player_list', $players);

      // Ensure player was added correctly
      $players = self::get_player_list();
      if (isset($players[$id]) && $players[$id] == $name) {
        break;
      }
    }

    return $id;
  }

  /**
   * Basic Construct
   * @param string $id
   */
  public function __construct($id, $name = null) {
    $this->id = $id;
    if ($name !== null) {
      $this->name = $name;
    } else {
      $players = self::get_player_list();
      if (!isset($players[$id])) {
        throw new Exception('Invalid Player ID');
      }
      $this->name = $players[$id];
    }
  }

  /**
   * Returns number of Points that the Player has
   * @return int
   */
  public function get_points() {
    $points = apc_fetch('points_' . $this->id);

    if ($points === false) {
      return 0;
    } else {
      return $points;
    }
  }

  /**
   * Add Points to a Player's Score
   * @param int $points
   */
  public function add_points($points) {
    if (is_numeric($points)) {
      apc_store('points_' . $this->id, $this->get_points() + $points);
    }
  }

  /**
   * Set a Player's Score
   * @param int $points
   */
  public function set_points($points) {
    if (is_numeric($points)) {
      apc_store('points_' . $this->id, $points);
    }
  }

  /**
   * Returns the Timestamp or false or true of the Player's Buzz-in Time
   * @return timestamp
   */
  public function get_buzz() {
    return apc_fetch('buzz_' . $this->id);
  }

  /**
   * Sets the Player's Buzz-in Time
   * Pass true to clear the timestamp
   * Pass null to invalidate the timestamp
   * @param bool $clear
   */
  public function set_buzz($clear = false) {
    if ($clear === false) {
      apc_store('buzz_' . $this->id, microtime(true));
    } elseif ($clear === true) {
      apc_delete('buzz_' . $this->id);
    } else {
      apc_store('buzz_' . $this->id, true);
    }
  }

  /**
   * Returns true if the Player can Buzz-in
   * @return bool
   */
  public function can_buzz() {
    if ($this->get_buzz() === false) {
      return true;
    } else {
      return false;
    }
  }
}