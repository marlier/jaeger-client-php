<?php

namespace Jaeger\Metrics;

interface Timer
{
    /**
     * @param int $time
     * @return void
     */
    function durationMicros($time);
}