<?php
namespace InterNations\Eos\FakeApi;

use Guzzle\Http\Message\RequestFactory;
use RuntimeException;
use Exception;
use Guzzle\Http\Client;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;

require_once __DIR__ . '/../vendor/autoload.php';

function store(Request $request, $name, $data) {
    file_put_contents(storage_file_name($request, $name), serialize($data));
}

function read(Request $request, $name) {
    $fileName = storage_file_name($request, $name);
    if (!file_exists($fileName)) {
        return [];
    }
    return unserialize(file_get_contents($fileName));
}

function append(Request $request, $name, $data) {
    $list = read($request, $name);
    $list[] = $data;
    store($request, $name, $list);
}

function storage_file_name(Request $request, $name) {
    return __DIR__ . '/../state/' . $name . '-' . $request->server->get('SERVER_PORT');
}

function clear(Request $request, $name) {
    $fileName = storage_file_name($request, $name);

    if (file_exists($fileName)) {
        unlink($fileName);
    }
}

$app = new Application();
$app['debug'] = true;

$app->delete('/_expectation', function (Request $request) {
    clear($request, 'expectations');

    return new Response('', 200);
});

$app->post('/_expectation', function (Request $request) {

    append($request, 'expectations', $request->request->all());

    return new Response('', 201);
});

$app->error(function (Exception $e) use ($app) {
    /** @var Request $request */
    $request = $app['request'];

    if ($e instanceof NotFoundHttpException) {

        append($request, 'latest', (string) $request);

        $expectations = read($request, 'expectations');

        $guzzleRequest = RequestFactory::getInstance()->fromMessage((string) $request);

        foreach ($expectations as $expectation) {
            foreach (unserialize($expectation['matchers']) as $matcher) {
                if (!$matcher($guzzleRequest)) {
                    break 2;
                }
            }

            return unserialize($expectation['response']);
        }

        return new Response('No expectation found', 404);
    }

    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    return new Response('Server error: ' . $e->getMessage(), $code);
});

$app->get('/_request/latest', function (Request $request) {
    $requestData = end(read($request, 'latest'));

    if ($request === null) {
        return new Response('No request recorded', 404);
    }

    return new Response($requestData, 200, ['Content-Type' => 'text/plain']);
});

$app->get('/_request/{index}', function (Request $request, $index) {
    $requestData = read($request, 'latest');
    if (!isset($requestData[$index])) {
        return new Response('Index ' . $index . ' not found', 404);
    }

    return new Response($requestData[$index], 200, ['Content-Type' => 'text/plain']);
})->assert('index', '\d+');

return $app;
