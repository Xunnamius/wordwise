<?php require_once '~devkey.php'; ?>
<?php require_once 'assets/tools/DeveloperErrorHandler.php'; ?>
<?php require_once 'assets/tools/Controller.php'; ?>
<?php require_once 'assets/tools/SQLFactory.php'; ?>
<?php require_once 'assets/tools/STR.php'; ?>
<?php
	class Main extends Controller
	{
		# Constants to modify
		const WORD_MAXLENGTH  = 45;
		const WORD_MINLENGTH  = 3;
		const USERNAME_MAXLEN = 50;
		const USERNAME_MINLEN = 4;
		const PASSWORD_HEXLEN = 40;
		
		const REQUEST_TIME_PERIOD 	= 10; # seconds; should be smaller than USER_TIMEOUT_PERIOD
		const REQUEST_MAX_REQUESTS 	= 27; # requests per ^ seconds
		const USER_TIMEOUT_PERIOD	= 60; # seconds; you bad little boy you :P
		
		public $MY_HOST;
		private $RESULT;
		private $sql;
		
		public function __construct()
		{
			$this->MY_HOST = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
			$this->RESULT = new RESULT();
			$this->sql = SQL::load_driver('MySQL');
			parent::__construct();
		}
		
		protected function run(){ header('Location: '.$this->MY_HOST); }
		
		protected function run_AJAX()
		{
			# Available actions (I'm writing a Command Pattern OO class that'll encapsulate all of this)
			$ACTIONS = array('handshake', 'auth', 'register', 'addWord', 'fetchRandomWord', 'unauth');
			
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
				$this->RESULT->userBanned();
			
			else if($_SESSION['USR']['FLAGS']['timeout'])
			{
				if(time() - $_SESSION['USR']['REQUEST']['time'] >= self::USER_TIMEOUT_PERIOD)
				{
					$_SESSION['USR']['FLAGS']['timeout'] = FALSE;
					$_SESSION['USR']['REQUEST']['count'] = 0;
					$_SESSION['USR']['REQUEST']['time'] = time(); # Reset our request timer
				}
				
				else
					$this->RESULT->userTimeout();
			}
			
			$_SESSION['USR']['REQUEST']['count']++;
			
			# Protection against spammers, bots, wannabes, etc. via frequency sentinel!
			if(time() - $_SESSION['USR']['REQUEST']['time'] >= self::REQUEST_TIME_PERIOD)
				$_SESSION['USR']['REQUEST']['count'] = 0;
			
			$_SESSION['USR']['REQUEST']['time'] = time();
			
			if($_SESSION['USR']['REQUEST']['count'] > self::REQUEST_MAX_REQUESTS)
			{
				$_SESSION['USR']['FLAGS']['timeout'] = TRUE;
				$this->RESULT->userTimeout();
			}
			
			# End sentinels; start main code
			$action = isset($_GET['action']) ? $_GET['action'] : NULL;
			$token  = isset($_GET['token'])  ? $_GET['token']  : NULL;
			$word = isset($_GET['word']) ? $_GET['word']=strtolower($_GET['word']) : NULL;
			
			# Okay, one more sentinel before we go on...
			if(!in_array($action, $ACTIONS))
				$this->RESULT->unknownAction();
			
			# Connect to our DB
			if($this->sql->new_connection('default'))
			{
				# Action: handshake
				if($action == $ACTIONS[0])
				{
					$_SESSION['USR']['DATA']['token'] = STR::random(100, 0, TRUE);
					
					$this->RESULT->OK(array(
						'token' => $_SESSION['USR']['DATA']['token'],
						'preauth' => $_SESSION['USR']['FLAGS']['authenticated']
					));
				}
				
				# Sentinel: if the token provided is invalid, complain about it
				if(is_null($_SESSION['USR']['DATA']['token']) || $token != $_SESSION['USR']['DATA']['token'])
					$this->RESULT->badToken();
				
				# Sequester auth-req commands from auth-not-req commands
				if(!$_SESSION['USR']['FLAGS']['authenticated'])
				{
					$sentinel = !isset($_GET['username'], $_GET['password']) ||
					   			strlen($_GET['username']) > self::USERNAME_MAXLEN ||
					   			strlen($_GET['username']) < self::USERNAME_MINLEN ||
					   			strlen($_GET['password']) != self::PASSWORD_HEXLEN || # 'password' is expected to be a 40 char SHA1 hash
					   			!$this->validate('password', TYPE_HEX, 'get');
					
					# Action: auth
					if($_GET['action'] == $ACTIONS[1])
					{
						if($sentinel)
							$this->RESULT->badAuthentication();
						
						$rows = $this->sql->query('SELECT username, email, id, banned b FROM users WHERE username = ? AND password = UNHEX(SHA1(CONCAT(hash_salt, ?))) LIMIT 1',
							array(array($_GET['username'], S),
								  array($_GET['password'], S)
							));
						
						if(!$rows->num_rows)
							$this->RESULT->notAuthenticated();
						
						else
						{
							$_SESSION['USR']['DATA']['username'] = $rows->rows[0]['username'];
							$_SESSION['USR']['DATA']['email'] = $rows->rows[0]['email'];
							$_SESSION['USR']['DATA']['id'] = $rows->rows[0]['id'];
							$_SESSION['USR']['FLAGS']['authenticated'] = TRUE;
							$_SESSION['USR']['FLAGS']['banned'] = $rows->rows[0]['b'] == 'T' ? TRUE : FALSE;
							
							$this->sql->query('UPDATE users SET sess_id = ? WHERE username = ? LIMIT 1',
								array(array(session_id(), S),
									  array($_GET['username'], S)
							));
							
							if($_SESSION['USR']['FLAGS']['banned'])
								$this->RESULT->userBanned();
							else
								$this->RESULT->OK();
						}
					}
					
					# Action: register
					else if($_GET['action'] == $ACTIONS[2])
					{
						if($sentinel || !$this->validate('email', TYPE_EMAIL, 'get'))
							$this->RESULT->badRequest();
						
						$rows = $this->sql->query('SELECT COUNT(*) c, username u, email e FROM users WHERE username = ? OR email = ? LIMIT 1',
							array(array($_GET['username'], S),
								  array($_GET['email'], S)
							));
						
						if($rows->rows[0]['c'])
						{
							if(strtolower($rows->rows[0]['u']) == strtolower($_GET['username']))
								$this->RESULT->usernameTaken();
								
							else if(strtolower($rows->rows[0]['e']) == strtolower($_GET['email']))
								$this->RESULT->emailTaken();
								
							else
								$this->RESULT->internalError();
						}
						
						else
						{
							$result = $this->sql->query('INSERT INTO users (username, password, email, hash_salt) VALUES (?, UNHEX(SHA1(CONCAT(?,?))), ?, ?)',
								array(array($_GET['username'], S),
									  array($randstr = STR::random(40), S),
									  array($_GET['password'], S),
									  array($_GET['email'], S),
									  array($randstr, S)
								));
							
							if($result->aff_rows)
								$this->RESULT->OK();
							else
								$this->RESULT->notRegistered();
						}
					}
					
					else $this->RESULT->forbidden();
				}
				
				else
				{
					$result = $this->sql->query('SELECT username u, banned b FROM users WHERE sess_id = ? LIMIT 1', array(array(session_id(), S)));
					$dishonorable = FALSE;
					
					# Sentinel to make sure two users do not log in with the same account
					if($result->num_rows != 1)
						$dishonorable = TRUE;
					
					else
					{
						# Sanity check
						if($result->rows[0]['u'] != $_SESSION['USR']['DATA']['username'])
							$this->RESULT->internalError();
						
						# Ban check
						if($result->rows[0]['b'] == 'T')
						{
							$_SESSION['USR']['FLAGS']['banned'] = TRUE;
							$this->RESULT->userBanned();
						}
					}
					
					# Action: unauth (logout; destroy session & data)
					if($dishonorable || $action == $ACTIONS[5])
					{
						# Bye bye!
						$_SESSION['USR'] = array();
						unset($_SESSION['USR']);
						
						session_regenerate_id();
						session_destroy();
						
						# Set the session cookie to expire 20 years in the past...
						if(isset($_COOKIE[session_name()])) setcookie(session_name(), '', time()-60*60*24*365*20, '', '', false, true);
						
						if($dishonorable)
							$this->RESULT->sessionMismatch();
						else
							$this->RESULT->OK();
					}
					
					# Action: addWord
					else if($action == $ACTIONS[3])
					{
						if($word === NULL)
							$this->RESULT->badRequest();
						
						if(strlen($word) > self::WORD_MAXLENGTH)
							$this->RESULT->wordTooLong();
						
						else if(strlen($word) < self::WORD_MINLENGTH)
							$this->RESULT->wordTooShort();
						
						# All sentinels cleared!
						else
						{
							# Attempt to check local cache
							$rows = $this->sql->query('SELECT id FROM dict WHERE term = ? LIMIT 1', array(array($word, S)));
							
							# (I hope) we've found it!
							if($rows->num_rows)
								$id = $rows->rows[0]['id'];
							
							# Not found, so fetch it instead
							else
							{
								$data = $this->fetch($word);
								
								if(!is_string($data))
									$this->RESULT->wordNotFound();
								
								$data = new SimpleXMLElement($data);
								
								if(empty($data))
									$this->RESULT->wordNotFound();
									
								else if(empty($data->result))
									$this->RESULT->badResponse();
								
								else
								{
									$data = $data->result;
									$result = $this->sql->query('INSERT INTO dict (term, definition, example, part_of_speech) VALUES (?, ?, ?, ?)',
										array(array($word, S),
											  array($data->definition, S),
											  array($data->example, S),
											  array(current($data->partofspeech), S)
									));
									
									if(!$result->aff_rows)
										$this->RESULT->SQLError();
									else
										$id = $result->insert_id;
								}
							}
							
							if($id <= 0)
								$this->RESULT->internalError();
							
							# Associate the word with the user's account...
							$r = $this->sql->query('SELECT COUNT(*) c FROM users_dict_junction WHERE user_id = ? AND dict_id = ? LIMIT 1',
								array(array($_SESSION['USR']['DATA']['id'], I),
									  array($id, I)
							));
							
							if($r->rows[0]['c'])
								$this->RESULT->wordAlreadyAdded();
							
							$res = $this->sql->query('INSERT INTO users_dict_junction (user_id, dict_id) VALUES (?, ?)',
								array(array($_SESSION['USR']['DATA']['id'], I),
									  array($id, I)
							));
							
							if(!$res->aff_rows)
								$this->RESULT->SQLError();
							else
								$this->RESULT->OK();
						}
					}
					
					# Action: fetchRandomWord
					else if($action == $ACTIONS[4])
					{
						$rows = $this->sql->query('SELECT d.term, d.definition def, d.example, d.part_of_speech pos '.
							'FROM dict d JOIN users_dict_junction udj ON (d.id = udj.dict_id) '.
							'WHERE udj.user_id = ? ORDER BY RAND() LIMIT 1',
							array(array($_SESSION['USR']['DATA']['id'], I)));
						
						if($rows->num_rows)
						{
							$this->RESULT->OK(array(
								'term' => $rows->rows[0]['term'],
								'definition' => $rows->rows[0]['def'],
								'example' => $rows->rows[0]['example'],
								'partOfSpeech' => $rows->rows[0]['pos']
							));
						}
						
						else
							$this->RESULT->OK(); # Return NULL if no word can be returned
					}
					
					else $this->RESULT->alreadyAuthenticated();
				}
			}
			
			else
				$this->RESULT->SQLError();
			
			$this->RESULT->unhandled();
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
					$this->RESULT->internalError();
			}
			
			catch(Exception $e)
			{ $this->RESULT->externalError(); }
				
			return $response;
		}
	}
	
	class RESULT
	{
		public function __call($method, $args)
		{
			if(!is_array($args) || !count($args))
				$args = array(NULL);
			
			if(in_array($method, array('send', 'OK')))
				$this->ok($args[0]);
				
			else switch($method)
			{
				case 'SQLError':					# General SQL error
					$msg = 'An unknown SQL error occurred';
					break;
				
				case 'internalError':				# General internal server error
					$msg = 'An unknown internal error occurred.';
					break;
				
				case 'externalError':				# General external error of some kind
					$msg = 'An unknown external error occurred.';
					break;
				
				
				
				case 'badRequest':					# Request was malformed in some way
					$msg = 'Your request was didn\'t look too good. Are you sure you\'re not forgetting something?';
					break;
				
				case 'badResponse':					# Response was malformed in some way
					$msg = 'Received bad response from external sources.';
					break;
				
				
				
				case 'unhandled':					# Request fell outside of the target domain in some way
					$msg = 'Request was unhandled.';
					break;
				
				case 'unknownAction':				# Unknown action in request
					$msg = 'User specified an unknown action.';
					break;
				
				
				
				case 'notAuthenticated':			# Auth failed
					$msg = 'The username/password combination provided was incorrect. Please try again.';
					break;
				
				case 'badAuthentication':			# Auth request was malformed in some way
					$msg = 'Are you sure you typed your username/password in correctly?';
					break;
				
				case 'alreadyAuthenticated':		# Re-authing is not allowed ;)
					$msg = 'You are already authenticated.';
					break;
				
				case 'forbidden':					# Not-auth user attempted to access a command that requires auth
					$msg = 'You do not have permission to give that command! Please refresh the page and try again.';
					break;
				
				case 'badToken':					# The token provided did not match the token on file
					$msg = 'Bad token. Please refresh.';
					break;
					
					
				
				case 'sessionMismatch':				# Two users attempted to log in using the same account (one was booted)
					$msg = 'You have been disconnected from the server! Are you attempting to log in twice?';
					break;
				
				case 'usernameTaken':				# Username (for registration) already taken
					$msg = 'The username you provided for registration is not available for use.';
					break;
				
				case 'emailTaken':					# Email address (for registration) already taken
					$msg = 'The email address you provided for registration is not available for use.';
					break;
				
				case 'notRegistered':				# Registration failed
					$msg = 'Registration failed. Contact support if you need help.';
					break;
					
					
				
				case 'wordTooLong':					# Word was too long
					$msg = sprintf('Word "%s" was longer than the %d-character maximum.', $_GET['word'], Main::WORD_MAXLENGTH);
					break;
				
				case 'wordTooShort':				# Word was too short
					$msg = sprintf('Word "%s" was shorter than the %d-character minimum.', $_GET['word'], Main::WORD_MINLENGTH);
					break;
				
				case 'wordNotFound':				# Word was not found
					$msg = sprintf('Word "%s" was not found in any internal or external dictionary.', $_GET['word']);
					break;
					
				case 'wordAlreadyAdded':			# Word was already associated with user
					$msg = sprintf('Word "%s" was already added!', $_GET['word']);
					break;
					
					
				
				case 'userBanned':					# User is banned from using the server "forever"!
					$msg = 'Your account has been banned. Please contact support with any further inquiries.';
					break;
					
				case 'userTimeout':					# User has made too many requests and has been timed out
					$msg = sprintf('Your account has been rate limited. Cool your heels for %d second(s).',
						max(1, Main::USER_TIMEOUT_PERIOD - (time() - $_SESSION['USR']['REQUEST']['time'])));
					break;
					
				default:
					$this->error('unknownResult',	# Unknown result as method call
						'Eek. Someone messed up on our end. Sorry about that.');
					break;
			}
			
			$this->error($method, $msg);
		}
		
		protected function ok($data)
		{ $this->create('ok', $data); }
		
		protected function error($err, $msg)
		{ $this->create('error', array('type' => $err, 'message' => $msg)); }
		
		private function create($result, $data=NULL)
		{ exit(json_encode(array('result' => $result, 'data' => $data))); }
	}
	
	new Main();
?>