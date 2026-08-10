<?php

require __DIR__ . '/../vendor/autoload.php';

$r = Illuminate\Http\Request::create('/', 'GET', ['hide_no_stock' => 1]);
var_dump($r->input('hide_no_stock'));
var_dump($r->boolean('hide_no_stock'));

$r2 = Illuminate\Http\Request::create('/', 'GET', ['hide_no_stock' => '1']);
var_dump($r2->boolean('hide_no_stock'));
