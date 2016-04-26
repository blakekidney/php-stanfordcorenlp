<?php
///////////////////////////////////////////////////////////////////////
//PAGE-LEVEL DOCBLOCK
/**
 * File for class: DB
 * @package    	PHP_StandfordCoreNLP
 * @copyright  	2016 Blake Kidney
 */
///////////////////////////////////////////////////////////////////////

/**
 * Class for accessing an SQL database, either MySQL or Micrsoft SQL.
 *
 * Example:
 * 
 * 	 $db = new DB('mysql', 'server', 'database', 'user', 'password');
 * 	 $results = $db->selectAll('DEGREE');
 * 	
 * @package    PHP_StandfordCoreNLP
 * @copyright  2016 Blake Kidney
 */
class DB {
	/**
	 * The types of databases.
	 * 
	 * @var  array
	 */	
	private $types = array('mysql', 'mssql');
	/**
	 * The type of the database for which to connect as defined in the $types variable.
	 * 
	 * @var  string
	 */	
	private $type = '';
	/**
	 * The name of the database for which to connect.
	 * 
	 * @var  string
	 */	
	private $host = '';
	/**
	 * Indicates whether or not the database is connected.
	 * 
	 * @var  boolean
	 */	
	private $database = '';
	/**
	 * The name of the user to connect to the database.
	 * 
	 * @var  string
	 */	
	private $user = '';
	/**
	 * The password for connecting to the database.
	 * 
	 * @var  string
	 */	
	private $pass = '';
	/**
	 * The host of where to look for the database.
	 * 
	 * @var  string
	 */	
	private $connected = false;	
	
	/**
	 * The PDO database handler.
	 * 
	 * @var  type
	 */	
	public $pdo = false;
	/**
	 * The last error that arose while accessing the database.
	 * 
	 * @var  type
	 */	
	public $error = '';	
	/**
	 * Indicates whether to display errors in the browser.
	 * 
	 * @var  boolean
	 */	
	public $displayErrors = false;	
	/**
	 * Constructor.
	 * 
	 * @param  string  	$type  The type of database (i.e. 'mysql' or 'mssql').
	 * @param  string   $host   	The server where the database is found.
	 * @param  string  	$database  Name of the database for which to connect.
	 * @param  string   $user   	User for connecting to database.
	 * @param  string   $pass   	Password for connecting to database.
	 * @param  boolean  $connect   Whether or not to connect to the database immediately.
	 */
	function __construct($type, $host, $database, $user, $pass, $connect=true) {
		$this->type = strtolower($type);
		$this->host = $host;
		$this->database = $database;
		$this->user = $user;
		$this->pass = $pass;
		if($connect) $this->connect();
	}
	
	/**
	 * Opens a connection to the database. 
	 *
	 * @see  http://www.php.net/manual/en/pdo.construct.php
	 * @see  http://us3.php.net/manual/en/mysqlinfo.concepts.charset.php
	 * @return  boolean  Indicates whether the database was able to connect or not.
	 */
	public function connect() {		
		if($this->connected) return true;
		if(!in_array($this->type, $this->types)) {
			$this->error = 'Could not connected to database because the type ('.$this->type.') is unknown.';
			return false;
		}
		if($this->type == 'mysql') $dsn = 'mysql:dbname='.$this->database.';host='.$this->host.';charset=utf8';
		if($this->type == 'mssql') $dsn = 'sqlsrv:Database='.$this->database.';server='.$this->host;  //charset=windows-1252 or cp1252
		try {
			$this->pdo = new PDO($dsn, $this->user, $this->pass);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //set to stop script if there is an error so that we can capture this in a try catch block
			$this->connected = true;
			return true;	
		} catch(PDOException $e) {
			$this->error = $e->getCode().'-'.$e->getMessage();
			if(class_exists('IO')) IO::report($this->error);
			if($this->displayErrors) Utils::preprint($this->error);
			return false;
		}
	}	
	/**
	 * Executes an SQL statement, returning either data results of a PDOStatement object.
	 * 
	 *    $sql = "SELECT * FROM enrollments WHERE YEAR(datestart) != YEAR(dateend)";
	 *    $enrollments = $this->query($sql, true);
	 * 
	 * @param  string  $sql  SQL statement.
	 * @param  boolean  $fetchAll  (optional) Whether or not to fetch the results.
	 * 
	 * @see  	http://www.php.net/manual/en/pdo.query.php
	 * @return  mixed  The data results or a PDOStatement or false if there was an error.
	 */
	public function query($sql, $fetchAll=false) {
		if(!$this->pdo) return false;
		$this->error = '';
		try {
			$stmt = $this->pdo->query($sql, PDO::FETCH_ASSOC);
		} catch(Exception $e) {
			$this->error = $e->getCode().'-'.$e->getMessage().'<br>SQL: '.$sql;;
			if(class_exists('IO')) IO::report($this->error);
			if($this->displayErrors) Utils::preprint($this->error);
			return false;
		}
		if($fetchAll) return $stmt->fetchAll();
		return $stmt;
	}
	/**
	 * Executse an SQL statement and return the number of affected rows.
	 * 
	 * Used for operations that can not return data other then the affected rows.
	 * 
	 * @param  string  $sql  SQL statement.
	 *
	 * @see  http://www.php.net/manual/en/pdo.exec.php
	 *
	 * @return  boolean|integer  Number of rows affected or false if there was an error. 
	 */
	public function exec($sql) {
		if(!$this->pdo) return false;
		$this->error = '';
		try {
			return $this->pdo->exec($sql);
		} catch(Exception $e) {
			$this->error = $e->getCode().'-'.$e->getMessage().'<br>SQL: '.$sql;;
			if(class_exists('IO')) IO::report($this->error);
			if($this->displayErrors) Utils::preprint($this->error);
			return false;
		}
	}
	/**
	 * Executes a prepared query against the database.
	 * 
	 * Protects against sql injection attacks. Returns either the data or statement.
	 * EXAMPLE: 
	 *    $name = "person";
	 * 	  $sql =  "SELECT * FROM users WHERE username = ?";
	 *    $stmt = safequery($sql, array($name));
	 * 
	 * If the prepared data ($pdata) array has keys, you cannot
	 * use the question mark as a placeholder. You must use a
	 * colon with the key name. 
	 * EXAMPLE: 
	 *    $name = "person";
	 *    $sql =  "SELECT * FROM users WHERE username = :keyname";
	 *    $stmt = safequery($sql, array('keyname' => $name));
	 * 
	 * @see   http://www.php.net/manual/en/pdostatement.execute.php
	 * @see   http://www.php.net/manual/en/pdostatement.fetch.php
	 *
	 * @param  string  	$sql  		SQL statement.
	 * @param  array  	$pdata  	The prepared data.
	 * @param  boolean  $dofetch  	Whether or not to fetch the data.
	 * @param  integer  $fetchmode  The mode for fetching the data which determines how the data is returned.
	 * 		PDO::FETCH_ASSOC: returns an array indexed by column name as returned in your result set
	 * 		PDO::FETCH_BOTH (default): returns an array indexed by both column name and 0-indexed column number as returned in your result set
	 * 		PDO::FETCH_BOUND: returns TRUE and assigns the values of the columns in your result set to the PHP variables to which they were bound with the PDOStatement::bindColumn() method
	 * 		PDO::FETCH_CLASS: returns a new instance of the requested class, mapping the columns of the result set to named properties in the class. If fetch_style includes  
	 * 		PDO::FETCH_CLASSTYPE (e.g. PDO::FETCH_CLASS | PDO::FETCH_CLASSTYPE) then the name of the class is determined from a value of the first column.
	 * 		PDO::FETCH_INTO: updates an existing instance of the requested class, mapping the columns of the result set to named properties in the class
	 * 		PDO::FETCH_LAZY: combines PDO::FETCH_BOTH and PDO::FETCH_OBJ, creating the object variable names as they are accessed
	 * 		PDO::FETCH_NUM: returns an array indexed by column number as returned in your result set, starting at column 0
	 * 		PDO::FETCH_OBJ: returns an anonymous object with property names that correspond to the column names returned in your result set
	 *
	 * @return  mixed  The data results or a PDOStatement or false if there was an error.
	 */
	public function safequery($sql, $pdata=array(), $dofetch=false, $fetchmode=PDO::FETCH_ASSOC) {
		if(!$this->pdo) return false;
		$this->error = '';
		try {
			//created a prepared statement from the sql and execute
			$stmt = $this->pdo->prepare($sql);
			$stmt->setFetchMode($fetchmode);
			$stmt->execute($pdata);
		} catch(Exception $e) {
			$this->error = $e->getCode().'-'.$e->getMessage().'<br>SQL: '.$sql;
			if(class_exists('IO')) IO::report($this->error);
			if($this->displayErrors) Utils::preprint($this->error);
			return false;
		}
		if($dofetch) return $stmt->fetchAll();
		return $stmt;
	}	
	/**
	 * Gets the ID of the last inserted row or sequence value.
	 * 
	 * NOTE: lastInsertId() is not reliable on microsoft SQL. It only works some times.
	 *
	 * @see  http://www.php.net/manual/en/pdo.lastinsertid.php
	 * 
	 * @param  string  $table  Name of the table. Fall back just in case the 
	 * 	                       others fail to ensure returning the last id.
	 * 
	 * @return  string  The id of the last inserted row.
	 */
	public function lastid($table=false) {
		if(!$this->pdo) return false;
		if($lastid = $this->pdo->lastInsertId()) return $lastid;
		if($this->type === 'mysql') {
			$results = $this->safequery("SELECT LAST_INSERT_ID(?)", array($table), true);
			if($results) return current($results[0]);
		}
		if($this->type === 'mssql') {
			if($result = $this->query("SELECT @@IDENTITY AS LastID", true)) return $result[0]['LastID'];
			if($table && $result = $this->safequery("SELECT IDENT_CURRENT(?) AS LastID", array($table), true)) return $result[0]['LastID'];
		}
		return false;
	}	
	/**
	 * Gets last ID produced on that table, regardless of table/scope/connection.
	 *
	 * @see  http://stackoverflow.com/questions/6140529/how-can-i-get-the-id-of-the-last-inserted-row-using-pdo-with-sql-server
	 * 
	 * @return  string  The id of the last inserted row.
	 */
	public function lastTableId($table) {
		if($this->type === 'mysql') {
			$results = $this->safequery("SELECT LAST_INSERT_ID(?)", array($table), true);
			if($results) return current($results[0]);
		}
		if($this->type === 'mssql') {
			$results = $this->safequery("SELECT IDENT_CURRENT(?)", array($table), true);
			if($results) return current($results[0]);
		}
		return false;
	}
	/**
	 * Returns the next auto increment id for a table.
	 * 
	 * @param  string  $table  The name of the table.
	 * 
	 * @return  int  The next auto increment id.
	 */
	public static function nextid($table) {
		if($this->type === 'mysql') {
			$sql = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?";
			if(($result = $this->safequery($sql, array($table), true)) === false) return false;
			return $result[0]['AUTO_INCREMENT'];
		}
		return false;
	}
	/**
	 * Quotes strings so they are safe to use in queries. 
	 *
	 * This is your fallback if you're not using prepared statements.
	 * 
	 * @param  string  $unsafe  The string to be quoted. 
	 * 
	 * @see  http://www.php.net/manual/en/pdo.quote.php
	 * 
	 * @return  string|boolean  The quoted string or false if quoting is not supported.
	 */
	public function quote($unsafe) {
		return $this->pdo->quote($unsafe);
	}
	/**
	 * Gets the number of rows affected by a DELETE, INSERT, or UPDATE statement. 
	 *
	 * @param  PDOStatement  $stmt  The PDO statment for which to obtain the rows affected. 
	 *    If not supplied, will default to the last created statement.
	 *
	 * @see  http://www.php.net/manual/en/pdostatement.rowcount.php
	 *	 
	 * @return  integer  The number of rows affected.
	 */
	public function rowCount($stmt) {
		return $stmt->rowCount();
	}
	/**
	 * Checks to see if the value exists within a specific column in the database.
	 * 
	 * @param  string  $table   The database table to select.
	 * @param  string  $column  The database field to select.
	 * @param  string  $value   The value to select in the column.
	 * 
	 * @return  boolean  Returns true or false if the table column has the value.
	 */
	public function has($table, $column, $value) {
		$sql = "SELECT $column FROM $table WHERE $column = ?";
		$stmt = $this->safequery($sql, array($value));
		if(!$stmt) return false;
		return ($stmt->rowCount() > 0);
	}
	/**
	 * Checks to see if the table has an id within the id column.
	 *
	 * @param  string  $table  The table to check.
	 * @param  string|int  $id  The id to look for.
	 * 
	 * @return  boolean  Returns true or false if the table has a row with the id.
	 */
	public function hasid($table, $id) {
		if($id <= 0) return false;
		$sql = "SELECT id FROM $table WHERE id = ?";
		$stmt = $this->safequery($sql, array($id));
		if(!$stmt) return false;
		return ($stmt->rowCount() > 0);
	}
	
	/**
	 * Returns true or false if record exists.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $where  The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * 
	 * @return  boolean  Indicates whether the row exists.
	 */
	public function exists($table, $where, $pdata=array()) {
		$sql = false;
		if($this->type === 'mysql') $sql = "SELECT EXISTS(SELECT * FROM $table WHERE $where LIMIT 1)";
		if($this->type === 'mssql') $sql = "IF EXISTS(SELECT TOP 1 * FROM $table WHERE $where) SELECT 1 ELSE SELECT 0";
		if(!$sql) return false;
		if(empty($pdata)) $result = $this->query($sql, true);
		else $result = $this->safequery($sql, $pdata, true);
		if(!$result) return false;
		return intval(reset($result[0]));
	}
	/**
	 * Returns the count of the conditions based upon a column.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $column  The name of the column within the table.
	 * @param  string  $where  (optional) The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * 
	 * @return  int  The total count.
	 */
	public function total($table, $column, $where=false, $pdata=array()) {
		$sql = "SELECT COUNT(DISTINCT $column) AS total FROM $table".($where ? " WHERE $where" : "");
		if(empty($pdata)) $result = $this->query($sql, true);
		else $result = $this->safequery($sql, $pdata, true);
		if(!$result) return false;
		return $result['total'];
	}
	/**
	 * Select rows from the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $where  (optional) The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * @param  string  $what  The SQL what clause.
	 * @param  string  $more  Additional SQL appended to the end of the statement. Use for limiting or ordering.
	 * 
	 * @return  array|boolean  A multi-dimensional array of the data from the database or false if there was an error.
	 */
	public function select($table, $where=false, $pdata=array(), $what='*', $more='') {
		$sql = "SELECT $what FROM $table".($where ? " WHERE $where" : "")." $more";
		if(empty($pdata)) $stmt = $this->query($sql);
		else $stmt = $this->safequery($sql, $pdata);
		if(!$stmt) return false;
		return $stmt->fetchAll();
	}
	/**
	 * Selects one row/record from the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $where  (optional) The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * @param  string  $what  The SQL what clause.
	 * @param  string  $more  Additional SQL appended to the end of the statement. Use for limiting or ordering.
	 * 
	 * @return  array|boolean  An array of the data from the database or false if there was an error.
	 */
	public function selectOne($table, $where, $pdata=array(), $what='*', $more='') {
		$sql = false;
		if($this->type === 'mysql') $sql = "SELECT $what FROM $table".($where ? " WHERE $where" : "")." $more LIMIT 1";
		if($this->type === 'mssql') $sql = "SELECT TOP 1 $what FROM $table".($where ? " WHERE $where" : "")." $more";
		if(!$sql) return false;		
		if(empty($pdata)) $stmt = $this->query($sql);
		else $stmt = $this->safequery($sql, $pdata);
		if(!$stmt) return false;
		return $stmt->fetch();
	}
	/**
	 * Select a single value from a single record in the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $field  The field value to select.
	 * @param  string  $where  (optional) The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * @param  string  $more  Additional SQL appended to the end of the statement. Use for limiting or ordering.
	 * 
	 * @return  array|boolean  A single value from the database or false if there was an error.
	 */
	public function selectValue($table, $field, $where=false, $pdata=array(), $more='') {
		$sql = false;
		if($this->type === 'mysql') $sql = "SELECT $field FROM $table".($where ? " WHERE $where" : "")." $more LIMIT 1";
		if($this->type === 'mssql') $sql = "SELECT TOP 1 $field FROM $table".($where ? " WHERE $where" : "")." $more";
		if(!$sql) return false;		
		if(empty($pdata)) $stmt = $this->query($sql);
		else $stmt = $this->safequery($sql, $pdata);
		if(!$stmt) return false;
		$result = $stmt->fetch();
		return $result[$field];
	}
	/**
	 * Select a single value from a single record in the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $field  The field value to select.
	 * @param  string  $where  (optional) The SQL where statement.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * 
	 * @return  array|boolean  A single value from the database or false if there was an error.
	 */
	public function selectNextValue($table, $field, $where=false, $pdata=array()) {
		$sql = false;
		if($this->type === 'mysql') $sql = "SELECT $field FROM $table".($where ? " WHERE $where" : "")." LIMIT 1 ORDER BY $field DESC";
		if($this->type === 'mssql') $sql = "SELECT TOP 1 $field FROM $table".($where ? " WHERE $where" : "")."  ORDER BY $field DESC";
		if(!$sql) return false;		
		if(empty($pdata)) $stmt = $this->query($sql);
		else $stmt = $this->safequery($sql, $pdata);
		if(!$stmt) return false;
		$result = $stmt->fetch();
		if(empty($result)) return '1';
		$result[$field]++;
		return (string) $result[$field];
	}
	/**
	 * Select distinct rows from the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $columns  Comma separated list of fields. 
	 * @param  boolean  $desc  Whether to sort the results in descending order or not.
	 * 
	 * @return  array|boolean  An array of the data from the database or false if there was an error.
	 */
	public function selectDistinct($table, $columns, $desc=false) {
		$sql = "SELECT DISTINCT $columns FROM $table ORDER BY $columns".($desc ? ' DESC' : '');
		$stmt = $this->safequery($sql);
		if(!$stmt) return false;
		return $stmt->fetchAll();
	}
	/**
	 * Select all rows from the database.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $more  Additional SQL appended to the end of the statement. Use for limiting or ordering.
	 * 
	 * @return  array|boolean  An array of the data from the database or false if there was an error.
	 */
	public function selectAll($table, $more='') {
		$sql = "SELECT * FROM $table $more";
		$stmt = $this->safequery($sql);
		if(!$stmt) return false;
		return $stmt->fetchAll();
	}
	/**
	 * Select all rows from the database with selected fields returned.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $what  The SQL what clause.
	 * @param  string  $more  Additional SQL appended to the end of the statement. Use for limiting or ordering.
	 * 
	 * @return  array|boolean  An array of the data from the database or false if there was an error.
	 */
	public function selectAllWhat($table, $what, $more='') {
		$sql = "SELECT $what FROM $table $more";
		$stmt = $this->safequery($sql);
		if(!$stmt) return false;
		return $stmt->fetchAll();
	}
	/**
	 * Inserts a new row into the database.
	 * 	
	 * EXAMPLE: 
	 * 	$pdata = array(
	 * 					'username' => $username,
	 * 					'date' => sqldate(),
	 * 					'ip' => getRealIpAddr(),
	 * 					'error' => $error
	 * 				 );	
	 * 	$stmt = $db->insert('login_attempts', $pdata);
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function insert($table, $pdata) {
		$columns = "";
		$values = "";
		foreach($pdata as $key => $value) {
			$columns .= ($columns == "") ? "" : ", ";
			$columns .= $key;
			$values .= ($values == "") ? "" : ", ";
			$values .= ":$key";
		}
		$sql = "INSERT INTO $table ($columns) VALUES ($values)";
		return $this->safequery($sql, $pdata);
	}
	/**
	 * Inserts a multiple rows into a database.
	 * 
	 * INSERT INTO tbl_name (a,b,c) VALUES(1,2,3),(4,5,6),(7,8,9);
	 * 	
	 * EXAMPLE: 
	 * 	$cols = array('a', 'b', 'c');		
	 * 	$rows = array(
	 * 					array(1,2,3),
	 * 					array(4,5,6),
	 * 					array(7,8,9),
	 * 				 );	
	 * 	$stmt = $db->insertmulti('tablename', $cols, $rows);
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement. Also, the lastInsertId will return
	 * 	   the id of the first inserted row.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  array  $columns  An array of the columns affected.
	 * @param  array  $rows  A multidimensional array with the rows of data.
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function insertmulti($table, $rows, $columns=false) {
		if(empty($rows)) {
			$this->error = 'The $rows array is empty when inserting multiple values.';
			return false;
		}
		if(empty($columns)) {
			$cols = implode(',', array_keys(current($rows)));
		} else {
			$cols = implode(',', $columns);
		}
		if(!$cols) {
			$this->error = 'The $cols string is blank when inserting multiple values.';
			return false;
		}
		$pdata = array();
		$holders = '';
		foreach($rows as $row) {
			$holders .= '(';
			foreach($row as $val) {
				$pdata[] = $val;
				$holders .= '?,';		
			}
			$holders = rtrim($holders, ',').'),';
		}
		$holders = rtrim($holders, ',');
		$sql = "INSERT INTO $table ($cols) VALUES $holders";
		return $this->safequery($sql, $pdata);
	}
	/**
	 * Inserts a multiple rows from an array of values into a 2 column table.
	 * 
	 * This is intended for tables setup for checkbox values that represent a many to many design. 
	 * The table should be designed with two columns that combine to make a unique key. 
	 * When the array of check box values is provided from the request, the previous values 
	 * are deleted and the new values are inserted. 
	 * 	
	 * EXAMPLE: 
	 * News articles can be assigned to multiple categories.
	 * A database table is setup with a `newsid` column and a `categoryid` column linking the two.
	 * A user clicks a series of checkboxes to indicate which categories the article belongs.
	 * 
	 * 	$_POST['categories'] = array('2', '5', '8');
	 *  $rowCount = $db->insertCheckboxes('articles_categories', 'newsid', '12', 'categoryid', $_POST['categories']);
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $idcolumn  The name of the column to which the values belong.
	 * @param  string  $idvalue  The value of the column tow which the values belong.
	 * @param  string  $cbcolumn  The name of the column representing the checkbox values.
	 * @param  array  $cbvalues  The checkbox values from the request.
	 * 
	 * @return  int|boolean  The row count or false if there was an error.
	 */
	public function insertCheckboxes($table, $idcolumn, $idvalue, $cbcolumn, $cbvalues) {
		global $weds;
		//delete the current relational records
		$this->safequery("DELETE FROM $table WHERE $idcolumn = ?", array($idvalue));
		//insert the new multiple records
		if(empty($cbvalues)) return 0;
		$idata = array();
		foreach($cbvalues as $value) {
			$idata[] = array($idvalue, $value);
		}
		$stmt = $this->insertmulti($table, $idata, array($idcolumn, $cbcolumn));
		if(!$stmt) return false;
		return $stmt->rowCount();
	}

	/**
	 * Updates a current row in the database.
	 *
	 * Takes an array of data, where the keys in the array are the column names
	 * and the values are the data that will be inserted into those columns.
	 * 	
	 * EXAMPLE: 
	 * 	$pdata = array(
	 * 					'username' => $username,
	 * 					'date' => sqldate(),
	 * 					'ip' => getRealIpAddr(),
	 * 					'error' => $error
	 * 				 );	
	 * 	$stmt = $db->update('login_attempts', "id='$id'", $pdata);
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $where  The SQL where statement.
	 * @param  array  $pdata  The prepared data. The key is the field and the value is the data.
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function update($table, $where, $pdata) {
		$set = "";
		foreach($pdata as $key => $value) {
			$set .= ($set == "") ? "" : ", ";
			$set .= "$key=:$key";			
		}
		$sql = "UPDATE $table SET $set WHERE $where";
		return $this->safequery($sql, $pdata);
	}
	/**
	 * Same as above but allows you to include prepared data
	 * in the where statement as well.
	 *
	 * EXAMPLE: 
	 * 	$stmt = $db->pupdate('people', array('id'=>'8'), array('prefix'=>'Mrs'));
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement.
	 * 			
	 * MULTIPLE WHERE CONDITIONS: 
	 * 	SELECT * FROM usertable WHERE FirstName = 'John' AND LastName = 'Smith';
	 * 	SELECT * FROM usertable WHERE FirstName = 'John' OR LastName = 'Smith';
	 * 	SELECT * FROM Users WHERE FirstName = 'John' AND (LastName = 'Smith' OR LastName = 'Jones');
	 * 	SELECT * FROM Users WHERE (FirstName = 'John' OR FirstName = 'Jennifer') AND (LastName = 'Smith' OR LastName = 'Jones');
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  array  $wheredata  An array where the key is the field and the value is the value to be compared.
	 * @param  array  $pdata  The prepared data. The key is the field and the value is the data.
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function pupdate($table, $wheredata, $pdata) {
		$set = "";
		foreach($pdata as $key => $value) {
			$set .= ($set == "") ? "" : ", ";
			$set .= "$key=:$key";			
		}
		$where = "";
		//create different keys for the where clause just in case they equal the prepared data keys
		$wdata = array();
		foreach($wheredata as $key => $value) {
			$k = 'w_'.$key;
			$wdata[$k] = $value;
			$where .= ($where == "") ? "" : " AND ";
			$where .= "$key=:$k";			
		}
		$sql = "UPDATE $table SET $set WHERE $where";
		return $this->safequery($sql, array_merge($wdata,$pdata));
	}
	/**
	 * Updates all the rows in the database.
	 *
	 * Takes an array of data, where the keys in the array are the column names
	 * and the values are the data that will be inserted into those columns.
	 * 	
	 * EXAMPLE: 
	 * 	$stmt = $db->updateAll('enrollments', array('moodleid' => '0'));
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  array  $pdata  The prepared data. The key is the field and the value is the data.
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function updateAll($table, $pdata) {
		$set = "";
		foreach($pdata as $key => $value) {
			$set .= ($set == "") ? "" : ", ";
			$set .= "$key=:$key";			
		}
		$sql = "UPDATE $table SET $set";
		return $this->safequery($sql, $pdata);
	}
	/**
	 * Deletes a row in the database.
	 *
	 * EXAMPLE: 
	 * 	$stmt = $db->delete('users', "id=?", array($id));
	 * 	$affected = $stmt->rowCount();
	 * 	
	 * *NOTE: This returns the statement. To find out how
	 * 	   many rows were affected, get the rowCount from 
	 * 	   the statement.
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  string  $where  The SQL where statement. Use a question mark in place of the actual data.
	 * @param  array  $pdata  (optional) The prepared data. Array keys must be numerical and sequential (no specific keys).
	 * 
	 * @return  PDOStatment|boolean  Either the PDO statment or false if an error was encountered.
	 */
	public function delete($table, $where, $pdata=array()) {
		$sql = "DELETE FROM $table WHERE $where";
		return $this->safequery($sql, $pdata);	
	}	
	/**
	 * Provides a description of the table.
	 * 
	 * Includes the "Name", "Null?", "Null", "Type"
	 * for each table column
	 * EXAMPLE: 
	 * 	$result = $db->describe('people');
	 * 
	 * @param  string  $table  The name of the database table.
	 * 
	 * @return  array|boolean  Either an array of data from the database or false if there was an error.
	 */
	public function describe($table) {
		$sql = false;
		if($this->type === 'mysql') $sql = "DESCRIBE ?";
		if($this->type === 'mssql') {		
			$sql = "SELECT column_name AS [Name],
					   IS_NULLABLE AS [Null?],
					   DATA_TYPE + CASE
									 WHEN CHARACTER_MAXIMUM_LENGTH IS NULL THEN ''
									 WHEN CHARACTER_MAXIMUM_LENGTH > 99999 THEN ''
									 ELSE '(' + Cast(CHARACTER_MAXIMUM_LENGTH AS VARCHAR(5)) + ')' 
								   END AS [Type]
					FROM   INFORMATION_SCHEMA.Columns
					WHERE  table_name = ?";
		}
		if(!$sql) return false;	
		return $this->safequery($sql, array($table), true);
	}
	
	/**
	 * Provides an array of all the tables within the database.
	 * 
	 * @return  array|boolean  Either an array of database tables or false if there was an error.
	 */
	public function tables() {
		$tables = array();
		if($this->type === 'mysql') $sql = "SHOW TABLES";
		if($this->type === 'mssql') $sql = "SELECT TABLE_NAME AS name FROM Information_schema.Tables WHERE Table_type = 'BASE TABLE'";
		if(!isset($sql)) return false;
		if($results = $this->query($sql, true)) {
			foreach($results as $table) {
				$tables[] = current($table);
			}
		};
		return $tables;
	}
	
	/**
	 * Checks for whether a table exists or not.
	 * 
	 * @param  string  $table  The table to check for existence.
	 * 
	 * @return  boolean  Indicates whether the table exists or not.
	 */
	public function tableExists($table) {
		$sql = "IF EXISTS(
			SELECT * 
			FROM INFORMATION_SCHEMA.TABLES 
			WHERE TABLE_SCHEMA = 'dbo' 
				AND TABLE_NAME = ?
		) SELECT 1 AS Result ELSE SELECT 0 AS Result";
		if(!$results = $this->safequery($sql, array($table), true)) {
			return NULL;
		}
		return $results[0]['Result'];
	}
	
	/**
	 * Provides an array of all the views within the database.
	 * 
	 * @return  array|boolean  Either an array of database tables or false if there was an error.
	 */
	public function views() {
		$views = array();
		if($this->type === 'mysql') $sql = "SHOW VIEWS";
		if($this->type === 'mssql') $sql = "SELECT TABLE_NAME AS name FROM Information_schema.Views";
		if(!isset($sql)) return false;
		if($results = $this->query($sql, true)) {
			foreach($results as $view) {
				$views[] = current($view);
			}
		};
		return $views;
	}
	
	/**
	 * Provides the names of all the columns in the table.
	 * 
	 * EXAMPLE: 
	 * 	$result = $db->columns('people');
	 * 	foreach($result as $row) {
	 * 		echo $row['column_name'];
	 * 	}
	 * 
	 * @param  string  $table  The name of the database table.
	 * @param  array  $exclude  An array of column names to exclude.
	 * 
	 * @return  array|boolean  Either an array of data from the database or false if there was an error.
	 */
	public function columns($table, $exclude=false) {
		$sql = "SELECT column_name FROM information_schema.columns WHERE table_name = ?";
		if(empty($exclude)) return $this->safequery($sql, array($table), true);
		$sql .= " AND column_name IN(".implode(',', array_fill(0, count($exclude), '?')).")";
		return $this->safequery($sql, array_unshift($table, $exclude), true);
	}
		
	
	/*
	##########################################################################################
											UTILITIES
	##########################################################################################
	*/
	/**
	 * Returns the first entry in an array, if it is an array.
	 * 
	 * @param  array  $results  The results returned from the database query.
	 * 
	 * @return  mixed  The first item in the array or false if there was an error.
	 */
	public static function first($results) {
		if(!$results) return false;
		return current($results);
	}
	/**
	 * Fills with a list of question marks to act as placeholders.
	 *
	 * Use when creating an IN statement for SQL prepared statements.
	 * 
	 * @param  array  $ray  An array that is counted so that we can fill with placeholders for each item.
	 * 
	 * @return  string  A comma separated list of questions marks for each array item.
	 */
	public static function markerfill($ray) {
		if(empty($ray)) return '';
		return implode(', ', array_fill(0, count($ray), '?'));
	}
	/**
	 * Converts a value into sql date string.
	 * 
	 * If the value is not given, returns current time.
	 * 
	 * @param  mixed  $value  (optional) The date to format. If NULL, then use today. 
	 *    The date may be a string of the date in another format or UNIX timestamp.
	 * 
	 * @return  string  SQL formatted DATE string.
	 */
	public static function sqldate($value=NULL) {
		if($value === NULL) return date("Y-m-d");
		if($value === 0) return '0000-00-00';
		if($value === '') return false;
		if(is_numeric($value)) return date("Y-m-d", $value);
		if(strlen($value) < 3) return false;
		try { $dt = new DateTime($value); } catch(Exception $e) { return false; }
		return $dt->format("Y-m-d");
	}
	/**
	 * Converts a value into sql datetime string.
	 * 
	 * If the value is not given, returns current time.
	 * 
	 * @param  mixed  $value  (optional) The date to format. If NULL, then use today. 
	 *    The date may be a string of the date in another format or UNIX timestamp.
	 * 
	 * @return  string  SQL formatted DATETIME string.
	 */
	public static function sqldatetime($value=NULL) {
		if($value === NULL) return date("Y-m-d H:i:s");
		if($value === 0) return '0000-00-00 00:00:00';
		if($value === '') return false;
		if(is_numeric($value)) return date("Y-m-d H:i:s", $value);
		if(strlen($value) < 3) return false;
		try { $dt = new DateTime($value); } catch(Exception $e) { return false; }
		return $dt->format("Y-m-d H:i:s");
	}
}

