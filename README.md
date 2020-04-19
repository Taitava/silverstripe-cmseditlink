# taitava/silverstripe-cmseditlink

In it's most simplest form, this module provides a `->CMSEditLink()` method via an `Extension` which can be applied to any `DataObject` classes you wish. The method returns a `CMSEditLink` instance  which can be converted to a string representing an URL to an editor page where the desired `DataObject` can be edited.

To add more complexity, you can also create *chained* links which routes the editor to edit a `DataObject` via another `DataObject`, and even via multiple `DataObject`s.

This module is currently in a development stage. It needs more testing before a stable release. Also some method names may still change. This is not a huge module, so I do think that it's possible to use this production projects - just make sure to test it really well :). **Note** that it's not currently possible to create editing links for `Member` objects. This is because `Members` are managed via `SecurityAdmin`, which is not a subclass of `ModelAdmin`. This module currently requires a `ModelAdmin` for scaffolding links.

Also the documentation is not the best possible at this point.

## Requirements

 - SilverStripe 4.x

## Installation

`composer require taitava/silverstripe-cmseditlink:*`

## Simple usage

First you need to apply this extension module to all the `DataObject` classes you want to. This example configuration code will apply it to all `DataObject` classes. Put this in a new file named *app\_config\cmseditlink.yml*:
```YAML
SilverStripe\ORM\DataObject:
  extensions:
    - Taitava\CMSEditLink\DataObjectExtension
```

Now you can create simple links just like this:
```php
$book = Book::get()->byID(1);
$link = $book->CMSEditLink();
```

The returned link is not a string, but an instance of `CMSEditLink` instead. However, it's easy to turn this instance into a simple string formatted url: `$link_url = (string) $book->CMSEditLink();` or `$link_url = $book->CMSEditLink()->URL();`.

Or yet another way to create links:
```php
$book = Book::get()->byID(1);
$link = Taitava\CMSEditLink\CMSEditLink::LinkFor($book);
```

If you are using the ->CMSEditLink() method, you will need to make sure that you enable the `Taitava\CMSEditLink\DataObjectExtension` extension to all the `DataObject`s you want to make the method available to. For example put this in your *app/_config/cmseditlink.yml*
file:

```YAML
SilverStripe\ORM\DataObject:
  extensions:
    - Taitava\CMSEditLink\DataObjectExtension
```

In the other hand, you can use `Taitava\CMSEditLink\CMSEditLink::LinkFor($any_data_object)` to create links for any `DataObjects` -
even for the ones which do not have the above mentioned extension enabled.

## A note about ModelAdmins

The only requirement for `DataObjects` is that there must be some `ModelAdmin` class present in your project which defines
the `DataObject`'s class name in it's `$managed_models` configuration array. If no such `ModelAdmin` exists, you need to
either create one yourself or you can use the 'chaining' functionality described above to first get a link for another
`DataObject` class which does have a `ModelAdmin` available, and then chain the link to the desired target object. This
requires an ORM relation between the two `DataObject` classes (in practise: any relation defined in `$has_one`,
`$has_many`, `$many_many` or `$belongs_to` is suitable).

The module tries to automatically find a `ModelAdmin` class which is able to manage the desired `DataObject`. Usually it picks a decent one, but there might be cases where multiple `ModelAdmin` classes list the `DataObject`'s class in their `$managed_models` configuration array, in which case the module picks the one that it happens to first come by. If you want, you can override the automatic selection of a `ModelAdmin` by defining a `getModelAdminForCMSEditLink()` method in your `DataObject` sub class:

```php
class Book extends DataObject
{
        public function getModelAdminForCMSEditLink()
        {
                return BookAdminWhichHandlesBooksWithSoftGloves::class;
                // Or:
                return BookAdminWhichHandlesBooksWithSoftGloves::create();
        }
}

class BookAdminWhichTearsPagesAndBurnsThemToAshes
{
        $managed_models = [
        	Book::class,
        ];
}

class BookAdminWhichHandlesBooksWithSoftGloves extends ModelAdmin
{
        $managed_models = [
        	Book::class,
        ];
}
```

## Link chaining and "breadcrumbs"

Links can be chained infinitely to create "breadcrumbs" that lead to the actual editing page. For example, if you have a `Book` object and you want to provide a CMS editing link which also denotes a `Library` object, you can create the link like this:

```php
$book = Book::get()->byID(1);
$book->CMSEditLink()->via($book->Library(), 'Books');
```
Or create the same link in another way:
```php
$library = Library::get()->byID(1);
$library->CMSEditLink()->hereon($library->Books()->first(), 'Books');
```

## Custom actions

By default, all created links will use the CMS action called *'edit'*. If you have defined your own, custom actions in the CMS, you can use them in the links just like this:
```php
$book = Book::get()->byID(1);
$link = $book->CMSEditLink()->action('customAction');
```

Or to make it shorter:
```php
$book = Book::get()->byID(1);
$link = $book->CMSEditLink('customAction');
```

## Customise the link that `->CMSEditLink()` is going to return

In addition to altering the link after it's been received from `->CMSEditLink()`, you can also perform some default alterations on a `DataObject` level:


```php
class Book extends DataObject
{
        private static $has_one = [
                'Library' => Library::class,
        ];

        public function ProvideCMSEditLink($action)
        {
                if ($action == 'edit') $action = 'tagAuthors'; // Switch the default 'edit' action to an author tagging action, or whatever.
                $link = CMSEditLink::LinkFor($this,$action);
                $link->via($this->Library(), 'Books'); // Scaffold the link via the book's holder Library
                return $link; // The alterations made in this function ...
        }
}

class Library extends DataObject
{
        private static $has_many = [
                'Books' => Book::class,
        ];
}

$book = Book::get()->byID(1);
$link = $book->CMSEditLink(); // ... will be in place here.
```

## Future

See the GitHub issue tracker for any current development plans and bugs. You can create new issues if you have development ideas, bug reports or any implementation/usage related questions.

Pull requests are also welcome!

## Author

Jarkko Linnanvirta. License: MIT.

Also thank you PsychoMo for helping me in a related SilverStripe forum topic: https://forum.silverstripe.org/t/modeladmin-how-to-get-a-cms-edit-link-url-for-a-specific-dataobject/1958/5
