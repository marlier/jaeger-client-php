<?php


namespace Jaeger;

use Jaeger\ThriftGen\AnnotationType;
use Jaeger\ThriftGen\Batch;
use Jaeger\ThriftGen\BinaryAnnotation;
use Jaeger\ThriftGen\Endpoint;
use Jaeger\ThriftGen\Process;
use Jaeger\ThriftGen\Span;
use const OpenTracing\Tags\COMPONENT;


class Thrift {

	const CLIENT_ADDR = "ca";
	const SERVER_ADDR = "sa";

	private function __construct() {
	}

	/**
	 * @param \Jaeger\Span[] $spans
	 * @return \Jaeger\ThriftGen\Span[]
	 */
	public static function makeZipkinBatch(array $spans): array
	{
		/** @var \Jaeger\ThriftGen\Span[] */
		$zipkinSpans = [];

		foreach ($spans as $span) {
			/** @var \Jaeger\Span $span */

			$endpoint = self::makeEndpoint(
				$span->getTracer()->getIpAddress(),
				0,  // span.port,
				$span->getTracer()->getServiceName()
			);

//            foreach ($span->getLogs() as $event) {
//                $event->setHost($endpoint);
//            }

			$timestamp = $span->getStartTime();
			$duration = $span->getEndTime() - $span->getStartTime();

			self::addZipkinAnnotations($span, $endpoint);

			$zipkinSpan = new ThriftGen\Span([
									   'name' => $span->getOperationName(),
									   'id' => $span->getContext()->getSpanId(),
									   'parent_id' => $span->getContext()->getParentId() ?? null,
									   'trace_id' => $span->getContext()->getTraceId(),
									   'annotations' => array(), // logs
									   'binary_annotations' => $span->getTags(),
									   'debug' => $span->isDebug(),
									   'timestamp' => $timestamp,
									   'duration' => $duration,
								   ]);

			$zipkinSpans[] = $zipkinSpan;
		}

		return $zipkinSpans;
	}

	private static function addZipkinAnnotations(\Jaeger\Span $span, $endpoint)
	{
		if ($span->isRpc()) {
			$isClient = $span->isRpcClient();

			if ($span->peer) {
				$host = self::makeEndpoint(
					$span->peer['ipv4'] ?? 0,
					$span->peer['port'] ?? 0,
					$span->peer['service_name'] ?? '');

				$key = ($isClient) ? self::SERVER_ADDR : self::CLIENT_ADDR;

				$peer = self::makePeerAddressTag($key, $host);
				$span->tags[$key] = $peer;
			}
		} else {
			$tag = self::makeLocalComponentTag(
				$span->getComponent() ?? $span->getTracer()->getServiceName(),
				$endpoint
			);

			$span->tags[COMPONENT] = $tag;
		}
	}

	private static function makeEndpoint(string $ipv4, int $port, string $serviceName): Endpoint
	{
		$ipv4 = self::ipv4ToInt($ipv4);

		return new Endpoint([
								'ipv4' => $ipv4,
								'port' => $port,
								'service_name' => $serviceName,
							]);
	}

	private static function ipv4ToInt($ipv4): int
	{
		if ($ipv4 == 'localhost') {
			$ipv4 = '127.0.0.1';
		} elseif ($ipv4 == '::1') {
			$ipv4 = '127.0.0.1';
		}

		return ip2long($ipv4);
	}

	// Used for Zipkin binary annotations like CA/SA (client/server address).
	// They are modeled as Boolean type with '0x01' as the value.
	private static function makePeerAddressTag($key, $host)
	{
		return new BinaryAnnotation([
										"key" => $key,
										"value" => '0x01',
										"annotation_type" => AnnotationType::BOOL,
										"host" => $host
									]);
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

	private static function makeLocalComponentTag(string $componentName, $endpoint): BinaryAnnotation
	{
		return new BinaryAnnotation([
										'key' => "lc",
										'value' => $componentName,
										'annotation_type' => AnnotationType::STRING,
										'host' => $endpoint,
									]);
	}


	public static function makeProcess(string $serviceName, array $tags): Process {
		$process = new Process([
			'serviceName' => $serviceName,
			'tags' => $tags
		]);
		return $process;
	}

}