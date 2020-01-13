<?php

namespace Mix\Sync\Invoke;

use Mix\Bean\BeanInjector;
use Mix\Pool\ConnectionPoolInterface;
use Mix\Sync\Invoke\Exception\CallException;
use Mix\Sync\Invoke\Exception\InvokeException;

/**
 * Class Client
 * @package Mix\Sync\Invoke
 */
class Client
{

    /**
     * 连接池
     * @var ConnectionPoolInterface
     */
    public $pool;

    /**
     * 连接
     * @var Connection
     */
    public $connection;

    /**
     * Authorization constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        BeanInjector::inject($this, $config);
    }

    /**
     * 获取连接
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->pool ? $this->pool->getConnection() : $this->connection;
    }

    /**
     * Invoke
     * @param \Closure $closure
     * @return mixed
     * @throws InvokeException
     * @throws \Swoole\Exception
     */
    public function invoke(\Closure $closure)
    {
        $code       = \Opis\Closure\serialize($closure);
        $connection = $this->getConnection();
        $connection->send($code . Connection::EOF);
        $data = unserialize($connection->recv());
        $connection->release();
        if ($data instanceof CallException) {
            throw new InvokeException($data->message, $data->code);
        }
        return $data;
    }

}
