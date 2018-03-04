<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

interface ReporterInterface
{
    /**
     * @param Span $span
     * @return mixed
     */
    public function reportSpan(Span $span);

    /**
     * @return void
     */
    public function close();
}
