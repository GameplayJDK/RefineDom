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

        if ($this->useInternalErrors === null)
        {
            libxml_use_internal_errors($this->useInternalErrors);
        }

        if ($this->disableEntityLoader === null)
        {
            libxml_disable_entity_loader($this->disableEntityLoader);
        }
    }

    public static function disable()
    {
        $this->useInternalErrors = libxml_use_internal_errors(true);
        $this->disableEntityLoader = libxml_disable_entity_loader(true);
    }
}
