<?php
namespace InterNations\Eos\FakeApi;

use Exception;
use Guzzle\Http\Client;
use Silex\Application;
use SuperClosure\SuperClosure;
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

    if ($request->request->has('matcher')) {
        $matcher = @unserialize($request->request->get('matcher'));
        $validator = static function ($closure) {
            return is_callable($closure);
        };
        if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
            return new Response('POST data key "matcher" must be a serialized list of closures', 417);
        }
    } else {
        $matcher = [];
    }

    if (!$request->request->has('response')) {
        return new Response('POST data key "response" not found in POST data', 417);
    }

    $response = @unserialize($request->request->get('response'));
    if (!$response instanceof Response) {
        return new Response('POST data key "response" must be a serialized Symfony response', 417);
    }

    append($request, 'expectations', ['matcher' => $matcher, 'response' => $response]);

    return new Response('', 201);
});

$app->error(function (Exception $e) use ($app) {
    /** @var Request $request */
    $request = $app['request'];

    if ($e instanceof NotFoundHttpException) {
        append($request, 'requests', (string) $request);

        $expectations = read($request, 'expectations');
        foreach ($expectations as $expectation) {
            foreach ($expectation['matcher'] as $pos => $matcher) {
                if (!$matcher($request)) {
                    break 2;
                }
            }

            return $expectation['response'];
        }

        return new Response('No matching expectation found', 404);
    }

    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    return new Response('Server error: ' . $e->getMessage(), $code);
});

$app->get('/_request/latest', function (Request $request) {
    $requests = read($request, 'requests');
    $requestData = end($requests);

    if ($request === null) {
        return new Response('No request recorded', 404);
    }

    return new Response($requestData, 200, ['Content-Type' => 'text/plain']);
});

$app->get('/_request/{index}', function (Request $request, $index) {
    $requestData = read($request, 'requests');
    if (!isset($requestData[$index])) {
        return new Response('Index ' . $index . ' not found', 404);
    }

    return new Response($requestData[$index], 200, ['Content-Type' => 'text/plain']);
})->assert('index', '\d+');

$app->delete('/_request', function (Request $request) {
    store($request, 'requests', []);

    return new Response('', 200);
});

$app->delete('/_all', function (Request $request) {
    store($request, 'requests', []);
    store($request, 'expectations', []);

    return new Response('', 200);
});

$app->get('/_me', function () {
    return new Response('O RLY?', 418, ['Content-Type' => 'text/plain']);
});

return $app;
