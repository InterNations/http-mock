<?php
namespace InterNations\Component\HttpMock\Tests\Matcher;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\Testing\AbstractTestCase;
use Symfony\Component\HttpFoundation\Request;

class ExtractorFactoryTest extends AbstractTestCase
{
    private ExtractorFactory $extractorFactory;

    /** @var Request|MockObject */
    private $request;

    public function setUp(): void
    {
        $this->extractorFactory = new ExtractorFactory();
        $this->request = $this->createMock('Symfony\Component\HttpFoundation\Request');
    }

    public function testGetMethod(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->will($this->returnValue('POST'));

        $extractor = $this->extractorFactory->createMethodExtractor();
        $this->assertSame('POST', $extractor($this->request));
    }

    public function testGetPath(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getPathInfo')
            ->will($this->returnValue('/foo/bar'));

        $extractor = $this->extractorFactory->createPathExtractor();
        $this->assertSame('/foo/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePath(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getPathInfo')
            ->will($this->returnValue('/foo/bar'));

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathTrailingSlash(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getPathInfo')
            ->will($this->returnValue('/foo/bar'));

        $extractorFactory = new ExtractorFactory('/foo/');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathThatDoesNotMatch(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getPathInfo')
            ->will($this->returnValue('/bar'));

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('', $extractor($this->request));
    }

    public function testGetHeaderWithExistingHeader(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_CONTENT_TYPE' => 'application/json']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertSame('application/json', $extractor($request));
    }

    public function testGetHeaderWithNonExistingHeader(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_X_FOO' => 'bar']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertNull($extractor($request));
    }

    public function testHeaderExistsWithExistingHeader(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_CONTENT_TYPE' => 'application/json']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertTrue($extractor($request));
    }

    public function testHeaderExistsWithNonExistingHeader(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_X_FOO' => 'bar']
        );

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertFalse($extractor($request));
    }
}
