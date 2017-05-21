<?php

class AwesomePerson extends ActiveRecord\Model
{
    public static $belongs_to = ['author'];
}
