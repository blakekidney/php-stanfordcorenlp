<?php

require_once 'StanfordCoreNLP.php';

$texts = array(
	'This is just a test.',
	'The movie was great. I loved it.',
	'JJ Abrams banishes memories of George Lucas’s prequels with this outrageously exciting and romantic return to a world you hadn’t realised you’d missed so much.'
);

//initialize a new instance of the CoreNLP
$nlp = new StanfordCoreNLP(CORE_NLP_PATH);
$nlp->open();

foreach($texts as $text) {
	
	//process the text
	$data = $nlp->processText($text);
	
	//display the results
	echo '<h3>'.$text.'</h3>';
	echo '<pre>'.print_r($data, true).'</pre>';
	
	//pull the sentiment and keywords
	$sentiment = $nlp->getSentiment();
	$keywords = $nlp->getKeywords();
	
	echo '<pre>SENTIMENT: '.$sentiment.'</pre>';
	echo '<pre>KEYWORDS: '.print_r($keywords, true).'</pre>';
	
	//close the connection to the NLP
	$nlp->close();

}


































