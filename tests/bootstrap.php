<?php
/**
 * PHPUnit bootstrap: runs the Kohana setup of index.php and
 * application/Bootstrap.php, but not the routing and the controller.
 */

define('IN_PRODUCTION', FALSE);
define('EXT', '.php');
define('DOCROOT', realpath(__DIR__.'/..').DIRECTORY_SEPARATOR);
define('KOHANA', 'index.php');
define('APPPATH', str_replace('\\', '/', realpath(DOCROOT.'application')).'/');
define('MODPATH', str_replace('\\', '/', DOCROOT.'modules').'/');
define('SYSPATH', str_replace('\\', '/', realpath(DOCROOT.'system')).'/');

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

define('SYSTEM_BENCHMARK', 'system_benchmark');

require SYSPATH.'core/Benchmark'.EXT;
require SYSPATH.'core/Event'.EXT;
final class Event extends Event_Core {}
require SYSPATH.'core/Kohana'.EXT;
final class Kohana extends Kohana_Core {}
require SYSPATH.'core/Kohana_Exception'.EXT;
class Kohana_Exception extends Kohana_Exception_Core {}
require SYSPATH.'core/Kohana_Config'.EXT;
require SYSPATH.'libraries/drivers/Config'.EXT;
require SYSPATH.'libraries/drivers/Config/Array'.EXT;
final class Kohana_Config extends Kohana_Config_Core {}

Kohana::setup();

// PHPUnit handles errors and output
restore_error_handler();
restore_exception_handler();

while (ob_get_level() > 0)
{
	ob_end_flush();
}

require __DIR__.'/TestCase.php';
