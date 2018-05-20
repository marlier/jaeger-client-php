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
			array_push( $tSpans, new Span( [] ) );
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