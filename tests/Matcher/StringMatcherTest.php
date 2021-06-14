<?php
namespace InterNations\Component\HttpMock\Tests\Matcher;

use InterNations\Component\HttpMock\Matcher\StringMatcher;
use InterNations\Component\HttpMock\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StringMatcherTest extends TestCase
{
    public function testConversionToString(): void
    {
        $matcher = new StringMatcher('0');
        $matcher->setExtractor(static fn () => 0);
        self::assertTrue($matcher->getMatcher()(new Request()));
    }
}
