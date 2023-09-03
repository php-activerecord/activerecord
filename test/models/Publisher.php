<?php

namespace test\models;

use ActiveRecord\Model;

class Publisher extends Model
{
    public static bool $cache = true;
    public static $cache_expire = 2592000; // 1 month. 60 * 60 * 24 * 30
}
