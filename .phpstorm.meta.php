<?php

namespace PHPSTORM_META
{
    /**
     * @link https://confluence.jetbrains.com/display/PhpStorm/PhpStorm+Advanced+Metadata
     */
    $STATIC_METHOD_TYPES = array(
        \Piwik\Container\StaticContainer::get('') => [
            "" == "@",
        ],
        \Interop\Container\ContainerInterface::get('') => [
            "" == "@",
        ],
    );
}
