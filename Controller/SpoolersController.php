<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class SpoolersController extends Controller
{
    public function indexAction()   
    {
        return $this->render('AriiJIDBundle:Spoolers:index.html.twig' );
    }

    public function toolbarAction()   
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Spoolers:toolbar.xml.twig',array(), $response );
    }

    public function formAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $this->render('AriiJIDBundle:Spoolers:form.json.twig',array(), $response );
    }

    public function chartsAction()   
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
                .$sql->OrderBy(array('SPOOLER_ID'));
        
        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        
        
        return $this->render('AriiJIDBundle:Spoolers:charts.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function listAction()   
    {
        return $this->render('AriiJIDBundle:Spoolers:list.html.twig' );
    }

    public function menuAction()   
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Spoolers:menu.xml.twig',array(), $response );
    }

    public function submenuAction()
    {
        $menu = "spoolers";
        $sub = array( 'index', 'list' );
        $here = $this->getRequest()->getPathInfo();
        $l = strlen($here)*(-1);
        $liste = array();
        foreach ($sub as $p) {
            $class='';
            $url = $this->generateUrl('arii_JID_'.$menu.'_'.$p);
            if (substr($url,$l)==$here) $class='active';
            array_push($liste, array( 'module' => $menu.'.'.$p, 'url' => $url, 'class' => $class ) );
        }

        return $this->render('AriiJIDBundle:Default:submenu.html.twig', array(
          'menu' => $liste
        ));
    }

    public function pieAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('chart');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME' );

$qry = $sql->Select(array('SPOOLER_ID as SPOOLER','count(ID) as NB'))
        .$sql->From(array('SCHEDULER_HISTORY'))
        .$sql->Where($Fields)
        .$sql->GroupBy(array('SPOOLER_ID'));
        return $data->render_sql($qry,"SPOOLER","SPOOLER,NB");
    }

    public function timelineAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('scheduler');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();

//        $options = $dhtmlx->Connector('options');

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'sh.SPOOLER_ID',
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{end_time}'   => 'sh.END_TIME' );

          $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID as SECTION_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID','sh.ID as COLOR'))  
                  .$sql->From(array('SCHEDULER_HISTORY sh'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME'));  

//          $data->set_options("section_id", $options );
          $data->event->attach("beforeRender", array( $this, "color_rows") );
          $data->render_sql($qry,"ID","START_TIME,END_TIME,JOB_NAME,COLOR,SECTION_ID");
    }
    
    function color_rows($row){
        if ($row->get_value('END_TIME')=='') {
            $row->set_value("COLOR", 'orange');
            $row->set_value("END_TIME", $this->ref_date );
        }
        elseif ($row->get_value('ERROR')>0) {
            $row->set_value("COLOR", 'red');
        }
    }    

    public function barAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'sh1.SPOOLER_ID',
            '{start_time}' => 'sh1.START_TIME',
            '{end_time}'   => 'sh1.END_TIME' );

$qry = $sql->Select(array('sh1.SPOOLER_ID','sh1.ERROR','isnull(END_TIME) as END','count(ID) as NB'))
       .$sql->From(array('SCHEDULER_HISTORY sh1'))
       .$sql->Where($Fields)
       .$sql->GroupBy(array('SPOOLER_ID','sh1.ERROR','isnull(END_TIME)'))
       .$sql->OrderBy(array('sh1.SPOOLER_ID'));

        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $spooler = $line['SPOOLER_ID'];
            $nb = $line['NB'];
            $Spoolers[$spooler]=1;
            if ($line['END']==1) {
                if (isset($HR[$spooler])) 
                    $HR[$spooler]+=$nb;
                else $HR[$spooler]=$nb;                
            }
            else {
                if ($line['ERROR']==0) {
                    if (isset($HS[$spooler])) 
                        $HS[$spooler]+=$nb;
                    else $HS[$spooler]=$nb;
                }
                else {
                    if (isset($HF[$spooler])) 
                        $HF[$spooler]+=$nb;
                    else $HF[$spooler]=$nb;
                }
            }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        if (isset($Spoolers)) {
            foreach($Spoolers as $i=>$v) {
                if (!isset($HS[$i])) $HS[$i]=0;
                if (!isset($HF[$i])) $HF[$i]=0;
                if (!isset($HR[$i])) $HR[$i]=0;
                $bar .= '<item id="'.$i.'"><SPOOLER>'.$i.'</SPOOLER><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE><RUNNING>'.$HR[$i].'</RUNNING></item>';
            }
        }
        $bar .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }

    public function timeline_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('scheduler');

       $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->get('ref_date');

        $sql = $this->container->get('arii_core.sql');
        
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'   => 'soh.JOB_CHAIN',
            '{error}'      => 'sosh.ERROR',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );

          $qry = $sql->Select(array('soh.HISTORY_ID as ID','soh.SPOOLER_ID as SECTION_ID','soh.JOB_CHAIN','soh.START_TIME','soh.END_TIME','sosh.ERROR','\'green\' as fill'))  
                 .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                 .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                 .$sql->Where($Fields)
                 .$sql->OrderBy(array('soh.SPOOLER_ID','soh.JOB_CHAIN','soh.START_TIME')); 
          $data->event->attach("beforeRender", array( $this, "color_rows") );
          $data->render_sql($qry,"ID","START_TIME,END_TIME,JOB_CHAIN,fill,section_id");
    }

    public function list_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('ID','SCHEDULER_ID','HOSTNAME','START_TIME','TCP_PORT','IS_RUNNING','IS_PAUSED'))
               .$sql->From(array('SCHEDULER_INSTANCES s'))
               .$sql->OrderBy(array('SCHEDULER_ID'));
        
        $data->event->attach("beforeRender", array( $this, "render_spoolers") );
        $data->render_sql($qry,"ID","SCHEDULER_ID,STATUS,START_TIME,HOSTNAME,TCP_PORT,IS_RUNNING,IS_PAUSED");
    }
    
    function render_spoolers($row) {
        if ($row->get_value('IS_PAUSED')==1) {
            $row->set_row_color("orange");
            $row->set_value("STATUS", 'PAUSED' );
        }
       elseif ($row->get_value('IS_RUNNING')==0) {
            $row->set_row_color("#fbb4ae");
            $row->set_value("STATUS", 'STOPPED' );
        }
        else {
            $row->set_row_color("#ccebc5");
            $row->set_value("STATUS", 'RUNNING' );
        }
    }
}
