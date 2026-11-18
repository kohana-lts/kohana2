<?php
/**
 * @group encrypt
 */
class EncryptTest extends Kohana_TestCase {

	const KEY = 'trwQwVXX96TIJoKxyBHB9AJkwAOHixuV1ENZmIWyanI0j1zNgSVvqywy044Agaj';

	protected function encrypt(array $config = array())
	{
		return new Encrypt($config + array('key' => EncryptTest::KEY));
	}

	public function provider_plain_texts()
	{
		return array(
			array(''),
			array('a'),
			array('The quick brown fox jumps over the lazy dog'),
			array(str_repeat('x', 16)),
			array("binary \x00\x01\xff data with trailing nulls\x00\x00"),
		);
	}

	/**
	 * @dataProvider provider_plain_texts
	 */
	public function test_encode_decode($plain)
	{
		$encrypt = $this->encrypt();
		$encoded = $encrypt->encode($plain);

		$this->assertMatchesRegularExpression('#^v2:[A-Za-z0-9+/]+={0,2}$#D', $encoded);
		$this->assertSame($plain, $encrypt->decode($encoded));
		$this->assertNotSame($encoded, $encrypt->encode($plain));
	}

	public function test_decode_fails_with_other_key()
	{
		$this->assertFalse($this->encrypt(array('key' => 'other key'))->decode($this->encrypt()->encode('secret')));
	}

	public function test_decode_rejects_changed_values()
	{
		$encrypt = $this->encrypt();
		$raw = base64_decode(substr($encrypt->encode('The quick brown fox jumps over the lazy dog'), 3));

		for ($i = 0; $i < strlen($raw); $i++)
		{
			$changed = $raw;
			$changed[$i] = chr(ord($changed[$i]) ^ 0x01);

			$this->assertFalse($encrypt->decode('v2:'.base64_encode($changed)), 'Byte '.$i.' was changed');
		}

		$this->assertFalse($encrypt->decode('v2:'.base64_encode(substr($raw, 0, -1))));
		$this->assertFalse($encrypt->decode('v2:'.base64_encode($raw.'x')));
	}

	public function provider_invalid()
	{
		return array(array(NULL), array(array()), array(''), array('v2:'), array('v2:!!!'), array('v2:'.base64_encode('short')), array(base64_encode(str_repeat('a', 64))));
	}

	/**
	 * @dataProvider provider_invalid
	 */
	public function test_decode_invalid_data($value)
	{
		$this->assertFalse($this->encrypt()->decode($value));
	}

	/**
	 * Values from the mcrypt Encrypt of Kohana 2.4 (upstream master), made
	 * with PHP 5.6 and ext/mcrypt (tests/data/encrypt_mcrypt_vectors.json).
	 */
	public function provider_mcrypt_vectors()
	{
		$data = array();

		foreach (json_decode(file_get_contents(__DIR__.'/../data/encrypt_mcrypt_vectors.json'), TRUE) as $row)
		{
			$data[] = array($row[0], base64_decode($row[1]), base64_decode($row[2]), $row[3]);
		}

		return $data;
	}

	/**
	 * @dataProvider provider_mcrypt_vectors
	 */
	public function test_decode_mcrypt_values_in_legacy_mode($mode, $key, $plain, $encoded)
	{
		$encrypt = $this->encrypt(array('key' => $key, 'mode' => $mode, 'cipher' => 'rijndael-128', 'legacy' => TRUE));

		// mcrypt padded with \0 bytes and decode() removed them
		$this->assertSame(rtrim($plain, "\0"), $encrypt->decode($encoded));
	}

	/**
	 * @dataProvider provider_mcrypt_vectors
	 */
	public function test_mcrypt_values_rejected_by_default($mode, $key, $plain, $encoded)
	{
		$this->assertFalse($this->encrypt(array('key' => $key, 'mode' => $mode))->decode($encoded));
	}

	public function test_no_default_key()
	{
		$this->expectException(Kohana_Exception::class);

		new Encrypt;
	}
}
