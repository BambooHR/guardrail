<?php

/** @phpstub */
class PDO {
	const ATTR_AUTOCOMMIT = null;
	const ATTR_CASE = null;
	const ATTR_CLIENT_VERSION = null;
	const ATTR_CONNECTION_STATUS = null;
	const ATTR_CURSOR = null;
	const ATTR_CURSOR_NAME = null;
	const ATTR_DEFAULT_FETCH_MODE = null;
	const ATTR_DRIVER_NAME = null;
	const ATTR_EMULATE_PREPARES = null;
	const ATTR_ERRMODE = null;
	const ATTR_FETCH_CATALOG_NAMES = null;
	const ATTR_FETCH_TABLE_NAMES = null;
	const ATTR_MAX_COLUMN_LEN = null;
	const ATTR_ORACLE_NULLS = null;
	const ATTR_PERSISTENT = null;
	const ATTR_PREFETCH = null;
	const ATTR_SERVER_INFO = null;
	const ATTR_SERVER_VERSION = null;
	const ATTR_STATEMENT_CLASS = null;
	const ATTR_STRINGIFY_FETCHES = null;
	const ATTR_TIMEOUT = null;
	const CASE_LOWER = null;
	const CASE_NATURAL = null;
	const CASE_UPPER = null;
	const CURSOR_FWDONLY = null;
	const CURSOR_SCROLL = null;
	const ERR_NONE = null;
	const ERRMODE_EXCEPTION = null;
	const ERRMODE_SILENT = null;
	const ERRMODE_WARNING = null;
	const FB_ATTR_DATE_FORMAT = null;
	const FB_ATTR_TIME_FORMAT = null;
	const FB_ATTR_TIMESTAMP_FORMAT = null;
	const FETCH_ASSOC = null;
	const FETCH_BOTH = null;
	const FETCH_BOUND = null;
	const FETCH_CLASS = null;
	const FETCH_CLASSTYPE = null;
	const FETCH_COLUMN = null;
	const FETCH_FUNC = null;
	const FETCH_GROUP = null;
	const FETCH_INTO = null;
	const FETCH_KEY_PAIR = null;
	const FETCH_LAZY = null;
	const FETCH_NAMED = null;
	const FETCH_NUM = null;
	const FETCH_OBJ = null;
	const FETCH_ORI_ABS = null;
	const FETCH_ORI_FIRST = null;
	const FETCH_ORI_LAST = null;
	const FETCH_ORI_NEXT = null;
	const FETCH_ORI_PRIOR = null;
	const FETCH_ORI_REL = null;
	const FETCH_PROPS_LATE = null;
	const FETCH_SERIALIZE = null;
	const FETCH_UNIQUE = null;
	const MYSQL_ATTR_CIPHER = null;
	const MYSQL_ATTR_COMPRESS = null;
	const MYSQL_ATTR_DIRECT_QUERY = null;
	const MYSQL_ATTR_FOUND_ROWS = null;
	const MYSQL_ATTR_IGNORE_SPACE = null;
	const MYSQL_ATTR_INIT_COMMAND = null;
	const MYSQL_ATTR_KEY = null;
	const MYSQL_ATTR_LOCAL_INFILE = null;
	const MYSQL_ATTR_MAX_BUFFER_SIZE = null;
	const MYSQL_ATTR_READ_DEFAULT_FILE = null;
	const MYSQL_ATTR_READ_DEFAULT_GROUP = null;
	const MYSQL_ATTR_SSL_CA = null;
	const MYSQL_ATTR_SSL_CAPATH = null;
	const MYSQL_ATTR_SSL_CERT = null;
	const MYSQL_ATTR_USE_BUFFERED_QUERY = null;
	const NULL_EMPTY_STRING = null;
	const NULL_NATURAL = null;
	const NULL_TO_STRING = null;
	const PARAM_BOOL = null;
	const PARAM_EVT_ALLOC = null;
	const PARAM_EVT_EXEC_POST = null;
	const PARAM_EVT_EXEC_PRE = null;
	const PARAM_EVT_FETCH_POST = null;
	const PARAM_EVT_FETCH_PRE = null;
	const PARAM_EVT_FREE = null;
	const PARAM_EVT_NORMALIZE = null;
	const PARAM_INPUT_OUTPUT = null;
	const PARAM_INT = null;
	const PARAM_LOB = null;
	const PARAM_NULL = null;
	const PARAM_STMT = null;
	const PARAM_STR = null;
	const SQLSRV_ATTR_DIRECT_QUERY = null;
	const SQLSRV_ATTR_QUERY_TIMEOUT = null;
	const SQLSRV_ENCODING_BINARY = null;
	const SQLSRV_ENCODING_DEFAULT = null;
	const SQLSRV_ENCODING_SYSTEM = null;
	const SQLSRV_ENCODING_UTF8 = null;
	const SQLSRV_TXN_READ_COMMITTED = null;
	const SQLSRV_TXN_READ_UNCOMMITTED = null;
	const SQLSRV_TXN_REPEATABLE_READ = null;
	const SQLSRV_TXN_SERIALIZABLE = null;
	const SQLSRV_TXN_SNAPSHOT = null;

	/**
	 *
	Creates a PDO instance representing a connection to a database

	 */
	public function __construct() {
	}

	/**
	 *
	Initiates a transaction

	 *
	 * @return bool
	 */
	public function beginTransaction() {
	}

	/**
	 *
	Commits a transaction

	 *
	 * @return bool
	 */
	public function commit() {
	}

	/**
	 * Get the requested schema information
	 *
	 * @param int    $schema_type
	 * @param string $table_name
	 * @param string $col_name
	 *
	 * @return array Array containing the schema information, when process is successful;
	 */
	public function cubrid_schema($schema_type, $table_name = null, $col_name = null) {
	}

	/**
	 *
	Fetch the SQLSTATE associated with the last operation on the database handle

	 *
	 * @return mixed Returns an SQLSTATE, a five characters alphanumeric identifier defined in
	 *               the ANSI SQL-92 standard.  Briefly, an SQLSTATE consists of a
	 *               two characters class value followed by a three characters subclass value. A
	 *               class value of 01 indicates a warning and is accompanied by a return code
	 *               of SQL_SUCCESS_WITH_INFO. Class values other than '01', except for the
	 *               class 'IM', indicate an error.  The class 'IM' is specific to warnings
	 *               and errors that derive from the implementation of PDO (or perhaps ODBC,
	 *               if you're using the ODBC driver) itself.  The subclass value '000' in any
	 *               class indicates that there is no subclass for that SQLSTATE.
	 */
	public function errorCode() {
	}

	/**
	 *
	Fetch extended error information associated with the last operation on the database handle

	 *
	 * @return array returns an array of error information
	 *               about the last operation performed by this database handle. The array
	 *               consists of the following fields:
	 */
	public function errorInfo() {
	}

	/**
	 *
	Execute an SQL statement and return the number of affected rows

	 *
	 * @param string $statement
	 *
	 * @return int returns the number of rows that were modified
	 *             or deleted by the SQL statement you issued. If no rows were affected,
	 *             returns .
	 */
	public function exec($statement) {
	}

	/**
	 *
	Retrieve a database connection attribute

	 *
	 * @param int $attribute
	 *
	 * @return mixed A successful call returns the value of the requested PDO attribute.
	 *               An unsuccessful call returns .
	 */
	public function getAttribute($attribute) {
	}

	/**
	 *
	Return an array of available PDO drivers

	 *
	 * @return array returns an array of PDO driver names. If
	 *               no drivers are available, it returns an empty array.
	 */
	public function getAvailableDrivers() {
	}

	/**
	 *
	Checks if inside a transaction

	 *
	 * @return bool Returns true if a transaction is currently active, and false if not.
	 */
	public function inTransaction() {
	}

	/**
	 *
	Returns the ID of the last inserted row or sequence value

	 *
	 * @param string $name
	 *
	 * @return string If a sequence name was not specified for the
	 *                parameter,  returns a
	 *                string representing the row ID of the last row that was inserted into
	 *                the database.
	 */
	public function lastInsertId($name = null) {
	}

	/**
	 * Creates a new large object
	 *
	 * @return string Returns the OID of the newly created large object on success, or false
	 *                on failure.
	 */
	public function pgsqlLOBCreate() {
	}

	/**
	 * Opens an existing large object stream
	 *
	 * @param string $oid
	 * @param string $mode
	 *
	 * @return resource Returns a stream resource on success.
	 */
	public function pgsqlLOBOpen($oid, $mode = 'rb') {
	}

	/**
	 * Deletes the large object
	 *
	 * @param string $oid
	 *
	 * @return bool
	 */
	public function pgsqlLOBUnlink($oid) {
	}

	/**
	 *
	Prepares a statement for execution and returns a statement object

	 *
	 * @param string $statement
	 * @param array  $driver_options
	 *
	 * @return PDOStatement If the database server successfully prepares the statement,
	 *                      returns a
	 *                      ``PDOStatement`` object.
	 *                      If the database server cannot successfully prepare the statement,
	 *                      returns false or emits
	 *                      ``PDOException`` (depending on ).
	 */
	public function prepare($statement, $driver_options = array()) {
	}

	/**
	 *
	Executes an SQL statement, returning a result set as a PDOStatement object

	 *
	 * @param string $statement
	 * @param string $statement
	 * @param int    $PDO::FETCH_COLUMN
	 * @param int    $colno
	 * @param string $statement
	 * @param int    $PDO::FETCH_CLASS
	 * @param string $classname
	 * @param array  $ctorargs
	 * @param string $statement
	 * @param int    $PDO::FETCH_INTO
	 * @param object $object
	 *
	 * @return PDOStatement returns a PDOStatement object, or false
	 *                      on failure.
	 */
	public function query($statement, $statement, $pdoFetchColumn, $colno, $statement, $pdoFetchClass, $classname, $ctorargs, $statement, $pdoFetchInto, $object) {
	}

/**
 *
Quotes a string for use in a query.

 *
 * @param string $string
 * @param int    $parameter_type
 *
 * @return string Returns a quoted string that is theoretically safe to pass into an
 *                SQL statement.  Returns false if the driver does not support quoting in
 *                this way.
 */
public function quote($string, $parameter_type = false) {
}

/**
 *
Rolls back a transaction

 *
 * @return bool
 */
public function rollBack() {
}

/**
 *
Set an attribute

 *
 * @param int   $attribute
 * @param mixed $value
 *
 * @return bool
 */
public function setAttribute($attribute, $value) {
}

/**
 *
Registers an aggregating User Defined Function for use in SQL statements

 *
 * @param string   $function_name
 * @param callable $step_func
 * @param callable $finalize_func
 * @param int      $num_args
 *
 * @return bool
 */
public function sqliteCreateAggregate($function_name, $step_func, $finalize_func, $num_args = null) {
}

/**
 *
Registers a User Defined Function for use in SQL statements

 *
 * @param string   $function_name
 * @param callable $callback
 * @param int      $num_args
 *
 * @return bool
 */
public function sqliteCreateFunction($function_name, $callback, $num_args = null) {
}
}