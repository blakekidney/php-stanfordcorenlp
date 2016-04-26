<?php
///////////////////////////////////////////////////////////////////////
//PAGE-LEVEL DOCBLOCK
/**
 * File for class: StanfordCoreNLP
 * @package    	PHP_StandfordCoreNLP
 * @copyright  	2016 Blake Kidney
 */
///////////////////////////////////////////////////////////////////////

/**
 * Class for using Stanford's Core Natural Language Processing.
 *
 * This is not intended for a single request / response through PHP.
 * Instead, this class is used to interact with the CoreNLP interactive shell
 * so as to batch process blocks of text.
 * 	 
 * @url  http://stanfordnlp.github.io/CoreNLP/	
 * 
 * @package    PHP_StandfordCoreNLP
 * @copyright  2016 Blake Kidney
 */
class StanfordCoreNLP {
	
	
	//////////////////////////////////////////////////////////
	//VARIABLES
	//////////////////////////////////////////////////////////
	
	/**
	 * The parsed data after processing.
	 * 
	 * @var  array
	 */	
	protected $data = false;
	
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
	 * A list of possible annotators with their descriptions.
	 * 
	 * @url  http://stanfordnlp.github.io/CoreNLP/annotators.html
	 * 
	 * @var  array
	 */	
	protected $annotators = array(

		'tokenize' 	=> 'Tokenizes the text. This component started as a PTB-style tokenizer, 
						but was extended since then to handle noisy and web text. The 
						tokenizer saves the character offsets of each token in the input 
						text, as CharacterOffsetBeginAnnotation and CharacterOffsetEndAnnotation.',
		
		'cleanxml' 	=> 'Remove xml tokens from the document',
		
		'ssplit' 	=> 'Splits a sequence of tokens into sentences.',
		
		'pos' 		=> 'Labels tokens with their POS tag. For more details see this page.',
		
		'lemma' 	=> 'Generates the word lemmas for all tokens in the corpus.',
		
		'ner' 		=> 'Recognizes named (PERSON, LOCATION, ORGANIZATION, MISC),  
						numerical (MONEY, NUMBER, ORDINAL, PERCENT), and temporal  
						(DATE, TIME, DURATION, SET) entities. Named entities are  
						recognized using a combination of three CRF sequence taggers  
						trained on various corpora, such as ACE and MUC. Numerical  
						entities are recognized using a rule-based system. Numerical  
						entities that require normalization, e.g., dates, are  
						normalized to NormalizedNamedEntityTagAnnotation.',
		
		'regexner' 	=> 'Implements a simple, rule-based NER over token sequences  
						using Java regular expressions. The goal of this Annotator  
						is to provide a simple framework to incorporate NE labels  
						that are not annotated in traditional NL corpora.  
						For example, the default list of regular expressions that  
						we distribute in the models file recognizes ideologies (IDEOLOGY),  
						nationalities (NATIONALITY), religions (RELIGION),  
						and titles (TITLE). Here is a simple example of how to use  
						RegexNER. For more complex applications, you might consider TokensRegex.',
		
		'sentiment' => 'Implements Socher et al’s sentiment model. Attaches a binarized  
						tree of the sentence to the sentence level CoreMap. The  
						nodes of the tree then contain the annotations from  
						RNNCoreAnnotations indicating the predicted class and  
						scores for that subtree.',
		
		'truecase' 	=> 'Recognizes the true case of tokens in text where this  
						information was lost, e.g., all upper case text. This  
						is implemented with a discriminative model implemented  
						using a CRF sequence tagger. The true case label, e.g.,  
						INIT_UPPER is saved in TrueCaseAnnotation. The token  
						text adjusted to match its true case is saved as  
						TrueCaseTextAnnotation.',
		
		'parse' 	=> 'Provides full syntactic analysis, using both the constituent  
						and the dependency representations. The constituent-based  
						output is saved in TreeAnnotation. We generate three  
						dependency-based outputs, as follows: basic, uncollapsed  
						dependencies, saved in BasicDependenciesAnnotation;  
						collapsed dependencies saved in CollapsedDependenciesAnnotation;  
						and collapsed dependencies with processed coordinations,  
						in CollapsedCCProcessedDependenciesAnnotation. Most users  
						of our parser will prefer the latter representation.',
		
		'depparse' 	=> 'Provides a fast syntactic dependency parser. We generate  
						three dependency-based outputs, as follows: basic, uncollapsed  
						dependencies, saved in BasicDependenciesAnnotation;  
						collapsed dependencies saved in CollapsedDependenciesAnnotation;  
						and collapsed dependencies with processed coordinations,  
						in CollapsedCCProcessedDependenciesAnnotation. Most users  
						of our parser will prefer the latter representation. ',
		
		'dcoref' 	=> 'Implements both pronominal and nominal coreference  
						resolution. The entire coreference graph (with head words  
						of mentions as nodes) is saved in CorefChainAnnotation. ',
		
		'relation' 	=> 'Stanford relation extractor is a Java implementation to  
						find relations between two entities. The current relation  
						extraction model is trained on the relation types (except  
						the ‘kill’ relation) and data from the paper Roth and Yih,  
						Global inference for entity and relation identification  
						via a linear programming formulation, 2007, except instead  
						of using the gold NER tags, we used the NER tags predicted  
						by Stanford NER classifier to improve generalization. The  
						default model predicts relations Live_In, Located_In,  
						OrgBased_In, Work_For, and None.',
		
		'natlog' 	=> 'Marks quantifier scope and token polarity, according to  
						natural logic semantics. Places an OperatorAnnotation on  
						tokens which are quantifiers (or other natural logic  
						operators), and a PolarityAnnotation on all tokens in  
						the sentence.',
		
		'quote' 	=> 'Deterministically picks out quotes delimited by “ or ‘  
						from a text. All top-level quotes, are supplied by the  
						top level annotation for a text. If a QuotationAnnotation  
						corresponds to a quote that contains embedded quotes,  
						these quotes will appear as embedded QuotationAnnotations  
						that can be accessed from the QuotationAnnotation that  
						they are embedded in. The QuoteAnnotator can handle  
						multi-line and cross-paragraph quotes, but any embedded  
						quotes must be delimited by a different kind of quotation  
						mark than its parents. Does not depend on any other  
						annotators. Support for unicode quotes is not yet present.'
					   
	);
	
	/**
	 * A list of parts of speech based on the Penn Treebank tag set.
	 * 
	 * @url  http://nlp.stanford.edu/software/tagger.shtml
	 * @url  http://acl.ldc.upenn.edu/J/J93/J93-2004.pdf
	 * @url  http://www.comp.leeds.ac.uk/amalgam/tagsets/upenn.html
	 * 
	 * @var  array
	 */	
	protected $pos = array(
		//Tag 		//Definition
		'CC' 	=> 'coordinating conjunction (and, or)',
		'CD' 	=> 'cardinal numeral (one, two, 2, etc.)',
		'DT' 	=> 'singular determiner/quantifier (this, that)',
		'EX' 	=> 'existential there',
		'FW' 	=> 'foreign word (hyphenated before regular tag)',
		'IN' 	=> 'preposition',
		'JJ' 	=> 'adjective',
		'JJR' 	=> 'comparative adjective',
		'JJS' 	=> 'semantically superlative adjective (chief, top)',
		'LS'	=> 'list item marker',
		'MD' 	=> 'modal auxiliary (can, should, will)',
		'NN' 	=> 'singular or mass noun',
		'NNS' 	=> 'plural noun',
		'NNP' 	=> 'proper noun or part of name phrase',
		'NNPS' 	=> 'possessive plural proper noun',
		'PDT'	=> 'predeterminer',
		'POS'	=> 'possessive ending',
		'PRP' 	=> 'Personal pronoun',
		'PRP$' 	=> 'Possessive pronoun',
		'RB' 	=> 'adverb',
		'RBR' 	=> 'comparative adverb',
		'RBS' 	=> 'adverb, superlative',
		'RP' 	=> 'adverb/particle (about, off, up)',
		'SYM'	=> 'symbol',
		'TO' 	=> 'infinitive marker to',
		'UH' 	=> 'interjection, exclamation',
		'VB' 	=> 'verb, base form',
		'VBD' 	=> 'verb, past tense',
		'VBG' 	=> 'verb, present participle/gerund',
		'VBN' 	=> 'verb, past participle',
		'VBP' 	=> 'verb, non 3rd person, singular, present',
		'VBZ' 	=> 'verb, 3rd. singular present',
		'WDT' 	=> 'wh- determiner (what, which)',
		'WP'	=> 'wh-pronoun',
		'WP$' 	=> 'possessive wh- pronoun (whose)',
		'WRB' 	=> 'wh- adverb (how, where, when)',
	);
	
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

	/**
	 * A list of the json error messages.
	 * 
	 * @var  array
	 */	
	protected $jsonErrors = false;
	
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
	 * @param  string  $annotators  (optional)  A string of the different annotators to use separated by a comma.
	 * @param  string  $memory  (optional)  A string of the memory to use with digits followed by g or m respectively.
	 * 
	 * @return  void
	 */
	public function open($annotators='tokenize,ssplit,pos,lemma,parse,sentiment', $memory='4g') {		
		
		//clean the $annotators string
		$annotators = trim(preg_replace('/\s+/', '', $annotators), ',');
		
		//check the annotators to see if they are valid
		$list = explode(',', $annotators);
		foreach($list as $item) {
			if(!isset($this->annotators[$item])) {
				throw new Exception('The annotator ('.$item.') is not valid. Please check the list.');
				exit();
			}
		}
		
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
		$cmd = 'java -cp "*" -Xmx'.$memory.' edu.stanford.nlp.pipeline.StanfordCoreNLP -annotators '.$annotators.' -stdin -outputFormat json';
		
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
		
		/*
		The only way to determine when the interactive shell is ready 
		for input is to look for the prompt in the stderr (message) output. 		
		*/
		
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
			
			//if we have no data, then break as an error may have occurred
			if($data === false) {				
				break;
			}
						
			//we have all the data once the prompt appears
			if(strpos($output, 'NLP> ') !== false) {
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
	 * @param  boolean  $returnJSON  (optional) Indicates whether to return JSON instead of PHP array.
	 *
	 * @return  array|string  The final processed text.
	 */
	public function processText($text, $returnJSON=false) {		
		
		//clear the processed data
		$this->data = false;
		
		//make sure the process has been opened
		if(!$this->opened || !$this->running()) return false;
		
		//trim the white space around the text
		$text = trim($text);
		
		//clean up double newlines
		$text = preg_replace('/(\r\n)+/', PHP_EOL, $text);
		
		//if we have no text return false
		if($text === '') {
			$this->display('The provided text string is blank.', 'red');
			return ($returnJSON) ? '[]' : array();
		}
		
		//the end of line signals the end of the text so it is necessary to include it
		$text = $text.PHP_EOL;
		
		//display the length of the text being written
		$this->display('Writing block of text with length of '.strlen($text));
		
		//write to the the input pipe		
		fwrite($this->pipes[0], $text);		
		
		//display that we are reading the results
		$this->display('Reading the results.');
		
		//obtain the output
		/*
		If the prompt appears and we call fgets, then the system will hang 
		while it waits for data since EOF is not returned. So, the only sure way 
		to determine when the output is finished is by looking for the prompt.
		When the prompt appears in the message output, then we know the processing 
		has been finished.
		*/
		
		//variables
		$timeout = 30; //timeout just in case something goes wrong
		$time = microtime(true);
		$output = '';
		$messages = '';
		$streamToRead = 'output';
		$endOutput = false;			
		$endMessage = false;
		$i = 0;
		$lastout = '';
		$paragrahTotal = substr_count($text, PHP_EOL);
		$paragraphCount = 0;
		
		//iterate until we have all the data
		while(true) {
			
			//keep count of the iterations
			$i++;
			
			//setup a variable to store the read data
			$data = '';
			
			//listen for a change in the stream
			$read   = array($this->pipes[1], $this->pipes[2]);
			$write  = NULL;
			$except = NULL;
			$numChangedStreams = stream_select($read, $write, $except, $timeout);
			
			//if this is the first iteration
			if($i === 1) {
				//always start with the output stream 
				//unless the message stream is the onlye thing in the read pipe
				if($numChangedStreams == 1 && $read[0] == $this->pipes[2]) {
					$streamToRead = 'messages';
				} else {
					$streamToRead = 'output';
				}			
			//otherwise, if the stream changed...
			} elseif($numChangedStreams > 1) {
				//check to see if the message stream has data 
				//or the output stream has ended, if so read it 
				if($this->pipeSize(2) > 0) {
					$streamToRead = 'messages';
				} else {
					$streamToRead = 'output';
				}	
				//echo '<pre style="color:#777">['.$i.'] STREAM CHANGE: '.$streamToRead.'</pre>';			
			}
			
			//if we only have one stream in the read buffer, set to read that stream
			if(count($read) == 1) {
				if($read[0] == $this->pipes[1]) $streamToRead = 'output';
				if($read[0] == $this->pipes[2]) $streamToRead = 'messages';
			}
			
			//read from output stream
			if($streamToRead == 'output' && !$endOutput) {
				//pull the data line by line
				$data = fgets($this->pipes[1]);				
				//add the data to the full output
				$output .= $data;
				if($data) $lastout = $data;
			}
			
			//read from messages stream
			if($streamToRead == 'messages' && !$endMessage) {
				//pull the data character by character
				$data = fgetc($this->pipes[2]);				
				//add the data to the full output
				$messages .= $data;
			}
			
			//since we are expecting json, we have all the output data at the last bracket
			//if there were multiple paragraphs, there will be multiple ending brackets
			//however, if an error occurs, this will never happen
			if($streamToRead == 'output' && $data === "}\r\n") {
				$paragrahCount++;
				if($paragrahCount == $paragrahTotal) {				
					$endOutput = true;
					//if the prompt has appeared, then break out of the loop
					if($endMessage) break;
				}
			}
			
			/***************************************************************
			THE STOP ABOVE DOESN'T WORK. THIS IS CAUSING PROBLEMS BECAUSE OF 
			MULTIPLE SENTENCES. THE PIPE IS NOT ENTIRELY CLEAR.
			**************************************************************/
									
			//we should have all the data once the prompt appears
			if(strpos($messages, 'NLP> ') !== false) {
				//indicate that the prompt has been found and has now appeared
				$endMessage = true;
				//if we have the end of our json, then break
				if($endOutput) break;
				//if not, then either an error occurred or we are still waiting
				//if the prompt is not alone, then additional messages occurred and we may have an error
				//otherwise, we need to wait longer for the rest of the json
				if($messages != 'NLP> ') break;	
			}
			
			//if both streams have ended, then break
			if($endOutput && $endMessage) break;
			
			//if we received data, then reset timer
			if($data) $time = microtime(true);
			
			//if the data is false, then EOF has been returned
			if($data === false) break;	
			
			//break from loop after timeout (in case the prompt doesn't appear)
			if((microtime(true) - $time) > $timeout) {
				//indicate that we broke out of the loop
				$this->display('Broke loop after '.$timeout.' seconds of receiving no new data.', 'red');
				$this->display('Exiting since the streams need to be cleared.', 'red');
				$this->close();
				exit();
				break;
			}
					
		}
				
		//if we have output, then parse the JSON
		if($output) {
			
			//now that we have obtained the output, we need to parse the JSON
			$this->display('Output obtained. Parsing JSON.');
			
			//convert the output to utf8
			$output = utf8_encode($output);
			
			//since we are expecting JSON, we need to ensure it is formatted properly
			$output = '['.str_replace('}'.PHP_EOL.'{', '},'.PHP_EOL.'{', $output).']';
			
			//parse the json into an array
			$this->data = @json_decode($output, true);
			if(!$this->data) {			
				$this->display('An error occurred parsing the JSON: '.$this->jsonLastErrorMessage(), 'red');
				$this->display($output, 'red');			
			} 
		
		}
		
		//if we have data, then validate it and fix the sentiment scores
		/*
		NOTE: The sentiment scores are off by one for some reason. So, we need to fix them.
			  They start with verynegative at 0 instead of 1. We will lookup the score based upon the text.
		*/
		if($this->data) {
			
			$this->display('Validating the data output to see if it matches the input.');
			
			$instr = preg_replace('/[^a-z0-9]/i', '', $text);  //remove all non-alphanumeric characters
			$outstr = '';
			foreach($this->data as &$paragraph) {
				foreach($paragraph['sentences'] as &$sentence) {
					//fix the score
					$sentence['sentimentValue'] = $this->sentimentTextToScore($sentence['sentiment']);					
					foreach($sentence['tokens'] as &$token) {
						$outstr .= $token['originalText'];
					}					
				}		
			}
			$outstr = preg_replace('/[^a-z0-9]/i', '', $outstr); //remove all non-alphanumeric characters
			
			if($outstr != $instr) {
				$this->display('The output string did not validate.', 'red');
				$this->display('INPUT: '.htmlspecialchars($instr), 'red');
				$this->display('OUTPUT: '.htmlspecialchars($outstr), 'red');
				Utils::preprint($this->data);
				return false;
			} else {
				$this->display('Valid!');
			}
			
		}		
		
		//display that we are finished
		$this->display('Processing complete!');
		
		//if we have messages, then display them
		if($messages) $this->display($messages, 'blue');
				
		return ($returnJSON ? $output : $this->data);
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
	 * Gets the keywords from the token array generated by nlp analysis.
	 * 
	 * TODO: This needs to be improved with a robust list of stopwords.
	 * 
	 * @param  array  $tokens  An array of tokens generated by nlp analysis.
	 * @param  array  $words  An array of words in which to add the keywords.
	 * 
	 * @return  void
	 */
	private function getKeywordsFromTokens($tokens, &$words) {
		if(!$tokens) return false;
		foreach($tokens as $token) {
			$word = strtolower($token['lemma']);
			//filter out undesired parts of speech
			//we want adjectives, nouns, verbs, adverbs
			if(!preg_match('/^(JJ|NN|RB|VB).*$/', $token['pos'])) continue;
			//if we have no letters, then skip
			if(!preg_match('/[a-z]/i', $word)) continue;
			//filter out particular keywords / stop words
			if(preg_match('/^(be|have|no|yes|none|m[rs]s?\.?|dr\.?)$/', $word)) continue;
			//use the lemma for the word if it is anything other than a noun
			if(!isset($words[$word])) $words[$word] = 0;
			$words[$word]++;	
		}	
	}
	
	//////////////////////////////////////////////////////////
	//PUBLIC UTILITIES
	//////////////////////////////////////////////////////////
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
		if($score == 1) return 5;
		if($score == 2) return 4;
		if($score == 4) return 2;
		if($score == 5) return 1;			
		return $score;
	}
	
	/**
	 * Calculates the average sentiment score based upon an array of values.
	 * 
	 * @param  array  $data  (optional) An array of processed data. If NULL, uses most recent result data.
	 * 
	 * @return  int  The average sentiment score.
	 */
	public function getSentiment($data=NULL) {
		if($data === NULL) $data = $this->data;
		if(!$data) return false;
		/*
		We exclude neutral values from our average 
		since they may influence the outcome causing. 
		The sentiment to be neutral even though 
		one sentence is negative causing the overall to be negative.
		*/		
		$c = 0;
		$s = 0;
		//iterate each paragraph
		foreach($data as $paragraph) {
			//iterate each of the sentences
			foreach($paragraph['sentences'] as $sentence) {
				$score = $this->sentimentTextToScore($sentence['sentiment']);
				//if the score is neutral, then skip it
				if($score == '3') continue;
				//increase the count
				$c++;
				//add the score to the sum
				$s += $score;								
			}		
		}
		//if we didn't count any values, then we contained all neutral, so return neutral
		if(!$c) return 3;
		//return the average
		return round($s/$c);
	}
	/**
	 * Gets the keywords from the array generated by nlp analysis.
	 * 
	 * @param  array  $data  (optional) An array of processed data. If NULL, uses most recent result data.
	 * 
	 * @return  array  An array of words where the key is the word and the value is the count.
	 */
	public function getKeywords($data=NULL) {
		if($data === NULL) $data = $this->data;
		if(!$data) return false;
		$words = array();
		//iterate each paragraph
		foreach($data as $paragraph) {
			//iterate each of the sentences
			foreach($paragraph['sentences'] as $sentence) {
				//get the keywords
				$this->getKeywordsFromTokens($sentence['tokens'], $words);				
			}		
		}		
		return $words;
	}
	
}

