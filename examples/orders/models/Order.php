<?php

class Order extends ActiveRecord\Model
{
    // order belongs to a person
    public static $belongs_to = [
        ['person']];

    // order can have many payments by many people
    // the conditions is just there as an example as it makes no logical sense
    public static $has_many = [
        ['payments'],
        ['people',
            'through'    => 'payments',
            'select'     => 'people.*, payments.amount',
            'conditions' => 'payments.amount < 200']];

    // order must have a price and tax > 0
    public static $validates_numericality_of = [
        ['price', 'greater_than' => 0],
        ['tax',   'greater_than' => 0]];

    // setup a callback to automatically apply a tax
    public static $before_validation_on_create = ['apply_tax'];

    public function apply_tax()
    {
        if ('VA' == $this->person->state) {
            $tax = 0.045;
        } elseif ('CA' == $this->person->state) {
            $tax = 0.10;
        } else {
            $tax = 0.02;
        }

        $this->tax = $this->price * $tax;
    }
}
