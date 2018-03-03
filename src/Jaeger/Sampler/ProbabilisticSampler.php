<?php

namespace Jaeger\Sampler;

use Exception;

use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_PROBABILISTIC;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

/**
 * A sampler that randomly samples a certain percentage of traces specified
 * by the samplingRate, in the range between 0.0 and 1.0.
 */
class ProbabilisticSampler implements SamplerInterface
{
    /** @var float */
    private $rate;

    /** @var array */
    private $tags = [];

    /** @var float */
    private $boundary;

    /**
     * @param float $rate
     * @throws Exception
     */
    public function __construct($rate)
    {
        $this->tags = [
            SAMPLER_TYPE_TAG_KEY => SAMPLER_TYPE_PROBABILISTIC,
            SAMPLER_PARAM_TAG_KEY => $rate,
        ];

        if ($rate <= 0.0 || $rate >= 1.0) {
            throw new Exception('Sampling rate must be between 0.0 and 1.0');
        }

        $this->rate = $rate;
        $this->boundary = $rate * PHP_INT_MAX;
    }

    /**
     * @param $traceId
     * @param string $operation
     * @return array|mixed
     */
    public function isSampled($traceId, $operation = '')
    {
        return [($traceId < $this->boundary), $this->tags];
    }

    /**
     * @return void
     */
    public function close()
    {
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "ProbabilisticSampler($this->rate)";
    }
}