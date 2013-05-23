<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\Process\Process;

class Server extends Process
{
    public function __construct($port = 28080)
    {
        parent::__construct(sprintf('php -S localhost:%d public/index.php public', $port), __DIR__ . '/../../../../');
    }

    public function start($callback = null)
    {
        parent::start($callback);
        sleep(2);
    }
}
