<?php

namespace Intervention\Image;

class ImageManagerStatic
{
    /**
     * Create image instance from path|resource|string
     * @param mixed $arg
     * @return \Intervention\Image\Image
     */
    public static function make($arg)
    {
        return new Image();
    }
}
