<?php

namespace ActiveRecord\PhpStan\Relation;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

trait RelationReflectionHelper
{
    /**
     * @return string[]
     */
    protected function dynamicReturnMethods(): array
    {
        return ['first', 'last', 'find', 'take'];
    }

    /**
     * @param array<mixed> $args
     *
     * @throws \PHPStan\ShouldNotHappenException
     */
    protected function computeTypeFromArgs(string $name, array $args, Type $modelType, Scope $scope): Type
    {
        $args = array_map(static function ($arg) use ($scope) {
            assert($arg instanceof Arg);
            $val = $arg->value;

            return $scope->getType($val);
        }, $args);

        switch ($name) {
            case 'find':
                $numArgs = count($args);
                $single = false;
                $nullable = false;

                if (1 == $numArgs) {
                    if (!$args[0]->isArray()->yes()) {
                        $single = true;
                    }
                } elseif ($numArgs > 1) {
                    if (($args[0] instanceof ConstantStringType) && (
                        'first' === $args[0]->getValue()
                        || 'last' === $args[0]->getValue()
                    )) {
                        $single = true;
                        $nullable = true;
                    }
                }

                if ($single && $nullable) {
                    return new UnionType([
                        $modelType,
                        new NullType()
                    ]);
                } elseif ($single) {
                    return $modelType;
                }

                return new ArrayType(new IntegerType(), $modelType);

            case 'first':
            case 'last':
            case 'take':
                $numArgs = count($args);
                if (1 == $numArgs) {
                    return new ArrayType(
                        new IntegerType(),
                        $modelType
                    );
                }

                return new UnionType([
                    $modelType,
                    new NullType()
                ]);

            default:
                throw new \Exception('Unknown method');
        }
    }
}
