<?php defined('SYSPATH') or die('No direct script access.');
/**
 * MySQL database connection.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

defined('RUNS_MYSQLND') OR define('RUNS_MYSQLND', function_exists('mysqli_fetch_all'));

/**
 * Database_Mysql uses MySQLi, so this class only keeps the "mysqli" type name.
 */
class Database_Mysqli_Core extends Database_Mysql {

} // End Database_MySQLi
