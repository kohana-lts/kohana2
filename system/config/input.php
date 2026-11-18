<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Input library config.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */

/**
 * Proxies that Input::ip_address() trusts. Each item is an IP address or a
 * CIDR range, for example '10.0.0.0/8' or '2001:db8::/32'. The X-Forwarded-For
 * and Client-IP headers are read only when REMOTE_ADDR is in this list.
 * Add only proxies that you operate: a client can send these headers.
 * @default array()
 */
$config['trusted_proxies'] = array();
