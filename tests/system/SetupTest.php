<?php
/**
 * Smoke tests: the framework boots and the core libraries and helpers run.
 */
class SetupTest extends Kohana_TestCase {

	public function test_setup_ran()
	{
		$this->assertTrue(class_exists('Kohana', FALSE));
		$this->assertSame('UTC', date_default_timezone_get() ?: 'UTC');
		$this->assertIsArray(Kohana::include_paths());
	}

	public function test_autoload_libraries_and_helpers()
	{
		foreach (array('Input', 'URI', 'Router', 'View', 'Validation', 'Session', 'Database', 'ORM', 'Encrypt', 'Image', 'Cache', 'I18n', 'Profiler') as $class)
		{
			$this->assertTrue(class_exists($class), $class);
		}

		foreach (array('arr', 'cookie', 'date', 'download', 'expires', 'feed', 'file', 'form', 'format', 'html', 'inflector', 'num', 'remote', 'request', 'security', 'text', 'upload', 'url', 'utf8', 'valid') as $helper)
		{
			$this->assertTrue(class_exists($helper), $helper);
		}
	}

	public function test_view_renders()
	{
		$view = new View('kohana/error_disabled');
		$view->set('error', 'x');

		$this->assertIsString($view->render());
	}

	public function test_helpers()
	{
		$this->assertSame('<a href="http://example.com/">x</a>', html::anchor('http://example.com/', 'x'));
		$this->assertTrue(valid::email('user@example.com'));
		$this->assertFalse(valid::email('not an email'));
		$this->assertSame('hello_world', url::title('Hello World', '_'));
		$this->assertSame(5, utf8::strlen('ščćžđ'));
		$this->assertSame('Ščćžđ', utf8::ucfirst('ščćžđ'));
		$this->assertSame('1.00 kB', text::bytes(1024));
		$this->assertIsString(text::random('alnum', 10));
		$this->assertSame('apples', inflector::plural('apple'));
		$this->assertSame('a&amp;b', html::chars('a&b'));
		$this->assertStringContainsString('<input', form::input('name', 'value'));
		$this->assertSame(array('a' => 1), arr::extract(array('a' => 1, 'b' => 2), 'a'));
	}

	public function test_validation()
	{
		$post = new Validation(array('name' => '', 'email' => 'bad'));
		$post->add_rules('name', 'required');
		$post->add_rules('email', 'required', 'valid::email');

		$this->assertFalse($post->validate());
		$this->assertArrayHasKey('name', $post->errors());
		$this->assertArrayHasKey('email', $post->errors());
	}

	public function test_input()
	{
		$input = Input::instance();

		$this->assertSame('<b>x</b>', $input->xss_clean('<b>x</b>'));
		$this->assertStringNotContainsString('<script', $input->xss_clean('<script>alert(1)</script>'));
	}

	public function test_encrypt_round_trip()
	{
		$encrypt = new Encrypt(array('key' => 'a test key that is long enough', 'mode' => MCRYPT_MODE_NOFB, 'cipher' => MCRYPT_RIJNDAEL_128));

		$this->assertSame('secret', $encrypt->decode($encrypt->encode('secret')));
	}
}
