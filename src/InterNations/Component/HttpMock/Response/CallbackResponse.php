<?php
namespace InterNations\Component\HttpMock\Response;

use Symfony\Component\HttpFoundation\Response;

class CallbackResponse extends Response
{
    private $callback;

    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    public function sendCallback()
    {
        if ($this->callback) {
            $callback = $this->callback;
            $callback($this);
        }
    }

    public function send()
    {
        $this->sendCallback();
        parent::send();
    }
}
