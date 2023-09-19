<?php

namespace ActiveRecord\PhpStan\Relation;

use ActiveRecord\Model;
use ActiveRecord\PhpStan\Model\ModelStaticMethodReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

class RelationMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->isClass(Relation::class)) {
            if (preg_match('/\bfind_by_/', $methodName)) {
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
