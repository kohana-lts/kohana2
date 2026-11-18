<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Session driver interface
 *
 * $Id$
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
interface Session_Driver {

	/**
	 * Opens a session.
	 *
	 * @param   string   save path
	 * @param   string   session name
	 * @return  boolean
	 */
	public function open($path, $name);

	/**
	 * Closes a session.
	 *
	 * @return  boolean
	 */
	public function close();

	/**
	 * Reads a session.
	 *
	 * @param   string  session id
	 * @return  string
	 */
	public function read($id);

	/**
	 * Writes a session.
	 *
	 * @param   string   session id
	 * @param   string   session data
	 * @return  boolean
	 */
	public function write($id, $data);

	/**
	 * Destroys a session.
	 *
	 * @param   string   session id
	 * @return  boolean
	 */
	public function destroy($id);

	/**
	 * Regenerates the session id.
	 *
	 * @return  string
	 */
	public function regenerate();

	/**
	 * Garbage collection.
	 *
	 * @param   integer  session expiration period
	 * @return  boolean
	 */
	public function gc($maxlifetime);

} // End Session Driver Interface

/**
 * Connects a Session_Driver to the PHP session module. PHP 8.4 deprecates
 * session_set_save_handler() with callbacks, and PHP 8.1 checks the return
 * types of SessionHandlerInterface. This class keeps both away from drivers.
 *
 * validateId() is used with session.use_strict_mode. It calls the method
 * validate_id($id) of the driver if the driver has one, else it accepts the ID.
 *
 * @package    Kohana
 */
class Session_Driver_Handler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {

	/**
	 * @var  Session_Driver
	 */
	protected $driver;

	public function __construct(Session_Driver $driver)
	{
		$this->driver = $driver;
	}

	public function open(string $path, string $name): bool
	{
		return (bool) $this->driver->open($path, $name);
	}

	public function close(): bool
	{
		return (bool) $this->driver->close();
	}

	public function read(string $id): string|false
	{
		// PHP fails the session start if read() returns FALSE
		return (string) $this->driver->read($id);
	}

	public function write(string $id, string $data): bool
	{
		return (bool) $this->driver->write($id, $data);
	}

	public function destroy(string $id): bool
	{
		return (bool) $this->driver->destroy($id);
	}

	public function gc(int $max_lifetime): int|false
	{
		$result = $this->driver->gc($max_lifetime);

		return ($result === FALSE) ? FALSE : (int) $result;
	}

	public function validateId(string $id): bool
	{
		return method_exists($this->driver, 'validate_id') ? (bool) $this->driver->validate_id($id) : TRUE;
	}

	public function updateTimestamp(string $id, string $data): bool
	{
		return (bool) $this->driver->write($id, $data);
	}

} // End Session Driver Handler
