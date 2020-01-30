<?php

namespace InterNations\Component\HttpMock\Tests\Matcher;

use GuzzleHttp\Psr7\Request;
use InterNations\Component\HttpMock\Matcher\StringMatcher;
use InterNations\Component\Testing\AbstractTestCase;

class StringMatcherTest extends AbstractTestCase
{
    public function testConversionToString()
    {
        $matcher = new StringMatcher('0');
        $matcher->setExtractor(static function () {
            return 0;
        });
        self::assertTrue($matcher->getMatcher()(new Request('GET', '/')));
    }
}
