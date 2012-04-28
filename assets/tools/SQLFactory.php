<?php
	/*------------------------- START COMMENT BLOCK -------------------------

	SQL (class), by Xunamius of Dark Gray (2010).
	http://darkgray.org/devs/Xunnamius

	You are free to use this code for personal/business use as
	long as this header (this whole comment block) remains
	intact and is presented as is with no modifications.

	--------------------------- END COMMENT BLOCK ---------------------------*/
	
	/*
	 * SQL Class
	 * 
	 * WARNING: DO NOT RENAME FILE.
	 *
	 * RDBMS factory class that dynamically loads different SQL drivers at runtime.
	 * 
	 * Note that all DB drivers should be available in the assets/DB_drivers/ directory.
	 * If they are anywhere else, they will not be found! Said drivers must also conform
	   conform to the following standards:
	   		- Must be a singleton-type class.
	   		- House a properly formatted public static method throw_errors()
			   that accepts one boolean as an argument
			- House a properly formatted public static method get_instance()
			   that returns to the caller an instance of the "driver"
			   object.
			- Must NOT display any errors (sans fatal) if the value passed
			   to throw_errors() is _not_ TRUE.
			- Class file should be named [name].php while the class itself
			   should be named [name]dbDriver where [name] is replaced with
			   the actual name of the database that the class is used to
			   interface with.
			- Class should be independent (no dependencies) and be safely
			   extensible.
			- Class must have NO aliases!
	 *
	 * All relational functionality in respect to manipulation and interrogation of the database
	   is done by the driver itself, and not this generic factory class. Don't confuse the two.
	 *
	 * Accepts a string as the name of the driver (typically something like 'MySQL' or 'Oracle').
	 *
	 * Better than straight PDO in my opinion - PDO neuters and then rapes MySQLi, for example.
	 * 
	 * Aliases:
	 *		(none)
	 *
	 * Properties:
	 *		- Final
	 * 		- Factory
	 *
	 * Pertinent Directives:
	 * 		- DG_DEBUG_MODE
	 * 
	 * Class-specific Constants:
	 *		(none)
	 * 
	 * Dependencies:
	 * 		+ DEH
	 *
	 * Plugins:
	 * 		(none)
	 *
	 * Audience: PHP 5.3.3
	 *
	 * Version: 1.22
	 */
	final class SQL
	{
		/* PHP Magic Method __clone() in Factory mode */
		private function __clone(){}
		
		/* PHP Magic Method __construct() in Factory mode */
		private function __construct(){}
		
		/*
		 * public static resource load_driver ( string $driver )
		 * 
		 * Attempts to load a SQL driver from the "DB_drivers" folder
		   and pass DG_DEBUG_MODE error permissions to it. Returns an
		   instance of the driver or NULL on failure.
		 *
		 * @param String driver name of the SQL driver to load
		 *
		 * @return Resource driver instance or NULL on failure
		 */
		public static function load_driver($driver)
		{
			$path = strpos($driver, '/') === FALSE && strpos($driver, '\\') === FALSE ? substr(__FILE__, 0, -14).'DB_drivers/'.$driver.'.dbd' : $driver;
			if(is_string($driver) && file_exists($path) && include_once $path)
			{
				$instance = @call_user_func(array($driver.'dbDriver', 'get_instance'));
				
				if(isset($instance))
				{
					$response = $instance->throw_errors(defined('DG_DEBUG_MODE')?DG_DEBUG_MODE:TRUE); // Update 1.22
					
					if(isset($response) && $response === true) return $instance;
					else DEH::except('The "'.$driver.'" driver was found, but contained no properly formatted "throw_errors()" method with which to preform construction!', E_USER_WARNING, count(debug_backtrace(FALSE))-2);
				}
				
				else DEH::except('The "'.$driver.'" driver was found, but contained no properly formatted "get_instance()" method with which to preform construction!', E_USER_WARNING, count(debug_backtrace(FALSE))-2);
			}
			
			else DEH::except('SQL Driver "'.$driver.'" was not found', E_USER_ERROR, count(debug_backtrace(FALSE))-2);
			return NULL;
		}
	}
	
	// Protect page from direct access
	if(count(get_included_files()) <= 1) die('<h1 style="color: red; font-weight: bold;">No.</h1>');