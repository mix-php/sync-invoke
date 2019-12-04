<?php

namespace Mix\Sync\Invoke;

use Mix\Pool\ConnectionTrait;
use Swoole\Coroutine\Client;
use SuperClosure\Serializer;
use SuperClosure\Analyzer\AstAnalyzer;

/**
 * Class Connection
 * @package Mix\Sync\Invoke
 */
class Connection
{

    use ConnectionTrait;

    /**
     * @var string
     */
    public $unixAddress = 'unix:/tmp/php.sock';

    /**
     * @var float
     */
    public $timeout = 0.0;

    /**
     * @var Client
     */
    protected $client;

    /**
     * EOF
     */
    const EOF = "\n";

    /**
     * Connection constructor.
     * @param string $unixAddress
     * @param float $timeout
     * @throws \Swoole\Exception
     */
    public function __construct(string $unixAddress, float $timeout)
    {
        $this->unixAddress = $unixAddress;
        $this->timeout     = $timeout;
        $client            = new Client(SWOOLE_SOCK_UNIX_STREAM);
        $client->set([
            'open_eof_check' => true,
            'package_eof'    => static::EOF,
        ]);
        if (!$client->connect($unixAddress, 0, $timeout)) {
            throw new \Swoole\Exception(sprintf('Connect failed (addr: %s) [%s] %s', $unixAddress, $client->errCode, $client->errMsg));
        }
        $this->client = $client;
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        // 丢弃连接
        $this->discard();
    }

    /**
     * 关闭连接
     * @throws \Swoole\Exception
     */
    public function close()
    {
        if (!$this->client->close()) {
            $errMsg  = $this->client->errMsg;
            $errCode = $this->client->errCode;
            if ($errMsg == '' && $errCode == 0) {
                return;
            }
            throw new \Swoole\Exception($errMsg, $errCode);
        }
    }

    /**
     * @param \Closure $closure
     * @throws \Swoole\Exception
     */
    public function invoke(\Closure $closure)
    {
        $serializer = new Serializer(new AstAnalyzer());
        $data       = $serializer->serialize($closure);
        $this->send($data . static::EOF);
    }

    /**
     * Send
     * @param string $data
     * @throws \Swoole\Exception
     */
    protected function send(string $data)
    {
        $len  = strlen($data);
        $size = $this->client->send($data);
        if ($size === false) {
            throw new \Swoole\Exception($this->client->errMsg, $this->client->errCode);
        }
        if ($len !== $size) {
            throw new \Swoole\Exception('The sending data is incomplete, it may be that the socket has been closed by the peer.');
        }
    }

}
