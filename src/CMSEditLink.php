<?php

namespace Taitava\CMSEditLink;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ViewableData;

/**
 * Class CMSEditLink
 *
 * CMSEditLinks are instances that can be used to easily generate links to DataObjects' editing pages in the CMS.
 *
 * The links can be chained infinitely to create "breadcrumbs" that lead to the actual editing page. For example, if
 * you have a `Book` object and you want ot provide a CMS editing link which also denotes a `Library` object, you can
 * create the link like this:
 *
 * ```php
 * $book = Book::get()->byID(1);
 * $book->CMSEditLink()->via($book->Library(), 'Books');
 * ```
 * Or create the same link in another way:
 * ```php
 * $library = Library::get()->byID(1);
 * $library->CMSEditLink()->hereon($library->Books()->first(), 'Books');
 * ```
 *
 * Too complex? No problem, you can create just simple links like this one:
 * ```php
 * $book = Book::get()->byID(1);
 * $book->CMSEditLink(); // No breadcrumbs for the Library which holds the book.
 * ```
 *
 * A CMSEditLink instance can be automatically converted to a simple string representing the absolute URL of the link. You can also
 * modify the link before converting it to a string. CMSEditLinks can be rendered in templates too, and you can also
 * call the URL() method of a CMSEditLink instance to get the same url.
 *
 * More details are in README.md.
 */
class CMSEditLink extends ViewableData
{
	/**
	 * @var DataObject
	 */
	private $data_object;
	
	private $action;
	
	/**
	 * @var CMSEditLink
	 */
	private $parent_link;
	
	/**
	 * @var CMSEditLink
	 */
	private $child_link;
	
	private $relation_name;
	
	public function __construct(DataObject $data_object, $action = 'edit')
	{
		parent::__construct();
		
		$this->data_object = $data_object;
		$this->action = $action;
	}
	
	/**
	 * This is the opposite of via(). See the documentation of that method for more information.
	 *
	 * TODO: Give this method a better name. Something descriptive but short and nice like the via() method has.
	 *
	 * @param DataObject|CMSEditLink $data_object_or_link
	 * @param string $relation_name A has_one/has_many/many_many/belongs_to/belongs_many_many relation name. Note that this should be the name of the relation that the current DataObject uses when referring to the given $data_object_or_link. See the example in via().
	 * @return CMSEditLink
	 * @see via()
	 */
	public function hereon($data_object_or_link, $relation_name)
	{
		$link = static::resolve_link($data_object_or_link);
		$link->parent_link = $this;
		$this->child_link = $link;
		$link->relation_name = $relation_name;
		return $link;
	}
	
	/**
	 * Instructs to chain our link AFTER the link defined as a parameter. An example:
	 * ```php
	 * class Library extends DataObject
	 * {
	 *  	private static $has_many = ['Books' => Book::class],
	 * }
	 *
	 * class Book extends DataObject
	 * {
	 * 	private static $has_one = ['Library' => Library::class];
	 *
	 * 	public function CMSEditLinkProvider()
	 * 	{
	 * 		return Taitava\CMSEditLink\CMSEditLink::LinkFor($this)->via($this->Library(), 'Books'); // via() Indicates that to be able to edit a Book in the CMS, the CMS should seek the Book via the Library where the Book belongs to.
	 *
	 * 		// Or the same result can be achieved by using hereon() like this: (note the swapped positions of $this and $this->Library()!)
	 * 		return Taitava\CMSEditLink\CMSEditLink::LinkFor($this->Library())->hereon($this, 'Books');
	 * 	}
 	 * }
	 * ```
	 *
	 * @param DataObject|CMSEditLink $data_object_or_link
	 * @param string $relation_name A has_one/has_many/many_many/belongs_to/belongs_many_many relation name. Note that this should be the name of the relation that the given $data_object_or_link uses when referring to the current DataObject. See the example above.
	 * @return CMSEditLink
	 */
	public function via($data_object_or_link, $relation_name)
	{
		$link = static::resolve_link($data_object_or_link);
		$link->child_link = $this;
		$this->parent_link = $link;
		$this->relation_name = $relation_name;
		return $link;
	}
	
	public function Parent()
	{
		return $this->parent_link;
	}
	
	public function Root()
	{
		if ($this->parent_link)
		{
			return $this->parent_link->Root();
		}
		else
		{
			return $this;
		}
	}
	
	/**
	 * @param bool $include_me
	 * @return CMSEditLink[]
	 */
	public function Children($include_me = false)
	{
		$children = [];
		if ($include_me) $children[] = $this;
		$child = $this->child_link;
		while ($child)
		{
			$children[] = $child;
			$child = $child->child_link;
		}
		return $children;
	}
	
	private static function resolve_link($data_object_or_link)
	{
		if ($data_object_or_link instanceof self)
		{
			// It's already a link instance, so no need to generate one
			// Clone the link to prevent possible further chained method calls from altering the original link
			return clone $data_object_or_link;
		}
		elseif ($data_object_or_link instanceof DataObject)
		{
			return static::LinkFor($data_object_or_link);
		}
		else
		{
			throw new InvalidArgumentException(__METHOD__ . ': Argument $data_object_or_link should be an instance of either DataObject or CMSEditLink, but it is: '.get_class($data_object_or_link).' ('.gettype($data_object_or_link).')');
		}
	}
	
	/**
	 * Returns a CMS edit link for the given DataObject.
	 *
	 * @param DataObject $data_object
	 * @param string $action Usually the default value 'edit' is what you want.
	 * @return static
	 */
	public static function LinkFor(DataObject $data_object, $action = 'edit')
	{
		return Injector::inst()->create(static::class, $data_object, $action);
	}
	
	/**
	 * @return string
	 * @throws \Exception
	 */
	public function __toString()
	{
		return $this->URL();
	}
	
	/**
	 * @return string
	 * @throws \Exception
	 */
	public function forTemplate()
	{
		return $this->__toString();
	}
	
	/**
	 * @return string
	 * @throws \Exception
	 */
	public function URL()
	{
		/** @var CMSLinkScaffolder $link_scaffolder */
		$link_scaffolder = Injector::inst()->create(CMSLinkScaffolder::class, $this->Root());
		return $link_scaffolder->scaffold();
	}
	
	/**
	 * Gets or sets the 'action' part of the link. 'Action' is the last part of the link and in CMS links it's usually
	 * 'edit'. Note that if you are using chained links, then only the **last** link's action in the chain is used and
	 * all others discarded! See these examples:
	 *
	 * ```php
	 * $book = Book::get()->byID(1);
	 * $book->CMSEditLink()->via($book->Library(), 'Books')->action('customAction'); // Wrong because the action would be set to the Library part of the link
	 * $book->CMSEditLink()->action('customAction')->via($book->Library(), 'Books'); // Correct because the Book part will the last one when the link chain will be rendered
	 * ```
	 * Another example:
	 * ```php
	 * $library = Library::get()->byID(1);
	 * $library->CMSEditLink()->hereon($library->Books()->first(), 'Books')->action('customAction'); // Correct because the Book part will the last one when the link chain will be rendered
	 * $library->CMSEditLink()->action('customAction')->hereon($library->Books()->first(), 'Books'); // Wrong because the action would be set to the Library part of the link
	 * ```
	 *
	 * @param string|null $set_action If defined, will set the link's 'action' to the specified string value.
	 * @return string|$this Returns the link's 'action', but only if $set_action is left null. If $set_action is something else than null, $this link is returned to make it possible to fluently continue calling methods after calling this method.
	 */
	public function action($set_action = null)
	{
		if (null !== $set_action)
		{
			$this->action = $set_action;
			return $this;
		}
		else
		{
			return $this->action;
		}
	}
	
	/**
	 * @return DataObject
	 */
	public function getDataObject()
	{
		return $this->data_object;
	}
	
	/**
	 * @param DataObject $data_object
	 */
	public function setDataObject($data_object)
	{
		$this->data_object = $data_object;
	}
	
	/**
	 * @return mixed
	 */
	public function getRelationName()
	{
		return $this->relation_name;
	}
	
	/**
	 * @param mixed $relation_name
	 */
	public function setRelationName($relation_name)
	{
		$this->relation_name = $relation_name;
	}
}