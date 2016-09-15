<?php
namespace InterNations\Component\HttpMock\Tests\Matcher;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\Testing\AbstractTestCase;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class ExtractorFactoryTest extends AbstractTestCase
{
    /** @var ExtractorFactory */
    private $extractorFactory;

    /** @var Request|MockObject */
    private $request;

    public function setUp()
    {
        $this->extractorFactory = new ExtractorFactory();
        $this->request = $this->createMock('Symfony\Component\HttpFoundation\Request');
    }

    public function testGetMethod()
    {
        $this->request
            ->expects($this->once())
            ->method('getMethod')
            ->will($this->returnValue('POST'));

        $extractor = $this->extractorFactory->createMethodExtractor();
        $this->assertSame('POST', $extractor($this->request));
    }

    public function testGetPath()
    {
        $this->request
            ->expects($this->once())
            ->method('getRequestUri')
            ->will($this->returnValue('/foo/bar'));

        $extractor = $this->extractorFactory->createPathExtractor();
        $this->assertSame('/foo/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePath()
    {
        $this->request
            ->expects($this->once())
            ->method('getRequestUri')
            ->will($this->returnValue('/foo/bar'));

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathTrailingSlash()
    {
        $this->request
            ->expects($this->once())
            ->method('getRequestUri')
            ->will($this->returnValue('/foo/bar'));

        $extractorFactory = new ExtractorFactory('/foo/');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($this->request));
    }

    public function testGetPathWithBasePathThatDoesNotMatch()
    {
        $this->request
            ->expects($this->once())
            ->method('getRequestUri')
            ->will($this->returnValue('/bar'));

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('', $extractor($this->request));
    }
}
