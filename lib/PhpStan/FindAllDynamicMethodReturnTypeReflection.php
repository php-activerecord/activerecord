<?php

namespace ActiveRecord\PhpStan;

namespace ActiveRecord\PhpStan;

use ActiveRecord\Model;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

class FindAllDynamicMethodReturnTypeReflection implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();
        $pos = strpos($name, "find_all");
        return $pos === 0;
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
