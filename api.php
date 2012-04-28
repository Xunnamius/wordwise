<?php require_once '~devkey.php'; ?>
<?php require_once 'assets/tools/DeveloperErrorHandler.php'; ?>
<?php require_once 'assets/tools/Controller.php'; ?>
<?php require_once 'assets/tools/SQLFactory.php'; ?>
<?php require_once 'assets/tools/STR.php'; ?>
<?php
	class Xunnamius extends Controller
	{
		# Constants to modify
		const WORD_MAXLENGTH  = 45;
		const WORD_MINLENGTH  = 3;
		const USERNAME_MAXLEN = 50;
		const USERNAME_MINLEN = 4;
		const PASSWORD_HEXLEN = 40;
		
		const REQUEST_TIME_PERIOD 	= 10; # seconds
		const REQUEST_MAX_REQUESTS 	= 10; # requests per ^ seconds
		const USER_TIMEOUT_PERIOD	= 60; # seconds; you bad little boy you :P
		
		public $MY_HOST;
		private $sql;
		
		public function __construct()
		{
			$this->MY_HOST = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
			$this->sql = SQL::load_driver('MySQL');
			parent::__construct();
		}
		
		protected function run(){ header('Location: '.$this->MY_HOST); }
		
		protected function run_AJAX()
		{
			session_start();
			
			# Set up our environment...
			if(!isset($_SESSION['USR']))
			{
				$_SESSION['USR'] = array(
					'REQUEST' => array(
						'time' => 0,
						'count' => 0
					),
					
					'FLAGS' => array(
						'timeout' => FALSE,
						'banned' => FALSE,
						'authenticated' => FALSE
					),
					
					'DATA' => array(
						'username' => NULL,
						'email' => NULL,
						'id' => NULL,
						'token' => NULL
					)
				);
			}
			
			# Ban/timeout the banned/timed-out
			if($_SESSION['USR']['FLAGS']['banned'])
				RESULT::userBanned();
			
			else if($_SESSION['USR']['FLAGS']['timeout'])
			{
				if(time() - $_SESSION['USR']['REQUEST']['time'] >= self::USER_TIMEOUT_PERIOD)
				{
					$_SESSION['USR']['FLAGS']['timeout'] = FALSE;
					$_SESSION['USR']['REQUEST']['time'] = time(); # Reset our request timer
				}
				
				else
					RESULT::userTimeout();
			}
			
			$_SESSION['USR']['REQUEST']['count']++;
			
			# Protection against spammers, bots, wannabes, etc. via frequency sentinel!
			if(time() - $_SESSION['USR']['REQUEST']['time'] >= self::REQUEST_TIME_PERIOD)
				$_SESSION['USR']['REQUEST']['count'] = 0;
			
			$_SESSION['USR']['REQUEST']['time'] = time();
			
			if($_SESSION['USR']['REQUEST']['count'] > self::REQUEST_MAX_REQUESTS)
			{
				$_SESSION['USR']['FLAGS']['timeout'] = TRUE;
				RESULT::userTimeout();
			}
			
			# End sentinels; start main code
			$action = isset($_GET['action']) ? $_GET['action'] : NULL;
			$token  = isset($_GET['token'])  ? $_GET['token']  : NULL;
			
			# Available actions (I'm writing an Command-pattern OO class that'll encapsulate all of this)
			$ACTIONS = array('handshake', 'auth', 'register', 'addword', 'fetchRandomWord', 'unauth');
			
			# Okay, one more sentinel before we go on...
			if(!in_array($action, $ACTIONS))
				RESULT::unknownAction();
			
			# Connect to our DB
			if($this->sql->new_connection('default'))
			{
				# Action: handshake
				if($action == $ACTIONS[0])
				{
					$_SESSION['USR']['DATA']['token'] = STR::random(100, 0, TRUE);
					
					RESULT::OK(array(
						'token' => $_SESSION['USR']['DATA']['token'],
						'preauth' => $_SESSION['USR']['FLAGS']['authenticated']
					));
				}
				
				# If the token provided is invalid, complain about it
				if(is_null($_SESSION['USR']['DATA']['token']) || $token != $_SESSION['USR']['DATA']['token'])
					RESULT::badToken();
				
				# Sequester auth-req commands from auth-not-req commands
				if(!$_SESSION['USR']['FLAGS']['authenticated'])
				{
					$sentinel = !isset($_GET['username'], $_GET['password']) ||
					   			strlen($_GET['username']) > self::USERNAME_MAXLEN ||
					   			strlen($_GET['username']) < self::USERNAME_MINLEN ||
					   			strlen($_GET['password']) != self::PASSWORD_HEXLEN || # 'password' is expected to be a 40 char SHA1 hash
					   			!$this->validate('password', TYPE_HEX, 'get');
					
					# Action: authorize
					if($_GET['action'] == $ACTIONS[1])
					{
						if($sentinel)
							RESULT::badAuthentication();
						
						$rows = $this->sql->query('SELECT username, email, id FROM users WHERE username = ? AND password = SHA1(CONCAT(hash_salt, ?)) LIMIT 1',
							array(array($_GET['username'], S),
								  array($_GET['password'], S)
							));
						
						if(!$rows->num_rows)
							RESULT::notAuthenticated();
						
						else
						{
							$_SESSION['USR']['DATA']['username'] = $rows[0]['username'];
							$_SESSION['USR']['DATA']['email'] = $rows[0]['email'];
							$_SESSION['USR']['DATA']['id'] = $rows[0]['id'];
							$_SESSION['USR']['FLAGS']['authenticated'] = TRUE;
							
							$this->sql->query('UPDATE users SET sess_id = ? WHERE username = ? LIMIT 1',
								array(array(session_id(), S),
									  array($_GET['username'], S)
							));
							
							RESULT::OK();
						}
					}
					
					# Action: register
					else if($_GET['action'] == $ACTIONS[2])
					{
						if($sentinel || !$this->validate('email', TYPE_EMAIL, 'get'))
							RESULT::badAuthentication();
						
						$rows = $this->sql->query('SELECT COUNT(*) c, username u, email e FROM users WHERE username = ? OR email = ? LIMIT 1',
							array(array($_GET['username'], S),
								  array($_GET['email'], S)
							));
						
						if($row[0]['c'])
						{
							if($row[0]['u'] == $_GET['username'])
								RESULT::usernameTaken();
								
							else if($row[0]['e'] == $_GET['email'])
								RESULT::emailTaken();
								
							else
								RESULT::internalError();
						}
						
						else
						{
							$result = $this->sql->query('INSERT INTO users (username, password, email, hash_salt) VALUES (?, UNHEX(SHA1(CONCAT(?,?))), ?, ?) LIMIT 1',
								array(array($_GET['username'], S),
									  array($randstr = STR::random(40), S),
									  array($_GET['password'], S),
									  array($_GET['email'], S),
									  array($randstr, S)
								));
							
							if($result->aff_rows)
								RESULT::OK();
							else
								RESULT::notRegistered();
						}
					}
					
					else RESULT::forbidden();
				}
				
				else
				{
					$result = $this->sql->query('SELECT username u FROM users WHERE sess_id = ? LIMIT 1', array(array(session_id(), S)));
					$dishonorable = FALSE;
					
					# Sentinel to make sure two users do not log in with the same account
					if($result->num_rows != 1)
						$dishonorable = TRUE;
					
					# Sanity check
					if($result[0]['u'] != $_SESSION['USR']['DATA']['username'])
						RESULT::internal();
					
					# Action: unauth (logout; destroy session & data)
					if($dishonorable || $action == 'unauth')
					{
						# Bye bye!
						$_SESSION['USR'] = array();
						unset($_SESSION['USR']);
						
						session_regenerate_id();
						session_destroy();
						
						# Set the session cookie to expire 20 years in the past...
						if(isset($_COOKIE[session_name()])) setcookie(session_name(), '', time()-60*60*24*365*20, '', '', false, true);
						
						if($dishonorable)
							RESULT::sessionMismatch();
						else
							RESULT::OK();
					}
					
					# Action: addWord
					else if($action == 'addWord')
					{
						$word = isset($_GET['word']) ? $_GET['word'] : NULL;
						
						if($word === NULL)
							RESULT::badRequest();
						
						if(strlen($word) > self::WORD_MAXLENGTH)
							RESULT::wordTooLong();
						
						else if(strlen($word) < self::WORD_MINLENGTH)
							RESULT::wordTooShort();
						
						# All sentinels cleared!
						else
						{
							# Attempt to check local cache
							$rows = $this->sql->query('SELECT id FROM dict WHERE term = ? LIMIT 1', array(array($word)));
							
							# (I hope) we've found it!
							if($rows->num_rows)
								$id = $rows[0]['id'];
							
							# Not found, so fetch it instead
							else
							{
								$data = $this->fetch($word);
								
								if(!is_string($data) || ($data = new SimpleXMLElement($data) && empty($data)))
									RESULT::wordNotFound();
								
								else if(empty($word->result))
									RESULT::badResponse();
								
								else
								{
									$data = $data->result;
									$result = $this->sql->query('INSERT INTO dict (term, definition, usage, part_of_speech) VALUES (?, ?, ?, ?)',
										array(array($word, S),
											  array($data->definition, S),
											  array($data->example, S),
											  array(current($word->partofspeech), S)
									));
									
									if(!$result->aff_rows)
										RESULT::SQLError();
									else
										$id = $result->insert_id;
								}
							}
							
							# Associate the word with the user's account...
							$res = $this->sql->query('INSERT INTO users_dict_junction (user_id, dict_id) VALUES (?, ?)',
								array(array($_SESSION['USR']['DATA']['id'], I),
									  array($id, I)
							));
							
							if(!$res->aff_rows)
								RESULT::SQLError();
							else
								RESULT::OK();
						}
					}
					
					# Action: fetchRandomWord
					else if($action == 'fetchRandomWord')
					{
						$rows = $this->sql->query('SELECT term, definition def, usage, part_of_speech pos FROM dict WHERE id IN '.
							'(SELECT dict_id FROM users_dict_junction WHERE user_id = ? ORDER BY RAND() LIMIT 1) LIMIT 1',
							array(array($_SESSION['USR']['DATA']['id'], I)));
						
						if($rows->num_rows)
						{
							RESULT::OK(array(
								'term' => $rows[0]['term'],
								'definition' => $rows[0]['def'],
								'usage' => $rows[0]['usage'],
								'partOfSpeech' => $rows[0]['pos']
							));
						}
						
						else
							RESULT::OK(); # Return NULL if no word can be returned
					}
					
					else RESULT::unknownAction();
				}
			}
			
			else
				RESULT::SQLError();
			
			RESULT::unhandled();
		}
		
		/* Fetches data from the server */
		protected function fetch($word)
		{
			$response = NULL;
			$host = 'http://www.abbreviations.com/services/v2/defs.php?uid=1001&tokenid=tk324324&word='.$word;
			
			try
			{
				// Use cURL to fetch data
				if(function_exists('curl_init'))
				{		
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $host);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Xunn-PHP API Client 1.0');
					$response = curl_exec($ch);
					curl_close($ch);
				}
				
				// Otherwise fall back to fopen()
				else if(ini_get('allow_url_fopen'))
					$response = file_get_contents($host, 'r');
					
				else
					RESULT::internal();
			}
			
			catch(Exception $e)
			{ RESULT::external(); }
				
			return $response;
		}
	}
	
	class RESULT
	{
		public static function __callStatic($method, $args)
		{
			if(!is_array($args) || !count($args))
				$args = array(NULL);
			
			if(in_array($method, array('send', 'OK')))
				$result = self::create('ok', $args[0]);
				
			else switch($method)
			{
				case 'SQLError':					# General SQL error
				case 'internalError':				# General internal server error
				case 'externalError':				# General external error of some kind
				
				case 'badRequest':					# Request was malformed in some way
				case 'badResponse':					# Response was malformed in some way
				
				case 'unhandled':					# Request fell outside of the target domain in some way
				case 'unknownAction':				# Unknown action in request
				
				case 'notAuthenticated':			# Auth failed
				case 'badAuthentication':			# Auth request was malformed in some way
				case 'forbidden':					# Not-auth user attempted to access a command that requires auth
				
				case 'badToken':					# The token provided did not match the token on file
				case 'sessionMismatch':				# Two users attempted to log in using the same account (one was booted)
				
				case 'usernameTaken':				# Username (for registration) already taken
				case 'emailTaken':					# Email address (for registration) already taken
				case 'notRegistered':				# Registration failed
				
				case 'wordTooLong':					# Word was too long
				case 'wordTooShort':				# Word was too short
				case 'wordNotFound':				# Word was not found
				
				case 'userBanned':					# User is banned from using the server "forever"!
				case 'userTimeout':					# User has made too many requests and has been timed out
					$result = self::create('error', $method);
					break;
					
				default:
					$result = self::create('error', 'unknownResult'); # Unknown result as method call
					break;
			}
			
			return $result;
		}
		
		private static function create($result, $data=NULL)
		{ exit(json_encode(array('result' => $result, 'data' => $data))); }
	}
	
	new Xunnamius();
?>