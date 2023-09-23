<?php

namespace test\models;

use ActiveRecord\Model;

class ValueStoreValidations extends Model
{
    public static string $table_name = 'valuestore';
    public static array $validates_uniqueness_of = ['key' => true];
}
