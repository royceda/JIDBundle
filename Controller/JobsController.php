<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class JobsController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../bundles/ariicore/images/wa');          
    }

    // Index des traitements
    // Si GPL -> Suivi
    // Si PRO -> Liste 
    public function indexAction()
    {
      $arii_pro = $this->container->getParameter('arii_pro');
      if ($arii_pro === true) 
        return $this->render('AriiJIDBundle:Jobs:treegrid.html.twig' );
      return $this->render('AriiJIDBundle:Jobs:grid.html.twig' );
    }

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:toolbar.xml.twig',array(), $response );
    }

    public function menuAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:menu.xml.twig',array(), $response );
    }

    public function gridAction($history_max=0,$ordered = 0) {
        $color = array (
            'SUCCESS' => '#ccebc5',
            'RUNNING' => '#ffffcc',
            'FAILURE' => '#fbb4ae',
            'STOPPED' => '#FF0000',
            'QUEUED' => '#AAA',
            'STOPPING' => '#ffffcc'
        );

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        if ($request->get('ordered')>0) {
            $ordered = $request->get('ordered');
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $session = $this->container->get('arii_core.session');
        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');
        $date = $this->container->get('arii_core.date');
        $Status = $session->get('status');

/* On stocke les états */
        $Fields = array (
        '{spooler}'    => 'sh.SPOOLER_ID',
        '{job_name}'   => 'sh.PATH' );

            $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                    .$sql->From(array('SCHEDULER_JOBS sh'))
                    .$sql->Where($Fields)
                    .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));
        
        $res = $data->sql->query( $qry );
        $n=0;
        while ($line = $data->sql->get_next($res)) {
             $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
             if ($line['STOPPED']=='1' ) {
                 $Stopped[$jn] = true;
             }
             if ($line['NEXT_START_TIME']!='' )
                 $Next[$jn] = $line['NEXT_START_TIME'];
             $n++;
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
        
    /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME' );
        if ($ordered==0) {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields)
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc'));  

        $res = $data->sql->query( $qry );
        $nb=0;
        $H = array();
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
            $list .='<row id="'.$line['ID'].'" style="background-color: '.$color[$status].'">';
            // Cas particulier pour les RUNNING
            $list .='<cell>'.$line['SPOOLER_ID'].'</cell>';              
            $list .='<cell>'.dirname($line['JOB_NAME']).'</cell>'; 
            $list .='<cell>'.basename($line['JOB_NAME']).'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell>'.$this->images.'/'.strtolower($status).'.png</cell>'; 
            if ($status=='RUNNING') {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$start.'</cell>'; 
                $list .='<cell/>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            else {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],$line['END_TIME'],'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$date->ShortDate($start).'</cell>'; 
                $list .='<cell>'.$date->ShortDate($end).'</cell>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            $list .='<cell>'.$line['EXIT_CODE'].'</cell>';
            $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt($start,$end,$status).'"/>]]></cell>'; 
            $list .='<cell>'.$line['PID'].'</cell>';
            $list .='<cell>'.$this->images.'/'.strtolower($line['CAUSE']).'.png</cell>'; 
            $list .='</row>';
        }
        
    /* On prend les taches en file d'attente */
        $Fields = array (
           '{spooler}'    => 'st.SPOOLER_ID', 
            '{job_name}'   => 'st.JOB_NAME',
            '{!(spooler)}' => 'st.JOB_NAME' );
        $qry = $sql->Select(array('st.TASK_ID as ID','st.SPOOLER_ID','st.JOB_NAME','st.START_AT_TIME as START_TIME','st.TASK_XML'))
                .$sql->From(array('SCHEDULER_TASKS st'))
                .$sql->Where($Fields)
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
                .$sql->OrderBy(array('st.SPOOLER_ID','st.JOB_NAME','st.START_AT_TIME desc'));  

        $res = $data->sql->query( $qry );
        $H = array();
        while ($line = $data->sql->get_next($res)) {
            $nb++;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            
            $status = 'QUEUED';
            $list .='<row id="'.$line['ID'].'" style="background-color: '.$color[$status].'">';
            // Cas particulier pour les RUNNING
            $list .='<cell>'.$line['SPOOLER_ID'].'</cell>';              
            $list .='<cell>'.dirname($line['JOB_NAME']).'</cell>'; 
            $list .='<cell>'.basename($line['JOB_NAME']).'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell>'.$this->images.'/'.strtolower($status).'.png</cell>'; 
            list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
            $list .='<cell>'.$date->ShortDate($start).'</cell>'; 
            $list .='<cell/>'; 
            $list .='<cell/>';
            $list .='<cell/>';
            $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt(gmdate("Y-M-d H:i:s"),$start,$status).'"/>]]></cell>'; 
            $list .='<cell/>';
            $list .='<cell>'.$this->images.'/queue.png</cell>'; 
            $list .='</row>';
        }

        if ($nb==0) {
            exit();
        }
        
        $list .= "</rows>\n";
        $response->setContent( $list );
        return $response;
    }

}