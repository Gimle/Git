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
