<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Configuration */
require_once '../Config.php';
/* Json Model */
require_once '../models/Json.php';

/**
 * Request Controller
 *
 * @category Controller
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */

class Request extends Json {
  protected $validMethods = array(
      // Generic Queries
    'get_categories',
    'get_players',
    'get_game_state',
    'get_active_player',
    'get_question',
      // Player Queries
    'new_player',
    'get_player',
    'pick_question',
    'buzz',
    'wait_buzz',
      // Admin Queries
    'start_buzzing',
    'answer_wrong',
    'answer_right',
    'start_round',
    'next_round',
    'skip_question',
    'new_active_player',
    'get_answer',
    'cache_status',
    'cache_clear',
    'mass_login',
    'pause_game'
  );

  /**
   * Looks for a logged in user.
   * 
   * @return Player object
   */
  private function _auth_player() {
    try {
      return new Player(Session::get_id());
    } catch (Exception $e) {
      throw new Exception('No Player Logged In');
    }
  }

  /**
   * Checks for admin authorization code
   * 
   * @throws Exception if invalid code sent
   */
  private function _auth_admin() {
    if (!isset($_REQUEST['wssid']) || 
        $_REQUEST['wssid'] != ADMIN_SECRET) {
      throw new Exception('Player is not Admin');
    }
  }

  /**
   * Gets the current logged in Player's information
   * 
   * @return array Player handle, points, state
   */
  public function get_player() {
    $player = $this->_auth_player();
    $state = $this->Game->get_game_state();

    // Get the current active player (if available)
    $active = '';
    if (in_array($state, array(Game::STATE_ANSWER, Game::STATE_PICK_QUESTION))) {
      try {
        $active = $this->Game->get_active_player();
      } catch (Exception $e) { }
    }

    return array(
      'handle' => $player->id,
        'name' => $player->name,
       'score' => $player->get_points(),
       'state' => $state,
      'active' => ($active === $player->id) ? true : false
    );
  }

  /**
   * Creates a new player to the game
   * 
   * @param Array $data Player name
   * @return Array Player handle, points, state
   */
  public function new_player($data) {

    if (!isset($data['name'])) {
      throw new Exception('Player Name Missing');
    }

    $id = Session::get_id(isset($data['override']));
    $player_id = Player::add_player($id, $data['name']);

    return $this->get_player();
  }

  public function wait_buzz() {
    $player = $this->_auth_player();

    // Hold the connection for 30 seconds
    set_time_limit(30);

    for ($i = 0; $i < 40; $i++) {
      $state = $this->Game->get_game_state();
      if ($state == Game::STATE_BUZZ_IN) {
        if ($player->can_buzz()) {
          return array('can_buzz' => true);
        } else {
          return array('can_buzz' => false);
        }
      } elseif ($state == Game::STATE_ANSWER) {
        return array('can_buzz' => false);
      }
      usleep(500000);
    }

    return array('can_buzz' => false);
  }

  /**
   * Buzzes
   *
   * @return Array buzz status (timestamp or false)
   */
  public function buzz() {
    $player = $this->_auth_player();

    if ($this->Game->get_game_state() === Game::STATE_BUZZ_IN) {
      if ($player->can_buzz()) {
        $player->set_buzz();
        $top = $this->Game->get_top_buzz();

        // Shouldn't happen
        if ($top === false) {
          return;
        }

        // Set the player
        $this->Game->set_active_player($top);

        // Update game state
        $this->Game->set_game_state(Game::STATE_ANSWER);
      }
    }

    return array('buzzed' => ($player->get_buzz() !== false));
  }

  public function answer_right() {
    $this->_auth_admin();

    if ($this->Game->get_game_state() != Game::STATE_ANSWER) {
      throw new Exception('Not in Answer State');
    }

    // Load Active Player
    $player = new Player($this->Game->get_active_player());

    // Get Active Question
    $qid = $this->Game->get_active_question();

    // Get Question Points
    $question = $this->Game->GameBoard->get_question($qid);

    // Add Points to Player
    if ($question['dd'] == 1) {
      $player->add_points($question['points'] * 2);
    } else {
      $player->add_points($question['points'] * 1);
    }

    // Set the Last Winner
    $this->Game->set_last_winner($player->id);

    // Move the Game State
    $this->Game->set_game_state(Game::STATE_PICK_QUESTION);

    // Clear Buzzes
    $this->Game->clear_buzzes(true);
  }

  public function answer_wrong() {
    $this->_auth_admin();

    if ($this->Game->get_game_state() != Game::STATE_ANSWER) {
      throw new Exception('Not in Answer State');
    }

    // Load Active Player
    $player = new Player($this->Game->get_active_player());

    // Get Active Question
    $qid = $this->Game->get_active_question();

    // Get Question Points
    $question = $this->Game->GameBoard->get_question($qid);

    // Remove Points to Player
    if ($question['dd'] == 1) {
      $player->add_points($question['points'] * -2);
    } else {
      $player->add_points($question['points'] * -1);
    }

    // Clear Buzzes
    $this->Game->clear_buzzes();

    // Move to Buzzing Round
    if ($question['dd'] == 1) {
      $this->Game->set_game_state(Game::STATE_PICK_QUESTION);
    } else {
      $this->Game->set_game_state(Game::STATE_BUZZ_IN);
    }
  }

  public function start_buzzing() {
    $this->_auth_admin();

    if ($this->Game->get_game_state() != Game::STATE_DISPLAY_QUESTION) {
      throw new Exception('Not in Display Question State');
    }

    // Clear Buzzes
    $this->Game->clear_buzzes(true);

    // Get Active Question
    $qid = $this->Game->get_active_question();

    // Get Question Points
    $question = $this->Game->GameBoard->get_question($qid);

    // If Daily Double, assign current picker
    if ($question['dd'] == 1) {
      // Move to Answer
      $this->Game->set_game_state(Game::STATE_ANSWER);
    } else {
      // Move to Buzzing Round
      $this->Game->set_game_state(Game::STATE_BUZZ_IN);
    }
  }

  public function get_active_player() {
    $player = new Player($this->Game->get_active_player());
    return array('handle' => $player->id,
                   'name' => $player->name);
  }

  public function get_game_state() {
    $output = array('game_state' => $this->Game->get_game_state());
    try {
      $output = array_merge($output, $this->get_active_player());
    } catch (Exception $e) {}
    return $output;
  }

  public function get_players() {
    $players = Player::get_players();
    $state = $this->Game->get_game_state();
    $output = array();

    // Get the current active player (if available)
    $active = '';
    if (in_array($state, array(Game::STATE_ANSWER, Game::STATE_PICK_QUESTION))) {
      try {
        $active = $this->Game->get_active_player();
      } catch (Exception $e) { }
    }

    foreach ($players as $id => $player) {
      $points = $player->get_points();
      if (!isset($output[$points])) {
        $output[$points] = array();
      }
      $output[$points][$player->name] = array(
          'handle' => $id,
            'name' => $player->name,
          'points' => $points,
          'active' => ($active === $id) ? TRUE : FALSE
      );
    }

    // Sort each array by name
    foreach ($output as $points => $players) {
      ksort($output[$points]);
    }
    // Then sort by points, desc
    krsort($output);

    // Merge together
    $final = array();
    foreach ($output as $players) {
      $final = array_merge($final, array_values($players));
    }

    return $final;
  }
  
  public function get_categories() {
    $questions = $this->Game->GameBoard->get();
    
    $cat_map = array();
    $categories = array();
    foreach ($questions as $id => $question) {
      $cat_id = $question['category_id'];
      if (!isset($categories[$cat_id])) {
        $categories[$cat_id] = array();
        $cat_map[$cat_id] = $question['category'];
      }
      // Rewrite Schema
      $question['id'] = $id;
      $question['played'] = $question['is_picked'];
      unset($question['answer']);
      unset($question['question']);
      unset($question['category']);
      unset($question['category_id']);
      unset($question['is_picked']);
      unset($question['dd']);
      $categories[$cat_id][$question['points']] = $question;
    }

    $output = array();
    foreach ($cat_map as $id => $name) {
      ksort($categories[$id], SORT_NUMERIC);

      $output[] = array(
          'id' => $id,
        'name' => $name,
   'questions' => array_values($categories[$id])
      );
    }
    return $output;
  }

  public function cache_clear() {
    $this->_auth_admin();

    return apc_clear_cache('user');
  }

  public function cache_status() {
    $this->_auth_admin();

    return apc_cache_info('user');
  }

  public function pick_question($data) {
    $player = $this->_auth_player();

    if ($this->Game->get_game_state() != Game::STATE_PICK_QUESTION) {
      throw new Exception('Not in Pick Question Game State');
    }

    if ($this->Game->get_active_player() != $player->id) {
      throw new Exception('Player not currently active');
    }

    if (!isset($data['id']) || !is_numeric($data['id'])) {
      throw new Exception('Missing Question ID');
    }

    // Mark as picked
    $this->Game->GameBoard->pick_question($data['id']);

    // Update Game
    $this->Game->set_active_question($data['id']);

    // Check for DD status
    $question = $this->Game->GameBoard->get_question($data['id']);

    // Update State depending on Daily Double
    $this->Game->set_game_state(Game::STATE_DISPLAY_QUESTION);
  }

  public function get_question() {
    if (!in_array($this->Game->get_game_state(),
            array(Game::STATE_ANSWER, Game::STATE_DISPLAY_QUESTION))) {
      throw new Exception('Not in Display Question Game State');
    }

    $qid = $this->Game->get_active_question();
    $question = $this->Game->GameBoard->get_question($qid);
    return array('id' => $qid,
                 'dd' => $question['dd'],
           'category' => $question['category'],
             'points' => $question['points'],
           'question' => $question['question']
    );
  }
  
  public function pause_game() {
    $this->_auth_admin();
    
    $this->Game->set_game_state(Game::STATE_PAUSED);
    $state = apc_fetch('game_paused') ? true : false;
    
    return array('paused' => $state);
  }
  
  public function start_round() {
    $this->_auth_admin();
    
    if ($this->Game->get_game_state() != Game::STATE_GAME_OVER) {
      throw new Exception('Game not in Game Over state');
    }

    // Reset scores
    $players = Player::get_players();
    foreach ($players as $player) {
      $player->set_points(0);
    }

    if (count($players) == 0) {
      throw new Exception('Cannot start, no players in game');
    }

    // Reload gameboard
    $this->Game->GameBoard->start_round();

    // Set active player
    $this->Game->set_active_player($this->Game->get_last_winner(true));

    // Move to pick question
    $this->Game->set_game_state(Game::STATE_PICK_QUESTION);
  }

  public function next_round() {
    $this->_auth_admin();

    if ($this->Game->get_game_state() != Game::STATE_ROUND_OVER) {
      throw new Exception('Game not in Round Over state');
    }

    // Reload gameboard
    $this->Game->GameBoard->next_round();

    // Set active player
    $this->Game->set_active_player($this->Game->get_last_winner());

    // Move to pick question
    $this->Game->set_game_state(Game::STATE_PICK_QUESTION);
  }

  /**
   *
   * @admin
   */
  public function new_active_player() {
    $this->_auth_admin();

    $player = $this->Game->get_last_winner(true);
    $this->Game->set_active_player($player);

    return $this->get_active_player();
  }

  public function skip_question() {
    $this->_auth_admin();

    if ($this->Game->get_game_state() != Game::STATE_BUZZ_IN) {
      throw new Exception('Not in Buzz In Game State');
    }

    // Set active player
    $this->Game->set_active_player($this->Game->get_last_winner());

    // Update State
    $this->Game->set_game_state(Game::STATE_PICK_QUESTION);
  }
  
  public function get_answer() {
    $this->_auth_admin();
    
    if (!in_array($this->Game->get_game_state(),
        array(Game::STATE_DISPLAY_QUESTION, Game::STATE_PICK_QUESTION, Game::STATE_BUZZ_IN, Game::STATE_ANSWER))) {
      throw new Exception('Game not in a valid state to get answer');
    }

    // Get Active Question
    $qid = $this->Game->get_active_question();

    // Get Question Points
    $question = $this->Game->GameBoard->get_question($qid);
    
    return array('id' => $qid,
             'answer' => $question['answer']);
  }

  public function mass_login() {
    $this->_auth_admin();

    $names = array('Jessie', 'Lonnie', 'Fernando', 'Avis', 'Tania', 'Rosalinda',
        'Elnora', 'Ashlee', 'Julio', 'Clinton', 'Neil', 'Alejandra', 'Tania',
        'Hugh', 'Lance', 'Sofia', 'Darren', 'Lance', 'Javier', 'Harriett',
        'Milagros', 'Lakisha', 'Lorrie', 'Allan', 'Katy', 'Neil', 'Tanisha', 'Kurt');
    foreach ($names as $name) {
      try {
        $this->new_player(array('name' => $name, 'override' => true));
      } catch (Exception $e) {}
    }
  }
}

new Request();
