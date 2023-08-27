<?php
namespace test\models;

use ActiveRecord\Model;
class AwesomePerson extends Model
{
    public static $belongs_to = ['author'];
}
