<?php

namespace Jaeger;

use Jaeger\Codec\BinaryCodec;
use Jaeger\Codec\CodecInterface;
use Jaeger\Codec\TextCodec;
use Jaeger\Codec\ZipkinCodec;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;
use Monolog\Logger;
use OpenTracing\Exceptions\InvalidSpanOption;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\StartSpanOptions;
use OpenTracing;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;
use const OpenTracing\Formats\BINARY;
use const OpenTracing\Formats\HTTP_HEADERS;
use const OpenTracing\Formats\TEXT_MAP;
use Psr\Log\LoggerInterface;

class Tracer implements OpenTracing\Tracer
{
    /** @var string */
    private $serviceName;

    /** @var ReporterInterface */
    private $reporter;

    /** @var SamplerInterface */
    private $sampler;

    private $ipAddress;

    private $metricsFactory;

    private $metrics;

    /** @var string */
    private $debugIdHeader;

    /** @var CodecInterface[] */
    private $codecs;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $oneSpanPerRpc;

    private $tags;

    /** @var ScopeManager */
    private $scopeManager;

    public function __construct(
        string $serviceName,
        ReporterInterface $reporter,
        SamplerInterface $sampler,
        bool $oneSpanPerRpc = True,
        LoggerInterface $logger = null,
        string $traceIdHeader = TRACE_ID_HEADER,
        string $baggageHeaderPrefix = BAGGAGE_HEADER_PREFIX,
        string $debugIdHeader = DEBUG_ID_HEADER_KEY,
        array $tags = null
    )
    {
        $this->serviceName = $serviceName;
        $this->reporter = $reporter;
        $this->sampler = $sampler;
        $this->oneSpanPerRpc = $oneSpanPerRpc;
        $this->logger = $logger ?? new Logger('jaeger_tracing');

        $this->ipAddress = getHostByName(getHostName());

        $this->debugIdHeader = $debugIdHeader;

        $this->codecs = [
            TEXT_MAP => new TextCodec(
                False,
                $traceIdHeader,
                $baggageHeaderPrefix,
                $debugIdHeader
            ),
            HTTP_HEADERS => new TextCodec(
                True,
                $traceIdHeader,
                $baggageHeaderPrefix,
                $debugIdHeader
            ),
            BINARY => new BinaryCodec(),
            ZIPKIN_SPAN_FORMAT => new ZipkinCodec(),
        ];

        $this->tags = [
            JAEGER_VERSION_TAG_KEY => JAEGER_CLIENT_VERSION,
        ];
        if ($tags !== null) {
            $this->tags = array_merge($this->tags, $tags);
        }

        $hostname = gethostname();
        if ($hostname === FALSE) {
            $this->logger->error('Unable to determine host name');
        } else {
            $this->tags[JAEGER_HOSTNAME_TAG_KEY] = $hostname;
        }

        $this->scopeManager = new ScopeManager();
    }

    public function setSampler(SamplerInterface $sampler)
    {
        $this->sampler = $sampler;

        return $this;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

	/**
	 * @inheritdoc
	 */
    public function getScopeManager() {
		return $this->scopeManager;
	}

	/**
	 * @inheritdoc
	 */
	public function getActiveSpan() {
		$activeScope = $this->scopeManager->getActive();
		if ( $activeScope == null ) {
			return null;
		}
		return $activeScope->getSpan();
	}

	/**
	 * @inheritdoc
	 */
	public function startActiveSpan( $operationName, $options = [] ) {
		if ( !$options instanceof StartSpanOptions ) {
			$options = StartSpanOptions::create( $options );
		}

		if ( $this->hasParentInOptions( $options ) && $this->getActiveSpan() !== null ) {
			$parent = $this->getActiveSpan()->getContext();
			$options->withParent( $parent );
		}

		$span = $this->startSpan( $operationName, $options );
		$scope = $this->scopeManager->activate( $span, $options->shouldFinishSpanOnClose() );
		return $scope;
	}

	/**
     * @param string $operationName
     * @param array|OpenTracing\StartSpanOptions $options
     * @return Span
     * @throws InvalidSpanOption for invalid option
     */
    public function startSpan($operationName, $options = [])
    {
    	if ( !$options instanceof StartSpanOptions ) {
    		$options = StartSpanOptions::create( $options );
		}

		$parent = null;
		if ( !empty( $options->getReferences() ) ) {
			$parent = $options->getReferences()[0]->getContext();
		}
        $tags = $options->getTags();
        $startTime = $options->getStartTime();

//        if ($options['references']) {
//            if (is_array($options['references'])) {
//                $references = $options['references'][0];
//            }
//            $parent = $references->referenced_context;
//        }

        $rpcServer = ($tags !== null) &&
            ($tags[SPAN_KIND] ?? null) == SPAN_KIND_RPC_SERVER;

        if ($parent === null) {
            $traceId = $this->randomId();
            $spanId = $traceId;
            $parentId = null;
            $flags = 0;
            $baggage = null;
            if ($parent === null) {
                list($sampled, $samplerTags) = $this->sampler->isSampled($traceId, $operationName);
                if ($sampled) {
                    $flags = SAMPLED_FLAG;
                    $tags = $tags ?? [];
                    foreach ($samplerTags as $key => $value) {
                        $tags[$key] = $value;
                    }
                }
            } else {  // have debug id
                $flags = SAMPLED_FLAG | DEBUG_FLAG;
                $tags = $tags ?? [];
                $tags[$this->debugIdHeader] = $parent->getDebugId();
            }
        } else {
            $traceId = $parent->getTraceId();
            if ($rpcServer && $this->oneSpanPerRpc) {
                // Zipkin-style one-span-per-RPC
                $spanId = $parent->getSpanId();
                $parentId = $parent->getParentId();
            } else {
                $spanId = $this->randomId();
                $parentId = $parent->getSpanId();
            }

            $flags = $parent->getFlags();
            $baggage = $parent->getBaggage();
        }

        $spanContext = new SpanContext(
            $traceId,
            $spanId,
            $parentId,
            $flags,
            $baggage
        );

        $span = new Span(
            $spanContext,
            $this,
            $operationName,
            $tags ?? [],
            $startTime
        );

        if (($rpcServer || $parentId === null) && ($flags & SAMPLED_FLAG)) {
            // this is a first-in-process span, and is sampled
            $span->setTags($this->tags);
        }

        return $span;
    }

    /**
     * @param OpenTracing\SpanContext $spanContext
     * @param int $format
     * @param array|binary $carrier
     * @throws UnsupportedFormat when the format is not recognized by the tracer
     * implementation
     */
    public function inject(OpenTracing\SpanContext $spanContext, $format, &$carrier)
    {
        $codec = $this->codecs[$format] ?? null;
        if ($codec === null) {
            throw new UnsupportedFormat($format);
        }

        return $codec->inject($spanContext, $carrier);
    }

    /**
     * @param int $format
     * @param array|binary $carrier
     * @return OpenTracing\SpanContext
     * @throws SpanContextNotFound when a context could not be extracted from Reader
     * @throws UnsupportedFormat when the format is not recognized by the tracer
     * implementation
     */
    public function extract($format, $carrier)
    {
        $codec = $this->codecs[$format] ?? null;
        if ($codec === null) {
            throw new UnsupportedFormat($format);
        }

        $context = $codec->extract($carrier);
        if ($context === null) {
            throw new UnsupportedFormat('Failed to find span context');
        }

        return $context;
    }

    /**
     * Allow tracer to send span data to be instrumented.
     *
     * This method might not be needed depending on the tracing implementation
     * but one should make sure this method is called after the request is finished.
     * As an implementor, a good idea would be to use an asynchronous message bus
     * or use the call to fastcgi_finish_request in order to not to delay the end
     * of the request to the client.
     *
     * @see fastcgi_finish_request()
     * @see https://www.google.com/search?q=message+bus+php
     */
    public function flush()
    {
        $this->reporter->close();
    }

    public function reportSpan(Span $span)
    {
        $this->reporter->reportSpan($span);
    }

    public function __toString(): string
    {
        return 'Tracer';
    }

    private function randomId(): int
    {
        return random_int(0, PHP_INT_MAX);
    }

    private function hasParentInOptions( StartSpanOptions $options ) {
    	$references = $options->getReferences();
    	foreach ( $references as $reference ) {
    		if ( $reference->isType( OpenTracing\Reference::CHILD_OF ) ) {
    			return $reference->getContext();
			}
		}
		return null;
	}
}
