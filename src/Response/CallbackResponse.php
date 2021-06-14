<?php
namespace InterNations\Component\HttpMock\Response;

use Symfony\Component\HttpFoundation\Response;

class CallbackResponse extends Response
{
    /** @var callable */
    private $callback;

    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    public function sendCallback(): void
    {
        if (!$this->callback) {
            return;
        }

        $callback = $this->callback;
        $callback($this);
    }

    public function send(): void
    {
        $this->sendCallback();
        parent::send();
    }
}
