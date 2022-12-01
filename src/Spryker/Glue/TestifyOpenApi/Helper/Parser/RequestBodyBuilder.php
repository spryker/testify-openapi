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
use Exception;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class RequestBodyBuilder
{
    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \Psr\Http\Message\StreamInterface|null
     */
    public function buildRequestBody(Operation $operation, ServerRequestInterface $request): ?StreamInterface
    {
        $requestBody = $operation->requestBody;

        if (!$requestBody) {
            return null;
        }

        if (!is_a($requestBody, RequestBody::class)) {
            return null;
        }

        $mediaType = $this->getMediaTypeByRequestHeader($requestBody, $request);

        if ($mediaType === null || !is_a($mediaType, MediaType::class) || !$mediaType->schema) {
            return null;
        }

        $schema = $mediaType->schema;

        if (!is_a($schema, Schema::class) || (!$schema->items && !$schema->properties)) {
            return null;
        }

        $requestData = $this->createRequestData($schema);

        $jsonRequest = json_encode($requestData);

        if (!$jsonRequest) {
            return null;
        }

        return Stream::create($jsonRequest);
    }

    /**
     * @param \cebe\openapi\spec\RequestBody $requestBody
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \cebe\openapi\spec\MediaType|null
     */
    protected function getMediaTypeByRequestHeader(RequestBody $requestBody, ServerRequestInterface $request): ?MediaType
    {
        $headers = $request->getHeaders();

        if (!isset($headers['Content-Type'])) {
            return null;
        }

        $contentType = $headers['Content-Type'][0];

        if (!isset($requestBody->content[$contentType])) {
            return null;
        }

        return $requestBody->content[$contentType];
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return array
     */
    protected function createRequestData(Schema $schema): array
    {
        if ($schema->items) {
            return $this->createFromItems($schema);
        }

        $data = [];

        /** @var \cebe\openapi\spec\Schema $propertySchema */
        foreach ($schema->properties as $propertyName => $propertySchema) {
            $data[$propertyName] = $this->getValue($propertySchema);
        }

        return $data;
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return array
     */
    protected function createFromItems(Schema $schema): array
    {
        /** @var \cebe\openapi\spec\Schema $items */
        $items = $schema->items;

        if ($items instanceof Schema && $schema->type === 'array') {
            return [$this->getValue($items)];
        }

        return $this->getValue($items);
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @throws \Exception
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
            // Return always the first enum to avoid flake tests
            return $schema->enum[0];
        }

        if ($type === 'boolean') {
            // Always return true for easier testing
            return true;
        }

        if ($type === 'object') {
            return $this->createRequestData($schema);
        }

        if ($type === 'array') {
            if (!$schema->items || !is_a($schema->items, Schema::class)) {
                throw new Exception(sprintf('Expected a Schema object but got: %s', gettype($schema->items)));
            }

            if ($schema->items->type !== 'object') {
                return [$this->getValue($schema->items)];
            }

            return [$this->createRequestData($schema->items)];
        }

        if ($type === 'string') {
            return $this->string($schema);
        }

        return $this->integer($schema);
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return string
     */
    protected function string(Schema $schema): string
    {
        if ($schema->format && $schema->format === 'date-time') {
            return '2022-12-01T13:51:46.495Z';
        }

        return 'string';
    }

    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return int
     */
    protected function integer(Schema $schema): int
    {
        return 0;
    }
}
