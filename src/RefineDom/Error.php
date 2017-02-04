<?php

namespace RefineDom;

class Error
{
    protected static $useInternalErrors = null;
    protected static $disableEntityLoader = null;

    public static function enable($clearErrors = true)
    {
        if ($clearErrors)
        {
            libxml_clear_errors();
        }

        if (static::useInternalErrors !== null)
        {
            libxml_use_internal_errors(static::useInternalErrors);
        }

        if (static::disableEntityLoader !== null)
        {
            libxml_disable_entity_loader(static::disableEntityLoader);
        }
    }

    public static function disable()
    {
        static::useInternalErrors = libxml_use_internal_errors(true);
        static::disableEntityLoader = libxml_disable_entity_loader(true);
    }
}
