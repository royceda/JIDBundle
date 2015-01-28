<?php
// src/Arii/JIDBundle/Controller/TransfersController.php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class DBGraphController extends Controller
{
    private $ref_date;
 
    public function pie_messagesAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'sh.SPOOLER_ID',
            'start_time' => 'sh.START_TIME',
            'end_time'   => 'sh.END_TIME',
            'error'      => 'sh.ERROR' );

$qry = 'SELECT count(MESSAGE_ID) as NB, SEVERITY group by SEVERITY
 FROM SCHEDULER_MESSAGES 
 WHERE '.$sql->Filter($Fields);

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $severity = $line['SEVERITY'];
            $Count[$severity] = $line['NB'];
        }
        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        if (isset($Count['ERROR']))
            print '<item id="1"><STATUS>ERROR</STATUS><JOBS>'.$Count['ERRROR'].'</JOBS><COLOR>red</COLOR></item>';
        if (isset($Count['WARN']))
            print '<item id="1"><STATUS>ERROR</STATUS><JOBS>'.$Count['ERRROR'].'</JOBS><COLOR>orange</COLOR></item>';
        print "</data>";
        exit();
    }

    public function radar_historyAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            '{start_time}'  => 'START_TIME');

$qry = $sql->Select(array('SPOOLER_ID','START_TIME','END_TIME','ERROR'))
        .$sql->From(array('SCHEDULER_HISTORY sh1'))
        .$sql->Where($Fields);
/*        
 INNER JOIN (
 SELECT max( ID ) AS ID
 FROM SCHEDULER_HISTORY as sh
 where not(isnull(sh.END_TIME)) and '.$sql->Filter($Fields).' GROUP BY SPOOLER_ID,JOB_NAME ) sh2 ON sh1.ID = sh2.ID';
*/
        $res = $data->sql->query( $qry );
        # Initialisation
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $machine = $line['SPOOLER_ID'];
            $Machine[$machine]=1;
            if ($line['ERROR']==0) {
                if (isset($HS[$machine]))
                    $HS[$machine]++;
                else $HS[$machine]=1;
            }
            else {
                if (isset($HF[$machine]))
                    $HF[$machine]++;
                else $HF[$machine]=1;
            }
        }
        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        foreach($Machine as $i=>$v) {
            if (!isset($HS[$i])) $HS[$i]=1;
            if (!isset($HF[$i])) $HF[$i]=1;
            print '<item id="'.$i.'"><MACHINE>'.$i.'</MACHINE><SUCCESS>0</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE></item>';
        }
        print "</data>";
        exit();
    }

    public function radar_ordersAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
       $Fields = array (
            'spooler'    => 'SPOOLER_ID',
            'job_chain'  => 'JOB_CHAIN',
            'start_time' => 'START_TIME',
            'end_time'   => 'END_TIME',
            'error'      => 'ERROR' );

$qry = 'SELECT soh.START_TIME,soh.END_TIME,sosh.ERROR
 FROM SCHEDULER_ORDER_HISTORY soh 
 INNER JOIN (
 SELECT max( HISTORY_ID ) AS HISTORY_ID
 FROM SCHEDULER_ORDER_HISTORY
 WHERE '.$sql->Filter($Fields).' 
 GROUP BY SPOOLER_ID,JOB_CHAIN,ORDER_ID
 ) soh2 ON soh.HISTORY_ID = soh2.HISTORY_ID 
 left join SCHEDULER_ORDER_STEP_HISTORY sosh 
 on soh.HISTORY_ID = sosh.HISTORY_ID';

        $res = $data->sql->query( $qry );
        # Initialisation
        for($i=0;$i<24;$i++) {
            $HS[$i]=0;
            $HF[$i]=0;
        }
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $hour = (int) substr($line['END_TIME'],11,2);
            if ($line['ERROR']==0) {
                $HS[$hour]++;
            }
            else {
                $HF[$hour]++;
            }
        }
        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        for($i=0;$i<24;$i++) {
            print '<item id="'.$i.'"><HOUR>'.$i.'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE></item>';
        }
        print "</data>";
        exit();
    }

    public function radar_plannedAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'scheduler',
            'job_name'   => 'JOB',
            'start_time' => 'SCHEDULE_PLANNED',
            'end_time'   => 'SCHEDULE_PLANNED',
            'status'     => 'STATUS' );

        $qry =' select  
 ID, SCHEDULER_ID,SCHEDULER_HISTORY_ID,SCHEDULER_ORDER_HISTORY_ID,JOB,JOB_CHAIN,ORDER_ID,SCHEDULE_PLANNED,SCHEDULE_EXECUTED,PERIOD_BEGIN,PERIOD_END,IS_REPEAT,START_START,STATUS,RESULT 
 from DAYS_SCHEDULE 
 where '.$sql->Filter($Fields);

        $res = $data->sql->query( $qry );
        # Initialisation
        for($i=0;$i<24;$i++) {
            $HS[$i]=0;
            $HF[$i]=0;
        }
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $hour = (int) substr($line['SCHEDULE_PLANNED'],11,2);
            if ($line['STATUS']==0) {
                $HS[$hour]++;
            }
            else {
                $HF[$hour]++;
            }
        }
        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        for($i=0;$i<24;$i++) {
            print '<item id="'.$i.'"><HOUR>'.$i.'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE></item>';
        }
        print "</data>";
        exit();
    }


    public function last_historyAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'SPOOLER_ID',
            'start_time' => 'START_TIME',
            'end_time'   => 'END_TIME' );

$qry = 'SELECT count(ID) as NB,left(START_TIME,13) as START_TIME,left(END_TIME,13) as END_TIME,ERROR
 FROM SCHEDULER_HISTORY
 where '.$sql->History($Fields).'
 GROUP BY left(START_TIME,13),left(END_TIME,13),ERROR';

$res = $data->sql->query( $qry );
        // Par jour 
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $hour = $line['START_TIME'];
            $nb=$line['NB'];
            $Hours[$hour]=1;
            if ($line['END_TIME']=='') {
                if (isset($HR[$hour]))
                    $HR[$hour]+=$nb;
                else 
                    $HR[$hour]=$nb;
            }
            else {
                if ($line['ERROR']==0) {
                    if (isset($HS[$hour]))
                        $HS[$hour]+=$nb;
                    else 
                        $HS[$hour]=$nb;                        
                }
                else {
                    if (isset($HF[$hour]))
                        $HF[$hour]+=$nb;
                    else 
                        $HF[$hour]=$nb;                        
                }
            }
        }

        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        foreach($Hours as $i=>$v) {
            if (!isset($HS[$i])) $HS[$i]=0;
            if (!isset($HF[$i])) $HF[$i]=0;
            if (!isset($HR[$i])) $HR[$i]=0;
                
            print '<item id="'.$i.'"><HOUR>'.(int) substr($i,11,2).'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE><RUNNING>'.$HR[$i].'</RUNNING></item>';
        }
        print "</data>";
        exit();
    }

    public function bar3_historyAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            'spooler'    => 'sh1.SPOOLER_ID' );

$qry = 'SELECT sh1.SPOOLER_ID,count(sh1.ID) as Nb
 FROM SCHEDULER_HISTORY sh1
 where not(isnull(sh1.END_TIME)) and sh1.ERROR>0 and '.$sql->Filter($Fields).' 
 group by sh1.SPOOLER_ID';

        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $machine = $line['SPOOLER_ID'];
            $Nb[$machine]= $line['Nb'];
        }
        header('Content-type: text/xml');
        print "<?xml version='1.0' encoding='utf-8' ?>";
        print "<data>";
        arsort($Nb);
        foreach($Nb as $i=>$v) {
            print '<item id="'.$i.'"><MACHINE>'.$i.'</MACHINE><NB>'.$Nb[$i].'</NB></item>';
        }
        print "</data>";
        exit();
    }

    public function timeline_historyAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('scheduler');

       $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->get('ref_date');

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

}
