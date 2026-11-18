<?php
/**
 * Kohana::find_file() must not leave the include paths (K2-17).
 */
class FindFileTest extends Kohana_TestCase {

	protected $links = array();

	protected function tearDown(): void
	{
		foreach ($this->links as $link)
		{
			if (is_link($link))
			{
				unlink($link);
			}
		}

		parent::tearDown();
	}

	public function provider_bad_names()
	{
		return array(
			array('views', '../../index'),
			array('views', '../../system/core/Kohana'),
			array('views', 'kohana/../../../index'),
			array('views', '..'),
			array('views', '/etc/passwd'),
			array('views', "kohana/error\0x"),
			array('views', 'kohana\\..\\..\\index'),
			array('../..', 'index'),
			array('config', '../../index'),
			array('i18n', '../../../index'),
			array('messages', '../../index'),
		);
	}

	/**
	 * @dataProvider provider_bad_names
	 */
	public function test_find_file_rejects_traversal($directory, $filename)
	{
		$this->assertFalse(Kohana::find_file($directory, $filename));
	}

	/**
	 * @dataProvider provider_bad_names
	 */
	public function test_find_file_required_throws($directory, $filename)
	{
		$this->expectException(Kohana_Exception::class);

		Kohana::find_file($directory, $filename, TRUE);
	}

	public function test_find_file_rejects_traversal_in_extension()
	{
		$this->assertFalse(Kohana::find_file('views', 'kohana/error', FALSE, 'php/../../../../index.php'));
	}

	public function test_view_rejects_traversal()
	{
		$this->expectException(Kohana_Exception::class);

		new View('../../index');
	}

	public function test_find_file_keeps_normal_names()
	{
		$this->assertSame(SYSPATH.'views/kohana/error.php', Kohana::find_file('views', 'kohana/error'));
		$this->assertSame(SYSPATH.'views/profiler/table.css', Kohana::find_file('views', 'profiler/table', FALSE, 'css'));
		$this->assertContains(SYSPATH.'config/database.php', Kohana::find_file('config', 'database'));
	}

	public function test_find_file_rejects_symlink_out_of_include_path()
	{
		$target = tempnam(sys_get_temp_dir(), 'kb_find_file');
		$this->links = array(SYSPATH.'views/kb_find_file_a.php', SYSPATH.'views/kb_find_file_b.php');

		foreach ($this->links as $link)
		{
			if ( ! @symlink($target, $link))
			{
				unlink($target);
				$this->markTestSkipped('Cannot create a symlink');
			}
		}

		try
		{
			$this->assertFalse(Kohana::find_file('views', 'kb_find_file_a'));

			Kohana::$find_file_realpath_check = FALSE;
			$this->assertSame($this->links[1], Kohana::find_file('views', 'kb_find_file_b'));
		}
		finally
		{
			Kohana::$find_file_realpath_check = TRUE;
			unlink($target);
		}
	}
}
