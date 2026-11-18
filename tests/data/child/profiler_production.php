<?php
/**
 * Prints the Profiler output with IN_PRODUCTION = TRUE as JSON.
 */
define('IN_PRODUCTION', TRUE);

require __DIR__.'/../../bootstrap.php';

$_POST = array('secret' => 'post-value');

Profiler::enable();

Kohana::$output = '<html><body>page</body></html>';
Event::run('system.display', Kohana::$output);

$result = json_encode(array('output' => Kohana::$output, 'return' => Profiler::render(TRUE)));

// Kohana::shutdown() must not print the page again
Event::clear('system.display');
Kohana::$output = '';

echo $result;
