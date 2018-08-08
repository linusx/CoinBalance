<?php

session_start();

use Coin\Coin;

$GLOBALS['loader'] = require __DIR__ . '/vendor/autoload.php';
$GLOBALS['loader']->addPsr4('Coin\\', __DIR__ . '/src');

$GLOBALS['dotenv'] = new Dotenv\Dotenv(__DIR__);
$GLOBALS['dotenv']->load();

Coin::Instance();