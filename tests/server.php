<?php

include '../vendor/autoload.php';
include 'class.php';

Swoole\Coroutine\run(function () {

    $server = new \Mix\Sync\Invoke\Server('unix:/tmp/php.sock');
    $server->start();

});
