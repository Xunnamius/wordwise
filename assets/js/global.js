/************************************************
  Copyright Dark Gray 2012. All Rights Reserved!
*************************************************/
// TODO: find if the next word without the letter exists, and then the next one

!function($)
{
	window.addEvent('domready', function()
	{
		// Viewport controls
		var VIEW = 
		{
			// Mask (overlay) control
			mask:
			{
				visible: false,
				
				show: function()
				{
					if(!this.visible)
					{
						$('pop_overlay').setStyle('display', 'block');
						this.visible = true;
					}
				},
				
				hide: function()
				{
					if(this.visible)
					{
						$('pop_overlay').setStyle('display', 'none');
						this.visible = false;
					}
				}
			},
			
			// Clear any popped elements (not the overlay)
			clearPopped: function(){ $$('.pop').setStyle('display', 'none'); }
		};
		
		// API controls
		var API = 
		{
			predata: { api: 1001, token: null },
			
			_raw: new Request.JSON({
				url: 'api.php',
				method: 'get',
				link: 'chain',
				timeout: 15000,
				funct: Function.from(),
				
				onTimeout: function(){ window.fireEvent('APITimeout', this); },
				onError:  function(xhr){ window.fireEvent('APIXHRError', { xhr: xhr, obj: this }); },
				
				onSuccess: function(data)
				{
					if(!data || !data.result || data.result != 'ok')
						window.fireEvent('APIError', { data: data.data, obj: this });
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
				}).get({
					action: 'register',
					username: usr,
					password: pwd.toSHA1(),
					email: email
				});
			},
			
			addWord: function(word, fn)
			{
				API._raw.setOptions({
					funct: function(d){ if(fn) fn(d); }
				}).get({ action: 'addWord', word: word });
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
		
		// Finish setting up our environment
		var __get = API._raw.get;
		VIEW.viewManager = new Fx.Transitional($('inner_container'), $('state_container'));
		API._raw.get = function(obj){ __get.call(this, Object.merge({}, API.predata, obj)); }
		
		/* Take advantage of splash screen... do all the heavy computing here */
		
		// Make the .pop_*s "poppable"
		$$('.pop').each(function(item)
		{
			item.pop = function(content, asHTML)
			{
				VIEW.clearPopped();
				VIEW.mask.show();
				this.setStyle('display', 'block');
				
				var child = this.getElement('.content');
				
				if(child)
				{
					if(asHTML) child.set('html', content);
					else child.set('text', content);
				}
				
				window.fireEvent('resize');
			}
		});
		
		// Resize behavior
		window.addEvent('resize', function()
		{
			$$('body').setStyle('height', window.getSize().y);
			$$('div.container, #outer_container, #inner_container, .view-state').setStyle('height', window.getSize().y);
			
			$$('.absolute-center').each(function(child)
			{
				var parentSize = child.getParent() == document.body ? window.getSize() : child.getParent().measure(function(){ return this.getSize() }),
					childSize = child.measure(function(){ return this.getSize() });
				
				child.setStyles({
					left: (parentSize.x-childSize.x)/2,
					top: (parentSize.y-childSize.y)/2
				});
			});
			
			var size = $('inner_container').getSize();
			
			$$('.view-state').each(function(view)
			{
				view.setStyle('width', size.x);
			});
		});
		
		// Events that pop .pop_error
		window.addEvent('APITimeout', function(e)
		{
			API._raw.cancel();
			$('pop_error').pop('The server is taking too long to respond.<br /><br />Please refresh the page and try again.', true);
			if(console && console.error) console.error('APITimeout:', e);
		});
		
		window.addEvent('APIXHRError', function(e)
		{
			API._raw.cancel();
			$('pop_error').pop('A nasty low-level error has occured. Please report this! (code: xhr)', false);
			if(console && console.error) console.error('APIXHRError:', e);
		});
		
		window.addEvent('APIError', function(e)
		{
			API._raw.cancel();
			var msg = e.data && e.data.message && e.data.type ?
				e.data.message :
				'Sorry about that. Please refresh the page. (code: api-unknown)';
			
			if(e.data.type == 'sessionMismatch')
				$$('#pop_error ._close').addEvent('click', function(e){ e.stop(); location.reload(); });
			
			$('pop_error').pop(msg, false);
			if(console && console.error) console.error('APIError:', e);
		});
		
		// Events that pop .pop_loading
		window.addEvent('APILoading', function(e)
		{
			$('pop_loading').pop();
		});
		
		// Events that pop .pop_success
		window.addEvent('APISuccess', function(e)
		{
			$('pop_success').pop(e.message);
		});
		
		// Configure each state...
		$$('#state_nexus ._login').addEvent('click', function(){ VIEW.viewManager.shiftLeft('state_login'); });
		$$('#state_nexus ._register').addEvent('click', function(){ VIEW.viewManager.shiftRight('state_register'); });
		$$('#state_nexus ._fp').addEvent('click', function(){ VIEW.viewManager.shiftDown('state_fp'); });
		
		$$('._back').addEvent('click', function(){ VIEW.viewManager.shiftOpposite('state_nexus'); });
		$$('._close').addEvent('click', function(){ VIEW.clearPopped(); VIEW.mask.hide(); });
		
		$$('#state_login form').addEvent('submit', function(e)
		{
			e.stop();
			var usr = this.getElement('._username'),
				pwd = this.getElement('._password');
			
			if(!usr.get('value'))
				return usr.highlight('#E44');
			if(!pwd.get('value'))
				return pwd.highlight('#E44');
			
			window.fireEvent('APILoading');
			API.authenticate(usr.get('value'), pwd.get('value'), function(e)
			{
				pwd.set('value', '');
				VIEW.viewManager.shiftUp('state_main');
				VIEW.clearPopped();
				VIEW.mask.hide();
			});
		});
		
		$$('#state_register form').addEvent('submit', function(e)
		{
			e.stop();
			var usr = this.getElement('._username'),
				pwd = this.getElement('._password'),
				email = this.getElement('._email');
			
			if(!usr.get('value'))
				return usr.highlight('#E44');
			if(!pwd.get('value') || pwd.get('value').length < 5)
				return pwd.highlight('#E44');
			if(!email.get('value'))
				return email.highlight('#E44');
			
			window.fireEvent('APILoading');
			API.register(usr.get('value'), pwd.get('value'), email.get('value'), function(e)
			{
				VIEW.viewManager.chain(function()
				{
					$$('#state_login form ._username').set('value', usr.get('value'));
					$$('#state_login form ._password').set('value', pwd.get('value'));
					usr.set('value', '');
					pwd.set('value', '');
					email.set('value', '');
				}).shiftUp('state_login');
				
				window.fireEvent('APISuccess', { message: 'Congrats for being awesome, {u}, for you have successfully registered!'.substitute({u:usr.get('value')}) });
			});
		});
		
		$$('#state_main ._logout').addEvent('click', function(e)
		{
			window.fireEvent('APILoading');
			API.unauth(function()
			{
				API.handshake(function()
				{
					setWord(true);
					VIEW.viewManager.chain(function(){ VIEW.clearPopped(); VIEW.mask.hide(); }).shiftOpposite('state_nexus');
				});
			});
		});
		
		var divtarget = $$('#state_main form > div')[0],
			divval = divtarget.getStyle('margin-top'),
			returnState = function()
			{
				divtarget.getParent().getElement('._new').set('text', 'New');
				divtarget.tween('margin-top', divval);
				divtarget.store('visible', false);
			},
			
			setWord = function(data)
			{
				if(data === true)
				{
					$('grabber_data').addClass('empty');
					$('grabber_data-head').set('text', 'Welcome to WordWise!');
					$('grabber_data-pos').set('text', '');
					$('grabber_data-def').set('html', '(tap "Get" if you\'re ready, or "New" if you\'re just starting out)');
					$('grabber_data-ex').set('text', '').setStyle('display', 'none');
				}
				
				else
				{
					$('grabber_data').removeClass('empty');
					$('grabber_data-head').set('text', data.term.capitalize());
					$('grabber_data-pos').set('text', data.partOfSpeech);
					$('grabber_data-def').set('html', data.definition);
					
					if(data.example)
						$('grabber_data-ex').set('text', 'Example(s): '+data.example).setStyle('display', 'block');
					else
						$('grabber_data-ex').set('text', '').setStyle('display', 'none');
				}
			};
		
		divtarget.set('tween', { duration: 1000, link: 'cancel' });
		
		$$('#state_main ._get').addEvent('click', function(e)
		{
			window.fireEvent('APILoading');
			
			if(divtarget.retrieve('visible'))
				returnState();
			
			var tries = 3, apicall = function()
			{
				API.fetchRandomWord(function(word)
				{
					if(!word.data || !word.data.term)
						$('grabber_data-ex').set('text', 'Psst: try pressing "New" instead.').setStyle('display', 'block');
					else if(tries-- && word.data.term.toLowerCase() == $('grabber_data-head').get('text').toLowerCase())
						return apicall();
					else
						setWord(word.data);
						
					VIEW.clearPopped();
					VIEW.mask.hide();
				});
			}
			
			apicall();
		});
		
		$$('#state_main form').addEvent('submit', function(e)
		{
			e.stop();
			
			if(divtarget.retrieve('visible'))
			{
				var word = this.getElement('._word');
				
				if(!word.get('value'))
					return word.highlight('#E44');
				
				returnState();
				
				window.fireEvent('APILoading');
				API.addWord(word.get('value'), function(e)
				{
					window.fireEvent('APISuccess', { message: 'You successfully added the word "{w}" to your list.'.substitute({w:word.get('value')}) });
					word.set('value', '');
				});
			}
			
			else
			{
				divtarget.tween('margin-top', 0);
				divtarget.store('visible', true);
				this.getElement('._new').set('text', 'Add it!');
			}
		});
		
		window.fireEvent('resize');
		
		// Let's get this show on the road!
		API.handshake(function(d)
		{
			if(d.data.preauth)
				VIEW.viewManager.shiftDown('state_main', 3000);
			else
				VIEW.viewManager.shiftUp('state_nexus', 3000);
		});
	});
}(document.id);