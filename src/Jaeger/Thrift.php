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
			$context = $span->getContext();
			$spanVars = [
				'trace_id' => $context->getTraceId(),
				'name' => $span->getOperationName(),
				'debug' => false,
				'timestamp' => $span->getStartTime(),
				'duration' => $span->getEndTime() - $span->getStartTime()
			];
			if ( $context->getParentId() !== null ) {
				$spanVars[ 'parent_id' ] = $context->getParentId();
			}
			array_push( $tSpans, new Span( $spanVars ) );
		}
		$batch = new Batch( [
			'process' => $process,
			'spans' => $tSpans
		] );

		#foreach ( $spans as $span ) {
		#	$context = $span->getContext();
		#	array_push($batch['spans'],
		#		new Span([
		#			'trace_id' => $context->getTraceId(),
		#			'name' => $span->getOperationName(),
		#			'id' => null,
		#			'parent_id' => $span->getParentId(),
		#			'annotations' => [],
		#			'binary_annotations' => [],
		#			'debug' => false,
		#			'timestamp' => $span->getStartTime(),
		#			'duration' => $span->getEndTime() - $span->getStartTime(),
		#			# 'trace_id_high' => ????
		#		])
		#	);
		#}
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