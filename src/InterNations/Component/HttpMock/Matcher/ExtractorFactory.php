<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Symfony\Component\HttpFoundation\Request;

class ExtractorFactory
{
    private $basePath = '';

    public function __construct($basePath = '')
    {
        $this->basePath = $basePath;
    }

    /** @return Closure */
    public function createPathExtractor()
    {
        $basePath = $this->basePath ?: '';
        return static function (Request $request) use ($basePath) {

            $length = strlen($basePath);
            $uri = $request->getRequestUri();
            if (substr($uri, 0, $length) === $basePath) {

                return substr($uri, $length);
            }

            return '';
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
