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
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn('POST');

        $extractor = $this->extractorFactory->createMethodExtractor();
        self::assertSame('POST', $extractor($this->request));
    }

    public function testGetPath(): void
    {
        $this->request
            ->expects(self::once())
            ->method('getPathInfo')
            ->willReturn('/foo/bar');

        $extractor = $this->extractorFactory->createPathExtractor();
        self::assertSame('/foo/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePath(): void
    {
        $this->request
            ->expects(self::once())
            ->method('getPathInfo')
            ->willReturn('/foo/bar');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        self::assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathTrailingSlash(): void
    {
        $this->request
            ->expects(self::once())
            ->method('getPathInfo')
            ->willReturn('/foo/bar');

        $extractorFactory = new ExtractorFactory('/foo/');

        $extractor = $extractorFactory->createPathExtractor();
        self::assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathThatDoesNotMatch(): void
    {
        $this->request
            ->expects(self::once())
            ->method('getPathInfo')
            ->willReturn('/bar');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        self::assertSame('', $extractor($this->request));
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
        self::assertSame('application/json', $extractor($request));
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
        self::assertNull($extractor($request));
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
        self::assertTrue($extractor($request));
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
        self::assertFalse($extractor($request));
    }
}
