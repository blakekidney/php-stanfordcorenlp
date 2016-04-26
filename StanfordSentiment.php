<?php
///////////////////////////////////////////////////////////////////////
//PAGE-LEVEL DOCBLOCK
/**
 * File for class: StanfordSentiment
 * @package    	PHP_StandfordCoreNLP
 * @copyright  	2016 Blake Kidney
 */
///////////////////////////////////////////////////////////////////////

/**
 * Class for using Stanford's Sentiment Processing.
 *
 * This is not intended for a single request / response through PHP.
 * Instead, this class is used to interact with the CoreNLP interactive shell
 * so as to batch process blocks of text.
 * 	 
 * @url  http://stanfordnlp.github.io/CoreNLP/sentiment.html	
 * 
 * @package    PHP_StandfordCoreNLP
 * @copyright  2016 Blake Kidney
 */
class StanfordSentiment {		
	
	
	//////////////////////////////////////////////////////////
	//VARIABLES
	//////////////////////////////////////////////////////////
	
	/**
	 * The types of databases.
	 * 
	 * @var  string
	 */	
	protected $path = '';
	
	/**
	 * Indicates whether to display messages in the browser.
	 * 
	 * @var  boolean
	 */	
	protected $displayMessages = false;	
		
	/**
	 * Provides a holder for the microtime when the process opened.
	 * 
	 * @var  number
	 */	
	private $timestart = false;
	
	/**
	 * The process.
	 * 
	 * @var  resource
	 */	
	protected $proc = false;
	/**
	 * Indicates the process was opened.
	 * 
	 * @var  boolean
	 */	
	private $opened = false;
	/**
	 * The descriptor spec used by the process.
	 * 
	 * @var  array
	 */	
	protected $descriptorspec = array(
		0 => array("pipe", "r"),  // stdin
		1 => array("pipe", "w"),  // stdout
		2 => array("pipe", "w")   // stderr
	);
	
	/**
	 * The pipes used by the process.
	 * 
	 * @var  array
	 */	
	protected $pipes = array();
	
	/**
	 * The sentiment scores and text.
	 * 
	 * @var  array
	 */	
	protected $sentimentText = array(
		'1' => 'Very Negative',
		'2' => 'Negative',
		'3' => 'Neutral',
		'4' => 'Positive',
		'5' => 'Very Positive',	
	);
	
	//////////////////////////////////////////////////////////
	//CONSTRUCTOR
	//////////////////////////////////////////////////////////
	
	/**
	 * Constructor.
	 * 
	 * @param  string  $path  The directory location of the StanfordCoreNLP.php
	 * @param  boolean  $displayMessages  (optional) Indicates whether to output messages from processor to the browser.
	 */
	public function __construct($path, $displayMessages=true) {
		//check to see if the file directory exists
		if(!file_exists($path)) {
			throw new Exception('The StanfordCoreNLP path ('.$path.') does not exist.');
			exit();
		}
		$this->path = $path;
		$this->displayMessages = $displayMessages;
		//if we are displaying messages, turn off output buffering
		if($displayMessages) $this->turnOffOutputBuffering();
	}
	
	//////////////////////////////////////////////////////////
	//PUBLIC METHODS
	//////////////////////////////////////////////////////////
	
	/**
	 * Opens and initializes the process. 
	 *
	 * @param  string  $memory  (optional)  A string of the memory to use with digits followed by g or m respectively.
	 * 
	 * @return  void
	 */
	public function open($memory='1g') {		
				
		//validate the memory requirements
		if(!preg_match('/^\d+[gm]$/', $memory)) {
			throw new Exception('The memory string ('.$memory.') is not valid. It must be digits followed by g or m.');
			exit();
		}
		
		//start the timer
		$this->timestart = microtime(true);
		
		//display the process has started
		$this->display('Initialized at '.date('r'));
		
		//construct the command
		$cmd = 'java -cp "*" -Xmx'.$memory.' edu.stanford.nlp.sentiment.SentimentPipeline -stdin';
		
		//open the process on the server 
		$this->proc = proc_open($cmd, $this->descriptorspec, $this->pipes, $this->path);
		
		//display the process has started
		$this->display('Process opened with command: '.PHP_EOL.'<span style="color:green">'.$cmd.'</span>');
		
		//check if the process opened or not
		if(!is_resource($this->proc)) {
			throw new Exception('The process failed to open.');
			exit();
		}
		
		//indicate the process has opened
		$this->opened = true;
				
		//register a shutdown function to ensure the process closes as the script ends
		register_shutdown_function(array($this, 'close'));
		
		//wait until we can recieve user input
				
		//variables
		$timeout = 60; //timeout just in case something goes wrong
		$time = microtime(true);
		$output = '';
		
		//we will grab the output character by character		
		if($this->displayMessages) echo '<pre style="color:blue">';
		
		//iterate until we have all the data
		while(true) {
		
			//pull the data character by character
			$data = fgetc($this->pipes[2]);
						
			//add the data to the full output
			$output .= $data;
			
			//display data if set
			if($this->displayMessages) echo $data;
			
			//if we have no data, then break
			if($data === false) {				
				break;
			}
						
			//we have all the data once this message appears
			if(strpos($output, 'Processing will end when EOF is reached.'.PHP_EOL) !== false) {
				break;
			}
			
			//break from loop after timeout (in case the prompt doesn't appear)
			if((microtime(true) - $time) > $timeout) {
				break;
			}
		}
		
		//close our pre statement
		if($this->displayMessages) echo '</pre>';
		
		//check for error
		if(strpos($output, 'Exception') !== false && strpos($output, 'NLP> ') === false) {
			$this->display('Process closed with error.', 'red');
			$this->close();										
			if(!$this->displayMessages) throw new Exception('An exception occurred. Display messages to inspect it.');		
		}		
		
	}
	
	/**
	 * Processes a set of text and returns the output as an array.
	 * 
	 * @param  string  $text  The text to perform natural language processing. 
	 *
	 * @return  array  The final processed text.
	 */
	public function processText($text) {		
		//make sure the process has been opened
		if(!$this->opened || !$this->running()) return false;
		
		$scores = array();
		
		//trim the white space around the text
		$text = trim($text);
		
		//remove paragraphs
		$text = preg_replace('/[\r\n]+/', ' ', $text);
		
		//display the length of the text being written
		$this->display('Processing text with length of '.strlen($text));
		
		//break into sentences so that we can process them one at a time
		$rgx = '/(?<!\w\.\w.)(?<![A-Z][a-z]\.)(?<=\.|\?)\s+/';
		$text = preg_replace($rgx, PHP_EOL, $text);
		$sentences = explode(PHP_EOL, $text);
		
		//display the length of the text being written
		$this->display('Sentences: '.count($sentences));
		
		if($sentences) foreach($sentences as $sentence) {
				
			//write to the the input pipe		
			fwrite($this->pipes[0], $sentence.PHP_EOL);		
					
			//obtain the output
			$score = $this->sentimentTextToScore( fgets($this->pipes[1]) );
			
			//add to the scores
			if($score) $scores[] = $score;			
			
		}	
		
		//display the length of the text being written
		$this->display('Scores: '.implode(', ', $scores), 'green');
			
		return $this->calcAvgSentiment($scores);
	}
	
	/**
	 * Closes process. 
	 *
	 * @return  void
	 */
	public function close() {		
		//make sure the process has been opening and is running
		if($this->opened && $this->running()) {
			
			$this->opened = false;
			
			//close all the pipes
			fclose($this->pipes[0]);
			fclose($this->pipes[1]);
			fclose($this->pipes[2]);	
			
			//close process
			$return_value = proc_close($this->proc);
			
			if($return_value == -1) {
				//terminate the process
				proc_terminate($this->proc);
				throw new Exception('Java encountered an error closing the process. Process terminated instead.');
			}			
			
			//display the result
			$this->display('Process closed. Return value: '.$return_value);			
		}
	}
	
	/**
	 * Indicates whether the process is running or not. 
	 *
	 * @return  boolean  Indicates whether the process is running or not.
	 */
	public function running() {		
		if(!is_resource($this->proc)) return false;
		$status = proc_get_status($this->proc);
		return $status['running'];
	}
	
	/**
	 * Gets the pid of the process
	 *
	 * @return  string  The pid of the process.
	 */
	public function pid() {		
		if(!is_resource($this->proc)) return false;
		$status = proc_get_status($this->proc);
		return $status['pid'];
	}
	
	//////////////////////////////////////////////////////////
	//PRIVATE METHODS
	//////////////////////////////////////////////////////////
	
	/**
	 * Gets the size of a pipe.
	 * 
	 * @param  int  $pipeindex  The index of the pipe to obtain the size.
	 *
	 * @return  string  The size of the pipe.
	 */
	private function pipeSize($pipeindex) {
		if($pipeindex != 1 && $pipeindex != 2) return NULL;
		$stat = fstat($this->pipes[$pipeindex]);
		if(!$stat) return NULL;
		return $stat['size'];	
	}
	
	/**
	 * Gets the last json error message.
	 *
	 * @return  string  The error message.
	 */
	private function jsonLastErrorMessage() {		
		
		if(function_exists('json_last_error_msg')) {
			return json_last_error_msg();	
		}
		
		//populate the data array if not set
		if(!$this->jsonErrors) {
			//form an array of all the json error messages
			$constants = get_defined_constants(true);
			$this->jsonErrors = array();
			foreach($constants['json'] as $name => $value) {
				if(!strncmp($name, 'JSON_ERROR_', 11)) {
					$this->jsonErrors[$value] = $name;
				}
			}			
		}		
		
		$i = json_last_error();
		if(isset($this->jsonErrors[$i])) {
			return $this->jsonErrors[$i];
		}	
			
		return 'Code: '.$i;		
	}
	/**
	 * Calculates the average sentiment score based upon an array of values.
	 * 
	 * @param  array  $values  An array of sentiment scores.
	 * 
	 * @return  int  The average sentiment score.
	 */
	private function calcAvgSentiment($values) {
		if(!$values) return 0;
		if(count($values) == 1) return current($values);
		/*
		We exclude neutral values from our average 
		since they may influence the outcome causing. 
		The sentiment to be neutral even though 
		one sentence is negative causing the overall to be negative.
		*/
		$c = 0;
		$s = 0;
		foreach($values as $val) {
			if($val == 3) continue;
			$c++;
			$s += $val;
		}
		//if we didn't count any values, then we contained all neutral, so return neutral
		if(!$c) return 3;
		//return the average
		return round($s/$c);
	}
	
	
	//////////////////////////////////////////////////////////
	//PUBLIC UTILITIES
	//////////////////////////////////////////////////////////
	
	/**
	 * Gets the sentiment text from the score.
	 * 
	 * @param  int  $score  A number between 1 and 5 representing the sentiment score.
	 * @param  string  $onFalse  What to return if a match is not found.
	 * 
	 * @return  string  The text name of the sentiment.
	 */
	public function sentimentScoreToText($score, $onFalse='unknown') {
		if(!isset($this->sentimentText[$score])) return $onFalse;
		return $this->sentimentText[$score];		
	}
	
	/**
	 * Converts the sentiment text into a score.
	 * 
	 * @param  string  $text  The sentiment text (i.e. postive, negative, neutral).
	 * @param  mixed  $onFalse  What to return if a match is not found.
	 * 
	 * @return  int  The score.
	 */
	public function sentimentTextToScore($text, $onFalse=0) {
		if(!preg_match('/(very)?\s*(positive|negative|neutral)/', strtolower($text), $m)) return $onFalse;
		if($m[2] == 'neutral') return 3;
		if($m[2] == 'positive') return ($m[1] == 'very' ? 5 : 4); 
		if($m[2] == 'negative') return ($m[1] == 'very' ? 1 : 2); 		
		return $onFalse;
	}
	
	/**
	 * Inverts the sentiment score.
	 * 
	 * @param  int  $score  The sentiment score
	 * 
	 * @return  int  The inverted score.
	 */
	public function invertSentiment($score) {
		if($score == '1') return '5';
		if($score == '2') return '4';
		if($score == '4') return '2';
		if($score == '5') return '1';			
		return $score;
	}
	
	
	
	/**
	 * Displays the message by echoing it to the browser. 
	 *
	 * @param  string  $color  The color of the message to display.
	 * 
	 * @return  void
	 */
	public function display($msg, $color=false) {		
		if($this->displayMessages) {
			echo '<pre style="margin:5px;'.($color ? ' color:'.$color.';' : '').'">'.
				 number_format((microtime(true) - $this->timestart), 4).'  '.$msg.'</pre>';
		}
	}
	/**
	 * Turns off output buffing so messages will display progressively. 
	 *
	 * @return  void
	 */
	public function turnOffOutputBuffering() {		
		// Turn off output buffering
		ini_set('output_buffering', 'off');
		// Turn off PHP output compression
		ini_set('zlib.output_compression', 'off');
		// Implicitly flush the buffer(s)
		ob_implicit_flush(true);

		// close all buffers if possible and discard any existing output
		// this can actually work around some whitespace problems in included files
		while(ob_get_level()) {
			if(!ob_end_clean()) {
				// prevent infinite loop when buffer can not be closed
				break;
			}
		}

		// disable any other output handlers
		ini_set('output_handler', '');

		//Clean the output buffer and turn off output buffering
		@ob_end_clean();

		//output a line of white space
		echo str_repeat(' ', 1024*64);
	}
	
}

