/*
---
description: Addition of the unserialize() method to the HashNav class. Requires the HashNav core to function.

license: MIT-style license

authors:
- Xunnamius

requires:
- core/1.3: [Cookie]
- HashNav/core

provides: [HashNav.unserialize]
...
*/

/* documentation and updates @ http://github.com/Xunnamius/HashNav */
(function() // Private
{
	HashNav.implement({
		
		unserialize: function(restoreParadigm, fireEventOnNav, cookieName, secure, customdata)
		{
			var buffer = {}, metadata = null, cookieName = cookieName || this.options.externalConstants[1], version = this.$_hidden_pseudoprivate_getState()[1];
			
			if(secure !== true) secure = false;
			if(restoreParadigm !== false) restoreParadigm = true;
			if(fireEventOnNav !== false) fireEventOnNav = true;
			
			if(customdata) Object.each(customdata, function(item, key){ buffer[key] = (key == 'version' ? item : JSON.decode(item, secure)); });
			else
			{
				buffer.version = JSON.decode(Cookie.read(cookieName+'version'), secure);
				if(!buffer.version) return false;
				metadata = buffer.version['s'];
				
				Object.each(metadata, function(value, key)
				{
					if(value > 1)
					{
						while(value--) buffer[key] = Cookie.read(cookieName+key+value) + (buffer[key] ? buffer[key] : '');
						buffer[key] = JSON.decode(buffer[key], secure);
					}
					
					else buffer[key] = JSON.decode(Cookie.read(cookieName+key), secure);
					
					if(typeof(buffer[key]) == 'undefined') return false;
				});
			}
			
			if(!buffer.version || !version || version.toString() != buffer.version['v'].toString()) return false;
			if(buffer.options && buffer.options.cookieOptions && buffer.options.cookieOptions.document === null) buffer.options.cookieOptions.document = document;
			
			if(restoreParadigm)
			{
				if(Object.every(buffer, function(item){ return !!item; }) && buffer.version && buffer.options && buffer.state)
				{
					this.$_hidden_pseudoprivate_setState(buffer.state, buffer.version.v);
					this.setOptions(buffer.options);
					
					if(this.$_hidden_history_loaded)
					{
						this.replace(buffer.history);
						this.navigateTo(-1, fireEventOnNav);
					}
					
					return true;
				}
				
				return false;
			}
			
			return buffer;
		}
		
	});
})();