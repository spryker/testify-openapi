# TestifyOpenApi Module
[![Latest Stable Version](https://poser.pugx.org/spryker/testify-openapi/v/stable.svg)](https://packagist.org/packages/spryker/testify-openapi)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg)](https://php.net/)

TestifyOpenAPI provides basic test infrastructure for testing your API schema.

## Installation

```
composer require spryker/testify-openapi --dev
```

## Documentation

[Module Documentation](https://academy.spryker.com/developing_with_spryker/module_guide/modules.html)


# Integration

Add the `\Spryker\Glue\TestifyOpenApi\Helper\OpenApiHelper` helper to your codeception.yml then run the build command to get the new methods generated into your tester class.

This Helper offers the following methods:

- `setAccessToken()` you can pass a valid access token to this method which will be used for all requests that are secured.
- `setOpenApi()` this method sets the path to an OpenAPI schema file that will be used to test the API.
- `setDebugPath()` this method can be used when you want to run only one specific path together with the `testAllPaths()` method.
- `testAllPaths()` this method will use the schema file and runs each operation and tries to automatically create valid requests based on your schema definition. It also tries to make tests for all expected responses (not completed feature)
- `testPath()` this method will run exactly one path, can be used for edge cases or tests that can't be set up automatically.
- `testPathWithoutSchemaValidation()` this method will run exactly one path without schema validation, can be used for egde cases that requires invalid requests to be sent.
- `addHook()` this method takes an instance of an `\Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook` see the dedicated section for hooks.

## How this tool works

The OpenAPI schema file defines the API with all it's expected request attributes, expected response codes and the expected response bodies. To ensure that the schema files is in line what the code behind that API is doing it reads the necessary informations out of the passed schema file.

Out of the schema file a request is build and validated against the schema file. When this validation passes it will fire the request against the application. The application returns a response code which is validated against the in the schema file defined one.

## Working with data

In many cases you need to have data in the database to run tests against e.g. a GET /pets/{petId} requires to have one Pet in your database. You can either set it manually and use a defined URL with your created data or you can use a Hook that reacts to this kind of requests. See the Hooks section in this document.

## Working with Security

When the operation in the schema contains a security configuration (only works with Bearer token authorization ATM) this tool will require to add the proper security header.

To get a valid access token you need to add a `AccessTokenHelper` see https://github.com/spryker-projects/registry-service/blob/master/tests/RegistryTest/Glue/Testify/_support/Helper/AccessTokenHelper.php as an example implementation.

You can use what ever you like to use to get a valid access token BUT don't use the Auth0 API to create one for tests as it will cost money for each token.

## Using Hooks

Hooks are a way to manipulate in many ways the request that is sent against the application. Use the `\Spryker\Glue\TestifyOpenApi\Helper\Hook\AbstractHook` class to build a hook.

The Abstract class extends Codeceptions `\Codeception\Module` which is used to get access to other Helpers and for automatically attaching the current Hook into the OpenApiHelper with the `_beforeSuite()` method.

The Abstract class defines the following methods:

- `accept()` this method is used to decide if the current Hook should be used for the current request.
- `setUp` this method can be used to prepare data in the database or what ever needs to be set up before the request is sent.
- `getPathParameters()` this method can be used when the path contains path placeholder like in `/pets/{petId}`. The former created Pet with the `setUp()` method can save e.g. the id of the created pet and return it in this method so the generated URL will be against the existing database entity.
- `getQueryParameters()` same as for the `getPathParameters()` but it will be used for query parameters e.g. `/pets/{petId}?key=value`.
- `manipulateRequest()` this method can be used to further manipulate the created ServerRequestInterface to e.g. add headers.
