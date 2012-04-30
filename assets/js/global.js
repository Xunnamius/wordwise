/************************************************
  Copyright Dark Gray 2012. All Rights Reserved!
*************************************************/
// TODO: find if the next word without the letter exists, and then the next one
!function($)
{
	window.addEvent('domready', function()
	{
		var gForm = $('grabber_form'),
			setWord = function(data)
			{
				hashNav.navigateTo('/'+data.term);
				$$('head title').set('text', 'Definition for "'+data.term+'"');

				$('grabber_data-head').set('text', data.term);
				$('grabber_data-pos').set('text', data.partofspeech);
				$('grabber_data-ex').set('text', data.examples).setStyle('display', 'block');
				$('grabber_data-def').set('html', data.definition);
				$('grabber_data-rel').set('html', data.related ? ('Related: '+data.related) : '');
			},
			
			clearWord = function()
			{
				hashNav.navigateTo('/');
				$$('head title').set('text', 'Welcome to wordwise!');
				
				$('grabber_data-head').set('text', '');
				$('grabber_data-pos').set('text', '');
				$('grabber_data-ex').set('text', '');
				$('grabber_data-def').set('html', '');
				$('grabber_data-rel').set('html', '');
			},
			
			setStateLoading = function(msg)
			{
				clearState();
				$('loadingState').addClass('alert-info').getElement('h3').set('text', 'Loading State (active)');
				if(msg) console.info('Loading:', msg);
			},
			
			setStateError = function(msg)
			{
				clearState();
				$('errorState').addClass('alert-error').getElement('h3').set('text', 'Error State (active)');
				if(msg) console.error('Error:', msg);
			},
			
			setStateSuccess = function(msg)
			{
				clearState();
				$('successState').addClass('alert-success').getElement('h3').set('text', 'Success State (active)');
				if(msg) console.log('Success:', msg);
			},
			
			clearState = function()
			{
				$('loadingState').removeClass('alert-info').getElement('h3').set('text', 'Loading State (inactive)');
				$('errorState').removeClass('alert-error').getElement('h3').set('text', 'Error State (inactive)');
				$('successState').removeClass('alert-success').getElement('h3').set('text', 'Success State (inactive)');
			};
		
		// Handshake
		// If auth state or pw saved, goto main screen
		// Otherwise, goto register/login/fp (henceforth rlfp) screen
		// If, on main screen, "New" is clicked, popup new word prompt and addword
		// If, on main screen, "Get" is clicked, fetchRandomWord and change active word
		// Be sure to ensure loading/error/success states occur between these
		
		var API = 
		{
			predata: { api: 1001, token: null },
			
			_raw: new Request.JSON({
				url: 'api.php',
				timeout: 15000,
				link: 'cancel',
				funct: Function.from(),
	
				onTimeout: function(){ console.log('to'); window.fireEvent('APITimeout', this); },
				onCancel: function(){ console.log('c'); window.fireEvent('APICanceled', this); },
				onError:  function(xhr){ console.log('e', xhr); window.fireEvent('APIXHRError', { xhr: xhr, obj: this }); },
				
				onSuccess: function(data)
				{
					if(!data || !data.result || data.result != 'ok')
						setStateError(data.data);
					else
						this.options.funct(data);
				}
			}),
			
			handshake: function(fn)
			{
				API._raw.setOptions({
					funct: function(d)
					{
						API.predata.token = d.data.token;
						if(fn) fn(d);
					}
				}).get({ action: 'handshake' });
			},
			
			authenticate: function(usr, pwd, fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({
					action: 'auth',
					username: usr,
					password: pwd.toSHA1()
				});
			},
			
			register: function(usr, pwd, email, fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({ action: 'register' });
			},
			
			addWord: function(fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({ action: 'addWord' });
			},
			
			fetchRandomWord: function(fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({ action: 'fetchRandomWord' });
			},
			
			unauth: function(fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({ action: 'unauth' });
			}
		};
		
		var hashNav = new HashNav(), get = API._raw.get;
		API._raw.get = function(obj){ get.call(this, Object.merge({}, API.predata, obj)); }
		
		/*API.handshake(function(d)
		{
			console.log('preauth: {preauth}, token: {token}'.substitute(d.data));
			API.authenticate('Xunnamius', 'test', function(e)
			{
				console.log('data: {data}'.substitute(e));
				API.fetchRandomWord(function(f)
				{
					console.log('data:', f.data);
				});
			});
		});*/
		
		$('login_form').enable = function()
		{
			this.getElements('div :not(#login_logout)').set('disabled', false);
			this.getElement('#login_logout').set('disabled', true);
		};
		
		$('login_form').disable = function()
		{
			this.getElements('div :not(#login_logout)').set('disabled', true);
			this.getElement('#login_logout').set('disabled', false);
		};
		
		$('login_form').toggle = function()
		{
			if(this.retrieve('enabled'))
			{
				this.store('enabled', false);
				this.disable();
			}
			
			else
			{
				this.store('enabled', true);
				this.enable();
			}
		};
		
		$('register_form').toggle
		
		var afterAuth = function()
		{
			$$('#login_form div :not(#login_logout)').set('disabled', true);
			$$('#register_form div *').set('disabled', true);
			$$('#grabber_form div *').set('disabled', false);
			$('login_logout').set('disabled', false);
			clearState();
		};
		
		setStateLoading();
		
		API.handshake(function(d)
		{
			if(d.data.preauth) afterAuth();
			else
			{
				$$('#login_form div :not(#login_logout)').set('disabled', false);
				$$('#register_form div *').set('disabled', false);
				clearState();
			}
		});
	});
}(document.id);