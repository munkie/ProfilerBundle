<?php

namespace Clamidity\ProfilerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Clamidity\ProfilerBundle\Model\Xhprof\XHProfReport;
use Clamidity\ProfilerBundle\Model\Xhprof\XHProfCallGraph;

/**
 * Description of ProfilerController
 *
 * @author Michael Shattuck <ms2474@gmail.com>
 */
class ProfilerController extends Controller
{
    /**
     *
     * @var XHProf
     */
    protected $xhprof;

    /**
     *
     * @var XHProfCallGraph
     */
    protected $callgraph;

    /**
     *
     * @param Request $request
     * @return Response 
     */
    public function indexAction(Request $request, $run)
    {
        $this->disableProfiler();

        $parameters    = $request->query->all();
        $query         = $this->getQuery($parameters);
        $squery        = $this->getSortedQuery($parameters);
        $params        = $parameters + array('run' => $run) + $this->getParameterArray();

        if ($request->query->has('all') && 0 == $request->query->get('all')) {
            $all = true;
        } else {
            $all = false;
        }

        $report = $this->getXhprof()->getReport($params);

        return $this->render('ClamidityProfilerBundle:Collector:index.html.twig', array(
            'url'    => $request->server->get('REQUEST_URI'),
            'params' => $params,
            'report' => $report,
            'run'    => $run,
            'query'  => $query,
            'squery' => $squery,
            'all'    => $all,
        ));
    }

    /**
     * @param Request $request
     * @param $run
     * @param $function
     * @return Response
     */
    public function functionAction(Request $request, $run, $function)
    {
        $this->disableProfiler();

        $xhprof           = $this->getXhprof();
        $parameters       = $_GET;
        $query            = $this->getQuery($parameters);
        $squery           = $this->getSortedQuery($parameters);
        $params           = $this->getParameterArray();
        $params['run']    = $run;
        $params['symbol'] = $function;

        foreach ($parameters as $key => $value) {
            $params[$key] = $value;
        }

        $report = $xhprof->getReport($params);

        return $this->render('ClamidityProfilerBundle:Collector:function.html.twig', array(
            'url'      => $request->server->get('REQUEST_URI'),
            'params'   => $params,
            'report'   => $report,
            'run'      => $run,
            'query'    => $query,
            'squery'   => $squery,
            'function' => $function,
        ));
    }

    public function callgraphAction(Request $request, $run)
    {
        ini_set('max_execution_time', 100);

        $xhprof     = $this->getCallGraph();
        $params     = array('run' => $run) + $request->query->all() + $this->getCallGraphArray();


        if ($params['threshold'] < 0) {
            $params['threshold'] = 0;
        } elseif ($params['threshold'] > 1) {
            $params['threshold'] = 1;
        }

        if (!empty($params['run'])) {
            $content = $xhprof->xhprof_render_image($params);
        }
        else {
            $content = $xhprof->xhprof_render_diff_image($params);
        }

        return new Response($content, 200, array('Content-Type' => 'image/png'));
    }

    /**
     * Function for retrieving parameters
     *
     * @return array
     */
    protected function getParameterArray()
    {
        $params = array(
            'run'    => '',
            'wts'    => '',
            'symbol' => '',
            'sort'   => 'wt',
            'run1'   => '',
            'run2'   => '',
            'source' => $this->container->getParameter('clamidity_profiler.file_extension'),
            'all'    => 100,
        );

        return $params;
    }

    protected function getCallGraphArray()
    {
        return array(
            'run'       => '',
            'source'    => $this->container->getParameter('clamidity_profiler.file_extension'),
            'func'      => '',
            'type'      => 'png',
            'threshold' => 0.01,
            'critical'  => true,
            'run1'      => '',
            'run2'      => ''
        );
    }

    /**
     * Function for disabling profiler 
     */
    protected function disableProfiler()
    {
        if ($this->has('profiler')) {
            $this->get('profiler')->disable();
        }
    }

    /**
     *
     * @return XHProfReport
     */
    protected function getXhprof()
    {
        if (!isset($this->xhprof)) {
            $this->xhprof = new XHProfReport($this->container->getParameter('clamidity_profiler.location_reports'));
        }

        return $this->xhprof;
    }

    /**
     *
     * @return XHProfCallGraph
     */
    protected function getCallGraph()
    {
        if (!isset($this->callgraph)) {
            $this->callgraph = new XHProfCallGraph($this->container->getParameter('clamidity_profiler.location_reports'));
        }

        return $this->callgraph;
    }

    /**
     * @param array $params
     * @return string
     */
    protected function getQuery(array $params)
    {
        return '?' . http_build_query($params);
    }

    /**
     * @param array $params
     * @return string
     */
    protected function getSortedQuery(array $params)
    {
        if (isset($params['sort'])) {
            unset($params['sort']);
        }

        return http_build_query($params);
    }
}
