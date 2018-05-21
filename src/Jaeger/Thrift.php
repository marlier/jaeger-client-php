<?php


namespace Jaeger;

use Jaeger\ThriftGen\Batch;
use Jaeger\ThriftGen\Process;
use Jaeger\ThriftGen\Span;

class Thrift {

	private function __construct() {
	}

	public static function makeJaegerBatch($spans, Process $process): Batch {
		$tSpans = [];

		foreach ( $spans as $span ) {
			$spanVars = [
				'trace_id' => $span->getContext()->getTraceId(),
				'name' => $span->getOperationName(),
				'debug' => $span->isDebug(),
				'timestamp' => $span->getStartTime(),
				'duration' => $span->getEndTime() - $span->getStartTime(),
				'annotations' => array(),
				'binary_annotations' => $span->getTags()
			];
			if ( $span->getContext()->getParentId() !== null ) {
				$spanVars[ 'parent_id' ] = $span->getContext()->getParentId();
			}
			array_push( $tSpans, new Span( $spanVars ) );
		}
		$batch = new Batch( [
			'process' => $process,
			'spans' => $tSpans
		] );

		return $batch;
	}

	public static function makeProcess(string $serviceName, array $tags): Process {
		$process = new Process([
			'serviceName' => $serviceName,
			'tags' => $tags
		]);
		return $process;
	}

}