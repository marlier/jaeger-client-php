<?php

namespace Jaeger\Metrics;

class NoopMetricsFactory implements MetricsFactory
{
    function createCounter($name, $tags)
    {
        return new class implements Counter
        {
            function inc($delta)
            {
            }
        };
    }

    function createTimer($name, $tags)
    {
        return new class implements Timer
        {
            function durationMicros($time)
            {
            }
        };
    }

    function createGauge($name, $tags)
    {
        return new class implements Gauge
        {
            function update($amount)
            {
            }
        };
    }
}