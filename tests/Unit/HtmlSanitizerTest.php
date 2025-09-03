<?php

namespace Tests\Unit;

use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_purifies_script_tags_and_onerror_attributes()
    {
        $settings = config('htmlpurifier.settings');
        $config = \HTMLPurifier_Config::createDefault();
        foreach ($settings as $k => $v) {
            $config->set($k, $v);
        }
        $purifier = new \HTMLPurifier($config);

        $dirty = '<p>hello <img src="x" onerror="alert(1)"> <script>alert(2)</script> <a href="javascript:alert(3)">link</a></p>';
        $clean = $purifier->purify($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('onerror=', $clean);
        $this->assertStringNotContainsString('javascript:alert', $clean);
    }
}
