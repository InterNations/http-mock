<?php
namespace InterNations\Component\HttpMock\Response;

use Symfony\Component\HttpFoundation\Response;

class CallbackResponse extends Response
{
    private $callback;

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    public function sendCallback(): void
    {
        if ($this->callback) {
            $callback = $this->callback;
            $callback($this);
        }
    }

    public function send(): void
    {
        $this->sendCallback();
        parent::send();
    }
}
