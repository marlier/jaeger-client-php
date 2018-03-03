<?php

namespace Jaeger;

use ArrayIterator;
use OpenTracing;

class SpanContext implements OpenTracing\SpanContext
{
    private $traceId;
    private $spanId;
    private $parentId;
    private $flags;
    private $baggage;
    private $debugId;

    /**
     * SpanContext constructor.
     * @param $traceId
     * @param $spanId
     * @param $parentId
     * @param $flags
     * @param null $baggage
     */
    public function __construct($traceId, $spanId, $parentId, $flags, $baggage = null)
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->parentId = $parentId;
        $this->flags = $flags;
        $this->baggage = $baggage ?? [];
        $this->debugId = null;
    }

    public static function withDebugId($debugId)
    {
        $ctx = new SpanContext(null, null, null, null);
        $ctx->debugId = $debugId;

        return $ctx;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->baggage);
    }

    /**
     * @param string $key
     * @return string
     */
    public function getBaggageItem($key): string
    {
        return $this->baggage[$key];
    }

    /**
     * Creates a new SpanContext out of the existing one and the new key:value pair.
     *
     * @param string $key
     * @param string $value
     * @return \OpenTracing\SpanContext
     */
    public function withBaggageItem($key, $value)
    {
        $baggage = $this->baggage;
        $baggage[$key] = $value;

        return new SpanContext(
            $this->traceId,
            $this->spanId,
            $this->parentId,
            $this->flags,
            $baggage
        );
    }

    /**
     * @return int
     */
    public function getTraceId()
    {
        return $this->traceId;
    }

    /**
     * @return int|null
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * @return int
     */
    public function getSpanId()
    {
        return $this->spanId;
    }

    /**
     * @return mixed
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @return array|null
     */
    public function getBaggage()
    {
        return $this->baggage;
    }

    /**
     * @return null
     */
    public function getDebugId()
    {
        return $this->debugId;
    }

    /**
     * @return bool
     */
    public function isDebugIdContainerOnly()
    {
        return ($this->traceId === null) && ($this->debugId !== null);
    }
}