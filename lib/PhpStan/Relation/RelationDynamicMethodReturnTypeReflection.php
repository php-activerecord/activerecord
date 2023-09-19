<?php

namespace ActiveRecord\PhpStan\Relation;

use ActiveRecord\Relation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;

class RelationDynamicMethodReturnTypeReflection implements DynamicMethodReturnTypeExtension
{
    use RelationReflectionHelper;

    public function getClass(): string
    {
        return Relation::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), $this->dynamicReturnMethods());
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);

        assert($calledOnType instanceof GenericObjectType);
        // Here you have access to the generic type
        $genericTypes = $calledOnType->getTypes();

        assert($genericTypes[0] instanceof TypeWithClassName);
        $args = $methodCall->args;

        assert($methodCall->name instanceof Identifier);

        return $this->computeTypeFromArgs(
            $methodCall->name->toString(),
            $args,
            $genericTypes[0],
            $scope
        );
    }
}
