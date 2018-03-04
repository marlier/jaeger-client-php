<?php

namespace Jaeger\Metrics;

interface Gauge
{
    /**
     * @param int $amount
     * @return void;
     */
    function update($amount);
}