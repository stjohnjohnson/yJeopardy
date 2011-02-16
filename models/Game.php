<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Configuration */
require_once '../Config.php';
/* Player Model */
require_once 'Player.php';
/* GameBoard Model */
require_once 'GameBoard.php';

 /**
  * Game Class
  *
  * @category Model
  * @package yJeopardy
  * @author Suresh Jayanty <jayantys@yahoo-inc.com>
  * @author St. John Johnson <stjohn@yahoo-inc.com>
  */
class Game {
  const STATE_PAUSED = 'PAUSED';
  const STATE_PICK_QUESTION = 'PICK_QUESTION';
  const STATE_DISPLAY_QUESTION = 'DISPLAY_QUESTION';
  const STATE_BUZZ_IN = 'BUZZ_IN';
  const STATE_ANSWER = 'ANSWER';
  const STATE_ROUND_OVER = 'ROUND_OVER';
  const STATE_GAME_OVER = 'GAME_OVER';

  public static $validStates = array(
    self::STATE_PAUSED,
    self::STATE_PICK_QUESTION,
    self::STATE_DISPLAY_QUESTION,
    self::STATE_BUZZ_IN,
    self::STATE_ANSWER,
    self::STATE_ROUND_OVER,
    self::STATE_GAME_OVER
  );
  
  public $GameBoard;
  public $Players;
  
  public function __construct() {
    // init the game board
    $this->GameBoard = new GameBoard();
    $this->Players = Player::get_players();
  }
  
  public function get_last_winner($new = false) {
    $winner = apc_fetch('last_winner');

    // If no last winner, pick random person
    if ($winner === false || $new) {
      $players = Player::get_player_list();

      if (count($players) === 0) {
        throw new Exception('No Players to Pick From');
      }

      do {
        $newWinner = array_rand($players);
      } while ($newWinner === $winner && count($players) > 1);

      $this->set_last_winner($newWinner);
      
      return $newWinner;
    } else {
      return $winner;
    }
  }

  public function set_last_winner($id) {
    apc_store('last_winner', $id);
  }
  
  public function get_game_state() {
    if (apc_fetch('game_paused') === true) {
      return self::STATE_PAUSED;
    }
    
    $state = apc_fetch('game_state');
    if ($state === false) {
      return self::STATE_GAME_OVER;
    }
    return $state;
  }
  
  public function set_game_state($state) {
    if (!in_array($state, self::$validStates)) {
      throw new Exception('Invalid state cannot be set');
    }

    if ($state === self::STATE_PAUSED) {
      if (apc_fetch('game_paused') === true) {
        apc_delete('game_paused');
      } else {
        apc_store('game_paused', true);
      }
      return;
    }

    if ($state === self::STATE_PICK_QUESTION) {
      if ($this->GameBoard->is_empty()) {
        if ($this->GameBoard->get_round() == NUM_ROUNDS) {
          $state = self::STATE_GAME_OVER;
        } else {
          $state = self::STATE_ROUND_OVER;
        }
      }
    }

    apc_delete('game_paused');
    apc_store('game_state', $state);
  }

  public function get_active_player() {
    if ($this->get_game_state() === self::STATE_BUZZ_IN) {
      $top = $this->get_top_buzz();
      if ($top === false) {
        throw new Exception('No active player exists');
      }
      return $top;
    } else {
      $active_player = apc_fetch('game_active_player');
      if ($active_player === false) {
        throw new Exception('No active player exists');
      }

      return $active_player;
    }
  }
  
  public function set_active_player($player) {
    if (!array_key_exists($player, $this->Players)) {
      throw new Exception('Player not in the player list');

    }
    
    apc_store('game_active_player', $player);
  }

  public function get_active_question() {
    $question = apc_fetch('game_active_question');

    if ($question === false) {
      throw new Exception('No active question exists');
    }

    return $question;
  }

  public function set_active_question($id) {
    apc_store('game_active_question', $id);
  }
  
  public function get_top_buzz() {
    $buzzes = array();

    // Loop through the players
    foreach ($this->Players as $id => $player) {
      $buzz = $player->get_buzz();

      if ($buzz === false || $buzz === true ) {
        continue;
      }

      // If they buzzed, add them to the list
      $buzzes[$id] = $buzz;
    }

    // If no buzzes yet, return false
    if (count($buzzes) == 0) {
      return false;
    }

    // Reverse sort the buzz list
    arsort($buzzes);

    // Reset pointer and return just the first key
    reset($buzzes);
    return key($buzzes);
  }

  public function clear_buzzes($all = false) {
    $last = '';
    // Enable all but the last player who answered and locked out players
    if (!$all) {
      $last = $this->get_active_player();
    }

    foreach ($this->Players as $id => $player) {
      // If player is locked out, do not clear them
      if (!$all) {
        if ($player->get_buzz() === true) {
          continue;
        }
      }

      if ($id == $last) {
        // Invalidate the Buzz
        $player->set_buzz(null);
      } else {
        // Clear the Buzz
        $player->set_buzz(true);
      }
    }
  }

}