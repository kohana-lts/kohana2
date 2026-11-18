<?php
/**
 * Profiler output escapes all values and never renders in production (K2-20).
 */
class ProfilerTest extends Kohana_TestCase {

	protected $post;
	protected $session;

	protected function setUp(): void
	{
		parent::setUp();

		$this->post = $_POST;
		$this->session = isset($_SESSION) ? $_SESSION : NULL;
	}

	protected function tearDown(): void
	{
		$_POST = $this->post;
		$_SESSION = $this->session;

		Event::clear('profiler.run');
		(new ReflectionProperty('Profiler', 'profiles'))->setValue(NULL, array());

		parent::tearDown();
	}

	protected function render(array $profiles)
	{
		foreach ($profiles as $profile)
		{
			Event::add('profiler.run', array('Profiler', $profile));
		}

		return Profiler::render(TRUE);
	}

	public function test_post_and_session_values_are_escaped()
	{
		$_POST = array('<b>k' => '<b>x', 'list' => array('<i>' => '<u>'));
		$_SESSION = array('<s>' => '<em>v', 'none' => NULL, 'n' => 5);

		$html = $this->render(array('post', 'session'));

		foreach (array('<b>', '<i>', '<u>', '<s>', '<em>') as $tag)
		{
			$this->assertStringNotContainsString($tag, $html);
		}

		$this->assertStringContainsString('&lt;b&gt;x', $html);
		$this->assertStringContainsString('&lt;em&gt;v', $html);
	}

	public function test_table_escapes_class_and_style()
	{
		$table = new Profiler_Table;
		$table->add_column('c"><b>', 'width: 1px" onclick="x');
		$table->add_row(array(NULL), 'r"><i>', 'color: red" onmouseover="y');

		$html = $table->render();

		$this->assertStringNotContainsString('<b>', $html);
		$this->assertStringNotContainsString('<i>', $html);
		$this->assertStringNotContainsString('" onclick', $html);
		$this->assertStringNotContainsString('" onmouseover', $html);
	}

	public function test_profiler_does_not_render_in_production()
	{
		$result = json_decode($this->run_child('profiler_production.php'), TRUE);

		$this->assertIsArray($result, 'The child process did not print JSON');
		$this->assertSame('<html><body>page</body></html>', $result['output']);
		$this->assertSame('', $result['return']);
	}
}
