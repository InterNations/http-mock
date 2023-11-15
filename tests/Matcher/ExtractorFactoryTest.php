<?php

namespace InterNations\Component\HttpMock\Tests\Matcher;

use GuzzleHttp\Psr7\Request;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ExtractorFactoryTest extends AbstractTestCase
{
    /** @var ExtractorFactory */
    private $extractorFactory;

    /** @var Request|MockObject */
    private $request;

    public function setUp() : void
    {
        $this->extractorFactory = new ExtractorFactory();
    }

    public function testGetMethod()
    {
        $request = new Request(
            'POST',
            '/'
        );

        $extractor = $this->extractorFactory->createMethodExtractor();
        $this->assertSame('POST', $extractor($request));
    }

    public function testGetPath()
    {
        $request = new Request(
            'GET',
            '/foo/bar'
        );

        $extractor = $this->extractorFactory->createPathExtractor();
        $this->assertSame('/foo/bar', $extractor($request));
    }

    public function testGetPathWithBasePath()
    {
        $request = new Request(
            'GET',
            '/foo/bar'
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($request));
    }

    public function testGetPathWithBasePathTrailingSlash()
    {
        $request = new Request(
            'GET',
            '/foo/bar'
        );

        $extractorFactory = new ExtractorFactory('/foo/');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($request));
    }

    public function testGetPathWithBasePathThatDoesNotMatch()
    {
        $request = new Request(
            'GET',
            '/bar'
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('', $extractor($request));
    }

    public function testGetHeaderWithExistingHeader()
    {
        $request = new Request(
            'GET',
            '/',
            ['Content-Type' => 'application/json']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertSame('application/json', $extractor($request));
    }

    public function testGetHeaderWithNonExistingHeader()
    {
        $request = new Request(
            'GET',
            '/',
            ['X-Foo' => 'bar']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertSame(null, $extractor($request));
    }

    public function testHeaderExistsWithExistingHeader()
    {
        $request = new Request(
            'GET',
            '/',
            ['Content-Type' => 'application/json']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertTrue($extractor($request));
    }

    public function testHeaderExistsWithNonExistingHeader()
    {
        $request = new Request(
            'GET',
            '/',
            ['X-Foo' => 'bar']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertFalse($extractor($request));
    }
}
