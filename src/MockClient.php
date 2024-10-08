<?php

declare(strict_types=1);

namespace Fansipan\Mock;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MockClient implements ClientInterface
{
    use AssertTrait;

    /**
     * @var null|ResponseInterface
     */
    private $defaultResponse;

    /**
     * @var \Iterator
     */
    private $responses;

    /**
     * @param  null|iterable<ResponseInterface>|ResponseInterface|ResponseInterface[] $response
     */
    public function __construct($response = null)
    {
        $this->setResponse($response);
    }

    /**
     * Set the response factory.
     *
     * @param  null|iterable<ResponseInterface>|ResponseInterface|ResponseInterface[] $response
     */
    public function setResponse($response = null): void
    {
        if (\is_null($response)) {
            $response = Psr17FactoryDiscovery::findResponseFactory()->createResponse();
        }

        if ($response instanceof ResponseInterface) {
            $this->defaultResponse = $response;
            return;
        }

        if (\is_array($response)) {
            $response = new \ArrayIterator($response);
        }

        if (! $response instanceof \Iterator) {
            throw new \InvalidArgumentException('Unable to create response for MockClient');
        }

        $this->responses = $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->defaultResponse instanceof ResponseInterface) {
            $this->record($request, $this->defaultResponse);

            return $this->defaultResponse; // @phpstan-ignore return.type
        }

        $method = $request->getMethod();
        $uri = $request->getUri();

        if (! $this->responses->valid()) {
            throw new \OutOfRangeException('The response iterator passed to Mock Client is empty.');
        }

        $responseFactory = $this->responses->current();
        $response = \is_callable($responseFactory) ? $responseFactory($method, $uri) : $responseFactory;
        $this->responses->next();

        if (! $response instanceof ResponseInterface) {
            throw new \InvalidArgumentException(sprintf('The response passed to Mock Client must return/yield an instance of Psr\Http\Message\ResponseInterface, "%s" given.', get_debug_type($response)));
        }

        $this->record($request, $response);

        return $response;
    }
}
