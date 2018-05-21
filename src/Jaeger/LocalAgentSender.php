<?php

namespace Jaeger;

use Jaeger\ThriftGen\AgentClient;
use Jaeger\ThriftGen\AnnotationType;
use Jaeger\ThriftGen\Batch;
use Jaeger\ThriftGen\BinaryAnnotation;
use Jaeger\ThriftGen\Endpoint;
use Jaeger\ThriftGen\Span;
use Monolog\Logger;
use const OpenTracing\Tags\COMPONENT;
use Psr\Log\LoggerInterface;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class LocalAgentSender
{
    const CLIENT_ADDR = "ca";
    const SERVER_ADDR = "sa";

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

        $this->logger->debug('LocalAgentSender\flush: Sending ' . $count . ' spans to Jaeger');
        $process = Thrift::makeProcess($this->spans[0]->getTracer()->getServiceName(), []);

        $this->logger->debug('localAgentSender\flush: Process thrift object has been created');
        $jaegerSpans = Thrift::makeJaegerBatch($this->spans, $process);
		$this->logger->debug('LocalAgentSender\flush: Jaeger batch created');

        $this->send($jaegerSpans);
        $this->logger->debug('LocalAgentSender\flush: Sent spans');
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

    /**
     * @param \Jaeger\Span[] $spans
     * @return \Jaeger\ThriftGen\Span[]
     */
    private function makeZipkinBatch(array $spans): array
    {
        /** @var \Jaeger\ThriftGen\Span[] */
        $zipkinSpans = [];

        foreach ($spans as $span) {
            /** @var \Jaeger\Span $span */

            $endpoint = $this->makeEndpoint(
                $span->getTracer()->getIpAddress(),
                0,  // span.port,
                $span->getTracer()->getServiceName()
            );

//            foreach ($span->getLogs() as $event) {
//                $event->setHost($endpoint);
//            }

            $timestamp = $span->getStartTime();
            $duration = $span->getEndTime() - $span->getStartTime();

            $this->addZipkinAnnotations($span, $endpoint);

            $zipkinSpan = new Span([
                'name' => $span->getOperationName(),
                'id' => $span->getContext()->getSpanId(),
                'parent_id' => $span->getContext()->getParentId() ?? null,
                'trace_id' => $span->getContext()->getTraceId(),
                'annotations' => array(), // logs
                'binary_annotations' => $span->getTags(),
                'debug' => $span->isDebug(),
                'timestamp' => $timestamp,
                'duration' => $duration,
            ]);

            $zipkinSpans[] = $zipkinSpan;
        }

        return $zipkinSpans;
    }

    private function addZipkinAnnotations(\Jaeger\Span $span, $endpoint)
    {
        if ($span->isRpc()) {
            $isClient = $span->isRpcClient();

            if ($span->peer) {
                $host = $this->makeEndpoint(
                    $span->peer['ipv4'] ?? 0,
                    $span->peer['port'] ?? 0,
                    $span->peer['service_name'] ?? '');

                $key = ($isClient) ? self::SERVER_ADDR : self::CLIENT_ADDR;

                $peer = $this->makePeerAddressTag($key, $host);
                $span->tags[$key] = $peer;
            }
        } else {
            $tag = $this->makeLocalComponentTag(
                $span->getComponent() ?? $span->getTracer()->getServiceName(),
                $endpoint
            );

            $span->tags[COMPONENT] = $tag;
        }
    }

    private function makeLocalComponentTag(string $componentName, $endpoint): BinaryAnnotation
    {
        return new BinaryAnnotation([
            'key' => "lc",
            'value' => $componentName,
            'annotation_type' => AnnotationType::STRING,
            'host' => $endpoint,
        ]);
    }

    private function makeEndpoint(string $ipv4, int $port, string $serviceName): Endpoint
    {
        $ipv4 = $this->ipv4ToInt($ipv4);

        return new Endpoint([
            'ipv4' => $ipv4,
            'port' => $port,
            'service_name' => $serviceName,
        ]);
    }

    private function ipv4ToInt($ipv4): int
    {
        if ($ipv4 == 'localhost') {
            $ipv4 = '127.0.0.1';
        } elseif ($ipv4 == '::1') {
            $ipv4 = '127.0.0.1';
        }

        return ip2long($ipv4);
    }

    // Used for Zipkin binary annotations like CA/SA (client/server address).
    // They are modeled as Boolean type with '0x01' as the value.
    private function makePeerAddressTag($key, $host)
    {
        return new BinaryAnnotation([
            "key" => $key,
            "value" => '0x01',
            "annotation_type" => AnnotationType::BOOL,
            "host" => $host
        ]);
    }
}
