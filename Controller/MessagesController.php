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
    
    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Messages:toolbar.xml.twig',array(), $response );
    }

    public function formAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $this->render('AriiJIDBundle:Messages:form.json.twig',array(), $response );
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
            '{spooler}'    => 'SCHEDULER_ID',
            '{start_time}' => 'LOGTIME');

        $qry = $sql->Select(array('LOGTIME','SEVERITY','count(MESSAGE_ID) as NB'))
                .$sql->From(array('SCHEDULER_MESSAGES'))
                .$sql->Where($Fields)
                .$sql->GroupBy(array('LOGTIME','SEVERITY'))
                .$sql->OrderBy(array('LOGTIME'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $nb = $line['NB'];
            $day = substr($line['LOGTIME'],8,5);
            $Days[$day]=1;
            if ($line['SEVERITY']=='WARN') {
                if (isset($HW[$day])) 
                    $HW[$day]+=$nb;
                else $HW[$day]=$nb;
            }
            else {
                    if (isset($HE[$day])) 
                        $HE[$day]+=$nb;
                    else $HE[$day]=$nb;
           }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        foreach($Days as $i=>$v) {
            if (!isset($HW[$i])) $HW[$i]=0;
            if (!isset($HE[$i])) $HE[$i]=0;
            $bar .= '<item id="'.$i.'"><HOUR>'.substr($i,-2).'</HOUR><ERROR>'.$HE[$i].'</ERROR><WARN>'.$HW[$i].'</WARN></item>';
        }

        $bar .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }

    public function spoolerAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{start_time}' => 'LOGTIME');

        $qry = $sql->Select(array('SCHEDULER_ID','SEVERITY','count(MESSAGE_ID) as NB'))
                .$sql->From(array('SCHEDULER_MESSAGES'))
                .$sql->Where($Fields)
                .$sql->GroupBy(array('SCHEDULER_ID','SEVERITY'))
                .$sql->OrderBy(array('SCHEDULER_ID'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $id = $line['SCHEDULER_ID'];
            $nb = $line['NB'];
            $Spoolers[$id]=1;
            if ($line['SEVERITY']=='WARN') {
                if (isset($HW[$id])) 
                    $HW[$id] += $nb;
                else $HW[$id]=$nb;
            }
            else {
                    if (isset($HE[$id])) 
                        $HE[$id] += $nb;
                    else $HE[$id]=$nb;
           }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        foreach($Spoolers as $i=>$v) {
            if (!isset($HW[$i])) $HW[$i]=0;
            if (!isset($HE[$i])) $HE[$i]=0;
            $bar .= '<item id="'.$i.'"><SPOOLER>'.$i.'</SPOOLER><ERROR>'.$HE[$i].'</ERROR><WARN>'.$HW[$i].'</WARN></item>';
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
            '{start_time}' => 'LOGTIME');
            
        $qry = $sql->Select(array('MESSAGE_ID as INFO','MESSAGE_ID as ERROR','MESSAGE_ID','SCHEDULER_ID','SEVERITY',
    'LOGTIME','JOB_CHAIN','ORDER_ID','JOB_NAME','TASK','LOG','REFERENCE_MESSAGE_ID','STATUS','CREATION_DATE','CNT')) 
        .$sql->From(array('SCHEDULER_MESSAGES'))
        .$sql->Where($Fields)
        .$sql->OrderBy(array('LOGTIME desc'));

        $data->event->attach("beforeRender", array( $this, "color_messages") );
        $data->render_sql($qry,"MESSAGE_ID","LOGTIME,SCHEDULER_ID,SEVERITY,ERROR,LOG,JOB_NAME,JOB_CHAIN,ORDER_ID,CNT,TASK");
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
        
        if ($row->get_value("JOB_NAME")=='nil')
            $row->set_value("JOB_NAME",'');
        if ($row->get_value("JOB_CHAIN")=='nil')
            $row->set_value("JOB_CHAIN",'');
        if ($row->get_value("ORDER_ID")=='nil')
            $row->set_value("ORDER_ID",'');
        if ($row->get_value("SEVERITY")=='ERROR') {
            $row->set_row_color("#fbb4ae");
        }
        elseif ($row->get_value("SEVERITY")=='WARN') {
            $row->set_row_color("#ffffcc");
        }
     }

    public function message2Action()
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
     
    public function messageAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('MESSAGE_ID','SCHEDULER_ID','SEVERITY','LOGTIME','JOB_CHAIN','ORDER_ID','JOB_NAME','TASK','LOG','REFERENCE_MESSAGE_ID','STATUS','CREATION_DATE','CNT'))
                .$sql->From(array('SCHEDULER_MESSAGES'))
                .$sql->Where(array('MESSAGE_ID' => $id));
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('form');
        $data->event->attach("beforeRender", array( $this, "render_message") );
        $data->render_sql($qry,'MESSAGE_ID','MESSAGE_ID,SCHEDULER_ID,SEVERITY,LOGTIME,JOB_CHAIN,ORDER_ID,JOB_NAME,TASK,LOG,REFERENCE_MESSAGE_ID,STATUS,CREATION_DATE,CNT');
    }
    
    function render_message($row){
        if ($row->get_value("JOB_NAME")=='nil')
            $row->set_value("JOB_NAME",'');
        if ($row->get_value("JOB_CHAIN")=='nil')
            $row->set_value("JOB_CHAIN",'');
        if ($row->get_value("ORDER_ID")=='nil')
            $row->set_value("ORDER_ID",'');
     }

}
