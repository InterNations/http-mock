<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Symfony\Component\HttpFoundation\Request;

class ExtractorFactory
{
    private $basePath;

    public function __construct($basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /** @return Closure */
    public function createPathExtractor()
    {
        $basePath = $this->basePath;

        return static function (Request $request) use ($basePath) {
            return substr_replace($request->getRequestUri(), '', 0, strlen($basePath));
        };
    }

    /** @return Closure */
    public function createMethodExtractor()
    {
        return static function (Request $request) {
            return $request->getMethod();
        };
    }
}
