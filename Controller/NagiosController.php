<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NagiosController extends Controller
{
    public function jobsAction($debug=0) {
        $request = Request::createFromGlobals();
        $filter  = str_replace('*','%',$request->query->get( 'filter' ));
        if ($request->query->get( 'debug' )>0) {
            $debug = $request->query->get( 'debug' );
        }
            
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            'STOPPED'    => 1 );
        $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                .$sql->From(array('SCHEDULER_JOBS sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));

        $res = $data->sql->query( $qry );
        $Stopped = array();
        while ($line = $data->sql->get_next($res)) {
             $job = '['.$line['SPOOLER_ID'].']/'.$line['JOB_NAME'];
             $Stopped[$job] = 1;
        }
        
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            'JOB_NAME'    => $filter );
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_NAME','h.ERROR','h.END_TIME','h.CAUSE',
            'oh.JOB_CHAIN','oh.ORDER_ID','osh.STATE', 'oh.STATE as ORDER_STATE','oh.END_TIME as ORDER_END_TIME')) 
               .$sql->From(array('SCHEDULER_HISTORY h'))
               .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY osh',array('h.ID','osh.TASK_ID'))
               .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY oh',array('osh.HISTORY_ID','oh.HISTORY_ID'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array('h.START_TIME desc'));

        $res = $data->sql->query( $qry );
        $Done = array();
        $Errors = array();
        $ToCheck = array();
        $Fatal_orders = array(); // Ordres suspendus
        $Fatal_jobs = array(); // Jobs stoppés
        $nb = 0;
        $date = $this->container->get('arii_core.date');
        $fatal = 0;
        while ($line = $data->sql->get_next($res)) {
            // On prend le dernier statut
            $spooler = $line['SPOOLER_ID'];
            $job     = '['.$spooler.']/'.$line['JOB_NAME'];
            if ((!isset($Done[$job])) and ($line['END_TIME']!='') ) {
//                print $line['SPOOLER_ID']." ".$line['ERROR']." ".$line['JOB_NAME']." ".$line['END_TIME']." END TIME ".$line['ORDER_END_TIME']."<br/>"; 
                $Done[$job] = $line['END_TIME'];
                $type = substr($line['STATE'],0,1);
                if (($line['ERROR']>0) and ($type!='?')) {
                    // si c'est un job ordonné on verifie si l'état est suspendu
                    if ($line['CAUSE']=='order') {
                        if (($line['ORDER_END_TIME']=='') and ($line['STATE']==$line['ORDER_STATE'])) {
                            $Fatal_orders[$job]=$date->Date2Local( $line['END_TIME'],$line['SPOOLER_ID']).'  SUSP.  ['.$line['SPOOLER_ID'].'] '.$line['JOB_NAME'].' -> '.$line['JOB_CHAIN'].','.$line['ORDER_ID'];
                            $fatal++;
                        }
                        if ($line['STATE']=='!') {
                            $Fatal_orders[$job]=$date->Date2Local( $line['END_TIME'],$line['SPOOLER_ID']).'  FATAL  ['.$line['SPOOLER_ID'].'] '.$line['JOB_NAME'].' -> '.$line['JOB_CHAIN'].','.$line['ORDER_ID'];
                            $fatal++;
                        }
                        else {
                            $Fatal_orders[$job]=$date->Date2Local( $line['END_TIME'],$line['SPOOLER_ID']).'  ERROR  ['.$line['SPOOLER_ID'].'] '.$line['JOB_NAME'].' -> '.$line['JOB_CHAIN'].','.$line['ORDER_ID'];                            
                        }
                    }
                    else {
                       if (isset($Stopped[$job])) {
                           $Fatal_jobs[$job] = $date->Date2Local( $line['END_TIME'],$line['SPOOLER_ID']).'  STOP!  ['.$line['SPOOLER_ID'].'] '.$line['JOB_NAME'];
                           $fatal++;
                       }
                       else {
                           $Fatal_jobs[$job] = $date->Date2Local( $line['END_TIME'],$line['SPOOLER_ID']).'  ERROR  ['.$line['SPOOLER_ID'].'] '.$line['JOB_NAME'];
                       }
                    }
                    array_push($Errors,$job);
                }
            }
            $nb++;
        }
        // Aucune information ? Bizarre
        if ($nb==0) {
            print 'UNKNOWN';
            exit(4);
        }
        
        // Aucune erreur ?
        if (count($Errors)==0) {
            print "OK";
            exit(0);
        }
        // Statut erreur ? 
        if ($fatal>0) {
            // CRITICAL
            $res = 'CRITICAL';
            $statut = 2;
        }
        else  {
            // WARNING
            $res = 'WARNING';
            $statut = 1;
        }

        $liste = array();
        $detail = array();
        foreach ($Errors as $job) {
            array_push($liste,$job);  
            if (isset($Fatal_orders[$job])) {
                array_push($detail, $Fatal_orders[$job]);
            }
            if (isset($Fatal_jobs[$job])) {
                array_push($detail, $Fatal_jobs[$job]);
            }
        }
        sort($liste);
        sort($detail);
        if ($debug>0) print "<pre>";
        print $res.'|'.implode(', ',$liste);
        print "\n".implode("\n",$detail);
        if ($debug>0) print "</pre>";
        exit($statut);
    }

}
