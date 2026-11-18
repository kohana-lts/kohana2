<?php
/**
 * text::random() must use a CSPRNG (K2-22).
 */
class TextRandomTest extends Kohana_TestCase {

	protected function tearDown(): void
	{
		mt_srand();

		parent::tearDown();
	}

	public function test_random_does_not_follow_mt_srand()
	{
		mt_srand(42);
		$a = text::random('alnum', 32);

		mt_srand(42);
		$b = text::random('alnum', 32);

		$this->assertNotSame($a, $b);
	}

	public function provider_types()
	{
		return array(
			array('alnum', '/^[0-9a-zA-Z]{16}$/'),
			array('alpha', '/^[a-zA-Z]{16}$/'),
			array('hexdec', '/^[0-9a-f]{16}$/'),
			array('numeric', '/^[0-9]{16}$/'),
			array('nozero', '/^[1-9]{16}$/'),
			array('distinct', '/^[2345679ACDEFHJKLMNPRSTUVWXYZ]{16}$/'),
			array('xy', '/^[xy]{16}$/'),
			array('čž', '/^(?:č|ž){16}$/u'),
		);
	}

	/**
	 * @dataProvider provider_types
	 */
	public function test_random_types($type, $pattern)
	{
		$this->assertMatchesRegularExpression($pattern, text::random($type, 16));
	}

	public function test_alnum_has_letter_and_digit()
	{
		for ($i = 0; $i < 200; $i++)
		{
			$str = text::random('alnum', 2);
			$this->assertMatchesRegularExpression('/[a-zA-Z]/', $str);
			$this->assertMatchesRegularExpression('/[0-9]/', $str);
		}
	}
}
