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
		protected function run()
		{
			header('Location: http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']));
		}
		
		protected function run_AJAX()
		{
			$json = $this->error('unhandled');
			
			if(!empty($_GET['word']))
			{
				$word = new SimpleXMLElement($this->fetch($_GET['word']));
				
				if(empty($word))
					$json = $this->error('wordnotfound');
				
				else
				{
					$word = $word->result;
					
					if(empty($word))
						$json = $this->error('badresponse');
						
					else
					{
						$arr = explode('; ', $word->example);
						array_walk($arr, create_function('&$v,$k', '$v = trim($v, \'" \');'));
						
						$json = array(
							'term' => $_GET['word'],
							'related' => implode(', ', array_slice(explode(', ', $word->term), 1)),
							'definition' => current($word->definition),
							'examples' => implode('; ', $arr),
							'partofspeech' => current($word->partofspeech)
						);
					}
				}
			}
			
			else
				$json = $this->error('badrequest');
				
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
		{
			return array('error' => $reason);
		}
	}
	
	new Xunnamius();
?>