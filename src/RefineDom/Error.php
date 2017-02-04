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

        if (self::useInternalErrors !== null)
        {
            libxml_use_internal_errors(self::useInternalErrors);
        }

        if (self::disableEntityLoader !== null)
        {
            libxml_disable_entity_loader(self::disableEntityLoader);
        }
    }

    public static function disable()
    {
        self::useInternalErrors = libxml_use_internal_errors(true);
        self::disableEntityLoader = libxml_disable_entity_loader(true);
    }
}
