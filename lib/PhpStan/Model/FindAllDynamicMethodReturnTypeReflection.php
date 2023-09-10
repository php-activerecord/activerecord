<?php

namespace ActiveRecord\PhpStan;

namespace ActiveRecord\PhpStan\Model;

use ActiveRecord\Model;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class FindAllDynamicMethodReturnTypeReflection implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();
        $pos = strpos($name, 'find_all');

        return 0 === $pos;
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        $class = $methodReflection->getDeclaringClass();

        return new ArrayType(
            new IntegerType(),
            new ObjectType($class->getName())
        );
    }
}
