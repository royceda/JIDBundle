<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class TimelineController extends Controller
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

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Default:toolbar_timeline.xml.twig', array(), $response);
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
        $Timeline['step'] = 60;
    
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['js_date'] = $year.','.($month - 1).','.$day;
        
        $Timeline['start'] = 0;
        $refresh = $session->GetRefresh();
        
        // Liste des spoolers pour cette plage
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME' );

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
        return $this->render('AriiJIDBundle:Default:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    function color_rows($row){
        $row->set_value("textColor", 'yellow');
        $row->set_value("JOB_NAME", '<img src="'.$this->images.'/running.png"/>');
        if ($row->get_value('END_TIME')=='') {
            $row->set_value("color", 'orange');
            $row->set_value("END_TIME", $this->ref_date );
        }
        elseif ($row->get_value('ERROR')>0) {
            $row->set_value("color", 'red');
        }
        else {
            $row->set_value("color", '#749400');            
        }
    }    

    public function historyAction($history=0,$ordered=false)
    {
        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history = $request->get('history');
        }
        if ($request->get('ordered')=='true') {
            $ordered = true;
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{!(spooler)}' => 'JOB_NAME',
            '{spooler}'    => 'SPOOLER_ID',
            '{job_name}'   => 'JOB_NAME',
            '{error}'      => 'ERROR',
            '{start_time}' => 'START_TIME' );
        if (!$ordered) {
            $Fields['{standalone}'] = 'CAUSE';
        }

        // le passé
        $qry = $sql->Select(array('ID','SPOOLER_ID','JOB_NAME','START_TIME','END_TIME','ERROR','EXIT_CODE','CAUSE','PID'))  
                  .$sql->From(array('SCHEDULER_HISTORY'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('SPOOLER_ID','JOB_NAME','START_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($Nb[$id]>$history) continue;
            $xml .= '<event id="'.$line['ID'].'">';
            $xml .= '<start_date>'.$line['START_TIME'].'</start_date>';
            $textColor='yellow';
            if ($line['END_TIME']!='') {
                $xml .= '<end_date>'.$line['END_TIME'].'</end_date>';
                if ($line['ERROR']==0) {
                   if ($line['CAUSE']=='order') {
                       $color= 'lightgreen';
                   }
                   else {
                        $color= '#749400';
                   }
                }
                else {
                   if ($line['CAUSE']=='order') {
                       $color= 'lightred';
                   }
                   else {
                        $color='red'; 
                   }
                }
                $xml .= '<text>'.$line['JOB_NAME'].' (exit '.$line['EXIT_CODE'].')</text>';
            }
            else {
                $xml .= '<end_date>'.gmdate("Y-M-d H:i:s").'</end_date>';
                $color = 'orange';
                $xml .= '<text>'.$line['JOB_NAME'].' (pid '.$line['PID'].')</text>';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
            $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }
    
    public function history_ordersAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{!(spooler)}'    => 'JOB_NAME',
            '{job_chain}'   => 'JOB_CHAIN',
            '{error}'      => 'ERROR',
            '{start_time}' => 'START_TIME' );

        $qry = $sql->Select(array('HISTORY_ID','SPOOLER_ID','ID','JOB_CHAIN','START_TIME','END_TIME','STATE'))  
                  .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('SPOOLER_ID','START_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ID'];
/*
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($Nb[$id]>$history) continue;
*/
            $xml .= '<event id="'.$line['HISTORY_ID'].'">';
            $xml .= '<start_date>'.$line['START_TIME'].'</start_date>';
            $textColor='yellow';
            if ($line['END_TIME']!='') {
                $xml .= '<end_date>'.$line['END_TIME'].'</end_date>';
                if (substr($line['STATE'],0,1)=='!') {
                   $color= 'red'; 
                }
                else {
                    $textColor='blue';
                    $color='lightyellow'; 
                }
                $xml .= '<text>'.$line['JOB_CHAIN'].','.$line['ID'].'</text>';
            }
            else {
                $xml .= '<end_date>'.gmdate("Y-M-d H:i:s").'</end_date>';
                $color = 'orange';
                $xml .= '<text>'.$line['JOB_NAME'].' (pid '.$line['PID'].')</text>';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
            $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

    public function jobsAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        
        // le futur
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_name}'   => 'PATH' );
        
        $qry = $sql->Select(array('SPOOLER_ID','PATH','NEXT_START_TIME'))  
                  .$sql->From(array('SCHEDULER_JOBS'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('SPOOLER_ID','PATH','NEXT_START_TIME'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            if ($line['NEXT_START_TIME']=='') continue;
            $id = $line['SPOOLER_ID'].'/'.$line['PATH'];
            $xml .= '<event id="J#'.$id.'">';
            $xml .= '<start_date>'.$line['NEXT_START_TIME'].'</start_date>';
            $xml .= '<end_date>'.$line['NEXT_START_TIME'].'</end_date>';
            $xml .= '<text>'.$line['PATH'].'</text>';
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>lightblue</color>';
            $xml .= '<textColor>white</textColor>';
            $xml .= '</event>';
        }

        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

    public function ordersAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $tools =  $this->container->get('arii_core.tools');
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        
        // le futur
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_name}'   => 'PATH' );
        
        $qry = $sql->Select(array('SPOOLER_ID','ORDER_XML','JOB_CHAIN','ID','STATE'))  
                  .$sql->From(array('SCHEDULER_ORDERS'))
                  .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            if ($line['ORDER_XML']=='') continue;
            $order_xml = $tools->xml2array($line['ORDER_XML']);
            if (!isset($order_xml['order_attr']['at'])) continue;
            $at = str_replace(array('T','Z'),array(' ',' '),substr($order_xml['order_attr']['at'],0,16));
            if ($at=='') continue;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ID'];
            $xml .= '<event id="O#'.$id.'">';
            $xml .= '<start_date>'.$at.'</start_date>';
            $xml .= '<end_date>'.$at.'</end_date>';
            $xml .= '<text>'.$line['JOB_CHAIN'].','.$line['ID'].' ('.$line['STATE'].')</text>';
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>blue</color>';
            $xml .= '<textColor>white</textColor>';
            $xml .= '</event>';
        }

        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

    public function ordered_jobsAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{!(spooler)}'    => 'JOB_NAME',
            '{job_name}'   => 'JOB_NAME',
            '{error}'      => 'ERROR',
            '{start_time}' => 'START_TIME',
            'CAUSE' => 'order' );

        // le passé
        $qry = $sql->Select(array('ID','SPOOLER_ID','JOB_NAME','START_TIME','END_TIME','ERROR','EXIT_CODE','CAUSE','PID'))  
                  .$sql->From(array('SCHEDULER_HISTORY'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('SPOOLER_ID','JOB_NAME','START_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($Nb[$id]>$history) continue;
            $xml .= '<event id="'.$line['ID'].'">';
            $xml .= '<start_date>'.$line['START_TIME'].'</start_date>';
            $textColor='yellow';
            if ($line['END_TIME']!='') {
                $xml .= '<end_date>'.$line['END_TIME'].'</end_date>';
                if ($line['ERROR']==0) {
                   $color= '#749400'; 
                }
                else {
                    $color='red'; 
                }
                $xml .= '<text>'.$line['JOB_NAME'].' (exit '.$line['EXIT_CODE'].')</text>';
            }
            else {
                $xml .= '<end_date>'.gmdate("Y-M-d H:i:s").'</end_date>';
                $color = 'orange';
                $xml .= '<text>'.$line['JOB_NAME'].' (pid '.$line['PID'].')</text>';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
            $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

    public function plannedAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{job_name}'   => 'JOB',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{status}'     => 'STATUS' );

        $qry = $sql->Select(array( 'ID','SCHEDULER_ID as SPOOLER_ID','SCHEDULER_HISTORY_ID','SCHEDULER_ORDER_HISTORY_ID','JOB as JOB_NAME','JOB_CHAIN','ORDER_ID','SCHEDULE_PLANNED','SCHEDULE_EXECUTED','PERIOD_BEGIN','PERIOD_END','IS_REPEAT','START_START','STATUS','RESULT'))
                .$sql->From(array('DAYS_SCHEDULE'))
                .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        $now = gmdate("Y-m-d H:i:s");
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($Nb[$id]>$history) continue;
            $xml .= '<event id="'.$line['ID'].'">';
            $xml .= '<start_date>'.$line['SCHEDULE_PLANNED'].'</start_date>';
            if ($line['JOB_NAME']=='') {
                $xml .= '<text>'.$line['JOB_CHAIN'].','.$line['ORDER_ID'].'</text>';
            }
            else {
                $xml .= '<text>'.$line['JOB_NAME'].'</text>';
            }
            $textColor='yellow';
            if ($line['SCHEDULE_EXECUTED']!='') {
                $xml .= '<end_date>'.$line['SCHEDULE_EXECUTED'].'</end_date>';
                $color= '#749400'; 
            }
            else {
                $xml .= '<end_date>'.$now.'</end_date>';
                if ($line['SCHEDULE_PLANNED']>$now) {
                    $color='purple';
                }
                else {
                    $color = 'red';
                }
                $xml .= '<text>'.$line['JOB_NAME'].'</text>';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
            $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

    public function eventsAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{start_time}' => 'CREATED',
            '{job_name}'   => 'JOB_NAME',
            '{job_chain}'   => 'JOB_CHAIN' );

        $qry = $sql->Select(array( 'ID','SPOOLER_ID','REMOTE_SCHEDULER_HOST','REMOTE_SCHEDULER_PORT','JOB_NAME','JOB_CHAIN','ORDER_ID','EVENT_CLASS','EVENT_ID','EXIT_CODE','PARAMETERS','CREATED','EXPIRES'))
                .$sql->From(array('SCHEDULER_EVENTS'))
                .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        $now = gmdate("Y-m-d H:i:s");
        while ($line = $data->sql->get_next($res)) {
            $xml .= '<event id="'.$line['ID'].'">';
            $xml .= '<start_date>'.$line['CREATED'].'</start_date>';
            $xml .= '<end_date>'.$line['CREATED'].'</end_date>';
            if ($line['JOB_CHAIN']!='') {
                $xml .= '<text>'.$line['JOB_NAME'].' ('.$line['JOB_CHAIN'].','.$line['ORDER_ID'].')</text>';
            }
            else {
                $xml .= '<text>'.$line['JOB_NAME'].'</text>';
            }
            $textColor='yellow';
            if ($line['EXIT_CODE']==0) {
                $color= '#749400'; 
            }
            else {
                $color = 'red';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
            $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

}
