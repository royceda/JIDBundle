<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ClustersController extends Controller
{
    protected $images;

    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
    }
    
    public function indexAction()
    {
      $arii_pro = $this->container->getParameter('arii_pro');
      if ($arii_pro === true) 
        return $this->render('AriiJIDBundle:Clusters:treegrid.html.twig' );
      return $this->render('AriiJIDBundle:Clusters:grid.html.twig' );
    }

    public function listAction()   
    {
        return $this->render('AriiJIDBundle:Clusters:list.html.twig' );
    }

    public function toolbarAction()   
    {
        return $this->render('AriiJIDBundle:Clusters:toolbar.xml.twig' );
    }

    public function menuAction()   
    {
        return $this->render('AriiJIDBundle:Clusters:menu.xml.twig' );
    }

    public function list_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');
        $date = $this->container->get('arii_core.date'); 
        
        $qry = $sql->Select(array('MEMBER_ID','SCHEDULER_ID','PRECEDENCE','LAST_HEART_BEAT','NEXT_HEART_BEAT','ACTIVE','EXCLUSIVE','DEAD','COMMAND','HTTP_URL','DEACTIVATING_MEMBER_ID','XML'))
               .$sql->From(array('SCHEDULER_CLUSTERS'))
               .$sql->OrderBy(array('SCHEDULER_ID','PRECEDENCE'));

        $res = $data->sql->query( $qry );
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        $last = ''; // scheduler_id en cours
        $rows = ''; // Contenu du cluster
        $Scheduler = array();
        while ($line = $data->sql->get_next($res)) {
                $s = $line['SCHEDULER_ID'];
                // nouveau cluster ?
                if (($last!='') and ($last!=$s)) {
                    $list .= $this->Cluster($Scheduler,$rows);
                    $Scheduler=array();
                    $rows = '';
                }
/*
     [cluster_member] => Array
        (
        )

    [cluster_member_attr] => Array
        (
            [backup_precedence] => 0
            [demand_exclusiveness] => yes
            [host] => sosparis
            [pid] => 6142
            [running_since] => 2014-03-08T10:42:44.397Z
            [tcp_port] => 4444
            [udp_port] => 4444
            [version] => 1.6.4035
        )

)
*/
                $xml = $tools->xml2array($line['XML']);
                $Scheduler['id'] = $s;
                $Scheduler['running_since'] = $date->ShortISODate( $xml['cluster_member_attr']['running_since'], $s );
                $Scheduler['pid']     = $xml['cluster_member_attr']['pid'];
                $Scheduler['version'] = $xml['cluster_member_attr']['version'];
                $Scheduler['last_heartbeat'] = '';
                $Scheduler['duration'] = '';
                $Scheduler['status'] = 'UNKNOWN';
                
                $p = $line['PRECEDENCE'];
                $status = 'ACTIVE';
                if ($line['DEAD'] >0) {
                    $status = 'DEAD';
                }
                elseif ($line['ACTIVE']==0) {
                    $status = 'INACTIVE';
                }
                $duration = time()-$line['LAST_HEART_BEAT'];
                if ($line['PRECEDENCE'] == '' ) {
                    if ($status == 'ACTIVE') {
                        $Scheduler['status'] = 'RUNNING';
                    }
                    else {
                        $Scheduler['status'] = $status;
                    }
                }
                else {
                    // nouveau membre
                    $style = 'background-color: #ccebc5;';
                    $icon = 'server_add';
                    if ($status == 'DEAD') {
                        $status = 'DOWN';
                        $icon = 'server_error';
                        $style = 'background-color: black; color: red;'; 
                    }
                    elseif ($duration > 60) {
                        $icon = 'server';
                        $status = 'LATE';
                        $style = 'background-color: #fbb4ae;'; 
                    }
                    elseif ($status == 'INACTIVE') {
                        $icon = 'server';
                        $style = 'background-color: #ffffcc;'; 
                    }
                    $rows .= '<row id="'.$s.'#'.$p.'" style="'.$style.';">';
                    $rows .= '<cell image="'.$icon.'.png"> '.$xml['cluster_member_attr']['host'].':'.$xml['cluster_member_attr']['tcp_port'].'</cell>';
                    $rows .= '<cell>'.$status.'</cell>';
                    $rows .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                    if ($status != 'DOWN') {
                        $rows .= '<cell>'. $date->ShortISODate( $xml['cluster_member_attr']['running_since'],$s ).'</cell>';
                    }
                    else {
                        $rows .= '<cell/>';
                    }
                    $heart_beat = $date->ShortDate( date( 'Y-m-d H:i:s', $line['LAST_HEART_BEAT'] ) );
                    $rows .= '<cell>'.$heart_beat.'</cell>';
                    $rows .= '<cell>'.$duration.'</cell>';
                    $rows .= '<cell>'.$xml['cluster_member_attr']['pid'].'</cell>';
                    $rows .= '<cell>'.$xml['cluster_member_attr']['version'].'</cell>';
                    $rows .= '</row>';
                    
                    // Reactualisation en cas de bascule
                    // Seulement si le scheduler est en base de données
                    if (isset($Scheduler['running_since'])) {
                        if ($xml['cluster_member_attr']['running_since']>$Scheduler['running_since']) {
                            $Scheduler['running_since'] = $date->ShortISODate( $xml['cluster_member_attr']['running_since'], $s );
                            $Scheduler['pid']     = $xml['cluster_member_attr']['pid'];
                            $Scheduler['version'] = $xml['cluster_member_attr']['version'];
                        }
                        if (($Scheduler['duration']=='') or ($Scheduler['duration']>$duration)) {
                            $Scheduler['last_heartbeat'] = $heart_beat;
                            $Scheduler['duration'] = $duration;
                        }

                        //  Cas à traiter
                        // le noeud actif est en retard => le scheduler ne tourne pas
                        if (($line['ACTIVE']>0) and ($status == 'LATE')) {
                             $Scheduler['status'] = 'WAITING';
                        }
                    }
                    $last = $s;
                }
        }
        if ($last!='') {
            $list .= $this->Cluster($Scheduler,$rows);
        }
        $list .= "</rows>\n";
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $list );
        return $response;
    }
    
    function Cluster($Scheduler,$rows) {
        $style = 'background-color: #ccebc5;';
        if (!isset($Scheduler['id'])) return;
        $cluster  = '<row id="'.$Scheduler['id'].'" style="'.$style.'">';
        $cluster .= '<cell image="spooler.png"> '.$Scheduler['id'].'</cell>';
        $cluster .= '<cell>'.$Scheduler['status'].'</cell>';
        $cluster .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($Scheduler['status']).'.png"/>]]></cell>';
        $cluster .= '<cell>'.$Scheduler['running_since'].'</cell>';
        $cluster .= '<cell>'.$Scheduler['last_heartbeat'].'</cell>';
        $cluster .= '<cell>'.$Scheduler['duration'].'</cell>';
        $cluster .= '<cell>'.$Scheduler['pid'].'</cell>';
        $cluster .= '<cell>'.$Scheduler['version'].'</cell>';
        $cluster .= $rows;
        $cluster .= '</row>';
        return $cluster;
    }
    function render_spoolers($row) {
        if ($row->get_value('IS_PAUSED')==1) {
            $row->set_row_color("orange");
            $row->set_value("STATUS", 'PAUSED' );
        }
       elseif ($row->get_value('IS_RUNNING')==0) {
            $row->set_row_color("#fbb4ae");
            $row->set_value("STATUS", 'STOPPED' );
        }
        else {
            $row->set_row_color("#ccebc5");
            $row->set_value("STATUS", 'RUNNING' );
        }
    }
}
