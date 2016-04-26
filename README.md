# PHP-StandfordCoreNLP

This script implements the Stanford CoreNLP command line interactive shell with PHP for the purpose of batch processing blocks of text. 

Since the Stanford CoreNLP libraries may take 3 to 15 seconds to load depending on the annotators selected, implementing the command line tools with a request / response scenario isn't ideal. Rather, iterating over records in a database provides a better a solution. 

Stanford CoreNLP will need to be installed on the server and capable of being run through the command line before this script will be usable.
