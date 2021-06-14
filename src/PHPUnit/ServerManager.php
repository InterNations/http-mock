<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use InterNations\Component\HttpMock\ServerProcess;
use SplObjectStorage;

// @codingStandardsIgnoreStart
final class ServerManager
// @codingStandardsIgnoreEnd
{
    /** @var SplObjectStorage|iterable<ServerProcess> */
    private SplObjectStorage $servers;

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ?: (self::$instance = new self());
    }

    public function add(ServerProcess $server): void
    {
        $this->servers->attach($server);
    }

    public function remove(ServerProcess $server): void
    {
        $this->servers->detach($server);
    }

    public function cleanup(): void
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
