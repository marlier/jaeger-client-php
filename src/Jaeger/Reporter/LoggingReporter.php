<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * LoggingReporter logs all spans.
 */
class LoggingReporter implements ReporterInterface
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * LoggingReporter constructor.
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new Logger('jaeger_tracing');
    }

    /**
     * @param Span $span
     */
    public function reportSpan(Span $span)
    {
        $this->logger->info('Reporting span ' . $span);
    }

    /**
     * @return void
     */
    public function close()
    {
    }
}