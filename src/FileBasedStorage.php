<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use function array_unshift;
use function file_put_contents;
use function serialize;

class FileBasedStorage implements RequestStorage
{
    private const NAMESPACE_REQUESTS = 'requests';
    private const NAMESPACE_EXPECTATIONS = 'expectations';

    private int $pid;

    private string $directory;

    private RequestStack $requestStack;

    public function __construct(int $pid, string $directory, RequestStack $requestStack)
    {
        if (!is_dir($directory)) {
            mkdir($directory);
        }

        $this->pid = $pid;
        $this->directory = $directory;
        $this->requestStack = $requestStack;
    }

    /** @param list<Request> $requests */
    public function storeRequests(array $requests): void
    {
        file_put_contents($this->getFileName(self::NAMESPACE_REQUESTS), serialize($requests));
    }

    public function appendRequest(Request $request): void
    {
        $list = $this->read(self::NAMESPACE_REQUESTS);
        $list[] = $request;
        $this->store(self::NAMESPACE_REQUESTS, $list);
    }

    /** @return list<ServerExpectation> */
    public function readExpectations(): array
    {
        return $this->read(self::NAMESPACE_EXPECTATIONS);
    }

    /** @return list<Request> */
    public function readRequests(): array
    {
        return $this->read(self::NAMESPACE_REQUESTS);
    }

    /** @param list<ServerExpectation> $expectations */
    public function storeExpectations(array $expectations): void
    {
        $this->store(self::NAMESPACE_EXPECTATIONS, $expectations);
    }

    public function prependExpectation(ServerExpectation $expectation): void
    {
        $list = $this->read(self::NAMESPACE_EXPECTATIONS);
        array_unshift($list, $expectation);
        $this->store(self::NAMESPACE_EXPECTATIONS, $list);
    }

    /** @param array<Request>|array<Expectation> $data */
    private function store(string $name, array $data): void
    {
        file_put_contents($this->getFileName($name), serialize($data));
    }

    /** @return array<Request>|array<Expectation> */
    private function read(string $name): array
    {
        $fileName = $this->getFileName($name);

        if (!file_exists($fileName)) {
            return [];
        }

        return Util::deserialize(file_get_contents($fileName));
    }

    private function getFileName(string $namespace): string
    {
        return sprintf(
            '%s/%d-%s-%d.data',
            $this->directory,
            $this->pid,
            $namespace,
            $this->requestStack->getMasterRequest()->server->get('SERVER_PORT')
        );
    }
}
