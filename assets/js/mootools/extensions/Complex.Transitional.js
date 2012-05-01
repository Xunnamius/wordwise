/*
---
description: A view manager for MooTools that enables a "complex" yet beautiful and enjoyable user experience.

license: MIT-style license

authors:
- Xunnamius

requires:
- core/1.3.2+

provides: [Fx.Transitional]
...
*/

/* documentation and updates @ http://github.com/Xunnamius/Complex.Transitional */
(function() // Private
{
	var Version = 1.3;
	
	this.Fx.Transitional = new Class(
	{
		Implements: Chain,
		
		// TODO: Implement Options and use setOptions
		initialize: function(innerWrapper, screenContainer)
		{
			this.iw = innerWrapper || $('inner_wrapper');
			this.container = $('screen_container') || screenContainer;
			
			if(!this.iw || !this.container)
				throw 'ViewManager received incomplete initialization arguments. Neither can be null.';
				
			this.iw.setStyles({ top: 0, left: 0 });
			
			// Only one element should be a child of the inner element. That is our active element!
			this.active = this.iw.getChildren()[0];
			this.iw.set('tween', { duration: 375, transition: 'sine:out', link: 'cancel' });
			
			this.lastShift = null;
		},
		
		/* TODO: optimize these methods -- remove redundancy! */
		
		// Shifts the viewport left
		// target_id can be an ID or an element (reference)
		shiftLeft: function(target_id, delay)
		{
			(function()
			{
				var original = this.iw.getSize();
				target_id = $(target_id) || $(target_id[0]) || target_id[0] || target_id;
	
				if(this.container.match(target_id.getParent()))
				{
					// Expand iw to be twice its current width
					this.iw.setStyle('width', original.x*2);
					
					// add new content from the container
					target_id.dispose().inject(this.iw);
					
					// morph iw: top -> original width, then reset
					this.iw.get('tween').
					start('left', -original.x).
					chain(function()
					{
						if(this.active) this.active.dispose().inject(this.container);
						this.active = target_id;
						this.iw.setStyles({ width: (100 * parseFloat(original.x)/parseFloat(this.iw.getParent().getSize().x))+'%', left: 0 });
						
						while(this.callChain());
						this.lastShift = 'left';
						return this;
					}.bind(this));
				}
				
				else throw 'ViewManager received an invalid target_id "'+target_id+'" (not a direct descendant of the container?)';
			}).delay(Math.abs(delay||0), this);
		},
		
		// Shifts the viewport right
		shiftRight: function(target_id, delay)
		{
			(function()
			{
				var original = this.iw.getSize();
				target_id = $(target_id) || $(target_id[0]) || target_id[0] || target_id;
				
				if(this.container.match(target_id.getParent()))
				{
					// Expand iw to be twice its current width
					this.iw.setStyle('width', original.x*2);
					
					// add new content from the container
					target_id.dispose().inject(this.iw, 'top');
					this.iw.setStyle('left', -original.x);
				
					this.iw.get('tween').
					start('left', 0).
					chain(function()
					{
						if(this.active) this.active.dispose().inject(this.container);
						this.active = target_id;
						this.iw.setStyle('width', (100 * parseFloat(original.x)/parseFloat(this.iw.getParent().getSize().x))+'%');
						
						while(this.callChain());
						this.lastShift = 'right';
						return this;
					}.bind(this));
				}
					
				else throw 'ViewManager received an invalid target_id "'+target_id+'" (not a direct descendant of the container?)';
			}).delay(Math.abs(delay||0), this);
		},
		
		// Shifts the viewport upwards
		shiftUp: function(target_id, delay)
		{
			(function()
			{
				var original = this.iw.getSize();
				target_id = $(target_id) || $(target_id[0]) || target_id[0] || target_id;
				
				if(this.container.match(target_id.getParent()))
				{
					// Expand iw to be twice its current height
					this.iw.setStyle('height', original.y*2);
					
					// add new content from the container
					target_id.dispose().inject(this.iw);
					
					// morph iw: top -> original height, then reset
					this.iw.get('tween').
					start('top', -original.y).
					chain(function()
					{
						if(this.active) this.active.dispose().inject(this.container);
						this.active = target_id;
						this.iw.setStyles({ height: (100 * parseFloat(original.y)/parseFloat(this.iw.getParent().getSize().y))+'%', top: 0 });
						
						while(this.callChain());
						this.lastShift = 'up';
						return this;
					}.bind(this));
				}
				
				else throw 'ViewManager received an invalid target_id "'+target_id+'" (not a direct descendant of the container?)';
			}).delay(Math.abs(delay||0), this);
		},
		
		// Shifts the viewport downwards
		shiftDown: function(target_id, delay)
		{
			(function()
			{
				var original = this.iw.getSize();
				target_id = $(target_id) || $(target_id[0]) || target_id[0] || target_id;
				
				if(this.container.match(target_id.getParent()))
				{
					// Expand iw to be twice its current height
					this.iw.setStyle('height', original.y*2);
					
					// add new content from the container
					target_id.dispose().inject(this.iw, 'top');
					this.iw.setStyle('top', -original.y);
				
					this.iw.get('tween').
					start('top', 0).
					chain(function()
					{
						if(this.active) this.active.dispose().inject(this.container);
						this.active = target_id;
						this.iw.setStyle('height', (100 * parseFloat(original.y)/parseFloat(this.iw.getParent().getSize().y))+'%');
						
						while(this.callChain());
						this.lastShift = 'down';
						return this;
					}.bind(this));
				}
				
				else throw 'ViewManager received an invalid target_id "'+target_id+'" (not a direct descendant of the container?)';
			}).delay(Math.abs(delay||0), this);
		},
		
		// Shifts the viewport randomly
		shift: function(target_id, delay)
		{
			var rand = Number.random(0, 10000);
			
			if(rand >= 7500) rand = this.shiftUp(target_id, delay);
			else if(rand >= 5000) rand = this.shiftRight(target_id, delay);
			else if(rand >= 2500) rand = this.shiftDown(target_id, delay);
			else rand = this.shiftLeft(target_id, delay);
			
			return rand;
		},
		
		//* Added in 1.3
		shiftOpposite: function(target_id, delay)
		{
			switch(this.lastShift)
			{
				case 'left':
					this.shiftRight(target_id, delay);
					break;
				case 'right':
					this.shiftLeft(target_id, delay);
					break;
				case 'up':
					this.shiftDown(target_id, delay);
					break;
				case 'down':
					this.shiftUp(target_id, delay);
					break;
				default:
					this.shift(target_id, delay);
					break;
			}
		},
		
		// Cancels the current animation and any chained functions
		//* Added in 1.2
		cancel: function()
		{
			this.iw.get('tween').cancel();
			this.clearChain();
		}
	});
})();