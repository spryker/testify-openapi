<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response as OperationResponse;
use cebe\openapi\spec\Schema;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\Stub;
use Codeception\TestInterface;
use Exception;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidHeaders;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Spryker\Glue\AuthRestApi\AuthRestApiFactory;
use Spryker\Glue\AuthRestApi\Plugin\GlueApplication\AccessTokenRestRequestValidatorPlugin;
use Spryker\Glue\AuthRestApi\Processor\AccessTokens\AccessTokenValidator;
use Spryker\Glue\GlueApplication\Bootstrap\GlueBootstrap;
use Spryker\Glue\GlueApplication\GlueApplicationDependencyProvider;
use Spryker\Glue\RestRequestValidator\Plugin\ValidateRestRequestAttributesPlugin;
use Spryker\Glue\TestifyOpenApi\Exception\InvalidParameterValueException;
use Spryker\Glue\TestifyOpenApi\Exception\OperationNotFoundException;
use Spryker\Glue\TestifyOpenApi\Exception\ValidationFailedException;
use Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook;
use Spryker\Glue\TestifyOpenApi\Helper\Parser\RequestBodyBuilder;
use Spryker\Glue\TestifyOpenApi\Helper\Parser\ValueResolver;
use Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic;
use Spryker\Glue\TestifyOpenApi\Helper\Statistic\StatisticConsolePrinter;
use Spryker\Shared\Application\ApplicationInterface;
use Spryker\Shared\Config\Application\Environment;
use Spryker\Shared\ErrorHandler\ErrorHandlerEnvironment;
use SprykerTest\Shared\Testify\Helper\DependencyHelperTrait;
use SprykerTest\Zed\Testify\Helper\Business\BusinessHelperTrait;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

class OpenApiHelper extends Module
{
    use DependencyHelperTrait;
    use BusinessHelperTrait;

    /**
     * @var \cebe\openapi\spec\OpenApi|null
     */
    protected ?OpenApi $openApi = null;

    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface|null
     */
    protected ?HttpKernelInterface $application = null;

    /**
     * @var string|null
     */
    protected ?string $accessToken = null;

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutputInterface|null
     */
    protected ?ConsoleOutputInterface $output = null;

    /**
     * Use this to debug only one specific endpoint. Use `setDebugPath()` method to run only one specified test case
     *
     * @example
     *
     * $endpoint = [
     *      'endpoint' => '/foo/{param}',
     *      'method' => 'get',
     *      'responseCode => 200
     * ];
     *
     * @var array|null
     */
    protected ?array $debugPath = null;

    /**
     * @var array<\Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook>
     */
    protected static $hooks = [];

    /**
     * @var \Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic|null
     */
    protected ?Statistic $statistic = null;

    /**
     * @codeCoverageIgnore
     *
     * @param \Codeception\Lib\ModuleContainer $moduleContainer
     * @param array|null $config
     */
    public function __construct(ModuleContainer $moduleContainer, $config = null)
    {
        parent::__construct($moduleContainer, $config);
    }

    /**
     * @param string $accessToken
     *
     * @return void
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return \Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic
     */
    protected function getStatistics(): Statistic
    {
        if ($this->statistic === null) {
            $this->statistic = new Statistic();
        }

        return $this->statistic;
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    protected function getOutput(): OutputInterface
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param \Codeception\TestInterface $test
     *
     * @return void
     */
    public function _before(TestInterface $test): void
    {
        $this->statistic = null; // Needs to be unsetted to have a fresh one for each test case.
    }

    /**
     * @param string $openApiFilename
     *
     * @return \cebe\openapi\spec\OpenApi
     */
    public function setOpenApi(string $openApiFilename): OpenApi
    {
        return $this->openApi = Reader::readFromYamlFile((string)realpath($openApiFilename));
    }

    /**
     * @return \cebe\openapi\spec\OpenApi
     */
    protected function getOpenApi(): OpenApi
    {
        if (!$this->openApi) {
            $this->fail(sprintf('Expected an OpenAPI schema to test against, none found. Please provide one with the %s::setOpenApi(string $openApiFilename) method.', static::class));
        }

        return $this->openApi;
    }

    /**
     * @param string $path
     * @param string $method
     * @param int $responseCode
     *
     * @return void
     */
    public function setDebugPath(string $path, string $method, int $responseCode): void
    {
        $this->debugPath = [
            'path' => $path,
            'method' => $method,
            'responseCode' => $responseCode,
        ];
    }

    /**
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook $hook
     *
     * @return void
     */
    public static function addHook(AbstractHook $hook): void
    {
        static::$hooks[] = $hook;
    }

    /**
     * @param string $path
     * @param string $url
     * @param string $method
     * @param int $expectedResponseCode
     * @param callable|null $requestManipulator
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPath(string $path, string $url, string $method, int $expectedResponseCode, ?callable $requestManipulator = null): ResponseInterface
    {
        return $this->doTestPath($path, $url, $method, $expectedResponseCode, true, $requestManipulator);
    }

    /**
     * Use this method to run tests without OpenAPI schema validation.
     *
     * This should only be used for testing edge cases which would cause exceptions in the schema validation and would
     * let us not run tests with invalid requests.
     *
     * @param string $path
     * @param string $url
     * @param string $method
     * @param int $expectedResponseCode
     * @param callable|null $requestManipulator
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPathWithoutSchemaValidation(
        string $path,
        string $url,
        string $method,
        int $expectedResponseCode,
        ?callable $requestManipulator = null
    ): ResponseInterface {
        return $this->doTestPath($path, $url, $method, $expectedResponseCode, false, $requestManipulator);
    }

    /**
     * @param string $path
     * @param string|null $url
     * @param string $method
     * @param int $expectedResponseCode
     * @param bool $requestValidationEnabled
     * @param callable|null $requestManipulator
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function doTestPath(
        string $path,
        ?string $url,
        string $method,
        int $expectedResponseCode,
        bool $requestValidationEnabled = true,
        ?callable $requestManipulator = null
    ): ResponseInterface {
        $this->getStatistics()->recordTest();

        $hook = $this->findHook($path, $method, $expectedResponseCode);

        $operation = $this->getOperation($path, $method);

        $headers = $this->resolveRequiredHeaders($operation, $path, $hook);

        $finalRequestManipulator = function (ServerRequestInterface $request) use ($headers, $operation, $requestManipulator) {
            // We first need to add the required request headers otherwise we can't get a valida request body if needed.
            foreach ($headers as $headerName => $headerValue) {
                $request = $request->withHeader($headerName, $headerValue);
            }

            $requestBody = (new RequestBodyBuilder())->buildRequestBody($operation, $request);

            if ($requestBody) {
                $request = $request->withBody($requestBody);
            }

            if ($this->requiresBearerAuthentication($operation)) {
                $request = $request->withHeader('Authorization', 'Bearer ' . $this->getAccessToken());
            }

            if ($requestManipulator) {
                return $requestManipulator($request);
            }

            return $request;
        };

        if (!$url) {
            $url = $this->generateUrl($path, $operation, $hook);
        }

        $request = $this->createRequest($url, $method);

        $request = $finalRequestManipulator($request);

        $operationAddress = $this->validateRequest($request, $requestValidationEnabled);

        // Force a 400 Invalid Request
        if ($expectedResponseCode === 400) {
            // Empty the body to get back a 400 Invalid Request
            $request = $request->withBody(Stream::create(''));
        }

        // Force a 401 Unauthorized
        if ($expectedResponseCode === 401) {
            // Wrong Bearer should result in a 401
            $request = $request->withHeader('Authorization', 'Bearer 12345');
        }

        // Force a 403 Forbidden
        if ($expectedResponseCode === 403) {
            // Remove Authorization header should result in a 403
            $request = $request->withoutHeader('Authorization');
        }

        if ($hook) {
            $request = $hook->manipulateRequest($request, $expectedResponseCode);
        }

        $response = $this->handleRequestInTheGlueApplication($request, $path, $expectedResponseCode);

        $this->validateResponse($response, $operationAddress, $method, $path, $expectedResponseCode);

        return $response;
    }

    /**
     * @return string
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        return Uuid::uuid4()->toString();
    }

    /**
     * @param string $lookupPath
     * @param string $lookupMethod
     *
     * @throws \Spryker\Glue\TestifyOpenApi\Exception\OperationNotFoundException
     *
     * @return \cebe\openapi\spec\Operation
     */
    protected function getOperation(string $lookupPath, string $lookupMethod): Operation
    {
        foreach ($this->getOpenApi()->paths as $path => $pathItem) {
            if ($lookupPath !== $path) {
                continue;
            }

            foreach ($pathItem->getOperations() as $method => $operation) {
                if (strtolower($lookupMethod) === strtolower($method)) {
                    return $operation;
                }
            }
        }

        throw new OperationNotFoundException(sprintf('Couldn\'t find an operation for [%s] %s', $lookupMethod, $lookupPath));
    }

    /**
     * @return \Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic
     */
    public function testAllPaths(): Statistic
    {
        foreach ($this->getOpenApi()->paths as $endpoint => $pathItem) {
            $this->executePathItem($endpoint, $pathItem);
        }

        $this->printStatistics();

        if ($this->getStatistics()->hasFailures()) {
            $this->fail('Couldn\'t validate your schema file. See output above.');
        }

        if ($this->getStatistics()->getTotalNumberOfTest() === 0) {
            $this->fail('No test was executed.');
        }

        return $this->getStatistics();
    }

    /**
     * @param string $endpoint
     * @param \cebe\openapi\spec\PathItem $pathItem
     *
     * @return void
     */
    protected function executePathItem(string $endpoint, PathItem $pathItem): void
    {
        foreach ($pathItem->getOperations() as $method => $operation) {
            $this->executeOperation($endpoint, $method, $pathItem, $operation);
        }
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param \cebe\openapi\spec\PathItem $pathItem
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return void
     */
    protected function executeOperation(string $endpoint, string $method, PathItem $pathItem, Operation $operation): void
    {
        if (!$operation->responses) {
            return;
        }

        foreach ($operation->responses as $responseCode => $response) {
            $this->execute($endpoint, $method, $responseCode, $pathItem, $operation, $response);
        }
    }

    /**
     * @param string $path
     * @param string $method
     * @param string|int $responseCode
     * @param \cebe\openapi\spec\PathItem $pathItem
     * @param \cebe\openapi\spec\Operation $operation
     * @param \cebe\openapi\spec\Response $response
     *
     * @return void
     */
    protected function execute(string $path, string $method, $responseCode, PathItem $pathItem, Operation $operation, OperationResponse $response): void
    {
        if ($this->debugPath && ($this->debugPath['path'] !== $path || $this->debugPath['method'] !== $method || (string)$this->debugPath['responseCode'] !== (string)$responseCode)) {
            return;
        }

        if (!is_numeric($responseCode)) {
            $this->getStatistics()->addWarning($path, $method, $responseCode, 'Response code is not numeric and can\'t be tested automatically.');

            return;
        }

        // Run application
        try {
            $this->doTestPath($path, null, $method, (int)$responseCode);
        } catch (Throwable $exception) {
            $this->getStatistics()->addFailure($path, $method, (int)$responseCode, $exception->getMessage());
        }
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return bool
     */
    protected function requiresBearerAuthentication(Operation $operation): bool
    {
        if (!isset($operation->security)) {
            return false;
        }

        foreach ($operation->security as $securityRequirement) {
            $security = (array)$securityRequirement->getSerializableData();

            if (isset($security['BearerAuth'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @param string $method
     * @param int $responseCode
     *
     * @return \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null
     */
    protected function findHook(string $path, string $method, int $responseCode): ?AbstractHook
    {
        foreach (static::$hooks as $hook) {
            if ($hook->accept($path, $method, $responseCode)) {
                $hook->setUp();

                return $hook;
            }
        }

        return null;
    }

    /**
     * Replace path parameters with values and add query parameters to the in the schema defined endpoint.
     *
     * In the schema you will most likely have paths defined like `/foo/{bar}` + maybe defined query parameters.
     * By the given schema we will replace the placeholder with values and add query parameters to the URL.
     *
     * @param string $path
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function generateUrl(string $path, Operation $operation, ?AbstractHook $hook): string
    {
        $path = $this->addPathParameter($path, $operation, $hook);

        return $this->addQueryParameter($path, $operation, $hook);
    }

    /**
     * @param string $path
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function addPathParameter(string $path, Operation $operation, ?AbstractHook $hook): string
    {
        $pathParams = $this->resolveParams($operation, 'path', $hook);

        foreach ($pathParams as $pathParamName => $pathParamValue) {
            $path = str_replace(sprintf('{%s}', $pathParamName), $pathParamValue, $path);
        }

        return $path;
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param string $type
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return array
     */
    protected function resolveParams(Operation $operation, string $type, ?AbstractHook $hook): array
    {
        $resolvedParameters = [];

        /** @var \cebe\openapi\spec\Parameter $parameter */
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in === $type) {
                $resolvedParameters[$parameter->name] = $this->resolveValueForParameter($parameter, $type, $hook);
            }
        }

        return $resolvedParameters;
    }

    /**
     * @param string $path
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function addQueryParameter(string $path, Operation $operation, ?AbstractHook $hook): string
    {
        $resolvedQueryParams = $this->resolveParams($operation, 'query', $hook);
        $queryParameters = [];

        foreach ($resolvedQueryParams as $queryParamName => $queryParamValue) {
            $queryParameters[] = sprintf('%s=%s', $queryParamName, $queryParamValue);
        }

        if (!count($queryParameters)) {
            return $path;
        }

        return sprintf('%s?%s', $path, implode('&', $queryParameters));
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return bool
     */
    protected function hasRequiredHeaders(Operation $operation): bool
    {
        /** @var \cebe\openapi\spec\Parameter $parameter */
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in === 'header' && $parameter->required) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param string $path
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return array<string, string>
     */
    protected function resolveRequiredHeaders(Operation $operation, string $path, ?AbstractHook $hook): array
    {
        $headers = [];

        /** @var \cebe\openapi\spec\Parameter $parameter */
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in === 'header' && $parameter->required) {
                $headers[$parameter->name] = (string)$this->resolveValueForParameter($parameter, 'header', $hook);
            }
        }

        if ($operation->requestBody && is_a($operation->requestBody, RequestBody::class) && $operation->requestBody->content) {
            // @TODO Add correct Content-Type header. Maybe set an expected one before running the tests?
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * @param \cebe\openapi\spec\Parameter $parameter
     * @param string $type
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @throws \Exception
     * @throws \Spryker\Glue\TestifyOpenApi\Exception\InvalidParameterValueException
     *
     * @return string|int
     */
    protected function resolveValueForParameter(Parameter $parameter, string $type, ?AbstractHook $hook)
    {
        if ($hook) {
            $resolvedValue = $hook->resolveValue($parameter->name, $type);

            if ($resolvedValue) {
                return $resolvedValue;
            }
        }

        if (!$parameter->schema || !is_a($parameter->schema, Schema::class)) {
            throw new Exception(sprintf('Parameter %s doesn\'t have a schema defined. Can\'t get a value for it.', $parameter->name));
        }

        $returnValue = $resolvedValue ?? $this->getValueForParameter($parameter->schema);

        if (is_array($returnValue)) {
            throw new InvalidParameterValueException(sprintf('Expected string or int but got: %s', gettype($returnValue)));
        }

        return $returnValue;
    }

    /**
     * Tries different ways to get a value for a parameter. In the schema of a property we can have examples, enums or other definitions
     * to specify for the user of this API what he should use.
     *
     * When there is no way to get it out of examples or enums we generate a random value based on the schema definition or a random one
     * for the defined type.
     *
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return array|string|int
     */
    protected function getValueForParameter(Schema $schema)
    {
        return (new ValueResolver())->getValueForSchema($schema);
    }

    /**
     * Use this method for testing with enabled (default) AccessTokenRequestValidatorPlugin to test the validation.
     * This requires to run tests with the `testEndpointWithoutSchemaValidation` method otherwise the request validator
     * would already fail because it does already some validation on the security defined in the schema file.
     *
     * @codeCoverageIgnore Only useful in Context of a Spryker Application
     *
     * @return void
     */
    public function letAccessTokenValidationFail(): void
    {
        $accessTokenValidatorMock = Stub::make(AccessTokenValidator::class, [
            'validateAccessToken' => false,
        ]);
        $authRestApiFactory = Stub::make(AuthRestApiFactory::class, [
            'createAccessTokenValidator' => $accessTokenValidatorMock,
        ]);

        $accessTokenRestRequestValidatorPlugin = new AccessTokenRestRequestValidatorPlugin();
        $accessTokenRestRequestValidatorPlugin->setFactory($authRestApiFactory);

        $this->getDependencyHelper()->setDependency(GlueApplicationDependencyProvider::PLUGIN_REST_REQUEST_VALIDATOR, [
            new ValidateRestRequestAttributesPlugin(),
            $accessTokenRestRequestValidatorPlugin,
        ]);
    }

    /**
     * This will remove the AccessTokenRestRequestValidatorPlugin from the project stack
     *
     * @TODO We need to get the original stack and remove just the AccessTokenRestRequestValidatorPlugin instead.
     *
     * @codeCoverageIgnore Only useful in Context of a Spryker Application
     *
     * @return void
     */
    public function disableAccessTokenValidation(): void
    {
        $this->getDependencyHelper()->setDependency(GlueApplicationDependencyProvider::PLUGIN_REST_REQUEST_VALIDATOR, [
            new ValidateRestRequestAttributesPlugin(),
        ]);
    }

    /**
     * Decodes a JSON response body.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array
     */
    public function getDecodedResponseBody(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true);
    }

    /**
     * @param string $path
     * @param string $method
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createRequest(string $path, string $method): ServerRequestInterface
    {
        $request = (new Psr17Factory())->createServerRequest($method, $path);

        // We need to manually add the query parameters to the request which is not done by the request factory automatically.
        $parts = parse_url($path);

        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);

            $request = $request->withQueryParams($query);
        }

        return $request;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param bool $requestValidationEnabled
     *
     * @throws \Spryker\Glue\TestifyOpenApi\Exception\ValidationFailedException
     *
     * @return \League\OpenAPIValidation\PSR7\OperationAddress
     */
    protected function validateRequest(ServerRequestInterface $request, bool $requestValidationEnabled): OperationAddress
    {
        if (!$requestValidationEnabled) {
            return $this->getOperationAddress($request);
        }

        $requestValidator = new ServerRequestValidator($this->getOpenApi());

        try {
            return $requestValidator->validate($request);
        } catch (InvalidHeaders $invalidHeaders) {
            throw new ValidationFailedException(sprintf('Headers seem to be missing. Exception: %s Passed headers: %s', $invalidHeaders->getMessage(), $this->implodeHeadersForException($request)), 0, $invalidHeaders);
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return string
     */
    protected function implodeHeadersForException(ServerRequestInterface $request): string
    {
        $headerStrings = [];

        foreach ($request->getHeaders() as $headerName => $headerValues) {
            $headerStrings[] = sprintf('%s: %s', $headerName, implode(', ', $headerValues));
        }

        return implode('; ', $headerStrings);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \League\OpenAPIValidation\PSR7\OperationAddress
     */
    protected function getOperationAddress(ServerRequestInterface $request): OperationAddress
    {
        $pathFinder = new PathFinder($this->getOpenApi(), (string)$request->getUri(), $request->getMethod());

        return $pathFinder->search()[0];
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param \League\OpenAPIValidation\PSR7\OperationAddress $operationAddress
     * @param string $method
     * @param string $path
     * @param int $expectedResponseCode
     *
     * @throws \Spryker\Glue\TestifyOpenApi\Exception\ValidationFailedException
     *
     * @return void
     */
    protected function validateResponse(
        ResponseInterface $response,
        OperationAddress $operationAddress,
        string $method,
        string $path,
        int $expectedResponseCode
    ): void {
        try {
            $requestValidator = new ResponseValidator($this->getOpenApi());
            $requestValidator->validate($operationAddress, $response);
        } catch (ValidationFailed $exception) {
            $responseBody = PHP_EOL . PHP_EOL . (string)$response->getBody();

            throw new ValidationFailedException(sprintf('Validation for [%s] %s with expected response code %s failed. %s%s', strtoupper($method), $path, $expectedResponseCode, $exception->getMessage(), $responseBody), 0, $exception);
        }

        Assert::assertSame($expectedResponseCode, $response->getStatusCode(), sprintf('[%s] %s returned a %s but expected response code is %s', $method, $path, $response->getStatusCode(), $expectedResponseCode));
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $psrRequest
     * @param string $path
     * @param int $expectedResponseCode
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function handleRequestInTheGlueApplication(ServerRequestInterface $psrRequest, string $path, int $expectedResponseCode): ResponseInterface
    {
        // Convert to Symfony HttpFoundation Request
        $httpFoundationFactory = new HttpFoundationFactory();
        $symfonyRequest = $httpFoundationFactory->createRequest($psrRequest);

        // Run the Glue Application
        $symfonyResponse = $this->getApplication()->handle($symfonyRequest);

        // Convert Symfony HttpFoundation Response to PSR 7 Response
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $psrResponse = $psrHttpFactory->createResponse($symfonyResponse);

        $this->getStatistics()->addRequestsResponses($path, $expectedResponseCode, $psrRequest, $psrResponse, $symfonyRequest, $symfonyResponse);

        return $psrResponse;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected function getApplication(): HttpKernelInterface
    {
        if ($this->application) {
            return $this->application;
        }

        Environment::initialize();

        $errorHandlerEnvironment = new ErrorHandlerEnvironment();
        $errorHandlerEnvironment->initialize();

        $this->application = new class () implements HttpKernelInterface {
            /**
             * @var \Spryker\Shared\Application\ApplicationInterface
             */
            protected ApplicationInterface $innerApplication;

            /**
             * @var \Symfony\Component\HttpKernel\HttpKernelInterface
             */
            protected HttpKernelInterface $application;

            public function __construct()
            {
                $this->innerApplication = (new GlueBootstrap())->boot();

                $reflectionClass = new ReflectionClass($this->innerApplication);
                $reflectionGlueApplicationBootstrapPluginProperty = $reflectionClass->getProperty('glueApplicationBootstrapPlugin');
                $reflectionGlueApplicationBootstrapPluginProperty->setAccessible(true);

                /** @var \Spryker\Glue\GlueApplicationExtension\Dependency\Plugin\GlueApplicationBootstrapPluginInterface $glueApplicationBootstrapPlugin */
                $glueApplicationBootstrapPlugin = $reflectionGlueApplicationBootstrapPluginProperty->getValue($this->innerApplication);

                /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $application */
                $application = $glueApplicationBootstrapPlugin->getApplication();
                $this->application = $application;
            }

            /**
             * @param \Symfony\Component\HttpFoundation\Request $request
             * @param int $type
             * @param bool $catch
             *
             * @return \Symfony\Component\HttpFoundation\Response
             */
            public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
            {
                return $this->application->handle($request);
            }
        };

        return $this->application;
    }

    /**
     * @return void
     */
    protected function printStatistics(): void
    {
        $statisticsConsolePrinter = new StatisticConsolePrinter();
        $statisticsConsolePrinter->printReport($this->getStatistics(), $this->getOutput());
    }
}
