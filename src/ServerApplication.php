<?php
namespace InterNations\Component\HttpMock;

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

        $container->services()
            ->set(RequestStorage::class)
                ->args([getmypid(), $this->getCacheDir() . '/state/']);
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes
            ->add('me', '/_me')
                ->controller([$this, 'meAction'])
                ->methods(['GET'])

            ->add('reset_all', '/_all')
                ->controller([$this, 'resetAll'])
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
        return new Response('O RLY?', Response::HTTP_I_AM_A_TEAPOT, ['Content-Type' => 'text/plain']);
    }

    public function resetAll(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $requestStorage->store($currentRequest, 'requests', []);
        $requestStorage->store($currentRequest, 'expectations', []);

        return new Response('', Response::HTTP_OK);
    }

    public function deleteRequests(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $requestStorage->store($currentRequest, 'requests', []);

        return new Response('', Response::HTTP_OK);
    }

    public function getRequestByIndex(
        int $index,
        Request $currentRequest,
        RequestStorage $requestStorage

    ): Response
    {
        $requests = $requestStorage->read($currentRequest, 'requests');

        if (!isset($requests[$index])) {
            return new Response('Index ' . $index . ' not found', Response::HTTP_NOT_FOUND);
        }

        return new Response($requests[$index], Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function getRequestByPosition(
        string $position,
        Request $currentRequest,
        RequestStorage $requestStorage
    ): Response
    {
        $requestData = $requestStorage->read($currentRequest, 'requests');
        $fn = 'array_' . ($position === 'last' ? 'pop': 'shift');
        $request = $fn($requestData);

        if (!$request) {
            return new Response($position . ' not available', Response::HTTP_NOT_FOUND);
        }

        return new Response($request, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function deleteRequestByPosition(
        string $position,
        Request $currentRequest,
        RequestStorage $requestStorage
    ): Response
    {
        $requests = $requestStorage->read($currentRequest, 'requests');
        $fn = 'array_' . ($position === 'last' ? 'pop' : 'shift');
        $request = $fn($requests);
        $requestStorage->store($currentRequest, 'requests', $requests);

        if (!$request) {
            return new Response($position . ' not possible', Response::HTTP_NOT_FOUND);
        }

        return new Response($request, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function countRequests(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        return new Response(
            count($requestStorage->read($currentRequest, 'requests')),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }

    public function postExpectations(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $matcher = [];

        if ($currentRequest->request->has('matcher')) {
            $matcherParameter = $currentRequest->request->get('matcher');
            if (!is_string($matcherParameter)) {
                return new Response(
                    'POST data key "matcher" must be a serialized list of closures',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }

            $matcher = Util::silentDeserialize($matcherParameter);
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

        $requestStorage->prepend(
            $currentRequest,
            'expectations',
            ['matcher' => $matcher, 'response' => $response, 'limiter' => $limiter, 'runs' => 0]
        );

        return new Response('', Response::HTTP_CREATED);
    }

    public function record(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $requestStorage->append($currentRequest, 'requests', serialize($currentRequest));

        $notFoundResponse = new Response('No matching expectation found', Response::HTTP_NOT_FOUND);

        $expectations = $requestStorage->read($currentRequest, 'expectations');

        foreach ($expectations as $pos => $expectation) {
            foreach ($expectation['matcher'] as $matcher) {
                if (!$matcher($currentRequest)) {
                    continue 2;
                }
            }

            $applicable = !isset($expectation['limiter']) || $expectation['limiter']($expectation['runs']);

            ++$expectations[$pos]['runs'];
            $requestStorage->store($currentRequest, 'expectations', $expectations);

            if (!$applicable) {
                $notFoundResponse = new Response('Expectation not met', Response::HTTP_GONE);
                continue;
            }

            return $expectation['response'];
        }

        return $notFoundResponse;
    }

    public function deleteExpectations(Request $currentRequest, RequestStorage $requestStorage): Response
    {
        $requestStorage->clear($currentRequest, 'expectations');

        return new Response('', Response::HTTP_OK);

    }
}
