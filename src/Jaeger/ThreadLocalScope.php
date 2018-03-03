<?php

namespace Jaeger;

use OpenTracing\Scope;
use OpenTracing\Span;

class ThreadLocalScope implements Scope
{
    /** @var ThreadLocalScopeManager */
    private $scopeManager;

    /** @var Span */
    private $wrapped;

    /** @var bool */
    private $finishOnClose;

    /** @var ThreadLocalScope */
    private $toRestore;

    /**
     * @param ThreadLocalScopeManager $scopeManager
     * @param Span $wrapped
     * @param bool $finishOnClose
     */
    function __construct(ThreadLocalScopeManager $scopeManager, Span $wrapped, $finishOnClose)
    {
        $this->scopeManager = $scopeManager;
        $this->wrapped = $wrapped;
        $this->finishOnClose = $finishOnClose;
        $this->toRestore = $scopeManager->tlsScope;
        $scopeManager->tlsScope = $this;
    }

    /**
     * Mark the end of the active period for the current thread and {@link Scope},
     * updating the {@link ScopeManager#active()} in the process.
     *
     * <p>
     * NOTE: Calling {@link #close} more than once on a single {@link Scope} instance leads to undefined
     * behavior.
     */
    public function close()
    {
        if ($this->scopeManager->tlsScope !== $this) {
            // This shouldn't happen if users call methods in the expected order. Bail out.
            return;
        }

        if ($this->finishOnClose) {
            $this->wrapped->finish();
        }

        $this->scopeManager->tlsScope = $this->toRestore;
    }

    /**
     * @return Span the {@link Span} that's been scoped by this {@link Scope}
     */
    public function getSpan()
    {
        return $this->wrapped;
    }
}