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
     * EOF
     * @var string
     */
    public $eof = "-Y3ac0v\n";

    /**
     * @var Client
     */
    protected $client;

    /**
     * Connection constructor.
     * @param string $unixAddress
     * @param float $timeout
     * @param string $eof
     * @throws \Swoole\Exception
     */
    public function __construct(string $unixAddress, float $timeout = 5.0, string $eof = "-Y3ac0v\n")
    {
        $this->unixAddress = $unixAddress;
        $this->timeout     = $timeout;
        $this->eof         = $eof;
        $client            = new Client(SWOOLE_SOCK_UNIX_STREAM);
        $client->set([
            'open_eof_check' => true,
            'package_eof'    => $eof,
        ]);
        if (!$client->connect(str_replace('unix:', '', $unixAddress), 0, $timeout)) {
            throw new \Swoole\Exception(sprintf("Connect failed (addr: '%s') [%s] %s", $unixAddress, $client->errCode, $client->errMsg));
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
     * Invoke
     * @param \Closure $closure
     * @return mixed
     * @throws \Swoole\Exception
     */
    public function invoke(\Closure $closure)
    {
        $serializer = new Serializer(new AstAnalyzer());
        $code       = $serializer->serialize($closure);
        $this->send($code . $this->eof);
        $data = $this->recv();
        return unserialize($data);
    }

    /**
     * Recv
     * @return mixed
     * @throws \Swoole\Exception
     */
    public function recv()
    {
        $data = $this->client->recv(-1);
        if ($data === false || $data === "") {
            throw new \Swoole\Exception($this->client->errMsg, $this->client->errCode);
        }
        return $data;
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
