<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DetailController extends Controller
{
    
    public function job_logAction()
    {
        $request = Request::createFromGlobals();
        $id = intval($request->query->get( 'id' ));
        return $this->render('AriiJIDBundle:Ajax:job_log.html.twig', $this->job_log($id));
    }

    public function job_log_uploadAction()
    {
        $request = Request::createFromGlobals();
        $id = intval($request->query->get( 'id' ));
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="job_log_'.$id.'.txt"');          
        print $this->job_log($id);
        exit();
    }

    public function order_log_uploadAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        if (strpos($id,'/')>0) {
            $I = explode('/',substr($id,2));
            $server = array_shift($I);
            $order = array_pop($I);
            $job_chain = implode('/',$I);
            $query = "select ID,ORDER_XML from SCHEDULER_ORDERS where ID='".$order."'";
            if ($result = $data->sql->query($query)) {
                $order = $data->sql->get_next($result);
                $xml = simplexml_load_string($order['ORDER_XML']);
                if ($xml != '') {
                    $logs = trim((string)$xml->log);
                    $log = gzdeflate($logs,9);
                    $Res = @gzinflate($log);
                }
                else {
                    $Res = '?!';
                }
                header('Content-type: text/plain');
                header('Content-Disposition: attachment; filename="order_log_'.$id.'.txt"');          
                print $Res;
            }
            else {
                print "...";
            }
            
            exit();
            
        }
        
        $qry = "select soh.HISTORY_ID,soh.LOG,soh.END_TIME,soh.SPOOLER_ID,soh.ORDER_ID
                FROM SCHEDULER_ORDER_HISTORY soh
                WHERE soh.HISTORY_ID=$id";
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }
        while ($Infos = $data->sql->get_next($res))
        {
            if($Infos['END_TIME'] == '')
            {
                header('Content-type: text/plain');
                header('Content-Disposition: attachment; filename="order_log_'.$id.'.txt"');          
                print "RUNNING...";
                exit();

            } 
            else{
                $Res = @gzinflate ( mb_substr($Infos['LOG'], 10, -8) );
            }
         }
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="order_log_'.$id.'.txt"');          
        print $Res;
        exit();
    }

    public function job_log($id)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');

        $data = $dhtmlx->Connector('data');
        $qry = $sql->Select(array('h.ID','h.SPOOLER_ID','h.LOG','h.END_TIME'))
            .$sql->From(array('SCHEDULER_HISTORY h'))
            .$sql->Where(array('h.ID'=>$id));
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            echo $exc->message();
        }

        $logs = array();
        $log = '';
        while ($Infos = $data->sql->get_next($res))
        {
            if ($Infos['END_TIME'] == '')
            {
                print "JOB RUNNING";
                exit();
                if ($dh = @fopen("http://".$Infos['HOSTNAME'].':'.$Infos['TCP_PORT'].'/show_log?task='.$Infos['ID'], "rb")){
                   $n = 0; $xml = '';
                    while (($log = fread($dh,409600)) and ($n < 100)) {
                        $xml .= $log;
                        $n++;
                    }
                    $Log = array();
                    $n = 0;
                    $last = '';
                    foreach (explode("\n", $xml ) as $l) {
                        if (substr($l,0,13) == "<span class='") {
                            $b = strpos($l,'>',13)+1;
                            $e = strpos($l,' </span>',$b);
                            $new = trim(substr($l,$b,$e-$b));
                            if ($last != $new ) {
                                array_push($Log,$new);
                            }
                            else {
                                array_push($Log,'');
                            }
                            $n++;
                            $last = $new;
                        } 
                   }
                   if ($n<100) {
                        $Infos['LOG'] = $Log;
                    }
                    else {
                        $Infos['LOG'] = array_merge(array_slice($Log,0,50),array('...'),array_slice($Log,$n-50,50));
                    }
              } else {
                  $log = "http://".$Infos['HOSTNAME'].':'.$Infos['TCP_PORT'].'/show_log?task='.$Infos['ID'];
              }
           } else {
              $log = @gzinflate ( substr($Infos['LOG'], 10, -8) );
           }     
        }
        return $log;
    }
    
    
    public function job_log_xmlAction()
    {
            $request = Request::createFromGlobals();
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            $sql = $this->container->get('arii_core.sql');
            $data = $dhtmlx->Connector('data');
        $qry = $sql->Select(array('h.ID','h.SPOOLER_ID','h.LOG','h.END_TIME'))
                .$sql->From(array('SCHEDULER_HISTORY h'));
            if ($request->query->get( 'id' )=='') {                
//                $qry .= ' WHERE h."ID" in (select max("ID") from SCHEDULER_HISTORY where "ERROR">0)';                
            }
            else {
                $id = intval($request->query->get( 'id' ));
                $qry .= $sql->Where(array('h.ID'=>$id));
            }

        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            echo $exc->message();
        }

        $logs = array();
        while ($Infos = $data->sql->get_next($res))
        {
            if ($Infos['END_TIME'] == '')
            {
                print "JOB RUNNING";
                exit();
                if ($dh = @fopen("http://".$Infos['HOSTNAME'].':'.$Infos['TCP_PORT'].'/show_log?task='.$Infos['ID'], "rb")){
                   $n = 0; $xml = '';
                    while (($log = fread($dh,409600)) and ($n < 100)) {
                        $xml .= $log;
                        $n++;
                    }
                    $Log = array();
                    $n = 0;
                    $last = '';
                    foreach (explode("\n", $xml ) as $l) {
                        if (substr($l,0,13) == "<span class='") {
                            $b = strpos($l,'>',13)+1;
                            $e = strpos($l,' </span>',$b);
                            $new = trim(substr($l,$b,$e-$b));
                            if ($last != $new ) {
                                array_push($Log,$new);
                            }
                            else {
                                array_push($Log,'');
                            }
                            $n++;
                            $last = $new;
                        } 
                   }
                   if ($n<100) {
                        $Infos['LOG'] = $Log;
                    }
                    else {
                        $Infos['LOG'] = array_merge(array_slice($Log,0,50),array('...'),array_slice($Log,$n-50,50));
                    }
              } else {
                  $Infos['LOG'] = "http://".$Infos['HOSTNAME'].':'.$Infos['TCP_PORT'].'/show_log?task='.$Infos['ID'];
              }
           } else {
               if (gettype($Infos['LOG'])=='object') {
                     $Infos['LOG'] = explode("\n",gzinflate ( substr($Infos['LOG']->load(), 10, -8) ));
               }
               else {
                     $Infos['LOG'] = explode("\n",gzinflate ( substr($Infos['LOG'], 10, -8) ));
               }
           }   
           
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rows><head><afterInit><call command="clearAll"/></afterInit></head>';
        sort($Infos['LOG']);
        foreach ($Infos['LOG'] as $l) {
           if ($l=='') continue;
           $date = substr($l,0,23);
           $code = '';
           $bgcolor ='';
           if (($p = strpos(' '.$l,'['))>0) {
                $type = strtoupper(substr($l,$p,1));
           
                if ($type == 'E') {
                    $bgcolor=' style="background-color: #fbb4ae;"';
                }
                elseif ($type=='W') {
                    $bgcolor=' style="background-color: #ffffcc;"';
                }
                $e = strpos($l,']');
                $msg = ltrim(substr($l,$e+2));
           }
           else {
               $type = '';
               $msg = substr($l,9);
           }
           
           if (substr($msg,0,10)=='SCHEDULER-') {
               $code = substr($msg,10,3);
               $msg = substr($msg,15);
           }
           else {
               $code = '';
           } 
           
           //erreur JAVA
           if (substr($msg,0,6)=='FATAL ') {
               $type = 'F';
               $bgcolor=' style="background-color: black; color: red;"';
               $msg = substr($msg,6);
           }
           elseif  (substr($msg,0,6)=='ERROR ') {
               $bgcolor=' style="background-color: red; color: yellow;"';
               $msg = substr($msg,6);
           }
           elseif (substr($msg,0,5)=='INFO ') {
               $bgcolor = ' style="background-color: lightblue;"';
               $msg = substr($msg,5);
           }           
           $xml .= "<row$bgcolor><cell>$date</cell>";
           $xml .= "<cell>$type</cell>";
           $xml .= "<cell><![CDATA[".utf8_encode($msg)."]]></cell>";
           $xml .= "<cell>$code</cell>";
         //  $xml .= "<cell><![CDATA[".utf8_encode($l)."]]></cell>";
           $xml .= "</row>"; 
        }
        $xml .= "</rows>\n";
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
         $response->setContent( $xml );
        return $response;
        }       
    }

    public function order_loghtmlAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $qry = "select soh.HISTORY_ID,soh.LOG,soh.END_TIME,soh.SPOOLER_ID,soh.ORDER_ID
                FROM SCHEDULER_ORDER_HISTORY soh
                WHERE soh.HISTORY_ID=$id";
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }
        while ($Infos = $data->sql->get_next($res))
        {
            if($Infos['END_TIME'] == '')
            {
                $query = "select ID,ORDER_XML from SCHEDULER_ORDERS where ID='".$Infos['ORDER_ID']."'";
                $result = $data->sql->query($query);
                $order = $data->sql->get_next($result);
                $xml = simplexml_load_string($order['ORDER_XML']);
                $logs = trim((string)$xml->log);
                $log = gzdeflate($logs,9);
                $Infos['LOG'] = explode("\n",@gzinflate($log));
            } 
            else{
                $Infos['LOG'] = explode("\n",@gzinflate ( substr($Infos['LOG'], 10, -8) ));
            }
            
            return $this->render('AriiJIDBundle:Ajax:order_log.html.twig', $Infos);
        }
    }



    public function plannedAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $date = $this->container->get('arii_core.date');
        $sql = $this->container->get('arii_core.sql');
                
        $qry = $sql->Select(array('ID','SCHEDULER_ID',
                    'SCHEDULER_HISTORY_ID','JOB','JOB_CHAIN','ORDER_ID',
                    'SCHEDULE_PLANNED','SCHEDULE_EXECUTED','PERIOD_BEGIN','PERIOD_END',
                    'IS_REPEAT','START_START','RESULT'))
                .$sql->From(array('DAYS_SCHEDULE'))
                .$sql->Where(array('ID'=>$id));
        $res = $data->sql->query($qry);
        
        $Infos = $data->sql->get_next($res);
        if (!isset($Infos['ID'])) {
            exit();
        }
        $Infos['STATUS'] = "SUCCESS";
        if($Infos['SCHEDULE_EXECUTED'] == "")
        {
            if(strtotime($Infos['SCHEDULE_PLANNED'])<=time()){
                $Infos['STATUS'] = "LATE";
                $Infos['DELAY'] = $this->Duration(strtotime($Infos['SCHEDULE_PLANNED']),time());
            } else{
                $Infos['STATUS'] = "WAITING";
                $Infos['DELAY'] = $this->Duration(time(),strtotime($Infos['SCHEDULE_PLANNED']));
            }
        } else{
            $Infos['DELAY'] = $this->Duration(strtotime($Infos['SCHEDULE_PLANNED']),strtotime($Infos['SCHEDULE_EXECUTED']));
        }      
        // on met en heure locale
        $Infos['SCHEDULE_PLANNED'] = $date->Date2Local( $Infos['SCHEDULE_PLANNED'], $Infos['SCHEDULER_ID'] );
        $Infos['SCHEDULE_EXECUTED'] = $date->Date2Local( $Infos['SCHEDULE_EXECUTED'], $Infos['SCHEDULER_ID'] );
       return $this->render("AriiJIDBundle:Ajax:planned.html.twig",$Infos);
       
    }
    
    public function planned_logAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $qry = $sql->Select(array('h.ID','h.SPOOLER_ID','h.LOG,HOSTNAME','TCP_PORT','UDP_PORT','h.END_TIME'))
                .$sql->From(array('SCHEDULER_HISTORY h'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES i',array('h.SPOOLER_ID','i.scheduler'))
                .$sql->LeftJoin('DAYS_SCHEDULE ds',array('h.ID','ds.SCHEDULER_HISTORY_ID'))
                .$sql->From(array('h.ID'=>$id));
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            echo $exc;
        }
        $logs = array();
        while ($Infos = $data->sql->get_next($res))
        { 
            $Infos['LOG'] = explode("\n",@gzinflate ( substr($Infos['LOG'], 10, -8) ));    
            return $this->render('AriiJIDBundle:Ajax:planned_log.html.twig', $Infos);
        }
        print "$id ?!";
        exit();
    }
    
    public function orderAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
       // $Info = array();
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('soh.HISTORY_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.SPOOLER_ID','soh.TITLE','soh.STATE','soh.STATE_TEXT','soh.START_TIME','soh.END_TIME','soh.LOG','sosh.STEP',
                'sosh.ERROR','sh.EXIT_CODE','sh.ERROR_TEXT','sh.STEPS,sh.CAUSE',
                'so.PAYLOAD as PARAMETERS','so.ORDER_XML'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDERS so',array('soh.ORDER_ID','so.ID'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->LeftJoin('SCHEDULER_HISTORY sh',array('sosh.TASK_ID','sh.ID'))
                .$sql->Where(array('soh.HISTORY_ID'=> $id))
                .$sql->OrderBy(array('soh.HISTORY_ID desc','sosh.STEP desc'));

        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }
        
        $tools = $this->container->get('arii_core.tools');
        $date = $this->container->get('arii_core.date');
        while ($Infos = $data->sql->get_next($res))
        {
            // Detail 
            if ($Infos['ORDER_XML']!=null)
            {
                $order_xml = $tools->xml2array($Infos['ORDER_XML']);
                $setback = 0; $setback_time = '';
                if (isset($order_xml['order_attr']['suspended']) && $order_xml['order_attr']['suspended'] == "yes")
                {
                    $order_status = "SUSPENDED";
                }
                elseif (isset($order_xml['order_attr']['setback_count']))
                {
                    $order_status = "SETBACK";
                    $setback = $order_xml['order_attr']['setback_count'];
                    $setback_time = $order_xml['order_attr']['setback'];
                }
                $next_time = '';
                if (isset($order_xml['order_attr']['start_time'])) {
                    $next_time = $order_xml['order_attr']['start_time'];
                }
                $at = '';
                if (isset($order_xml['order_attr']['at'])) {
                    $at = $order_xml['order_attr']['at'];
                }
                $hid = 0;
                if (isset($order_xml['order_attr']['history_id'])) {
                    $hid = $order_xml['order_attr']['history_id'];
                }
            } 

            $Infos['STATUS'] = "SUCCESS";
            if($Infos['END_TIME']=="")
            {
                $Infos['STATUS'] = "RUNNING";
                if ($Infos['ERROR']>0)
                {
                    $Infos['STATUS'] = "SUSPENDED";
                }
                list($Infos['START_TIME'],$Infos['END_TIME'],$Infos['NEXT_START_TIME'],$Infos['DURATION']) = $date->getLocaltimes( $Infos['START_TIME'], $Infos['END_TIME'], '', $Infos['SPOOLER_ID'] );
                $Infos['DURATION'] = "";
            } else
            {
                if($Infos['ERROR']>0)
                {
                  $Infos['STATUS'] = "FAILURE";
                }
                list($Infos['START_TIME'],$Infos['END_TIME'],$Infos['NEXT_START_TIME'],$Infos['DURATION']) = $date->getLocaltimes( $Infos['START_TIME'], $Infos['END_TIME'], '', $Infos['SPOOLER_ID'] );
            }
            if ($Infos['PARAMETERS']) {
                $params = $Infos['PARAMETERS'];
                // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
                while (($p = strpos($params,'<variable name="'))>0) {
                    $begin = $p+16;
                    $end = strpos($params,'" value="',$begin);
                    $var = substr($params,$begin,$end-$begin);
                    $params = substr($params,$end+9);
                    $end = strpos($params,'"/>');
                    $val = substr($params,0,$end);
                    $params = substr($params,$end+2);
                    
                    # Attention aux password !
                    $val = preg_replace("/password=(.*?) /","password=**********","$val ");
                    $Value[$var]=$val;
                }
                $Infos['PARAMETERS'] = $Value;
            }
           return $this->render('AriiJIDBundle:Ajax:order.html.twig', $Infos);
        }
        print "$id ?!";
        exit();
    }

    public function planAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        // Order direct
        if (strpos(" $id",'/')>0) {
            $Infos = explode('/',$id);
            $spooler = array_shift($Infos);
            $order = array_pop($Infos);
            $job_chain = implode('/',$Infos);
        }
        else {
            // recuperation du contexte
            $qry = $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_ID'))
                    .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                    .$sql->Where(array('HISTORY_ID' => $id));

            $res = $data->sql->query($qry);
            $line = $data->sql->get_next($res);        
            if(empty($line))
            {
                return new Response($id.' ?!');
            }
            $job_chain = $line['JOB_CHAIN'];
            $order = $line['ORDER_ID'];
            $spooler = $line['SPOOLER_ID'];
        }

        $qry = $sql->Select(array('so.PAYLOAD as PARAMETERS','so.initial_state as STATE','so.RUN_TIME as START_TIME'))
                .$sql->From(array('SCHEDULER_ORDERS so'))
                .$sql->Where(array('so.SPOOLER_ID' => $spooler, 
                    'so.JOB_CHAIN' => $job_chain,
                    'so.ID' => $order ));
                
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }

        while ($Infos = $data->sql->get_next($res))
        {
            $Infos['ORDER_ID'] = $order;
            $Infos['JOB_CHAIN'] = $job_chain;
            $Infos['STATUS'] = "ACTIVATED";
            if ($Infos['PARAMETERS']) {
                if (gettype($Infos['PARAMETERS'])=='object') {
                    $params = $Infos['PARAMETERS']->load();
                }
                else {
                    $params = $Infos['PARAMETERS'];
                }
                // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
                while (($p = strpos($params,'<variable name="'))>0) {
                    $begin = $p+16;
                    $end = strpos($params,'" value="',$begin);
                    $var = substr($params,$begin,$end-$begin);
                    $params = substr($params,$end+9);
                    $end = strpos($params,'"/>');
                    $val = substr($params,0,$end);
                    $params = substr($params,$end+2);
                    
                    # Attention aux password !
                    $val = preg_replace("/password=(.*?) /","password=**********","$val ");
                    $Value[$var]=$val;
                }
                $Infos['PARAMETERS'] = $Value;
            } 
           return $this->render('AriiJIDBundle:Ajax:order_plan.html.twig', $Infos);
        }
        print "$id ?!";
        exit();
    }

    public function next_jobAction( )
    {
            $request = Request::createFromGlobals();
            $id = $request->get('id');
        
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            $sql = $this->container->get('arii_core.sql');
            $date = $this->container->get('arii_core.date');
            $INFOS = explode('/',$id);
            $spooler =  array_shift($INFOS);
            $job = implode('/',$INFOS);
            $data = $dhtmlx->Connector('data');
            $qry = $sql->Select(array('SPOOLER_ID','PATH as JOB_NAME','STOPPED','NEXT_START_TIME' ))
                    .$sql->From(array('SCHEDULER_JOBS'))
                    .$sql->Where(array('SPOOLER_ID'=>$spooler,'PATH'=>$job));
            
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }

        while ($Infos = $data->sql->get_next($res)) {
            // heure courant
            $Infos['NEXT_START_TIME'] = $date->Date2Local($Infos['NEXT_START_TIME'],$Infos['SPOOLER_ID']);
            return $this->render('AriiJIDBundle:Ajax:job_next.html.twig', $Infos);
        }
        exit();      
    }

    public function next_orderAction( ) {
    // traité dans le plan    
    }

    public function jobAction( )
    {
            $request = Request::createFromGlobals();
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            $sql = $this->container->get('arii_core.sql');
            $date = $this->container->get('arii_core.date');
            
            $data = $dhtmlx->Connector('data');
            if (!$data) {
                exit();
            }
            $qry = $sql->Select(array('h.ID','h.SPOOLER_ID','h.JOB_NAME','h.START_TIME','h.END_TIME','h.CAUSE','h.STEPS','h.EXIT_CODE','h.ERROR','h.ERROR_CODE','h.ERROR_TEXT','h.PARAMETERS','h.PID',    
                'oh.JOB_CHAIN','oh.STATE','oh.ORDER_ID','oh.TITLE',
                'osh.TASK_ID'))
                    .$sql->From(array('SCHEDULER_HISTORY h'))
                    .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY osh',array('h.ID','osh.TASK_ID'))
                    .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY oh',array('osh.HISTORY_ID','oh.HISTORY_ID'));
            if ($request->query->get( 'id' )=='') {
                exit();
                $qry .= $sql->Where(array('{max_error_id}' => 'h.ID'));
            }
            else {
                $id = intval($request->query->get( 'id' ));
                $qry .= $sql->Where(array('h.ID'  => $id));
            }
            
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }

        while ($Infos = $data->sql->get_next($res)) {
                     
            $Infos['STATUS'] = 'SUCCESS';
            if ($Infos['END_TIME'] == '') {
                $Infos['STATUS'] = 'RUNNING';
                $Infos['DURATION'] = '';
                
            }
            else {
                if ($Infos['ERROR']>0) {
                    $Infos['STATUS'] = 'FAILURE';
                }
                $Infos['DURATION'] = $this->Duration(strtotime($Infos['START_TIME']),strtotime($Infos['END_TIME']));
            }
            # Parametres
            if ($Infos['PARAMETERS']) {
                $params = $Infos['PARAMETERS'];
                // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
                while (($p = strpos($params,'<variable name="'))>0) {
                    $begin = $p+16;
                    $end = strpos($params,'" value="',$begin);
                    $var = substr($params,$begin,$end-$begin);
                    $params = substr($params,$end+9);
                    $end = strpos($params,'"/>');
                    $val = substr($params,0,$end);
                    $params = substr($params,$end+2);
                    
                    # Attention aux password !
                    $val = preg_replace("/password=(.*?) /","password=**********","$val ");
                    $Value[$var]=$val;
                }
                $Infos['PARAMETERS'] = $Value;
            }
            // heure courant
            $Infos['END_TIME'] = $date->Date2Local($Infos['END_TIME'],$Infos['SPOOLER_ID']);
            $Infos['START_TIME'] = $date->Date2Local($Infos['START_TIME'],$Infos['SPOOLER_ID']);            
            return $this->render('AriiJIDBundle:Ajax:job.html.twig', $Infos);
        }
        exit();      
    }
        
    function FormatTime($d) {
       $str = '';
       if ($d>86400) {
           $n = (int) ($d/86400);
           $d %= 86400;
           $str .= ' '.$n.'d'; 
           return $str;
       }
       if ($d>3600) {
           $n = (int) ($d/3600);
           $d %= 3600;
           $str .= ' '.$n.'h';           
           return $str;
       }
       if ($d>60) {
           $n = (int) ($d/60);
           $d %= 60;
           $str .= ' '.$n.'m';           
       }
       if ($d>0) 
        $str .= ' '.$d.'s';
       return $str;        
    }
    
    function Duration($start,$end = '' ) {
       if ($end == '') {
           $end = time();
       }
       $d = $end - $start;
       return $this->FormatTime($d);
    }
    
    public function job_infoAction( )
    {
            $request = Request::createFromGlobals();
            $id = $request->query->get( 'id' );
                    
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            
            $data = $dhtmlx->Connector('data');

$qry = "select h.ID,h.JOB_NAME 
from SCHEDULER_HISTORY h
where h.ID=$id";

        $res = $data->sql->query( $qry );
        if ($Infos = $data->sql->get_next($res)) {
            // On recupere les parametres de connexion
            
            $SOS = $this->container->get('arii_core.sos');
            $data = $SOS->XMLCommand($Infos['HOSTNAME'], $Infos['TCP_PORT'],'<show_job job="'.$Infos['JOB_NAME'].'"/>');
            if (!isset($data['spooler'])) {
                print_r($data);
                print $Infos['HOSTNAME'];
                print $Infos['TCP_PORT'];
                exit();
            }
            
            // print "<pre>"; print_r($data); print "</pre>"; exit();
            // Complement
            foreach (array('JOB_NAME') as $i) {
                $data[$i] = $Infos[$i];
            }
            return $this->render('AriiJIDBundle:Ajax:job_info.html.twig', $data);
        }
        else {
            print "$id ?!";
            exit();
        }
    }

    public function grid_historyAction( )
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_NAME'))
                .$sql->From(array('SCHEDULER_HISTORY h'));

        if ($request->query->get( 'id' )!='') {
            $id = $request->query->get( 'id' );
            $qry .= $sql->Where(array('h.ID'=>$id));
        }
        else {
            exit();
            $qry .= $sql->Where(array('{max_error_id}' => 'h.ID' ));
        }

        $data = $dhtmlx->Connector('data');
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        $spooler =  $Infos['SPOOLER_ID'];
        $job =      $Infos['JOB_NAME'];
        
        $data2 = $dhtmlx->Connector('grid');
        $qry2 = $sql->Select(array('ID','SPOOLER_ID','JOB_NAME','START_TIME','END_TIME','EXIT_CODE','ERROR','ERROR_TEXT'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('SPOOLER_ID' => $spooler, 'JOB_NAME' => $job))
                .$sql->OrderBy(array('START_TIME desc')); 
        $data2->event->attach("beforeRender",array( $this,  "render_grid" ) );
        $data2->render_sql($qry2,'ID','START_TIME,END_TIME,DURATION,ERROR,EXIT_CODE,ERROR_TEXT');     
    }
        
    public function grid_order_historyAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get('id');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data =$dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('soh.SPOOLER_ID','soh.ORDER_ID','soh.JOB_CHAIN'))
               .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
               .$sql->Where(array('soh.HISTORY_ID' => $id));
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        $spooler_id = $Infos['SPOOLER_ID'];
        $order_id = $Infos['ORDER_ID'];
        $job_chain = $Infos['JOB_CHAIN'];
         
        $grid = $dhtmlx->Connector('data');
        $qry2 = $sql->Select(array('soh.history_id','soh.order_id','soh.start_time','soh.end_time','soh.state_text','soh.title',
                'sosh.task_id','sosh.state','sosh.step','soh.state as end_state','sosh.error','sosh.error_text','sosh.start_time as start_step','sosh.end_time as end_step'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.history_id','sosh.history_id'))
                .$sql->Where(array('soh.spooler_id' => $spooler_id,
                                   'soh.order_id' => $order_id,
                                   'soh.job_chain' => $job_chain ))
                .$sql->OrderBy(array('soh.history_id desc','start_time DESC'));
        $res = $data->sql->query( $qry2 );
        
        $xml = '<rows>';
        $last = -1; $order = '';
        $state = $start = $step = $end = $text = $title = '';
        $error = $nb_err = 0;
        while ($Infos = $data->sql->get_next($res)) {
            $id = $Infos['history_id'];
            // si nouvel ordre, on ecrit le dernier order et on ouvre un nouveau
            if ($id!=$last) {
                $xml .= $this->order_row($order,$last,$state,$step,$start,$end,$text,$title,$error,$nb_err);
                // on demarre un nouveau contenu
                $order = '';
                $state = $Infos['end_state'];
                $start = $Infos['start_time'];
                $end =$Infos['end_time'];
                $text = $Infos['state_text'];
                $title = $Infos['title'];
                $nb_err = 0;
            }
            $error = $Infos['error'];
            if ($error==0) {
                $col = '#ccebc5';
            }
            else {
                $nb_err++;
                $col = '#fbb4ae';
            }
            $order .= '<row id="'.$id.'#'.$Infos['task_id'].'" style="background-color: '.$col.';">';
            $order .= '<cell>'.$Infos['state'].'</cell>';
            $order .= '<cell>'.$Infos['step'].'</cell>';
            $order .= '<cell>'.$Infos['start_step'].'</cell>';
            $order .= '<cell>'.$Infos['end_step'].'</cell>';
            $order .= "<cell>".$this->Duration(strtotime($Infos['start_step']),strtotime($Infos['end_step']))."</cell>";            
            $order .= '<cell>'.$Infos['error_text'].'</cell>';
            $step = $Infos['step'];
            $order .= '</row>';               
            $last = $id;
           // $xml .= '<row><cell>'.$Infos['history_id'].'-'.$Infos['error'].'-'.$Infos['end_state'].'-'.$Infos['step'].'-'.$Infos['state'].'-'.$Infos['error'].'</cell></row>';
        }        
        $xml .= $this->order_row($order, $id,$state,$step,$start,$end,$text,$title,$error,$nb_err);
        $xml .= '</rows>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $xml );
        return $response;
        // $grid->event->attach("beforeRender",array( $this, "render_order"));
        //$grid->render_sql($qry2,'history_id',"start_time,end_time,duration,state,end_state,error");
    }
    private function order_row($order, $id,$state,$step,$start,$end,$text,$title,$error,$nb_err) {
        if ($order === '') { return ''; }
        if ($error > 0)  {
            $col = "#fbb4ae";
        } // au moins une erreur ?
        elseif ($nb_err>0) {
            $col = "#ccebc5";
        }
        else {
             $col = "#ccebc5";
        }
        $xml = '<row id="'.$id.'"  style="background-color: '.$col.';">';
        $xml .= "<cell>$state</cell>";
        $xml .= "<cell>$step</cell>";
        $xml .= "<cell>$start</cell>";
        $xml .= "<cell>$end</cell>";
        $xml .= "<cell>".$this->Duration(strtotime($start),strtotime($end))."</cell>";
        $xml .= "<cell>$text";
        if ($title != '') {
            $xml .= " ($title)";
        }
        $xml .= "</cell>";
        $xml .=  $order.'</row>';
        return $xml;
    }
    function render_order($row){
        $start = strtotime($row->get_value("start_time"));
	$end = $row->get_value("end_time");
        $row->set_value("duration",$this->Duration( $start, strtotime($end) ) );
        
        $error = $row->get_value("error");
        if($error == 0)
        {
            $row->set_row_attribute("class","backgroundsuccess");
        }
        else 
        {
            $row->set_row_attribute("class","backgroundfailure");
        }
        $row->set_value("start_time", $this->ShortDate( $row->get_value("start_time") )  );
        if (substr($row->get_value("start_time"),0,10)==substr($row->get_value("end_time"),0,10))
            $row->set_value("end_time", substr($row->get_value("end_time"),11 ) );        
        else
            $row->set_value("end_time", $this->ShortDate( $row->get_value("end_time") )  );        
    }
    
    function render_grid($row){
        $date = $this->container->get('arii_core.date');
	$spooler = $row->get_value("SPOOLER_ID");
	$start = strtotime($row->get_value("START_TIME"));
	$end = $row->get_value("END_TIME");
	$row->set_value("DURATION",$this->Duration( $start, strtotime($end) ) );
        if ($end == 0) {
            $row->set_row_attribute("class","backgroundrunning");
        }
        else {
            if ($row->get_value("ERROR")==0) {
                    $row->set_row_attribute("class","backgroundsuccess");
            }
            else {
                    $row->set_row_attribute("class","backgroundfailure");
            }
        }
	$row->set_value("START_TIME", $date->ShortDate( $date->Date2Local( $row->get_value("START_TIME"), $spooler ) ) );
        $EndDate =  $this->ShortDate( $date->Date2Local( $row->get_value("END_TIME"), $spooler ) ) ;
        if (substr($row->get_value("START_TIME"),0,10)==substr($row->get_value("END_TIME"),0,10))
            $row->set_value("END_TIME", substr($EndDate,11 ) );        
        else
            $row->set_value("END_TIME", $EndDate );        
    }
    
    function ShortDate($date) {
        if (substr($date,0,10)==date('Y-m-d'))
                return substr($date,11);
        return $date;
    }

    
    public function chart_historyAction( )
    {
        $request = Request::createFromGlobals();
        $spooler = $request->query->get( 'spooler' );
        $job = $request->query->get( 'job' );

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');
        $data2 = $dhtmlx->Connector('chart');
        $qry2 = $sql->Select(array('ID','START_TIME','END_TIME','ERROR'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('SPOOLER_ID' => $spooler,
                    'JOB_NAME' => $job))
                .$sql->OrderBy(array('START_TIME desc')); 
        $data2->event->attach("beforeRender",array( $this,  "render_chart" ) );
        $data2->render_sql($qry2,'ID','START_TIME,START,DURATION,COLOR');
    }

    function render_chart($row){
	$start = strtotime($row->get_value("START_TIME"));
	$end = $row->get_value("END_TIME");
        if ($end>0)
            $row->set_value("DURATION",strtotime($end)-$start );
        else
            $row->set_value("DURATION",0 );
        if ($end == '') {
            $row->set_value("COLOR", "orange");
        }
        else {
            if ($row->get_value("ERROR")=='1')
                $row->set_value("COLOR", "red");
            else 
                $row->set_value("COLOR", "#749400");
        }
	$row->set_value("START", $start);
    }

}

