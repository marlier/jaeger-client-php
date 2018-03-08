<?php

namespace Jaeger;

use InvalidArgumentException;
use Jaeger\Metrics\MetricsFactory;
use Jaeger\Metrics\NoopMetricsFactory;
use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\SamplerInterface;
use PHPUnit\Framework\TestCase;

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

//    function testTracerImplementsCloseable()
//    {
//        $this->assertTrue($this->tracer instanceof Closable);
//    }

    function testClose()
    {
        $reporter = $this->createMock(ReporterInterface::class);
        $sampler = $this->createMock(SamplerInterface::class);

        $reporter->expects($this->once())->method('close');
        $sampler->expects($this->once())->method('close');

        $tracer = new Tracer("bonda", $reporter, $sampler);
        $tracer->close();
    }

//    function testAsChildOfAcceptNull()
//    {
//        $tracer = new Tracer("foo", new InMemoryReporter(), new ConstSampler(true));
//
//        $span = $tracer->startSpan("foo", ['child_of' => null]);
//        $span->finish();
//        $this->assertTrue($span->getReferences()->isEmpty());
//
//        $span = $tracer->startSpan("foo", ['child_of' => null]);
//        $span->finish();
//        $this->assertTrue($span->getReferences()->isEmpty());
//    }

    function testActiveSpan()
    {
        $mockSpan = $this->createMock(Span::class);

        $this->tracer->getScopeManager()->activate($mockSpan);
        $this->assertEquals($mockSpan, $this->tracer->getActiveSpan());
    }

//    function testSpanContextNotSampled()
//    {
//        $expectedOperation = "fry";
//        $first = $this->tracer->startSpan($expectedOperation);
//        $this->tracer->startSpan($expectedOperation, ['child_of' => $first->getContext()->withFlags(0)]);
//
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=y"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=n"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:traces", "sampled=y,state=started"));
//        $this->assertEquals(0, $this->metricsFactory->getCounter("jaeger:traces", "sampled=n,state=started"));
//    }
}