<?php

class Payment extends ActiveRecord\Model
{
    // payment belongs to a person
    public static $belongs_to = [
        ['person'],
        ['order']];
}
