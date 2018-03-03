<?php

namespace Jaeger\Sampler;

interface SamplerInterface
{
    /**
     * @param $traceId
     * @param $operation
     * @return mixed
     */
    public function isSampled($traceId, $operation);

    /**
     * @return void
     */
    public function close();

    /**
     * @return string
     */
    public function __toString();
}