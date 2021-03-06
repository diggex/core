<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace TestHelpers;

/**
 * Helper to administer Tags
 * 
 * @author Artur Neumann <artur@jankaritech.com>
 *
 */
class TagsHelper {
	/**
	 * tags a file
	 * 
	 * @param string $baseUrl
	 * @param string $taggingUser
	 * @param string $password
	 * @param string $tagName
	 * @param string $fileName
	 * @param string $fileOwner
	 * @param int $davPathVersionToUse (1|2)
	 * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|NULL
	 */
	public static function tag(
		$baseUrl,
		$taggingUser,
		$password,
		$tagName,
		$fileName,
		$fileOwner,
		$davPathVersionToUse = 1
	) {
		$fileID = WebDavHelper::getFileIdForPath(
			$baseUrl, $fileOwner, $password, $fileName
		);
		
		$tag = self::requestTagByDisplayName(
			$baseUrl, $taggingUser, $password, $tagName
		);
		$tagID = (int) $tag ['{http://owncloud.org/ns}id'];
		$path = '/systemtags-relations/files/' . $fileID . '/' . $tagID;
		$response = WebDavHelper::makeDavRequest(
			$baseUrl, $taggingUser, $password, "PUT",
			$path, null, null, null, $davPathVersionToUse, "systemtags"
		);
		return $response;
	}

	/**
	 * get all tags of a user
	 * 
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param string $withGroups
	 * @return array
	 */
	public static function requestTagsForUser(
		$baseUrl,
		$user,
		$password,
		$withGroups = false
	) {
		$baseUrl = WebDavHelper::sanitizeUrl($baseUrl, true);
		$client = WebDavHelper::getSabreClient($baseUrl, $user, $password);
		$properties = [ 
				'{http://owncloud.org/ns}id',
				'{http://owncloud.org/ns}display-name',
				'{http://owncloud.org/ns}user-visible',
				'{http://owncloud.org/ns}user-assignable',
				'{http://owncloud.org/ns}can-assign' 
		];
		if ($withGroups) {
			array_push($properties, '{http://owncloud.org/ns}groups');
		}
		$appPath = '/systemtags/';
		$fullUrl = $baseUrl
			. WebDavHelper::getDavPath($user, 2, "systemtags")
			. $appPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response;
	}

	/**
	 * find a tag by its name
	 * 
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param string $tagDisplayName
	 * @param string $withGroups
	 * @return array
	 */
	public static function requestTagByDisplayName(
		$baseUrl,
		$user,
		$password,
		$tagDisplayName,
		$withGroups = false
	) {
		$tagList = self::requestTagsForUser($baseUrl, $user, $password, $withGroups);
		foreach ($tagList as $path => $tagData) {
			if (!empty($tagData) 
				&& $tagData['{http://owncloud.org/ns}display-name'] === $tagDisplayName
			) {
				return $tagData;
			}
		}
	}

	/**
	 *
	 * @param string $baseUrl see: self::makeDavRequest()
	 * @param string $user
	 * @param string $password
	 * @param string $name
	 * @param bool $userVisible
	 * @param bool $userAssignable
	 * @param string $groups separated by "|"
	 * @param int $davPathVersionToUse (1|2)
	 * @return array ['lastTagId', 'HTTPResponse']
	 * @throws \GuzzleHttp\Exception\ClientException
	 * @link self::makeDavRequest()
	 */
	public static function createTag(
		$baseUrl,
		$user,
		$password,
		$name,
		$userVisible = true,
		$userAssignable = true,
		$groups = null,
		$davPathVersionToUse = 1
	) {
		$tagsPath = '/systemtags/';
		$body = [
				'name' => $name,
				'userVisible' => $userVisible,
				'userAssignable' => $userAssignable,
		];

		if ($groups !== null) {
			$body['groups'] = $groups;
		}
		
		$response = WebDavHelper::makeDavRequest(
			$baseUrl,
			$user,
			$password,
			"POST",
			$tagsPath,
			['Content-Type' => 'application/json',],
			null,
			json_encode($body),
			$davPathVersionToUse,
			"systemtags"
		);
		$responseHeaders = $response->getHeaders();
		$tagUrl = $responseHeaders['Content-Location'][0];
		$lastTagId = substr($tagUrl, strrpos($tagUrl, '/') + 1);
		return ['lastTagId' => $lastTagId, 'HTTPResponse' => $response];
	}

	/**
	 * 
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param int $tagID
	 * @param int $davPathVersionToUse (1|2)
	 * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|NULL
	 * @throws \GuzzleHttp\Exception\ClientException
	 */
	public static function deleteTag(
		$baseUrl,
		$user,
		$password,
		$tagID,
		$davPathVersionToUse = 1
	) {
		$tagsPath = '/systemtags/' . $tagID;
		$response = WebDavHelper::makeDavRequest(
			$baseUrl, $user, $password,
			"DELETE", $tagsPath, [ ], null, null, $davPathVersionToUse, "systemtags"
		);
		return $response;
	}

	/**
	 * 
	 * @param string $type
	 * @throws \Exception
	 * @return boolean[]
	 */
	public static function validateTypeOfTag($type) {
		$userVisible = true;
		$userAssignable = true;
		switch ($type) {
			case 'normal' :
				break;
			case 'not user-assignable' :
				$userAssignable = false;
				break;
			case 'not user-visible' :
				$userVisible = false;
				break;
			default :
				throw new \Exception('Unsupported type');
		}
		
		return array($userVisible, $userAssignable);
	}
}