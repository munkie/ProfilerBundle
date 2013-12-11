<?php

namespace Clamidity\ProfilerBundle\DataCollector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Clamidity\ProfilerBundle\Model\Xhprof\XHProfLib;

/**
 * XhprofDataCollector.
 *
 * @author Jonas Wouters <hello@jonaswouters.be>
 */
class XhprofCollector extends DataCollector
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $runId;

    /**
     * @var bool
     */
    protected $profiling = false;

    /**
     * @var string
     */
    protected $xhprof;

    /**
     * @var string
     */
    protected $fileExtension;

    /**
     * @var boolean
     */
    protected $enabled;

    /**
     * @var boolean
     */
    protected $overwrite;

    /**
     * @var string
     */
    protected $reportsLocation;

    /**
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->fileExtension = $container->getParameter('clamidity_profiler.file_extension');
        $this->enabled = $container->getParameter('clamidity_profiler.enabled');
        $this->reportsLocation = $container->getParameter('clamidity_profiler.location_reports');
        $this->overwrite = $container->getParameter('clamidity_profiler.overwrite');

        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if ($this->extensionEnabled()) {

            if (!$this->runId) {
                $this->stopProfiling();
            }

            $this->data = array(
                'xhprof' => $this->runId,
                'source' => $this->fileExtension,
            );
        }
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param string $requestUri
     */
    public function startProfiling($requestUri = null)
    {
        if ($this->extensionEnabled()) {

            if (PHP_SAPI == 'cli') {
                $_SERVER['REMOTE_ADDR'] = null;
                $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
            }

            if ($requestUri) {
                $_SERVER['REQUEST_URI'] = $requestUri;
            }

            $this->profiling = true;
            xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
            xhprof_enable(XHPROF_FLAGS_NO_BUILTINS);

            if ($this->logger) {
                $this->logger->debug('Enabled XHProf');
            }
        }
    }

    /**
     * @return null|string
     */
    public function stopProfiling()
    {
        if (!$this->extensionEnabled() || !$this->profiling) {
            return null;
        }

        $this->profiling = false;
        $xhprofData = xhprof_disable();

        if ($this->logger) {
            $this->logger->debug('Disabled XHProf');
        }

        $xhprofRuns = new XHProfLib($this->reportsLocation);
        $this->runId = $xhprofRuns->save_run($xhprofData, $this->fileExtension);

        return $this->runId;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'xhprof';
    }

    /**
     * Gets the run id.
     *
     * @return integer The run id
     */
    public function getXhprof()
    {
        if ($this->extensionEnabled()) {
            return $this->data['xhprof'];
        }
    }

    /**
     * Gets the XHProf url.
     *
     * @return integer The XHProf url
     */
    public function getXhprofUrl()
    {
        if ($this->extensionEnabled()) {
            return $_SERVER['SCRIPT_NAME'] . '/_memory_profiler/' . $this->getXhprof() . '/';
        }
    }

    /**
     * @return bool
     */
    protected function extensionEnabled()
    {
        return function_exists('xhprof_enable');
    }

    /**
     * @return string
     */
    protected function getFileName()
    {
        $uri  = 'url:_';
        $uri .= $_SERVER['REQUEST_URI'];
        $uri  = str_replace('/', '_', $uri);
        $uri  = str_replace('_app_dev.php', 'app_dev.php', $uri);

        if (!$this->overwrite) {
            $uri .= '|date:_'.date('d-m-Y').'|time:_'.date('g:i:sa');
        }

        return $uri;
    }
}
