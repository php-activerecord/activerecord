<?php

class RmBldg extends ActiveRecord\Model
{
    public static $table = 'rm-bldg';

    public static $validates_presence_of = [
        ['space_out', 'message' => 'is missing!@#'],
        ['rm_name']
    ];

    public static $validates_length_of = [
        ['space_out', 'within' => [1, 5]],
        ['space_out', 'minimum' => 9, 'too_short' => 'var is too short!! it should be at least %d long']
    ];

    public static $validates_inclusion_of = [
        ['space_out', 'in' => ['jpg', 'gif', 'png'], 'message' => 'extension %s is not included in the list'],
    ];

    public static $validates_exclusion_of = [
        ['space_out', 'in' => ['jpeg']]
    ];

    public static $validates_format_of = [
        ['space_out', 'with' => '/\A([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})\Z/i']
    ];

    public static $validates_numericality_of = [
        ['space_out', 'less_than' => 9, 'greater_than' => '5'],
        ['rm_id', 'less_than' => 10, 'odd' => null]
    ];
}
