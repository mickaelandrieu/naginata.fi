/***************
 * NAGINATA.fi *
 *************** 
 * sendanmaki.js
 */

// Google Analytics for naginata.fi
var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-2643697-14']);
_gaq.push(['_trackPageview']);

(function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
// Enought about Google Analytics

$(document).ready(function() {
    sendanmaki.domReady();
	mdrnzr.domReady();
    /*
     * imgareaselect
     *
     * CSS3 stuff
     * image-orientation: 0deg
     *
     * transform: translate(80px, 80px) scale(1.5, 1.5) rotate(45deg);
     *
     * h1
{
rotation-point:50% 50%;
rotation:180deg;
}
     */
});


var sendanmaki = {
    /**
     * Is the user logged in to the backend?
     * The initial value is in body data.
     */
    isLoggedIn: 0,

    /**
     * Email address of the current user against OpenID.
     */
    userEmail: '',
	
	/**
	 * Keep alive interval.
	 * 3 minutes
	 */
	keepAlive: (1000 * 60 * 3),

    /**
     * Colors to be used as a feedback of an ongoing AJAX call.
     */
    colors: {
        green : '#39B54A',
        blue : '#75B2F1',
        red : '#F13A1C',
        yellow : '#FAE534'
    },

    /**
     * This shall be run on domReady in order to initiate
     * all the handlers needed.
     */
    domReady: function() {
        var fData = $('footer').data();
        sendanmaki.isLoggedIn = fData.isLoggedIn;
        sendanmaki.userEmail = fData.userEmail;
        
		/*
        $('article a:not(.mediathumb a, .imagelist a)').click(function() {
            console.log('something was clicked');
            return false;
        });
		*/
	
		// href has link to actual page, rel has inline link
        $('.mediathumb a:has(img)').click(function() {
			sendanmaki.mediaThumbClick($(this));
			return false;
		});
		$('.imagelist a').colorbox({
			rel: 'several'
		});

        // Track ColorBox usage with Google Analytics
        $(document).on('cbox_complete', function() {
            var href = $.colorbox.element().attr("href");
            if (href) {
                _gaq.push(['_trackPageview', href]);
            }
        });

        // Open modal for logging in via OAuth and edit pages.
        $('a[href="#contribute"]').click(function() {
            sendanmaki.contributeClick();
            return false;
        });

        // Logged in can most likely edit content, thus AJAX.
        $('#colorbox form').live('submit', function() {
            if (sendanmaki.isLoggedIn) {
                sendanmaki.submitEditForm($(this));
            }
            else {
                sendanmaki.submitLoginForm($(this));
            }
            return false;
        });

        $('#colorbox input[type="button"][name="close"]').live('click', function() {
            $.colorbox.close();
        });

        $(window).on('beforeunload', function() {
            console.log('beforeunload');
            //return false;
        });
		
		// Finally check if body data is set. It is used only for messaging
		var msg = $('body').data('msgLoginSuccess'); // 1 or 0
		if (typeof msg !== 'undefined') {          
			sendanmaki.showAppMessage(msg ? 'loginSuccess' : 'loginFailure');
		}

        // So sad, but in 2012 there still needs to be a keep alive call
        setInterval(function() {
            $.post('/keep-session-alive', {foo: 'bar'}, function(received, status) {
                console.log(received.answer); // seconds
            }, 'json');
        }, sendanmaki.keepAlive);
    },
	
	/**
	 * Handle a click on a media thumbnail.
	 * It can be a Flickr image, Vimeo or Youtube video.
	 */
	mediaThumbClick: function($a) {
		var data = $a.data();
		
		// Tell Analytics
		_gaq.push(['_trackPageview', $a.attr('href')]);
		
		if (data.type && data.type == 'flash') {
			// Vimeo has size data, Youtube does not
			var w = $('#wrapper').width();
			var h = w * 0.75;
			if (data.width) {
				w = $a.data('width');
			}
			if (data.height) {
				h = $a.data('height');
			}
			var player = $.flash.create({
				swf: data.url,
				height: '100%',
				width: '100%'
			});
			$.colorbox({
				html: player,
				title: $a.attr('title'),
				height: h,
				width: w,
				scrolling: false
			});
		}
		else {
			$.colorbox({
				href: data.url,
				photo: true
			});
		}
    },

    /**
     * Callback for submitting the contribution form.
     */
    submitEditForm: function($form) {
        var data = {
            lang: 'fi',
            page: location.pathname,
            content: $form.children('textarea[name="content"]').text()
        };

        var orig = $form.css('background-color');
        $form.css('background-color', sendanmaki.colors.blue);

        $.post($form.attr('action'), data, function(received, status){
            console.log('status' + status);
            console.dir(received);
            if (status != 'success') {
                $form.css('background-color', sendanmaki.colors.red);
            }
            else if (received.answer) {
                // 1 or true
                $form.css('background-color', sendanmaki.colors.green);
                setTimeout(function() {
                    $form.css('background-color', orig);
                    $.colorbox.close();
                }, 2 * 1000);
            }
            else {
                $form.css('background-color', sendanmaki.colors.yellow);
            }
        }, 'json');
    },

    /**
     * Submit the OpenID login form to our backend that will redirect to the 
     * OpenID providers web site.
     */
    submitLoginForm: function($form) {
        var data = {
            lang: 'fi',
            page: location.pathname,
            identifier: $('input[name="identifier"]').val()
        };
        console.log('about to submit login form');
        console.dir(data);

        // This will be redirected to the OpenID provider site
        $.post($form.attr('action'), data, function(received, status) {
            console.log('status' + status);
            console.dir(received);
            if (status == 'success' && received.answer) {
                location.href = received.answer;
            }
        }, 'json');
    },

    /**
     * Callback for a click on the #contribute link located in the footer.
     * This should open login form or edit form, depending of the login status.
     */
    contributeClick: function() {
        var opts = {
            title: $(this).attr('title'),
            modal: true
        };
        var form = sendanmaki.loginForm;
        if (sendanmaki.isLoggedIn) {
            form = $(sendanmaki.editForm).children('textarea').text($('article').html()).parent().get(0);
        }
        else {
            //
        }
        opts.html = form;
        console.dir(opts);
        /*
        var originalClose = $.colorbox.close;
        $.colorbox.close = function(){
            var response;
            if ($('#cboxLoadedContent').find('form').length > 0) {
                response = confirm('Do you want to close this window?');
                if (!response) {
                    return; // Do nothing.
                }
            }
            originalClose();
        };
        */

        // How about call back for content update?
        $.colorbox(opts);
        if (sendanmaki.isLoggedIn) {
            $('textarea').wymeditor({
                lang: 'fi',
                skin: 'compact',
                updateSelector: 'input[type="submit"]',
                updateEvent: 'mousedown',
                postInit: function(wym) {
                    $('iframe').on('keyup', function() {
                        console.log('iframe keyup event occurred');
                        wym.update();
                    });
                }
            });
        }
        else {
            $('input[name="identifier"]').focus();
			// TODO: send button should be disabled until a valid OpenID is entered
        }
    },
    
    /**
     * Show a message that was set via temporary session variable
     * body shall contain all the message data
     * @param   msg    Data item to be used
     */
    showAppMessage: function(msg) {
        var text = $('body').data(msg);
        console.log('showAppMessage. msg: ' + msg + ', text: ' + text);
        if (typeof text !== 'undefined') {
			// Show colorbox
			$.colorbox({
				html: '<h1 class="appmessage">' + text + '</h1>',
				modal: true,
				scrolling: false,
				onComplete: function() {
					// Hide automatically after 4 seconds
					setTimeout(function() {
						$.colorbox.close();
					}, 4 * 1000);
				}
			});
        }
    },

    /**
     * data = {x1, x2, y1, y2, width, heigth, note, url}
     */
    showImgNote: function(data) {
        console.log('showNote.');
		console.dir(data);
        var parent = $('img[src="' + data.url + '"]').parent();
        var div = $('<div class="note"></div>');
        var tpo = parent.position();
        div.css('left', data.x1 + tpo.left).css('top', data.y1 + tpo.top);
        var area = $('<span class="notearea"></span>');
        var note = $('<span class="notetext">' + data.note + '</span>');
        area.css('width', data.width).css('height', data.height);
        div.append(area, note);
        parent.append(div).show(400);
    },

    /**
     * A form to be shown in colorbox when editing an article content.
     */
    editForm: '<form action="/update-article" method="post">' +
        '<textarea name="content"></textarea>' +
        '<input type="submit" value="Lähetä" />' +
        '<input type="button" name="close" value="Sulje" />' +
        '</form>',

    /**
     * Login form. Please note that this uses OpenID.
     */
    loginForm: '<form action="/authenticate-user" method="post">' +
        '<label>Email (OpenID identification)<input type="email" name="identifier" /></label>' +
        '<input type="submit" value="Lähetä" />' +
        '<input type="button" name="close" value="Sulje" />' +
        '</form>'
};

/**
 * Modernizr test results
 * https://raw.github.com/paazmaya/PaazioTools/master/JavaScript/modernizr.htm
 * Also uses SWFObject.
 */
var mdrnzr = {
    results: {},
	interval: 60 * 60 * 24 * 7 * 2, // 2 weeks
	key: 'last-modernizr',
	
    /**
     * This shall be run on domReady in order to check against localStorage
     * when was the last time the results were sent, if any.
	 * In case localStorage is not available, not supported or two weeks old,
	 * it will be send again.
     */
    domReady: function() {
		var update = false;
		if (localStorage) {
			var previous = localStorage.getItem(mdrnzr.key);
			console.log('previous: ' + previous + ', mdrnzr.interval: ' + mdrnzr.interval + ', previous < $.now() + mdrnzr.interval: ' + (previous < $.now() + mdrnzr.interval));
			if ((previous && previous < $.now() + mdrnzr.interval) || !previous) {
				update = true;
			}
		}
		else {
			update = true;
		}
		
		if (update) {
			mdrnzr.loopThru(Modernizr);
			mdrnzr.results['modernizr'] = mdrnzr.results;
			console.dir(mdrnzr.results['modernizr']);
			mdrnzr.results['useragent'] = navigator.userAgent;
			mdrnzr.results['flash'] = $.flash.version.string;
			mdrnzr.sendData();
		}
	},

    loopThru: function(obj, prefix) {
        if (!prefix) {
            prefix = '';
        }
        for (var i in obj) {
            if (obj.hasOwnProperty(i)) {
                var type = (typeof obj[i]);
                if (type == "boolean" || type == "string") {
                    mdrnzr.results[prefix + i] = obj[i];
                }
                else if (type == "object") {
                    mdrnzr.loopThru(obj[i], i + '.');
                }
            }
        }
    },

    sendData: function() {
        $.post('/receive-modernizr-statistics', mdrnzr.results, function(incoming, status) {
            // Thank you, if success
			if (localStorage && status == 'success') {
				localStorage.setItem(mdrnzr.key, $.now());
			}
        }, 'json');
    }
};
