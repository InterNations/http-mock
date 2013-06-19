<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use InterNations\Component\HttpMock\Server;
use SplObjectStorage;

// @codingStandardsIgnoreStart
final class ServerManager
// @codingStandardsIgnoreEnd
{
    /** @var SplObjectStorage|Server[] */
    private $servers;

    private static $instance;

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function add(Server $server)
    {
        $this->servers->attach($server);
    }

    public function remove(Server $server)
    {
        $this->servers->detach($server);
    }

    public function cleanup()
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }
    }

    private function __construct()
    {
        $this->servers = new SplObjectStorage();
        register_shutdown_function([$this, 'cleanup']);
    }

    private function __clone()
    {
    }
}
