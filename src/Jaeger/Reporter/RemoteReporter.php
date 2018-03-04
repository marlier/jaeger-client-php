<?php

namespace Jaeger\Reporter;

use Jaeger\LocalAgentSender;
use Jaeger\Span;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class RemoteReporter implements ReporterInterface
{
    /** @var LocalAgentSender */
    private $transport;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $serviceName;

    /** @var int */
    private $batchSize;

    /**
     * RemoteReporter constructor.
     * @param $transport
     * @param string $serviceName
     * @param int $batchSize
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        $transport,
        $serviceName,
        $batchSize = 10,
        LoggerInterface $logger = null
    )
    {
        $this->transport = $transport;
        $this->serviceName = $serviceName;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?? new Logger('jaeger_tracing');
    }

    /**
     * @param Span $span
     * @return mixed|void
     */
    public function reportSpan(Span $span)
    {
        $this->transport->append($span);
    }

    /**
     * @return void
     */
    public function close()
    {
        $this->transport->flush();
        $this->transport->close();
    }
}