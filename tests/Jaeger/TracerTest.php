<?php

namespace Jaeger;

use InvalidArgumentException;
use Jaeger\Metrics\MetricsFactory;
use Jaeger\Metrics\NoopMetricsFactory;
use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Sampler\ConstSampler;
use PHPUnit\Framework\TestCase;

use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\SPAN_KIND;

final class TracerTest extends TestCase
{
    /** @var Tracer */
    public $tracer;

    /** @var MetricsFactory */
    public $metricsFactory;

    function setUp()
    {
        $this->metricsFactory = new NoopMetricsFactory(); // TODO InMemoryMetricsFactory
        $this->tracer = new Tracer("TracerTestService", new InMemoryReporter(), new ConstSampler());
    }

    function testDefaultConstructor()
    {
        $this->tracer = new Tracer("name");
        $this->assertTrue($this->tracer->getReporter() instanceof InMemoryReporter); // TODO RemoteReporter
        // no exception
        $this->tracer->startSpan("foo")->finish();
    }

    function testBuildSpan()
    {
        $expectedOperation = "fry";
        $span = $this->tracer->startSpan($expectedOperation);

        $this->assertEquals($expectedOperation, $span->getOperationName());
    }

//    function testTracerMetrics()
//    {
//        $expectedOperation = "fry";
//        $this->tracer->startSpan($expectedOperation);
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=y"));
//        $this->assertEquals(0, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=n"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:traces", "sampled=y,state=started"));
//        $this->assertEquals(0, $this->metricsFactory->getCounter("jaeger:traces", "sampled=n,state=started"));
//    }
//
//    function testRegisterInjector()
//    {
//        $injector = mock(Injector::class);
//
//        $this->tracer = new Tracer("TracerTestService", new InMemoryReporter(), new ConstSampler(true));
//        $span = $this->tracer->buildSpan("leela")->start();
//
//        $carrier = mock(TextMap::class);
//        $this->tracer->inject($span->context(), TEXT_MAP, $carrier);
//
//        $this->verify(injector)->inject(any(SpanContext::class), any(TextMap::class));
//    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testServiceNameNotNull()
    {
        new Tracer(null, new InMemoryReporter(), new ConstSampler(true));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testServiceNameNotEmptyNull()
    {
        new Tracer("  ", new InMemoryReporter(), new ConstSampler(true));
    }

//    function testBuilderIsServerRpc()
//    {
//        $spanBuilder = $this->tracer->buildSpan("ndnd");
//        $spanBuilder->withTag(SPAN_KIND, "server");
//
//        $this->assertTrue($spanBuilder->isRpcServer());
//    }

//    function testBuilderIsNotServerRpc()
//    {
//        $spanBuilder = $this->tracer->buildSpan("Lrrr");
//        $spanBuilder->withTag(SPAN_KIND, "peachy");
//
//        $this->assertFalse($spanBuilder->isRpcServer());
//    }
//
//    function testWithBaggageRestrictionManager()
//    {
//        $tracer =
//            new Tracer("TracerTestService", new InMemoryReporter(), new ConstSampler(true));
////        ->withMetrics(new Metrics(metricsFactory))
//        $span = $this->tracer->buildSpan("some-operation")->start();
//
//        $key = "key";
//        $this->tracer->setBaggage(span, key, "value");
//
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:baggage_updates", "result=ok"));
//    }
//
//    function testTracerImplementsCloseable()
//    {
//        $this->assertTrue(Closeable::class . isAssignableFrom(Tracer::class));
//    }
//
//    function testClose()
//    {
//        $reporter = mock(Reporter::class);
//        $sampler = mock(Sampler::class);
//        $this->tracer = new Tracer("bonda", $reporter, $sampler);
//        $this->tracer->close();
//        $this->verify(reporter)->close();
//        $this->verify(sampler)->close();
//    }
//
//    function testAsChildOfAcceptNull()
//    {
//        $this->tracer = new Tracer("foo", new InMemoryReporter(), new ConstSampler(true));
//
//        $span = $this->tracer->buildSpan("foo")->asChildOf(null)->start();
//        $span->finish();
//        $this->assertTrue($span->getReferences()->isEmpty());
//
//        $span = $this->tracer->buildSpan("foo")->asChildOf(null) . start();
//        $span->finish();
//        $this->assertTrue($span->getReferences()->isEmpty());
//    }
//
//    function testActiveSpan()
//    {
//        $mockSpan = mock(Span::class);
//        $this->tracer->scopeManager()->activate($mockSpan, true);
//        $this->assertEquals($mockSpan, $this->tracer->activeSpan());
//    }
//
//    function testSpanContextNotSampled()
//    {
//        $expectedOperation = "fry";
//        $first = $this->tracer->buildSpan($expectedOperation)->start();
//        $this->tracer->buildSpan($expectedOperation)->asChildOf($first->context()->withFlags(0)) . start();
//
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=y"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=n"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:traces", "sampled=y,state=started"));
//        $this->assertEquals(0, $this->metricsFactory->getCounter("jaeger:traces", "sampled=n,state=started"));
//    }
}