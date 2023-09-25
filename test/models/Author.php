<?php

namespace test\models;

use ActiveRecord\Model;

class Author extends Model
{
    public static string $pk = 'author_id';
    public static array $has_many = [
        'books' => true
    ];
    public static array $has_one = [
        'awesome_person' => [
            'foreign_key' => 'author_id',
            'primary_key' => 'author_id'
        ],
        'parent_author' => [
            'class_name' => Author::class,
            'foreign_key' => 'parent_author_id'
        ]
    ];
    public static array $belongs_to = [];

    public function set_password($plaintext)
    {
        $this->encrypted_password = md5($plaintext);
    }

    public function set_name($value)
    {
        $value = strtoupper($value);
        $this->assign_attribute('name', $value);
    }

    public function return_something()
    {
        return ['sharks' => 'lasers'];
    }
}
