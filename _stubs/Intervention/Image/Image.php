<?php

namespace Intervention\Image;

class Image
{
    public function width() { return 0; }
    public function resize($w, $h = null, $cb = null) { return $this; }
    public function upsize() { return $this; }
    public function encode($format = null, $quality = 90) { return $this; }
}
