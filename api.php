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
				# handshake
				if($action == $ACTIONS[0])
				{
					$_SESSION['USR']['DATA']['token'] = 
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
					   			strlen($_GET['password']) != self::PASSWORD_HEXLEN ||
					   			!$this->validate('password', TYPE_HEX, 'get');
					
					# authorize
					if($_GET['action'] == $ACTIONS[1])
					{
						if($sentinel)
							RESULT::badAuthentication();
						
						$rows = $dbc->query('SELECT * FROM users WHERE username = ? AND password = ? LIMIT 1',
							array(array($_GET['username'], S),
								  array($_GET['email'], S)
							));
					}
					
					# register
					else if($_GET['action'] == $ACTIONS[2])
					{
						if($sentinel || !$this->validate('email', TYPE_EMAIL, 'get'))
							RESULT::badAuthentication();
						
						$rows = $dbc->query('SELECT COUNT(*) c, username u, email e FROM users WHERE username = ? OR email = ? LIMIT 1',
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
							$result = $dbc->query('INSERT INTO users (username, password, email) VALUES (?, UNHEX(?), ?) LIMIT 1',
								array(array($_GET['username'], S),
									  array($_GET['password'], S),
									  array($_GET['email'], S)
								));
							
							if($result->aff_rows)
								RESULT::OK();
							else
								RESULT::notRegistered();
						}
					}
					
					RESULT::notAuthorized();
				}
				
				else
				{
					$json = ERROR::badRequest();
					
					// Check APIGUID w/ session call and variable check
					if(false)
					{
						
					}
					
					else if($actionIS)
					{
						$action = $_GET['action'];
						
						if($action == 'unauth')
						{
							
						}
						
						else if($action == 'addWord')
						{
							if(!empty($_GET['word']))
							{
								if(strlen($_GET['word']) > self::WORD_MAXLENGTH)
									$json = ERROR::wordTooLong();
								
								else if(strlen($_GET['word']) < self::WORD_MINLENGTH)
									$json = ERROR::wordTooShort();
									
								else
								{
									$json = $this->fetch($_GET['word']);
									
									if(is_string($json))
									{
										$word = new SimpleXMLElement($json);
										
										if(empty($word))
											$json = ERROR::wordNotFound();
										
										else if(empty($word->result))
											$json = ERROR::badResponse();
												
										else
										{
											$word = $word->result;
											
											$examples = explode('; ', $word->example);
											array_walk($examples, create_function('&$v,$k', '$v = trim($v, \'" \');'));
											
											$rel = explode(', ', $word->term);
											$related = array();
											
											foreach($rel as $term)
												if($term != $_GET['word'] && stripos($term, ' ') === FALSE)
													$related[] = '<a href="'.$this->MY_HOST.'/#!/'.$term.'">'.$term.'</a>';
											
											$def = explode(' ', $word->definition);
											$definition = array();
											
											foreach($def as $term)
											{
												$symbolless = preg_replace('/[^a-z0-9-]/i', '', $term);
												
												if(strlen($symbolless) >= self::WORD_MINLENGTH)
													$definition[] = '<a href="'.$this->MY_HOST.'/#!/'.$symbolless.'">'.$term.'</a>';
												else
													$definition[] = $term;
											}
											
											$json = array(
												'term' => $_GET['word'],
												'related' => implode(', ', $related),
												'definition' => implode(' ', $definition),
												'examples' => implode('; ', $examples),
												'partofspeech' => current($word->partofspeech)
											);
										}
									}
								}
							}
						}
						
						else if($action == 'fetchRandomWord')
						{
							
						}
						
						else $json = ERROR::unknownAction();
					}
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
					$response = ERROR::internal();
			}
			
			catch(Exception $e)
			{ $response = ERROR::external(); }
				
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
				case 'badToken':					# The token provided did not match the token on file
				
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