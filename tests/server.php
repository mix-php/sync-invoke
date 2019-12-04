<?php

include '../vendor/autoload.php';
include 'class.php';

Swoole\Coroutine\run(function () {

    $server = new \Mix\Sync\Invoke\Server(9505, true);
    $server->start();

});
