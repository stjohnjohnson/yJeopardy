<?php
/**
 * Copyright (c) 2010 Yahoo! Inc. All rights reserved. Copyrights licensed under the MIT License.
 *
 * User Interface
 *
 * @category Web
 * @package yJeopardy
 * @author Alex Ivashchenko <alexi@yahoo-inc.com>
 * @author Suresh Jayanty <jayantys@yahoo-inc.com>
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 */
// Provide alternate interface
if (isset($_GET['alt'])) {
  header('Location: user.php');
  exit(1);
}
// only iPhone section for now
?><!doctype html>  
<html lang="en">
<head>
 <title>y!jeopardy</title>
 <meta name="apple-mobile-web-app-capable" content="yes"/>
 <meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no, width=device-width"/>
 <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
 <link type="text/css" href="s/m.css" rel="stylesheet" />
 <script type="text/javascript" src="a/zepto/zepto.js"></script>
 <script type="text/javascript" src="a/zepto/event.js"></script>
 <script type="text/javascript" src="a/zepto/ajax.js"></script>
 <script type="text/javascript" src="a/zepto/detect.js"></script>
 <script type="text/javascript">
/* <![CDATA[ */

var ORIENT  = '',
    PLAYER  = {},
    QUESTION = {},
    SCORE   = null,
    STATE   = '',
    ACTIVE  = false,
    CURRENT = null,
    CATS    = {},
    CANBUZZ = false;

$(document).ready(function()
{
    // {{{ android hacks
    if ($.os.android)
    {
        $('#loading').find('.sign').text('@');
    }
    // }}}

    // {{{ nice things
    $('div.submit').bind('touchstart', function(e)
    {
        $(this).addClass('pressed');
    });
    $('div.submit').bind('touchend', function(e)
    {
        $(this).removeClass('pressed');
    });
    // }}}

    // {{{ add observers
    // pcik question
    $('#question_submit').bind('click', function(e)
    {
        var question;

        if (question = $('ul.points', $('#question')).first().find('li.selected').get(0))
        {
            YJ.flip('back');
            YJ.request('pick_question', {id: question.id.replace('q_', '')}, function(r)
            {
                if (r.status == 'ok')
                {
                    YJ.State.DISPLAY_QUESTION.start();
                }
                else
                {
                    YJ.State.ERROR();
                }
            });
        }
        else
        {
            alert('Please select Category and Question first');
        }
    });

    // buzz!
    $('#buzz_submit').bind('touchstart mousedown', function(e)
    {
        if (!$(this).hasClass('enabled'))
        {
            return true;
        }

        $(this).removeClass('enabled');

        YJ.request('buzz', {}, function(r)
        {
            if (r.status == 'ok')
            {
                if (!r.data.buzzed)
                    alert('Somebody buzzed first');
            }
            else
            {
                YJ.State.ERROR();
            }
        });
    });

    // }}}

    $(document.body).bind('touchmove', function(e)
    {
        e.preventDefault();
    });

    delay(main, 1.5);
});

YJ =
{
    defaults:
    {
        path: 'Request.php'
    },

    live: function(method, interval, callback)
    {
        periodical = function()
        {
            YJ.request(method, {}, callback);
        };

        setInterval(periodical, interval*1000);
//        periodical();
    },

    flip: function(side)
    {
        if ($('#page').hasClass('flipped'))
        {
            if (side != 'back') $('#page').removeClass('flipped');
        }
        else
        {
            if (side != 'front') $('#page').addClass('flipped');
        }
    },

    request: function(method, params, callback)
    {
        params = $.extend({method: method}, params || {})

        $.ajax({
            url: this.defaults.path+'?'+this.query(params),
            dataType: 'json', // what response type you accept from the server ('json', 'xml', 'html', or 'text')
            success: callback
        });
    },

    query: function(params)
    {
        var r = [],
            add = function(key, value)
            {
                r.push(encodeURIComponent(key)+'='+encodeURIComponent(value));
            };

        // deal with arrays or objects only
        if (params instanceof Array)
            for (var i=0,s=params.length;i<s;i++) add(params[i].key, params[i].value);
        else
            for (var key in params) add(key, params[key]);

        return r.join('&').replace(/%20/g, "+");
    }
}

YJ.State =
{
    INIT:
    {
        start: function()
        {
            YJ.State.SCORE();

            YJ.live('get_game_state', 0.5, function(r)
            {
                var oState  = STATE,
                    oActive = ACTIVE;

                if (r.status == 'ok')
                {
                    if (PLAYER.handle && PLAYER.handle == r.data.handle)
                        ACTIVE = true;
                    else
                        ACTIVE = false;

                    if (oActive != ACTIVE)
                        YJ.State.ACTIVE();

                    if (STATE != r.data.game_state)
                    {
                        STATE = r.data.game_state;

                        YJ.State.SCORE();

                        if (YJ.State[oState] && YJ.State[oState].stop) YJ.State[oState].stop(STATE);
                        if (YJ.State[STATE] && YJ.State[STATE].start) YJ.State[STATE].start(oState);
                    }
                }
            });
        },
        stop: function()
        {
        }
    },
    ACTIVE: function()
    {
        if (ACTIVE)
        {
            CURRENT = $('#question').first();
            $('#inactive').hide();
            YJ.State.SCORE();
        }
        else
        {
            CURRENT = $('#inactive').first();
            $('#question').hide();
        }

        switch (STATE)
        {
            case 'PICK_QUESTION':
                CURRENT.show();
                break;

            case 'ANSWER':
                if (ACTIVE)
                    $('#answer').show();
                else
                    CURRENT.show();
                break;
        }

    },
    SCORE: function()
    {
        YJ.request('get_player', {}, function(r)
        {
            if (r.status == 'ok')
            {
                PLAYER = r.data;
                if (SCORE !== PLAYER.score)
                {
                    SCORE = PLAYER.score;
                    $('#user').html(PLAYER.name + '<span class="score">Score: '+PLAYER.score+'</span>').addClass('active');
                }
            }
        });
    },
    QUESTION: function()
    {
        if (QUESTION.category)
            $('#picked_question').html(QUESTION.category + '<span class="points">'+QUESTION.points+'</span>').addClass('active');
        else
            $('#picked_question').removeClass('active');
    },
    ERROR: function()
    {
        $('#loading').hide();
        $('#error').show();
        YJ.flip('back');
    },

    PICK_QUESTION:
    {
        start: function(oState)
        {
            $('.card.content').hide();

            // clear previous question
            QUESTION = {};
            YJ.State.QUESTION();

            YJ.State.ACTIVE();

            // reset
            $('ul.categories', $('#question')).first().html('').removeClass('ready').unbind();
            $('ul.points', $('#question')).first().html('').removeClass('ready').unbind();

            YJ.request('get_categories', {}, function(r)
            {
                var available;

                if (r.status == 'ok')
                {
                    // reset
                    var categories = $('ul.categories', $('#question')).first().html('').removeClass('ready').unbind(),
                        points = $('ul.points', $('#question')).first().html('').removeClass('ready').unbind();

                    CATS = {};

                    // add new
                    for (var i=0; i<r.data.length; i++)
                    {
                        available = false;
                        CATS[r.data[i].id] = r.data[i];
                        for (var j=0; j<r.data[i].questions.length; j++)
                        {
                            if (!r.data[i].questions[j].played) available = true;
                            if (i==0)
                            {
                                points.html(points.html() + '<li>'+r.data[i].questions[j].points+'</li>');
                            }
                        }
                        categories.html(categories.html() + '<li id="c_'+r.data[i].id+'" class="'+((available) ? 'available' : '')+'">'+r.data[i].name+'</li>');
                    }

                    // add observers
                    categories.bind('click', function(e)
                    {
                        var category,
                            points,
                            cell,
                            catId;

                        if (e.target.tagName == 'LI')
                        {
                            category = $(e.target);

                            if (!category.hasClass('available'))
                            {
                                alert('All questions in this category have already been played');
                                return true;
                            }

                            if (!category.hasClass('selected'))
                            {
                                // clean up others
                                points = $('ul.points', $('#question')).first().removeClass('active');
                                $('ul.categories', $('#question')).first().find('li').removeClass('selected');

                                catId = category.get(0).id.replace('c_', '');

                                if (CATS[catId])
                                {
                                    category.addClass('selected');

                                    for (var i=0; i<CATS[catId].questions.length; i++)
                                    {
                                        cell = points.find('li').get(i);
                                        cell.id = 'q_'+CATS[catId].questions[i].id;
                                        if (!CATS[catId].questions[i].played)
                                        {
                                            cell.className = 'available';
                                        }
                                        else
                                        {
                                            cell.className = '';
                                        }
                                    }

                                    points.addClass('active');
                                }
                                else
                                {
                                    alert('Try another category');
                                }
                            }
                        }
                    });
                    points.bind('click', function(e)
                    {
                        var question;

                        if (!points.hasClass('active'))
                        {
                            alert('Please choose category first.');
                            return true;
                        }

                        if (e.target.tagName == 'LI')
                        {
                            question = $(e.target);

                            if (!question.hasClass('available'))
                            {
                                alert('This question has already been played');
                                return true;
                            }

                            if (!question.hasClass('selected'))
                            {
                                // clean others
                                points.find('li').removeClass('selected');
                                question.addClass('selected');
                            }
                        }
                    });

                    // set ready flag
                    categories.addClass('ready');
                    points.addClass('ready');

                    YJ.flip('front');
                }
                else
                {
                    YJ.State.ERROR();
                }
            });
        },
        stop: function()
        {
            YJ.flip('back');
            $('#question').hide();
            $('#inactive').hide();
        }
    },
    DISPLAY_QUESTION:
    {
        start: function()
        {
            YJ.request('get_question', {}, function(r)
            {
                if (r.status == 'ok')
                {
                    QUESTION = r.data;
                    YJ.State.QUESTION();

                    $('#display_question').show().find('.cell').html(QUESTION.question);
                    if (QUESTION.dd)
                        $('#display_question').addClass('doubles');
                    else
                        $('#display_question').removeClass('doubles');

                    YJ.flip('front');

                    // add waiting for the buzz
                    YJ.State._BUZZING();
                }
                else
                {
                    YJ.State.ERROR();
                }
            });
        },
        stop: function()
        {
            YJ.flip('back');
            $('#display_question').hide();
        }
    },

    _BUZZING: function()
    {
        if (CANBUZZ)
        {
            $('#buzz_submit').addClass('enabled');
        }
        else
        {
            $('#buzz_submit').removeClass('enabled');

            // add waiting for the buzz
            YJ.request('wait_buzz', {}, function(r)
            {
                if (r.status == 'ok')
                {
                    if (r.data.can_buzz)
                    {
                        CANBUZZ = true;
                        if (STATE != 'BUZZ_IN')
                            YJ.State.BUZZ_IN.start();
                        else
                            YJ.State._BUZZING();
                    }
                    else
                    {
                        CANBUZZ = false;
                        // try again
                        if (['BUZZ_IN', 'ANSWER', 'DISPLAY_QUESTION'].indexOf(STATE) != -1) YJ.State._BUZZING();
                    }
                }
                else
                {
                    YJ.State.ERROR();
                }
            });
        }
    },

    BUZZ_IN:
    {
        start: function()
        {
            STATE = 'BUZZ_IN';

            $('#buzzing').show();

            YJ.State._BUZZING();

            YJ.flip('front');
        },
        stop: function()
        {
            CANBUZZ = false;
            $('#display_question').hide();
            $('#buzzing').hide();
            YJ.flip('back');
        }
    },
    ANSWER:
    {
        start: function()
        {
            YJ.request('get_question', {}, function(r)
            {
                if (r.status == 'ok')
                {
                    QUESTION = r.data;
                    YJ.State.QUESTION();
                }
            });

            YJ.State.ACTIVE();

            YJ.State._BUZZING();

            YJ.flip('front');
        },
        stop: function()
        {
            YJ.State.SCORE();
            YJ.flip('back');
            $('#answer').hide();
        }
    },
    PAUSED:
    {
        start: function()
        {
            $('#loading').hide();
            $('#pause').show();
            YJ.flip('back');
        },
        stop: function()
        {
            YJ.flip('front');
            delay(function()
            {
                $('#pause').hide();
                $('#loading').show();
            }, 1);
        }
    },
    GAME_OVER:
    {
        start: function()
        {
            YJ.State.SCORE();
            $('#loading').hide();
            $('#game_over').show();
            YJ.flip('back');
        },
        stop: function()
        {
            YJ.flip('front');
            delay(function()
            {
                $('#game_over').hide();
                $('#loading').show();
            }, 1);
        }
    },
    ROUND_OVER:
    {
        start: function()
        {
            YJ.State.SCORE();
            $('#loading').hide();
            $('#round_over').show();
            YJ.flip('back');
        },
        stop: function()
        {
            YJ.flip('front');
            delay(function()
            {
                $('#round_over').hide();
                $('#loading').show();
            }, 1);
        }
    }
}

var delay = function(f, t)
{
    setTimeout(f, t*1000);
};

var main = function()
{
    // re hide cards
    $('.hidden').hide().removeClass('hidden');

    // initial request
    YJ.request('get_player', {}, function(r)
    {
        if (r.status == 'ok')
        {
            PLAYER = r.data;
            YJ.State.INIT.start();
        }
        else
        {
            $('#login').show();

            $('#login_submit').bind('click', function(e)
            {
                e.preventDefault();

                if ($('#login_player').attr('value').length > 0)
                {
                    YJ.flip('back');

                    delay(function(){
                    YJ.request('new_player', {name: $('#login_player').attr('value')}, function(r)
                    {
                        if (r.status != 'ok')
                        {
                            alert(r.data);
                            YJ.flip('front');
                            return true;
                        }

                        PLAYER = r.data;
                        YJ.State.INIT.start();

                        YJ.flip('front');
                    });
                    }, 1);

                }
                else
                {
                    alert('Please enter your name');
                }

                return false;
            });

            YJ.flip('front');
        }
    });

};

/* ]]> */
 </script>
 <link rel="shortcut icon" href="favicon.ico">
</head>
<body><div id="container">
<div id="user"></div>
<div id="picked_question"></div>
<div id="page" class="flipped">
<!-- loading page -->
<div id="loading" class="card status">
 <span class="sign rotating">&#9881;</span>
</div>
<!-- login page -->
<div id="login" class="card content hidden">
 <h1>Enter your name</h1>
 <input id="login_player" class="text" type="text" name="name" value="" autocorrect="off" placeholder="Name" />
 <span class="sign yahoo">Y!</span>
 <div id="login_submit" class="submit">Continue</div>
</div>
<!-- inactive -->
<div id="inactive" class="card content hidden">
 <div class="message"><div class="cell">Please wait for your turn</div></div>
</div>
<!-- question page -->
<div id="question" class="card content hidden">
 <h1>Pick the question</h1>
 <ul class="categories"></ul>
 <ul class="points"></ul>
 <div id="question_submit" class="submit">Continue</div>
</div>
<!-- display question page -->
<div id="display_question" class="card content hidden">
 <h1>... and the question is:</h1>
 <div class="question"><div class="cell"></div></div>
</div>
<!-- buzzing page -->
<div id="buzzing" class="card content hidden">
 <h1>Buzz time!</h1>
 <div id="buzz_submit" class="submit">Buzz</div>
</div>
<!-- answer page -->
<div id="answer" class="card content hidden">
 <div class="message"><div class="cell">Now you can answer the question</div></div>
</div>
<!-- error page -->
<div id="error" class="card content error hidden">
 <h1>Houston, We've Got a Problem!</h1>
</div>
<!-- pause -->
<div id="pause" class="card status hidden">
 <h1>Pause</h1>
 <span class="sign rotating">&#9881;</span>
</div>
<!-- game_over -->
<div id="game_over" class="card status error hidden">
 <h1>Are you game?</h1>
</div>
<!-- round_over -->
<div id="round_over" class="card status error hidden">
 <h1>How about another round?</h1>
</div>
<!-- done -->
<div id="preloader"><span class="droid">yjeopardy</span><span class="yahoo">yjeopardy</span></div>
</div>
</div</body>
</html>
