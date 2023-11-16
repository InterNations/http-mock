<?php

declare(strict_types=1);

namespace InterNations\Component\HttpMock\Matcher;

use Psr\Http\Message\RequestInterface as Request;

class ExtractorFactory
{
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function createPathExtractor() : callable
    {
        $basePath = $this->basePath;

        return static function (Request $request) use ($basePath) {
            return substr_replace($request->getUri()->getPath(), '', 0, strlen($basePath));
        };
    }

    public function createMethodExtractor() : callable
    {
        return static function (Request $request) {
            return $request->getMethod();
        };
    }

    public function createParamExtractor($param) : callable
    {
        return static function (Request $request) use ($param) {
            $query = [];

            parse_str($request->getUri()->getQuery(), $query);

            return $query[$param] ?? null;
        };
    }

    public function createParamExistsExtractor(string $param) : callable
    {
        return static function (Request $request) use ($param) {
            $query = [];

            parse_str($request->getUri()->getQuery(), $query);

            return isset($query[$param]);
        };
    }

    public function createHeaderExtractor($header) : callable
    {
        return static function (Request $request) use ($header) {
            $r = $request->getHeaderLine($header);
            if (empty($r)) {
                return null;
            }

            return $r;
        };
    }

    public function createHeaderExistsExtractor($header) : callable
    {
        return static function (Request $request) use ($header) {
            return $request->hasHeader($header);
        };
    }
}
