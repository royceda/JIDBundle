<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class SpoolerController extends Controller
{
   // force un update avec un show state
   public function UpdateAction($spooler='',$delay=60,$force=1) {
       $request = Request::createFromGlobals();
       if ($spooler=='')
        $spooler = $request->get('id');

       // on recupere la connection
       // on part du principe qu'on est en JID donc mode simple
       $dhtmlx = $this->container->get('arii_core.dhtmlx');
       $data = $dhtmlx->Connector('data');
       
       $sql = $this->container->get('arii_core.sql');
       $qry = $sql->Select(array('HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING'))
               .$sql->From(array('SCHEDULER_INSTANCES'))
               .$sql->Where(array('SCHEDULER_ID' => $spooler ));

      $res = $data->sql->query( $qry );
       // pourrais etre en parametre si besoin
       $protocol = "http"; $path = "";
       $hostname = '';
       while ($line = $data->sql->get_next($res)) {
            $hostname = $line['HOSTNAME'];
            $port = $line['TCP_PORT'];
            $start = $line['START_TIME'];
       }
       // Si le l'id est celui de la configuration
       // on se rabat sur celui la
       if ($hostname=='') {
           if ($this->container->getParameter('osjs_id')==$spooler) {
            $hostname = $this->container->getParameter('osjs_ipaddress');
            $port = $this->container->getParameter('osjs_port');
            $path = $this->container->getParameter('osjs_path');
            $protocol = $this->container->getParameter('osjs_protocol');
            }
            else {
                print "$spooler !!";
                exit();
            }
       }
       // on execute la commande show_state
       $SOS = $this->container->get('arii_core.sos');
       $result = $SOS->XMLCommand($spooler,$hostname,$port,$path,$protocol,'<show_state/>');
       if (isset($result['ERROR'])) {
            print '<font color="red">'.$result['ERROR'].'</font>';
            exit();
       }
       $is_paused = $is_running = 0;
       if (isset($result['spooler']['answer']['state_attr'])){
           $date = $this->container->get('arii_core.date');
           $attr = $result['spooler']['answer']['state_attr'];
           $state = $attr['state'];
           $start = $date->Date2Local($attr['spooler_running_since'],$spooler);
            print "<table>";
            print "<tr><th align='right'>State</th><td>".$state."</td></tr>";
            print "<tr><th align='right'>Start</th><td>".$start."</td></tr>";
            print "</table>";
            if ($state=="paused") {
                $is_paused=1;
                $is_running=0;
            }
            elseif ($state=="running") {
                $is_paused=0;
                $is_running=1;
            }
        }
        
        $qry = $sql->Update(array('SCHEDULER_INSTANCES'))
                .$sql->Set(array(   'START_TIME' => $start,
                                    'IS_RUNNING' => $is_running,
                                    'IS_PAUSED' => $is_paused))
                .$sql->Where(array('SCHEDULER_ID' => $spooler ));
        $res = $data->sql->query( $qry );
        exit();
   }
}
