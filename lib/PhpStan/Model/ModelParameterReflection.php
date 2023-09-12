<?php

declare(strict_types=1);

namespace ActiveRecord\PhpStan\Model;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class ModelParameterReflection implements ParameterReflection
{
    public function getName(): string
    {
        return 'name';
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function getDefaultValue(): ?Type
    {
        return null;
    }

    public function getType(): Type
    {
        return new MixedType();
    }

    public function passedByReference(): PassedByReference
    {
        return PassedByReference::createNo();
    }

    public function isVariadic(): bool
    {
        return false;
    }
}
