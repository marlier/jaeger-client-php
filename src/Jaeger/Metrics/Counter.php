<?php

namespace Jaeger\Metrics;

interface Counter
{
    /**
     * @param int $delta
     * @return void
     */
    function inc($delta);
}