<?php

namespace InterNations\Component\HttpMock\Matcher;

use Psr\Http\Message\RequestInterface as Request;

class ExtractorFactory
{
    private $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function createPathExtractor()
    {
        $basePath = $this->basePath;

        return static function (Request $request) use ($basePath) {
            return substr_replace($request->getUri()->getPath(), '', 0, strlen($basePath));
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
            return $request->getParam($param);
        };
    }

    public function createParamExistsExtractor($param)
    {
        return static function (Request $request) use ($param) {
            return $request->getParam($param, false) !== false;
        };
    }

    public function createHeaderExtractor($header)
    {
        return static function (Request $request) use ($header) {
            $r = $request->getHeaderLine($header);
            if (empty($r)) {
                return null;
            }

            return $r;
        };
    }

    public function createHeaderExistsExtractor($header)
    {
        return static function (Request $request) use ($header) {
            return $request->hasHeader($header);
        };
    }
}
