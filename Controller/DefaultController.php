<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends Controller
{
    protected $images;
    protected $TZLocal;
    protected $TZSpooler;
    protected $TZOffset;
    protected $CurrentDate;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
          
          $this->CurrentDate = date('Y-m-d');
    }

    public function ribbonAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        
        return $this->render('AriiJIDBundle:Default:ribbon.json.twig',array(), $response );
    }

    public function readmeAction()
    {
        return $this->render('AriiJIDBundle:Default:readme.html.twig');
    }

    public function indexAction()   
    {
        $session = $this->container->get('arii_core.session');
        
        // Une date peut etre passe en get
        $request = Request::createFromGlobals();
        if ($request->query->get( 'ref_date' )) {
            $ref_date   = $request->query->get( 'ref_date' );
            $session->setRefDate( $ref_date );
        } else {
            $ref_date   = $session->getRefDate();
        }
        $Timeline['ref_date'] = $ref_date;
        
        $past   = $session->getRefPast();
        $future = $session->getRefFuture();
        
        // On prend 24 fuseaux entre maintenant et le passe
        // on trouve le step en minute
        $step = ($future-$past)*2.5; // heure * 60 minutes / 24 fuseaux
        if ($step == 0) $step = 1;
        $Timeline['step'] = $step;
    
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['js_date'] = $year.','.($month - 1).','.$day;
        
        $refresh = $session->GetRefresh();
        
        // Liste des spoolers pour cette plage
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME' );

    $qry = $sql->Select(array('SPOOLER_ID'),'distinct') 
               .$sql->From(array('SCHEDULER_HISTORY'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array( 'SPOOLER_ID' ));

    $SPOOLERS = array();
        if ($data) {
            $res = $data->sql->query( $qry );
            while ($line = $data->sql->get_next($res)) {
                array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
            }
        }
        $Timeline['spoolers'] = $SPOOLERS;
        return $this->render('AriiJIDBundle:Default:index.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }
    
    public function lastAction()
    {
        return $this->render('AriiJIDBundle:Default:activities.html.twig' );
    }

    public function plannedAction()
    {
        return $this->render('AriiJIDBundle:Default:planned.html.twig', 
                array(  'refresh'=>$this->getRefresh() )
                );
    }
    
    public function planned_pieAction()
    {
        return $this->render('AriiJIDBundle:Default:planned_pie.html.twig' );
    }

    public function historyAction()
    {
        return $this->render('AriiJIDBundle:Default:history.html.twig' );
    }

    public function history_pieAction()
    {
        return $this->render('AriiJIDBundle:Default:history_pie.html.twig' );
    }

    public function history_timelineAction()
    {
        return $this->render('AriiJIDBundle:Default:history_timeline.html.twig');
    }

    public function messagesAction()
    {
        return $this->render('AriiJIDBundle:Default:messages.html.twig');
    }
    
    public function spoolersAction()
    {
        return $this->render('AriiJIDBundle:Default:spoolers.html.twig');
    }

    public function eventsAction()
    {
        return $this->render('AriiJIDBundle:Default:events.html.twig');
    }

    public function timelineAction()
    {
        return $this->render('AriiJIDBundle:Default:timeline.html.twig');
    }

    public function pie_chartAction()
    {
        $request = $here = $this->getRequest()->getPathInfo();
        if (strpos($request,"/orders"))
            return $this->render('AriiJIDBundle:Sidebar:pie_chart_orders.html.twig');
        return $this->render('AriiJIDBundle:Sidebar:pie_chart.html.twig');
    }

    public function job_infoAction()
    {
        $request = $here = $this->getRequest()->getPathInfo();
        if (strpos($request,"/orders"))
            return $this->render('AriiJIDBundle:Sidebar:job_info_orders.html.twig');
        return $this->render('AriiJIDBundle:Sidebar:job_info.html.twig');
    }

    public function toolbar_activitiesAction()
    {
        return $this->render('AriiJIDBundle:Default:toolbar_activities.xml.twig' );
    }

    public function last_xmlAction($history_max=0,$ordered = 0) {
    {
        $color = array (
            'SUCCESS' => '#ccebc5',
            'RUNNING' => '#ffffcc',
            'FAILURE' => '#fbb4ae',
            'STOPPED' => '#FF0000'
        );

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        if ($request->get('ordered')>0) {
            $ordered = $request->get('ordered');
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $session = $this->container->get('arii_core.session');
        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');
        $date = $this->container->get('arii_core.date');
        $Status = $session->get('status');

    /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME' );
        if ($ordered==0) {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.START_TIME desc','sh.END_TIME desc'));  
    
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        
        $res = $data->sql->query( $qry );
        $nb=0;
        $H = array();
        while ($line = $data->sql->get_next($res)) {
            $nb++;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($H[$id])) {
                $H[$id]++;
            }
            else {
                $H[$id]=0;
            }
            if ($H[$id]>$history_max) {
                continue;
            }
            if ($line['END_TIME']=='') {
                $status = 'RUNNING';
            } // cas des historique
            elseif ($line['ERROR']>0) {
                $status = 'FAILURE';
            }
            else {
                $status = 'SUCCESS';
            }
            $list .='<row id="'.$line['ID'].'" style="background-color: '.$color[$status].'">';
            // Cas particulier pour les RUNNING
            $list .='<cell>'.$line['SPOOLER_ID'].'</cell>';              
            $list .='<cell>'.dirname($line['JOB_NAME']).'</cell>'; 
            $list .='<cell>'.basename($line['JOB_NAME']).'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>'; 
            if ($status=='RUNNING') {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$start.'</cell>'; 
                $list .='<cell/>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            else {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],$line['END_TIME'],'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$date->ShortDate($start).'</cell>'; 
                $list .='<cell>'.$date->ShortDate($end).'</cell>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            $list .='<cell>'.$line['EXIT_CODE'].'</cell>';
            $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt($start,$end,$status).'"/>]]></cell>'; 
            $list .='<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($line['CAUSE']).'.png"/>]]></cell>'; 
            $list .='</row>';
        }
        
        if ($nb==0) {
            exit();
        }
        
        $list .= "</rows>\n";
        $response->setContent( $list );
        return $response;
    }
        
    }
}
