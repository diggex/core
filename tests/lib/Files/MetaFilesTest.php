<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */


namespace Test\Files;


use OC\Files\Meta\MetaFileIdNode;
use OC\Files\Meta\MetaFileVersionNode;
use OC\Files\Meta\MetaRootNode;
use OC\Files\Meta\MetaVersionCollection;
use OC\Files\View;
use OCP\Files\Folder;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Class MetaFilesTest
 *
 * @package Test\Files
 * @group DB
 */
class MetaFilesTest extends TestCase {
	use UserTrait;

	public function testMetaInNodeAPI() {
		// workaround: resetup versions hooks
		\OCA\Files_Versions\Hooks::connectHooks();

		// create user
		$userId = 'meta-data-user';
		$user = $this->createUser($userId);
		$this->loginAsUser($userId);

		// create file
		$fileName = "$userId/files/" . $this->getUniqueID('file') . '.txt';
		$view = new View();
		$view->file_put_contents($fileName, '1234');
		$info = $view->getFileInfo($fileName);

		// work on node api
		/** @var Folder $metaNodeOfFile */
		$metaNodeOfFile = \OC::$server->getRootFolder()->get("meta");
		$this->assertInstanceOf(MetaRootNode::class, $metaNodeOfFile);
		$this->assertEquals([], $metaNodeOfFile->getDirectoryListing());

		$metaNodeOfFile = \OC::$server->getRootFolder()->get("meta/{$info->getId()}");
		$this->assertInstanceOf(MetaFileIdNode::class, $metaNodeOfFile);
		$children = iterator_to_array($metaNodeOfFile->getDirectoryListing());
		$this->assertEquals(1, count($children));
		$this->assertInstanceOf(MetaVersionCollection::class, $children[0]);

		$metaNodeOfFile = \OC::$server->getRootFolder()->get("meta/{$info->getId()}/v");
		$this->assertInstanceOf(MetaVersionCollection::class, $metaNodeOfFile);
		$children = $metaNodeOfFile->getDirectoryListing();
		$this->assertEquals(0, count($children));

		// write again to get another version
		$view->file_put_contents($fileName, '12344567890');
		$children = $metaNodeOfFile->getDirectoryListing();
		$this->assertEquals(1, count($children));
		$this->assertInstanceOf(MetaFileVersionNode::class, $children[0]);

		$verionId = $children[0]->getName();
		$metaNodeOfFile = \OC::$server->getRootFolder()->get("meta/{$info->getId()}/v/$verionId");
		$this->assertInstanceOf(MetaFileVersionNode::class, $metaNodeOfFile);
		$this->assertEquals($verionId, $metaNodeOfFile->getName());
	}
}