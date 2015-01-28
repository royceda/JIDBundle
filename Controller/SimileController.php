<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class SimileController extends Controller
{
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
    }

    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Simile:index.html.twig' );
    }

    public function historyAction()
    {
        return $this->render('AriiJIDBundle:Simile:history.html.twig' );
    }

    public function ordersAction()
    {
        return $this->render('AriiJIDBundle:Simile:orders.html.twig' );
    }

    public function plannedAction()
    {
        return $this->render('AriiJIDBundle:Simile:planned.html.twig' );
    }

    public function history_xmlAction($part='')
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');

        header("Content-type: text/xml");
        print "<data>\n";
        
if (($part=='orders') || ($part=='')) {
        $Fields = array (
            'spooler'    => 'sh.SPOOLER_ID',
            'job_chain'  => 'sh.JOB_CHAIN',
            'start_time' => 'sh.START_TIME',
            'end_time'   => 'sh.END_TIME' );

// ordres
$qry = 'SELECT sh.HISTORY_ID, sh.SPOOLER_ID, sh.JOB_CHAIN, sh.ORDER_ID, sh.START_TIME, sh.END_TIME 
 FROM SCHEDULER_ORDER_HISTORY sh
 where '.$sql->Filter($Fields).' order by sh.SPOOLER_ID,sh.HISTORY_ID
 limit 0,1000';

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            print "<event ";
                if ($line['END_TIME']=='') {
                print ' start="'.substr(date("r", strtotime($line['START_TIME'])),5).'"';
                print ' end="'.substr(date("r", time()),5).'"';
                print ' title="'.$line['ORDER_ID'].'"';
                print ' isDuration="false"';                    
                print ' color="yellow"';
                $icon = 'order';
                print ' icon="'.$this->images.'/'.$icon.'.png"';                
                print ">\n";
            }
            else {
                print ' start="'.substr(date("r", strtotime($line['START_TIME'])),5).'"';
                print ' end="'.substr(date("r", strtotime($line['END_TIME'])),5).'"';
                print ' title="'.$line['ORDER_ID'].'-&gt;'.$line['JOB_CHAIN'].'"';
                print ' isDuration="false"';                    
                print ' color="purple"';
                $icon = 'order';
                print ' icon="'.$this->images.'/'.$icon.'.png"';                
                print ">\n";
            }
            print "&lt;table&gt;";
            print "&lt;tr&gt;&lt;td&gt;Spooler:&lt;/td&gt;&lt;td&gt;".$line['SPOOLER_ID'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;tr&gt;&lt;td&gt;Order:&lt;/td&gt;&lt;td&gt;".$line['ORDER_ID'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;tr&gt;&lt;td&gt;Job chain:&lt;/td&gt;&lt;td&gt;".$line['JOB_CHAIN'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;/table&gt;";
            print "</event>\n";
        }
}

if (($part=='history') || ($part=='')) {

         $Fields = array (
            'spooler'    => 'sh.SPOOLER_ID',
            'job_chain'  => 'sh.JOB_CHAIN',
            'start_time' => 'sh.START_TIME',
            'end_time'   => 'sh.END_TIME',
            'error'      => 'sh.ERROR' );

 $qry = 'SELECT sh.ID, sh.SPOOLER_ID, sh.JOB_NAME, sh.START_TIME, sh.END_TIME, sh.ERROR, sh.EXIT_CODE, sh.CAUSE, sh.PID, sj.STOPPED, sj.NEXT_START_TIME, sh.CAUSE
 FROM SCHEDULER_HISTORY sh
 left join SCHEDULER_JOBS sj 
 on sh.SPOOLER_ID=sj.SPOOLER_ID and sh.JOB_NAME=sj.PATH
 where '.$sql->Filter($Fields).' order by sh.SPOOLER_ID,sh.CAUSE,sh.ID
 limit 0,1000';

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            print "<event ";
                if ($line['END_TIME']=='') {
                print ' start="'.substr(date("r", strtotime($line['START_TIME'])),5).'"';
                print ' end="'.substr(date("r", time()),5).'"';
                print ' title="'.$line['JOB_NAME'].'"';
                print ' isDuration="true"';                    
                $light = '';
                if ($line['CAUSE']=='order') {
                    $icon = 'ordered_job';
                    // print ' icon="'.$this->images.'/'.$icon.'.png"';                
                }
                $color = $light.'orange';
                print ' color="'.$color.'"';
                print ">\n";
            }
            else {
                print ' start="'.substr(date("r", strtotime($line['START_TIME'])),5).'"';
                print ' end="'.substr(date("r", strtotime($line['END_TIME'])),5).'"';
                print ' title="'.$line['JOB_NAME'].'"';
                print ' isDuration="true"';                    
                $light = '';
                if ($line['CAUSE']=='order') {
                    $icon = 'ordered_job';
                    // print ' icon="'.$this->images.'/'.$icon.'.png"';                
                    $light = 'light';
                }
                $color = $light.'green';
                if ($line['ERROR']==1)
                    $color=$light.'red';
                print ' color="'.$color.'"';
                print ">\n";
            }
            print "&lt;table&gt;";
            print "&lt;tr&gt;&lt;td&gt;Spooler:&lt;/td&gt;&lt;td&gt;".$line['SPOOLER_ID'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;tr&gt;&lt;td&gt;Job name:&lt;/td&gt;&lt;td&gt;".$line['JOB_NAME'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;tr&gt;&lt;td&gt;Exit code:&lt;/td&gt;&lt;td&gt;".$line['EXIT_CODE'].'&lt;/td&gt;&lt;/tr&gt;';
            print "&lt;/table&gt;";
            print "</event>\n";
        }
}
if (($part=='planned') || ($part=='')) {
// evenements
        $Fields = array (
            'spooler'    => 'scheduler',
            'job_name'   => 'JOB',
            'start_time' => 'SCHEDULE_PLANNED',
            'end_time'   => 'SCHEDULE_PLANNED',
            'status'     => 'STATUS' );

        $qry =' select  
 ID,SCHEDULER_ID,SCHEDULER_HISTORY_ID,SCHEDULER_ORDER_HISTORY_ID,JOB,JOB_CHAIN,ORDER_ID,SCHEDULE_PLANNED,SCHEDULE_EXECUTED,PERIOD_BEGIN,PERIOD_END,IS_REPEAT,START_START,STATUS,RESULT 
 from DAYS_SCHEDULE 
 where '.$sql->Filter($Fields);

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $icon = 'failure';
            if ($line['SCHEDULE_EXECUTED']=='') {
                print "<event ";
                print ' start="'.substr(date("r", strtotime($line['SCHEDULE_PLANNED'])),5).'"';
                print ' title="'.$line['JOB'].'"';
                print ' isDuration="false"';
                print ' icon="'.$this->images.'/'.$icon.'.png"';                
                $color = 'black';
                print ' color="'.$color.'"';
                print ">\n";
                print "SPOOLER: ".$line['scheduler'];
                print "</event>\n";                
            }
            else {
                if ($line['SCHEDULE_PLANNED']>$line['SCHEDULE_EXECUTED']) {
                    $icon = 'late';
                    $color = '#fb8072';
                }
                else {
                    $icon = 'success';
                    $color = '#bebada';
                }
                print "<event ";
                print ' start="'.substr(date("r", strtotime($line['SCHEDULE_PLANNED'])),5).'"';
                print ' end="'.substr(date("r", strtotime($line['SCHEDULE_EXECUTED'])),5).'"';
                print ' title="'.$line['JOB'].'"';
                print ' isDuration="false"';
                print ' icon="'.$this->images.'/'.$icon.'.png"';                
                $color = 'black';
                print ' color="'.$color.'"';
                print ">\n";
                print "SPOOLER: ".$line['scheduler'];
                print "</event>\n";                
            }
        }
}        

if (($part=='events') || ($part=='')) {
// evenements
        $Fields = array (
            'spooler'    => 'sh.ID',
            'job_chain'  => 'sh.JOB_CHAIN',
            'job_name' => 'sh.JOB_NAME',
            'start_time'   => 'sh.CREATED',
            'end_time'   => 'sh.EXPIRES' );

 $qry = 'SELECT sh.ID, sh.SPOOLER_ID, sh.JOB_NAME, sh.CREATED, sh.EXPIRES, sh.EVENT_CLASS, sh.EVENT_ID, sh.JOB_CHAIN, sh.ORDER_ID, sh.EXIT_CODE 
 FROM SCHEDULER_EVENTS sh
 where '.$sql->Filter($Fields).' order by sh.SPOOLER_ID,sh.ID
 limit 0,1000';

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            print "<event ";
            print ' start="'.substr(date("r", strtotime($line['CREATED'])),5).'"';
            print ' end="'.substr(date("r", strtotime($line['EXPIRES'])),5).'"';
            print ' title="'.$line['EVENT_CLASS'].'#'.$line['EVENT_ID'].'"';
            print ' isDuration="false"';
            $icon= 'event';
            print ' icon="'.$this->images.'/'.$icon.'.png"';                
            $color = 'black';
            print ' color="'.$color.'"';
            print ">\n";
            print "SPOOLER: ".$line['SPOOLER_ID'];
            print "</event>\n";
        }
}        
        print "</data>\n";
        exit();
    }

}
