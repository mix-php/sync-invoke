<?php

namespace Mix\Sync\Invoke;

use Mix\Server\Connection;
use Mix\Server\Exception\ReceiveException;
use SuperClosure\Serializer;
use SuperClosure\Analyzer\AstAnalyzer;

/**
 * Class Server
 * @package Mix\Sync\Invoke
 */
class Server
{

    /**
     * @var int
     */
    public $port = 0;

    /**
     * @var bool
     */
    public $reusePort = false;

    /**
     * @var \Mix\Server\Server
     */
    protected $server;

    /**
     * EOF
     */
    const EOF = "-Y3ac0v\n";

    /**
     * Server constructor.
     * @param int $port
     * @param bool $reusePort
     */
    public function __construct(int $port, bool $reusePort = false)
    {
        $this->port      = $port;
        $this->reusePort = $reusePort;
    }

    /**
     * Start
     * @throws \Swoole\Exception
     */
    public function start()
    {
        $server = $this->server = new \Mix\Server\Server('127.0.0.1', $this->port, false, $this->reusePort);
        $server->set([
            'open_eof_check' => true,
            'package_eof'    => static::EOF,
        ]);
        $server->handle(function (Connection $conn) {
            while (true) {
                try {
                    $data       = $conn->recv();
                    $serializer = new Serializer(new AstAnalyzer());
                    $closure    = $serializer->unserialize($data);
                    $data       = call_user_func($closure);
                    $conn->send(serialize($data) . static::EOF);
                } catch (\Throwable $e) {
                    // 忽略服务器主动断开连接异常
                    if ($e instanceof ReceiveException && $e->getCode() == 104) {
                        return;
                    }
                    // 抛出异常
                    throw $e;
                }
            }
        });
        $server->start();
    }

    /**
     * Shutdown
     * @throws \Swoole\Exception
     */
    public function shutdown()
    {
        $this->server->shutdown();
    }

}
