<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Database connection settings, defined as arrays, or "groups". If no group
 * name is used when loading the database library, the group named "default"
 * will be used.
 *
 * Each group can be connected to independently, and multiple groups can be
 * connected at once. For more information about connecting to a database group
 * see the [Database] library.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

/**
 * Group Options:
 *
 *  - benchmark     - Enable or disable database benchmarking
 *  - persistent    - Enable or disable a persistent connection
 *  - connection    - Array of connection specific parameters; alternatively,
 *                  you can use a DSN though it is not as fast and certain
 *                  characters could create problems (like an '@' character
 *                  in a password):
 *                  'connection'    => 'mysql://dbuser:secret@localhost/kohana'
 *  - character_set - Database character set
 *  - table_prefix  - Database table prefix
 *  - object        - Enable or disable object results
 *  - cache         - Enable or disable query caching
 *  - escape        - Enable automatic query builder escaping. FALSE turns off
 *                  all value and identifier quoting, so every value goes into
 *                  the SQL as is. Never set FALSE when a query uses untrusted
 *                  data. The library logs an alert when it is FALSE.
 *
 * Query builder input rules:
 *
 *  - Operators must be in Database_Builder::$operators and JOIN types in
 *    Database_Builder::$join_types. Add to these arrays for more.
 *  - ORDER BY directions must be ASC, DESC or a Database_Expression.
 *  - A column string with double quotes may contain only function calls
 *    such as COUNT(DISTINCT "users.id"). Use db::expr() for other SQL, or set
 *    Database::$legacy_column_expressions = TRUE for the old pass-through.
 *  - Use db::escape_like() for LIKE values from input.
 */
$config['default'] = array
(
	'benchmark'     => FALSE,
	'persistent'    => FALSE,
	'connection'    => array
	(
		'type'     => 'mysql',
		'user'     => 'dbuser',
		'pass'     => 'p@ssw0rd',
		'host'     => 'localhost',
		'port'     => FALSE,
		'socket'   => FALSE,
		'database' => 'kohana',
		'params'   => NULL,
	),
	'character_set' => 'utf8',
	'table_prefix'  => '',
	'object'        => TRUE,
	'cache'         => FALSE,
	'escape'        => TRUE
);