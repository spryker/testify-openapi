<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper\Statistic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Statistic
{
    /**
     * @var array
     */
    protected array $statistics = [];

    /**
     * @var int
     */
    protected int $totalOfNumberTests = 0;

    /**
     * @return void
     */
    public function recordTest(): void
    {
        $this->totalOfNumberTests++;
    }

    /**
     * @param string $path
     * @param int $expectedResponseCode
     * @param \Psr\Http\Message\ServerRequestInterface $psrRequest
     * @param \Psr\Http\Message\ResponseInterface $psrResponse
     * @param \Symfony\Component\HttpFoundation\Request $symfonyRequest
     * @param \Symfony\Component\HttpFoundation\Response $symfonyResponse
     *
     * @return $this
     */
    public function addRequestsResponses(
        string $path,
        int $expectedResponseCode,
        ServerRequestInterface $psrRequest,
        ResponseInterface $psrResponse,
        Request $symfonyRequest,
        Response $symfonyResponse
    ) {
        $method = $psrRequest->getMethod();

        $this->statistics[$path][$method][$expectedResponseCode]['requestsAndResponses'] = [
            [
                'psrRequest' => $psrRequest,
                'symfonyRequest' => $symfonyRequest,
                'psrResponse' => $psrResponse,
                'symfonyResponse' => $symfonyResponse,
            ],
        ];

        return $this;
    }

    /**
     * @param string $path
     * @param string $method
     * @param int $expectedResponseCode
     * @param string $failureMessage
     *
     * @return $this
     */
    public function addFailure(string $path, string $method, int $expectedResponseCode, string $failureMessage)
    {
        if (!isset($this->statistics[$path][$method][$expectedResponseCode]['failures'])) {
            $this->statistics[$path][$method][$expectedResponseCode]['failures'] = [];
        }

        $this->statistics[$path][$method][$expectedResponseCode]['failures'][] = $failureMessage;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasFailures(): bool
    {
        foreach ($this->statistics as $methods) {
            foreach ($methods as $responseCodes) {
                foreach ($responseCodes as $statistic) {
                    if (isset($statistic['failures']) && count($statistic['failures'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @param string $method
     * @param string|int $expectedResponseCode
     * @param string $warningMessage
     *
     * @return $this
     */
    public function addWarning(string $path, string $method, string|int $expectedResponseCode, string $warningMessage)
    {
        if (!isset($this->statistics[$path][$method][$expectedResponseCode]['warnings'])) {
            $this->statistics[$path][$method][$expectedResponseCode]['warnings'] = [];
        }

        $this->statistics[$path][$method][$expectedResponseCode]['warnings'][] = $warningMessage;

        return $this;
    }

    /**
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * @return int
     */
    public function getTotalNumberOfTest(): int
    {
        return $this->totalOfNumberTests;
    }

    /**
     * @return int
     */
    public function getTotalNumberOfFailures(): int
    {
        $totalNumberOfFailures = 0;

        foreach ($this->statistics as $methods) {
            foreach ($methods as $responseCodes) {
                foreach ($responseCodes as $statistic) {
                    if (isset($statistic['failures'])) {
                        $totalNumberOfFailures += count($statistic['failures']);
                    }
                }
            }
        }

        return $totalNumberOfFailures;
    }

    /**
     * @return int
     */
    public function getTotalNumberOfWarnings(): int
    {
        $totalNumberOfWarnings = 0;

        foreach ($this->statistics as $paths) {
            foreach ($paths as $statistic) {
                if (!isset($statistic['warnings'])) {
                    continue;
                }
                $totalNumberOfWarnings += count($statistic['warnings']);
            }
        }

        return $totalNumberOfWarnings;
    }
}
