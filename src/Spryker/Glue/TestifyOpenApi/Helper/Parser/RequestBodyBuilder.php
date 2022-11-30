<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper\Parser;

use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Schema;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class RequestBodyBuilder
{
    /**
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return \Psr\Http\Message\StreamInterface|null
     */
    public function buildRequestBody(Operation $operation): ?StreamInterface
    {
        $requestBody = $operation->requestBody;

        if (!$requestBody) {
            return null;
        }

        if (!is_a($requestBody, RequestBody::class)) {
            return null;
        }

        $mediaType = $requestBody->content['application/json'];

        if (!is_a($mediaType, MediaType::class) || !$mediaType->schema) {
            return null;
        }

        $schema = $mediaType->schema;

        if (!is_a($schema, Schema::class) || !$schema->properties) {
            return null;
        }

        $requestData = $this->createRequestData($schema->properties);

        $jsonRequest = json_encode($requestData);

        if (!$jsonRequest) {
            return null;
        }

        return Stream::create($jsonRequest);
    }

    /**
     * @param array $properties
     *
     * @return array
     */
    protected function createRequestData(array $properties): array
    {
        $data = [];

        foreach ($properties as $propertyName => $schema) {
            $data[$propertyName] = $this->getValue($schema);
        }

        return $data;
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return mixed|array<array>|array<string>|array<int>|string|int
     */
    protected function getValue(Schema $schema)
    {
        $type = $schema->type;

        if ($schema->example) {
            return $schema->example;
        }

        if ($schema->enum) {
            // Return randomly one of the enums
            // @TODO this one is very likely ending up in flaky tests
            return $schema->enum[rand(0, count($schema->enum) - 1)];
        }

        if ($schema->properties) {
            return $this->createRequestData($schema->properties);
        }

        if ($schema->items && is_a($schema->items, Schema::class)) {
            $value = $this->getValueByType($schema->items->type, $schema);

            if ($type === 'array') {
                return [$value];
            }

            return $value;
        }

        return $this->getValueByType($schema->type, $schema);
    }

    /**
     * @param string $type
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return array|string|int|null
     */
    protected function getValueByType(string $type, Schema $schema)
    {
        if ($type === 'string') {
            return 'string';
        }

        if ($type === 'integer') {
            return rand();
        }

        if ($type === 'object' && $schema->items && is_a($schema->items, Schema::class)) {
            return $this->createRequestData($schema->items->properties);
        }

        return null;
    }
}
