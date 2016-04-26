<?php

/*
The following example is based upon a survey. The script iterates 
over the open ended answers from the survey and then calculates 
the sentiment for each. It then updates the record in the database 
with the calculated sentiment value. Ideally, this script would 
be run periodically through a cron job.
*/


//database settings
define('DB_SERVER', 	'mysql.server.com');
define('DB_NAME', 		'mydb');
define('DB_USER', 		'myname');
define('DB_PASSWORD', 	'maypassword');

//error settings - display errors when testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

//raise time limit to 30 minutes just in case
set_time_limit(30*60);

//increase the memory limit allowed
ini_set('memory_limit', '1G');

//load the necessary configuration and classes
require_once 'DB.php';
require_once 'StanfordSentiment.php';

$sql = "
	SELECT id, question, answer, sentiment 
	FROM survey_answers 
	WHERE sentiment IS NOT NULL
	LIMIT 1000
";

//connect to the databases
$db = new DB('mysql', DB_SERVER, DB_NAME, DB_USER, DB_PASSWORD);

//pull the records
$records = $db->query($sql, true);

if(!empty($records)) {
	
	//initialize a new instance of the CoreNLP
	$nlp = new StanfordSentiment(CORE_NLP_PATH);
	$nlp->open();
	
	foreach($records as $row) {
		
		$text = $row['answer'];
		$id = $row['id'];
		$score = 0;
		
		//if the text is "not applicable", set the score to neutral
		if(preg_match('/^\s*(n\/a|not\s+applicable)\s*$/i', $text)) {
			$score = 3;	
		}
		
		//if the text is "yes", "no", or "none"; then we need to consider the question asked
		//as their sentiment depends on whether they agree with the sentiment of the question
		if(preg_match('/^[^a-z]*(yes|no)(ne|thing|tta)?[^a-z]*$/i', $text, $m)) {
			$yesno = strtolower($m[1]);
			$text = $row['question'];
		}
		
		//process the text
		if(!$score) $score = $nlp->processText($text);
				
		//if they answered "no" to the question, we need to invert the sentiment as they disagree
		if($yesno == 'no') $score = $nlp->invertSentiment($score);
		
		//update the database with the sentiment score
		$db->pupdate('survey_answers', array('id' => $id), array('sentiment' => $score));
		
	}
	
	//close the connection to the NLP
	$nlp->close();
	
}



































