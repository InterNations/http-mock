<?php

namespace InterNations\Component\HttpMock\PHPUnit;

use InterNations\Component\HttpMock\Server;
use SplObjectStorage;

final class ServerManager
{
    /** @var SplObjectStorage|Server[] */
    private mixed $servers;

    private static ?ServerManager $instance = null;

    public static function getInstance() : self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function add(Server $server) : void
    {
        $this->servers->attach($server);
    }

    public function remove(Server $server) : void
    {
        $this->servers->detach($server);
    }

    public function cleanup() : void
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

    private function __clone() : void
    {
    }
}
