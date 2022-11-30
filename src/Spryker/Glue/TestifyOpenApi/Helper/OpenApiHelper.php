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
use cebe\openapi\spec\Response as OperationResponse;
use cebe\openapi\spec\Schema;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\Stub;
use Codeception\TestInterface;
use Exception;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
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
use Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook;
use Spryker\Glue\TestifyOpenApi\Helper\Parser\RequestBodyBuilder;
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
     * @var array<string, string>
     */
    protected array $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface|null
     */
    protected ?HttpKernelInterface $application = null;

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutputInterface|null
     */
    protected ?ConsoleOutputInterface $output = null;

    /**
     * @var array<string, string|int>
     */
    protected array $definedHeaders = [];

    /**
     * @var array<string, string|int>
     */
    protected array $definedPathParameters = [];

    /**
     * @var array<string, string|int>
     */
    protected array $definedQueryParameters = [];

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
        $this->application = $this->getApplication();
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
     * @param array $headers
     *
     * @return void
     */
    public function setDefaultHeaders(array $headers): void
    {
        $this->defaultHeaders = $headers;
    }

    /**
     * @param array<string, string|int> $headers
     *
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->definedHeaders = $headers;
    }

    /**
     * @param array<string, string|int> $pathParameters
     *
     * @return void
     */
    public function setPathParameters(array $pathParameters): void
    {
        $this->definedPathParameters = $pathParameters;
    }

    /**
     * @param array<string, string|int> $pathQueryParameters
     *
     * @return void
     */
    public function setQueryParameters(array $pathQueryParameters): void
    {
        $this->definedQueryParameters = $pathQueryParameters;
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
     * @param callable|null $requestManipulator
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPath(string $path, string $url, string $method, ?callable $requestManipulator = null): ResponseInterface
    {
        $this->getStatistics()->recordTest();

        $request = $this->createRequest($url, $method, $this->defaultHeaders);

        if ($requestManipulator) {
            $request = $requestManipulator($request);
        }

        $operationAddress = $this->validateRequest($request);

        $response = $this->handleRequestInTheGlueApplication($request, $path);

        $this->validateResponse($response, $operationAddress);

        return $response;
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
     * @param callable|null $requestManipulator
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPathWithoutSchemaValidation(
        string $path,
        string $url,
        string $method,
        ?callable $requestManipulator = null
    ): ResponseInterface {
        $this->getStatistics()->recordTest();

        $request = $this->createRequest($url, $method, $this->defaultHeaders);

        if ($requestManipulator) {
            $request = $requestManipulator($request);
        }

        $response = $this->handleRequestInTheGlueApplication($request, $path);

        $this->validateResponse($response, $this->getOperationAddress($request));

        return $response;
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

        $name = sprintf('%s|%s|%s', $path, $method, $responseCode);

        if (!is_numeric($responseCode)) {
            $this->getStatistics()->addWarning($path, $method, 'Response code is not numeric and can\'t be tested automatically.');

            return;
        }

        $this->getOutput()->writeln(sprintf('Searching hook point for %s', $name));
        $hook = $this->findHook($path, $method, (int)$responseCode);

        $headers = [];

        if ($this->hasRequiredHeaders($operation)) {
            $this->getOutput()->writeln(sprintf('Path %s has headers, searching for possible values.', $path));
            $headers = $this->resolveRequiredHeaders($operation, $headers, $path, $hook);
        }

        $requestManipulator = function (ServerRequestInterface $request) use ($headers, $operation) {
            $requestBody = (new RequestBodyBuilder())->buildRequestBody($operation);

            if ($requestBody) {
                $request = $request->withBody($requestBody);
            }

            if ($this->requiresBearerAuthentication($operation)) {
                $request = $request->withHeader('Authorization', 'Bearer ' . Uuid::uuid4());
            }

            foreach ($headers as $headerName => $headerValue) {
                $request = $request->withHeader($headerName, $headerValue);
            }

            return $request;
        };

        $url = $this->generateUrl($path, $operation, $hook);
        $this->getOutput()->writeln(sprintf('Generated URL: %s', $url));

        $this->getOutput()->writeln('Preparing request');

        // Run application
        try {
            $this->getOutput()->writeln('Sending request...');
            $this->testPath($path, $url, $method, $requestManipulator);
            $this->getOutput()->writeln('<fg=green>Received request, seems to be all good.</>');
        } catch (Throwable $exception) {
            $this->getStatistics()->addFailure($path, $method, $exception->getMessage());
            $this->getOutput()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
        }
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     *
     * @return bool
     */
    protected function requiresBearerAuthentication(Operation $operation): bool
    {
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
                $this->getOutput()->writeln('');
                $this->getOutput()->writeln(sprintf('Found hook: %s', $hook->getDescription()));

                $hook->setUp();

                return $hook;
            }
        }

        $this->getOutput()->writeln('');
        $this->getOutput()->writeln('No hook found');

        return null;
    }

    /**
     * Replace path parameters with values and add query parameters to the in the schema defined endpoint.
     *
     * In the schema you will most likely have paths defined like `/foo/{bar}` + maybe defined query parameters.
     * By the given schema we will replace the placeholder with values and add query parameters to the URL.
     *
     * @param string $endpoint
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function generateUrl(string $endpoint, Operation $operation, ?AbstractHook $hook): string
    {
        $endpoint = $this->addPathParameter($endpoint, $operation, $hook);

        return $this->addQueryParameter($endpoint, $operation, $hook);
    }

    /**
     * @param string $endpoint
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function addPathParameter(string $endpoint, Operation $operation, ?AbstractHook $hook): string
    {
        $pathParams = $this->resolveParams($operation, 'path', $endpoint, $hook);

        foreach ($pathParams as $pathParamName => $pathParamValue) {
            $endpoint = str_replace(sprintf('{%s}', $pathParamName), $pathParamValue, $endpoint);
        }

        return $endpoint;
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param string $type
     * @param string $endpoint
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return array
     */
    protected function resolveParams(Operation $operation, string $type, string $endpoint, ?AbstractHook $hook): array
    {
        $resolvedParameters = [];

        /** @var \cebe\openapi\spec\Parameter $parameter */
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in === $type) {
                $resolvedParameters[$parameter->name] = $this->resolveValueForParameter($parameter, $endpoint, $type, $hook);
            }
        }

        return $resolvedParameters;
    }

    /**
     * @param string $endpoint
     * @param \cebe\openapi\spec\Operation $operation
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return string
     */
    protected function addQueryParameter(string $endpoint, Operation $operation, ?AbstractHook $hook): string
    {
        $resolvedQueryParams = $this->resolveParams($operation, 'query', $endpoint, $hook);
        $queryParameters = [];

        foreach ($resolvedQueryParams as $queryParamName => $queryParamValue) {
            $queryParameters[] = sprintf('%s=%s', $queryParamName, $queryParamValue);
        }

        if (!count($queryParameters)) {
            return $endpoint;
        }

        return sprintf('%s?%s', $endpoint, implode('&', $queryParameters));
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
     * @param array $headers
     * @param string $path
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @return array<string, string>
     */
    protected function resolveRequiredHeaders(Operation $operation, array $headers, string $path, ?AbstractHook $hook): array
    {
        /** @var \cebe\openapi\spec\Parameter $parameter */
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in === 'header' && $parameter->required) {
                $headers[$parameter->name] = $this->resolveValueForParameter($parameter, $path, 'header', $hook);
            }
        }

        return $headers;
    }

    /**
     * @param \cebe\openapi\spec\Parameter $parameter
     * @param string $path
     * @param string $type
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook|null $hook
     *
     * @throws \Exception
     *
     * @return string|int
     */
    protected function resolveValueForParameter(Parameter $parameter, string $path, string $type, ?AbstractHook $hook)
    {
        if (isset($this->definedHeaders[$parameter->name])) {
            return $this->definedHeaders[$parameter->name];
        }

        if ($hook) {
            $resolvedValue = $hook->resolveValue($parameter->name, $type);

            if ($resolvedValue) {
                return $resolvedValue;
            }
        }

        if (!$parameter->schema || !is_a($parameter->schema, Schema::class)) {
            throw new Exception(sprintf('Parameter %s doesn\'t have a schema defined. Can\'t get a value for it.', $parameter->name));
        }

        return $resolvedValue ?? $this->getValueForParameter($parameter->schema, $parameter, $path);
    }

    /**
     * Tries different ways to get a value for a parameter. In the schema of a property we can have examples, enums or other definitions
     * to specify for the user of this API what he should use.
     *
     * When there is no way to get it out of examples or enums we generate a random value based on the schema definition or a random one
     * for the defined type.
     *
     * @param \cebe\openapi\spec\Schema $schema
     * @param \cebe\openapi\spec\Parameter $parameter
     * @param string $path
     *
     * @throws \Exception
     *
     * @return string|int
     */
    protected function getValueForParameter(Schema $schema, Parameter $parameter, string $path)
    {
        if ($schema->example) {
            return $schema->example;
        }

        if ($schema->enum) {
            // @TODO We should generate some mutations for this or at least randomly chose a value.
            // ALl possible enums should be tested otherwise we can get randomly failing tests because one of the enums
            // could be valid and another one not.
            return $schema->enum[0];
        }

        $this->getOutput()->writeln(sprintf('Parameter <fg=yellow>%s</> for endpoint <fg=yellow>%s</> doesn\'t have an example or en enum defined. Try to guess one from the defined type <fg=yellow>%s</>.', $parameter->name, $path, $schema->type));

        if ($schema->type === 'string') {
            return 'asdftzuibin'; // @TODO replace with faker
        }
        if ($schema->type === 'int') {
            return 12345; // @TODO replace with faker
        }

        throw new Exception(sprintf('Couldn\'t guess a value for type <fg=yellow>%s</>', $schema->type));
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
     * @param string $endpoint
     * @param string $method
     * @param array $headers
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createRequest(string $endpoint, string $method, array $headers = []): ServerRequestInterface
    {
        $request = (new Psr17Factory())->createServerRequest($method, $endpoint);

        foreach ($headers as $headerName => $headerValue) {
            $request = $request->withHeader($headerName, $headerValue);
        }

        // We need to manually add the query parameters to the request which is not done by the request factory automatically.
        $parts = parse_url($endpoint);

        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);

            $request = $request->withQueryParams($query);
        }

        return $request;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \League\OpenAPIValidation\PSR7\OperationAddress
     */
    protected function validateRequest(ServerRequestInterface $request): OperationAddress
    {
        $requestValidator = new ServerRequestValidator($this->getOpenApi());

        return $requestValidator->validate($request);
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
     *
     * @return void
     */
    protected function validateResponse(ResponseInterface $response, OperationAddress $operationAddress): void
    {
        $requestValidator = new ResponseValidator($this->getOpenApi());
        $requestValidator->validate($operationAddress, $response);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $psrRequest
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function handleRequestInTheGlueApplication(ServerRequestInterface $psrRequest, string $path): ResponseInterface
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

        $this->getStatistics()->addRequestsResponses($path, $psrRequest, $psrResponse, $symfonyRequest, $symfonyResponse);

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

            public function __construct()
            {
                $this->innerApplication = (new GlueBootstrap())->boot();
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
                $reflectionClass = new ReflectionClass($this->innerApplication);
                $reflectionGlueApplicationBootstrapPluginProperty = $reflectionClass->getProperty('glueApplicationBootstrapPlugin');
                $reflectionGlueApplicationBootstrapPluginProperty->setAccessible(true);

                /** @var \Spryker\Glue\GlueApplicationExtension\Dependency\Plugin\GlueApplicationBootstrapPluginInterface $glueApplicationBootstrapPlugin */
                $glueApplicationBootstrapPlugin = $reflectionGlueApplicationBootstrapPluginProperty->getValue($this->innerApplication);

                /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $application */
                $application = $glueApplicationBootstrapPlugin->getApplication();

                return $application->handle($request);
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
