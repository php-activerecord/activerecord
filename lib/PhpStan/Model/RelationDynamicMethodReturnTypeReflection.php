<?php

namespace ActiveRecord\PhpStan\Model;

use ActiveRecord\iRelation;
use ActiveRecord\Relation;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Method\MethodCall;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

// DynamicStaticMethodReturnTypeExtension
class RelationDynamicMethodReturnTypeReflection implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        $res = Relation::class;
        return $res;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['first', 'find']);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, \PhpParser\Node\Expr\MethodCall $methodCall, Scope $scope): Type
    {
        $calledOnType = $scope->getType($methodCall->var);

        assert($calledOnType instanceof \PHPStan\Type\Generic\GenericObjectType);
            // Here you have access to the generic type
        $genericTypes = $calledOnType->getTypes();
        assert($genericTypes[0] instanceof ObjectType);
        $subclass = $genericTypes[0]->getClassName();
        $args = $methodCall->args;

        $args = array_map(static function ($arg) use ($scope) {
            assert($arg instanceof Arg);
            $val = $arg->value;

            return $scope->getType($val);
        }, $args);

        switch ($methodCall->name) {
            case 'find':
                $numArgs = count($args);
                $single = false;
                $nullable = false;

                if (1 == $numArgs) {
                    if (!($args[0] instanceof ConstantArrayType)
                        || (!$this->isNumericArray($args[0]))) {
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
                        new ObjectType($subclass),
                        new NullType()
                    ]);
                } elseif ($single) {
                    return new ObjectType($subclass);
                }

                return new ArrayType(new IntegerType(), new ObjectType($subclass));

            case 'first':
                $numArgs = count($args);
                if (1 == $numArgs) {
                    return new ArrayType(
                        new IntegerType(),
                        $genericTypes[0]
                    );
                }

                return new UnionType([
                    $genericTypes[0],
                    new NullType()
                ]);

            default:
                throw new \Exception('Unknown method');
        }
    }

    protected function isNumericArray(ConstantArrayType $args): bool
    {
        $keys = $args->getKeyTypes();
        $allInt = true;
        foreach ($keys as $key) {
            if (!($key instanceof IntegerType)) {
                $allInt = false;
                break;
            }
        }

        return $allInt;
    }
}
