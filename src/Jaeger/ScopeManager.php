<?php
/**
 * Created by PhpStorm.
 * User: imarlier
 * Date: 5/19/18
 * Time: 5:37 PM
 */

namespace Jaeger;

use OpenTracing;
use OpenTracing\Span;

class ScopeManager implements OpenTracing\ScopeManager {

	/* @var Scope */
	private $active;

	public function activate( Span $span, $finishSpanOnClose ) {
		$this->active = new Scope($this, $span, $finishSpanOnClose);
		return $this->active;
	}

	public function getActive() {
		return $this->active;
	}

	public function setActive( Scope $scope = null ) {
		$this->active = $scope;
	}
}