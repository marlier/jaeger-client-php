<?php

namespace Jaeger;

use InvalidArgumentException;
use Jaeger\Codec\BinaryCodec;
use Jaeger\Codec\CodecInterface;
use Jaeger\Codec\TextCodec;
use Jaeger\Codec\ZipkinCodec;
use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\SamplerInterface;
use Monolog\Logger;
use OpenTracing\Exceptions\SpanContextNotFound;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing;
use OpenTracing\ScopeManager;
use Psr\Log\LoggerInterface;

use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;
use const OpenTracing\Formats\BINARY;
use const OpenTracing\Formats\HTTP_HEADERS;
use const OpenTracing\Formats\TEXT_MAP;

class Tracer implements OpenTracing\Tracer
{
    /** @var string */
    private $serviceName;

    /** @var ReporterInterface */
    private $reporter;

    /** @var SamplerInterface */
    private $sampler;

    /** @var string */
    private $ipAddress;

    /** @var string */
    private $debugIdHeader;

    /** @var CodecInterface[] */
    private $codecs;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $oneSpanPerRpc;

    /** @var array */
    private $tags;

    /** @var \OpenTracing\ScopeManager */
    private $scopeManager;

    /**
     * @param string $serviceName
     * @param ReporterInterface $reporter
     * @param SamplerInterface $sampler
     * @param bool $oneSpanPerRpc
     * @param ScopeManager|null $scopeManager
     * @param LoggerInterface|null $logger
     * @param string $traceIdHeader
     * @param string $baggageHeaderPrefix
     * @param string $debugIdHeader
     * @param array|null $tags
     */
    public function __construct(
        $serviceName,
        ReporterInterface $reporter = null,
        SamplerInterface $sampler = null,
        $oneSpanPerRpc = True,
        ScopeManager $scopeManager = null,
        LoggerInterface $logger = null,
        $traceIdHeader = TRACE_ID_HEADER,
        $baggageHeaderPrefix = BAGGAGE_HEADER_PREFIX,
        $debugIdHeader = DEBUG_ID_HEADER_KEY,
        $tags = null
    )
    {
        $this->serviceName = $this->checkValidServiceName($serviceName);

        if ($reporter === null) {
            $this->reporter = new InMemoryReporter();
        } else {
            $this->reporter = $reporter;
        }

        if ($sampler === null) {
            $this->sampler = new ConstSampler();
        } else {
            $this->sampler = $sampler;
        }

        $this->oneSpanPerRpc = $oneSpanPerRpc;

        if ($scopeManager === null) {
            $this->scopeManager = new ThreadLocalScopeManager();
        } else {
            $this->scopeManager = $scopeManager;
        }

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
    }

    /**
     * @param SamplerInterface $sampler
     * @return $this
     */
    public function setSampler($sampler)
    {
        $this->sampler = $sampler;

        return $this;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * @param string $operationName
     * @param array $options
     * @return Span|OpenTracing\Span
     */
    public function startSpan($operationName, $options = [])
    {
        $parent = $options['child_of'] ?? null;
        $tags = $options['tags'] ?? null;
        $startTime = $options['startTime'] ?? null;

//        if ($options['references']) {
//            if (is_array($options['references'])) {
//                $references = $options['references'][0];
//            }
//            $parent = $references->referenced_context;
//        }

        if ($parent instanceof Span) {
            /** @var Span */
            $parent = $parent->getContext();
        }

        $rpcServer = ($tags !== null) &&
            ($tags[SPAN_KIND] ?? null) == SPAN_KIND_RPC_SERVER;

        if ($parent === null || $parent->isDebugIdContainerOnly()) {
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
     * @param string $format
     * @param $carrier
     * @return mixed
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
     * @param string $format
     * @param $carrier
     * @return SpanContext|null|OpenTracing\SpanContext
     */
    public function extract($format, $carrier)
    {
        $codec = $this->codecs[$format] ?? null;
        if ($codec === null) {
            throw new UnsupportedFormat($format);
        }

        $context = $codec->extract($carrier);
        if ($context === null) {
            throw new SpanContextNotFound('Failed to find span context');
        }

        return $context;
    }

    public function flush()
    {
        $this->sampler->close();
        $this->reporter->close();
    }

    /**
     * @param Span $span
     */
    public function reportSpan($span)
    {
        $this->reporter->reportSpan($span);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return 'Tracer';
    }

    /**
     * @return int
     */
    private function randomId()
    {
        return rand(0, PHP_INT_MAX);
    }

    /**
     * @return OpenTracing\ScopeManager
     */
    public function getScopeManager()
    {
        return $this->scopeManager;
    }

    /**
     * @return null|OpenTracing\Span
     */
    public function getActiveSpan()
    {
        $scope = $this->scopeManager->getActiveScope();
        return $scope == null ? null : $scope->getSpan();
    }

    /**
     * @param string $operationName
     * @param array $options
     * @return OpenTracing\Scope|OpenTracing\Span
     * @throws \Exception
     */
    public function startActiveSpan($operationName, $options = [])
    {
        return $this->scopeManager->activate($this->startSpan($operationName, $options));
    }

    /**
     * @return ReporterInterface
     */
    public function getReporter()
    {
        return $this->reporter;
    }

    /**
     * @param string $serviceName
     * @return string
     */
    private function checkValidServiceName($serviceName)
    {
        if ($serviceName == null || strlen(trim($serviceName)) == 0) {
            throw new InvalidArgumentException("Service name must not be null or empty");
        }
        return $serviceName;
    }

    public function close()
    {
        $this->flush();
    }
}
