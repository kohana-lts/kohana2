<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Database wrapper.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
abstract class Database_Core {

	const SELECT          =  1;
	const INSERT          =  2;
	const UPDATE          =  3;
	const DELETE          =  4;
	const CROSS_REQUEST   =  5;
	const PER_REQUEST     =  6;

	protected static $instances = array();

	// Global benchmarks
	public static $benchmarks = array();

	// Last execute query
	protected $last_query;

	// Configuration array
	protected $config;

	// Required configuration keys
	protected $config_required = array();

	// Raw server connection
	protected $connection;

	// Cache (Cache object for cross-request, array for per-request)
	protected $cache;

	// Quote character to use for identifiers (tables/columns/aliases)
	protected $quote = '"';

	/**
	 * @var  boolean  TRUE passes column strings that contain `"` to the SQL
	 * without a syntax check, as Kohana 2.4 did. Unsafe with request input.
	 */
	public static $legacy_column_expressions = FALSE;

	/**
	 * Returns a singleton instance of Database.
	 *
	 * @param   string  Database name
	 * @return  Database_Core
	 */
	public static function instance($name = 'default')
	{
		if ( ! isset(Database::$instances[$name]))
		{
			// Load the configuration for this database group
			$config = Kohana::config('database.'.$name);

			if (is_string($config['connection']))
			{
				// Parse the DSN into connection array
				$config['connection'] = Database::parse_dsn($config['connection']);
			}

			// Set the driver class name
			$driver = 'Database_'.ucfirst($config['connection']['type']);

			// Create the database connection instance
			Database::$instances[$name] = new $driver($config);
		}

		return Database::$instances[$name];
	}

	/**
	 * Constructs a new Database object
	 *
	 * @param   array  Database config array
	 * @return  Database_Core
	 */
	protected function __construct(array $config)
	{
		// Store the config locally
		$this->config = $config;

		if (empty($this->config['escape']))
		{
			// See the 'escape' option in config/database.php
			Kohana_Log::add('alert', 'Database: escape is FALSE, so values and identifiers are not quoted');
		}

		if ($this->config['cache'] !== FALSE)
		{
			if (is_string($this->config['cache']))
			{
				// Use Cache library
				$this->cache = new Cache($this->config['cache']);
			}
			elseif ($this->config['cache'] === TRUE)
			{
				// Use array
				$this->cache = array();
			}
		}
	}

	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Connects to the database
	 *
	 * @return void
	 */
	abstract public function connect();

	/**
	 * Disconnects from the database
	 *
	 * @return void
	 */
	abstract public function disconnect();

	/**
	 * Sets the character set
	 *
	 * @return void
	 */
	abstract public function set_charset($charset);

	/**
	 * Executes the query
	 *
	 * @param  string  SQL
	 * @return Database_Result
	 */
	abstract public function query_execute($sql);

	/**
	 * Escapes the given value
	 *
	 * @param  mixed  Value
	 * @return mixed  Escaped value
	 */
	abstract public function escape($value);

	/**
	 * List constraints for the given table
	 *
	 * @param  string  Table name
	 * @return array
	 */
	abstract public function list_constraints($table);

	/**
	 * List fields for the given table
	 *
	 * @param  string  Table name
	 * @return array
	 */
	abstract public function list_fields($table);

	/**
	 * List tables for the given connection (checks for prefix)
	 *
	 * @return array
	 */
	abstract public function list_tables();

	/**
	 * Converts the given DSN string to an array of database connection components
	 *
	 * @param  string  DSN string
	 * @return array
	 */
	public static function parse_dsn($dsn)
	{
		$db = array
		(
			'type'     => FALSE,
			'user'     => FALSE,
			'pass'     => FALSE,
			'host'     => FALSE,
			'port'     => FALSE,
			'socket'   => FALSE,
			'database' => FALSE
		);

		// Get the protocol and arguments
		list ($db['type'], $connection) = explode('://', $dsn, 2);

		if ($connection[0] === '/')
		{
			// Strip leading slash
			$db['database'] = substr($connection, 1);
		}
		else
		{
			$connection = parse_url('http://'.$connection);

			if (isset($connection['user']))
			{
				$db['user'] = $connection['user'];
			}

			if (isset($connection['pass']))
			{
				$db['pass'] = $connection['pass'];
			}

			if (isset($connection['port']))
			{
				$db['port'] = $connection['port'];
			}

			if (isset($connection['host']))
			{
				if ($connection['host'] === 'unix(')
				{
					list($db['socket'], $connection['path']) = explode(')', $connection['path'], 2);
				}
				else
				{
					$db['host'] = $connection['host'];
				}
			}

			if (isset($connection['path']) AND $connection['path'])
			{
				// Strip leading slash
				$db['database'] = substr($connection['path'], 1);
			}
		}

		return $db;
	}

	/**
	 * Returns the last executed query for this database
	 *
	 * @return string
	 */
	public function last_query()
	{
		return $this->last_query;
	}

	/**
	 * Executes the given query, returning the cached version if enabled
	 *
	 * @param  string  SQL query
	 * @return Database_Result
	 */
	public function query($sql)
	{
		// Start the benchmark
		$start = microtime(TRUE);

		if (is_array($this->cache))
		{
			$hash = $this->query_hash($sql);

			if (isset($this->cache[$hash]))
			{
				// Use cached result
				$result = $this->cache[$hash];

				// It's from cache
				$sql .= ' [CACHE]';
			}
			else
			{
				// No cache, execute query and store in cache
				$result = $this->cache[$hash] = $this->query_execute($sql);
			}
		}
		else
		{
			// Execute the query, cache is off
			$result = $this->query_execute($sql);
		}

		// Stop the benchmark
		$stop = microtime(TRUE);

		if ($this->config['benchmark'] === TRUE)
		{
			// Benchmark the query
			Database::$benchmarks[] = array('query' => $sql, 'time' => $stop - $start, 'rows' => count($result));
		}

		return $result;
	}

	/**
	 * Performs the query on the cache (and caches it if it's not found)
	 *
	 * @param   string  query
	 * @param   int     time-to-live (NULL for Cache default)
	 * @return  Database_Cache_Result
	 */
	public function query_cache($sql, $ttl)
	{
		if ( ! $this->cache instanceof Cache)
		{
			throw new Database_Exception('Database :name has not been configured to use the Cache library.');
		}

		// Start the benchmark
		$start = microtime(TRUE);

		$hash = $this->query_hash($sql);

		if (($data = $this->cache->get($hash)) !== NULL)
		{
			// Found in cache, create result
			$result = new Database_Cache_Result($data, $sql, $this->config['object']);

			// It's from the cache
			$sql .= ' [CACHE]';
		}
		else
		{
			// Run the query and return the full array of rows
			$data = $this->query_execute($sql)->as_array(TRUE);

			// Set the Cache
			$this->cache->set($hash, $data, NULL, $ttl);

			// Create result
			$result = new Database_Cache_Result($data, $sql, $this->config['object']);
		}

		// Stop the benchmark
		$stop = microtime(TRUE);

		if ($this->config['benchmark'] === TRUE)
		{
			// Benchmark the query
			Database::$benchmarks[] = array('query' => $sql, 'time' => $stop - $start, 'rows' => count($result));
		}

		return $result;
	}

	/**
	 * Generates a hash for the given query
	 *
	 * @param   string  SQL query string
	 * @return  string
	 */
	protected function query_hash($sql)
	{
		return sha1(str_replace("\n", ' ', trim($sql)));
	}

	/**
	 * Clears the internal query cache.
	 *
	 * @param   mixed  clear cache by SQL statement, NULL for all, or TRUE for last query
	 * @param   integer  Type of cache to clear, Database::CROSS_REQUEST or Database::PER_REQUEST
	 * @return  Database
	 */
	public function clear_cache($sql = NULL, $type = NULL)
	{
		if ($this->cache instanceof Cache AND ($type == NULL OR $type == Database::CROSS_REQUEST))
		{
			// Using cross-request Cache library
			if ($sql === TRUE)
			{
				$this->cache->delete($this->query_hash($this->last_query));
			}
			elseif (is_string($sql))
			{
				$this->cache->delete($this->query_hash($sql));
			}
			else
			{
				$this->cache->delete_all();
			}
		}
		elseif (is_array($this->cache) AND ($type == NULL OR $type == Database::PER_REQUEST))
		{
			// Using per-request memory cache
			if ($sql === TRUE)
			{
				unset($this->cache[$this->query_hash($this->last_query)]);
			}
			elseif (is_string($sql))
			{
				unset($this->cache[$this->query_hash($sql)]);
			}
			else
			{
				$this->cache = array();
			}
		}
	}

	/**
	 * Quotes the given value. With the config option 'escape' => FALSE it
	 * returns the value as is, so never use that option with untrusted data.
	 *
	 * @param   mixed  value
	 * @return  mixed
	 */
	public function quote($value)
	{
		if ( ! $this->config['escape'])
			return $value;

		if ($value === NULL)
		{
			return 'NULL';
		}
		elseif ($value === TRUE)
		{
			return 'TRUE';
		}
		elseif ($value === FALSE)
		{
			return 'FALSE';
		}
		elseif (is_int($value))
		{
			return (int) $value;
		}
		elseif ($value instanceof Database_Expression)
		{
			return (string) $value;
		}
		elseif (is_float($value))
		{
			// Convert to non-locale aware float to prevent possible commas
			return sprintf('%F', $value);
		}

		return '\''.$this->escape($value).'\'';
	}

	/**
	 * Quotes a table, adding the table prefix
	 * Reserved characters not allowed in table names for the builder are [ .*] (space, dot, asterisk)
	 *
	 * @param   string|array    table name or array - 'users u', 'users AS u' or array('u' => 'users')
	 * @param   string          table alias
	 * @return  string
	 * @throws  Database_Exception  for a table string with more than a name and an alias
	 */
	public function quote_table($table, $alias = NULL)
	{
		if (is_array($table))
		{
			// Using array('u' => 'user')
			$alias = key($table);
			$table = reset($table);
		}

		if ($table instanceof Database_Expression)
		{
			if ($alias)
			{
				if ($this->config['escape'])
				{
					$alias = $this->quote_identifier($alias);
				}

				return $table.' AS '.$alias;
			}

			return (string) $table;
		}

		$table = (string) $table;

		if (strpos($table, ' ') !== FALSE)
		{
			// Using format 'user u' or 'user AS u'
			$parts = preg_split('/\s+/', trim($table));

			if (count($parts) === 3 AND strtoupper($parts[1]) === 'AS')
			{
				unset($parts[1]);
			}

			if (count($parts) !== 2)
				throw new Database_Exception('Invalid table name, use "table alias" or a Database_Expression');

			list($table, $alias) = array_values($parts);
		}

		if ($this->config['table_prefix'])
		{
			$table = $this->config['table_prefix'].$table;
		}

		if ($this->config['escape'])
		{
			$table = $this->quote_identifier($table);

			if ($alias)
			{
				$alias = $this->quote_identifier($alias);
			}
		}

		if ($alias)
			return $table.' AS '.$alias;

		return $table;
	}

	/**
	 * Quotes column or table.column, adding the table prefix if necessary
	 * Reserved characters not allowed in table names for the builder are [ .*] (space, dot, asterisk)
	 *
	 * A string with double quotes is a function form, e.g. array('mycount' => 'COUNT("users.id")').
	 * It may contain only function calls, optional DISTINCT, `*`, integers and
	 * "quoted" names. Use a Database_Expression for other SQL.
	 *
	 * @param   string|array    column name or array('u' => 'COUNT("*")')
	 * @param   string          column alias
	 * @return  string
	 * @throws  Database_Exception  for a function form with other SQL
	 */
	public function quote_column($column, $alias = NULL)
	{
		if ($column === '*')
			return $column;

		if (is_array($column))
		{
			$alias = key($column);
			$column = reset($column);
		}

		if ($column instanceof Database_Expression)
		{
			if ($alias)
			{
				if ($this->config['escape'])
				{
					$alias = $this->quote_identifier($alias);
				}

				return $column.' AS '.$alias;
			}

			return (string) $column;
		}

		$column = (string) $column;

		if ( ! $this->config['escape'])
		{
			if ($this->config['table_prefix'] AND strpos($column, '.') !== FALSE)
			{
				$column = (strpos($column, '"') === FALSE)
					? $this->config['table_prefix'].$column
					: $this->prefix_quoted_names($column);
			}

			// Strip double quotes
			$column = str_replace('"', '', $column);

			if ($alias)
				return $column.' AS '.$alias;

			return $column;
		}

		if (strpos($column, '"') === FALSE)
		{
			if ($this->config['table_prefix'] AND strpos($column, '.') !== FALSE)
			{
				// Attach table prefix if table.column format
				$column = $this->config['table_prefix'].$column;
			}

			$column = $this->quote_name_path($column);
		}
		elseif (Database::$legacy_column_expressions)
		{
			if ($this->config['table_prefix'] AND strpos($column, '.') !== FALSE)
			{
				$column = $this->prefix_quoted_names($column);
			}

			if ($this->quote !== '"')
			{
				// Replace double quotes
				$column = str_replace('"', $this->quote, $column);
			}

			// Replace . with "." and unescape any asterisks
			$column = str_replace('.', $this->quote.'.'.$this->quote, $column);
			$column = str_replace($this->quote.'*'.$this->quote, '*', $column);
		}
		else
		{
			$column = $this->quote_function_form($column);
		}

		if ($alias)
			return $column.' AS '.$this->quote_identifier($alias);

		return $column;
	}

	/**
	 * Quotes one identifier. Embedded quote characters are doubled.
	 *
	 * @param   string  identifier
	 * @return  string
	 */
	public function quote_identifier($name)
	{
		return $this->quote.str_replace($this->quote, $this->quote.$this->quote, (string) $name).$this->quote;
	}

	/**
	 * Quotes each part of a dotted name, e.g. table.column. A `*` part stays as is.
	 *
	 * @param   string  name
	 * @return  string
	 */
	protected function quote_name_path($name)
	{
		$parts = array();

		foreach (explode('.', $name) as $part)
		{
			$parts[] = ($part === '*') ? '*' : $this->quote_identifier($part);
		}

		return implode('.', $parts);
	}

	/**
	 * Adds the table prefix to each "table.column" in a string.
	 *
	 * @param   string  column string
	 * @return  string
	 */
	protected function prefix_quoted_names($column)
	{
		return preg_replace('/"([^".]++)\.([^"]++)"/', '"'.$this->config['table_prefix'].'$1.$2"', $column);
	}

	/**
	 * Parses and quotes a function form such as COUNT(DISTINCT "users.id").
	 * Grammar: expr = "name" | WORD ( [DISTINCT] [arg {, arg}] ), arg = expr | * | number.
	 *
	 * @param   string  column string
	 * @return  string
	 * @throws  Database_Exception
	 */
	protected function quote_function_form($column)
	{
		if ( ! preg_match_all('/"[^"]+"|[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|[(),*]|\s+/', $column, $matches)
			OR implode('', $matches[0]) !== $column)
			throw new Database_Exception('Invalid column, use a Database_Expression for SQL expressions');

		$tokens = array();

		foreach ($matches[0] as $token)
		{
			if (trim($token) !== '')
			{
				$tokens[] = $token;
			}
		}

		$i = 0;
		$sql = $this->parse_function_form($tokens, $i);

		if ($sql === NULL OR $i !== count($tokens))
			throw new Database_Exception('Invalid column, use a Database_Expression for SQL expressions');

		return $sql;
	}

	/**
	 * Parses one expression of a function form, see [Database::quote_function_form].
	 *
	 * @param   array    tokens
	 * @param   integer  token position, moved past the expression
	 * @param   boolean  accept `*` and numbers (function arguments only)
	 * @return  string   quoted SQL, or NULL for a syntax error
	 */
	protected function parse_function_form(array $tokens, & $i, $argument = FALSE)
	{
		if ( ! isset($tokens[$i]))
			return NULL;

		$token = $tokens[$i++];

		if ($token[0] === '"')
		{
			$name = substr($token, 1, -1);

			if ($this->config['table_prefix'] AND strpos($name, '.') !== FALSE)
			{
				$name = $this->config['table_prefix'].$name;
			}

			return $this->quote_name_path($name);
		}

		if ($argument AND ($token === '*' OR ctype_digit($token[0])))
			return $token;

		if ( ! preg_match('/^[A-Za-z_]/', $token) OR ! isset($tokens[$i]) OR $tokens[$i] !== '(')
			return NULL;

		// Function call
		$sql = $token.'(';
		$i++;

		if (isset($tokens[$i]) AND $tokens[$i] === ')')
		{
			$i++;
			return $sql.')';
		}

		if (isset($tokens[$i]) AND strtoupper($tokens[$i]) === 'DISTINCT')
		{
			$sql .= 'DISTINCT ';
			$i++;
		}

		$args = array();

		while (TRUE)
		{
			if (($arg = $this->parse_function_form($tokens, $i, TRUE)) === NULL)
				return NULL;

			$args[] = $arg;

			if ( ! isset($tokens[$i]))
				return NULL;

			$token = $tokens[$i++];

			if ($token === ')')
				return $sql.implode(', ', $args).')';

			if ($token !== ',')
				return NULL;
		}
	}

	/**
	 * Get the table prefix
	 *
	 * @param  string  Optional new table prefix to set
	 * @return string
	 */
	public function table_prefix($new_prefix = NULL)
	{
		$prefix = $this->config['table_prefix'];

		if ($new_prefix !== NULL)
		{
			// Set a new prefix
			$this->config['table_prefix'] = $new_prefix;
		}

		return $prefix;
	}

	/**
	 * Fetches SQL type information about a field, in a generic format.
	 *
	 * @param   string  field datatype
	 * @return  array
	 */
	protected function sql_type($str)
	{
		static $sql_types;

		if ($sql_types === NULL)
		{
			// Load SQL data types
			$sql_types = Kohana::config('sql_types');
		}

		$str = trim($str);

		if (($open = strpos($str, '(')) !== FALSE)
		{
			// Closing bracket
			$close = strpos($str, ')', $open);

			// Length without brackets
			$length = substr($str, $open + 1, $close - 1 - $open);

			// Type without the length
			$type = substr($str, 0, $open).substr($str, $close + 1);
		}
		else
		{
			// No length
			$type = $str;
		}

		if (empty($sql_types[$type]))
			throw new Database_Exception('Undefined field type :type', array(':type' => $str));

		// Fetch the field definition
		$field = $sql_types[$type];

		$field['sql_type'] = $type;

		if (isset($length))
		{
			// Add the length to the field info
			$field['length'] = $length;
		}

		return $field;
	}

} // End Database
