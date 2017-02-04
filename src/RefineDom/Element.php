<?php

namespace RefineDom;

use DOMDocument;
use DOMNode;
use DOMElement;
use DOMText;
use DOMComment;

use InvalidArgumentException;
use RuntimeException;
use LogicException;

class Element
{
    protected $node;

    public function __construct($name, $value = null, $attributes = [])
    {
        if (is_string($name))
        {
            $document = new DOMDocument('1.0', 'UTF-8');

            $node = $document->createElement($name);

            $this->setNode($node);
        }
        else
        {
            $this->setNode($name);
        }

        if ($value !== null)
        {
            $this->setValue($value);
        }

        if (!is_array($attributes))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 3rd parameter to be array, %2$s given.', [ __METHOD__, (is_object($attributes) ? get_class($attributes) : gettype($attributes)) ]));
        }

        foreach ($attributes as $name => $value)
        {
            $this->setAttribute($name, $value);
        }
    }

    public static function create($name, $value = null, $attributes = [])
    {
        return (new Element($name, $value, $attributes));
    }

    public static function createFromSelector($selector, $value = null, $attributes = [])
    {
        return Document::create()->createElementFromSelector($selector, $value, $attributes);
    }

    public function appendChild($nodes)
    {
        if ($this->node->ownerDocument === null)
        {
            throw new LogicException('Can not append child to Element without owner DOMDocument.');
        }

        $returnArray = true;

        if (!is_array($nodes))
        {
            $nodes = [ $nodes ];

            $returnArray = false;
        }

        $result = [];

        foreach ($nodes as $node)
        {
            if ($node instanceof Element)
            {
                $node = $node->getNode();
            }

            if (!$node instanceof DOMNode)
            {
                throw new InvalidArgumentException(vsprintf('%1$s expects the first parameter to be instance of Element or DOMNode, %2$s given.', [ __METHOD__, (is_object($node) ? get_class($node) : gettype($node)) ]));
            }

            Error::disable();

            $clone = $node->cloneNode(true);
            $node = $this->node->ownerDocument->importNode($clone, true);

            $result[] = $this->node->appendChild($node);

            Error::enable();
        }

        $result = array_map(function ($node) {
            return (new Element($node));
        }, $result);

        return ($returnArray ? $result : $result[0]);
    }

    public function has($expression, $type = Query::TYPE_CSS)
    {
        return $this->toDocument()->has($expression, $type);
    }

    public function find($expression, $type = Query::TYPE_CSS, $wrapElement = true)
    {
        return $this->toDocument()->find($expression, $type, $wrapElement);
    }

    public function findIndex($expression, $index = 0, $type = Query::TYPE_CSS, $wrapElement = true)
    {
        return $this->toDocument()->findIndex($expression, $index, $type, $wrapElement);
    }

    public function first($expression, $type = Query::TYPE_CSS, $wrapElement = true)
    {
        return $this->toDocument()->first($expression, $type, $wrapElement);
    }

    public function xPath($expression, $wrapElement = true)
    {
        return $this->toDocument()->xPath($expression, $wrapElement);
    }

    public function xPathIndex($expression, $index = 0, $wrapElement = true)
    {
        return $this->toDocument()->xPathIndex($expression, $index, $wrapElement);
    }

    public function count($expression, $type = Query::TYPE_CSS)
    {
        return $this->toDocument()->count($expression, $type);
    }

    public function matches($selector, $strict = false)
    {
        if (!$strict)
        {
            $node = $this->node->cloneNode();

            if (!$this->node instanceof DOMElement)
            {
                throw new LogicException('Node must be instance of DOMElement.');
            }

            $innerHtml = $node->ownerDocument->saveXml($node, LIBXML_NOEMPTYTAG);
            $html = '<root>' . $innerHtml . '</root>';

            $selector = 'root > ' . trim($selector);

            $document = new Document($html);

            return $document->has($selector);
        }

        $segments = Query::getSegments($selector);

        if (!array_key_exists('tag', $segments))
        {
            throw new RuntimeException(vsprintf('Tag name must be specified in %1$s', [ $selector ]));
        }

        if ($segments['tag'] !== $this->node->tagName && $segments['tag'] !== '*')
        {
            return false;
        }

        $segments['id'] = array_key_exists('id', $segments) ? $segments['id'] : null;

        if ($segments['id'] !== $this->getAttribute('id'))
        {
            return false;
        }

        $classes = $this->hasAttribute('class') ? explode(' ', trim($this->getAttribute('class'))) : [];

        $segments['classes'] = array_key_exists('classes', $segments) ? $segments['classes'] : [];

        $diff1 = array_diff($segments['classes'], $classes);
        $diff2 = array_diff($classes, $segments['classes']);

        if (count($diff1) > 0 || count($diff2) > 0)
        {
            return false;
        }

        $attributes = $this->attributes();

        unset($attributes['id']);
        unset($attributes['class']);

        $segments['attributes'] = array_key_exists('attributes', $segments) ? $segments['attributes'] : [];

        $diff1 = array_diff_assoc($segments['attributes'], $attributes);
        $diff2 = array_diff_assoc($attributes, $segments['attributes']);

        if (count($diff1) > 0 || count($diff2) > 0)
        {
            return false;
        }

        return true;
    }

    public function hasAttribute($name)
    {
        return $this->node->hasAttribute($name);
    }

    public function setAttribute($name, $value)
    {
        if (is_numeric($value))
        {
            $value = (string) $value;
        }

        if (!is_string($value) and $value !== null)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 2nd parameter to be string or null, %2$s given.', [ __METHOD__, (is_object($value) ? get_class($value) : gettype($value)) ]));
        }

        $this->node->setAttribute($name, $value);

        return $this;
    }

    public function getAttribute($name, $default = null)
    {
        if ($this->hasAttribute($name))
        {
            return $this->node->getAttribute($name);
        }

        return $default;
    }

    public function removeAttribute($name)
    {
        $this->node->removeAttribute($name);

        return $this;
    }

    public function attr($name, $value = null)
    {
        if ($value === null) {
            return $this->getAttribute($name);
        }

        return $this->setAttribute($name, $value);
    }

    public function attributes()
    {
        if (!$this->node instanceof DOMElement)
        {
            return null;
        }

        $attributes = [];

        foreach ($this->node->attributes as $name => $attr)
        {
            $attributes[$name] = $attr->value;
        }

        return $attributes;
    }

    public function html($options = LIBXML_NOEMPTYTAG)
    {
        return $this->toDocument()->html($options);
    }

    public function innerHtml($options = LIBXML_NOEMPTYTAG, $delimiter = '')
    {
        $innerHtml = [];
        $childNodes = $this->node->childNodes;

        foreach ($childNodes as $node)
        {
            $innerHtml[] = $node->ownerDocument->saveXml($node, $options);
        }

        return implode($delimiter, $innerHtml);
    }

    public function setInnerHtml($html)
    {
        if (!is_string($html))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, %2$s given.', [ __METHOD__, (is_object($html) ? get_class($html) : gettype($html)) ]));
        }

        foreach ($this->node->childNodes as $node)
        {
            $this->node->removeChild($node);
        }

        if ($html !== '')
        {
            Error::disable();

            $html = "<html-fragment>$html</html-fragment>";

            $document = new Document($html);

            $fragment = $document->first('html-fragment')->getNode();

            foreach ($fragment->childNodes as $node)
            {
                $newNode = $this->node->ownerDocument->importNode($node, true);

                $this->node->appendChild($newNode);
            }

            Error::enable();
        }

        return $this;
    }

    public function xml($options = 0)
    {
        return $this->toDocument()->xml($options);
    }

    public function text()
    {
        return $this->node->textContent;
    }

    public function setValue($value)
    {
        if (is_numeric($value))
        {
            $value = (string) $value;
        }

        if (!is_string($value) && $value !== null)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, %2$s given.', [ __METHOD__, (is_object($value) ? get_class($value) : gettype($value)) ]));
        }

        $this->node->nodeValue = $value;

        return $this;
    }

    public function isTextNode()
    {
        return ($this->node instanceof DOMText);
    }

    public function isCommentNode()
    {
        return $this->node instanceof DOMComment;
    }

    public function is($node)
    {
        if ($node instanceof self)
        {
            $node = $node->getNode();
        }

        if (!$node instanceof DOMNode)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be instance of %2$s or DOMDocument, %3$s given.', [ __METHOD__, __CLASS__, (is_object($node) ? get_class($node) : gettype($node)) ]));
        }

        return $this->node->isSameNode($node);
    }

    public function parent()
    {
        if ($this->node->parentNode === null)
        {
            return null;
        }

        if ($this->node->parentNode instanceof DOMDocument)
        {
            return (new Document($this->node->parentNode));
        }

        return (new Element($this->node->parentNode));
    }

    public function closest($selector, $strict = false)
    {
        $node = $this;

        while (true)
        {
            $parent = $node->parent();

            if ($parent === null || $parent instanceof Document)
            {
                return null;
            }

            if ($parent->matches($selector, $strict))
            {
                return $parent;
            }

            $node = $parent;
        }
    }

    public function previousSibling()
    {
        if ($this->node->previousSibling === null)
        {
            return null;
        }

        return (new Element($this->node->previousSibling));
    }

    public function nextSibling()
    {
        if ($this->node->nextSibling === null)
        {
            return null;
        }

        return (new Element($this->node->nextSibling));
    }

    public function child($index)
    {
        $child = $this->node->childNodes->item($index);

        return ($child === null ? null : new Element($child));
    }

    public function firstChild()
    {
        if ($this->node->firstChild === null)
        {
            return null;
        }

        return (new Element($this->node->firstChild));
    }

    public function lastChild()
    {
        if ($this->node->lastChild === null)
        {
            return null;
        }

        return (new Element($this->node->lastChild));
    }

    public function children()
    {
        $children = [];

        foreach ($this->node->childNodes as $node)
        {
            $children[] = new Element($node);
        }

        return $children;
    }

    public function remove()
    {
        if ($this->node->parentNode === null)
        {
            throw new LogicException('Can not remove element without parent node.');
        }

        $node = $this->node->parentNode->removeChild($this->node);

        return (new Element($node));
    }

    public function replace($newNode, $clone = true)
    {
        if ($this->node->parentNode === null)
        {
            throw new LogicException('Can not replace element without parent node.');
        }

        if ($newNode instanceof Element)
        {
            $newNode = $newNode->getNode();
        }

        if (!$newNode instanceof DOMNode)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be instance of Element or DOMNode, %2$s given.', [ __METHOD__, (is_object($newNode) ? get_class($newNode) : gettype($newNode)) ]));
        }

        if ($clone)
        {
            $newNode = $newNode->cloneNode(true);
        }

        if ($newNode->ownerDocument === null || !$this->getDocument()->is($newNode->ownerDocument))
        {
            $newNode = $this->node->ownerDocument->importNode($newNode, true);
        }

        $node = $this->node->parentNode->replaceChild($newNode, $this->node);

        return (new Element($node));
    }

    public function getLineNo()
    {
        return $this->node->getLineNo();
    }

    public function cloneNode($deep = true)
    {
        $node = $this->node->cloneNode($deep);

        return (new Element($node));
    }

    protected function setNode($node)
    {
        if (!is_object($node))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, %2$s given.', [ __METHOD__, gettype($node) ]));
        }

        if (!$node instanceof DOMElement && !$node instanceof DOMText && !$node instanceof DOMComment)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be instance of DOMElement, DOMText or DOMComment, %2$s given.', [ __METHOD__, get_class($node) ]));
        }

        $this->node = $node;

        return $this;
    }

    public function getNode()
    {
        return $this->node;
    }

    public function getDocument()
    {
        if ($this->node->ownerDocument === null)
        {
            return null;
        }

        return (new Document($this->node->ownerDocument));
    }

    public function toDocument($encoding = 'UTF-8')
    {
        $document = Document::create(null, false, true, $encoding);
        $document->appendChild($this->node);

        return $document;
    }

    public function __set($name, $value)
    {
        return $this->setAttribute($name, $value);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'tag':
                return $this->node->tagName;
                break;
            default:
                return $this->getAttribute($name);
                break;
        }
    }

    public function __isset($name)
    {
        return $this->hasAttribute($name);
    }

    public function __unset($name)
    {
        $this->removeAttribute($name);
    }

    public function __toString()
    {
        return $this->html();
    }

    public function __invoke($expression, $type = Query::TYPE_CSS, $wrapElement = true)
    {
        return $this->find($expression, $type, $wrapElement);
    }
}
