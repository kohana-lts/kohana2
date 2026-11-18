<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Session cookie driver.
 *
 * The session data lives in the cookie "<session.name>_data". The Encrypt
 * library always encrypts and authenticates it, together with the session ID
 * and an expiry time. read() returns no data for a changed, expired or
 * foreign cookie. The encryption key must be secret: with the key, a client
 * can write any session data, and PHP unserializes it (object injection).
 *
 * [!!] The server keeps no state. A copied data cookie stays valid until it
 * expires, also after destroy() or regenerate(). Use a server-side driver
 * when a logout must end the session at once.
 *
 * $Id$
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Session_Cookie_Driver implements Session_Driver {

	// First field of the payload
	const VERSION = 'k2s1';

	// Maximum length of the cookie value, the signature included
	const MAX_LENGTH = 4000;

	protected $cookie_name;
	protected $encrypt; // Library

	// Lifetime of a payload in seconds
	protected $lifetime;

	public function __construct()
	{
		$this->cookie_name = Kohana::config('session.name').'_data';

		// session.encryption can name an Encrypt config group
		$group = Kohana::config('session.encryption');
		$this->encrypt = (is_string($group) AND $group !== '') ? new Encrypt($group) : Encrypt::instance();

		$this->lifetime = (int) Kohana::config('session.expiration');

		if ($this->lifetime <= 0)
		{
			// Same limit as session.gc_maxlifetime in Session
			$this->lifetime = 86400;
		}

		Kohana_Log::add('debug', 'Session Cookie Driver Initialized');
	}

	public function open($path, $name)
	{
		return TRUE;
	}

	public function close()
	{
		return TRUE;
	}

	public function read($id)
	{
		$data = cookie::get($this->cookie_name);

		if ( ! is_string($data) OR $data === '')
			return '';

		$data = $this->encrypt->decode($data);

		if ( ! is_string($data))
			return '';

		// Payload: version|expires|session id|session data
		$data = explode('|', $data, 4);

		if (count($data) !== 4 OR $data[0] !== Session_Cookie_Driver::VERSION OR ! ctype_digit($data[1]))
			return '';

		if ((int) $data[1] < time() OR ! hash_equals($data[2], (string) $id))
			return '';

		return $data[3];
	}

	/**
	 * Checks that the data cookie belongs to this ID, for session.use_strict_mode.
	 *
	 * @param   string   session id
	 * @return  boolean
	 */
	public function validate_id($id)
	{
		return $this->read($id) !== '';
	}

	public function write($id, $data)
	{
		if ( ! Session::$should_save)
			return TRUE;

		$data = $this->encrypt->encode(Session_Cookie_Driver::VERSION.'|'.(time() + $this->lifetime).'|'.$id.'|'.$data);

		if (strlen($data) + cookie::SIGNATURE_LENGTH + 1 > Session_Cookie_Driver::MAX_LENGTH)
		{
			Kohana_Log::add('error', 'Session ('.$id.') data exceeds the 4KB limit, ignoring write.');
			return FALSE;
		}

		return cookie::set($this->cookie_name, $data, Kohana::config('session.expiration'));
	}

	public function destroy($id)
	{
		return cookie::delete($this->cookie_name);
	}

	public function regenerate()
	{
		session_regenerate_id(TRUE);

		// Return new id
		return session_id();
	}

	public function gc($maxlifetime)
	{
		return TRUE;
	}

} // End Session Cookie Driver Class
