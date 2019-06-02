<?php

namespace Taitava\CMSEditLink;

use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class CMSLinkScaffolderTest extends SapphireTest
{
	/**
	 * @deprecated TODO: This test uses a Member singleton to test link creation. It fails because Members are not managed via any ModelAdmin class. Fix this by either using something else than Members as test subjects, or implement a way to replace ModelAdmins with LeftAndMain instances.
	 *
	 * @throws \Exception
	 */
	public function testScaffold()
	{
		$member = Member::singleton();
		$group = Group::singleton();
		$link = CMSEditLink::LinkFor($member)->via($group, 'Members');
		$correct_url = Director::absoluteURL('security/EditForm/field/Groups/item/2/ItemEditForm/field/Members/item/2/edit');
		$actual_url = $link->URL();
		$this->assertEquals($correct_url, $actual_url);
	}
}