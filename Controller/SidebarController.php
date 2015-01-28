<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;

class SidebarController extends Controller
{
    public function todoAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $qry = 'SELECT so.MOD_TIME, so.SPOOLER_ID, so.JOB_CHAIN, so.ID, so.STATE, so.STATE_TEXT, so.TITLE, HOSTNAME, TCP_PORT
            from SCHEDULER_ORDERS so
            left join SCHEDULER_INSTANCES
            on SPOOLER_ID=scheduler
            where STATE_TEXT like "PROMPT: %"';
        $res = $data->sql->query( $qry );
        $Todo = array();
        $nb=0;
        while ($line = $data->sql->get_next($res)) {
            $New['type'] ='prompt';
            $New['title'] =  utf8_encode ( substr($line['STATE_TEXT'],8) );
            $Msg = array();
            if ($line['TITLE'] != '') 
                array_push($Msg,$line['TITLE']);
            array_push($Msg,'['.$line['ID'].' -> '.$line['JOB_CHAIN'].'('.$line['STATE'].')]');
            $New['message'] = implode( '<br/>', $Msg );
            $Actions['Accept'] = 'javascript:PromptAccept("'.$line['HOSTNAME'].'","'.$line['TCP_PORT'].'","'.$line['ID'].'","'.$line['JOB_CHAIN'].'","'.$line['STATE'].'" );';
            $Actions['Cancel'] = 'javascript:PromptCancel("'.$line['HOSTNAME'].'","'.$line['TCP_PORT'].'","'.$line['ID'].'","'.$line['JOB_CHAIN'].'","'.$line['STATE'].'" );';
            $New['actions'] = $Actions;
            array_push($Todo, $New);
            $nb++;
        }       
        if ($nb==0)
            return new Response('');
        return $this->render('AriiJIDBundle:Sidebar:todo.html.twig', array('Todo' => $Todo ) );
    }

    public function calendarAction($mode = '') 
    {
        $session = $this->getRequest()->getSession();
        $request = Request::createFromGlobals();
        
        // Date courante
        $info = localtime(time(), true);
        $dc = $info['tm_mday'];        
        $datec = sprintf("%04d-%02d",$info['tm_year']+1900,$info['tm_mon']+1);
        $heurec = sprintf("%02d:%02d:%02d",$info['tm_hour'],$info['tm_min'],$info['tm_sec']);
        
        $time = $request->query->get( 'ref_date' );
       
        
        if ($time == "") {
            $time = $session->get('ref_date' );
        }

        // Date reference Get ou Session ou Date actuelle
        if ($time=="") {    
            $time = time();
            $info = localtime($time, true);
            $heure = sprintf("%02d:%02d:%02d",$info['tm_hour'],$info['tm_min'],$info['tm_sec']);
            $y = $info['tm_year']+1900;
            $m = $info['tm_mon']+1;
            $d = $info['tm_mday'];
        }
        else {    
            $y = substr($time,0,4);
            $m = substr($time,5,2);
            $d = substr($time,8,2);
        /*  $h = substr($time,11,2);
            $mi = substr($time,14,2);
            $s = substr($time,17,2);
       */
            $heure = substr($time,11,8);
        }
/*      
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        if ($data) {
            $Fields = array ( 'getmonth(START_TIME)' => $m );
            $sql = $this->container->get('arii_core.sql');
            $qry = $sql->Select(array('day(START_TIME) as JOUR','count(ID) as NB','ERROR'))
                    .$sql->From(array('SCHEDULER_HISTORY'))
                    .$sql->Where($Fields)
                    .$sql->GroupBy(array('day(START_TIME)','ERROR'));
// exit();
// $qry = 'select day(start_time) as jour,count(id) as nb,error from SCHEDULER_HISTORY where month(start_time)='.$m.' and not(isnull(end_time)) group by day(start_time),error';
            $res = $data->sql->query( $qry );
            $max = 0;
            while ($line = $data->sql->get_next($res)) {
                $jour = $line['JOUR']; 
                $Max[$jour]=0;
                if ($line['ERROR']>0) {
                    $Error[$jour] = $line['NB'];
                    $Max[$jour] += $line['NB'];
                }
                else {
                    $Success[$jour] = $line['NB'];
                    $Max[$jour] += $line['NB'];
                }
                if ($Max[$jour]>$max) {
                    $max = $Max[$jour];
                } 
            }
        }
 
 */
        $Cal['heure'] = $heurec;
        
        // Precedent
        $mp = $m - 1;
        if ($mp<1) {
            $yp = $y - 1;
        }
        else {
            $yp = $y;
        }
        $Cal['precedent'] = $_SERVER['PHP_SELF'].'?ref_date='.sprintf("%04d-%02d-%02d ",$yp,$mp,$d).$heurec;

        // 1er jour du mois
        $Cal['mois'] = 'str_month.'.($m*1);
        $Cal['annee'] = $y;
        $date = sprintf("%04d-%02d",$y,$m );

        $first = mktime(0,0,0,$m,1,$y);
        // dernier jour du mois
        if ($m==12) {
            $m=1;
            $y++;
        }
        else {
            $m++;
        }
        $last = mktime(0,0,0,$m,1,$y);
        // Jour de la semaine de ce mois
        $info_first = localtime($first, true);
        $jf = $info_first['tm_wday'];
        // on doit avoir 35 jours !
        // on commence la semaine au lundi
        // $jf = ($jf+1) % 7;
        if($jf==0)
        {
            $jf=7;
        }
        for($i=0;$i<35;$i++) {
            $D[$i] = '<span></span>';
        }
        // Nombre de jours
        $nb = ($last - $first)/86400;
        // Si le jour est superieur, on se recale au mois
        if ($d>$nb) $d=$nb;
        
        for($i=1;$i<=$nb;$i++) {
             $j = $jf+$i-2;
            
            $D[$j] = '<a href="'.$_SERVER['PHP_SELF'].'?ref_date='.$date.'-'.substr("0".$i,-2).' '.$heurec.'"';
            if (($date==$datec) and ($i==$dc)) 
                $D[$j] .= ' class="today"';
            elseif ($i==$d)
                $D[$j] .= ' class="event"  style="border: 2px solid yellow;"';
            else {
                // couleur par erreurs
                $nb_jobs = 0;
                if (isset($Success[$i])) {
                    $nb_jobs = $Success[$i];
                    $bg = '';
                    if (isset($Error[$i])) {
                        $nb_jobs += $Error[$i];
                        $percent = round($Error[$i]*255/$nb_jobs);
                        $red = dechex($percent);
                        $green = dechex(255-$percent);
                        $bg = 'background-color: #'.substr('0'.$red,-2).substr('0'.$green,-2).'00;';
                    }
                    $volume=round($nb_jobs*3/$max);                   
                    $D[$j] .= ' style="border: '.$volume.'px solid #AAA;'.$bg.'"';
                }
            }
            $D[$j] .= '>'.$i.'</a>';

        }
        $Cal['jours'] = $D;
        $Cal['suivant'] = $_SERVER['PHP_SELF'].'?ref_date='.sprintf("%04d-%02d-%02d ",$y,$m,$d).$heurec;
        
        $ref_date = $date.'-'.substr("0".$d,-2).' '.$heurec;
        $session->set( 'ref_date', $ref_date );
        
        // Passe et futur
        $Cal['ref_past'] = $session->get('ref_past' );
        if ($Cal['ref_past']=="") 
            $Cal['ref_past'] = 4;
        $Cal['ref_future'] = $session->get('ref_future' );
        if ($Cal['ref_future']=="") 
            $Cal['ref_future'] = 2;
        
        // Rien, passé ou future
        $Cal['Mode'] = $mode;
        
      return $this->render('AriiJIDBundle:Sidebar:calendar.html.twig', $Cal );
    }

}
