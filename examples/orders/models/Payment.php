<?php

class Payment extends ActiveRecord\Model
{
    // payment belongs to a person
    public static array $belongs_to = [
        ['person'],
        ['order']];
}
