<?php

namespace Taitava\CMSEditLink;

use Exception;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use UnexpectedValueException;

/**
 * Class CMSLinkScaffolder
 *
 * This is a helper class for Taitava\CMSEditLink\CMSEditLink for generating chained CMS links. It' not meant to be used externally.
 *
 * @internal
 */
class CMSLinkScaffolder
{
	/** @var CMSEditLink */
	private $root_link;
	
	public function __construct(CMSEditLink $root_link)
	{
		$this->root_link = $root_link;
	}
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function scaffold()
	{
		$model_admin = static::PickModelAdmin($this->root_link->getDataObject(), $managed_data_object_class);
		$managed_data_object_class = str_replace('\\', '-', $managed_data_object_class);
		$link_stack = $this->root_link->Children(true);
		$url = null;
		$last_action = '';
		foreach ($link_stack as $link)
		{
			$data_object = $link->getDataObject();
			if (!$url)
			{
				// Root link
				$url = Controller::join_links(
					$model_admin->Link($managed_data_object_class),
					'EditForm',
					'field',
					$managed_data_object_class,
					'item',
					$data_object->ID
					// Note that we will not define an action here, it will be done below.
				);
			}
			else
			{
				// Child link
				$url = Controller::join_links(
					$url,
					'ItemEditForm',
					'field',
					$link->getRelationName(),
					'item',
					$data_object->ID
					// Note that we will not define an action here, it will be done below.
				);
			}
			$last_action = $link->action();
		}
		return Controller::join_links($url, $last_action);
	}
	
	/**
	 * Searches all ModelAdmin classes and tries to find a one which can manage the passed $data_object. If the DataObject
	 * has defined a method named `getModelAdminForCMSEditLink()`, then we will use exactly the ModelAdmin returned by
	 * that method (but we still do strict checking for the returned ModelAdmin to ensure that it really can manage
	 * the passed $data_object). `getModelAdminForCMSEditLink()` may return either a string representing a ModelAdmin
	 * class name, or an instance of a ModelAdmin class.
	 *
	 * If the method cannot find a suitable ModelAdmin class, it will throw either an Exception or UnexpectedValueException.
	 *
	 * @param DataObject $data_object
	 * @param string $managed_data_object_class This variable will be set to contain the exact DataObject class name which matched with the managed model names of the returned ModelAdmin. An example: DataObjects can be extended from parent DataObjects, which means that a ModelAdmin which is able to manage SiteTree objects, is also able to manage Page objects. If $data_object is an instance of Page, and the ModelAdmin is defined to manage SiteTrees, then the generated link URL must contain 'SiteTree' as a class name instead of 'Page'. This is why return a DataObject class name too.
	 * @return ModelAdmin
	 * @throws Exception
	 */
	private static function PickModelAdmin(DataObject $data_object, &$managed_data_object_class)
	{
		// Check if the DataObject class has a method that can provide a ModelAdmin
		$method_name = 'getModelAdminForCMSEditLink';
		$getter_found = false;
		if ($data_object->hasMethod($method_name))
		{
			// The getter method exists
			$getter_found = true;
			$model_admin = $data_object->$method_name();
			
			// Ensure that the method's return value is what it should be
			if ($model_admin instanceof ModelAdmin)
			{
				$model_admin_candidates = [$model_admin];
			}
			elseif (is_string($model_admin) && is_subclass_of($model_admin, ModelAdmin::class, true))
			{
				$model_admin_candidates = [Injector::inst()->get($model_admin)];
			}
			else
			{
				throw new UnexpectedValueException($data_object->ClassName.'::'.$method_name.'() should return either a ModelAdmin instance or a string of a ModelAdmin class name.');
			}
		}
		else
		{
			// No ModelAdmin getter method is defined
			// Load a list of all ModelAdmins to be used as candidates
			$model_admin_candidates = ClassInfo::subclassesFor(ModelAdmin::class);
			array_shift($model_admin_candidates); // Remove "SilverStripe\Admin\ModelAdmin" from the list
		}
		
		// Iterate the candidates and try to find a one which can manage the given DataObject
		$data_object_classes = array_reverse(ClassInfo::ancestry($data_object)); // Reverse the array to make the root class name to be the last one. This way we will prefer
		foreach ($data_object_classes as $data_object_class)
		{
			foreach ($model_admin_candidates as $model_admin_candidate)
			{
				/** @var ModelAdmin $model_admin */
				if (is_object($model_admin_candidate))
				{
					$model_admin = $model_admin_candidate;
				}
				else
				{
					$model_admin = Injector::inst()->get($model_admin_candidate);
				}
				
				// Check if this ModelAdmin can manage the class
				$managed_classes = array_keys($model_admin->getManagedModels());
				if (in_array($data_object_class, $managed_classes))
				{
					$managed_data_object_class = $data_object_class; // Report the caller which class name matched the chosen ModelAdmin
					return $model_admin;
				}
			}
		}
		
		// The candidate(s) were not able to manage the DataObject
		if ($getter_found)
		{
			throw new UnexpectedValueException($data_object->ClassName.'::'.$method_name.'() returned a wrong ModelAdmin instance/classname. The ModelAdmin should be able to manage instances of "'.$data_object->ClassName.'".');
		
		}
		else
		{
			throw new Exception(__METHOD__ . ': Could not find a ModelAdmin class for a DataObject class "' . $data_object->ClassName . '"');
		}
	}
}