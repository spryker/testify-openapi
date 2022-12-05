<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper\Parser;

use cebe\openapi\spec\Schema;
use Exception;

class ValueResolver
{
    /**
     * @param \cebe\openapi\spec\Schema $schema
     *
     * @return array|string|int
     */
    public function getValueForSchema(Schema $schema): array|string|int
    {
        if (!$schema->items && !$schema->properties) {
            return $this->getValue($schema);
        }

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

        if ($schema->default) {
            return $schema->default;
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
            return $this->getValueForSchema($schema);
        }

        if ($type === 'array') {
            if (!$schema->items || !is_a($schema->items, Schema::class)) {
                throw new Exception(sprintf('Expected a Schema object but got: %s', gettype($schema->items)));
            }

            if ($schema->items->type !== 'object') {
                return [$this->getValue($schema->items)];
            }

            return [$this->getValueForSchema($schema->items)];
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
