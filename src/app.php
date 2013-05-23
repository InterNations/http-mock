<?php
namespace InterNations\Eos\FakeApi;

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

$app->post('/_expectation', function (Request $request) {
    clear($request, 'expectation');
    clear($request, 'latest');

    store($request, 'expectation', $request->request->all());

    return new Response('', 201);
});



$app->error(function (Exception $e) use ($app) {
    /** @var Request $request */
    $request = $app['request'];

    if ($e instanceof NotFoundHttpException) {
        $expectation = read($request, 'expectation');

        append($request, 'latest', (string) $request);

        $response = new Response();

        if (isset($expectation['sleepMs'])) {
            usleep($expectation['sleepMs'] * 1000);
        }

        if (isset($expectation['body'])) {
            $response->setContent($expectation['body']);
        }

        if (isset($expectation['statusCode'])) {
            $response->setStatusCode($expectation['statusCode']);
        }

        if (isset($expectation['callbackUrlPropertyPath'])) {
            $accessor = PropertyAccess::createPropertyAccessor();
            $callbackUrl = $accessor->getValue(
                ['request' => $request->request->all()],
                $expectation['callbackUrlPropertyPath']
            );

            if (empty($callbackUrl)) {
                throw new RuntimeException(
                    sprintf(
                        'Could not extract property from path "%s"', $expectation['callbackUrlPropertyPath']
                    )
                );
            }

            $client = new Client('http://127.0.0.1:8080');
            $client->get($callbackUrl)->send();
        }

        return $response;
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
