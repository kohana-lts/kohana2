<?php defined('SYSPATH') OR die('No direct access allowed.');

// Configurations written for the mcrypt version of this class use these.
defined('MCRYPT_RIJNDAEL_128') OR define('MCRYPT_RIJNDAEL_128', 'rijndael-128');
defined('MCRYPT_MODE_CBC') OR define('MCRYPT_MODE_CBC', 'cbc');
defined('MCRYPT_MODE_CFB') OR define('MCRYPT_MODE_CFB', 'cfb');
defined('MCRYPT_MODE_ECB') OR define('MCRYPT_MODE_ECB', 'ecb');
defined('MCRYPT_MODE_NOFB') OR define('MCRYPT_MODE_NOFB', 'nofb');
defined('MCRYPT_MODE_NCFB') OR define('MCRYPT_MODE_NCFB', 'ncfb');

/**
 * The Encrypt library provides authenticated two-way encryption of text and
 * binary strings with OpenSSL.
 *
 * encode() uses AES-256-CBC with a random IV, then signs the IV and the
 * ciphertext with HMAC-SHA256 (encrypt-then-MAC). decode() returns FALSE for
 * any value that was changed. HKDF derives both sub-keys from the configured
 * key, so the key must be a long random secret.
 *
 * ##### Loading the encryption library
 *
 *     $encrypt = Encrypt::instance();
 *
 *     // Or with a configuration group or array
 *     $encrypt = new Encrypt('other_group');
 *
 * The mcrypt version of this class had no MAC. To read values that it wrote,
 * set 'legacy' => TRUE in the configuration group, together with the old
 * 'cipher' and 'mode'. Only the rijndael-128 cipher is supported. Disable it
 * again after the migration: legacy values are not authenticated.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Encrypt_Core {

	// Prefix of values from encode()
	const PREFIX = 'v2:';

	const CIPHER = 'aes-256-cbc';

	const IV_SIZE = 16;

	const MAC_SIZE = 32;

	// Configuration
	protected $config;

	// Sub-keys derived from the configured key
	protected $encryption_key;
	protected $mac_key;

	/**
	 * Returns a singleton instance of Encrypt.
	 *
	 * @param   array  configuration options
	 * @return  Encrypt_Core
	 */
	public static function instance($config = NULL)
	{
		static $instance;

		// Create the singleton
		empty($instance) and $instance = new Encrypt((array) $config);

		return $instance;
	}

	/**
	 * Loads encryption configuration and derives the keys.
	 *
	 * @throws  Kohana_Exception
	 * @param   array|string  custom configuration or config group name
	 * @return  void
	 */
	public function __construct($config = FALSE)
	{
		if (is_string($config))
		{
			$name = $config;

			// Test the config group name
			if (($config = Kohana::config('encryption.'.$config)) === NULL)
				throw new Kohana_Exception('The :name: group is not defined in your configuration.', array(':name:' => $name));
		}

		if (is_array($config))
		{
			// Append the default configuration options
			$config += (array) Kohana::config('encryption.default');
		}
		else
		{
			// Load the default group
			$config = Kohana::config('encryption.default');
		}

		$config += array(
			'key'    => NULL,
			'mode'   => MCRYPT_MODE_NOFB,
			'cipher' => MCRYPT_RIJNDAEL_128,
			'legacy' => FALSE,
		);

		if ( ! isset($config['key']) OR (string) $config['key'] === '')
			throw new Kohana_Exception('To use the Encrypt library, you must set an encryption key in your config file');

		// The default key of Kohana 2.x releases is public, so it protects nothing
		if ($config['key'] === 'K0H@NA+PHP_7hE-SW!FtFraM3w0R|<' AND empty($config['legacy']))
			throw new Kohana_Exception('The encryption key is the public default key of Kohana 2. Set a new random key');

		$this->encryption_key = hash_hkdf('sha256', (string) $config['key'], 32, 'kohana-encrypt-aes-256-cbc');
		$this->mac_key        = hash_hkdf('sha256', (string) $config['key'], 32, 'kohana-encrypt-hmac-sha256');

		// Cache the config in the object
		$this->config = $config;

		Kohana_Log::add('debug', 'Encrypt Library initialized');
	}

	/**
	 * Encrypts and signs a string. The result is printable.
	 *
	 * @param   string  data to encrypt
	 * @return  string  encrypted data
	 */
	public function encode($data)
	{
		$iv = random_bytes(Encrypt::IV_SIZE);

		$encrypted = openssl_encrypt((string) $data, Encrypt::CIPHER, $this->encryption_key, OPENSSL_RAW_DATA, $iv);

		if ($encrypted === FALSE)
			throw new Kohana_Exception('Could not encrypt the data');

		$mac = hash_hmac('sha256', Encrypt::PREFIX.$iv.$encrypted, $this->mac_key, TRUE);

		return Encrypt::PREFIX.base64_encode($iv.$encrypted.$mac);
	}

	/**
	 * Verifies and decrypts a string from encode().
	 *
	 * @param   string  encoded string to be decrypted
	 * @return  string|FALSE  FALSE if the value is not valid or was changed
	 */
	public function decode($data)
	{
		if ( ! is_string($data))
			return FALSE;

		if (strncmp($data, Encrypt::PREFIX, strlen(Encrypt::PREFIX)) !== 0)
			return $this->config['legacy'] ? $this->decode_legacy($data) : FALSE;

		$raw = base64_decode(substr($data, strlen(Encrypt::PREFIX)), TRUE);

		// The ciphertext has at least one block
		if ($raw === FALSE OR strlen($raw) < Encrypt::IV_SIZE + 16 + Encrypt::MAC_SIZE)
			return FALSE;

		$iv        = substr($raw, 0, Encrypt::IV_SIZE);
		$encrypted = substr($raw, Encrypt::IV_SIZE, -Encrypt::MAC_SIZE);
		$mac       = substr($raw, -Encrypt::MAC_SIZE);

		$expected = hash_hmac('sha256', Encrypt::PREFIX.$iv.$encrypted, $this->mac_key, TRUE);

		if ( ! hash_equals($expected, $mac))
			return FALSE;

		return openssl_decrypt($encrypted, Encrypt::CIPHER, $this->encryption_key, OPENSSL_RAW_DATA, $iv);
	}

	/**
	 * Decrypts a value from the mcrypt version of this class with OpenSSL.
	 * These values have no MAC, so an attacker can change them.
	 *
	 * @param   string  base64 of IV and ciphertext
	 * @return  string|FALSE
	 */
	protected function decode_legacy($data)
	{
		if ($this->config['cipher'] !== MCRYPT_RIJNDAEL_128)
			return FALSE;

		$modes = array(
			MCRYPT_MODE_NOFB => array('ofb', TRUE),
			MCRYPT_MODE_CBC  => array('cbc', TRUE),
			MCRYPT_MODE_NCFB => array('cfb', TRUE),
			MCRYPT_MODE_CFB  => array('cfb8', TRUE),
			MCRYPT_MODE_ECB  => array('ecb', FALSE),
		);

		if ( ! isset($modes[$this->config['mode']]))
			return FALSE;

		list($mode, $has_iv) = $modes[$this->config['mode']];

		$data = base64_decode($data, TRUE);

		if ( ! $data)
			return FALSE;

		$iv = '';

		if ($has_iv)
		{
			if (strlen($data) < 16)
				return FALSE;

			$iv   = substr($data, 0, 16);
			$data = substr($data, 16);
		}
		elseif (strlen($data) >= 16)
		{
			// mcrypt wrote an unused IV in ECB mode too
			$data = substr($data, 16);
		}

		$key = Encrypt::legacy_key((string) $this->config['key']);
		$cipher = 'aes-'.(strlen($key) * 8).'-'.$mode;

		$plain = openssl_decrypt($data, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

		if ($plain === FALSE)
			return FALSE;

		// mcrypt padded with \0 bytes
		return rtrim($plain, "\0");
	}

	/**
	 * Pads or cuts a key as mcrypt did for rijndael-128 (16, 24 or 32 bytes).
	 *
	 * @param   string  key
	 * @return  string
	 */
	public static function legacy_key($key)
	{
		foreach (array(16, 24, 32) as $size)
		{
			if (strlen($key) <= $size)
				return str_pad($key, $size, "\0");
		}

		return substr($key, 0, 32);
	}

} // End Encrypt
