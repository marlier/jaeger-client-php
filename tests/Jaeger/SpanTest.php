<?php

namespace Jaeger;

use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Sampler\ConstSampler;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    /** @var Clock */
    private $clock;
    /** @var InMemoryReporter */
    private $reporter;
    /** @var Tracer */
    private $tracer;
    /** @var Span */
    private $span;
    /** @var InMemoryMetricsFactory */
    private $metricsFactory;
    /** @var Metrics */
    private $metrics;

    function setUp()
    {
//        $this->metricsFactory = new InMemoryMetricsFactory();
        $this->reporter = new InMemoryReporter();
//        $this->clock = $this->createMock(Clock::class);
//        $this->metrics = new Metrics($this->metricsFactory);
        $this->tracer = new Tracer("SamplerTest", $this->reporter, new ConstSampler(true));

        $this->span = $this->tracer->startSpan("some-operation");
    }

//    function testSpanMetrics()
//    {
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:started_spans", "sampled=y"));
//        $this->assertEquals(1, $this->metricsFactory->getCounter("jaeger:traces", "sampled=y,state=started"));
//    }

//    function testSetAndGetBaggageItem()
//    {
//        $service = "SamplerTest";
//        $mgr = Mockito . mock(DefaultBaggageRestrictionManager::class);
//        $this->tracer =
//            new Tracer . Builder(service, reporter, new ConstSampler(true))
//            . withClock(clock)
//            . withBaggageRestrictionManager(mgr)
//            . build();
//
//        $this->span = $this->tracer->startSpan("some-operation");
//
//        $key = "key";
//        $value = "value";
//
//        $this->when(mgr . getRestriction($service, $key)) . thenReturn(Restriction . of(true, 10));
//        $this->span->setBaggageItem(key, "value");
//        $this->verify(mgr) . getRestriction($service, $key);
//        $this->assertEquals($value, $this->span->getBaggageItem($key));
//    }

//    function testSetBooleanTag()
//    {
//        $expected = true;
//        $key = "tag.key";
//
//        $this->span->setTag($key, $expected);
//        var_dump($this->span->getTags());
//        $this->assertEquals($expected, $this->span->getTags()[$key]);
//    }

    function testSetOperationName()
    {
        $expected = "modified.operation";

        $this->assertEquals("some-operation", $this->span->getOperationName());
        $this->span->overwriteOperationName($expected);
        $this->assertEquals($expected, $this->span->getOperationName());
    }

    /*
    function testSetStringTag()
    {
      String expected = "expected.value";
      String key = "tag.key";

      span.setTag(key, expected);
      $this->assertEquals(expected, span.getTags().get(key));
    }

    function testSetNumberTag()
    {
      Integer expected = 5;
      String key = "tag.key";

      span.setTag(key, expected);
      $this->assertEquals(expected, span.getTags().get(key));
    }

    function testWithTimestampAccurateClock()
    {
      testWithTimestamp(true);
    }

    function testWithTimestampInaccurateClock()
    {
      testWithTimestamp(false);
    }

    private function testWithTimestamp(boolean accurate) {
      when(clock.isMicrosAccurate()).thenReturn(accurate);
      when(clock.currentTimeMicros())
          .thenThrow(new IllegalStateException("currentTimeMicros() called"));
      when(clock.currentNanoTicks())
          .thenThrow(new IllegalStateException("currentNanoTicks() called"));

      Span span = (Span) tracer.buildSpan("test-service-name").withStartTimestamp(567).start();
      span.finish(999);

      $this->assertEquals(1, reporter.getSpans().size());
      $this->assertEquals(567, span.getStart());
      $this->assertEquals(999 - 567, span.getDuration());
    }


    function testMultipleSpanFinishDoesNotCauseMultipleReportCalls() {
      Span span = (Span) tracer.buildSpan("test-service-name").start();
      span.finish();

      $this->assertEquals(1, reporter.getSpans().size());

      Span reportedSpan = reporter.getSpans().get(0);

      // new finish calls will not affect size of reporter.getSpans()
      span.finish();

      $this->assertEquals(1, reporter.getSpans().size());
      $this->assertEquals(reportedSpan, reporter.getSpans().get(0));
    }


    function testWithoutTimestampsAccurateClock() {
      when(clock.isMicrosAccurate()).thenReturn(true);
      when(clock.currentTimeMicros()).thenReturn(1L).thenReturn(5L);
      when(clock.currentNanoTicks())
          .thenThrow(new IllegalStateException("currentNanoTicks() called"));

      Span span = (Span) tracer.buildSpan("test-service-name").start();
      span.finish();

      $this->assertEquals(1, reporter.getSpans().size());
      $this->assertEquals(1, span.getStart());
      $this->assertEquals(4, span.getDuration());
    }


    function testWithoutTimestampsInaccurateClock() {
      when(clock.isMicrosAccurate()).thenReturn(false);
      when(clock.currentTimeMicros())
          .thenReturn(100L)
          .thenThrow(new IllegalStateException("currentTimeMicros() called 2nd time"));
      when(clock.currentNanoTicks()).thenReturn(20000L).thenReturn(30000L);

      Span span = (Span) tracer.buildSpan("test-service-name").start();
      span.finish();

      $this->assertEquals(1, reporter.getSpans().size());
      $this->assertEquals(100, span.getStart());
      $this->assertEquals(10, span.getDuration());
    }


    function testSpanToString() {
      Span span = (Span) tracer.buildSpan("test-operation").start();
      SpanContext expectedContext = span.context();
      SpanContext actualContext = SpanContext.contextFromString(span.context().contextAsString());

      $this->assertEquals(expectedContext.getTraceId(), actualContext.getTraceId());
      $this->assertEquals(expectedContext.getSpanId(), actualContext.getSpanId());
      $this->assertEquals(expectedContext.getParentId(), actualContext.getParentId());
      $this->assertEquals(expectedContext.getFlags(), actualContext.getFlags());
    }


    function testOperationName() {
      String expectedOperation = "leela";
      Span span = (Span) tracer.buildSpan(expectedOperation).start();
      $this->assertEquals(expectedOperation, span.getOperationName());
    }


    function testLogWithTimestamp() {
      long expectedTimestamp = 2222;
      final String expectedLog = "some-log";
      final String expectedEvent = "event";
      Map<String, String> expectedFields = new HashMap<String, String>() {
        {
          put(expectedEvent, expectedLog);
        }
      };

      span.log(expectedTimestamp, expectedEvent);
      span.log(expectedTimestamp, expectedFields);
      span.log(expectedTimestamp, (String) null);
      span.log(expectedTimestamp, (Map<String, ?>) null);

      LogData actualLogData = span.getLogs().get(0);

      $this->assertEquals(expectedTimestamp, actualLogData.getTime());
      $this->assertEquals(expectedEvent, actualLogData.getMessage());

      actualLogData = span.getLogs().get(1);

      $this->assertEquals(expectedTimestamp, actualLogData.getTime());
      assertNull(actualLogData.getMessage());
      $this->assertEquals(expectedFields, actualLogData.getFields());
    }


    function testLog() {
      final long expectedTimestamp = 2222;
      final String expectedLog = "some-log";
      final String expectedEvent = "expectedEvent";

      when(clock.currentTimeMicros()).thenReturn(expectedTimestamp);

      span.log(expectedEvent);

      Map<String, String> expectedFields = new HashMap<String, String>() {
        {
          put(expectedEvent, expectedLog);
        }
      };
      span.log(expectedFields);
      span.log((String) null);
      span.log((Map<String, ?>) null);

      LogData actualLogData = span.getLogs().get(0);

      $this->assertEquals(expectedTimestamp, actualLogData.getTime());
      $this->assertEquals(expectedEvent, actualLogData.getMessage());

      actualLogData = span.getLogs().get(1);

      $this->assertEquals(expectedTimestamp, actualLogData.getTime());
      assertNull(actualLogData.getMessage());
      $this->assertEquals(expectedFields, actualLogData.getFields());
    }


    function testSpanDetectsSamplingPriorityGreaterThanZero() {
      Span span = (Span) tracer.buildSpan("test-service-operation").start();
      Tags.SAMPLING_PRIORITY.set(span, 1);

      $this->assertEquals(span.context().getFlags() & SpanContext.flagSampled, SpanContext.flagSampled);
      $this->assertEquals(span.context().getFlags() & SpanContext.flagDebug, SpanContext.flagDebug);
    }


    function testSpanDetectsSamplingPriorityLessThanZero() {
      Span span = (Span) tracer.buildSpan("test-service-operation").start();

      $this->assertEquals(span.context().getFlags() & SpanContext.flagSampled, SpanContext.flagSampled);
      Tags.SAMPLING_PRIORITY.set(span, -1);
      $this->assertEquals(span.context().getFlags() & SpanContext.flagSampled, 0);
    }


    function testBaggageOneReference() {
      io.opentracing.Span parent = tracer.buildSpan("foo").start();
      parent.setBaggageItem("foo", "bar");

      io.opentracing.Span child = tracer.buildSpan("foo")
          .asChildOf(parent)
          .start();

      child.setBaggageItem("a", "a");

      assertNull(parent.getBaggageItem("a"));
      $this->assertEquals("a", child.getBaggageItem("a"));
      $this->assertEquals("bar", child.getBaggageItem("foo"));
    }


    function testBaggageMultipleReferences() {
      io.opentracing.Span parent1 = tracer.buildSpan("foo").start();
      parent1.setBaggageItem("foo", "bar");
      io.opentracing.Span parent2 = tracer.buildSpan("foo").start();
      parent2.setBaggageItem("foo2", "bar");

      io.opentracing.Span child = tracer.buildSpan("foo")
          .asChildOf(parent1)
          .addReference(References.FOLLOWS_FROM, parent2.context())
          .start();

      child.setBaggageItem("a", "a");
      child.setBaggageItem("foo2", "b");

      assertNull(parent1.getBaggageItem("a"));
      assertNull(parent2.getBaggageItem("a"));
      $this->assertEquals("a", child.getBaggageItem("a"));
      $this->assertEquals("bar", child.getBaggageItem("foo"));
      $this->assertEquals("b", child.getBaggageItem("foo2"));
    }


    function testImmutableBaggage() {
      io.opentracing.Span span = tracer.buildSpan("foo").start();
      span.setBaggageItem("foo", "bar");
      {
        Iterator<Entry<String, String>> baggageIter = span.context().baggageItems().iterator();
        baggageIter.next();
        baggageIter.remove();
      }

      Iterator<Entry<String, String>> baggageIter = span.context().baggageItems().iterator();
      baggageIter.next();
      $this->assertFalse(baggageIter.hasNext());
    }


    function testExpandExceptionLogs() {
      RuntimeException ex = new RuntimeException(new NullPointerException("npe"));
      Map<String, Object> logs = new HashMap<>();
      logs.put(Fields.ERROR_OBJECT, ex);
      Span span = (Span)tracer.buildSpan("foo").start();
      span.log(logs);

      List<LogData> logData = span.getLogs();
      $this->assertEquals(1, logData.size());
      $this->assertEquals(4, logData.get(0).getFields().size());

      $this->assertEquals(ex, logData.get(0).getFields().get(Fields.ERROR_OBJECT));
      $this->assertEquals(ex.getMessage(), logData.get(0).getFields().get(Fields.MESSAGE));
      $this->assertEquals(ex.getClass().getName(), logData.get(0).getFields().get(Fields.ERROR_KIND));
      StringWriter sw = new StringWriter();
      ex.printStackTrace(new PrintWriter(sw));
      $this->assertEquals(sw.toString(), logData.get(0).getFields().get(Fields.STACK));
    }


    function testExpandExceptionLogsExpanded() {
      RuntimeException ex = new RuntimeException(new NullPointerException("npe"));
      Map<String, Object> logs = new HashMap<>();
      logs.put(Fields.ERROR_OBJECT, ex);
      logs.put(Fields.MESSAGE, ex.getMessage());
      logs.put(Fields.ERROR_KIND, ex.getClass().getName());
      StringWriter sw = new StringWriter();
      ex.printStackTrace(new PrintWriter(sw));
      logs.put(Fields.STACK, sw.toString());
      Span span = (Span)tracer.buildSpan("foo").start();
      span.log(logs);

      List<LogData> logData = span.getLogs();
      $this->assertEquals(1, logData.size());
      $this->assertEquals(4, logData.get(0).getFields().size());

      $this->assertEquals(ex, logData.get(0).getFields().get(Fields.ERROR_OBJECT));
      $this->assertEquals(ex.getMessage(), logData.get(0).getFields().get(Fields.MESSAGE));
      $this->assertEquals(ex.getClass().getName(), logData.get(0).getFields().get(Fields.ERROR_KIND));
      $this->assertEquals(sw.toString(), logData.get(0).getFields().get(Fields.STACK));
    }


    function testExpandExceptionLogsLoggedNoException() {
      Span span = (Span)tracer.buildSpan("foo").start();

      Object object = new Object();
      Map<String, Object> logs = new HashMap<>();
      logs.put(Fields.ERROR_OBJECT, object);
      span.log(logs);

      List<LogData> logData = span.getLogs();
      $this->assertEquals(1, logData.size());
      $this->assertEquals(1, logData.get(0).getFields().size());
      $this->assertEquals(object, logData.get(0).getFields().get(Fields.ERROR_OBJECT));
    }


    function testNoExpandExceptionLogs() {
      Tracer tracer = new Tracer.Builder("fo", reporter, new ConstSampler(true))
          .build();

      Span span = (Span)tracer.buildSpan("foo").start();

      RuntimeException ex = new RuntimeException();
      Map<String, Object> logs = new HashMap<>();
      logs.put(Fields.ERROR_OBJECT, ex);
      span.log(logs);

      List<LogData> logData = span.getLogs();
      $this->assertEquals(1, logData.size());
      $this->assertEquals(1, logData.get(0).getFields().size());
      $this->assertEquals(ex, logData.get(0).getFields().get(Fields.ERROR_OBJECT));
    }


    function testSpanNotSampled() {
      Tracer tracer = new Tracer.Builder("fo", reporter, new ConstSampler(false))
          .build();
      io.opentracing.Span foo = tracer.buildSpan("foo")
          .start();
      foo.log(Collections.emptyMap())
          .finish();
      $this->assertEquals(0, reporter.getSpans().size());
    }
    */
}