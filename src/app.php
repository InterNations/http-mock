<?php

// @codingStandardsIgnoreLine

namespace InterNations\Component\HttpMock;

use Error;
use Exception;
use Fig\Http\Message\StatusCodeInterface;
use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Factory\Psr17\GuzzlePsr17Factory;
use Throwable;

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
    throw new RuntimeException(sprintf('Could not locate autoloader file. Tried "%s"', implode('", "', $autoloadFiles)));
}

$container = new Container([
    'settings' => [
        'displayErrorDetails' => true,
    ],
    'storage' => new RequestStorage(getmypid(), __DIR__ . '/../state/')
]);


AppFactory::setContainer(new PsrContainer($container));
AppFactory::setResponseFactory(GuzzlePsr17Factory::getResponseFactory());

$app = AppFactory::create();

$app->delete(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->clear($request, 'expectations');

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->post(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $data = json_decode($request->getBody()->getContents(), true);
        $matcher = [];

        if (!empty($data['matcher'])) {
            if (!is_string($data['matcher'])) {
                $response->getBody()->write('POST data key "matcher" must be a serialized list of closures');

                return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
            }

            $matcher = Util::silentDeserialize($data['matcher']);
            $validator = function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                $response->getBody()->write('POST data key "matcher" must be a serialized list of closures');

                return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
            }
        }

        if (empty($data['response'])) {
            $response->getBody()->write('POST data key "response" not found in POST data');

            return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
        }

        try {
            $responseToSave = Util::responseDeserialize($data['response']);
        } catch (Exception $e) {
            $response->getBody()->write('POST data key "response" must be an http response message in text form');

            return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
        }

        $limiter = null;

        if (!empty($data['limiter'])) {
            $limiter = Util::silentDeserialize($data['limiter']);

            if (!is_callable($limiter)) {
                $response->getBody()->write('POST data key "limiter" must be a serialized closure');

                return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
            }
        }

        // Fix issue with silex default error handling
        // not sure if this is need anymore
        $response = $response->withHeader('X-Status-Code', $response->getStatusCode());

        $responseCallback = null;
        if (!empty($data['responseCallback'])) {
            $responseCallback = Util::silentDeserialize($data['responseCallback']);

            if ($responseCallback !== null && !is_callable($responseCallback)) {
                $response->getBody()->write('POST data key "responseCallback" must be a serialized closure: '
                    . print_r($data['responseCallback'], true));

                return $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);
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

        return $response->withStatus(StatusCodeInterface::STATUS_CREATED);
    }
);

$container['phpErrorHandler'] = function ($container) {
    return function (Request $request, Response $response, Error $e) use ($container) {

        $response->getBody()->write($e->getMessage() . "\n" . $e->getTraceAsString() . "\n");

        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/plain');
    };
};

$notfoundHandler = function (Request $request, Response $response) use ($container) : Response {
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

        $notFoundResponse = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

        $expectations = $container['storage']->read($request, 'expectations');

        foreach ($expectations as $pos => $expectation) {
            foreach ($expectation['matcher'] as $matcher) {
                if (!$matcher($request)) {
                    continue 2;
                }
            }

            if (isset($expectation['limiter']) && !$expectation['limiter']($expectation['runs'])) {
                if ($notFoundResponse->getStatusCode() != StatusCodeInterface::STATUS_GONE) {
                    $response->getBody()->write('Expectation no longer applicable');

                    $notFoundResponse = $response->withStatus(StatusCodeInterface::STATUS_GONE);
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

        if ($notFoundResponse->getStatusCode() == StatusCodeInterface::STATUS_NOT_FOUND) {
            $notFoundResponse->getBody()->write('No matching expectation found');
        }

        return $notFoundResponse;
    };

$phpErrorHandler = function (Request $request, Response $response, Throwable $e) use ($container): Response {
        $response->getBody()->write('Server error: ' . $e->getMessage());

        return $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
    };

$app->get(
    '/_request/count',
    function (Request $request, Response $response) use ($container) {
        $count = count($container['storage']->read($request, 'requests'));

        $response->getBody()->write((string) $count);

        return $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->get(
    '/_request/{index:[0-9]+}',
    function (Request $request, Response $response, $args) use ($container) {
        $index = (int) $args['index'];
        $requestData = $container['storage']->read($request, 'requests');

        if (!isset($requestData[$index])) {
            $response->getBody()->write('Index ' . $index . ' not found');

            return $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $response->getBody()->write($requestData[$index]);

        return $response->withStatus(StatusCodeInterface::STATUS_OK)
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
            $response->getBody()->write($action . ' not possible');

            return $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $response->getBody()->write($requestString);

        return $response->withStatus(StatusCodeInterface::STATUS_OK)
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
            $response->getBody()->write($action . ' not available');

            return $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $response->getBody()->write($requestString);

        return $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->delete(
    '/_request',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->store($request, 'requests', []);

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->delete(
    '/_all',
    function (Request $request, Response $response) use ($container) {
        $container['storage']->store($request, 'requests', []);
        $container['storage']->store($request, 'expectations', []);

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->get(
    '/_me',
    function (Request $request, Response $response) {
        $response->getBody()->write('O RLY?');

        return $response->withStatus(StatusCodeInterface::STATUS_IM_A_TEAPOT)
            ->withHeader('Content-Type', 'text/plain');
    }
);

$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app, $notfoundHandler, $phpErrorHandler) {
    if ($exception instanceof HttpNotFoundException) {
        try {
            return $notfoundHandler($request, $app->getResponseFactory()->createResponse());
        } catch (Throwable $throwable) {
            return $phpErrorHandler($request, $app->getResponseFactory()->createResponse(), $throwable);
        }
    }

    return $phpErrorHandler($request, $app->getResponseFactory()->createResponse(), $exception);
});

return $app;
