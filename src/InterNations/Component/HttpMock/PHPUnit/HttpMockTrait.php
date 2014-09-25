<?php
namespace InterNations\Component\HttpMock\PHPUnit;

trait HttpMockTrait
{
    /**
     * @var HttpMockFacade
     */
    protected static $staticHttp;

    /**
     * @var HttpMockFacade
     */
    protected $http;

    protected static function setUpHttpMockBeforeClass($port = null, $host = null, $basePath = null)
    {
        $port = $port ?: 28080;
        $host = $host ?: 'localhost';
        static::$staticHttp = new HttpMockFacade($port, $host, $basePath);
        ServerManager::getInstance()->add(static::$staticHttp->server);
    }

    protected function setUpHttpMock()
    {
        static::assertHttpMockSetup();

        $this->http = clone static::$staticHttp;
    }

    protected static function assertHttpMockSetup()
    {
        if (!static::$staticHttp instanceof HttpMockFacade) {
            static::fail(
                sprintf(
                    'Static HTTP mock facade not present. Did you forget to invoke static::setUpHttpMockBeforeClass()'
                    . ' in %s::setUpBeforeClass()?',
                    get_called_class()
                )
            );
        }
    }

    protected function tearDownHttpMock()
    {
        if (!$this->http) {
            return;
        }

        $http = $this->http;
        $this->http = null;
        $this->assertSame(
            '',
            (string) $http->server->getIncrementalErrorOutput(),
            'HTTP mock server standard error output should be empty'
        );
    }

    protected static function tearDownHttpMockAfterClass()
    {
        static::$staticHttp->server->stop();
        ServerManager::getInstance()->remove(static::$staticHttp->server);
    }
}
