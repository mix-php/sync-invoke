<?php

include '../vendor/autoload.php';
include 'class.php';

Swoole\Coroutine\run(function () {

    $conn = new \Mix\Sync\Invoke\Connection(9505, 5);
    $data = $conn->invoke(function () {
        $obj = new Hello();
        return [1, 2, 3, $obj];
    });
    var_dump($data);

});
