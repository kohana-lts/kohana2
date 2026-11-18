<?php
/**
 * upload helper (K2-16).
 */
class UploadTest extends Kohana_TestCase {

	protected $files = array();

	protected function tearDown(): void
	{
		foreach (array_reverse($this->files) as $file)
		{
			is_dir($file) ? rmdir($file) : (is_file($file) AND unlink($file));
		}

		parent::tearDown();
	}

	protected function upload($name, $content)
	{
		$tmp = tempnam(sys_get_temp_dir(), 'kb_upload');
		file_put_contents($tmp, $content);
		$this->files[] = $tmp;

		return array('name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content));
	}

	protected function png()
	{
		return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
	}

	public function test_type_rejects_php_with_image_extension()
	{
		$file = $this->upload('x.php.jpg', "<?php system(\$_GET['c']); ?>");

		$this->assertFalse(upload::type($file, array('jpg', 'png')));
	}

	public function test_type_accepts_real_image()
	{
		$this->assertTrue(upload::type($this->upload('Photo.PNG', $this->png()), array('jpg', 'png')));
	}

	public function test_type_rejects_extension_not_allowed()
	{
		$this->assertFalse(upload::type($this->upload('x.png.php', $this->png()), array('jpg', 'png')));
		$this->assertFalse(upload::type($this->upload('png', $this->png()), array('jpg', 'png')));
	}

	public function test_type_rejects_extension_without_mime_list()
	{
		$this->assertFalse(upload::type($this->upload('x.kbunknown', $this->png()), array('kbunknown')));
	}

	public function test_type_mime_check_opt_out()
	{
		$file = $this->upload('x.php.jpg', "<?php echo 1; ?>");

		$this->set_config('upload.check_mime', FALSE);
		$this->assertTrue(upload::type($file, array('jpg')));

		$this->set_config('upload.check_mime', TRUE);
		$this->assertTrue(upload::type($file, array('jpg'), FALSE));
	}

	public function test_type_ignores_missing_upload()
	{
		$this->assertTrue(upload::type(array('name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => ''), array('jpg')));
	}

	public function provider_filenames()
	{
		return array(
			array('x.php.jpg', 'jpg'),
			array('Photo.JPEG', 'jpeg'),
			array('archive.tar.gz', 'gz'),
			array('noext', NULL),
			array('evil.jpg.', NULL),
			array('evil.p h p', NULL),
			array("a.ph\0p", NULL),
			array('.htaccess', 'htaccess'),
			array('a.'.str_repeat('x', 40), NULL),
		);
	}

	/**
	 * @dataProvider provider_filenames
	 */
	public function test_filename_is_random_with_one_safe_extension($name, $ext)
	{
		$filename = upload::filename($name);

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}'.($ext === NULL ? '' : '\.'.preg_quote($ext, '/')).'$/D', $filename);
		$this->assertNotSame($filename, upload::filename($name));
	}

	public function test_save_creates_directory_with_0755()
	{
		$dir = sys_get_temp_dir().'/kb_upload_'.bin2hex(random_bytes(6));
		$this->files[] = $dir;

		$this->set_config('upload.create_directories', TRUE);

		$umask = umask(0);

		try
		{
			// The move fails outside a real upload, but the directory is created first
			$this->assertFalse(upload::save($this->upload('x.jpg', 'x'), NULL, $dir));
		}
		finally
		{
			umask($umask);
		}

		$this->assertSame('0755', substr(sprintf('%o', fileperms($dir)), -4));
	}

	public function test_default_directory_is_not_docroot_upload()
	{
		$this->assertStringStartsWith(APPPATH, Kohana::config('upload.directory'));
	}
}
