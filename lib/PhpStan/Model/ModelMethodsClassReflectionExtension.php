<?php

namespace ActiveRecord\PhpStan\Model;

use ActiveRecord\Model;
use ActiveRecord\Relation;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

class ModelMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->isSubclassOf(Model::class) || $classReflection->is(Relation::class)) {
            if (preg_match('/\bfind_by_/', $methodName)) {
                return true;
            }

            if (str_ends_with($methodName, '_set')) {
                return true;
            }
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new ModelStaticMethodReflection($classReflection, $methodName);
    }
}
