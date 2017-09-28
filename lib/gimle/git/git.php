<?php
declare(strict_types=1);
namespace gimle\git;

use \gimle\Exception;
use function \gimle\exec;

class Git
{
	use \gimle\trick\Multiton;
	use \gimle\git\Commands;

	/**
	 * The base path of the git repository.
	 *
	 * @var string
	 */
	private $base = '';

	/**
	 * The username to tun commands under. Escaped for shell execute.
	 *
	 * @var ?string
	 */
	private $user = null;

	/**
	 * The username to tun commands under. Not escaped.
	 *
	 * @var ?string
	 */
	 private $userUnquoted = null;

	/**
	 * Return an instance of the git object.
	 *
	 * @param $base string The base path of the git repository.
	 * @param $username ?string The username to tun commands under.
	 * @return object
	 */
	public static function getInstance (string $base, ?string $username = null): self
	{
		if (!isset(self::$instances[(string) $username . '@' . $base])) {
			$me = get_called_class();

			self::$instances[(string) $username . '@' . $base] = new $me($base, $username);
		}

		return self::$instances[(string) $username . '@' . $base];
	}

	/**
	 * A private cunstructor for the getInstance metod to use.
	 *
	 * @param $base string The base path of the git repository.
	 * @param $username ?string The username to tun commands under.
	 */
	private function __construct (string $base, ?string $username)
	{
		$this->base = escapeshellarg($base);
		if ($username !== null) {
			$this->user = escapeshellarg($username);
			$this->userUnquoted = $username;
		}
	}

	/**
	 * Executes a git command in the current working directory as the current user.
	 *
	 * @param string $cmd Command to execute.
	 * @return array
	 */
	public function gitexec ($cmd): array
	{
		$exec = "git -C {$this->base} {$cmd}";
		return $this->exec($exec);
	}

	public function getMyKey (): string
	{
		$exec = 'cat ' . posix_getpwnam($this->userUnquoted)['dir'] . '/.ssh/id_rsa.pub';
		$result = $this->exec($exec);
		if (($result['result']['return'] !== 0) || (!isset($result['result']['stout'][0])) || (isset($result['result']['stout'][1]))) {
			$e = new Exception('');
			$e->set('result', $result);
			throw $e;
		}
		return $result['result']['stout'][0];
	}

	/**
	 * Executes any command as the current user.
	 *
	 * @param string $cmd Command to execute.
	 * @return array
	 */
	public function exec ($cmd): array
	{
		$randString = self::generateRandomString();
		while (strpos($cmd, $randString) !== false) {
			$randString = self::generateRandomString();
		}
		$exec = '';
		if ($this->user !== null) {
			$exec = "sudo -u {$this->user} ";
		}
		$exec .= "bash <<{$randString}\n{$cmd}\n{$randString}\n";
		$result = exec($exec);
		return ['cmd' => $cmd, 'exec' => $exec, 'result' => $result];
	}

	/**
	 * Generates a string of random uppercase characters.
	 *
	 * @return string
	 */
	public static function generateRandomString (): string
	{
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < 10; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}
