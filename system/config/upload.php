<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Upload config
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

/**
 * Default directory for upload::save(). This path is relative to your index
 * file. Absolute paths are also supported.
 *
 * Keep uploads out of the web server document root, or the server can run
 * or serve an uploaded file as code or HTML. APPPATH is inside DOCROOT in the
 * stock layout: the rules in example.htaccess (or the same rules in your
 * server config) block direct access to it. Move the application directory
 * out of DOCROOT when you can.
 */
$config['directory'] = APPPATH.'upload';

/**
 * Enable or disable directory creation.
 */
$config['create_directories'] = FALSE;

/**
 * Remove spaces from uploaded filenames.
 */
$config['remove_spaces'] = TRUE;

/**
 * upload::type() also checks the file content with finfo against
 * config/mimes.php. Set FALSE to check only the file name extension.
 */
$config['check_mime'] = TRUE;
