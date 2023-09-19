<?php

namespace ActiveRecord\PhpStan\Model;

use ActiveRecord\Model;
use ActiveRecord\PhpStan\Relation\RelationReflectionHelper;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class ModelDynamicStaticMethodReturnTypeReflection implements DynamicStaticMethodReturnTypeExtension
{
    use RelationReflectionHelper;

    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), $this->dynamicReturnMethods());
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        assert($methodCall->class instanceof Name);
        $subclass = $methodCall->class->toString();
        $args = $methodCall->args;

        assert($methodCall->name instanceof Identifier);

        return $this->computeTypeFromArgs(
            $methodCall->name,
            $args,
            new ObjectType($subclass),
            $scope
        );
    }
}
