<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * InMemoryReporter stores spans in memory and returns them via getSpans().
 */
class InMemoryReporter implements ReporterInterface
{
    /** @var array */
    private $spans = [];

    /**
     * @param Span $span
     * @return mixed|void
     */
    public function reportSpan(Span $span)
    {
        $this->spans[] = $span;
    }

    /**
     * @return array
     */
    public function getSpans()
    {
        return $this->spans;
    }

    /**
     * @return void
     */
    public function close()
    {
    }
}