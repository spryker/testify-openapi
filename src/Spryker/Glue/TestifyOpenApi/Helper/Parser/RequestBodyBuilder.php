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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class RequestBodyBuilder
{
    /**
     * @var \Spryker\Glue\TestifyOpenApi\Helper\Parser\ValueResolver
     */
    protected ValueResolver $valueResolver;

    public function __construct()
    {
        $this->valueResolver = new ValueResolver();
    }

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

        $requestData = $this->valueResolver->getValueForSchema($schema);

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
}
