<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class MessagesController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');          
    }

    public function indexAction()   
    {
        return $this->render('AriiJIDBundle:Messages:index.html.twig' );
    }
    
    public function pieAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('chart');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'scheduler',
            'start_time' => 'CREATION_DATE',
            'end_time'   => 'CREATION_DATE' );

$qry = 'SELECT SEVERITY,count(MESSAGE_ID) as NB
 FROM SCHEDULER_MESSAGES
 where '.$sql->History($Fields).'
 GROUP BY SEVERITY';
        return $data->render_sql($qry,"SEVERITY","SEVERITY,NB");
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
            'spooler'    => 'sh.SPOOLER_ID',
            'job_name'   => 'sh.JOB_NAME',
            'error'      => 'sh.ERROR',
            'start_time' => 'sh.START_TIME',
            'end_time'   => 'sh.END_TIME' );

  /*        $qry = 'SELECT distinct sh.SPOOLER_ID as label, sh.SPOOLER_ID as value
                  FROM SCHEDULER_HISTORY sh
                  where not(sh.JOB_NAME="(Spooler)") and '.$sql->History($Fields).' order by sh.SPOOLER_ID';  
          $options->render_sql($qry,"section_id","value,label");
 */          
          $qry = 'SELECT sh.ID, sh.SPOOLER_ID as section_id, sh.JOB_NAME, sh.START_TIME, sh.END_TIME, sh.ERROR, sh.EXIT_CODE, sh.CAUSE, sh.PID, "green" as color  
                  FROM SCHEDULER_HISTORY sh
                  where not(sh.JOB_NAME="(Spooler)") and '.$sql->History($Fields).' order by sh.SPOOLER_ID, sh.JOB_NAME,sh.START_TIME';  

//          $data->set_options("section_id", $options );
          $data->event->attach("beforeRender", array( $this, "color_rows") );
          $data->render_sql($qry,"ID","START_TIME,END_TIME,JOB_NAME,color,section_id");
    }
    
    function color_rows($row){
        if ($row->get_value('END_TIME')=='') {
            $row->set_value("color", 'orange');
            $row->set_value("END_TIME", $this->ref_date );
        }
        elseif ($row->get_value('ERROR')>0) {
            $row->set_value("color", 'red');
        }
    }    

    public function barAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'sh1.SPOOLER_ID',
            'start_time' => 'sh1.START_TIME',
            'end_time'   => 'sh1.END_TIME' );

$qry = 'SELECT sh1.ID,sh1.START_TIME,sh1.END_TIME,sh1.ERROR
 FROM SCHEDULER_HISTORY sh1
 where '.$sql->History($Fields).' 
 order by sh1.END_TIME';

        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $day = substr($line['START_TIME'],8,5);
            $Days[$day]=1;
            if ($line['END_TIME']='') {
                if (isset($HR[$day])) 
                    $HR[$day]++;
                else $HR[$day]=1;
            }
            else {
                if ($line['ERROR']==0) {
                    if (isset($HS[$day])) 
                        $HS[$day]++;
                    else $HS[$day]=1;
                }
                else {
                    if (isset($HF[$day])) 
                        $HF[$day]++;
                    else $HF[$day]=1;
                }
            }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        if (isset($Days)) {
            foreach($Days as $i=>$v) {
                if (!isset($HS[$i])) $HS[$i]=0;
                if (!isset($HF[$i])) $HF[$i]=0;
                if (!isset($HR[$i])) $HR[$i]=0;
                $bar .= '<item id="'.$i.'"><HOUR>'.substr($i,-2).'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE><RUNNING>'.$HR[$i].'</RUNNING></item>';
            }
        }
        $bar .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }

    public function messagesAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            '{start_time}' => 'LOGTIME',
            '{end_time}'   => 'LOGTIME' );
            
        $qry = $sql->Select(array('MESSAGE_ID as INFO','MESSAGE_ID as ERROR','MESSAGE_ID','SCHEDULER_ID','SEVERITY',
    'LOGTIME','JOB_CHAIN','ORDER_ID','JOB_NAME','TASK','LOG','REFERENCE_MESSAGE_ID','STATUS','CREATION_DATE','CNT')) 
        .$sql->From(array('SCHEDULER_MESSAGES'))
        .$sql->Where($Fields)
        .$sql->OrderBy(array('LOGTIME desc'));

        $data->event->attach("beforeRender", array( $this, "color_messages") );
        $data->render_sql($qry,"MESSAGE_ID","LOGTIME,SCHEDULER_ID,SEVERITY,ERROR,LOG,INFO,CNT,TASK");
    }
    
    function color_messages($row){
        $message = $row->get_value('LOG');
        $row->set_value("LOGTIME", substr($message,0,19));

        $p = strpos($message,"]",26);
        // decoupage de la date 
        $message = trim(substr($message,$p+1));
        
        // est ce qu'il y a une info 
        $info = array();
        if (substr($message,0,1)=='(') {
            $p = strpos($message,")",3);
           // array_push($info,substr($message,1,$p-1));
            $message = trim(substr($message,$p+1));
        }
        if (substr($message,0,10)=='SCHEDULER-') {
            $p = strpos($message," ",10);
            $row->set_value("ERROR",substr($message,10,$p-10));
            $message = trim(substr($message,$p+1));
        }
        $row->set_value("LOG", str_replace(array('<','>'),array('&lt;','&gt;'),$message ));        
        
        foreach (array('JOB_CHAIN','ORDER_ID','JOB_NAME') as $nil ) {
            if ($row->get_value($nil)!='nil') {
                array_push($info,'<img src="'.$this->images.'/'.strtolower($nil).'.png"/>'.$row->get_value($nil));
            }
        }
        $row->set_value("INFO",implode(' ',$info));
        
        if ($row->get_value("SEVERITY")=='ERROR') {
            $row->set_row_attribute("class","backgroundfailure");
        }
        else {
            $row->set_row_attribute("class","backgroundrunning");
        }
    }

    public function messageAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');
        
        $sql = $this->container->get('arii_core.sql');
            
        $qry = $sql->Select(array('MESSAGE_ID as INFO','MESSAGE_ID as ERROR','MESSAGE_ID','SCHEDULER_ID','SEVERITY',
    'LOGTIME','JOB_CHAIN','ORDER_ID','JOB_NAME','TASK','LOG','REFERENCE_MESSAGE_ID','STATUS','CREATION_DATE','CNT')) 
        .$sql->From(array('SCHEDULER_MESSAGES'));
        if ($id>0) {
            $qry .= $sql->Where(array('MESSAGE_ID'=>$id));
        }
        else {
            $qry .= " where MESSAGE_ID in (select max(MESSAGE_ID) from SCHEDULER_MESSAGES)";
        }
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        if (is_array($Infos))
            return $this->render('AriiJIDBundle:Messages:detail.html.twig', $Infos);
        exit();
        // if (isset($Infos['LOG'])) $Infos['LOG'] = substr($Infos['LOG'],50); 
     }
}
