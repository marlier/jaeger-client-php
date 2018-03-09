<?php

namespace Jaeger\Senders;

interface Sender
{
    /**
     * @param Span $span
     * @return int
     */
    function append(Span $span);

    /**
     * @return int
     */
    function flush();

    /**
     * @return int
     */
    function close();
}