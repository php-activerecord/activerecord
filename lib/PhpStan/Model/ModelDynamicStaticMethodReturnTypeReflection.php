<?php

namespace ActiveRecord\PhpStan\Model;

use ActiveRecord\iRelation;
use ActiveRecord\Model;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

// DynamicStaticMethodReturnTypeExtension
class ModelDynamicStaticMethodReturnTypeReflection implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['first','find']);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        assert($methodCall->class instanceof Name);
        $subclass = $methodCall->class->toString();
        $args = $methodCall->args;

        $args = array_map(static function ($arg) use ($scope) {
            assert($arg instanceof Arg);
            $val = $arg->value;

            return $scope->getType($val);
        }, $args);

        switch($methodCall->name) {
            case "find":

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
                        new ObjectType($methodCall->class),
                        new NullType()
                    ]);
                } elseif ($single) {
                    return new ObjectType($subclass);
                }

                return new ArrayType(new IntegerType(), new ObjectType($subclass));

            case "first":
                $numArgs = count($args);
                $single = false;
                $nullable = false;

                if (1 == $numArgs) {
                    return new ArrayType(
                        new IntegerType(),
                        new ObjectType($subclass)
                    );
                } else {
                    return new UnionType([
                        new ObjectType($methodCall->class),
                        new NullType()
                    ]);
                }
            default:
                throw new \Exception("Unknown method");
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
