<?php
// src/Arii/JIDBundle/Service/AriiHistory.php
/*
 * Recupere les données et fournit un tableau pour les composants DHTMLx
 */ 
namespace Arii\JIDBundle\Service;

class AriiHistory
{
    protected $db;
    protected $sql;
    protected $date;
    protected $tools;
    
    public function __construct (  
            \Arii\CoreBundle\Service\AriiDHTMLX $db, 
            \Arii\CoreBundle\Service\AriiSQL $sql,
            \Arii\CoreBundle\Service\AriiDate $date,
            \Arii\CoreBundle\Service\AriiTools $tools ) {
        $this->db = $db;
        $this->sql = $sql;
        $this->date = $date;
        $this->tools = $tools;
    }

/*********************************************************************
 * Informations de connexions
 *********************************************************************/
   public function Jobs($history_max=0,$ordered = 0,$only_warning= 1,$next=1) {   
        $data = $this->db->Connector('data');
        $sql = $this->sql;
        $date = $this->date;
        
        $Fields = array (
        '{spooler}'    => 'sh.SPOOLER_ID',
        '{job_name}'   => 'sh.PATH' );

        $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                .$sql->From(array('SCHEDULER_JOBS sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
             $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
             if ($line['STOPPED']=='1' ) {
                 $Stopped[$jn] = true;
             }
             if ($line['NEXT_START_TIME']!='' ) {
                 $Next_start[$jn] = $line['NEXT_START_TIME'];
             }
        }

        /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME' );
        if (!$ordered) {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc'));  

        $res = $data->sql->query( $qry );
        $nb=0;
        $H = array();
        $Jobs = array();
        while ($line = $data->sql->get_next($res)) {
            $nb++;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($H[$id])) {
                if ($line['END_TIME']!='')
                    $H[$id]++;
            }
            else {
                $H[$id]=0;
            }
            if ($H[$id]>$history_max) {
                continue;
            }
            
            if (isset($Stopped[$id]) and ($Stopped[$id]==1)) {
                if ($line['END_TIME']=='')
                    $status = 'STOPPING';
                else
                    $status = 'STOPPED';
            }
            elseif ($line['END_TIME']=='') {
                $status = 'RUNNING';
            } // cas des historique
            elseif ($line['ERROR']>0) {
                $status = 'FAILURE';
            }
            else {
                $status = 'SUCCESS';
            }
            
            if (($only_warning) and ($status == 'SUCCESS')) continue;
            
            $n = $H[$id];     
            $Jobs[$id]['spooler'] = $line['SPOOLER_ID'];
            $Jobs[$id]['folder'] = dirname($line['JOB_NAME']);
            $Jobs[$id]['name'] = basename($line['JOB_NAME']);
            $Jobs[$id]['runs'][$n]['dbid'] = $line['ID'];
            $Jobs[$id]['runs'][$n]['status'] = $status;
            $Jobs[$id]['runs'][$n]['exit'] = $line['EXIT_CODE'];  
            $Jobs[$id]['runs'][$n]['pid'] = $line['PID'];  
            $Jobs[$id]['runs'][$n]['cause'] = $line['CAUSE']; 
            if (isset($Stopped[$id]))
                $Jobs[$id]['stopped'] = true; 
            if (isset($Next_start[$id]))
                $Jobs[$id]['next_start'] = $Next_start[$id]; 
            
            if ($status=='RUNNING') {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
                $Jobs[$id]['runs'][$n]['end'] = ''; 
            }
            else {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],$line['END_TIME'],'', $line['SPOOLER_ID'], false  );                                     
                $Jobs[$id]['runs'][$n]['end'] = $end; 
            }
            $Jobs[$id]['runs'][$n]['start'] = $start; 
            $Jobs[$id]['runs'][$n]['next'] = $next; 
            $Jobs[$id]['runs'][$n]['duration'] = $duration; 
        }  
        return $Jobs;
   }

   public function Orders($history=0,$nested=false,$only_warning=true,$sort='last') {   
        $data = $this->db->Connector('data');
        $sql = $this->sql;
        $date = $this->date;
        $tools = $this->tools;

        $Orders = array();
        $Chains = array();
        $Nodes = array();
        
        // On regarde les chaines stoppés
        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            'STOPPED'  => 1 );
             
        $qry = $sql->Select( array('SPOOLER_ID','PATH as JOB_CHAIN') )
                .$sql->From( array('SCHEDULER_JOB_CHAINS') )
                .$sql->Where( $Fields );

        $res = $data->sql->query( $qry );
        $nb = 0;
        $StopChain = array();
        while ($line = $data->sql->get_next($res)) {
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $StopChain[$cn]=1;
        }

        // On regarde les nodes
        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            'ACTION'  => '(!null)' );
        $qry = $sql->Select( array('SPOOLER_ID','JOB_CHAIN','ORDER_STATE','ACTION') )
                .$sql->From( array('SCHEDULER_JOB_CHAIN_NODES') )
                .$sql->Where( $Fields );

        $res = $data->sql->query( $qry );
        $nb = 0;
        $StopNode = $SkipNode = array();
        while ($line = $data->sql->get_next($res)) {
            $sn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ORDER_STATE'];
            if ($line['ACTION'] == 'next_state') $SkipNode[$sn]=1;
            if ($line['ACTION'] == 'stop') $StopNode[$sn]=1;
        }

        // Historique des ordres
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'   => 'soh.JOB_CHAIN',
            '{start_time}' => 'soh.START_TIME' );
        
        switch ($sort) {
            case 'spooler':
                $Sort = array('soh.spooler','soh.JOB_CHAIN','soh.HISTORY_ID desc','sosh.STEP desc');
                break;
            case 'chain':
                $Sort = array('soh.JOB_CHAIN','soh.spooler','soh.HISTORY_ID desc','sosh.STEP desc');
                break;
            default:
                $Sort = array('soh.HISTORY_ID desc','sosh.STEP desc');
                break;
        }
        
        $qry = $sql->Select(array('soh.HISTORY_ID','soh.TITLE','soh.START_TIME','soh.END_TIME','soh.SPOOLER_ID','soh.ORDER_ID','soh.JOB_CHAIN','soh.STATE','soh.STATE_TEXT','sosh.ERROR'))  
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->Where($Fields)
                .$sql->OrderBy($Sort);

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            if (!$nested) {
                if (substr($line['ORDER_ID'],0,1)=='.') continue; 
            }

            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $id = $cn.','.$line['ORDER_ID'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                $Nb[$id]=0;
            }            
            if ($Nb[$id]>$history) continue;
                        
            $Orders[$id]['DBID'] = $line['HISTORY_ID']; 
            $Orders[$id] = $line;

            if (isset($StopChain[$cn]))
                $Orders[$id]['CHAIN_STOPPED'] = true;

            $sn = $cn.'/'.$line['STATE'];
            if (isset($StopNode[$sn])) {
                $Orders[$id]['NODE_STOPPED'] = true;
            }
            if (isset($SkipNode[$sn]))
                $Orders[$id]['NODE_SKIPPED'] = true;
            
            // Complement
            $Orders[$id]['FOLDER'] = dirname($line['JOB_CHAIN']);
            $Orders[$id]['NAME'] = basename($line['JOB_CHAIN']);
            $Orders[$id]['NEXT_TIME'] = '';

            if ($line['END_TIME']=='') {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'], gmdate("Y-M-d H:i:s"), '', $line['SPOOLER_ID'], true  );
                $Orders[$id]['END_TIME'] = '';
            }
            else { 
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'], $line['END_TIME'], '', $line['SPOOLER_ID'], true  );
                $Orders[$id]['END_TIME'] = $end;
            }
            $Orders[$id]['START_TIME'] = $start;
            $Orders[$id]['DURATION'] = $duration;
                    
        }

        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            '{order_id}'  => 'ID',
/*          'created_time' => 'CREATED_TIME',
*/          '{start_time}' => 'MOD_TIME'
                );
        
        $qry = $sql->Select( array('SPOOLER_ID','JOB_CHAIN','ID as ORDER_ID','PRIORITY','STATE as ORDER_STATE','STATE_TEXT','TITLE','CREATED_TIME','MOD_TIME','ORDERING','INITIAL_STATE','ORDER_XML' ) )
                .$sql->From( array('SCHEDULER_ORDERS') )
                .$sql->Where( $Fields )
                .$sql->OrderBy( array('ORDERING desc') );
        //when we want to store the planned orders, we also need to store the job chains which the planned orders belong.
        $res = $data->sql->query( $qry );
        $nb = 0;
        while ($line = $data->sql->get_next($res)) {
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $on = $cn.','.$line['ORDER_ID'];
            if (!isset($Orders[$on])) continue;
            
            if ($line['ORDER_XML']!=null)
            {
                if (gettype($line['ORDER_XML'])=='object') {
                    $order_xml = $tools->xml2array($line['ORDER_XML']->load());
                }
                else {
                    $order_xml = $tools->xml2array($line['ORDER_XML']);
                }
                $setback = 0; $setback_time = '';
                if (isset($order_xml['order_attr']['suspended']) && $order_xml['order_attr']['suspended'] == "yes")
                {
                    $Orders[$on]['SUSPENDED'] = true;
                }
                elseif (isset($order_xml['order_attr']['setback_count']))
                {
                    $Orders[$on]['SETBACK'] = true;
                    $Orders[$on]['SETBACK_COUNT'] = $order_xml['order_attr']['setback_count'];
                    if (isset($order_xml['order_attr']['setback']))
                        $Orders[$on]['SETBACK_TIME'] = $order_xml['order_attr']['setback'];
                    else 
                        $Orders[$on]['SETBACK_TIME'] = '';
                }
 
                if (isset($order_xml['order_attr']['at'])) {
                    $at = $date->Date2Local($order_xml['order_attr']['at'],$line['SPOOLER_ID']);
                    $Orders[$on]['NEXT_TIME'] = $date->Date2Local($order_xml['order_attr']['at'],$line['SPOOLER_ID'],true);
                }
                elseif (isset($order_xml['order_attr']['start_time'])) {
                    $Orders[$on]['NEXT_TIME'] = $date->Date2Local($order_xml['order_attr']['start_time'],$line['SPOOLER_ID'],true);
                }
                else {
                    $at = '';
                }
                $hid = 0;
                if (isset($order_xml['order_attr']['history_id'])) {
                    $hid = $order_xml['order_attr']['history_id'];
                }
            }
            
        }

        $New = array();
        $Keys = array_keys($Orders);
        // sort($Keys);

        foreach ( $Keys as $on) {            
            $line = $Orders[$on];
                    // Calcul du statut
            if (isset($line['SUSPENDED'])) {
                $status = 'SUSPENDED';     
            }
            elseif (isset($line['CHAIN_STOPPED'])) {
                $status = 'CHAIN STOP.';     
            }
            elseif (isset($line['NODE_STOPPED'])) {
                $status = 'NODE STOP.';     
            }
            elseif (isset($line['NODE_SKIPPED'])) {
                $status = 'NODE SKIP.';     
            }
            elseif (isset($line['SETBACK'])) {
                $status = 'SETBACK';     
            }
            elseif ($line['END_TIME']=='') {
                $status = 'RUNNING';     
            }
            elseif (substr($line['END_TIME'],0,1)=='!') {
                $status = 'FATAL';
            }
            elseif ($line['ERROR']==0) {               
                $status = 'SUCCESS';     
            }
            else {
                $status = 'FAILURE';  
            }
            if (($only_warning)and ($status == 'SUCCESS')) continue;

            $Orders[$on]['STATUS'] = $status;   
            if  ($line['NEXT_TIME']==$line['START_TIME'])
                $Orders[$on]['NEXT_TIME']='';                
            
            $New[$on] = $Orders[$on];
        }

        return $New;
   }


}
