<?php
declare(strict_types=1);
namespace gimle\git;

use function \gimle\d;

class SimpleXmlElement extends \gimle\xml\SimpleXmlElement
{
	public function getGroups (string $containing): array
	{
		$return = [];

		$q = '/gitolite/group/name';
		$groups = $this->xpath($q);
		if (!empty($groups)) {
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
		}

		return $return;
	}

	public function isAdmin ($user)
	{
		$accesses = $this->getUserAccess('gitolite-admin', $user);
		foreach ($accesses as $access) {
			if (($access['perm'] === 'RW+') && ($access['ref'] === null)) {
				return true;
			}
		}
		return false;
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

		if ($includeGroupChecks) {
			$repoGroups = $this->getGroups($repo);
			$userGroups = $this->getGroups($user);
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
