<?php

namespace Jaeger;

use Jaeger\ThriftGen\AgentClient;
use Jaeger\ThriftGen\Batch;
use Jaeger\ThriftGen\Span;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class LocalAgentSender
{
    /** @var Span[] */
    private $spans = [];

    /** @var int */
    private $batchSize;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var AgentClient */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(string $host, int $port,
								int $batchSize = 10, LoggerInterface $logger = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?? new Logger('jaeger_tracing\LocalAgentSender');

        $udp = new TUDPTransport($this->host, $this->port, $this->logger);
        $transport = new TBufferedTransport($udp, 4096, 4096);
        $transport->open();
        $protocol = new TCompactProtocol($transport);

        // Create client
        $this->client = new AgentClient($protocol);
    }

    /**
     * @param \Jaeger\Span $span
     *
     * @return int the number of flushed spans
     */
    public function append(\Jaeger\Span $span): int
    {
        $this->spans[] = $span;

        if (count($this->spans) >= $this->batchSize) {
            return $this->flush();
        }

        return 0;
    }

    /** @return int the number of flushed spans */
    public function flush(): int
    {
        $count = count($this->spans);
        if ($count === 0) {
            return 0;
        }

        /* Jaeger */
		/*
		$this->logger->debug('LocalAgentSender\flush: Sending ' . $count . ' spans to Jaeger');
        $process = Thrift::makeProcess($this->spans[0]->getTracer()->getServiceName(), []);
		$this->logger->debug('localAgentSender\flush: Process thrift object has been created');
        $jaegerSpans = Thrift::makeJaegerBatch($this->spans, $process);
		$this->logger->debug('LocalAgentSender\flush: Jaeger batch created');
        $this->send($jaegerSpans);
        $this->logger->debug('LocalAgentSender\flush: Sent spans');
		*/

		/* Zipkin Thrift */
		$this->logger->debug('localAgentSender\flush: creating a Zipkin Thrift batch');
		$zipkinSpans = Thrift::makeZipkinBatch($this->spans);
		$this->logger->debug('localAgentSender\flush: Zipkin batch created');
		$this->sendZipkin($zipkinSpans);
		$this->logger->debug('localAgentSender\flush: Zipkin batch sent');
        $this->spans = [];

        return $count;
    }

    public function close()
    {
    }

	/**
	 * @param Batch $spans
	 */
	private function send( Batch $spans )
    {
		$this->logger->debug('localAgentSender\send: Calling emitBatch');
		$this->client->emitBatch($spans);
        $this->logger->debug('localAgentSender\send: batch emitted');
    }

    private function sendZipkin( array $zipkinSpans ) {
		$this->client->emitZipkinBatch( $zipkinSpans );
	}
}
