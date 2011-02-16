<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 *
 * Alternate User Interface
 *
 * @category Web
 * @package yJeopardy
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */
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
      var user = {
        update_game_state: function() {
          new Ajax.Request('Request.php?method=get_player&r=' + Math.random(), {
            onSuccess: function(transport) {
              if (user.timer == 0) {
                // Add timer
                user.timer = setInterval(function() { user.update_game_state(); }, 1000);
              }
              if (transport.responseJSON.status == 'ok') {
                var state = '';

                state = transport.responseJSON.data.state;
                active = transport.responseJSON.data.active;

                if (transport.responseJSON.data.name != user.player_name) {
                  $('name').update('(' + transport.responseJSON.data.name + ')');
                }
                if (transport.responseJSON.data.score != user.score) {
                  $('score').update('Points: ' + transport.responseJSON.data.score);
                }
                user.player_id = transport.responseJSON.data.handle;
                user.player_name = transport.responseJSON.data.name;
                user.score = transport.responseJSON.data.score;

                if (state != user.game_state || active != user.active) {
                  // update local variable
                  user.game_state = state;
                  user.active = active;
                  
                  // display appropriate screen
                  user.change_screen(state, active);
                }
              } else {
                user.change_screen('LOGIN');
              }
            }
          });
        },
        change_screen: function(state, active) {
          // Adjust screen
          window.scrollTo(0, 1);
          
          // Show display
          this.hide_panels(state);

          var message = '', buzz = false;
          switch (state) {
            case 'GAME_OVER':
              message = 'The next game will be starting soon.';
              break;
            case 'LOGIN':
              message = 'Welcome to Y!Jeopardy.<br />Please enter your name.';
              break;
            case 'ROUND_OVER':
              message = 'The next round will be starting soon.';
              break;
            case 'ANSWER':
              if (active) {
                message = 'You buzzed in first!<br />Please shout your answer!';
              } else {
                buzz = true;
                message = 'Someone else beat you. :(';
              }
              break;
            case 'DISPLAY_QUESTION':
              // Reset Buzzer
              this.can_buzz = false;
              $('buzzer').className = 'button';
              message = 'Please wait until the button turns red to buzz-in.';
              buzz = true;
              break;
            case 'PICK_QUESTION':
              $('pick_question').update('');
              if (active) {
                message = 'Please choose the next question.';
                this.get_questions();
              } else {
                message = 'Please wait for the next question.'
              }
              break;
            case 'BUZZ_IN':
              message = 'Press the red button if you know the answer.';
              buzz = true;
              break;
            case 'PAUSED':
              message = 'Game is currently paused, please wait.';
              break;
          }
          // Add message
          $('game_state').update(message);

          // Add buzz button
          if (buzz) {
            $('buzz').show();
            this.wait_buzz();
          } else {
            $('buzz').hide();
            this.can_buzz = false;
            $('buzzer').className = 'button';
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

            new Ajax.Request('Request.php?method=' + method, {
              onSuccess: function(transport) {
                user.ajax = false;
                $('loader').hide();
                new Effect.Opacity('loader', { from: 0.75, to: 0.0, duration: 0.1 });

                if (transport.responseJSON.status == 'ok') {
                  user.update_game_state();
                } else {
                  alert('Error: ' + transport.responseJSON.data);
                }
              }
            });
          }
        },
        get_questions: function() {
          new Ajax.Request('Request.php?method=get_categories&r=' + Math.random(), {
            onSuccess: function(transport) {
              if (transport.responseJSON.status == 'ok') {
                user.questions = transport.responseJSON.data;
                user.show_categories(-1);
              } else {
                alert('Error: ' + transport.responseJSON.data);
              }
            }
          });
        },
        show_categories: function(index) {
          if (index == -1) {
            $('pick_question').update('');
            user.questions.each(function(item, i) {
              $('pick_question').innerHTML += '<div class="button green" onclick="user.show_categories(' + i + ')">'
                                            + item['name'] + '</div>';
            });
          } else {
            $('pick_question').update('<div class="button red" onclick="user.show_categories(-1)">Back</div>');
            user.questions[index]['questions'].each(function(item, i) {
              $('pick_question').innerHTML += '<div class="button ' + (item['played'] == 0?'green':'')
                                              + '" onclick="user.pick_question(' + item['id'] + ')">'
                                              + item['points'] + '</div>';
            });
          }
        },
        wait_buzz: function() {
          if (this.game_state == 'BUZZ_IN' ||
              this.game_state == 'DISPLAY_QUESTION' ||
              this.game_state == 'ANSWER') {
            new Ajax.Request('Request.php?method=wait_buzz&r=' + Math.random(), {
              onSuccess: function(transport) {
                if (transport.responseJSON.status == 'ok') {
                  if (transport.responseJSON.data.can_buzz == true) {
                    $('buzzer').className = 'button red';
                    user.can_buzz = true;
                  } else {
                    $('buzzer').className = 'button';
                    user.can_buzz = false;
                    setTimeout(function() { user.wait_buzz(); }, 500);
                  }
                } else {
                  alert('Error: ' + transport.responseJSON.data);
                }
              }
            });
          }
        },
        pick_question: function(id) {
          this.ajax_call('pick_question&id=' + id);
        },
        login: function() {
          this.game_state = '';
          this.score = -1;
          this.player_name = -1;
          this.player_id = -1;
          this.ajax_call('new_player&name=' + $('username').value);
        },
        buzz: function() {
          if (this.can_buzz) {
            this.ajax_call('buzz');
          }
        },
        game_state: '',
        player_id: '',
        player_name: '',
        score: -1,
        active: -1,
        can_buzz: false,
        questions: {},
        timer: 0,
        ajax: false
      };

      Event.observe(document, 'dom:loaded', function() {
        user.update_game_state();
      });
    </script>

  </head>

  <body onload="window.scrollTo(0,1);">
    <div class="container">
      <div class="menu">
        <div style="float:left">
        Y! Jeopardy
        </div>
        <span id="name" style="padding-left: 10px; float:left">
        </span>
        <span class="button" onclick="user.change_screen('LOGIN')">
          Logout
        </span>
      </div>
      <div class="content">
        <br />
        <h2 id="score" style="color:#FFF;font-size:14px;"></h2>
        <h2 id="game_state"></h2>
        <br />
        <div id="login" class="panel" style="display:none">
          <fieldset class="message">
            <legend></legend>
            <span style="font-size:11px;font-weight:bold;">Name:</span>
            <br />
            <input type="text" id="username" style="width:250px;font-size:40px;font-weight:bold;border:0px;" maxlength="15" />
          </fieldset>
          <br />
          <div class="button" onclick="user.login()">
            Login
          </div>
        </div>
        <div id="buzz" style="display: none">
          <div class="button" id="buzzer" onmousedown="user.buzz()">
            BUZZ-IN
          </div>
          <br />
        </div>
        <div id="paused" class="panel" style="display:none"></div>
        <div id="game_over" class="panel" style="display: none"></div>
        <div id="round_over" class="panel" style="display: none"></div>
        <div id="pick_question" class="panel" style="display: none"></div>
        <div id="display_question" class="panel" style="display: none"></div>
        <div id="buzz_in" class="panel" style="display: none"></div>
        <div id="answer" class="panel" style="display: none"></div>
      </div>
  	</div>
    <div id="loader" style="display: none;">
      <img src="data:image/gif;base64,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" alt="loading..." />
    </div>
  </body>
</html>