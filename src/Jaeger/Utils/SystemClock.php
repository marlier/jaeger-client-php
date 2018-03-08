<?php

namespace Jaeger\Utils;

class SystemClock implements Clock
{

    /**
     * Returns the current time in microseconds.
     *
     * @return int the difference, measured in microseconds, between the current time and and the Epoch
     * (that is, midnight, January 1, 1970 UTC).
     */
    function currentTimeMicros()
    {
        list($mSec, $sec) = explode(" ", microtime());
        return round(((float)$mSec + (float)$sec) * 1000);
    }

    /**
     * Returns the current value of the running Java Virtual Machine's high-resolution time source, in
     * nanoseconds.
     *
     * <p>
     * This method can only be used to measure elapsed time and is not related to any other notion of
     * system or wall-clock time.
     *
     * @return int the current value of the running Java Virtual Machine's high-resolution time source, in
     * nanoseconds
     */
    function currentNanoTicks()
    {
        return 0;
    }

    /**
     * @return bool true if the time returned by {@link #currentTimeMicros()} is accurate enough to
     * calculate span duration as (end-start). If this method returns false, the {@code Tracer} will
     * use {@link #currentNanoTicks()} for calculating duration instead.
     */
    function isMicrosAccurate()
    {
        return true;
    }
}