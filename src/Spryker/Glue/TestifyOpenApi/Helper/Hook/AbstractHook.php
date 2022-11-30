<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper\Hook;

use Codeception\Module;
use Psr\Http\Message\ServerRequestInterface;
use Spryker\Glue\TestifyOpenApi\Helper\OpenApiHelper;

abstract class AbstractHook extends Module
{
    /**
     * @param array $settings
     *
     * @return void
     */
    public function _beforeSuite($settings = [])
    {
        OpenApiHelper::addHook($this);
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param int $responseCode
     *
     * @return bool
     */
    abstract public function accept(string $endpoint, string $method, int $responseCode): bool;

    /**
     * Returns an array of path parameters that will be used to generate the full resource name.
     *
     * @return array<string, string|int>
     */
    public function getPathParameters(): array
    {
        return [];
    }

    /**
     * Returns an array of query parameters that will be used to generate the full resource name.
     *
     * @return array<string, string|int>
     */
    public function getQueryParameters(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return static::class;
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function prepareRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request;
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return string|int|null
     */
    public function resolveValue(string $name, string $type)
    {
        if ($type === 'path') {
            return $this->getPathParameters()[$name] ?? null;
        }

        if ($type === 'query') {
            return $this->getQueryParameters()[$name] ?? null;
        }

        return null;
    }
}
