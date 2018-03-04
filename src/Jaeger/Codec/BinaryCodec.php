<?php

namespace Jaeger\Codec;

use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\SpanContext;

class BinaryCodec implements CodecInterface
{
    /**
     * @param SpanContext $spanContext
     * @param $carrier
     */
    public function inject(SpanContext $spanContext, $carrier)
    {
        throw new UnsupportedFormat('Binary encoding not implemented');
    }

    /**
     * @param $carrier
     * @return void
     */
    public function extract($carrier)
    {
        throw new UnsupportedFormat('Binary encoding not implemented');
    }
}