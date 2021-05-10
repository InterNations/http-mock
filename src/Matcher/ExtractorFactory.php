<?php
namespace InterNations\Component\HttpMock\Matcher;

use Symfony\Component\HttpFoundation\Request;

final class ExtractorFactory
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function createPathExtractor(): callable
    {
        $basePath = $this->basePath;

        return static function (Request $request) use ($basePath) {
            return substr_replace($request->getPathInfo(), '', 0, strlen($basePath));
        };
    }

    public function createMethodExtractor(): callable
    {
        return static function (Request $request) {
            return $request->getMethod();
        };
    }

    public function createParamExtractor(string $param): callable
    {
        return static function (Request $request) use ($param) {
            return $request->query->get($param);
        };
    }

    public function createParamExistsExtractor(string $param): callable
    {
        return static function (Request $request) use ($param) {
            return $request->query->has($param);
        };
    }

    public function createHeaderExtractor(string $header): callable
    {
        return static function (Request $request) use ($header) {
            return $request->headers->get($header);
        };
    }

    public function createHeaderExistsExtractor(string $header): callable
    {
        return static function (Request $request) use ($header) {
            return $request->headers->has($header);
        };
    }
}
