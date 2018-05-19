<?php
/**
 * Created by PhpStorm.
 * User: imarlier
 * Date: 5/19/18
 * Time: 6:11 PM
 */

namespace Jaeger;

use OpenTracing;
use OpenTracing\Span;

class Scope implements OpenTracing\Scope {

	/** @var Span */
	private $span;

	public function __construct( ScopeManager $scopeManager, Span $span, $finishSpanOnClose ) {
		$this->scopeManager = $scopeManager;
		$this->span = $span;
		$this->finishSpanOnClose = $finishSpanOnClose;
		$this->priorActiveSpan = $scopeManager->getActive();
	}

	public function close() {
		if ( $this->scopeManager->getActive() !== $this ) {
			return;
		}

		if ( $this->finishSpanOnClose ) {
			$this->span->finish();
		}

	}

	public function getSpan() {
		return $this->span;
	}
}