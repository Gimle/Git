<?php
declare(strict_types=1);

namespace gimle\git\gitolite
{
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
}
