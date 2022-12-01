<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace TestifyOpenApi;

use Codeception\Actor;
use Codeception\Stub;
use PHPUnit\Framework\Assert;
use Spryker\Glue\TestifyOpenApi\Helper\OpenApiHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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
     * @var array<string>
     */
    protected array $output;

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
            'getOutput' => $this->getOutput(),
        ]);
    }

    /**
     * @return \Symfony\Component\Console\Output\ConsoleOutputInterface
     */
    protected function getOutput(): ConsoleOutputInterface
    {
        return Stub::construct(ConsoleOutput::class, [], [
            'write' => function (string $message) {
                $this->output[] = $message;
            },
            'writeln' => function (string $message) {
                $this->output[] = $message;
            },
        ]);
    }

    /**
     * @param string $expectedOutput
     *
     * @return void
     */
    public function assertOutputContains(string $expectedOutput): void
    {
        $found = false;

        foreach ($this->output as $row) {
            if (strpos($row, $expectedOutput) !== false) {
                $found = true;
            }
        }

        Assert::assertTrue($found, sprintf('Expected %s in the output, but wasn\'t found.', $expectedOutput));
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
            'id' => 10,
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

    /**
     * @return array
     */
    public function getValidStoreOrder(): array
    {
        return [
            'id' => 10,
            'petId' => 198772,
            'quantity' => 7,
            'shipDate' => '2022-12-01T13:51:46.495Z',
            'status' => 'approved',
            'complete' => true,
        ];
    }

    /**
     * @return array
     */
    public function getValidUser(): array
    {
        return [
            'id' => 10,
            'username' => 'theUser',
            'firstName' => 'John',
            'lastName' => 'James',
            'email' => 'john@email.com',
            'password' => '12345',
            'phone' => '12345',
            'userStatus' => 1,
        ];
    }

    /**
     * @return array
     */
    public function getValidUserWhiteList(): array
    {
        return [
            [
                'id' => 10,
                'username' => 'theUser',
                'firstName' => 'John',
                'lastName' => 'James',
                'email' => 'john@email.com',
                'password' => '12345',
                'phone' => '12345',
                'userStatus' => 1,
            ],
        ];
    }
}
