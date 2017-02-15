<?php

namespace RefineDom;

use DOMDocument;
use DOMNode;
use DOMElement;
use DOMXPath;

use InvalidArgumentException;
use RuntimeException;

class Document
{
    protected $document;

    protected $isHtml;
    protected $encoding;

    public function __construct($content = null, $isFile = false, $isHtml = true, $encoding = 'UTF-8')
    {
        if (!is_string($content) && !is_object($content) && !is_null($content))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, object or null, %2$s given.', [ __METHOD__, gettype($content) ]));
        }

        if (!is_bool($isFile))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 2nd parameter to be boolean, %2$s given.', [ __METHOD__, gettype($isFile) ]));
        }

        if (!is_bool($isHtml))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 3rd parameter to be boolean, %2$s given.', [ __METHOD__, gettype($isHtml) ]));
        }

        if (!is_string($encoding))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 4th parameter to be string, %2$s given.', [ __METHOD__, gettype($encoding) ]));
        }

        $this->document = new DOMDocument('1.0', $this->encoding);

        $this->isHtml = $isHtml;
        $this->encoding = $encoding;

        $this->setPreserveWhiteSpace(false);

        if ($content !== null) {
            if ($content instanceof DOMDocument)
            {
                $this->loadDocument($content);
            }
            else
            {
                $this->load($content, $isFile);
            }
        }
    }

    public static function create($content = null, $isFile = false, $isHtml = true, $encoding = 'UTF-8')
    {
        return (new Document($content, $isFile, $isHtml, $encoding));
    }

    public function createElement($name, $value = null, $attributes = [])
    {
        $node = $this->document->createElement($name);

        $element = new Element($node, $value, $attributes);

        return $element;
    }

    public function createElementFromSelector($selector, $value = null, $attributes = [])
    {
        $segments = Query::getSegments($selector);

        $name = array_key_exists('tag', $segments) ? $segments['tag'] : 'div';

        if (array_key_exists('attributes', $segments))
        {
            $attributes = array_merge($attributes, $segments['attributes']);
        }

        if (array_key_exists('id', $segments))
        {
            $attributes['id'] = $segments['id'];
        }

        if (array_key_exists('classes', $segments))
        {
            $attributes['class'] = implode(' ', $segments['classes']);
        }

        return $this->createElement($name, $value, $attributes);
    }

    public function appendChild($nodes)
    {
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
            $node = $this->document->importNode($clone, true);

            $result[] = $this->document->appendChild($node);

            Error::enable();
        }

        $result = array_map(function ($node) {
            return (new Element($node));
        }, $result);

        return ($returnArray ? $result : $result[0]);
    }

    public function load($content, $isFile = false, $loadOptions = 0)
    {
        if (!is_string($content))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, %2$s given. Use loadDocument to load a DOMDocument.', [ __METHOD__, (is_object($content) ? get_class($content) : gettype($content)) ]));
        }

        if (!is_bool($isFile))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 2nd parameter to be boolean, %2$s given.', [ __METHOD__, gettype($isFile) ]));
        }

        if (!is_integer($loadOptions)) {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 3rd parameter to be integer, %2$s given.', [ __METHOD__, (gettype($loadOptions)) ]));
        }

        $content = trim($content);

        if ($isFile) {
            $content = $this->loadFile($string);
        }

        Error::disable();

        if ($this->isHtml)
        {
            $this->document->loadHtml($content, $loadOptions);
        }
        else
        {
            $declaration = '<?xml';

            if (substr($content, 0, strlen($declaration)) !== $declaration)
            {
                $declaration = vsprintf('<?xml version="1.0" encoding="%1$s" ?>', [ $this->document->encoding ]);

                $content = $declaration . $content;
            }

            $this->document->loadXml($content, $loadOptions);
        }

        Error::enable();

        return $this;
    }

    public function loadDocument($document)
    {
        if (!is_object($document))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be object, %2$s given.', [ __METHOD__, gettype($document) ]));;
        }

        if (!$document instanceof DOMDocument)
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be instance of DOMDocument, %2$s given.', [ __METHOD__, get_class($document) ]));
        }

        $this->document = $document;

        return $this;
    }

    protected function loadFile($path)
    {
        if (!is_string($path))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be string, %2$s given.', [ __METHOD__, gettype($path) ]));
        }

        if (!file_exists($path))
        {
            throw new RuntimeException(vsprintf('File %1$s not found.', [ $path ]));
        }

        $content = file_get_contents($path);

        if ($content === false)
        {
            throw new RuntimeException(vsprintf('File %1$s could not be loaded.', [ $path ]));
        }

        return $content;
    }

    public function has($expression, $type = Query::TYPE_CSS)
    {
        $xPath = new DOMXPath($this->document);

        $expression = Query::compile($expression, $type);
        $expression = vsprintf('count(%1$s) > 0', [ $expression ]);

        return $xPath->evaluate($expression);
    }

    public function find($expression, $type = Query::TYPE_CSS, $wrapElement = true, $contextNode = null)
    {
        $expression = Query::compile($expression, $type);

        $xPath = new DOMXPath($this->document);

        $xPath->registerNamespace("php", "http://php.net/xpath");
        $xPath->registerPhpFunctions();

        if ($contextNode !== null)
        {
            if ($contextNode instanceof Element)
            {
                $contextNode = $contextNode->getNode();
            }

            if (!$contextNode instanceof DOMElement)
            {
                throw new InvalidArgumentException(vsprintf('%1$s expects the 4th parameter to be instance of Element or DOMElement, %2$s given.', [ __METHOD__, (is_object($contextNode) ? get_class($contextNode) : gettype($contextNode)) ]));
            }

            if ($type === Query::TYPE_CSS)
            {
                $expression = '.' . $expression;
            }
        }

        $nodeList = $xPath->query($expression, $contextNode);

        $result = [];

        if ($wrapElement)
        {
            foreach ($nodeList as $node)
            {
                $result[] = $this->wrapNode($node);
            }
        }
        else
        {
            foreach ($nodeList as $node)
            {
                $result[] = $node;
            }
        }

        return $result;
    }

    public function findIndex($expression, $index = 0, $type = Query::TYPE_CSS, $wrapElement = true, $contextNode = null)
    {
        $result = $this->find($expression, $type, $wrapElement, $contextNode);

        if (count($result) <= $index)
        {
            return null;
        }

        return $result[$index];
    }

    public function first($expression, $type = Query::TYPE_CSS, $wrapElement = true, $contextNode = null)
    {
        $expression = Query::compile($expression, $type);
        $expression = vsprintf('(%1$s)[1]', [ $expression ]);

        $nodes = $this->find($expression, Query::TYPE_XPATH, false, $contextNode);

        if (count($nodes) === 0)
        {
            return null;
        }

        return ($wrapElement ? $this->wrapNode($nodes[0]) : $nodes[0]);
    }

    protected function wrapNode($node)
    {
        switch (get_class($node))
        {
            case 'DOMElement':
                return (new Element($node));
                break;
            case 'DOMText':
                return $node->data;
                break;
            case 'DOMAttr':
                return $node->value;
                break;
        }

        throw new RuntimeException(vsprintf('Unknown node type: %1$s.', [ get_class($node) ]));
    }

    public function xPath($expression, $wrapElement = true, $contextNode = null)
    {
        return $this->find($expression, Query::TYPE_XPATH, $wrapElement, $contextNode);
    }

    public function xPathIndex($expression, $index = 0, $wrapElement = true, $contextNode = null)
    {
        $result = $this->xPath($expression, $wrapElement, $contextNode);

        if (count($result) <= $index)
        {
            return null;
        }

        return $result[$index];
    }

    public function count($expression, $type = Query::TYPE_CSS)
    {
        $xPath = new DOMXPath($this->document);

        $expression = Query::compile($expression, $type);
        $expression = vsprintf('count(%1$s)', [ $expression ]);

        return $xPath->evaluate($expression);
    }

    public function html($options = LIBXML_NOEMPTYTAG)
    {
        return trim($this->document->saveXML($this->getElement(), $options));
    }

    public function xml($options = 0)
    {
        return trim($this->document->saveXML($this->document, $options));
    }

    public function setPreserveWhiteSpace($preserveWhiteSpace = true)
    {
        if (!is_bool($preserveWhiteSpace))
        {
            throw new InvalidArgumentException(sprintf('%1$s expects the 1st parameter to be boolean, %2$s given.', [ __METHOD__, gettype($preserveWhiteSpace) ]));
        }

        $this->document->preserveWhiteSpace = $preserveWhiteSpace;

        return $this;
    }

    public function setFormat($format = true)
    {
        if (!is_bool($format))
        {
            throw new InvalidArgumentException(sprintf('%1$s expects the 1st parameter to be boolean, %2$s given.', [ __METHOD__, gettype($format) ]));
        }

        $this->document->formatOutput = $format;

        return $this;
    }

    public function format($format = true)
    {
        return $this->setFormat($format);
    }

    public function text()
    {
        return $this->getElement()->textContent;
    }

    public function is($document)
    {
        if ($document instanceof self)
        {
            $element = $document->getElement();
        }
        else
        {
            if (!$document instanceof DOMDocument)
            {
                throw new InvalidArgumentException(sprintf('%1$s expects the 1st parameter to be instance of %2$s or DOMDocument, %3$s given.', [ __METHOD__, __CLASS__, (is_object($document) ? get_class($document) : gettype($document)) ]));
            }

            $element = $document->documentElement;
        }

        if ($element === null)
        {
            return false;
        }

        return $this->getElement()->isSameNode($element);
    }

    public function isHtml()
    {
        return $this->isHtml;
    }

    public function setIsHtml($isHtml)
    {
        if (!is_bool($isHtml))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be boolean, %2$s given.', [ __METHOD__, gettype($isHtml) ]));
        }

        if ($this->document->documentElement !== null)
        {
            throw new RuntimeException('The type of an already loaded Document can not be changed.');
        }

        $this->isHtml = $isHtml;

        return $this;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function getElement()
    {
        return $this->document->documentElement;
    }

    public function toElement()
    {
        if ($this->document->documentElement === null)
        {
            throw new RuntimeException('An empty Document can not be converted to Element.');
        }

        return (new Element($this->document->documentElement));
    }

    public function __toString()
    {
        return $this->isHtml ? $this->html() : $this->xml();
    }

    public function __invoke($expression, $type = Query::TYPE_CSS, $wrapElement = true)
    {
        return $this->find($expression, $type, $wrapElement);
    }
}
