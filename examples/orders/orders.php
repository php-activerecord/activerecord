<?php

require_once __DIR__ . '/../../ActiveRecord.php';

// initialize ActiveRecord
ActiveRecord\Config::initialize(function ($cfg) {
    $cfg->set_connections(['development' => 'mysql://test:test@127.0.0.1/orders_test']);

    // you can change the default connection with the below
    // $cfg->set_default_connection('production');
});

// create some people
$jax = new Person(['name' => 'Jax', 'state' => 'CA']);
$jax->save();

// compact way to create and save a model
$tito = Person::create(['name' => 'Tito', 'state' => 'VA']);

// place orders. tax is automatically applied in a callback
// create_orders will automatically place the created model into $tito->orders
// even if it failed validation
$pokemon = $tito->create_orders(['item_name' => 'Live Pokemon', 'price' => 6999.99]);
$coal    = $tito->create_orders(['item_name' => 'Lump of Coal', 'price' => 100.00]);
$freebie = $tito->create_orders(['item_name' => 'Freebie', 'price' => -100.99]);

if (count($freebie->errors) > 0) {
    echo "[FAILED] saving order $freebie->item_name: " . join(', ', $freebie->errors->full_messages()) . "\n\n";
}

// payments
$pokemon->create_payments(['amount' => 1.99, 'person_id' => $tito->id]);
$pokemon->create_payments(['amount' => 4999.50, 'person_id' => $tito->id]);
$pokemon->create_payments(['amount' => 2.50, 'person_id' => $jax->id]);

// reload since we don't want the freebie to show up (because it failed validation)
$tito->reload();

echo "$tito->name has " . count($tito->orders) . ' orders for: ' . join(', ', ActiveRecord\collect($tito->orders, 'item_name')) . "\n\n";

// display summary of all payments made by Tito and Jax
$conditions = [
    'conditions'    => ['id IN(?)', [$tito->id, $jax->id]],
    'order'            => 'name desc'];

foreach (Person::all($conditions) as $person) {
    $n = count($person->payments);
    $total = array_sum(ActiveRecord\collect($person->payments, 'amount'));
    echo "$person->name made $n payments for a total of $$total\n\n";
}

// using order has_many people through payments with options
// [
//   'people' => [
//     'through' => 'payments',
//     'select' => 'people.*, payments.amount',
//     'conditions' => 'payments.amount < 200'
//   ]
// ];
// this means our people in the loop below also has the payment information since it is part of an inner join
// we will only see 2 of the people instead of 3 because 1 of the payments is greater than 200
$order = Order::find($pokemon->id);
echo "Order #$order->id for $order->item_name ($$order->price + $$order->tax tax)\n";

foreach ($order->people as $person) {
    echo "  payment of $$person->amount by " . $person->name . "\n";
}
