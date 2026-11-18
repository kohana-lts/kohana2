<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * File-based Cache driver.
 *
 * $Id: File.php 4605 2009-09-14 17:22:21Z kiall $
 *
 * @package    Cache
 * @author     Kohana Team
 * @copyright  (c) 2007-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Cache_File_Driver extends Cache_Driver {
	protected $config;
	protected $backend;

	public function __construct($config)
	{
		$this->config = $config;
		$this->config['directory'] = str_replace('\\', '/', realpath($this->config['directory'])).'/';

		if ( ! is_dir($this->config['directory']) OR ! is_writable($this->config['directory']))
			throw new Cache_Exception('The configured cache directory, :directory:, is not writable.', array(':directory:' => $this->config['directory']));
	}

	/**
	 * Finds the cache files for keys or tags. File names are
	 * "sha1(key)~tag hashes~expiry", so no key or tag reaches glob().
	 *
	 * @param  mixed   cache keys, tags, or TRUE for all files
	 * @param  bool    search for tags
	 * @return array   of filenames matching the id or tag
	 */
	public function exists($keys, $tag = FALSE)
	{
		if ($keys === TRUE)
		{
			// Find all the files
			return glob($this->config['directory'].'*~*~*') ?: array();
		}
		elseif ($tag === TRUE)
		{
			$hashes = array_map(array($this, 'tag_hash'), (array) $keys);

			$files = array();

			foreach (glob($this->config['directory'].'*~*~*') ?: array() as $path)
			{
				$parts = explode('~', basename($path));

				if (count($parts) === 3 AND $parts[1] !== '' AND array_intersect(explode('+', $parts[1]), $hashes))
				{
					$files[] = $path;
				}
			}

			return $files;
		}
		else
		{
			$paths = array();

			foreach ((array) $keys as $key)
			{
				$paths = array_merge($paths, glob($this->config['directory'].sha1((string) $key).'~*') ?: array());
			}

			return $paths;
		}
	}

	/**
	 * Returns the file name part for one tag.
	 *
	 * @param   string  tag
	 * @return  string
	 */
	protected function tag_hash($tag)
	{
		return substr(sha1((string) $tag), 0, 16);
	}

	public function set($items, $tags = NULL, $lifetime = NULL)
	{
		if ($lifetime !== 0)
		{
			// File driver expects unix timestamp
			$lifetime += time();
		}

		$tags = empty($tags) ? array() : array_values(array_map('strval', (array) $tags));

		$tag_hashes = implode('+', array_unique(array_map(array($this, 'tag_hash'), $tags)));

		$success = TRUE;

		foreach ($items as $key => $value)
		{
			if (is_resource($value))
				throw new Cache_Exception('Caching of resources is impossible, because resources cannot be serialised.');

			// Remove old cache file
			$this->delete($key);

			$data = serialize(array('key' => (string) $key, 'tags' => $tags, 'value' => $value));

			if ( ! (bool) file_put_contents($this->config['directory'].sha1((string) $key).'~'.$tag_hashes.'~'.$lifetime, $data, LOCK_EX))
			{
				$success = FALSE;
			}
		}

		return $success;
	}

	/**
	 * Reads a cache file.
	 *
	 * @param   string  file path
	 * @return  array   key, tags and value, or NULL for a missing, expired or old-format file
	 */
	protected function read($file)
	{
		if ($this->expired($file))
			return NULL;

		// Turn off errors while reading the file
		$ER = error_reporting(0);

		$data = file_get_contents($file);

		error_reporting($ER);

		if ($data === FALSE)
			return NULL;

		$data = unserialize($data);

		if ( ! is_array($data) OR ! isset($data['key'], $data['tags']) OR ! array_key_exists('value', $data))
			return NULL;

		return $data;
	}

	public function get($keys, $single = FALSE)
	{
		$items = array();

		foreach ((array) $keys as $key)
		{
			foreach ($this->exists($key) as $file)
			{
				if (($data = $this->read($file)) !== NULL AND $data['key'] === (string) $key)
				{
					$items[$data['key']] = $data['value'];
				}
			}
		}

		// Reutrn a single item if only one key was requested
		if ($single)
		{
			return (count($items) > 0) ? current($items) : NULL;
		}
		else
		{
			return $items;
		}
	}

	/**
	 * Get cache items by tag
	 */
	public function get_tag($tags)
	{
		// An array will always be returned
		$result = array();

		$tags = array_map('strval', (array) $tags);

		foreach ($this->exists($tags, TRUE) as $path)
		{
			if (($data = $this->read($path)) !== NULL AND array_intersect($tags, $data['tags']))
			{
				$result[$data['key']] = $data['value'];
			}
		}

		return $result;
	}

	/**
	 * Delete cache items by keys or tags
	 */
	public function delete($keys, $tag = FALSE)
	{
		$success = TRUE;

		$paths = $this->exists($keys, $tag);

		// Disable all error reporting while deleting
		$ER = error_reporting(0);

		foreach ($paths as $path)
		{
			// Remove the cache file
			if ( ! unlink($path))
			{
				Kohana_Log::add('error', 'Cache: Unable to delete cache file: '.$path);
				$success = FALSE;
			}
		}

		// Turn on error reporting again
		error_reporting($ER);

		return $success;
	}

	/**
	 * Delete cache items by tag
	 */
	public function delete_tag($tags)
	{
		return $this->delete($tags, TRUE);
	}

	/**
	 * Empty the cache. This also removes files in the old "key~tags~expiry" format.
	 */
	public function delete_all()
	{
		return $this->delete(TRUE);
	}

	/**
	 * Check if a cache file has expired by filename.
	 *
	 * @param  string|array   array of filenames
	 * @return bool
	 */
	protected function expired($file)
	{
		// Get the expiration time
		$expires = (int) substr($file, strrpos($file, '~') + 1);

		// Expirations of 0 are "never expire"
		return ($expires !== 0 AND $expires <= time());
	}
} // End Cache Memcache Driver
