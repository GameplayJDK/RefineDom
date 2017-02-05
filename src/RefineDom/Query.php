<?php

namespace RefineDom;

use InvalidArgumentException;
use RuntimeException;

class Query
{
    const TYPE_XPATH = 'XPATH';
    const TYPE_CSS   = 'CSS';

    protected static $compiled = array();

    public static function compile($expression, $type = self::TYPE_CSS)
    {
        if ($type === self::TYPE_XPATH)
        {
            return $expression;
        }

        $selectors = explode(',', $expression);
        $paths = [];

        foreach ($selectors as $selector)
        {
            $selector = trim($selector);

            if (array_key_exists($selector, static::$compiled))
            {
                $paths[] = static::$compiled[$selector];

                continue;
            }

            static::$compiled[$selector] = static::cssToXPath($selector);

            $paths[] = static::$compiled[$selector];
        }

        $expression = implode('|', $paths);

        return $expression;
    }

    public static function cssToXPath($selector, $prefix = '//')
    {
        $position = strrpos($selector, '::');

        if ($position !== false)
        {
            $property = substr($selector, $position + 2);
            $property = self::parseProperty($property);
            $property = self::convertProperty($property['name'], $property['args']);

            $selector = substr($selector, 0, $position);
        }

        if (substr($selector, 0, 1) === '>')
        {
            $prefix = '/';

            $selector = ltrim($selector, '> ');
        }

        $segments = self::getSegments($selector);
        $xPath = '';

        while (count($segments) > 0)
        {
            $xPath .= self::buildXPath($segments, $prefix);

            $selector = substr($selector, strlen($segments['selector']));
            $selector = trim($selector);
            $prefix   = isset($segments['rel']) ? '/' : '//';

            if ($selector === '')
            {
                break;
            }

            $segments = self::getSegments($selector);
        }

        if (isset($property))
        {
            $xPath = $xPath . '/' . $property;
        }

        return $xPath;
    }

    protected static function parseProperty($property)
    {
        $namePattern = '(?P<name>[\w\-]*)';
        $argsPattern = '(?:\((?P<args>[^\)]+)\))';
        $regexp = '/(?:' . $namePattern . $argsPattern . '?)?/is';

        if (preg_match($regexp, $property, $segments)) {
            $result = [];

            $result['name'] = $segments['name'];
            $result['args'] = isset($segments['args']) ? explode('|', $segments['args']) : [];

            return $result;
        }

        throw new RuntimeException('Invalid selector.');
    }

    protected static function convertProperty($name, $args = [])
    {
        if ($name === 'text')
        {
            return 'text()';
        }

        if ($name === 'attr')
        {
            $attributes = [];

            foreach ($args as $attribute)
            {
                $attributes[] = vsprintf('name() = "%1$s"', [ $attribute ]);
            }

            return vsprintf('@*[%1$s]', [ implode(' or ', $attributes) ]);
        }

        throw new RuntimeException('Invalid selector: Unknown property type.');
    }

    public static function buildXPath($segments, $prefix = '//')
    {
        $tagName = isset($segments['tag']) ? $segments['tag'] : '*';

        $attributes = [];

        if (isset($segments['id']))
        {
            $attributes[] = vsprintf('@id="%1$s"', [ $segments['id'] ]);
        }

        if (isset($segments['classes']))
        {
            foreach ($segments['classes'] as $class)
            {
                $attributes[] = vsprintf('contains(concat(" ", normalize-space(@class), " "), " %1$s ")', [ $class ]);
            }
        }

        if (isset($segments['attributes']))
        {
            foreach ($segments['attributes'] as $name => $value)
            {
                $attributes[] = self::convertAttribute($name, $value);
            }
        }

        if (isset($segments['pseudo']))
        {
            $expression   = isset($segments['expr']) ? trim($segments['expr']) : '';

            $parameters = explode(',', $expression);

            $attributes[] = self::convertPseudo($segments['pseudo'], $parameters, $tagName);
        }

        if (count($attributes) === 0 and !isset($segments['tag']))
        {
            throw new InvalidArgumentException('Segments should contain the tag name or at least one attribute.');
        }

        $xPath = $prefix . $tagName;

        if ($count = count($attributes))
        {
            $xPath = $xPath . (($count > 1) ? vsprintf('[(%1$s)]', [ implode(') and (', $attributes) ]) : vsprintf('[%1$s]', [ $attributes[0] ]));
        }

        return $xPath;
    }

    protected static function convertAttribute($name, $value)
    {
        $isSimpleSelector = !in_array(substr($name, 0, 1), [ '^', '!' ]);
        $isSimpleSelector = $isSimpleSelector && (!in_array(substr($name, -1), [ '^', '$', '*', '!', '~' ]));

        if ($isSimpleSelector)
        {
            $xPath = ($value === null) ? ('@' . $name) : vsprintf('@%1$s="%2$s"', [ $name, $value ]);

            return $xPath;
        }

        if (substr($name, 0, 1) === '^')
        {
            $xPath = vsprintf('@*[starts-with(name(), "%1$s")]', [ substr($name, 1) ]);

            return ($value === null) ? $xPath : vsprintf('%1$s="%2$s"', [ $xPath, $value ]);
        }

        if (substr($name, 0, 1) === '!')
        {
            $xPath = vsprintf('not(@%1$s)', [ substr($name, 1) ]);

            return $xPath;
        }

        switch (substr($name, -1))
        {
            case '^':
                $xPath = vsprintf('starts-with(@%1$s, "%2$s")', [ substr($name, 0, -1), $value ]);
                break;
            case '$':
                $xPath = vsprintf('ends-with(@%1$s, "%2$s")', [ substr($name, 0, -1), $value ]);
                break;
            case '*':
                $xPath = vsprintf('contains(@%1$s, "%2$s")', [ substr($name, 0, -1), $value ]);
                break;
            case '!':
                $xPath = vsprintf('not(@%1$s="%2$s")', [ substr($name, 0, -1), $value ]);
                break;
            case '~':
                $xPath = vsprintf('contains(concat(" ", normalize-space(@%1$s), " "), " %2$s ")', [ substr($name, 0, -1), $value ]);
                break;
        }

        return $xPath;
    }

    protected static function convertPseudo($pseudo, $parameters = [], &$tagName)
    {
        switch ($pseudo)
        {
            case 'first-child':
                return 'position() = 1';
                break;
            case 'last-child':
                return 'position() = last()';
                break;
            case 'nth-child':
                $xPath = vsprintf('(name()="%1$s") and (%2$s)', [ $tagName, self::convertNthExpression($parameters[0]) ]);
                $tagName = '*';

                return $xPath;
                break;
            case 'contains':
                $string = trim($parameters[0], ' \'"');
                $caseSensetive = isset($parameters[1]) && (trim($parameters[1]) === 'true');

                return self::convertContains($string, $caseSensetive);
                break;
            case 'has':
                return self::cssToXPath($parameters[0], './/');
                break;
            case 'not':
                return vsprintf('not(self::%1$s)', [ self::cssToXPath($parameters[0], '') ]);
                break;
            case 'nth-of-type':
                return self::convertNthExpression($parameters[0]);
                break;
            case 'empty':
                return 'count(descendant::*) = 0';
                break;
            case 'not-empty':
                return 'count(descendant::*) > 0';
                break;
        }

        throw new RuntimeException(vsprintf('Invalid selector: Unknown pseudo-class "%1$s"', [ $pseudo ]));
    }

    protected static function convertNthExpression($expression)
    {
        if ($expression === '')
        {
            throw new RuntimeException('Invalid selector: nth-child (or nth-last-child) expression must not be empty.');
        }

        if ($expression === 'odd')
        {
            return 'position() mod 2 = 1 and position() >= 1';
        }

        if ($expression === 'even')
        {
            return 'position() mod 2 = 0 and position() >= 0';
        }

        if (is_numeric($expression))
        {
            return vsprintf('position() = %1$d', [ $expression ]);
        }

        if (preg_match("/^(?P<mul>[0-9]?n)(?:(?P<sign>\+|\-)(?P<pos>[0-9]+))?$/is", $expression, $segments))
        {
            if (isset($segments['mul']))
            {
                $multiplier = $segments['mul'] === 'n' ? 1 : trim($segments['mul'], 'n');
                $sign = (isset($segments['sign']) && $segments['sign'] === '+') ? '-' : '+';
                $position = isset($segments['pos']) ? $segments['pos'] : 0;

                return vsprintf('(position() %1$s %2$d) mod %3$d = 0 and position() >= %4$d', [ $sign, $position, $multiplier, $position ]);
            }
        }

        throw new RuntimeException('Invalid selector: Invalid nth-child expression.');
    }

    protected static function convertContains($string, $caseSensetive = false)
    {
        if ($caseSensetive)
        {
            return vsprintf('text() = "%1$s"', [ $string ]);
        }

        if (function_exists('mb_strtolower'))
        {
            return vsprintf('php:functionString("mb_strtolower", .) = php:functionString("mb_strtolower", "%1$s")', [ $string ]);
        }
        else
        {
            return vsprintf('php:functionString("strtolower", .) = php:functionString("strtolower", "%1$s")', [ $string ]);
        }
    }

    public static function getSegments($selector)
    {
        $selector = trim($selector);

        if ($selector === '')
        {
            throw new InvalidArgumentException('The selector must not be empty.');
        }

        $tagPattern = '(?P<tag>[\*|\w|\-]+)?';
        $idPattern = '(?:#(?P<id>[\w|\-]+))?';
        $classesPattern = '(?P<classes>\.[\w|\-|\.]+)*';
        $attrsPattern = '(?P<attrs>(?:\[.+?\])*)?';
        $pseudoNamePattern = '(?P<pseudo>[\w\-]+)';
        $pseudoExprPattern = '(?:\((?P<expr>[^\)]+)\))';
        $pseudoPattern = '(?::' . $pseudoNamePattern . $pseudoExprPattern . '?)?';
        $relPattern = '\s*(?P<rel>>)?';

        $regexp = '/' . $tagPattern . $idPattern . $classesPattern . $attrsPattern . $pseudoPattern . $relPattern . '/is';

        if (preg_match($regexp, $selector, $segments))
        {
            if ($segments[0] === '')
            {
                throw new RuntimeException('Invalid selector.');
            }

            $result['selector'] = $segments[0];

            if (isset($segments['tag']) && $segments['tag'] !== '')
            {
                $result['tag'] = $segments['tag'];
            }

            if (isset($segments['id']) && $segments['id'] !== '')
            {
                $result['id'] = $segments['id'];
            }

            if (isset($segments['attrs']))
            {
                $attributes = trim($segments['attrs'], '[]');
                $attributes = explode('][', $attributes);

                foreach ($attributes as $attribute)
                {
                    if ($attribute !== '')
                    {
                        list($name, $value) = array_pad(explode('=', $attribute, 2), 2, null);

                        if ($name === '')
                        {
                            throw new RuntimeException('Invalid selector: Attribute name must not be empty.');
                        }

                        $result['attributes'][$name] = is_string($value) ? trim($value, '\'"') : null;
                    }
                }
            }

            if (isset($segments['classes']))
            {
                $classes = trim($segments['classes'], '.');
                $classes = explode('.', $classes);

                foreach ($classes as $class)
                {
                    if ($class !== '')
                    {
                        $result['classes'][] = $class;
                    }
                }
            }

            if (isset($segments['pseudo']) && $segments['pseudo'] !== '')
            {
                $result['pseudo'] = $segments['pseudo'];

                if (isset($segments['expr']) && $segments['expr'] !== '')
                {
                    $result['expr'] = $segments['expr'];
                }
            }

            if (isset($segments['rel']))
            {
                $result['rel'] = $segments['rel'];
            }

            return $result;
        }

        throw new RuntimeException('Invalid selector.');
    }

    public static function getCompiled()
    {
        return static::$compiled;
    }

    public static function setCompiled($compiled)
    {
        if (!is_array($compiled))
        {
            throw new InvalidArgumentException(vsprintf('%1$s expects the 1st parameter to be array, %2$s given.', [ __METHOD__, gettype($compiled) ]));
        }

        static::$compiled = $compiled;
    }
}
