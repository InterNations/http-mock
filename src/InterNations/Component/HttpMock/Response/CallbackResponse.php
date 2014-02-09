<?php
namespace InterNations\Component\HttpMock\Response;

use Jeremeamia\SuperClosure\SerializableClosure;
use Symfony\Component\HttpFoundation\Response;

class CallbackResponse extends Response
{
    /** @var SerializableClosure */
    private $callback;

    public function setCallback(SerializableClosure $callback)
    {
        $this->callback = $callback;
    }

    /** @return SerializableClosure */
    public function getCallback()
    {
        return $this->callback;
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
