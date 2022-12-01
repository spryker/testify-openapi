<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Glue\TestifyOpenApi\Parser;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use Codeception\Test\Unit;
use Exception;
use Nyholm\Psr7\ServerRequest;
use Spryker\Glue\TestifyOpenApi\Helper\Parser\RequestBodyBuilder;
use TestifyOpenApi\TestifyOpenApiTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Glue
 * @group TestifyOpenApi
 * @group Parser
 * @group RequestBodyBuilderTest
 * Add your own group annotations below this line
 */
class RequestBodyBuilderTest extends Unit
{
    /**
     * @var \TestifyOpenApi\TestifyOpenApiTester
     */
    public TestifyOpenApiTester $tester;

    /**
     * @var \cebe\openapi\spec\OpenApi|null
     */
    protected ?OpenApi $openApi = null;

    /**
     * @return void
     */
    public function testPetPutRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/pet';
        $method = 'put';

        $expectedJson = json_encode($this->tester->getValidPet());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @return void
     */
    public function testPetPostRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/pet';
        $method = 'post';

        $expectedJson = json_encode($this->tester->getValidPet());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @return void
     */
    public function testStoreOrderPostRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/store/order';
        $method = 'post';

        $expectedJson = json_encode($this->tester->getValidStoreOrder());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @return void
     */
    public function testUserPostRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/user';
        $method = 'post';

        $expectedJson = json_encode($this->tester->getValidUser());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @return void
     */
    public function testUserWhiteListPostRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/user/createWithList';
        $method = 'post';

        $expectedJson = json_encode($this->tester->getValidUserWhiteList());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @return void
     */
    public function testUserPutRequestBodyBuilderShouldReturnExpectedBody(): void
    {
        $path = '/user/{username}';
        $method = 'put';

        $expectedJson = json_encode($this->tester->getValidUser());

        $requestBodyBuilder = new RequestBodyBuilder();
        $operation = $this->getOperationByPathAndMethod($path, $method);
        $request = new ServerRequest($method, $path, ['Content-Type' => 'application/json']);

        $requestBody = (string)$requestBodyBuilder->buildRequestBody($operation, $request);
        $this->assertNotNull($requestBody, sprintf('Could not generate a request body for [%s] %s', $method, $path));

        $this->assertSame($expectedJson, $requestBody);
    }

    /**
     * @param string $lookupPath
     * @param string $method
     *
     * @throws \Exception
     *
     * @return \cebe\openapi\spec\Operation
     */
    protected function getOperationByPathAndMethod(string $lookupPath, string $method): Operation
    {
        foreach ($this->getOpenApi()->paths as $path => $pathItem) {
            if ($lookupPath === $path) {
                return $this->getOperationByMethod($pathItem, $method);
            }
        }

        throw new Exception(sprintf('Couldn\'t find operations for path %s', $lookupPath));
    }

    /**
     * @param \cebe\openapi\spec\PathItem $pathItem
     * @param string $lookupMethod
     *
     * @throws \Exception
     *
     * @return \cebe\openapi\spec\Operation
     */
    protected function getOperationByMethod(PathItem $pathItem, string $lookupMethod): Operation
    {
        foreach ($pathItem->getOperations() as $method => $operation) {
            if ($lookupMethod === $method) {
                return $operation;
            }
        }

        throw new Exception(sprintf('Couldn\'t find operation for method %s', $lookupMethod));
    }

    /**
     * @return \cebe\openapi\spec\OpenApi
     */
    protected function getOpenApi(): OpenApi
    {
        if (!$this->openApi) {
            $this->openApi = $this->tester->getOpenApiHelper()->setOpenApi(codecept_data_dir('pet.yml'));
        }

        return $this->openApi;
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param string $contentType
     *
     * @return bool
     */
    protected function supportsContentType(Operation $operation, string $contentType = 'application/json'): bool
    {
        $supportedContentTypes = $operation->requestBody->content;

        if (!isset($supportedContentTypes[$contentType])) {
            return false;
        }

        return true;
    }
}
