<?php
namespace InterNations\Component\HttpMock\Server;

use InterNations\Component\HttpMock\Util;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use function array_filter;
use function base64_encode;
use function count;
use function getmypid;
use function is_array;
use function is_callable;
use function is_string;
use function random_bytes;
use function serialize;

final class ServerApplication extends Kernel
{
    use MicroKernelTrait;

    /** @return list<Bundle> */
    public function registerBundles(): array
    {
        return [new FrameworkBundle()];
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => base64_encode(random_bytes(8))]);

        $container
            ->services()
            ->set(RequestStorage::class, FileBasedStorage::class)
                ->autowire()
                ->args([getmypid(), $this->getCacheDir() . '/state/']);
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes
            ->add('me', '/_me')
                ->controller([$this, 'meAction'])
                ->methods(['GET'])

            ->add('reset_all', '/_all')
                ->controller([$this, 'deleteAll'])
                ->methods(['DELETE'])

            ->add('request_delete_all', '/_request')
                ->controller([$this, 'deleteRequests'])
                ->methods(['DELETE'])

            ->add('request_by_position_get', '/_request/{position}')
                ->controller([$this, 'getRequestByPosition'])
                ->methods(['GET'])
                ->requirements(['position' => 'first|last'])#

            ->add('request_by_position_delete', '/_request/{position}')
                ->controller([$this, 'deleteRequestByPosition'])
                ->methods(['DELETE'])
                ->requirements(['position' => 'first|last'])

            ->add('request_by_index_get', '/_request/{index}')
                ->controller([$this, 'getRequestByIndex'])
                ->methods(['GET'])
                ->requirements(['index' => '\d+'])

            ->add('request_count', '/_request/count')
                ->controller([$this, 'countRequests'])
                ->methods(['GET'])

            ->add('expectations_delete_all', '/_expectation')
                ->controller([$this, 'deleteExpectations'])
                ->methods(['DELETE'])

            ->add('expectations_post', '/_expectation')
                ->controller([$this, 'postExpectations'])
                ->methods(['POST'])

            ->add('record', '{path}')
                ->controller([$this, 'record'])
                ->requirements(['path' => '.*'])
        ;
    }

    public function meAction(): Response
    {
        return self::createResponse(Response::HTTP_I_AM_A_TEAPOT, 'O RLY?');
    }

    public function deleteAll(RequestStorage $requestStorage): Response
    {
        $requestStorage->storeRequests([]);
        $requestStorage->storeExpectations([]);

        return self::createResponse(Response::HTTP_OK);
    }

    public function deleteRequests(RequestStorage $requestStorage): Response
    {
        $requestStorage->storeRequests([]);

        return self::createResponse(Response::HTTP_OK);
    }

    public function getRequestByIndex(int $index, RequestStorage $requestStorage): Response
    {
        $requests = $requestStorage->readRequests();

        if (!isset($requests[$index])) {
            return self::createResponse(Response::HTTP_NOT_FOUND, 'Index ' . $index . ' not found');
        }

        return self::createResponse(Response::HTTP_OK, serialize($requests[$index]));
    }

    public function getRequestByPosition(string $position, RequestStorage $requestStorage): Response
    {
        $requestData = $requestStorage->readRequests();
        $fn = 'array_' . ($position === 'last' ? 'pop': 'shift');
        $request = $fn($requestData);

        if (!$request) {
            return self::createResponse(Response::HTTP_NOT_FOUND, $position . ' not available');
        }

        return self::createResponse(Response::HTTP_OK, serialize($request));
    }

    public function deleteRequestByPosition(string $position, RequestStorage $requestStorage): Response
    {
        $requests = $requestStorage->readRequests();
        $fn = 'array_' . ($position === 'last' ? 'pop' : 'shift');
        $request = $fn($requests);
        $requestStorage->storeRequests($requests);

        if (!$request) {
            return self::createResponse(Response::HTTP_NOT_FOUND, $position . ' not possible');
        }

        return self::createResponse(Response::HTTP_OK, serialize($request));
    }

    public function countRequests(RequestStorage $requestStorage): Response
    {
        return self::createResponse(Response::HTTP_OK, count($requestStorage->readRequests()));
    }

    public function postExpectations(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $matcher = [];

        if ($currentRequest->request->has('matcher')) {
            $matcherParameter = $currentRequest->request->get('matcher');
            if (!is_string($matcherParameter)) {
                return self::createResponse(
                    Response::HTTP_EXPECTATION_FAILED,
                    'POST data key "matcher" must be a serialized list of closures'
                );
            }

            $matcher = Util::silentDeserialize($matcherParameter);
            $validator = static function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                return self::createResponse(
                    Response::HTTP_EXPECTATION_FAILED,
                    'POST data key "matcher" must be a serialized list of closures'
                );
            }
        }

        if (!$currentRequest->request->has('response')) {
            return new Response('POST data key "response" not found in POST data', Response::HTTP_EXPECTATION_FAILED);
        }

        $response = Util::silentDeserialize($currentRequest->request->get('response'));

        if (!$response instanceof Response) {
            return new Response(
                'POST data key "response" must be a serialized Symfony response',
                Response::HTTP_EXPECTATION_FAILED
            );
        }

        $limiter = null;

        if ($currentRequest->request->has('limiter')) {
            $limiter = Util::silentDeserialize($currentRequest->request->get('limiter'));

            if (!is_callable($limiter)) {
                return new Response(
                    'POST data key "limiter" must be a serialized closure',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }
        }

        $requestStorage->prependExpectation(new ServerExpectation($matcher, $response, $limiter, 0));

        return self::createResponse(Response::HTTP_CREATED);
    }

    public function record(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $requestStorage->appendRequest($currentRequest);
        $expectations = $requestStorage->readExpectations();

        try {
            foreach ($expectations as $expectation) {
                $response = $expectation->matchRequest($currentRequest);

                if (!$response) {
                    continue;
                }

                return $response;
            }

            return self::createResponse(Response::HTTP_NOT_FOUND, 'No matching expectation found');
        } finally {
            $requestStorage->storeExpectations($expectations);
        }
    }

    public function deleteExpectations(RequestStorage $requestStorage): Response
    {
        $requestStorage->storeExpectations([]);

        return self::createResponse(Response::HTTP_OK);
    }

    private static function createResponse(int $statusCode, string $body = ''): Response
    {
        return new Response($body, $statusCode, ['Content-Type' => 'text/plain']);
    }
}
