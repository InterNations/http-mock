<?php

// @codingStandardsIgnoreLine

namespace InterNations\Component\HttpMock;

use Error;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\App;
use Slim\Container;
use Slim\Http\StatusCode;

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
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
    throw new RuntimeException(sprintf('Could not locate autoloader file. Tried "%s"', implode($autoloadFiles, '", "')));
}

$container = new Container([
    'settings' => [
        'displayErrorDetails' => true,
    ],
]);
$container['storage'] = new RequestStorage(getmypid(), __DIR__ . '/../state/');
$app = new App($container);

$app->delete(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->clear($request, 'expectations');

        return $response->withStatus(StatusCode::HTTP_OK);
    }
);

$app->post(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $data = json_decode($request->getBody()->getContents(), true);
        $matcher = [];

        if (!empty($data['matcher'])) {
            $matcher = Util::silentDeserialize($data['matcher']);
            $validator = function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                return $response->withStatus(StatusCode::HTTP_EXPECTATION_FAILED)->write(
                    'POST data key "matcher" must be a serialized list of closures'
                );
            }
        }

        if (empty($data['response'])) {
            return $response->withStatus(StatusCode::HTTP_EXPECTATION_FAILED)->write(
                'POST data key "response" not found in POST data'
            );
        }

        try {
            $responseToSave = Util::responseDeserialize($data['response']);
        } catch (Exception $e) {
            return $response->withStatus(StatusCode::HTTP_EXPECTATION_FAILED)->write(
                'POST data key "response" must be an http response message in text form'
            );
        }

        $limiter = null;

        if (!empty($data['limiter'])) {
            $limiter = Util::silentDeserialize($data['limiter']);

            if (!is_callable($limiter)) {
                return $response->withStatus(StatusCode::HTTP_EXPECTATION_FAILED)->write(
                    'POST data key "limiter" must be a serialized closure'
                );
            }
        }

        // Fix issue with silex default error handling
        // not sure if this is need anymore
        $response = $response->withHeader('X-Status-Code', $response->getStatusCode());

        $responseCallback = null;
        if (!empty($data['responseCallback'])) {
            $responseCallback = Util::silentDeserialize($data['responseCallback']);

            if ($responseCallback !== null && !is_callable($responseCallback)) {
                return $response->withStatus(StatusCode::HTTP_EXPECTATION_FAILED)->write(
                    'POST data key "responseCallback" must be a serialized closure: '
                    . print_r($data['responseCallback'], true)
                );
            }
        }

        $container['storage']->prepend(
            $request,
            'expectations',
            [
                'matcher' => $matcher,
                'response' => $data['response'],
                'limiter' => $limiter,
                'responseCallback' => $responseCallback,
                'runs' => 0,
            ]
        );

        return $response->withStatus(StatusCode::HTTP_CREATED);
    }
);

$container['phpErrorHandler'] = function ($container) {
    return function (Request $request, Response $response, Error $e) use ($container) {
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/plain')
            ->write($e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    };
};

$container['notFoundHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        $container['storage']->append(
            $request,
            'requests',
            serialize(
                [
                    'request' => Util::serializePsrMessage($request),
                    'server' => $request->getServerParams(),
                ]
            )
        );

        $notFoundResponse = $response->withStatus(StatusCode::HTTP_NOT_FOUND);

        $expectations = $container['storage']->read($request, 'expectations');

        foreach ($expectations as $pos => $expectation) {
            foreach ($expectation['matcher'] as $matcher) {
                if (!$matcher($request)) {
                    continue 2;
                }
            }

            if (isset($expectation['limiter']) && !$expectation['limiter']($expectation['runs'])) {
                if ($notFoundResponse->getStatusCode() != StatusCode::HTTP_GONE) {
                    $notFoundResponse = $response->withStatus(StatusCode::HTTP_GONE)
                        ->write('Expectation no longer applicable');
                }
                continue;
            }

            ++$expectations[$pos]['runs'];
            $container['storage']->store($request, 'expectations', $expectations);

            $r = Util::responseDeserialize($expectation['response']);
            if (!empty($expectation['responseCallback'])) {
                $callback = $expectation['responseCallback'];

                return $callback($r);
            }

            return $r;
        }

        if ($notFoundResponse->getStatusCode() == StatusCode::HTTP_NOT_FOUND) {
            $notFoundResponse = $notFoundResponse->write('No matching expectation found');
        }

        return $notFoundResponse;
    };
};

$container['errorHandler'] = function ($container) {
    return function (Request $request, Response $response, Exception $e) use ($container) {
        return $response->withStatus(StatusCode::HTTP_INTERNAL_SERVER_ERROR)->write(
            'Server error: ' . $e->getMessage());
    };
};

$app->get(
    '/_request/count',
    function (Request $request, Response $response) use ($container) {
        $count = count($container['storage']->read($request, 'requests'));

        return $response->withStatus(StatusCode::HTTP_OK)
            ->write($count)
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->get(
    '/_request/{index:[0-9]+}',
    function (Request $request, Response $response, $args) use ($container) {
        $index = (int) $args['index'];
        $requestData = $container['storage']->read($request, 'requests');

        if (!isset($requestData[$index])) {
            return $response->withStatus(StatusCode::HTTP_NOT_FOUND)->write(
                'Index ' . $index . ' not found');
        }

        return $response->withStatus(StatusCode::HTTP_OK)
            ->write($requestData[$index])
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->delete(
    '/_request/{action:last|latest|first}',
    function (Request $request, Response $response, $args) use ($container) {
        $action = $args['action'];

        $requestData = $container['storage']->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' || $action === 'latest' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        $container['storage']->store($request, 'requests', $requestData);

        if (!$requestString) {
            return $response->withStatus(StatusCode::HTTP_NOT_FOUND)->write(
                $action . ' not possible'
            );
        }

        return $response->withStatus(StatusCode::HTTP_OK)
            ->write($requestString)
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->get(
    '/_request/{action:last|latest|first}',
    function (Request $request, Response $response, $args) use ($container) {
        $action = $args['action'];
        $requestData = $container['storage']->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' || $action === 'latest' ? 'pop' : 'shift');
        $requestString = $fn($requestData);

        if (!$requestString) {
            return $response->withStatus(StatusCode::HTTP_NOT_FOUND)->write(
                $action . ' not available'
            );
        }

        return $response->withStatus(StatusCode::HTTP_OK)
            ->withHeader('Content-Type', 'text/plain')
            ->write($requestString);
    }
);

$app->delete(
    '/_request',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->store($request, 'requests', []);

        return $response->withStatus(StatusCode::HTTP_OK);
    }
);

$app->delete(
    '/_all',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->store($request, 'requests', []);
        $container['storage']->store($request, 'expectations', []);

        return $response->withStatus(StatusCode::HTTP_OK);
    }
);

$app->get(
    '/_me',
    function (Request $request, Response $response) {
        return $response->withStatus(StatusCode::HTTP_IM_A_TEAPOT)
            ->write('O RLY?')
            ->withHeader('Content-Type', 'text/plain');
    }
);

return $app;
