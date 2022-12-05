<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Glue\TestifyOpenApi\Helper;

use Codeception\Test\Unit;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spryker\Glue\TestifyOpenApi\Exception\OperationNotFoundException;
use Spryker\Glue\TestifyOpenApi\Exception\ValidationFailedException;
use Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic;
use Symfony\Component\HttpFoundation\JsonResponse;
use TestifyOpenApi\TestifyOpenApiTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Glue
 * @group TestifyOpenApi
 * @group Helper
 * @group OpenApiHelperTest
 * Add your own group annotations below this line
 */
class OpenApiHelperTest extends Unit
{
    /**
     * @var \TestifyOpenApi\TestifyOpenApiTester
     */
    public TestifyOpenApiTester $tester;

    /**
     * @return void
     */
    public function testTestPathWithoutSettingAnOpenApiThrowsAnAssertionFailedError(): void
    {
        // Expect
        $this->expectException(AssertionFailedError::class);

        // Act
        $this->tester->getOpenApiHelper()->testPath('/pets', '/pets', 'get', 200);
    }

    /**
     * @return void
     */
    public function testTestPathThrowsAnNoOperationExceptionWhenPathAndMethodCombinationNotFoundInOpenApiSchema(): void
    {
        // Arrange
        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // Expect
        $this->expectException(OperationNotFoundException::class);

        // Act
        $openApiHelper->testPath('/pets', '/pets', 'get', 200);
    }

    /**
     * @return void
     */
    public function testTestPathThrowsInvalidBodyExceptionWhenRequestBodyDoesntMatchTheInTheOpenApiSchemaDefinedOne(): void
    {
        // Arrange
        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // Expect
        $this->expectException(InvalidBody::class);

        // Act
        // We need to set a wrong body as the default generated one is valid
        $openApiHelper->testPath('/pet', '/pet', 'put', 200, function (ServerRequestInterface $request) {
            return $request->withBody(Stream::create('{}'));
        });
    }

    /**
     * @return void
     */
    public function testTestPathThrowsValidationFailedExceptionWhenExpectedResponseCodeAndActualResponseCodeDontMatch(): void
    {
        // Arrange
        // Expected Response Code in the schema is 200 for this request, we return 201 to make the assertion on the response code fail.
        $this->tester->fakeResponse('/pet', 'put', new JsonResponse([], 201));

        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // Expect
        $this->expectException(ValidationFailedException::class);

        // Act
        $openApiHelper->testPath('/pet', '/pet', 'put', 200, function (ServerRequestInterface $request) {
            return $request->withBody(Stream::create(json_encode($this->tester->getValidPet())));
        });
    }

    /**
     * @return void
     */
    public function testTestPathWithValidRequestAndResponseReturnsResponse(): void
    {
        // Arrange
        $this->tester->fakeResponse('/pet', 'put', new JsonResponse($this->tester->getValidPet(), 200));

        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // Act
        $response = $openApiHelper->testPath('/pet', '/pet', 'put', 200, function (ServerRequestInterface $request) {
            return $request->withBody(Stream::create(json_encode($this->tester->getValidPet())));
        });

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testTestPathWithoutSchemaValidationValidRequestAndResponseReturnsResponse(): void
    {
        // Arrange
        $this->tester->fakeResponse('/pet', 'put', new JsonResponse($this->tester->getValidPet(), 200));

        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // Act
        $response = $openApiHelper->testPathWithoutSchemaValidation('/pet', '/pet', 'put', 200, function (ServerRequestInterface $request) {
            return $request->withBody(Stream::create(json_encode($this->tester->getValidPet())));
        });

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return void
     */
    public function testTestAllRunsOnlyDefinedPathTestsAndReturnsStatistics(): void
    {
        // Arrange
        $this->tester->fakeResponse('/pet', 'put', new JsonResponse($this->tester->getValidPet(), 200));

        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // We still only want one test to be executed as
        $openApiHelper->setDebugPath('/pet', 'put', 200);

        // Act
        $statistics = $openApiHelper->testAllPaths();

        $this->assertInstanceOf(Statistic::class, $statistics);
        $this->assertFalse($statistics->hasFailures());
    }

    /**
     * @return void
     */
    public function testTestAllFailsWhenNoTestWasExecuted(): void
    {
        // Arrange
        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));

        // We still only want one test to be executed as
        $openApiHelper->setDebugPath('/non-existent-endpoint', 'put', 200);

        // Expect
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No test was executed.');

        // Act
        $openApiHelper->testAllPaths();
    }

    /**
     * @return void
     */
    public function testTestAllPrintsTableWithInfoAtTheEnd(): void
    {
        // Arrange
        $this->tester->fakeResponse('/pet', 'put', new JsonResponse($this->tester->getValidPet(), 200));
        $openApiHelper = $this->tester->getOpenApiHelper();
        $openApiHelper->setOpenApi(codecept_data_dir('pet.yml'));
        $openApiHelper->setDebugPath('/pet', 'put', 200);

        // Act
        $openApiHelper->testAllPaths();

        // Assert
        $this->tester->assertOutputContains('200');
    }
}
