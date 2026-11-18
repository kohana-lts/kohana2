<?php
/**
 * Runs one HTTP-like request under php-cgi for the session, cookie and URL
 * tests (Kohana_HttpTestCase). The scenario comes from the environment
 * variable KOHANA_TEST_SCENARIO (JSON):
 *
 *     config  config key => value
 *     ini     ini name => value
 *     steps   list of array(action, arguments...)
 *
 * The script prints a marker and then a JSON result. It never prints an
 * error page, because the error page contains the environment.
 */

define('IN_PRODUCTION', FALSE);
define('EXT', '.php');
define('DOCROOT', realpath(__DIR__.'/../..').DIRECTORY_SEPARATOR);
define('KOHANA', 'index.php');
define('APPPATH', str_replace('\\', '/', realpath(DOCROOT.'application')).'/');
define('MODPATH', str_replace('\\', '/', DOCROOT.'modules').'/');
define('SYSPATH', str_replace('\\', '/', realpath(DOCROOT.'system')).'/');

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$scenario = json_decode((string) getenv('KOHANA_TEST_SCENARIO'), TRUE);

$result = array('results' => array(), 'error' => NULL, 'notices' => array());

// Prints the result last, also after exit() in url::redirect()
register_shutdown_function(function () use ( & $result)
{
	while (ob_get_level() > 0)
	{
		ob_end_clean();
	}

	$result['session_id'] = session_id();
	$result['session'] = isset($_SESSION) ? $_SESSION : NULL;
	$result['headers'] = headers_list();

	echo "\n@@RESULT@@", json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR);
});

define('SYSTEM_BENCHMARK', 'system_benchmark');

require SYSPATH.'core/Benchmark'.EXT;

// As in application/Bootstrap.php: Kohana::render() reads this benchmark
Benchmark::start(SYSTEM_BENCHMARK.'_total_execution');

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

restore_error_handler();
restore_exception_handler();

set_error_handler(function ($code, $message) use ( & $result)
{
	$result['notices'][] = $message;
	return TRUE;
});

foreach ((array) $scenario['ini'] as $name => $value)
{
	ini_set($name, $value);
}

Kohana_Config::instance()->set('log.log_threshold', 0);

foreach ((array) $scenario['config'] as $key => $value)
{
	Kohana_Config::instance()->set($key, $value);
}

try
{
	foreach ((array) $scenario['steps'] as $step)
	{
		$action = array_shift($step);

		switch ($action)
		{
			case 'start':
				$result['results'][] = Session::instance()->id();
			break;
			case 'set':
				Session::instance()->set($step[0], $step[1]);
				$result['results'][] = TRUE;
			break;
			case 'get':
				$result['results'][] = Session::instance()->get($step[0]);
			break;
			case 'regenerate':
				Session::instance()->regenerate();
				$result['results'][] = Session::instance()->id();
			break;
			case 'destroy':
				Session::instance()->destroy();
				$result['results'][] = session_id();
			break;
			case 'write_close':
				Session::instance()->write_close();
				$result['results'][] = TRUE;
			break;
			case 'call':
				$result['results'][] = call_user_func_array(array_shift($step), $step);
			break;
			default:
				throw new Exception('Unknown action '.$action);
		}
	}
}
catch (Throwable $e)
{
	$result['error'] = get_class($e).': '.$e->getMessage();
}
