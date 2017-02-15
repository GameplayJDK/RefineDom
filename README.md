# RefineDom

RefineDom - Simple and fast Html parser refined.

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Creating new document](#creating-new-document)
- [Search for elements](#search-for-elements)
- [Verify if element exists](#verify-if-element-exists)
- [Supported selectors](#supported-selectors)
- [Output](#output)
- [Creating a new element](#creating-a-new-element)
- [Getting parent element](#getting-parent-element)
- [Getting sibling elements](#getting-sibling-elements)
- [Getting the child elements](#getting-the-child-elements)
- [Getting owner document](#getting-owner-document)
- [Working with element attributes](#working-with-element-attributes)
- [Comparing elements](#comparing-elements)
- [Adding a child element](#adding-a-child-element)
- [Replacing an element](#replacing-an-element)
- [Removing element](#removing-element)
- [Working with cache](#working-with-cache)
- [Comparison with other parsers](#comparison-with-other-parsers)

## Installation

At the time of writing there is no public Packagist package. Therefor a custom vcs repository has to be defined in your `composer.json`:

```json
...
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:GameplayJDK/RefineDom.git"
    }
],
...
```

After that you can install RefineDom using the following command:

    composer require gameplayjdk/refinedom

In the future you may be able to install without defining a custom vcs repository.

## Quick start

```php
use RefineDom\Document;

$document = new Document('news.html', true);

$posts = $document->find('.post');

foreach ($posts as $post)
{
    echo($post->text(), "\n");
}
```

## Creating new document

RefineDom currently allows to load html in three ways:

##### With constructor

```php
// the first parameter is a string with html
$document = new Document($html);

// file path
$document = new Document('page.html', true);

// or DOMDocument
$document = new Document($doc);
```

The second parameter specifies if you need to load file. Default is `false`.

##### With separate methods

```php
$document = new Document();

$document->load($html);

$document->loadFile('page.html');

$document->loadDocument($doc);
```

The `load` method is also available for loading Xml but requires `$isHtml` to be set to false using either the third constructor argument or `$document->setIsHtml(false);`.

It then accept additional options:

```php
$document->load($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
```

## Search for elements

RefineDom allows CSS selectors or XPath expressions for search. You need to pass the expression as the first parameter, and specify its type in the second one (default type is `Query::TYPE_CSS`):

##### With method `find()`:

```php
use RefineDom\Document;
use RefineDom\Query;
    
...

// with CSS selector
$posts = $document->find('.post');

// or XPath
$posts = $document->find("//div[contains(@class, 'post')]", Query::TYPE_XPATH);
```

##### With magic method `__invoke()`:

```php
$posts = $document('.post');
```

##### With method `xPath()`:

```php
$posts = $document->xPath("//*[contains(concat(' ', normalize-space(@class), ' '), ' post ')]");
```

You can search inside an element:

```php
echo $document->find('.post')[0]->find('h2')[0]->text();
```

If the elements that match a given expression are found, then the methods return an array of instances of `RefineDom\Element`, otherwise an empty array. You could also get an array of `DOMElement` objects. To get this, pass `false` as the third parameter.

To avoid the array square brackets (these: `[]`), use the `findIndex` and `xPathIndex` methods:

```php
$posts = $document->findIndex('header', 1)->xPathIndex("//h1")->text();
```

Its first argument defaults to zero and the other arguments are the ones available for normal search methods.

### Verify if element exists

To verify if an element exist use the `has` method:

```php
if ($document->has('.post'))
{
    // code
}
```

If you need to check if element exist and then get it:

```php
if ($document->has('.post'))
{
    $elements = $document->find('.post');
    // code
}
```

but it would be faster like this:

```php
if (count($elements = $document->find('.post')) > 0)
{
    // code
}
```

because in the first case it makes two requests.

Note that expressions are cached thougth. See the [working with cache](#working-with-cache) section for details.

This check also works with `findIndex` (and also `xPathIndex`):

```php
if (($element = $document->findIndex('.post', 3)) !== null)
{
    // code
}
```

which retun `null` when the element at index does not exist.

## Supported selectors

RefineDom supports search by:

- tag
- class, ID, name and value of an attribute
- pseudo-classes:
    - first-, last-, nth-child
    - empty and not-empty
    - contains
    - has

```php
// all links
$document->find('a');

// any element with id = "foo" and a "bar" class
$document->find('#foo.bar');

// any element with attribute "name"
$document->find('[name]');
// which is the same as
$document->find('*[name]');

// input field with the name "foo"
$document->find('input[name=foo]');
$document->find('input[name=\'bar\']');
$document->find('input[name="baz"]');

// any element that has an attribute starting with "data-" and the value "foo"
$document->find('*[^data-=foo]');

// all links starting with https
$document->find('a[href^=https]');

// all images with the extension "png" assuming their src attribute ends 'png'
$document->find('img[src$=png]');

// all links containing the string "example.com"
$document->find('a[href*=example.com]');

// text of the links with "foo" class
$document->find('a.foo::text');

// address and title of all the links with "bar" class
$document->find('a.bar::attr(href|title)');
```

## Output

### Getting Html

##### With method `html()`:

```php    
$post = $document->findIndex('.post');

echo $post->html();
```

##### Casting to string:

```php
$html = (string) $posts[0];
```

##### Formatting Html output:

```php
$html = $document->format()->html();
```

An element does not have the `format()` method, so if you need to output formatted Html of the element, then first you have to convert it to a document like this:

```php
$html = $element->toDocument()->format()->html();
```

Adittionally you can supply additional options to `xml` as well as to `html`:

```php
$html = $document->format()->xml(LIBXML_NOEMPTYTAG);
```

##### Unformatted Html output:

To output unformatted html or xml give a boolean argument to `format` (or `setFormat`):

```php
$unformatted = $document->format(false)->html();
```

#### Inner Html

```php
$innerHtml = $element->innerHtml();
```

Document does not have the method `innerHtml()`, therefore, if you need to get inner Html of a document, convert it into an element first:

```php
$innerHtml = $document->toElement()->innerHtml();
```

### Getting content

```php    
$posts = $document->find('.post');

echo $posts[0]->text();
```

## Creating a new element

### Creating an instance of the class

```php
use RefineDom\Element;

$element = new Element('span', 'Hello');
    
// outputs "<span>Hello</span>"
echo $element->html();
```

First parameter is the name of the element, the second one is its text value (optional), the third one is an array of element attributes (also optional).

An example of creating an element with attributes:

```php
$attributes = ['name' => 'description', 'placeholder' => 'Enter description of item'];

$element = new Element('textarea', 'Text', $attributes);
```

An element can also be created from an instance of the class `DOMElement`:

```php
use RefineDom\Element;
use DOMElement;

$domElement = new DOMElement('span', 'Hello');

$element = new Element($domElement);
```

### Using the method `createElement` of a document

```php
$document = new Document($html);

$element = $document->createElement('span', 'Hello');
```

## Getting parent element

```php
$document = new Document($html);

$input = $document->findIndex('input[name=email]');

var_dump($input->parent());
```

## Getting sibling elements

```php
$document = new Document($html);

$item = $document->findIndex('ul.menu > li', 1);

var_dump($item->previousSibling());

var_dump($item->nextSibling());
```

## Getting the child elements

```php
$html = '
<ul>
    <li>Foo</li>
    <li>Bar</li>
    <li>Baz</li>
</ul>
';

$document = new Document($html);
$list = $document->first('ul');

// string(3) "Baz"
var_dump($item->child(2)->text());

// string(3) "Foo"
var_dump($item->firstChild()->text());

// string(3) "Baz"
var_dump($item->lastChild()->text());

// array(3) { ... }
var_dump($item->children());
```

## Getting owner document

```php
$document = new Document($html);

$element = $document->findIndex('input[name=email]', 0);

$otherDocument = $element->getDocument();

// bool(true)
var_dump($document->is($otherDocument));
```

## Working with element attributes

#### Getting the tag name
```php
$name = $element->tag;
```

#### Creating/updating an attribute

##### With method `setAttribute`:
```php
$element->setAttribute('name', 'username');
```

##### With method `attr`:
```php
$element->attr('name', 'username');
```

##### With magic method `__set`:
```php
$element->name = 'username';
```

#### Getting value of an attribute

##### With method `getAttribute`:
```php
$username = $element->getAttribute('value');
```

##### With method `attr`:
```php
$username = $element->attr('value');
```

##### With magic method `__get`:
```php
$username = $element->name;
```

Returns `null` if attribute is not found.

#### Verify if attribute exists

##### With method `hasAttribute`:
```php
if ($element->hasAttribute('name'))
{
    // code
}
```

##### With magic method `__isset`:
```php
if (isset($element->name))
{
    // code
}
```

#### Removing attribute:

##### With method `removeAttribute`:
```php
$element->removeAttribute('name');
```

##### With magic method `__unset`:
```php
unset($element->name);
```

## Comparing elements

```php
$element = new Element('span', 'hello');
$otherElement = new Element('span', 'hello');

// bool(true)
var_dump($element->is($element));

// bool(false)
var_dump($element->is($otherElement));
```

## Appending child elements

```php
$list = new Element('ul');

$item = new Element('li', 'Item 1');

$list->appendChild($item);

$items = [
    new Element('li', 'Item 2'),
    new Element('li', 'Item 3'),
];

$list->appendChild($items);
```

## Adding a child element

```php
$list = new Element('ul');

$item = new Element('li', 'Item 1');
$items = [
    new Element('li', 'Item 2'),
    new Element('li', 'Item 3'),
];

$list->appendChild($item);
$list->appendChild($items);
```

## Replacing an element

```php
$element = new Element('span', 'hello');

$document->find('.post')[0]->replace($element);
```

## Removing element

```php
$document->findIndex('.post')->remove();
```

## Working with cache

Cache is an associative array of XPath expressions, that were converted from CSS selectors. The CSS selector is the key.

#### Getting from cache

```php
use RefineDom\Query;

...

$xPath = Query::compile('h2');
$compiled = Query::getCompiled();

// array('h2' => '//h2')
var_dump($compiled);
```

#### Installing a cache

Using a predefined cache can help to improve the speed as there is no need to recompile a selector.

```php
Query::setCompiled(['h2' => '//h2']);
```

## Comparison with other parsers

This comparison refers to DiDom, not RefineDom. Numbers should not be off that much thought.

[Comparison with other parsers](https://github.com/Imangazaliev/DiDOM/wiki/Comparison-with-other-parsers-(1.0))
