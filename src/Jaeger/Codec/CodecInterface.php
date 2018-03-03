<?php

namespace Jaeger\Codec;

use OpenTracing\SpanContext;

interface CodecInterface
{
    public function inject(SpanContext $spanContext, $carrier);

    /**
     * @param $carrier
     * @return mixed
     */
    public function extract($carrier);
}