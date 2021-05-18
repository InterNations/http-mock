<?php
namespace InterNations\Component\HttpMock\Server;

use Symfony\Component\HttpFoundation\Request;

interface RequestStorage
{
    /** @param list<ServerExpectation> $expectations */
    public function storeExpectations(array $expectations): void;
    /** @return array<ServerExpectation> */
    public function readExpectations(): array;
    public function prependExpectation(ServerExpectation $expectation): void;

    /** @param list<Request> $requests */
    public function storeRequests(array $requests): void;
    /** @return array<Request> */
    public function readRequests(): array;
    public function appendRequest(Request $request): void;
}
