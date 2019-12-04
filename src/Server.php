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
     * @var string
     */
    public $unixAddress = 'unix:/tmp/php.sock';

    /**
     * @var bool
     */
    public $reusePort = false;

    /**
     * @var \Mix\Server\Server
     */
    public $server;

    /**
     * EOF
     */
    const EOF = "\n";

    /**
     * Server constructor.
     * @param string $unixAddress
     * @param bool $reusePort
     */
    public function __construct(string $unixAddress, bool $reusePort = false)
    {
        $this->unixAddress = $unixAddress;
        $this->reusePort   = $reusePort;
    }

    /**
     * Start
     * @throws \Swoole\Exception
     */
    public function start()
    {
        $server = $this->server = new \Mix\Server\Server($this->unixAddress, 0, false, $this->reusePort);
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
                    call_user_func($closure);
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
