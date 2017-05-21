<?php

class Publisher extends ActiveRecord\Model
{
    public static $pk = 'publisher_id';
    public static $cache = true;
    public static $cache_expire = 2592000; // 1 month. 60 * 60 * 24 * 30
}
