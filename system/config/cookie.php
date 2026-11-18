<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Cookie config settings. These are the default settings used by the [cookie]
 * helper. You can override these settings by passing parameters to the cookie
 * helper functions.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

/**
 * Domain, to restrict the cookie to a specific website domain. For security,
 * you are encouraged to set this option. An empty setting allows the cookie
 * to be read by any website domain.
 * @default ''
 */
$config['domain'] = '';

/**
 * Restrict cookies to a specific path, typically the installation directory.
 * @default '/'
 */
$config['path'] = '/';

/**
 * Lifetime of the cookie. A setting of 0 makes the cookie active until the
 * users browser is closed or the cookie is deleted.
 * @default = 0
 */
$config['expire'] = 0;

/**
 * Send the cookie only over HTTPS. The default is FALSE, so that a new
 * installation also works over HTTP.
 *
 * [!!] Set this to TRUE on sites that use HTTPS. Otherwise the browser also
 * sends the session cookie over plain HTTP.
 * @default FALSE
 */
$config['secure'] = FALSE;

/**
 * Make the cookie accessible only through HTTP, not through JavaScript.
 * @default TRUE
 */
$config['httponly'] = TRUE;

/**
 * SameSite attribute of the cookie: 'Lax', 'Strict', 'None' or '' to not send
 * the attribute. Browsers ignore 'None' if secure is FALSE.
 * @default 'Lax'
 */
$config['samesite'] = 'Lax';

/**
 * Add the browser User-Agent to the signature of signed cookies. A browser
 * update then makes all its signed cookies invalid. The binding adds little
 * security, because an attacker can copy the User-Agent too.
 * @default FALSE
 */
$config['user_agent'] = FALSE;

/**
 * Cookie salt for signed cookies: the HMAC-SHA256 key of cookie::salt().
 * Set a long random secret in application/config/cookie.php, for example
 * base64_encode(random_bytes(32)). The cookie helper and the Session library
 * throw an exception while the salt is empty or 'K0hAN4 15 Th3 B357'.
 *
 * [!!] Changing this value will invalidate all existing cookies!
 * @default ''
 */
$config['salt'] = '';