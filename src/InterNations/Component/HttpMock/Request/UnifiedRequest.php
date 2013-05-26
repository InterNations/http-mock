<?php
namespace InterNations\Component\HttpMock\Request;

use BadMethodCallException;
use Guzzle\Common\Collection;
use Guzzle\Http\EntityBodyInterface;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\QueryString;
use Guzzle\Http\Url;

class UnifiedRequest
{
    /**
     * @var RequestInterface
     */
    private $wrapped;

    /**
     * @var string
     */
    private $userAgent;

    public function __construct(RequestInterface $wrapped, array $params = [])
    {
        $this->wrapped = $wrapped;
        $this->init($params);
    }

    /**
     * Get the user agent of the request
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Get the body of the request if set
     *
     * @return EntityBodyInterface|null
     */
    public function getBody()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    /**
     * Get a POST field from the request
     *
     * @param string $field Field to retrieve
     *
     * @return mixed|null
     */
    public function getPostField($field)
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    /**
     * Get the post fields that will be used in the request
     *
     * @return QueryString
     */
    public function getPostFields()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    /**
     * Returns an associative array of POST field names to PostFileInterface objects
     *
     * @return array
     */
    public function getPostFiles()
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    /**
     * Get a POST file from the request
     *
     * @param string $fieldName POST fields to retrieve
     *
     * @return array|null Returns an array wrapping an array of PostFileInterface objects
     */
    public function getPostFile($fieldName)
    {
        return $this->invokeWrappedIfEntityEnclosed(__FUNCTION__, func_get_args());
    }

    /**
     * Get application and plugin specific parameters set on the message.
     *
     * @return Collection
     */
    public function getParams()
    {
        return $this->wrapped->getParams();
    }

    /**
     * Retrieve an HTTP header by name. Performs a case-insensitive search of all headers.
     *
     * @param string $header Header to retrieve.
     * @param boolean $string Set to true to get the header as a string
     *
     * @return string|Header|null Returns NULL if no matching header is found. Returns a string if $string is set to
     *                            TRUE. Returns a Header object if a matching header is found.
     */
    public function getHeader($header, $string = false)
    {
        return $this->wrapped->getHeader($header, $string);
    }

    /**
     * Get a tokenized header as a Collection
     *
     * @param string $header Header to retrieve
     * @param string $token  Token separator
     *
     * @return Collection|null
     */
    public function getTokenizedHeader($header, $token = ';')
    {
        return $this->wrapped->getTokenizedHeader($header, $token);
    }

    /**
     * Get all headers as a collection
     *
     * @param boolean $asObjects Set to true to retrieve a collection of Header objects
     *
     * @return Collection Returns a {@see Collection} of all headers
     */
    public function getHeaders($asObjects = false)
    {
        return $this->wrapped->getHeaders($asObjects);
    }

    /**
     * Get an array of message header lines
     *
     * @return array
     */
    public function getHeaderLines()
    {
        return $this->wrapped->getHeaderLines();
    }

    /**
     * Check if the specified header is present.
     *
     * @param string $header The header to check.
     *
     * @return boolean Returns TRUE or FALSE if the header is present
     */
    public function hasHeader($header)
    {
        return $this->wrapped->hasHeader($header);
    }

    /**
     * Get the raw message headers as a string
     *
     * @return string
     */
    public function getRawHeaders()
    {
        return $this->wrapped->getRawHeaders();
    }

    /**
     * Get a Cache-Control directive from the message
     *
     * @param string $directive Directive to retrieve
     *
     * @return null|string
     */
    public function getCacheControlDirective($directive)
    {
        return $this->wrapped->getCacheControlDirective($directive);
    }

    /**
     * Check if the message has a Cache-Control directive
     *
     * @param string $directive Directive to check
     *
     * @return boolean
     */
    public function hasCacheControlDirective($directive)
    {
        return $this->wrapped->hasCacheControlDirective($directive);
    }

    /**
     * Get the collection of key value pairs that will be used as the query
     * string in the request
     *
     * @return QueryString
     */
    public function getQuery()
    {
        return $this->wrapped->getQuery();
    }

    /**
     * Get the HTTP method of the request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->wrapped->getMethod();
    }

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->wrapped->getScheme();
    }

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost()
    {
        return $this->wrapped->getHost();
    }

    /**
     * Get the HTTP protocol version of the request
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->wrapped->getProtocolVersion();
    }

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath()
    {
        return $this->wrapped->getPath();
    }

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return integer|null
     */
    public function getPort()
    {
        return $this->wrapped->getPort();
    }

    /**
     * Get the username to pass in the URL if set
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->wrapped->getUsername();
    }

    /**
     * Get the password to pass in the URL if set
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->wrapped->getPassword();
    }

    /**
     * Get the full URL of the request (e.g. 'http://www.guzzle-project.com/')
     * scheme://username:password@domain:port/path?query_string#fragment
     *
     * @param boolean $asObject Set to TRUE to retrieve the URL as a clone of the URL object owned by the request.
     *
     * @return string|Url
     */
    public function getUrl($asObject = false)
    {
        return $this->wrapped->getUrl($asObject);
    }

    /**
     * Get an array of Cookies
     *
     * @return array
     */
    public function getCookies()
    {
        return $this->wrapped->getCookies();
    }

    /**
     * Get a cookie value by name
     *
     * @param string $name Cookie to retrieve
     *
     * @return null|string
     */
    public function getCookie($name)
    {
        return $this->wrapped->getCookie($name);
    }

    /**
     * Check whether or not the request is a request that resulted from a redirect
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return $this->wrapped->isRedirect();
    }

    protected function invokeWrappedIfEntityEnclosed($method, array $params = [])
    {
        if (!$this->wrapped instanceof EntityEnclosingRequestInterface) {
            throw new BadMethodCallException(
                sprintf(
                    'Cannot call method "%s" on a request that does not enclose an entity.'
                    . ' Did you expect a POST/PUT request instead of %s %s?',
                    $method,
                    $this->wrapped->getMethod(),
                    $this->wrapped->getPath()
                )
            );
        }

        return call_user_func_array([$this->wrapped, $method], $params);
    }

    private function init(array $params)
    {
        foreach ($params as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }
}
