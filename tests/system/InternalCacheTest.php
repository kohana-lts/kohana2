<?php
/**
 * Kohana::cache() and Kohana::cache_save() with and without encryption.
 */
class InternalCacheTest extends Kohana_TestCase {

	protected $dir;
	protected $backup;

	protected static function set($name, $value)
	{
		(new ReflectionProperty('Kohana_Core', $name))->setValue(NULL, $value);
	}

	protected static function get($name)
	{
		return (new ReflectionProperty('Kohana_Core', $name))->getValue();
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->backup = array(self::get('internal_cache_path'), self::get('internal_cache_encrypt'), self::get('internal_cache_key'));

		$this->dir = sys_get_temp_dir().'/kohana2-cache-'.bin2hex(random_bytes(4)).'/';
		mkdir($this->dir);

		self::set('internal_cache_path', $this->dir);
		self::set('internal_cache_key', 'a long random test key for the internal cache');
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->dir.'*'));
		rmdir($this->dir);

		self::set('internal_cache_path', $this->backup[0]);
		self::set('internal_cache_encrypt', $this->backup[1]);
		self::set('internal_cache_key', $this->backup[2]);

		parent::tearDown();
	}

	public function provider_encrypt()
	{
		return array(array(TRUE), array(FALSE));
	}

	/**
	 * @dataProvider provider_encrypt
	 */
	public function test_round_trip($encrypt)
	{
		self::set('internal_cache_encrypt', $encrypt);

		$data = array('database' => array('pass' => 'secret password'), 'n' => 1);

		$this->assertTrue(Kohana::cache_save('test', $data, 60));
		$this->assertSame($data, Kohana::cache('test', 60));

		$raw = file_get_contents($this->dir.'kohana_test');
		$encrypt
			? $this->assertStringNotContainsString('secret password', $raw)
			: $this->assertStringContainsString('secret password', $raw);
	}

	public function test_changed_file_is_rejected()
	{
		self::set('internal_cache_encrypt', TRUE);

		Kohana::cache_save('test', array('a' => 1), 60);

		$raw = file_get_contents($this->dir.'kohana_test');
		$raw[40] = chr(ord($raw[40]) ^ 1);
		file_put_contents($this->dir.'kohana_test', $raw);

		$this->assertNull(Kohana::cache('test', 60));
		$this->assertFileDoesNotExist($this->dir.'kohana_test');
	}

	public function test_other_key_is_rejected()
	{
		self::set('internal_cache_encrypt', TRUE);

		Kohana::cache_save('test', array('a' => 1), 60);
		self::set('internal_cache_key', 'another key');

		$this->assertNull(Kohana::cache('test', 60));
	}

	public function test_objects_are_not_created()
	{
		self::set('internal_cache_encrypt', FALSE);

		file_put_contents($this->dir.'kohana_test', serialize(array('o' => new ArrayObject(array(1)))));

		$cache = Kohana::cache('test', 60);

		$this->assertNotInstanceOf('ArrayObject', $cache['o']);
	}
}
