<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class SpoolerController extends Controller
{

   public function form_toolbarAction()   
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Spooler:form_toolbar.xml.twig',array(), $response );
    }

    public function tasksAction()
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $spooler = $request->query->get( 'spooler' );
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('TASK_ID','JOB_NAME','ENQUEUE_TIME','START_AT_TIME'))
                .$sql->From(array('SCHEDULER_TASKS'))
                .$sql->Where(array('SPOOLER_ID'=>$spooler))
                .$sql->OrderBy(array('ENQUEUE_TIME desc'));
                
        $data = $dhtmlx->Connector('grid');
        $data->render_sql($qry,'TASK_ID','JOB_NAME,ENQUEUE_TIME,START_AT_TIME');     
    }
    
    public function ordersAction()
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $spooler = $request->query->get( 'spooler' );
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('ORDERING','ID','JOB_CHAIN','STATE','STATE_TEXT','TITLE'))
                .$sql->From(array('SCHEDULER_ORDERS'))
                .$sql->Where(array('SPOOLER_ID'=>$spooler))
                .$sql->OrderBy(array('ORDERING'));
                
        $data = $dhtmlx->Connector('grid');
        $data->render_sql($qry,'ORDERING','ID,JOB_CHAIN,STATE,STATE_TEXT,TITLE');     
    }

    public function jobsAction()
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $spooler = $request->query->get( 'spooler' );
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('SPOOLER_ID','PATH','STOPPED','NEXT_START_TIME'))
                .$sql->From(array('SCHEDULER_JOBS'))
                .$sql->Where(array('SPOOLER_ID'=>$spooler))
                .$sql->OrderBy(array('PATH'));
                
        $data = $dhtmlx->Connector('grid');
        $data->event->attach("beforeRender",array( $this,  "render_job" ) );
        $data->render_sql($qry,'PATH','PATH,STOPPED,NEXT_START_TIME');     
    }

    function render_job($row){
        $date = $this->container->get('arii_core.date');
        if ($row->get_value("STOPPED")==1) {
            $row->set_row_attribute("style","background-color: #fbb4ae;");
        }
        else {
            $row->set_row_attribute("style","background-color: #ccebc5;");            
        }
        $spooler = $row->get_value("SPOOLER_ID");
	$row->set_value("NEXT_START_TIME", $date->Date2Local( $row->get_value("NEXT_START_TIME"), $spooler ) );
    }

    public function task_paramsAction()
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $id = $request->query->get( 'id' );
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('TASK_ID','PARAMETERS'))
                .$sql->From(array('SCHEDULER_TASKS'))
                .$sql->Where(array('TASK_ID'=>$id));
                
        $data = $dhtmlx->Connector('data');
        $res = $data->sql->query($qry);
        $line = $data->sql->get_next($res);
        
        $params = $line['PARAMETERS'];
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

    public function deleteAction()
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $id = $request->query->get( 'id' );
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Delete(array('SCHEDULER_INSTANCES'))
                .$sql->Where(array('ID'=>$id));

        $data = $dhtmlx->Connector('data');
        $res = $data->sql->query( $qry );
            print $this->get('translator')->trans("Spooler deleted !");
//            print $this->get('translator')->trans("Unable to delete !");
        exit();
    }

    public function formAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('ID','SCHEDULER_ID','HOSTNAME','TCP_PORT','UDP_PORT','START_TIME','STOP_TIME','DB_NAME','DB_HISTORY_TABLE_NAME','DB_ORDER_HISTORY_TABLE_NAME','DB_ORDERS_TABLE_NAME','DB_TASKS_TABLE_NAME','DB_VARIABLES_TABLE_NAME','WORKING_DIRECTORY','LIVE_DIRECTORY','LOG_DIR','INCLUDE_PATH','INI_PATH','IS_SERVICE','IS_RUNNING','IS_PAUSED','IS_CLUSTER','IS_AGENT','PARAM','SUPERVISOR_HOSTNAME','SUPERVISOR_TCP_PORT','IS_SOS_COMMAND_WEBSERVICE','JETTY_HTTP_PORT','JETTY_HTTPS_PORT'))
                .$sql->From(array('SCHEDULER_INSTANCES'))
                .$sql->Where(array('ID' => $id));
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('form');
        $data->render_sql($qry,'ID','ID,SCHEDULER_ID,HOSTNAME,TCP_PORT,UDP_PORT,START_TIME,STOP_TIME,DB_NAME,DB_HISTORY_TABLE_NAME,DB_ORDER_HISTORY_TABLE_NAME,DB_ORDERS_TABLE_NAME,DB_TASKS_TABLE_NAME,DB_VARIABLES_TABLE_NAME,WORKING_DIRECTORY,LIVE_DIRECTORY,LOG_DIR,INCLUDE_PATH,INI_PATH,IS_SERVICE,IS_RUNNING,IS_PAUSED,IS_CLUSTER,IS_AGENT,PARAM,SUPERVISOR_HOSTNAME,SUPERVISOR_TCP_PORT,IS_SOS_COMMAND_WEBSERVICE,JETTY_HTTP_PORT,JETTY_HTTPS_PORT');
    }
    
   // force un update avec un show state
   public function UpdateAction($spooler='',$delay=60,$force=1) {
       $request = Request::createFromGlobals();
       if ($spooler=='')
            $spooler = $request->get('id');

       // on recupere la connection
       // on part du principe qu'on est en JID donc mode simple
       $dhtmlx = $this->container->get('arii_core.dhtmlx');
       $data = $dhtmlx->Connector('data');
       
       $sql = $this->container->get('arii_core.sql');
       $qry = $sql->Select(array('HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING'))
               .$sql->From(array('SCHEDULER_INSTANCES'))
               .$sql->Where(array('SCHEDULER_ID' => $spooler ));

      $res = $data->sql->query( $qry );
       // pourrais etre en parametre si besoin
       $protocol = "http"; $path = "";
       $hostname = '';
       while ($line = $data->sql->get_next($res)) {
            $hostname = $line['HOSTNAME'];
            $port = $line['TCP_PORT'];
            $start = $line['START_TIME'];
       }
       // Si le l'id est celui de la configuration
       // on se rabat sur celui la
       /* Obsolete
       if ($hostname=='') {
           if ($this->container->getParameter('osjs_id')==$spooler) {
            $hostname = $this->container->getParameter('osjs_ipaddress');
            $port = $this->container->getParameter('osjs_port');
            $path = $this->container->getParameter('osjs_path');
            $protocol = $this->container->getParameter('osjs_protocol');
            }
            else {
                print "$spooler !!";
                exit();
            }
       }
        */
       // on execute la commande show_state
       $SOS = $this->container->get('arii_jid.sos');
       $result = $SOS->Command($spooler,'<show_state/>');
       $is_paused = $is_running = 0;
       $host = $hostname;
       if (isset($result['spooler']['answer']['state_attr'])){
           $date = $this->container->get('arii_core.date');
           $attr = $result['spooler']['answer']['state_attr'];
           $state = $attr['state'];
           $start = $date->Date2Local($attr['spooler_running_since'],$spooler);
           $host = $attr['host'];
            print "<table>";
            print "<tr><th align='right'>State</th><td>".$state."</td></tr>";
            print "<tr><th align='right'>Start</th><td>".$start."</td></tr>";
            print "</table>";
            if ($state=="paused") {
                $is_paused=1;
                $is_running=0;
            }
            elseif ($state=="running") {
                $is_paused=0;
                $is_running=1;
            }
        }
        else {
            print '<font color="red">!!!!</font>';
        }
        
        $qry = $sql->Update(array('SCHEDULER_INSTANCES'))
                .$sql->Set(array(   'START_TIME' => $start,
                                    'IS_RUNNING' => $is_running,
                                    'IS_PAUSED' => $is_paused,
                                    'HOSTNAME' => $host ))
                .$sql->Where(array('SCHEDULER_ID' => $spooler ));

        $res = $data->sql->query( $qry );
        exit();
   }
}
