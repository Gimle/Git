<?php
declare(strict_types=1);
namespace gimle\git;

use \gimle\{Exception, Config};
use function \gimle\exec;

class Gitolite
{
	use \gimle\trick\Singelton;

	/**
	 * The holder for the Git class instance for Gitolite.
	 *
	 * @var ?string
	 */
	private $git = null;

	/**
	 * Holds the found keys so they don't need to be searched from disk again.
	 *
	 * @var ?string
	 */
	private $sshKeyCache = null;

	/**
	 * Holds the found keys information so they don't need to be searched from disk again.
	 *
	 * @var ?string
	 */
	 private $sshKeyCacheAnalyzed = null;

	/**
	 * Create the Gitolite object
	 */
	private function __construct ()
	{
		$this->git = Git::getInstance(Config::get('git.gitolite-admin.path'), Config::get('git.gitolite-admin.user'));
	}

	/**
	 * Retrieve a named public key for the given user.
	 *
	 * @param string $user The username.
	 * @param string $title The title of the key.
	 * @return ?string The public key.
	 */
	public function getSshKey ($user, $title): ?array
	{
		$this->getSshKeys($user);
		if (isset($this->sshKeyCache[$title])) {
			return $this->sshKeyCache[$title];
		}
		return null;
	}

	/**
	 * Retrieve all public keys for the given user.
	 *
	 * @param string $user The username.
	 * @param bool $analyse Provide aditional information about the key.
	 * @return array The public keys.
	 */
	public function getSshKeys ($user, $analyze = false): array
	{
		if ($analyze === false) {
			if ($this->sshKeyCache !== null) {
				return $this->sshKeyCache;
			}
			else {
				$return = [];
				if (file_exists(Config::get('git.gitolite-admin.path') . 'keydir/' . $user)) {
					foreach (new \DirectoryIterator(Config::get('git.gitolite-admin.path') . 'keydir/' . $user) as $fileInfo) {
						$fileName = $fileInfo->getFilename();
						if (substr($fileName, 0, 1) === '.') {
							continue;
						}
						$localName = 'keydir/' . $user . '/' . $fileName . '/' . $user . '.pub';
						$fullName = Config::get('git.gitolite-admin.path') . $localName;
						$return[$fileName] = [
							'file' => $localName,
							'key' => trim(file_get_contents($fullName)),
						];
					}
				}
			}
			$this->sshKeyCache = $return;
			return $return;
		}

		if ($this->sshKeyCacheAnalyzed !== null) {
			return $this->sshKeyCacheAnalyzed;
		}

		$this->sshKeyCacheAnalyzed = $this->getSshKeys($user);

		foreach ($this->sshKeyCacheAnalyzed as $title => $info) {
			try {
				$this->sshKeyCacheAnalyzed[$title]['fingerprint'] = $this->sshKeyInfo(Config::get('git.gitolite-admin.path') . $info['file']);
			}
			catch (Exception $e) {
				$this->sshKeyCacheAnalyzed[$title]['fingerprint'] = null;
			}

			$this->sshKeyCacheAnalyzed[$title]['datetime'] = $this->git->lastCreationDate($info['file']);
		}
		return $this->sshKeyCacheAnalyzed;
	}

	/**
	 * Gather extra information about the ssh public key.
	 *
	 * @throws gimle\Exception If there is an error getting key information.
	 * @param string $fileName The filename of the key, relative to the gitolite repository root.
	 * @return string.
	 */
	public function sshKeyInfo (string $fileName): string
	{
		$result = exec('ssh-keygen -lf ' . escapeshellarg($fileName));
		if ($result['return'] !== 0) {
			throw new Exception($result['sterr'][0], $result['return']);
		}
		return $result['stout'][0];
	}

	/**
	 * Add a public key to the given user.
	 *
	 * @throws gimle\Exception If the key can not be added.
	 * @param string $key The public key.
	 * @param string $user The username.
	 * @param string $title The title of the key.
	 * @return void.
	 */
	public function addSshKey ($key, $user, $title): void
	{
		$this->sshKeyCache = null;
		$key = trim($key);
		$dir = Config::get('git.gitolite-admin.path') . 'keydir/' . $user . '/' . $title . '/';
		$e = new Exception('');
		$e->set('dir', $dir);

		$usedKeys = $this->getSshKeys($user);
		$usedTitles = array_keys($usedKeys);

		if (in_array($title, $usedTitles)) {
			$e->setMessage('Title in use.');
			$e->set('title', $dir);
			throw $e;
		}

		foreach ($usedKeys as $usedKey) {
			if ($usedKey['key'] === $key) {
				$e->setMessage('Key in use.');
				$e->set('key', $key);
				throw $e;
			}
		}

		$isClean = $this->git->isClean();
		if (!$isClean) {
			$e->setMessage('Gitolite directory is not clean.');
			$e->set('result', $this->git->status());
			throw $e;
		}

		$mkdir = $this->git->exec('mkdir -p ' . $dir);
		if ($mkdir['result']['return'] !== 0) {
			$e->setMessage('Error while creating directory.');
			$e->set('result', $mkdir);
			throw $e;
		}
		$createFile = $this->git->exec('echo ' . escapeshellarg($key) . ' > ' . $dir . $user . '.pub');
		if ($createFile['result']['return'] !== 0) {
			$e->setMessage('Error while creating file.');
			$e->set('result', $createFile);
			throw $e;
		}
		$add = $this->git->add($dir . $user . '.pub');
		if ($add['result']['return'] !== 0) {
			$e->setMessage('Error while adding the key to git.');
			$e->set('result', $add);
			throw $e;
		}
		$commit = $this->git->commit('Added new key: "' . $title . '" for user: "' . $user . '"');
		if ($commit['result']['return'] !== 0) {
			$e->setMessage('Error while comitting to git.');
			$e->set('result', $commit);
			throw $e;
		}
		$push = $this->git->push();
		if ($push['result']['return'] !== 0) {
			$e->setMessage('Error while pushing to git.');
			$e->set('result', $push);
			throw $e;
		}
	}

	/**
	 * Delete a public key from the given user.
	 *
	 * @throws gimle\Exception If the key can not be deleted.
	 * @param string $user The username.
	 * @param string $title The title of the key.
	 * @return void.
	 */
	public function deleteSshKey ($user, $title): void
	{
		$e = new Exception('');
		$e->set('user', $user);
		$e->set('title', $title);

		$this->getSshKeys($user);
		if (isset($this->sshKeyCache[$title])) {
			$remove = $this->git->rm($this->sshKeyCache[$title]['file']);
			if ($remove['result']['return'] !== 0) {
				$e->setMessage('Error while removing the key from git.');
				$e->set('result', $add);
				throw $e;
			}
			$commit = $this->git->commit('Removed key: "' . $title . '" for user: "' . $user . '"');
			if ($commit['result']['return'] !== 0) {
				$e->setMessage('Error while comitting to git.');
				$e->set('result', $commit);
				throw $e;
			}
			$push = $this->git->push();
			if ($push['result']['return'] !== 0) {
				$e->setMessage('Error while pushing to git.');
				$e->set('result', $push);
				throw $e;
			}
			return;
		}
		$e->setMessage('Key not found.');
		$e->set('result', $push);
		throw $e;
	}

	/**
	 * Return the gitolite config file as a SimpleXmlElement.
	 *
	 * @throws gimle\Exception If the config can not be parsed.
	 * @return SimpleXmlElement
	 */
	public function configToXml ()
	{
		$lines = file(Config::get('git.gitolite-admin.path') . 'conf/gitolite.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return null;
		}

		$splitComment = function ($text) {
			if (strpos($text, '#') === false) {
				return ['text' => trim($text), 'comment' => null];
			}
			$result = explode('#', $text, 2);
			return ['text' => trim($result[0]), 'comment' => trim($result[1])];
		};

		$sxml = new SimpleXmlElement('<gitolite/>');
		$inRepo = false;
		$cacheComments = [];
		foreach ($lines as $linenum => $line) {
			$linestr = trim(preg_replace('/\s+/s', ' ', $line));
			if ($linestr === '') {
				continue;
			}
			if (substr($linestr, 0, 1) === '#') {
				if ($inRepo === false) {
					$comment = $sxml->addChild('comment');
					$comment[0] = trim(substr($line, 1));
				}
				else {
					$cacheComments[] = trim(substr($line, 1));
				}
				continue;
			}

			if (substr($linestr, 0, 1) === '@') {
				$linestr = explode('=', $linestr);
				if (count($linestr) !== 2) {
					throw new Exception('Expected key value definition pair.');
				}
				$group = $sxml->addChild('group');
				$group['name'] = trim($linestr[0]);
				$linestr[1] = $splitComment($linestr[1]);
				if ($linestr[1]['comment'] !== null) {
					$group['comment'] = $linestr[1]['comment'];
				}
				$names = explode(' ', $linestr[1]['text']);
				foreach ($names as $name) {
					$groupName = $group->addChild('name');
					$groupName[0] = $name;
				}
				continue;
			}

			if (substr($linestr, 0, 5) === 'repo ') {
				if (!empty($cacheComments)) {
					foreach ($cacheComments as $cacheComment) {
						$comment = $sxml->addChild('comment');
						$comment[0] = $cacheComment;
					}
					$cacheComments = [];
				}
				$inRepo = true;
				$repo = $sxml->addChild('repo');
				$linestr = $splitComment(substr($linestr, 5));
				if ($linestr['comment'] !== null) {
					$repo['comment'] = $linestr['comment'];
				}
				$names = explode(' ', $linestr['text']);
				foreach ($names as $name) {
					$repoName = $repo->addChild('name');
					$repoName[0] = $name;
				}
				continue;
			}

			if ($inRepo === false) {
				throw new Exception('Expected to be in a repo.');
			}

			$linestr = explode('=', $linestr);
			if (count($linestr) !== 2) {
				throw new Exception('Expected key value access pair.');
			}

			if (!empty($cacheComments)) {
				foreach ($cacheComments as $cacheComment) {
					$comment = $repo->addChild('comment');
					$comment[0] = $cacheComment;
				}
				$cacheComments = [];
			}

			if (substr($linestr[0], 0, 6) === 'option') {
				$option = $repo->addChild('option');
				$option['name'] = trim(substr($linestr[0], 7));
				$linestr[1] = $splitComment($linestr[1]);
				$option[0] = $linestr[1]['text'];
				if ($linestr[1]['comment'] !== null) {
					$option['comment'] = $linestr[1]['comment'];
				}
			}
			elseif (substr($linestr[0], 0, 6) === 'config') {
				$config = $repo->addChild('config');
				$config['name'] = trim(substr($linestr[0], 7));
				$linestr[1] = $splitComment($linestr[1]);
				$config[0] = $linestr[1]['text'];
				if ($linestr[1]['comment'] !== null) {
					$config['comment'] = $linestr[1]['comment'];
				}
			}
			else {
				$user = $repo->addChild('access');
				$linestr[1] = $splitComment($linestr[1]);
				$user['name'] = $linestr[1]['text'];

				if (strpos(trim($linestr[0]), ' ') === false) {
					$user['perm'] = trim($linestr[0]);
				}
				else {
					$splitted = explode(' ', trim($linestr[0]));
					$user['perm'] = trim($splitted[0]);
					$user['ref'] = trim($splitted[1]);
				}

				if ($linestr[1]['comment'] !== null) {
					$user['comment'] = $linestr[1]['comment'];
				}
			}
		}
		return $sxml;
	}

	/**
	 * Convert a SimpleXmlElemt to gitolite config.
	 *
	 * @param SimpleXmlElement $config The config xml.
	 * @return string The gitolite config.
	 */
	public static function configFromXml (\SimpleXmlElement $config): string
	{
		$return = '';

		foreach ($config->xpath('/gitolite/*') as $element) {
			$tagName = (string) $element->getName();

			if ($tagName === 'group') {
				$return .= (string) $element['name'] . ' =';
				foreach ($element->xpath('./name') as $sub) {
					$return .= ' ' . (string) $sub;
				}
				if ((bool) $element['comment']) {
					$return .= ' # ' . (string) $element['comment'];
				}
				$return .= "\n";
			}
			elseif ($tagName === 'repo') {
				$return .= 'repo';
				foreach ($element->xpath('./name') as $sub) {
					$return .= ' ' . (string) $sub;
				}
				if ((bool) $element['comment']) {
					$return .= ' # ' . (string) $element['comment'];
				}
				$return .= "\n";
				foreach ($element->xpath('./*[not(self::name)]') as $sub) {
					$subName = (string) $sub->getName();
					if ($subName === 'access') {
						$return .= '	' . (string) $sub['perm'];
						if ((bool) $sub['ref']) {
							$return .= ' ' . (string) $sub['ref'];
						}
						elseif (strlen((string) $sub['perm']) < 4) {
							$return .= str_repeat(' ', (4 - strlen((string) $sub['perm'])));
						}

						$return .= ' = ' . (string) $sub['name'];
						if ((bool) $sub['comment']) {
							$return .= ' # ' . (string) $sub['comment'];
						}
						$return .= "\n";
					}
					elseif ($subName === 'comment') {
						$return .= '	# ' . (string) $sub . "\n";
					}
					elseif ($subName === 'option') {
						$return .= '	option ' . (string) $sub['name'] . ' = ' . (string) $sub;
						if ((bool) $sub['comment']) {
							$return .= ' # ' . (string) $sub['comment'];
						}
						$return .= "\n";
					}
					elseif ($subName === 'config') {
						$return .= '	config ' . (string) $sub['name'] . ' = ' . (string) $sub;
						if ((bool) $sub['comment']) {
							$return .= ' # ' . (string) $sub['comment'];
						}
						$return .= "\n";
					}
					else {
						$e = new Exception('Error converting config (1).');
						$e->set('subName', $subName);
						throw $e;
					}
				}
			}
			elseif ($tagName === 'comment') {
				$return .= '# ' . (string) $element;
			}
			else {
				throw new Exception('Error converting config (2).');
			}

			$return .= "\n";
		}
		return $return;
	}
}
