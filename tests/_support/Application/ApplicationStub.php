<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace TestifyOpenApi\Application;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApplicationStub implements HttpKernelInterface
{
    /**
     * @var array
     */
    protected array $responsesForRequests = [];

    /**
     * @param array $responsesForRequests
     */
    public function __construct(array $responsesForRequests)
    {
        $this->responsesForRequests = $responsesForRequests;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param string $url
     * @param string $method
     *
     * @return void
     */
    public function addResponseForRequest(Response $response, string $url, string $method): void
    {
        $this->responsesForRequests[] = [
            'url' => $url,
            'method' => $method,
            'response' => $response,
        ];
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $type
     * @param bool $catch
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true)
    {
        return $this->getMatchingResponseForRequest($request);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getMatchingResponseForRequest(Request $request): Response
    {
        foreach ($this->responsesForRequests as $responseForRequest) {
            if ($request->getPathInfo() === $responseForRequest['url'] && strtolower($request->getMethod()) === strtolower($responseForRequest['method'])) {
                return $responseForRequest['response'];
            }
        }

        throw new Exception(sprintf('Could not find a response for your request: [%s] %s', strtoupper($request->getMethod()), $request->getPathInfo()));
    }
}
