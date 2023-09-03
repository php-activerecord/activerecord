<?php

declare(strict_types=1);

namespace ActiveRecord\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VoidType;

class ModelStaticMethodReflection implements MethodReflection
{
    private ClassReflection $classReflection;
    private string $name;

    public function __construct(ClassReflection $classReflection, string $name)
    {
        $this->classReflection = $classReflection;
        $this->name = $name;
    }

    public function isFinal(): \PHPStan\TrinaryLogic
    {
        // TODO: Implement isFinal() method.
        return TrinaryLogic::createNo();
    }

    public function isInternal(): \PHPStan\TrinaryLogic
    {
        // TODO: Implement isInternal() method.
        return TrinaryLogic::createNo();
    }

    public function getDocComment(): ?string
    {
        // TODO: Implement getDocComment() method.
        return null;
    }

    public function isDeprecated(): \PHPStan\TrinaryLogic
    {
        // TODO: Implement isDeprecated() method.
        return TrinaryLogic::createNo();
    }

    public function hasSideEffects(): \PHPStan\TrinaryLogic
    {
        // TODO: Implement hasSideEffects() method.
        return TrinaryLogic::createMaybe();
    }

    public function getThrowType(): ?\PHPStan\Type\Type
    {
        // TODO: Implement getThrowType() method.
        return null;
    }

    public function getDeprecatedDescription(): ?string
    {
        // TODO: Implement getDeprecatedDescription() method.
        return null;
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->classReflection;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function isStatic(): bool
    {
        return true;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isVariadic(): bool
    {
        return false;
    }

    /**
     * @return \PHPStan\Reflection\ParametersAcceptor[]
     */
    public function getVariants(): array
    {
        if (preg_match("/find_(all_)?by_/", $this->name)) {
            return [
                new FunctionVariant(
                    TemplateTypeMap::createEmpty(),
                    TemplateTypeMap::createEmpty(),
                    [new ModelParameterReflection()],
                    false,
                    new ObjectType($this->classReflection->getDisplayName())
                )
            ];
        } elseif (preg_match("/_set$/", $this->name)) {
            return [
                new FunctionVariant(
                    TemplateTypeMap::createEmpty(),
                    TemplateTypeMap::createEmpty(),
                    [new ModelParameterReflection()],
                    false,
                    new ObjectType($this->classReflection->getDisplayName())
                )
            ];
        } elseif (preg_match("/_refresh/", $this->name)) {
            return [
                new FunctionVariant(
                    TemplateTypeMap::createEmpty(),
                    TemplateTypeMap::createEmpty(),
                    [],
                    false,
                    new VoidType()
                )
            ];
        } elseif (preg_match("/_dirty/", $this->name)) {
            return [
                new FunctionVariant(
                    TemplateTypeMap::createEmpty(),
                    TemplateTypeMap::createEmpty(),
                    [],
                    false,
                    new VoidType()
                )
            ];
        }

        return [
            new FunctionVariant(
                TemplateTypeMap::createEmpty(),
                TemplateTypeMap::createEmpty(),
                [],
                false,
                new ObjectType($this->classReflection->getDisplayName())
            ),
        ];
    }
}
