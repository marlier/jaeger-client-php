<?php

namespace Jaeger;

use Exception;
use Jaeger\Reporter\CompositeReporter;
use Jaeger\Reporter\LoggingReporter;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use Jaeger\Sampler\SamplerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use OpenTracing\GlobalTracer;

class Config
{
    private $config;

    /** @var string */
    private $serviceName;

    private $errorReporter;

    private $initialized = false;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        $config,
        string $serviceName = null,
        LoggerInterface $logger = null
//        $metricsFactory = null
    )
    {
        $this->config = $config;

        $this->serviceName = (array_key_exists('service_name', $config)) ? $config['service_name'] : $serviceName;
        if ($this->serviceName === null) {
            throw new Exception('service_name required in the config or param');
        }

//        $this->errorReporter = new ErrorReporter(
//            metrics=Metrics(),
//            logger=logger if self.logging else None,
//        );

        $this->logger = $logger ?? new Logger('jaeger_tracing');
        $this->logger->debug(print_r($config, true));
    }

    /** @return Tracer|null */
    public function initializeTracer()
    {
        if ($this->initialized) {
            $this->logger->warning('Config\initializeTracer: Jaeger tracer already initialized, skipping');
            return null;
        }

        $channel = $this->getLocalAgentSender();

        $sampler = $this->getSampler();
        if ($sampler === null) {
            $sampler = new ConstSampler(true);
        }
        $this->logger->info('Config\initializeTracer: Using sampler ' . $sampler);

        $reporter = new RemoteReporter(
            $channel,
            $this->serviceName,
            $this->getBatchSize(),
            $this->logger
        );

        if ($this->getLogging()) {
            $reporter = new CompositeReporter($reporter, new LoggingReporter($this->logger));
        }

        $tracer = $this->createTracer($reporter, $sampler);

        $this->initializeGlobalTracer($tracer);
        return $tracer;
    }

    public function createTracer(ReporterInterface $reporter, SamplerInterface $sampler): Tracer
    {
        return new Tracer(
            $this->serviceName,
            $reporter,
            $sampler,
            true,
            $this->logger
        );
    }

    private function initializeGlobalTracer($tracer)
    {
        GlobalTracer::set($tracer);
        $this->logger->info(
            sprintf(
                'OpenTracing\GlobalTracer initialized to %s[app_name=%s]',
                $tracer,
                $this->serviceName
            )
        );
    }

    private function getLogging(): bool
    {
        return (array_key_exists('logging', $this->config)) ? (bool)$this->config['logging'] : false;
    }

    /**
     * @return SamplerInterface|null
     * @throws Exception
     */
    private function getSampler()
    {
        $samplerConfig = $this->config['sampler'] ?? [];
        $samplerType = $samplerConfig['type'] ?? null;
        $samplerParam = $samplerConfig['param'] ?? null;

        if ($samplerType === null) {
            return null;
        } elseif ($samplerType === SAMPLER_TYPE_CONST) {
            return new ConstSampler($samplerParam ?? false);
        } elseif ($samplerType === SAMPLER_TYPE_PROBABILISTIC) {
            return new ProbabilisticSampler((float) $samplerParam);
        }
//        } elseif (in_array($samplerType, [SAMPLER_TYPE_RATE_LIMITING, 'rate_limiting'])) {
//            return RateLimitingSampler(max_traces_per_second=float(sampler_param))
//        }

        throw new Exception('Unknown sampler type ' . $samplerType);
    }

    private function getBatchSize(): int
    {
        if (isset($this->config['reporter_batch_size'])) {
            return (int) $this->config['reporter_batch_size'];
        }
        return 10;
    }

    private function getLocalAgentSender(): LocalAgentSender
    {
        $this->logger->info('Config\getLocalAgentSender: Initializing Jaeger Tracer with UDP reporter to ' .
							$this->getLocalAgentReportingHost() . ':' . $this->getLocalAgentReportingPort());
        return new LocalAgentSender(
            $this->getLocalAgentReportingHost(),
            $this->getLocalAgentReportingPort(),
			$this->getBatchSize(),
			$this->logger
        );
    }

    private function getLocalAgentGroup()
    {
        return ( array_key_exists( 'local_agent', $this->config ) ) ? $this->config['local_agent'] : [];
    }

    private function getLocalAgentReportingHost(): string
    {

        return ( array_key_exists( 'reporting_host', $this->getLocalAgentGroup() ) )
			? $this->getLocalAgentGroup()['reporting_host']
			: DEFAULT_REPORTING_HOST;
    }

    private function getLocalAgentSamplingPort(): int {
		return ( array_key_exists( 'sampling_port', $this->getLocalAgentGroup() ) )
			? $this->getLocalAgentGroup()[ 'sampling_port' ]
			: DEFAULT_SAMPLING_PORT;
	}

    private function getLocalAgentReportingPort(): int
    {
    	$this->logger->debug(print_r($this->getLocalAgentGroup(), true));
        return ( array_key_exists( 'reporting_port', $this->getLocalAgentGroup() ) )
			? $this->getLocalAgentGroup()['reporting_port']
			: DEFAULT_REPORTING_PORT;
    }
}
