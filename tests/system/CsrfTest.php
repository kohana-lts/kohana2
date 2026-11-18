<?php
/**
 * csrf helper (K2-19). The tests use an array session: Session needs
 * session_start() before any output.
 */
class CsrfTest extends Kohana_TestCase {

	protected $session;

	protected function setUp(): void
	{
		parent::setUp();

		$this->session = new Kb_Array_Session;
		csrf::$session = $this->session;
	}

	protected function tearDown(): void
	{
		csrf::$session = NULL;

		parent::tearDown();
	}

	public function test_token_is_stable_and_random()
	{
		$token = csrf::token();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
		$this->assertSame($token, csrf::token());
		$this->assertSame($token, $this->session->get(csrf::$session_key));
	}

	public function test_valid_accepts_the_session_token()
	{
		$this->assertTrue(csrf::valid(csrf::token()));
	}

	public function provider_bad_tokens()
	{
		return array(array(NULL), array(''), array(FALSE), array(0), array(array()), array('abc'), array(str_repeat('0', 64)));
	}

	/**
	 * @dataProvider provider_bad_tokens
	 */
	public function test_valid_rejects_bad_tokens($token)
	{
		csrf::token();

		$this->assertFalse(csrf::valid($token));
	}

	public function test_valid_without_session_token()
	{
		$this->assertFalse(csrf::valid(''));
		$this->assertFalse(csrf::valid(NULL));
		$this->assertFalse($this->session->get(csrf::$session_key));
	}

	public function test_new_token_replaces_old_one()
	{
		$old = csrf::token();
		$new = csrf::token(TRUE);

		$this->assertNotSame($old, $new);
		$this->assertFalse(csrf::valid($old));
		$this->assertTrue(csrf::valid($new));
	}

	public function test_validation_rule()
	{
		$post = Validation::factory(array('csrf_token' => 'forged'))
			->add_rules('csrf_token', 'required', 'csrf::valid');
		$this->assertFalse($post->validate());

		$post = Validation::factory(array())
			->add_rules('csrf_token', 'required', 'csrf::valid');
		$this->assertFalse($post->validate());

		$post = Validation::factory(array('csrf_token' => csrf::token()))
			->add_rules('csrf_token', 'required', 'csrf::valid');
		$this->assertTrue($post->validate());
	}

	public function test_field()
	{
		$this->assertSame('<input type="hidden" name="csrf_token" value="'.csrf::token().'" />', csrf::field());
	}
}

class Kb_Array_Session {

	public $data = array();

	public function get($key, $default = FALSE)
	{
		return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
	}

	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}
}
