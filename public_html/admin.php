<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Configuration */
require_once '../Config.php';

/**
 * Admin Interface
 *
 * @category Web
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] != ADMIN_USERNAME ||
    $_SERVER['PHP_AUTH_PW'] != ADMIN_PASSWORD) {
  header('WWW-Authenticate: Basic realm="Y!Jeopardy Admin"');
  header('HTTP/1.0 401 Unauthorized');
  exit;
}

?>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no, width=device-width"/>
    <title>y!jeopardy</title>
    <link type="text/css" href="s/mobile.css" rel="stylesheet" media="screen, projection" />
    <script type="text/javascript" src="a/prototype.js"></script>
    <script type="text/javascript" src="a/scriptaculous/scriptaculous.js"></script>
    <script type="text/javascript">
      var admin = {
        update_game_state: function() {
          new Ajax.Request('Request.php?method=get_game_state&r=' + Math.random(), {
            onSuccess: function(transport) {
              if (transport.responseJSON.status == 'ok') {
                if (admin.timer == 0) {
                  // Add timer
                  admin.timer = setInterval(function() { admin.update_game_state(); }, 1000);
                }
                var state = '', player = '';

                state = transport.responseJSON.data.game_state;
                if (transport.responseJSON.data.handle) {
                  player = transport.responseJSON.data.name + ' ('
                         + transport.responseJSON.data.handle + ')';
                }
                if (state != admin.game_state || player != admin.active_player) {
                  // update dom
                  if (player != '') {
                    $('game_state').update(state + ' - ' + player);
                  } else {
                    $('game_state').update(state);
                  }
                }

                if (state != admin.game_state) {
                  // display appropriate screen
                  admin.change_screen(state);

                  // update local variable
                  admin.game_state = state;
                }
              } else {
                alert('Error: ' + transport.responseJSON.data);
              }
            }
          });
        },
        update_answer: function() {
          new Ajax.Request('Request.php?wssid=<?php echo ADMIN_SECRET; ?>&method=get_answer&r=' + Math.random(), {
            onSuccess: function(transport) {
              if (transport.responseJSON.status == 'ok') {
                if (admin.game_state != 'ANSWER') {
                  alert('Answer: ' + transport.responseJSON.data.answer);
                } else {
                  $('answer_text').update(transport.responseJSON.data.answer);
                }
              } else {
                alert('Error: ' + transport.responseJSON.data);
              }
            }
          });
        },
        change_screen: function(state) {
          // show display
          this.hide_panels(state);

          switch (state) {
            case 'ANSWER':
              this.update_answer();
              break;
            case 'PICK_QUESTION':
            case 'BUZZ_IN':
              // Add timer
              this.timer = setInterval(function() { admin.update_game_state(); }, 500);
              break;
          }
        },
        hide_panels: function(active) {
          active = active.toLowerCase();
          $$('div.panel').each(function(panel) {
            if (panel.id == active) {
              panel.show();
            } else {
              panel.hide();
            }
          });
        },
        ajax_call: function(method) {
          if (this.ajax == false) {
            // prevent multiple calls
            this.ajax = true;

            // Remove Timer for update
            clearInterval(this.timer);
            this.timer = 0;

            $('loader').show();
            new Effect.Opacity('loader', { from: 0.0, to: 0.75, duration: 0.1 });

            new Ajax.Request('Request.php?wssid=<?php echo ADMIN_SECRET; ?>&method=' + method + '&r=' + Math.random(), {
              onSuccess: function(transport) {
                admin.ajax = false;
                $('loader').hide();
                new Effect.Opacity('loader', { from: 0.75, to: 0.0, duration: 0.1 });

                if (transport.responseJSON.status == 'ok') {
                  admin.update_game_state();
                } else {
                  alert('Error: ' + transport.responseJSON.data);
                }
              }
            });
          }
        },
        pause_game: function() {
          this.ajax_call('pause_game');
        },
        start_game: function() {
          this.ajax_call('start_round');
        },
        reset_game: function() {
          this.ajax_call('cache_clear');
        },
        start_round: function() {
          this.ajax_call('next_round');
        },
        skip_player: function() {
          this.ajax_call('new_active_player');
        },
        skip_question: function() {
          this.ajax_call('skip_question');
          this.update_answer();
        },
        start_buzzing: function() {
          this.ajax_call('start_buzzing');
        },
        answer_right: function() {
          this.ajax_call('answer_right');
        },
        answer_wrong: function() {
          this.ajax_call('answer_wrong');
        },
        game_state: '',
        active_player: '',
        timer: 0,
        ajax: false
      };

      Event.observe(document, 'dom:loaded', function() {
        admin.update_game_state();
      });
    </script>

  </head>

  <body onload="setInterval(function() { window.scrollTo(0, 1) }, 1000);">
    <div class="container">
      <div class="menu">
        <div style="float:left">
        Y! Jeopardy
        </div>
        <span class="button" onclick="admin.pause_game()">
          Pause
        </span>
      </div>
      <div class="content">
        <br />
        <h2 id="game_state">Loading...</h2>
        <br />
        <div id="paused" class="panel" style="display: none">
          <div class="button" onclick="admin.pause_game()">
            Resume Game
          </div>
        </div>
        <div id="game_over" class="panel" style="display: none">
          <div class="button" onclick="admin.start_game()">
            Start Game
          </div>
          <br />
          <div class="button" onclick="admin.reset_game()">
            Reset Game
          </div>
        </div>
        <div id="round_over" class="panel" style="display: none">
          <div class="button" onclick="admin.start_round()">
            Start Next Round
          </div>
        </div>
        <div id="pick_question" class="panel" style="display: none">
          <div class="button" onclick="admin.skip_player()">
            Skip Player
          </div>
        </div>
        <div id="display_question" class="panel" style="display: none">
          <div class="button" onclick="admin.start_buzzing()">
            Begin Buzzing
          </div>
        </div>
        <div id="buzz_in" class="panel" style="display: none">
          <div class="button" onclick="admin.skip_question()">
            Skip Question
          </div>
        </div>
        <div id="answer" class="panel" style="display: none">
          <fieldset class="message">
            <legend></legend>
            <span style="font-size:11px;font-weight:bold;">Answer:</span>
            <br />
            <span id="answer_text" style="padding-left: 5px;">
              
            </span>
          </fieldset>
          <br />
          <div class="button green" onclick="admin.answer_right()">
            Answer Right
          </div>
          <br />
          <div class="button red" onclick="admin.answer_wrong()">
            Answer Wrong
          </div>
        </div>
      </div>
  	</div>
    <div id="loader" style="display: none;">
      <img src="data:image/gif;base64,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" alt="loading..." />
    </div>
  </body>
</html>