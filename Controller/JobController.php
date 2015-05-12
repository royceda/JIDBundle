<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class JobController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../bundles/ariicore/images/wa');          
    }
    
    public function formAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('ID','JOB_NAME','STEPS','CAUSE','ERROR','ERROR_TEXT','EXIT_CODE','END_TIME','PID'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('ID' => $id));
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('form');
        $data->event->attach("beforeRender",array($this,"form_render"));
        $data->render_sql($qry,'ID','ID,FOLDER,NAME,STATUS,JOB_NAME,STEPS,CAUSE,ERROR,ERROR_TEXT,EXIT_CODE,PID');
    }
    
    function form_render ($data){
        $data->set_value('FOLDER',dirname($data->get_value('JOB_NAME'))); 
        $data->set_value('NAME',basename($data->get_value('JOB_NAME'))); 
        if ($data->get_value('END_TIME')=='') {
            $data->set_value('STATUS','RUNNING');
        }
        elseif ($data->get_value('ERROR')>0) {
            $data->set_value('STATUS','FAILURE');            
        }
        else {
            $data->set_value('STATUS','SUCCESS');            
        }
    }

    public function params_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:Jobs:params_toolbar.xml.twig",array(), $response );
    }

    public function paramsAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('PARAMETERS'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('ID' => $id));
        
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

    public function logAction()
    {
            $request = Request::createFromGlobals();
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            $sql = $this->container->get('arii_core.sql');
            $data = $dhtmlx->Connector('data');
                $qry = $sql->Select(array('h.ID','h.SPOOLER_ID','h.LOG','h.END_TIME'))
                .$sql->From(array('SCHEDULER_HISTORY h'));
                $id = intval($request->query->get( 'id' ));
                $qry .= $sql->Where(array('h.ID'=>$id));

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

    public function historyAction($history_max=0,$ordered = 0)
    {
        $request = Request::createFromGlobals();
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_NAME'))
                .$sql->From(array('SCHEDULER_HISTORY h'));
            $id = $request->query->get( 'id' );
            $qry .= $sql->Where(array('h.ID'=>$id));

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

    function render_grid($row){
        $date = $this->container->get('arii_core.date');
	$spooler = $row->get_value("SPOOLER_ID");
	$start = strtotime($row->get_value("START_TIME"));
	$end = $row->get_value("END_TIME");
	$row->set_value("DURATION",$date->Duration( $start, strtotime($end) ) );
        if ($end == 0) {
            $row->set_row_attribute("style","background-color: #ffffcc;");
        }
        else {
            if ($row->get_value("ERROR")==0) {
                    $row->set_row_attribute("style","background-color: #ccebc5;");
            }
            else {
                    $row->set_row_attribute("style","background-color: #fbb4ae;");
            }
        }
	$row->set_value("START_TIME", $date->ShortDate( $date->Date2Local( $row->get_value("START_TIME"), $spooler ) ) );
        $EndDate =  $date->ShortDate( $date->Date2Local( $row->get_value("END_TIME"), $spooler ) ) ;
        if (substr($row->get_value("START_TIME"),0,10)==substr($row->get_value("END_TIME"),0,10))
            $row->set_value("END_TIME", substr($EndDate,11 ) );        
        else
            $row->set_value("END_TIME", $EndDate );        
    }

}
