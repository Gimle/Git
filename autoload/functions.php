<?php
declare(strict_types=1);

namespace gimle\git\gitolite
{
	use \gimle\git\SimpleXmlElement;

	const FILTER_VALIDATE_GITOLITE_FILENAME = 'gitolite_v';

	function isRegex (string $string): bool
	{
		return (bool) strpbrk($string, '^[]*?()$');
	}

	function filter_var ($variable, $filter = FILTER_VALIDATE_GITOLITE_FILENAME, $options = null)
	{
		if ($filter === FILTER_VALIDATE_GITOLITE_FILENAME) {
			return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9\.\-\_]*$/', $variable);
		}
		return \gimle\filter_var($variable, $filter, $options);
	}

	function matches ($string, $clause)
	{
		if ($string === $clause) {
			return true;
		}
		if (isRegex($clause)) {
			if (preg_match('/' . str_replace('/', '\\/', $clause) . '/', $string)) {
				return true;
			}
		}
		return false;
	}

	function parse_config_file ($filename)
	{
		$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
}
