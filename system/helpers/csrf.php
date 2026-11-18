<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * CSRF token helper. The token is random and bound to the session.
 *
 * ##### Example
 *
 *     // In the form view
 *     echo form::open('user/save'), csrf::field();
 *
 *     // In the controller
 *     $post = Validation::factory($_POST)
 *         ->add_rules('csrf_token', 'required', 'csrf::valid');
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class csrf_Core {

	/**
	 * @var  string  session key of the token
	 */
	public static $session_key = 'csrf_token';

	/**
	 * @var  string  form field name for csrf::field()
	 */
	public static $field = 'csrf_token';

	/**
	 * @var  object  session with get() and set(); NULL uses Session::instance()
	 */
	public static $session;

	/**
	 * Returns the token of the session. Creates one if there is none.
	 *
	 * @param   boolean  TRUE replaces the token, for example after a login
	 * @return  string   64 hex characters
	 */
	public static function token($new = FALSE)
	{
		$session = csrf::session();

		$token = $session->get(csrf::$session_key);

		if ($new OR ! is_string($token) OR ! preg_match('/^[0-9a-f]{64}$/D', $token))
		{
			$token = bin2hex(random_bytes(32));

			$session->set(csrf::$session_key, $token);
		}

		return $token;
	}

	/**
	 * Checks a token against the token of the session. It does not create
	 * a token, so a session without one never validates.
	 *
	 * @param   mixed    token from the request
	 * @return  boolean
	 */
	public static function valid($token)
	{
		if ( ! is_string($token) OR $token === '')
			return FALSE;

		$expected = csrf::session()->get(csrf::$session_key);

		if ( ! is_string($expected) OR $expected === '')
			return FALSE;

		return hash_equals($expected, $token);
	}

	/**
	 * Returns a hidden input with the token.
	 *
	 * @return  string
	 */
	public static function field()
	{
		return '<input type="hidden" name="'.htmlspecialchars(csrf::$field, ENT_QUOTES, 'UTF-8')
			.'" value="'.csrf::token().'" />';
	}

	/**
	 * @return  object  session
	 */
	protected static function session()
	{
		return (csrf::$session === NULL) ? Session::instance() : csrf::$session;
	}

} // End csrf
