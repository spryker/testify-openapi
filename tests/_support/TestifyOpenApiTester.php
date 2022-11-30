<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace TestifyOpenApi;

use Codeception\Actor;
use Codeception\Stub;
use Spryker\Glue\TestifyOpenApi\Helper\OpenApiHelper;
use Symfony\Component\HttpFoundation\Response;
use TestifyOpenApi\Application\ApplicationStub;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class TestifyOpenApiTester extends Actor
{
    use _generated\TestifyOpenApiTesterActions;

    /**
     * @var array
     */
    protected array $responsesForRequests = [];

    /**
     * @return \Spryker\Glue\TestifyOpenApi\Helper\OpenApiHelper
     */
    public function getOpenApiHelper(): OpenApiHelper
    {
        // We need to have an application mock running as set up Glue Bootstrap to run with our tests would be too much effort.
        return Stub::make(OpenApiHelper::class, [
            'getApplication' => new ApplicationStub($this->responsesForRequests),
        ]);
    }

    /**
     * @param string $path
     * @param string $method
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function fakeResponse(string $path, string $method, Response $response): void
    {
        $this->responsesForRequests[] = [
            'url' => $path,
            'method' => $method,
            'response' => $response,
        ];
    }

    /**
     * @return array
     */
    public function getValidPet(): array
    {
        return [
            'id' => 1,
            'name' => 'doggie',
            'category' => [
                'id' => 1,
                'name' => 'Dogs',
            ],
            'photoUrls' => [
                'string',
            ],
            'tags' => [
                [
                    'id' => 0,
                    'name' => 'string',
                ],
            ],
            'status' => 'available',
        ];
    }
}
