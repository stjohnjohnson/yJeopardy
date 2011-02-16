<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 */
/* Configuration */
require_once '../Config.php';

/**
 * AJAX Call Test Environment
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
    <title>Testing</title>
     <script type="text/javascript" src="a/prototype.js"></script>
     <script type="text/javascript" src="a/scriptaculous/scriptaculous.js"></script>
     <script type="text/javascript">
       var url = 'Request.php';

        var STATE = '',
            PLAYER = '';

       function updateGameState() {
         new Ajax.Request(url + '?method=get_game_state&r=' + Math.random(), {
            onSuccess: function(transport) {
              if (transport.responseJSON.status == 'ok') {
                if (STATE != transport.responseJSON.data.game_state) {
                  STATE = transport.responseJSON.data.game_state;
                  $('game_state').update(STATE);
                }
                if (transport.responseJSON.data.handle) {
                  if (PLAYER != transport.responseJSON.data.handle) {
                    PLAYER = transport.responseJSON.data.handle;
                    $('active_player').update(transport.responseJSON.data.name + '(' + PLAYER + ')');
                  }
                }
              } else {
                $('game_state').update('error');
                $('active_player').update(transport.responseJSON.data);
              }
            }
          });
       }

       function openLink(link) {
         $('test').src = 'Request.php?wssid=<?php echo ADMIN_SECRET; ?>&method=' + link + '&r=' + Math.random();
         return false;
       }

       function login() {
         var name = prompt('User Name?');
         if (name == null)
           return false;

         $('test').src = 'Request.php?override=true&method=new_player&name=' + name + '&r=' + Math.random();
         return false;
       }

       setInterval(updateGameState, 2000);

     </script>
  </head>
  <body>
    <div style="text-align:center;font-size:20px;">
      State: <span style="font-weight:bold;" id="game_state"></span> &amp;
      Player: <span style="font-weight:bold;" id="active_player"></span>
      <br/>
      <a href="#" onclick="return login()">Login</a>
    </div>
    <br style="clear:both;"/>
    <div style="float: left; width: 200px;">
<?php
  $link = 'Request.php?wssid=' . ADMIN_SECRET . '&method=';
  $array = array('get_categories',
    'get_players',
    'get_game_state',
    'get_active_player',
    'get_question',
      // Player Queries
    'get_player',
    'buzz',
    'wait_buzz',
      // Admin Queries
    'start_buzzing',
    'skip_question',
    'answer_wrong',
    'answer_right',
    'start_round',
    'next_round',
    'new_active_player',
    'get_answer',
    'cache_status',
    'cache_clear',
    'mass_login',
    'pause_game');

  for ($i = 1; $i <= 30; $i++) {
    echo '<a href="#" onclick="return openLink(\'pick_question&id=' . $i . '\')">Pick Question: ' . $i . '</a><br/>';
  }
?>
    </div>
    <div style="float: left; width: 200px;">
<?php
  foreach ($array as $a) {
    echo '<a href="#" onclick="return openLink(\'' . $a . '\')">' . $a . '</a><br/>';
  }
?>
    </div>
    <div style="float: left; width: 500px;">
      <iframe name="test" id="test" style="width: 500px; height:500px;"></iframe>
    </div>
  </body>
</html>