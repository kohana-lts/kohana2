<?php
/**
 * File cache driver (K2-18): keys and tags must never act as glob patterns.
 */
class CacheFileTest extends Kohana_TestCase {

	protected $dir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = sys_get_temp_dir().'/kb_cache_'.bin2hex(random_bytes(6));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) as $file)
		{
			if (is_file($file))
			{
				unlink($file);
			}
		}

		rmdir($this->dir);

		parent::tearDown();
	}

	protected function cache($prefix = NULL)
	{
		return new Cache(array(
			'driver'   => 'file',
			'params'   => array('directory' => $this->dir),
			'lifetime' => 1800,
			'prefix'   => $prefix,
		));
	}

	public function provider_patterns()
	{
		return array(array('*'), array('secret_*'), array('secret_?'), array('[s]ecret_a'), array('*~*'));
	}

	/**
	 * @dataProvider provider_patterns
	 */
	public function test_key_is_not_a_pattern($pattern)
	{
		$cache = $this->cache();
		$cache->set('secret_a', 'top secret');

		$this->assertNull($cache->get($pattern));
		$this->assertSame(array(), $cache->get(array($pattern)));

		$cache->delete($pattern);
		$this->assertSame('top secret', $cache->get('secret_a'));
	}

	/**
	 * @dataProvider provider_patterns
	 */
	public function test_tag_is_not_a_pattern($pattern)
	{
		$cache = $this->cache();
		$cache->set('secret_a', 'top secret', array('secret_tag'));

		$this->assertSame(array(), $cache->get_tag($pattern));

		$cache->delete_tag($pattern);
		$this->assertSame('top secret', $cache->get('secret_a'));
	}

	public function test_keys_with_path_characters_stay_in_the_directory()
	{
		$cache = $this->cache();
		$keys = array('../escape', 'a/b', 'a\\b', 'with space', 'tilde~key', "nul\0key");

		foreach ($keys as $i => $key)
		{
			$cache->set($key, $i);
		}

		foreach ($keys as $i => $key)
		{
			$this->assertSame($i, $cache->get($key));
		}

		$this->assertFileDoesNotExist(dirname($this->dir).'/escape~~'.(time() + 1800));
		$this->assertCount(count($keys), glob($this->dir.'/*'));
	}

	public function test_multiple_keys_return_original_keys()
	{
		$cache = $this->cache();
		$cache->set(array('a/1' => 1, 'b 2' => 2));

		$this->assertSame(array('a/1' => 1, 'b 2' => 2), $cache->get(array('a/1', 'b 2', 'missing')));
	}

	public function test_prefix_is_stripped()
	{
		$cache = $this->cache('p_');
		$cache->set('k', 'v', array('t'));

		$this->assertSame('v', $cache->get('k'));
		$this->assertSame(array('k' => 'v'), $cache->get(array('k')));
		$this->assertSame(array('k' => 'v'), $cache->get_tag('t'));
	}

	public function test_tags()
	{
		$cache = $this->cache();
		$cache->set('a', 1, array('t1', 't2'));
		$cache->set('b', 2, array('t2'));
		$cache->set('c', 3);

		$this->assertSame(array('a' => 1), $cache->get_tag('t1'));
		$this->assertEqualsCanonicalizing(array('a' => 1, 'b' => 2), $cache->get_tag(array('t2')));

		$cache->delete_tag('t1');
		$this->assertNull($cache->get('a'));
		$this->assertSame(2, $cache->get('b'));

		$cache->set('b', 22);
		$this->assertSame(array(), $cache->get_tag('t2'));
		$this->assertSame(22, $cache->get('b'));
	}

	public function test_expired_items_are_missing()
	{
		$cache = $this->cache();
		$cache->set('old', 'x', array('t'), -10);
		$cache->set('forever', 'y', NULL, 0);

		$this->assertNull($cache->get('old'));
		$this->assertSame(array(), $cache->get_tag('t'));
		$this->assertSame('y', $cache->get('forever'));
	}

	public function test_delete_all_keeps_other_files()
	{
		$cache = $this->cache();
		$cache->set('a', 1);
		$cache->set('b', 2, array('t'));
		file_put_contents($this->dir.'/kohana_find_file_paths', 'internal');

		$cache->delete_all();

		$this->assertNull($cache->get('a'));
		$this->assertNull($cache->get('b'));
		$this->assertFileExists($this->dir.'/kohana_find_file_paths');
	}
}
