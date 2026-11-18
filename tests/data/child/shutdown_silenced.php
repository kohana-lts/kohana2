<?php
/**
 * Runs the shutdown handler after an error that @ silenced. Prints JSON
 * only when the handler did not show an error page.
 */
require __DIR__.'/../../bootstrap.php';

Kohana_PHP_Exception::enable();

@file_get_contents(__DIR__.'/this-file-does-not-exist');

Kohana_PHP_Exception::shutdown_handler();

echo json_encode(array('ok' => TRUE));
