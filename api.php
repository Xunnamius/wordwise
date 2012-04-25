<?php
	define('DG_AJAX_ID', 'api');
	define('DG_AJAX_VALUE', 1001);
	define('DG_AJAX_SCOPE', 'get');
	define('DG_DEBUG_MODE', FALSE);
?>
<?php require_once 'Controller.php'; ?>
<?php
	class Xunnamius extends Controller
	{
		public $MY_HOST;
		const WORD_MAXLENGTH = 45;
		const WORD_MINLENGTH = 3;
		
		public function __construct()
		{
			$this->MY_HOST = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
			parent::__construct();
		}
		
		protected function run()
		{
			header('Location: '.$this->MY_HOST);
		}
		
		protected function run_AJAX()
		{
			$json = $this->error('unhandled');
			
			if(empty($_GET['word']))
				$json = $this->error('badrequest');
				
			else if(strlen($_GET['word']) > self::WORD_MAXLENGTH)
				$json = $this->error('wordtoolong');
			
			else if(strlen($_GET['word']) < self::WORD_MINLENGTH)
				$json = $this->error('wordtooshort');
				
			else
			{
				$json = $this->fetch($_GET['word']);
				
				if(is_string($json))
				{
					$word = new SimpleXMLElement($json);
					
					if(empty($word))
						$json = $this->error('wordnotfound');
					
					else if(empty($word->result))
						$json = $this->error('badresponse');
							
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
				
			echo json_encode($json);
		}
		
		/* Fetches data from the server */
		protected function fetch($word)
		{
			$response = NULL;
			$host = 'http://www.abbreviations.com/services/v2/defs.php?uid=1001&tokenid=tk324324&word='.$word;
			
			// Use cURL to fetch data
			if(function_exists('curl_init'))
			{		
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $host);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Xunn PHP API Fetcher 1.0');
				$response = curl_exec($ch);
				curl_close($ch);
			}
			
			// Otherwise fall back to fopen()
			else if(ini_get('allow_url_fopen'))
				$response = file_get_contents($host, 'r');
				
			else
				$response = $this->error('badserver');
				
			return $response;
		}
		
		protected function error($reason)
		{ return array('error' => $reason); }
	}
	
	new Xunnamius();
?>