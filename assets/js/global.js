/************************************************
  Copyright Dark Gray 2012. All Rights Reserved.
*************************************************/

!function($)
{
	window.addEvent('domready', function()
	{
		var gForm = $('grabber_form'),
			setWord = function(data)
			{
				gForm.enable();
				$('grabber_data')
					.removeClass('alert-error')
					.removeClass('alert-success')
					.addClass('alert-info');
					
				$('grabber_data-head').set('text', data.term);
				$('grabber_data-pos').set('text', data.partofspeech);
				$('grabber_data-ex').set('text', data.examples).setStyle('display', 'block');
				$('grabber_data-def').set('text', data.definition);
				$('grabber_data-rel').set('text', data.related ? ('Related: '+data.related) : '');
			},
			
			setError = function(error)
			{
				gForm.enable();
				$('grabber_data')
					.removeClass('alert-info')
					.removeClass('alert-success')
					.addClass('alert-error');
					
				$('grabber_data-head').set('text', 'Error :(');
				$('grabber_data-pos').set('text', '');
				$('grabber_data-ex').set('text', '');
				$('grabber_data-def').set('text', error);
				$('grabber_data-rel').set('text', 'Refresh to try again!');
			},
			
			setLoading = function()
			{
				gForm.disable();
				$('grabber_data')
					.removeClass('alert-info')
					.removeClass('alert-error')
					.addClass('alert-success');
					
				$('grabber_data-head').set('text', 'Loading your word...');
				$('grabber_data-pos').set('text', '');
				$('grabber_data-ex').set('text', '');
				$('grabber_data-def').set('text', '');
				$('grabber_data-rel').set('text', '(this should only take a second)');
			};
		
		gForm.disable = function()
		{
			$('grabber_word').set('disabled', true);
			$('grabber_grab').set('disabled', true);
		};
		
		gForm.enable = function()
		{
			$('grabber_word').set('disabled', false);
			$('grabber_grab').set('disabled', false);
		};
		
		gForm.addEvent('submit', function(e)
		{
			e.stop();
			setLoading();
			
			(function(){
				var word = $('grabber_word');
				if(!word.get('value'))
					return word.highlight('#d44');
					
				new Request.JSON({
					url: 'dict.api.php',
					timeout: 15000,
					
					onTimeout: function(){ setError('Operation timed out.') },
					onCancel: function(){ setError('Operation was canceled.') },
					onError:  function(xhr){ setError('XHR Error: '+xhr) },
					
					onSuccess: function(data)
					{
						if(data.error)
							setError(data.error+' (inline error)');
						else
							setWord(data);
					}
				}).get('api=1001&word='+encodeURIComponent(word.get('value')));
			}).delay(500, this);
		});
		
		$('grabber_word').set('placeholder', 'Your word here...');
		gForm.enable();
	});
}(document.id);