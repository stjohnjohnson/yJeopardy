<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 *
 * GameBoard Class
 *
 * @category Model
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */

class GameBoard {
  private $_BOARD;
  private $_ROUND;

  /**
   * 
   * Reads a config file for questions and answers
   * and initialized the $_BOARD array 
   */
  public function __construct() {
    $round = apc_fetch('game_round');
    if ($round === false) {
      $this->start_round();
    }

    $board = apc_fetch('game_board');
    if ($board === false) {
      $board = $this->__reload_board($round);
    }
    
    $this->_BOARD = $board;
    $this->_ROUND = $round;
  }

  public function start_round() {
    $this->_ROUND = 1;
    apc_store('game_round', $this->_ROUND);

    $this->_BOARD = $this->__reload_board($this->_ROUND);
  }

  public function next_round() {
    $this->_ROUND++;
    apc_store('game_round', $this->_ROUND);

    $this->_BOARD = $this->__reload_board($this->_ROUND);
  }

  private function __reload_board($id) {
    if (!is_numeric($id)) {
      throw new Exception('Invalid Round Number');
    }
    $lines = file_get_contents('../questions/round-' . $id . '.yaml');
    $lines = explode("\n", $lines);
    $board = array();

    $generate_dd = true;

    $i = 0;
    $category = '';
    $sub_category = '';
    $question_id = $category_id = 0;
    foreach($lines as $line) {
      if (preg_match('/^([^\s].+):/', $line, $matches)) {
        $category = $matches[1];
        $category_id++;
        continue;
      }
      if (preg_match('/^\s+(.+):/', $line, $matches)) {
        $sub_category = $matches[1];
        $question_id++;
        $i = 0;
        continue;
      }
      if (preg_match('/^\s+-\s*(.+)/', $line, $matches)) {
        if ($i === 0) {
          $board[$question_id] = array('category_id' => $category_id,
                                          'category' => $category,
                                            'points' => $sub_category,
                                          'question' => $matches[1],
                                         'is_picked' => 0,
                                                'dd' => 0);
        } elseif ($i === 1) {
          $board[$question_id]['answer'] = $matches[1];
        } else {
          $board[$question_id]['dd'] = 1;
          $generate_dd = false;
        }
        $i++;
        
        continue;
      }
    }

    if ($generate_dd) {
      $dd_question_id = rand(0, $question_id);
      $board[$dd_question_id]['dd'] = 1;
    }
    apc_store('game_board', $board);

    return $board;
  }
  
  public function get() {
    return $this->_BOARD;
  }

  public function get_round() {
    return $this->_ROUND;
  }
  
  /**
   * 
   * Returns a boolean value based on if the question is already picked
   */
  private function __is_picked($id) {
    return $this->_BOARD[$id]['is_picked'];
  }
  
  public function pick_question($id) {
    if ($this->__is_picked($id) === 0) {
      $this->_BOARD[$id]['is_picked'] = 1;
      apc_store('game_board', $this->_BOARD);
    } else {
      throw new Exception('Question already been picked');
    }
  }
  
  public function mark_question_dd($category, $points) {
    $this->_BOARD[$category][$points]['dd'] = 1;
    apc_store('game_board', $this->_BOARD);
  } 
  
  public function get_question($id) {
    if (!isset($this->_BOARD[$id])) {
      throw new Exception('Question does not exist');
    }
    return $this->_BOARD[$id];
  }

  public function is_empty() {
    foreach ($this->_BOARD as $question) {
      if ($question['is_picked'] == 0) {
        return false;
      }
    }

    return true;
  }
}


?>