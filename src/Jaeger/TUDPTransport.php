<?php

namespace Jaeger;

use Psr\Log\LoggerInterface;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

class TUDPTransport extends TTransport
{
    private $socket;
    private $host;
    private $port;

    public function __construct($host, $port, LoggerInterface $logger = null)
    {
    	$this->logger = $logger ?? new Logger('jaeger_tracing\TUDPTransport');
        $this->host = $host;
        $this->port = $port;
        $this->logger->debug('TUDPTransport\__construct: Creating a UDP socket');
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    /**
     * Whether this transport is open.
     *
     * @return boolean true if open
     */
    public function isOpen()
    {
        return $this->socket !== null;
    }

    /**
     * Open the transport for reading/writing
     *
     * @throws TTransportException if cannot open
     */
    public function open()
	{
        $ok = socket_connect($this->socket, $this->host, $this->port);
        $this->logger->debug('TUDPTransport\open: Connected socket to ' . $this->host . ':' . $this->port);
        if ($ok === FALSE) {
            throw new TTransportException('socket_connect failed');
        }
    }

    /**
     * Close the transport.
     */
    public function close()
    {
        socket_close($this->socket);
        $this->socket = null;
    }

    /**
     * Read some data into the array.
     *
     * @param int $len How much to read
     * @return string The data that has been read
     * @throws TTransportException if cannot read any more data
     */
    public function read($len)
    {
    }

    /**
     * Writes the given data out.
     *
     * @param string $buf The data to write
     * @throws TTransportException if writing fails
     */
    public function write($buf)
    {
        if (!$this->isOpen()) {
            throw new TTransportException('transport is closed');
        }
		$this->logger->debug('TUDPTransport\write: Writing a buffer to UDP Socket');
        $ok = socket_write($this->socket, $buf);
        if ($ok === FALSE) {
            throw new TTransportException('socket_write failed');
        }
        $this->logger->debug('TUDPTransport\write: Wrote buffer to UDP');
    }
}
