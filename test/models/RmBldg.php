<?php

namespace test\models;

use ActiveRecord\Model;

class RmBldg extends Model
{
    public static $table = 'rm-bldg';

    public static array $validates_presence_of = [
        'space_out' => ['message' => 'is missing!@#'],
        'rm_name' => true
    ];

    public static array $validates_length_of = [
        'space_out' => [
            'within' => [1, 5],
            'minimum' => 9,
            'too_short' => 'var is too short!! it should be at least %d long'
        ]
    ];

    public static array $validates_inclusion_of = [
        'space_out' => [
            'in' => ['jpg', 'gif', 'png'],
            'message' => 'extension %s is not included in the list'
        ],
    ];

    public static array $validates_exclusion_of = [
        'space_out' => [
            'in' => ['jpeg']
        ]
    ];

    public static array $validates_format_of = [
        'space_out' => [
            'with' => '/\A([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})\Z/i'
        ]
    ];

    public static array $validates_numericality_of = [
        'space_out' => [
            'less_than' => 9,
            'greater_than' => '5'
        ],
        'rm_id' => [
            'less_than' => 10,
            'odd' => null
        ]
    ];
}
