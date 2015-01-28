<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class JobController extends Controller
{
    
    public function toolbar_executeAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_execute.xml.twig",array(), $response );
    }

    public function toolbar_startAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_start.xml.twig",array(), $response );
    }
    
    public function toolbar_killAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_kill_task.xml.twig",array(), $response );
    }

    public function toolbar_deleteAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_delete_task.xml.twig",array(), $response );
    }

    public function toolbar_stopAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_stop.xml.twig",array(), $response );
    }
       
    public function toolbar_unstopAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_unstop.xml.twig",array(), $response );
    }

    public function toolbar_paramsAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Job:toolbar_params.xml.twig",array(), $response );
    }

/*  A REVOIR */    
    
    public function toolbar_schedule_listAction()
    {
        return $this->render('AriiJIDBundle:Toolbar:schedule_list.html.twig');
    }
    public function toolbar_refreshAction()
    {
        return $this->render('AriiJIDBundle:Toolbar:refresh.html.twig');
    }
    
    public function toolbar_start_job_paramAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_start_job_param.html.twig");
    }

    public function toolbar_start_orderAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_start_order.html.twig");
    }

    public function toolbar_stop_jobAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_stop_job.html.twig");
    }

    public function toolbar_purge_jobAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_purge_job.html.twig");
    }

    public function toolbar_purge_orderAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_purge_order.html.twig");
    }

    public function toolbar_kill_jobAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_kill_job.html.twig");
    }

    public function toolbar_job_whyAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_job_why.html.twig");
    }

    public function toolbar_add_orderAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_add_order.html.twig");
    }

    public function toolbar_order_paramAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:toolbar_order_param.html.twig");
    }

    public function add_order_parametersAction()
    {
        
    }
    
    public function start_job_parametersAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $qry = "SELECT parameters FROM SCHEDULER_HISTORY WHERE id='$id'";
        $res = $data->sql->query($qry);
        $line = $data->sql->get_next($res);
        
        $params = $line['parameters'];
        $Parameters = array();
        
        while (($p = strpos($params,'<variable name="'))>0) {
            $begin = $p+16;
            $end = strpos($params,'" value="',$begin);
            $var = substr($params,$begin,$end-$begin);
            $params = substr($params,$end+9);
            $end = strpos($params,'"/>');
            $val = substr($params,0,$end);
            $params = substr($params,$end+2);
            if (strpos(" $val",'password')>0) {
                // a voir avec les connexions
            } 
            else {
                $Parameters[$var] = $val;
            }
        }
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        
        foreach ($Parameters as $vr => $vl)
        {
            $list .= '<row><cell>'.$vr.'</cell><cell>'.$vl.'</cell></row>';
        }
        $list .= '</rows>';
        
        $response->setContent($list);
        return $response;
        
    }
    
    public function footerAction()
    {
        return $this->render("AriiJIDBundle:Toolbar:footer.xml.twig");
    }
    
}
