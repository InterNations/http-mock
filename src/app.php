<?php // @codingStandardsIgnoreLine
namespace InterNations\Component\HttpMock;

use Exception;
use RuntimeException;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    throw new RuntimeException(
        sprintf('Could not locate autoloader file. Tried "%s"', implode($autoloadFiles, '", "'))
    );
}

$app = new Application();
$app['debug'] = true;
$app['storage'] = new RequestStorage(getmypid(), __DIR__ . '/../state/');

$app->delete(
    '/_expectation',
    static function (Request $request) use ($app) {
        $app['storage']->clear($request, 'expectations');

        return new Response('', Response::HTTP_OK);
    }
);

$app->post(
    '/_expectation',
    static function (Request $request) use ($app) {

        $matcher = [];

        if ($request->request->has('matcher')) {
            $matcher = Util::silentDeserialize($request->request->get('matcher'));
            $validator = static function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                return new Response(
                    'POST data key "matcher" must be a serialized list of closures',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }
        }

        if (!$request->request->has('response')) {
            return new Response('POST data key "response" not found in POST data', Response::HTTP_EXPECTATION_FAILED);
        }

        $response = Util::silentDeserialize($request->request->get('response'));

        if (!$response instanceof Response) {
            return new Response(
                'POST data key "response" must be a serialized Symfony response',
                Response::HTTP_EXPECTATION_FAILED
            );
        }

        $limiter = null;

        if ($request->request->has('limiter')) {
            $limiter = Util::silentDeserialize($request->request->get('limiter'));

            if (!is_callable($limiter)) {
                return new Response(
                    'POST data key "limiter" must be a serialized closure',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }
        }

        $countARunEvenIfLimiterDoesntMatch = false;
        if ($request->request->has('countARunEvenIfLimiterDoesntMatch')) {
            $countARunEvenIfLimiterDoesntMatch = Util::silentDeserialize($request->request->get('countARunEvenIfLimiterDoesntMatch'));
        }

        // Fix issue with silex default error handling
        $response->headers->set('X-Status-Code', $response->getStatusCode());

        $app['storage']->prepend(
            $request,
            'expectations',
            ['matcher' => $matcher, 'response' => $response, 'limiter' => $limiter, 'runs' => 0, 'countARunEvenIfLimiterDoesntMatch' => $countARunEvenIfLimiterDoesntMatch]
        );

        return new Response('', Response::HTTP_CREATED);
    }
);

$app->error(
    static function (Exception $e, Request $request, $code, GetResponseForExceptionEvent $event = null) use ($app) {
        if ($e instanceof NotFoundHttpException) {
            if (method_exists($event, 'allowCustomResponseCode')) {
                $event->allowCustomResponseCode();
            }

            $app['storage']->append(
                $request,
                'requests',
                serialize(
                    [
                        'server'    => $request->server->all(),
                        'request'   => (string) $request,
                        'enclosure' => $request->request->all(),
                    ]
                )
            );

            $notFoundResponse = new Response('No matching expectation found', Response::HTTP_NOT_FOUND);

            $expectations = $app['storage']->read($request, 'expectations');

            foreach ($expectations as $pos => $expectation) {
                foreach ($expectation['matcher'] as $matcher) {
                    if (!$matcher($request)) {
                        continue 2;
                    }
                }

                $applicable = !isset($expectation['limiter']) || $expectation['limiter']($expectation['runs']);

                ++$expectations[$pos]['runs'];
                $app['storage']->store($request, 'expectations', $expectations);

                if (!$applicable) {
                    $notFoundResponse = new Response('Expectation not met', Response::HTTP_GONE);
                    continue;
                }

                return $expectation['response'];
            }

            return $notFoundResponse;
        }

        return new Response('Server error: ' . $e->getMessage(), $code);
    }
);

$app->get(
    '/_request/count',
    static function (Request $request) use ($app) {
        return count($app['storage']->read($request, 'requests'));
    }
);

$app->get(
    '/_request/{index}',
    static function (Request $request, $index) use ($app) {
        $requestData = $app['storage']->read($request, 'requests');

        if (!isset($requestData[$index])) {
            return new Response('Index ' . $index . ' not found', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestData[$index], Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '\d+');

$app->delete(
    '/_request/{action}',
    static function (Request $request, $action) use ($app) {
        $requestData = $app['storage']->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        $app['storage']->store($request, 'requests', $requestData);

        if (!$requestString) {
            return new Response($action . ' not possible', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestString, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '(last|first)');

$app->get(
    '/_request/{action}',
    static function (Request $request, $action) use ($app) {
        $requestData = $app['storage']->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);

        if (!$requestString) {
            return new Response($action . ' not available', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestString, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
)->assert('index', '(last|first)');

$app->delete(
    '/_request',
    static function (Request $request) use ($app) {
        $app['storage']->store($request, 'requests', []);

        return new Response('', Response::HTTP_OK);
    }
);

$app->delete(
    '/_all',
    static function (Request $request) use ($app) {
        $app['storage']->store($request, 'requests', []);
        $app['storage']->store($request, 'expectations', []);

        return new Response('', Response::HTTP_OK);
    }
);

$app->get(
    '/_me',
    static function () {
        return new Response('O RLY?', Response::HTTP_I_AM_A_TEAPOT, ['Content-Type' => 'text/plain']);
    }
);

return $app;
