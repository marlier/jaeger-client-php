<?php

use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Reporter\LoggingReporter;
use Jaeger\Reporter\NullReporter;
use Jaeger\Span;
use \PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ReporterTest extends TestCase
{
    public function testNullReporter()
    {
        $span = $this->createMock(Span::class);

        $reporter = new NullReporter();
        $reporter->reportSpan($span);
        $reporter->close();

        // NullReporter doesn't actually preserve spans, so no way to retrieve them
        $this->assertTrue(true);
    }

    public function testInMemoryReporter()
    {
        $span = $this->createMock(Span::class);
        $reporter = new InMemoryReporter();

        $reporter->reportSpan($span);
        $reporter->close();

        $spans = $reporter->getSpans();
        $this->assertEquals([$span], $spans);
    }

    public function testLoggingReporter()
    {
        $logger = $this->createMock(NullLogger::class);
        $span = $this->createMock(Span::class);
        $reporter = new LoggingReporter($logger);

        $logger->method('info')
            ->with($this->stringStartsWith('Reporting span'));

		$logger->expects($this->once())
			   ->method('info');

        $reporter->reportSpan($span);

    }
}