<?php
	/*------------------------- START COMMENT BLOCK -------------------------

	STR, by Xunamius of Dark Gray (2010).
	http://darkgray.org/devs/Xunnamius

	You are free to use this code for personal/business use as
	long as this header (this whole comment block) remains
	intact and is presented as is with no modifications.

	--------------------------- END COMMENT BLOCK ---------------------------*/
	
	/*
	 * STR Class
	 * 
	 * Provides the developer with a vast array of string-related methods.
	 *
	 * Aliases:
	 *		(none)
	 *
	 * Properties:
	 *		- Independent
	 *		- Instantiation Unnecessary (static)
	 * 		- Extensible
	 *
	 * Pertinent Directives:
	 *		(none)
	 * 
	 * Class-specific Constants:
	 *		(none)
	 *
	 * Dependencies:
	 * 		(none)
	 *
	 * Plugins:
	 * 		(none)
	 *
	 * Audience: PHP 5.3.3
	 *
	 * Version: 1.5
	 */
	class STR
	{
		/* PHP Magic Method __construct() in Static mode */
		public function __construct(){}
		
		/*
		 * public string random( [ $ss_string_length = 0 [, integer $no_repeat_level = 0 [, $sha = FALSE [, array $session_secret_seed ]]]] )
		 *
		 * Generates a random string. Great for encryption keys. Random length
		   unless otherwise specified. Random seed unless otherwise specified.
		 *
		 * $no_repeat_level specifies the level of repeat allowed within the
		   returned string. Zero (0; default) means there are no limits on
		   repeats. One (1) denotes characters are not allowed to repeat
		   next to each other (in front of or behind). Two (2) denotes
		   characters are not allowed to repeat within the sequence at all.
		 *
		 * Note that if the end of the hex table is reached (due to a
		   no_repeat_level of three (3)), the remaining didit count will
		   be padded with zeros.
		 *
		 * @param Integer[optional] ss_string_length returned string length
		 * @param Array[optional] session_secret_seed generation seed
		 * @param Bool[optional] sha true/false sha1() results
		 * @param Integer[optional] no_repeat_level character repeat level
		 *
		 * @return String random string
		 */
		public static function random($ss_string_length=0, $no_repeat_level=0, $sha=FALSE, array $session_secret_seed=NULL)
		{
			$no_repeat_level = abs($no_repeat_level);
			if(!isset($session_secret_seed))
				$session_secret_seed = array('!','@','#','$','%','^','&','*','(',')','_','-','+','=','1','2','3','4','5','6','7','8','9','0','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
			
			// Populate hex_table with 0-9 & setup the array to populate the last (session_secret_multiplier) characters of hex_table
			$hex_table = array();
			
			shuffle($session_secret_seed);
			if(!$no_repeat_level)
			{
				$session_secret_multiplier = rand(10, 50);
				
				// Finish the creation of the hex_table
				for($i=0; $i<$session_secret_multiplier; ++$i) $hex_table[] = $session_secret_seed[rand(0, count($session_secret_seed)-1)];
			}
			
			else $hex_table = $session_secret_seed;
			shuffle($hex_table);
			
			// Generate real session_secret_seed from hex_table
			$session_secret_seed = array();
			
			$new_len = FALSE;
			if($ss_string_length <= 0)
			{
				$ss_string_length = rand(10, 50);
				$new_len = TRUE;
			}
			
			for($i=0; $i<$ss_string_length; ++$i)
			{
				if(count($hex_table) > 0)
				{
					$r = rand(0, count($hex_table)-1);
					$target = count($session_secret_seed);
					
					if(!$no_repeat_level || !$target) $session_secret_seed[] = $hex_table[$r];
					else if($no_repeat_level == 1 && $session_secret_seed[$target-1] != $hex_table[$r]) $session_secret_seed[] = $hex_table[$r];
					else if($no_repeat_level > 1 && !in_array($hex_table[$r], $session_secret_seed)) $session_secret_seed[] = $hex_table[$r];
					else $ss_string_length++;
					
					if($no_repeat_level > 1) unset($hex_table[$r]);
					shuffle($hex_table);
				}
				
				else if(!$new_len) $session_secret_seed[] = 0;
				else break;
			}
			
			$return = implode($session_secret_seed);
			$return = $sha ? sha1($return) : $return;
			return $return;
		}
		
		/*
		 * public string break_words( $string [, $length = 35 [, $wrapString = ' ' ]] )
		 *
		 * Breaks long words found in strings apart (with respect to entities and
		   xHTML tags).
		 *
		 * @param String string target string
		 * @param Integer[optional] length max contiguous length of a string
		 * @param String[optional] wrapString string to wrap chars with
		 *
		 * @return String formatted string data
		 */
		public static function break_words($string, $length=35, $wrapString=' ')
		{
			$wrapped = '';
			$word = '';
			$in_html = FALSE;
			$in_entity = FALSE;
			$string = (string) $string;
			
			for($i=0, $j=strlen($string); $i<$j; ++$i)
			{
				$char = $string[$i];
				
				// We're entering an xHTML tag/entity...
				if($char == '<' || ($char == '&' && preg_match('/^&#?[a-z0-9]{1,6};/i', substr($string, $i))))
				{
					if(!empty($word))
					{
						$wrapped .= $word;
						$word = '';
					}
				
					if($char == '<') $in_html = TRUE;
					else $in_entity = TRUE;
					$wrapped .= $char;
				}
				
				// We're leaving an xHTML tag/entity...
				else if(in_array($char, array('>', ';')))
				{
					if($char == '>') $in_html = FALSE;
					else $in_entity = FALSE; // Bug fixed in update 1.3
					$wrapped .= $char;
				}
				
				// If we're inside an xHTML tag, append to wrapped string
				else if($in_html || $in_entity) $wrapped .= $char;
				
				// Whitespace/tab/end of line
				else if($char == ' ' || $char == "\t" || $char == "\n")
				{
					$wrapped .= $word.$char;
					$word = '';
				}
				
				// Check chars
				else
				{
					$word .= $char;
					
					if(strlen($word) > $length)
					{
						$wrapped .= $word.$wrapString;
						$word = '';
					}
				}
			}
			
			if($word != '') $wrapped .= $word;
			return $wrapped;
		}
		
		/*
		 * public string limit_words( $string [, $word_count_limit = 7 [, $char_length_limit = 35 [, $cutoff_non_alphanumeric = FALSE [, $separator = ' ' ]]]] )
		 * | Added in update 1.3, fixed in update 1.4, and added $cutoff_non_alphanumeric in update 1.41
		 *
		 * Limits the amount of words allowed in a string (words are determined
		   by the separator). Ignores xHTML tags and entities.
		 *
		 * @param String string target string
		 * @param Integer[optional] word_count_limit maximum amount of words allowed
		 * @param String[optional] total_length_limit max contiguous character length limit of the string
		 * @param String[optional] separator string that separates words in your string
		 *
		 * @return Array (your formatted string, your formatted string unmodified by cutoff_non_alphanumeric)
		 */
		public static function limit_words($string, $word_count_limit=7, $char_length_limit=35, $cutoff_non_alphanumeric=FALSE, $separator=' ')
		{
			// Warning: there is some higher-level logic in here. You should probably check out the echoed version instead!
			$in_html = FALSE;
			$in_entity = FALSE;
			
			$string = (string) $string;
			$terminators = array('>', ';');
			
			$word_count = 1;
			$char_count = 0;
			$new_string = '';
			$last_word_pos = 0;
			
			for($i=0, $j=strlen($string); $i<$j; ++$i)
			{
				$char = $string[$i];
				
				$special = FALSE;
				if($char == '<') $special = TRUE;
				
				if(!$in_html && !$in_entity)
				{
					if(!$special) $char_count++;
					
					if($char == $separator || ($separator == ' ' && ($char == "\t" || $char == "\n")))
					{
						$word_count++;
						$last_word_pos = $i;
						if($word_count > $word_count_limit) break;
					}
					
					else if($char_count > $char_length_limit)
					{
						if($last_word_pos) $new_string = substr($new_string, 0, $last_word_pos);
						break;
					}
				}
				
				if($special || ($char == '&' && preg_match('/^&#?[a-z0-9]{1,6};/i', substr($string, $i))))
				{
					if($char == '<') $in_html = TRUE;
					else $in_entity = TRUE;
				}
				
				else if(in_array($char, $terminators))
				{
					if($char == '>') $in_html = FALSE;
					else $in_entity = FALSE;
				}
				
				$new_string .= $char;
			}
			
			$new_string = trim($new_string);
			$endpos = strlen($new_string)-1;
			$endchar = $new_string[$endpos];
			
			// Added in update 1.41; fixed in 1.5
			if($cutoff_non_alphanumeric && !in_array($endchar, $terminators) && !ctype_alnum($endchar)) $return = substr($new_string, 0, $endpos);
			else $return = $new_string;
			
			return $return;
		}
	}
	
	/* Class-specific Constants */
	
	// Protect page from direct access
	if(count(get_included_files()) <= 1) die('<h1 style="color: red; font-weight: bold;">No.</h1>');
?>