<?php

namespace Jaeger\Metrics;

/**
 * Provides a standardized way to create metrics-related objects, like {@link Counter}, {@link Timer} and {@link Gauge}.
 */
interface MetricsFactory
{
    /**
     * @param string $name
     * @param array $tags
     * @return Counter
     */
    function createCounter($name, $tags);

    /**
     * @param string $name
     * @param array $tags
     * @return Timer
     */
    function createTimer($name, $tags);

    /**
     * @param string $name
     * @param array $tags
     * @return Gauge
     */
    function createGauge($name, $tags);
}