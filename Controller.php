<?php
	/*------------------------- START COMMENT BLOCK -------------------------

	Controller, by Xunamius of Dark Gray (2010).
	http://darkgray.org/devs/Xunnamius

	You are free to use this code for personal/business use as
	long as this header (this whole comment block) remains
	intact and is presented as is with no modifications.

	--------------------------- END COMMENT BLOCK ---------------------------*/
	
	/*
	 * Controller Class
	 * 
	 *
	 * WARNING: Functionality of other classes invoked from within
	   this class's methods may (not likely!) be a little iffy.
	   This is ESPECIALLY true if your objects/code throws an
	   EXCEPTION (or FATAL warning) from within a run/run_AJAX
	   method of this class!
	 *
	 *
	 * This is a developer's class, used to streamline project
	   production from within programming teams using abstract
	   class functionality.
	 *
	 * This class provides developers with the tools to quickly
	   and easily filter and validate variable data, access
	   legacy superglobal data, and work together with other
	   developers whilst mitigating the various risks associated
	   with working closly on the same project.
	 *
	 * Be sure to read each method's description, look over the
	   comments, and, most importantly, check out the example
	   index page included with this class file to learn how to
	   use this PHP class properly!
	 *
	 * Note: for state/city/address validation,
	   see http://www.varnagiris.net/2006/03/11/php-ups-address-validation-tool/
	 * 
	 * Aliases:
	 *		(none)
	 *
	 * Properties:
	 *		- Abstract
	 *		- Independent Interface
	 * 		- Extensible
	 *
	 * Pertinent Directives:
	 * 		- DG_AJAX_ID 		(MIXED) determines the name (key/ID) of the request param that triggers AJAX
	 							 functionality.
	 *		- DG_AJAX_VALUE 	(MIXED) determines the value of the request param that triggers AJAX functionality.
	 *		- DG_AJAX_SCOPE 	(STRING) determines the variable scope of the request param that triggers AJAX
	 							 functionality (defaults to 'POST', all superglobal names are available as options,
								 sans underscore prefix)
	 * 
	 * Class-specific Constants (13 distinct, 17 in all):
	 *		- TYPE_STRING (0)			A valid string/returns a valid encoded string
	 *  	- TYPE_HTML_ESCAPE (13)		Escapes/encodes symbols/tags in plain text so as to protect the integrity of xHTML
	 									 pages.
	 *		- TYPE_INT (1)				Alias of TYPE_INTEGER
	 *		- TYPE_INTEGER (1)			Whole (base 10 non scientific) numbers
	 *		- TYPE_BOOL (11)			True/False
	 *		- TYPE_FLOAT (2)			_Decimal_ numbers only; no other symbols allowed (INTs are NOT FLOATs!)
	 *		- TYPE_DOUBLE (2)			Alias of TYPE_FLOAT
	 *		- TYPE_EMAIL (3)			A valid Email Address
	 *		- TYPE_VALID_EMAIL (4)		A valid Email Address that actually exists using checkdnsrr() Note: NOT 100% ACCURATE
	 									 (only checks DNS servers if domain can accept emails)! DEV BEWARE!!!
	 *		- TYPE_URI (5)				A valid URI (no scheme)
	 *		- TYPE_URL (5)				Alias of TYPE_URI
	 *		- TYPE_VALID_URI (6)		Alias of TYPE_VALID_URL
	 *		- TYPE_VALID_URL (6)		A valid URL (no scheme) that actually exists on the net and responds like a good lil'
	 									 webpage should [using Sockets + Header (slightly faster than cURL)]. Note: To prevent
										 malicious, sucky, or generally BAD pages from slowing down your site, there is a
										 timeout of 5 seconds for this procedure. If a website takes longer than 5 seconds to
										 respond, this function will report back FALSE! Moreover, if the site doesn't exist,
										 IT WILL DRASTICALLY SLOW DOWN THE LOADING OF YOUR PAGE. I recommend this only be used
										 in conjunction with AJAX, or administration pages only! DEV BEWARE!!!
	 *		- TYPE_IPHONE (7)			International Phone numbers (USA, extension, and '+' support included); does not play
	 									 well with urlencoded/raw PHP superglobal data!
	 *		- TYPE_NAME (8)				Valid name (no whacky symbols etc.); Very accurate, if I do say so myself (and I do).
	 *		- TYPE_AGE (9)				Valid age 1-120
	 *		- TYPE_TSEX (10)			Traditional sex (male/female). Herms etc. not accounted for, sorry!
	 *		- TYPE_IP (12)				Validate an IPv4/6 IP address
	 *		- TYPE_ALPHA (14)			A string can only contains letters of the alphabet
	 *		- TYPE_ALPHANUM (15)		A string can only contain numbers and letters
	 *		- TYPE_UPPER (16)			A string can only contain uppercase letters
	 *		- TYPE_LOWER (17)			A string can only contain lowercase letters
	 * 
	 * Dependencies:
	 * 		(none)
	 *
	 * Plugins:
	 * 		- DEH Class
	 *
	 * Audience: PHP 5.3.3
	 *
	 * Version: 1.41
	 */
	abstract class Controller
	{
		/* Setup runtime environment... */
		public static $original_GET = NULL;
		public static $original_POST = NULL;
		public static $original_REQUEST = NULL;
		
		protected static $engine_has_run = FALSE;
		protected static $reg_has_run = FALSE;
		protected static $ajax_has_run = FALSE;
		
		protected static $reg_method_objects = array();
		protected static $ajax_method_objects = array();
		
		/*
		 * PHP Magic Method __construct ( [ mixed $ajax_id [, mixed $ajax_value [, string $ajax_scope [, bool $loyalty = TRUE]]]] )
		 *
		 * A custom ajax_id/value/scope may be set using this custom constructor,
		   along with this object's loyalty to the DG_AJAX_* constants.
		 *
		 * @param Mixed[optional] ajax_id custom DG_AJAX_ID
		 * @param Mixed[optional] ajax_value custom DG_AJAX_VALUE
		 * @param String[optional] ajax_scope custom DG_AJAX_SCOPE
		 * @param Bool[optional] loyalty class loyalty
		 *
		 * @return nothing
		 */
		public function __construct($ajax_id=NULL, $ajax_value=NULL, $ajax_scope=NULL, $loyalty=TRUE)
		{
			// Idiot-proof update 1.1!
			if((isset($ajax_id) && !isset($ajax_value)) xor (!isset($ajax_id) && isset($ajax_value)) || (defined('DG_AJAX_ID') || !defined('DG_AJAX_VALUE')) xor (!defined('DG_AJAX_ID') || defined('DG_AJAX_VALUE')))
				$this->except('Construction of '.get_class($this).' failed. (bad params?)', E_USER_ERROR, 2);
			
			// Populate global arrays...
			self::$original_GET = (isset(self::$original_GET) ? self::$original_GET : $_GET);
			self::$original_POST = (isset(self::$original_POST) ? self::$original_POST : $_POST);
			self::$original_REQUEST = (isset(self::$original_REQUEST) ? self::$original_REQUEST : $_REQUEST);
			
			// Use the AbstractedcControlEngine function in your or the server's
			// devkey to enforce select global functionality that needs to be
			// acted on independently of the page developer's code. Only called
			// once! If needs to be called again, call from run()/run_AJAX().
			if(!self::$engine_has_run && function_exists('AbstractedcControlEngine'))
			{
				AbstractedcControlEngine($this);
				self::$engine_has_run = TRUE;
			}
			
			// Decides if AJAX will be run or not. Ajax will only be called once.
			// If it needs to be called again, call from run()/run_AJAX().
			if((defined('DG_AJAX_ID') && defined('DG_AJAX_VALUE')) || isset($ajax_id, $ajax_value))
			{
				$type = 'POST';
				if(isset($ajax_scope)) $type = strtoupper($ajax_scope);
				else if($loyalty && defined('DG_AJAX_SCOPE')) $type = strtoupper(DG_AJAX_SCOPE);
				$type = '_'.$type;
				
				if((isset($ajax_id, $ajax_value) && array_key_exists($ajax_id, $GLOBALS[$type]) && $GLOBALS[$type][$ajax_id] == ($ajax_value))
				|| ($loyalty && array_key_exists(DG_AJAX_ID, $GLOBALS[$type]) && $GLOBALS[$type][DG_AJAX_ID] == (DG_AJAX_VALUE)))
					self::$ajax_method_objects[] = $this;
			}
			
			self::$reg_method_objects[] = $this;
		}
		
		/* PHP Magic Method __destruct() */
		public function __destruct()
		{
			// __destruct() actually runs stuff around here!
			// We can also be sure that our __destruct() method will be called
			// first, since we're already inside of it!
			if(count(self::$ajax_method_objects))
			{
				if(!self::$ajax_has_run)
				{
					self::$ajax_has_run = TRUE;
					foreach(self::$ajax_method_objects as $obj)
						call_user_func(array($obj, 'run_AJAX'));
				}
			}
			
			// Note that advanced run call functionality can also be defined in
			// the run_AJAX() method if you're pro enough! 'Cause if run_AJAX() 
			// is called successfully, this run() method will NOT be called
			// automatically!
			else if(!self::$reg_has_run)
			{
				self::$reg_has_run = TRUE;
				foreach(self::$reg_method_objects as $obj)
					call_user_func(array($obj, 'run'));
			}
		}
		
		/* Used to manage exceptions within the Controller's subclasses. */
		protected function except($msg, $lvl)
		{
			if(class_exists('DEH', FALSE)) DEH::except($msg, $lvl, count(debug_backtrace(FALSE))-2);
			else
			{
				$pre = '<span style="color: red; font-weight: bold;">';
				$msg = $msg.'</span>';
				if($lvl == E_USER_ERROR) die($pre.'Error: '.$msg);
				else echo $pre.'Warning: '.$msg;
			}
			
			return NULL;
		}
		
		/*
		 * public mixed filter ( string $var , integer $type [, string $scope ] )
		 *
		 * Used to filter given variable data internally, using a variety of
		   specially-made filters. $var may either be a key within the
		   specified scope or variable data.
		 *
		 * @param String var variable data
		 * @param Int type filter type
		 * @param String scope variable scope
		 *
		 * @return Mixed filtered data in proper format/of proper type
		 */
		public function filter($var, $type, $scope=NULL)
		{
			if(isset($scope))
			{
				// Update 1.27: filter will return FALSE if the variable is undefined (instead of throwing an error)
				$scope = '_'.strtoupper($scope);
				if(array_key_exists($scope, $GLOBALS) && array_key_exists($var, $GLOBALS[$scope])) $var = $GLOBALS[$scope][$var];
				else return FALSE;
				/*else $this->except('Filtration within '.get_class($this).' failed. Could not resolve var/scope.', E_USER_ERROR, 2);*/
			}
			
			if(isset($var))
			{
				switch((int) $type)
				{
					case TYPE_STRING:
						return (string) filter_var($var, FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH | FILTER_FLAG_ENCODE_AMP);
						break;
						
					case TYPE_HTML_ESCAPE:
						return (string) filter_var($var, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_HIGH);
						break;
					
					case TYPE_BOOL:
						return filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
						break;
					
					case TYPE_INTEGER: // Fixed in update 1.3
						$var = explode('.', (string)$var, 2);
						return (int) filter_var($var[0], FILTER_SANITIZE_NUMBER_INT);
						break;
					
					case TYPE_FLOAT:
						return (float) filter_var($var, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
						break;
					
					case TYPE_EMAIL:
						return (string) filter_var($var, FILTER_SANITIZE_EMAIL);
						break;
					
					case TYPE_ALPHA:
						return preg_replace('/[^a-zA-Z]*/', '', $var);
						break;
					
					case TYPE_ALPHANUM:
						return preg_replace('/[^a-zA-Z\d]*/', '', $var);
						break;
					
					case TYPE_LOWER:
						return preg_replace('/[^a-z]*/', '', $var);
						break;
					
					case TYPE_UPPER:
						return preg_replace('/[^A-Z]*/', '', $var);
						break;
				}
			}
			
			$this->except('Filtration within '.get_class($this).' failed. (bad params?)', E_USER_ERROR, 2);
			return NULL;
		}
		
		/*
		 * public bool validate ( string $var , integer $type [, string $scope ] )
		 *
		 * Used to validate given variable data internally, using a variety of
		   specially-made filters. Returns true/false on valid/invalid. $var
		   may either be a key within the specified scope or variable data.
		 *
		 * @param String var variable data
		 * @param Int type validation type
		 * @param String scope variable scope
		 *
		 * @return Bool TRUE/FALSE on valid/invalid.
		 */
		public function validate($var, $type, $scope=NULL)
		{
			if(isset($scope))
			{
				// Update 1.27: filter will return FALSE if the variable is undefined (instead of throwing an error)
				$scope = '_'.strtoupper($scope);
				if(array_key_exists($scope, $GLOBALS) && array_key_exists($var, $GLOBALS[$scope])) $var = $GLOBALS[$scope][$var];
				else return FALSE;
				/*else $this->except('Validation within '.get_class($this).' failed. Could not resolve var/scope.', E_USER_ERROR, 2);*/
			}
			
			if(isset($var))
			{
				switch((int) $type)
				{
					case TYPE_INTEGER:
						return filter_var($var, FILTER_VALIDATE_INT) !== FALSE ? TRUE : FALSE;
						break;
					
					case TYPE_FLOAT:
						return strpos($var, '.') !== FALSE && filter_var($var, FILTER_VALIDATE_FLOAT) !== FALSE ? TRUE : FALSE;
						break;
					
					case TYPE_BOOL:
						$filt = filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? TRUE : FALSE;
						return isset($filt);
						break;
					
					case TYPE_EMAIL:
						return filter_var($var, FILTER_VALIDATE_EMAIL) ? TRUE : FALSE;
						break;
					
					case TYPE_VALID_EMAIL:
						$next = explode('@', $var);
						$next = next($next);
						return $this->validate($var, TYPE_EMAIL) && checkdnsrr($next);
						break;
					
					case TYPE_URI:
						return (bool) preg_match('/^((nntp|sftp|ftp(s)?|http(s)?|gopher|news|file|telnet):\/\/)?(([a-zA-Z0-9\._-]*([a-zA-Z0-9]\.[a-zA-Z0-9])[a-zA-Z]{1,6})|(([0-9]{1,3}\.){3}[0-9]{1,3}))(:\d+)?(\/[^:][^\s]*)?$/', $var);
						break;
					
					case TYPE_VALID_URI:
						$url = @parse_url($var); 
						if(!$url || !$this->validate($var, TYPE_URI)) return FALSE;
						
						$url = array_map('trim', $url);
						$url['port'] = (!isset($url['port'])) ? 80 : (int)$url['port'];
						
						$path = (isset($url['path'])) ? $url['path'] : '/';
						$path .= (isset($url['query'])) ? "?$url[query]" : '';
						
						if(isset($url['host']) && $url['host'] != gethostbyname($url['host']))
						{
							$fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, 5);
						
							if(!$fp) return FALSE; //socket not opened
							fputs($fp, "HEAD $path HTTP/1.1\r\nHost: $url[host]\r\n\r\n"); //socket opened
							$headers = fread($fp, 4096);
							fclose($fp);
						
							if(preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers)) return TRUE;
							else return FALSE;
						}
						 
						return FALSE;
						break;
					
					case TYPE_IPHONE:
						return (bool) preg_match('/^((\+)?[1-9]{1,4})?([-\s\.\/])?((\(\d{1,4}\))|\d{1,4})(([-\s\.\/])?[0-9]{1,6}){2,6}(\s?(ext|x)\s?[0-9]{1,6})?$/i', $var);
						break;
					
					case TYPE_NAME:
						return (bool) preg_match("/^\s*([A-Za-z]{2,4}\.?\s*)?(['\-A-Za-z]+\s*){1,2}([A-Za-z]+\.?\s*)?(['\-A-Za-z]+\s*){1,2}(([jJsSrR]{2}\.)|([XIV]{1,6}))?\s*$/i", $var);
						break;
					
					case TYPE_AGE:
						return $this->validate($var, TYPE_INT) && $var > 0 && $var <= 120;
						break;
					
					case TYPE_TSEX:
						if(in_array(strtoupper($var), array('M', 'MALE', 'F', 'FEMALE', 'BOY', 'GIRL', 'B', 'G'))) return TRUE;
						else return FALSE;
						break;
					
					case TYPE_IP:
						return filter_var($var, FILTER_VALIDATE_IP) ? TRUE : FALSE;
						break;
					
					case TYPE_ALPHA:
						return ctype_alpha($var);
						break;
					
					case TYPE_ALPHANUM:
						return ctype_alnum($var);
						break;
					
					case TYPE_LOWER:
						return ctype_lower($var);
						break;
					
					case TYPE_UPPER:
						return ctype_upper($var);
						break;
				}
			}
			
			$this->except('Validation within '.get_class($this).' failed. (bad params?)', E_USER_ERROR, 2);
			return NULL;
		}
		
		/* abstract protected run_AJAX() */
		abstract protected function run_AJAX();
		/* abstract protected run() */
		abstract protected function run();
	}
	
	/* Class-specific Constants */
	define('TYPE_STRING', 0);		// A valid string
	define('TYPE_HTML_ESCAPE', 13);	// Escapes/encodes symbols/tags in text so as to protect xHTML pages
	define('TYPE_INT', 1);			// Alias of TYPE_INTEGER
	define('TYPE_INTEGER', 1);		// Whole (base 10 non scientific) numbers
	define('TYPE_BOOL', 11);		// True/False
	define('TYPE_FLOAT', 2);		// _Decimal_ numbers only; no other symbols allowed (INTs are NOT FLOATs!)
	define('TYPE_DOUBLE', 2);		// Alias of TYPE_FLOAT
	define('TYPE_EMAIL', 3);		// A valid Email Address
	define('TYPE_VALID_EMAIL', 4);	// A valid Email Address that actually exists using checkdnsrr()
	define('TYPE_URI', 5);			// A valid URI (no scheme)
	define('TYPE_VALID_URI', 6);	// Alias of TYPE_VALID_URL
	define('TYPE_URL', 5);			// Alias of TYPE_URI
	define('TYPE_VALID_URL', 6);	// A valid URL (no scheme) that actually exists on the net
	define('TYPE_IPHONE', 7);		// International Phone numbers (USA, extension, and '+' support included)
	define('TYPE_NAME', 8);			// Valid name (no whacky symbols etc.)
	define('TYPE_AGE', 9);			// Valid age 1-120
	define('TYPE_TSEX', 10);		// Traditional sex (male/female). Herms etc. not accounted for, sorry!
	define('TYPE_IP', 12);			// Validate an IPv4&6 IP address
	
	/* Added in update 1.4 */
	define('TYPE_ALPHA', 14);		// Letters only
	define('TYPE_ALPHANUM', 15);	// Letters and numbers only
	define('TYPE_UPPER', 16);		// Letters are all uppercase
	define('TYPE_LOWER', 17);		// Letters are all lowercase
	
	// Protect page from direct access
	if(count(get_included_files()) <= 1) die('<h1 style="color: red; font-weight: bold;">No.</h1>');
?>