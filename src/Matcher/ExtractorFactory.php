<?php
namespace InterNations\Component\HttpMock\Matcher;

use Symfony\Component\HttpFoundation\Request;

class ExtractorFactory
{
    private $basePath;

    public function __construct($basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function createPathExtractor()
    {
        $basePath = $this->basePath;

        return static function (Request $request) use ($basePath) {
            return substr_replace($request->getRequestUri(), '', 0, strlen($basePath));
        };
    }

    public function createMethodExtractor()
    {
        return static function (Request $request) {
            return $request->getMethod();
        };
    }

    public function createParamExtractor($param)
    {
        return static function (Request $request) use ($param) {
            return $request->query->get($param);
        };
    }

    public function createParamExistsExtractor($param)
    {
        return static function (Request $request) use ($param) {
            return $request->query->has($param);
        };
    }

    public function createHeaderExtractor($header)
    {
        return static function (Request $request) use ($header) {
            return $request->headers->get($header);
        };
    }

    public function createHeaderExistsExtractor($header)
    {
        return static function (Request $request) use ($header) {
            return $request->headers->has($header);
        };
    }
}
