<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Request;

class RequestStorage
{
    private int $pid;

    private string $directory;

    public function __construct(int $pid, string $directory)
    {
        $this->pid = $pid;
        $this->directory = $directory;
    }

    /** @param array<Request>|array<Expectation> $data */
    public function store(Request $request, string $name, array $data): void
    {
        file_put_contents($this->getFileName($request, $name), serialize($data));
    }

    /** @return array<Request>|array<Expectation> */
    public function read(Request $request, string $name): array
    {
        $fileName = $this->getFileName($request, $name);

        if (!file_exists($fileName)) {
            return [];
        }

        return Util::deserialize(file_get_contents($fileName));
    }

    /** @param Request|Expectation $data */
    public function append(Request $request, string $name, $data): void
    {
        $list = $this->read($request, $name);
        $list[] = $data;
        $this->store($request, $name, $list);
    }

    /** @param Request|Expectation $data */
    public function prepend(Request $request, string $name, $data): void
    {
        $list = $this->read($request, $name);
        array_unshift($list, $data);
        $this->store($request, $name, $list);
    }

    private function getFileName(Request $request, string $name): string
    {
        return $this->directory . $this->pid . '-' . $name . '-' . $request->server->get('SERVER_PORT');
    }

    public function clear(Request $request, string $name): void
    {
        $fileName = $this->getFileName($request, $name);

        if (!file_exists($fileName)) {
            return;
        }

        unlink($fileName);
    }
}
