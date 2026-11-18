<?php
/**
 * Prints the global data after the system.routing event as JSON. Input must
 * clean it although no code calls Input::instance(). The command line
 * argument is the URI, with GET data that Router::find_uri() parses.
 */
$_GET = array('x' => '<script>alert(1)</script>', 'bad key' => '1', 'c' => "a\x01b");
$_POST = array('p' => "<a href=\"javascript&colon;alert(1)\">x</a>");
$_COOKIE = array('bad key' => 'x', 'ok' => 'y');

require __DIR__.'/../../bootstrap.php';

Event::run('system.routing');

echo json_encode(array('get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE));
