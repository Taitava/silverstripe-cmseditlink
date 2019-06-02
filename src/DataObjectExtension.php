<?php

namespace Taitava\CMSEditLink;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Class DataObjectExtension
 *
 * @property DataObject $owner
 */
class DataObjectExtension extends Extension
{
	/**
	 * Returns a link to the edit page of this DataObject in the CMS. The returned value is an instance of CMSEditLink
	 * which can be automatically converted to a simple string representing the absolute URL of the link. You can also
	 * modify the link before converting it to a string. CMSEditLinks can be rendered in templates too, and you can also
	 * call the URL() method of a CMSEditLink instance to get the same url.
	 *
	 * @param string $action
	 * @return CMSEditLink
	 */
	public function CMSEditLink($action = 'edit')
	{
		$menthod_name = 'ProvideCMSEditLink';
		if ($this->owner->hasMethod($menthod_name))
		{
			$link = $this->owner->$menthod_name($action);
			if (!$link instanceof CMSEditLink)
			{
				throw new InvalidTypeException('Method ' . $this->owner->ClassName . '::' . $menthod_name . '() should return an instance of ' . CMSEditLink::class . '.');
			}
			return $link;
		}
		return CMSEditLink::LinkFor($this->owner,$action);
	}
	
}