<?php
declare(strict_types=1);
namespace gimle\git;

trait Commands
{
	/**
	 * Git add
	 *
	 * @param string $add What to add.
	 * @return array
	 */
	public function add ($add): array
	{
		$exec = 'add ' . escapeshellarg($add);
		return $this->gitexec($exec);
	}

	/**
	 * Git rm
	 *
	 * @param string $add What to remove.
	 * @param bool $recursive remove recursively?
	 * @return array
	 */
	 public function rm ($remove, $recursive = false): array
	{
		if ($recursive) {
			$exec = 'rm -r ' . escapeshellarg($remove);
		}
		else {
			$exec = 'rm ' . escapeshellarg($remove);
		}
		return $this->gitexec($exec);
	}

	/**
	 * Git commit
	 *
	 * @param string $message Commit message.
	 * @return array
	 */
	public function commit ($message)
	{
		$exec = 'commit -m ' . escapeshellarg($message);
		return $this->gitexec($exec);
	}

	/**
	 * Git push
	 *
	 * @return array
	 */
	public function push ()
	{
		$exec = 'push';
		return $this->gitexec($exec);
	}

	/**
	 * Git status
	 *
	 * @return array
	 */
	public function status ()
	{
		$exec = 'status';
		return $this->gitexec($exec);
	}

	public function log ()
	{
		$exec = 'log';
		return $this->gitexec($exec);
	}

	/**
	 * Return the last creation date for a file.
	 *
	 * @return ?string
	 */
	public function lastCreationDate (string $file): ?string
	{
		$exec = 'log --diff-filter=A --follow --format=%aD -1 -- ' . $file;
		$result = $this->gitexec($exec);
		if (($result['result']['return'] !== 0) || (!isset($result['result']['stout'][0])) || (isset($result['result']['stout'][1]))) {
			return null;
		}
		return $result['result']['stout'][0];
	}

	/**
	 * Checks if the current working directory is clean or not.
	 * Note: This method will not check if there is unpushed changes.
	 *
	 * @return bool
	 */
	public function isClean ()
	{
		$exec = 'status --porcelain';
		$result = $this->gitexec($exec);
		if (($result['result']['return'] !== 0) || (!empty($result['result']['stout']))) {
			return false;
		}
		return true;
	}
}
