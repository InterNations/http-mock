<?php // @codingStandardsIgnoreStart
// @codingStandardsIgnoreEnd
namespace InterNations\Component\HttpMock;

use Exception;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$autoloadFiles = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../../../../../../autoload.php',
];
$autoloaderFound = false;
foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaderFound = true;
        break;
    }
}
if (!$autoloaderFound) {
    throw new Exception(
        sprintf('Could not locate autoloader file. Tried "%s"', join($autoloadFiles, '", "'))
    );
}

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

function prepend(Request $request, $name, $data) {
    $list = read($request, $name);
    array_unshift($list, $data);
    store($request, $name, $list);
}

function silent_deserialize($serialized) {
    // @codingStandardsIgnoreStart
    return @unserialize($serialized);
    // @codingStandardsIgnoreEnd
}

function storage_file_name(Request $request, $name) {
    return __DIR__ . '/../../../../state/' . $name . '-' . $request->server->get('SERVER_PORT');
}

function clear(Request $request, $name) {
    $fileName = storage_file_name($request, $name);

    if (file_exists($fileName)) {
        unlink($fileName);
    }
}

$app = new Application();
$app['debug'] = true;

$app->delete(
    '/_expectation',
    static function (Request $request) {
        clear($request, 'expectations');

        return new Response('', 200);
    }
);

$app->post(
    '/_expectation',
    static function (Request $request) {

        $matcher = [];
        if ($request->request->has('matcher')) {
            $matcher = silent_deserialize($request->request->get('matcher'));
            $validator = static function ($closure) {
                return is_callable($closure);
            };
            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                return new Response('POST data key "matcher" must be a serialized list of closures', 417);
            }
        }

        if (!$request->request->has('response')) {
            return new Response('POST data key "response" not found in POST data', 417);
        }

        $response = silent_deserialize($request->request->get('response'));
        if (!$response instanceof Response) {
            return new Response('POST data key "response" must be a serialized Symfony response', 417);
        }

        $limiter = null;
        if ($request->request->has('limiter')) {
            $limiter = silent_deserialize($request->request->get('limiter'));
            if (!is_callable($limiter)) {
                return new Response('POST data key "limiter" must be a serialized closure', 417);
            }
        }

        // Fix issue with silex default error handling
        $response->headers->set('X-Status-Code', $response->getStatusCode());

        prepend(
            $request,
            'expectations',
            ['matcher' => $matcher, 'response' => $response, 'limiter' => $limiter, 'runs' => 0]
        );

        return new Response('', 201);
    }
);

$app->error(
    static function (Exception $e) use ($app) {
        if ($e instanceof NotFoundHttpException) {
            /** @var Request $request */
            $request = $app['request'];
            append(
                $request,
                'requests',
                serialize(['server' => $request->server->all(), 'request' => (string) $request])
            );

            $notFoundResponse = new Response('No matching expectation found', 404);

            $expectations = read($request, 'expectations');
            foreach ($expectations as $pos => $expectation) {
                foreach ($expectation['matcher'] as $matcher) {
                    if (!$matcher($request)) {
                        continue 2;
                    }
                }

                if (isset($expectation['limiter']) && !$expectation['limiter']($expectation['runs'])) {
                    $notFoundResponse = new Response('Expectation no longer applicable', 410);
                    continue;
                }

                ++$expectations[$pos]['runs'];
                store($request, 'expectations', $expectations);

                return $expectation['response'];
            }

            return $notFoundResponse;
        }

        $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
        return new Response('Server error: ' . $e->getMessage(), $code);
    }
);

$app->get(
    '/_request/{index}',
    static function (Request $request, $index) {
        $requestData = read($request, 'requests');
        if (!isset($requestData[$index])) {
            return new Response('Index ' . $index . ' not found', 404);
        }

        return new Response($requestData[$index], 200, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '\d+');

$app->delete(
    '/_request/{action}',
    static function (Request $request, $action) {
        $requestData = read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        store($request, 'requests', $requestData);
        if (!$requestString) {
            return new Response($action . ' not possible', 404);
        }

        return new Response($requestString, 200, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '(last|first)');

$app->get(
    '/_request/{action}',
    static function (Request $request, $action) {
        $requestData = read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        if (!$requestString) {
            return new Response($action . ' not available', 404);
        }

        return new Response($requestString, 200, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '(last|first)');

$app->delete(
    '/_request',
    static function (Request $request) {
        store($request, 'requests', []);

        return new Response('', 200);
    }
);

$app->delete(
    '/_all',
    static function (Request $request) {
        store($request, 'requests', []);
        store($request, 'expectations', []);

        return new Response('', 200);
    }
);

$app->get(
    '/_me',
    static function () {
        return new Response('O RLY?', 418, ['Content-Type' => 'text/plain']);
    }
);

return $app;
