<?php
declare(strict_types=1);
namespace gimle\git;

class SimpleXmlElement extends \gimle\xml\SimpleXmlElement
{
	/**
	 * Retrieve a list of groups.
	 *
	 * @param string $containing The repo or username.
	 * @return array An array with group names.
	 */
	public function getGroups (string $containing): array
	{
		$return = [];

		$q = '/gitolite/group/name';
		$groups = $this->xpath($q);
		foreach ($groups as $group) {
			$name = (string) $group;
			if (substr($name, 0, 1) === '@') {
				if (in_array($name, $return)) {
					$return[] = (string) $group->getParent()['name'];
				}
			}
			elseif (gitolite\matches($containing, $name)) {
				$return[] = (string) $group->getParent()['name'];
			}
		}

		return $return;
	}

	/**
	 * Check if the user has access to gitolite admin.
	 *
	 * @param string $user The username.
	 * @return bool
	 */
	public function isAdmin (string $user): bool
	{
		$accesses = $this->getUserAccess('gitolite-admin', $user);
		foreach ($accesses as $access) {
			if (($access['perm'] === 'RW+') && ($access['ref'] === null)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieve a list of repos the user has access to.
	 *
	 * @param string $user The username.
	 * @return array An array with repo name and permission.
	 */
	public function getUserRepos (string $user): array
	{
		$validAccessNames = $this->getGroups($user);
		$validAccessNames[] = $user;
		$return = [];
		foreach ($this->xpath('./repo') as $repo) {
			$hasAccess = false;
			foreach ($validAccessNames as $name) {
				$test = current($repo->xpath('./access[@name=' . $repo->real_escape_string($name) . ']'));
				if ($test !== false) {
					$name = (string) $test->getParent()->name;

					if (substr($name, 0, 1) === '@') {
						$group = current($this->xpath('./group[@name=' . $this->real_escape_string($name) . ']'));
						if ($group !== false) {
							foreach ($group->xpath('./name') as $access) {
								$return[] = [
									'perm' => (string) $test['perm'],
									'name' => (string) $access,
								];
							}
						}
					}
					else {
						$return[] = [
							'perm' => (string) $test['perm'],
							'name' => $name,
						];
					}
				}
			}
		}
		return $return;
	}

	public function getUserAccess (string $repo, string $user, bool $includeGroupChecks = true): array
	{
		$return = [];

		$repoObjs = $this->xpath('/gitolite/repo/name');
		if ((bool) $repoObjs) {
			foreach ($repoObjs as $repoObj) {
				if (gitolite\matches($repo, (string) $repoObj)) {
					$accesses = $repoObj->xpath('./../access[@name=' . $this->real_escape_string($user) . ']');
					if ((bool) $accesses) {
						foreach ($accesses as $access) {
							$return[] = ['perm' => (string) $access['perm'], 'ref' => ((bool) $access['ref'] ? (string) $access['ref'] : null)];
						}
					}
				}
			}
		}

		if ($includeGroupChecks === true) {
			$repoGroups = $this->getGroups($repo);
			$userGroups = $this->getGroups($user);
			$repoGroups[] = '@all';
			$userGroups[] = '@all';

			foreach ($repoGroups as $repoGroup) {
				$return = array_merge($return, $this->getUserAccess($repoGroup, $user, false));
			}
			foreach ($userGroups as $userGroup) {
				$return = array_merge($return, $this->getUserAccess($repo, $userGroup, false));
			}
			foreach ($repoGroups as $repoGroup) {
				foreach ($userGroups as $userGroup) {
					$return = array_merge($return, $this->getUserAccess($repoGroup, $userGroup, false));
				}
			}
		}
		return $return;
	}

}
