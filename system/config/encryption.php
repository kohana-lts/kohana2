<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Encrypt configuration is defined in groups which allows you to easily switch
 * between different encryption settings for different uses.
 *
 * [!!] All groups inherit and overwrite the default group.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

/**
 * Group Options:
 *
 * - key    - Secret key for encryption and decryption. Use at least 32 random
 *            bytes, for example base64_encode(random_bytes(32)). There is no
 *            default: Encrypt throws an exception until you set a key.
 *
 * The next options are only for values that the old mcrypt version of
 * Encrypt wrote. These values have no MAC. Disable 'legacy' when the
 * migration is complete.
 *
 * - legacy - Decode values from the mcrypt version. Default FALSE.
 * - cipher - mcrypt cipher of these values. Only 'rijndael-128' is supported.
 * - mode   - mcrypt mode of these values: nofb, cbc, ncfb, cfb or ecb.
 */
$config['default'] = array
(
	'key'    => '',
	'legacy' => FALSE,
	'mode'   => 'nofb',
	'cipher' => 'rijndael-128',
);
