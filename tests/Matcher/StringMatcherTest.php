<?php
namespace Pagely\Component\HttpMock\Tests\Matcher;

use Pagely\Component\HttpMock\Matcher\StringMatcher;
use InterNations\Component\Testing\AbstractTestCase;
use GuzzleHttp\Psr7\Request;

class StringMatcherTest extends AbstractTestCase
{
    public function testConversionToString()
    {
        $matcher = new StringMatcher('0');
        $matcher->setExtractor(static function() {
           return 0;
        });
        self::assertTrue($matcher->getMatcher()(new Request('GET', '/')));
    }
}
