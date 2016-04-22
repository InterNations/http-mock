<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Request;

class RequestStorage
{
    private $pid;

    private $directory;

    public function __construct($pid, $directory)
    {
        $this->pid = $pid;
        $this->directory = $directory;
    }

    public function store(Request $request, $name, $data)
    {
        file_put_contents($this->getFileName($request, $name), serialize($data));
    }

    public function read(Request $request, $name)
    {
        $fileName = $this->getFileName($request, $name);
        if (!file_exists($fileName)) {
            return [];
        }
        return Util::deserialize(file_get_contents($fileName));
    }

    public function append(Request $request, $name, $data)
    {
        $list = $this->read($request, $name);
        $list[] = $data;
        $this->store($request, $name, $list);
    }

    public function prepend(Request $request, $name, $data)
    {
        $list = $this->read($request, $name);
        array_unshift($list, $data);
        $this->store($request, $name, $list);
    }

    private function getFileName(Request $request, $name)
    {
        return $this->directory . $this->pid . '-' . $name . '-' . $request->server->get('SERVER_PORT');
    }

    public function clear(Request $request, $name)
    {
        $fileName = $this->getFileName($request, $name);

        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }
}
