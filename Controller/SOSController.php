<?php
namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SOSController extends Controller
{
   protected $id_why=0;
    
   public function XMLCommandAction( )
   {
        $audit = $this->container->get('arii_core.audit');
        $errorlog = $this->container->get('arii_core.log');
        $sos = $this->container->get('arii_jid.sos');
        $plan = $this->container->get('arii_ds.sos');
        
        $request = Request::createFromGlobals();
        $xml_command = $request->get( 'command' );
        $spooler_id = $request->get( 'spooler_id' );

        $SOS = $this->container->get('arii_jid.sos');

        switch ($xml_command) {            
            case 'add_order': 
                // En entrée:
                //   order_id: identifiant du traitement
                $id = $request->get('id');                
                $order_id = $request->get('order_id');    
                $title = $request->get('title'); 
                $at = $request->request->get('at');
                $start_state = $request->request->get('start_state');
                $end_state = $request->request->get('end_state');
                // informations du job
                list($spooler_id,$oid,$job_chain) = $sos->getOrderInfos($id);

                // Cas particulier de la nested job chain qui n'est pas dans la DB
                if ($request->get( 'chain' ) != 'undefined') {
                    $chain = trim($request->get( 'chain' ));
                    $job_chain = dirname($job_chain).'/'.$chain;
                    # on force l'order id si il y a un oubli
                    if (strpos($order_id,'.')==0) {
                        $order_id = $chain.'.'.$order_id;
                    }
                }
                
                $cmd = '<add_order job_chain="'.$job_chain.'" id="'.$order_id.'"';
                if ($title!='') {
                    $cmd .= ' title="'.$title.'"';
                }
                if ($start_state!=''){
                    $cmd .= ' state="'.$start_state.'"';
                }
                if ($end_state!='' && $end_state !="none")
                {
                    $cmd .= ' end_state="'.$end_state.'"';
                }
                if ($at == '') $at = 'now';
                $cmd .= ' at="'.$at.'">';
                $params_string = $request->request->get( 'paramsStr' );
                if ($params_string!='') {
                    $cmd  .= '<params>';
                    foreach (explode(',',urldecode($params_string)) as $params) { 
                       // $val = $request->request->get( $var );
                        $param = explode('=', $params);
                        $cmd  .= '<param name="'.$param[0].'" value="'.$param[1].'"/>';
                    }
                    $cmd  .= '</params>';
                 }
                 $cmd .= '</add_order>';
                break;
            case 'start_order':
            case 'modify_order':
                // En entrée:
                //   order_id: identifiant du traitement
                //   at: heure de depart
                 $id = $request->get('order_id');
                 $at = $request->get('time');
                 
                 list($spooler_id,$order_id,$job_chain) = $sos->getOrderInfos($id);                 
                 // Attention! si c'est un ordre statique, il faut un add order
                 $cmd = '<modify_order order="'.$order_id.'" job_chain="'.$job_chain.'"';
                 if ($request->get('action')=='suspended') {
                     $cmd .= ' suspended="yes"';
                 }
                 else {
                     $cmd .= ' suspended="no"';
                 }
                 if ($at == '') $at = 'now';
                 $cmd .= ' at="'.$at.'">';
                 
                 $params_string = $request->get('params');
                 if ($params_string!='') {
                    $cmd .= '<params>';
                    foreach (explode(',',urldecode($params_string)) as $params) {
                        $param = explode('=', $params);
                        $cmd  .= '<param name="'.$param[0].'" value="'.$param[1].'"/>';
                    }
                    $cmd .= '</params>';
                 }
                 $cmd .= '</modify_order>';
                 break;                    
            case 'start_job':
                // En entrée:
                //   job_id: identifiant du traitement
                //   at:     heure de démarrage
                //   params: paramètres
                $params_string = $request->get('params');
                $at = $request->get('time');
                
                // informations du job
                if ($request->get('job_id')!='') {
                    $job_id = $request->get('job_id');
                    list($spooler_id,$job) = $sos->getJobInfos($job_id);
                }
                else {
                    $job_id = $request->get('plan_id');                    
                    list($spooler_id,$job) = $plan->getJobInfos($job_id);
                }
                // construction de la commande
                $cmd  = '<start_job job="'.$job.'"';
                if ($at == '') $at = 'now';
                $cmd .= ' at="'.$at.'">';
                //$parameters = $request->request->get( 'parameters' );
                 if ($params_string!='') {
                    $cmd  .= '<params>';
                    foreach (explode(',',urldecode($params_string)) as $params) { 
                       // $val = $request->request->get( $var );
                        $param = explode('=', $params);
                        $cmd  .= '<param name="'.$param[0].'" value="'.$param[1].'"/>';
                    }
                    $cmd  .= '</params>';
                 }
                 $cmd .= '</start_job>';
                break;
            case 'why_job':
                // En entrée:
                //   job_id: identifiant du traitement
                $job_id = $request->get('job_id');
                // informations du job
                list($spooler_id,$job) = $sos->getJobInfos($job_id);
                // construction de la commande
                $cmd  = '<job.why job="'.$job.'"/>';
                $result = $SOS->Command($spooler_id,$cmd,'attr' );
                $why = $result['spooler']['answer']['job.why'];
                $reason_why = $this->show_array($why);
                return $this->render('AriiJIDBundle::bootstrap.html.twig',array('page' => $reason_why ) );
                break;
            case 'kill_task': 
                 $job_id = $request->get( 'job_id' );
                 if (($p=strpos($job_id,'#'))>0) {
                     $job_id=substr($job_id,0,$p);
                 }
                 list($spooler_id,$job) = $sos->getJobInfos($job_id);
                 $cmd  = '<kill_task job="'.$job.'"';
                 $cmd .= ' id="'.$job_id.'"';
//                 if ($request->request->get( 'immediately' )=='yes')
// http://www.sos-berlin.com/mediawiki/index.php/What_is_the_difference_between_%22end%22_and_%22kill_immediately%22%3F
                 $cmd .= ' immediately="yes"';
                 $cmd .= '/>';                 
                 break;
            case 'delete_task': 
                 $job_id = $request->get( 'job_id' );
                 if (($p=strpos($job_id,'#'))>0) {
                     $job_id=substr($job_id,0,$p);
                 }
                 list($spooler_id,$job) = $sos->getTaskInfos($job_id);
                 $cmd  = '<kill_task job="'.$job.'"';
                 $cmd .= ' id="'.$job_id.'"';
//                 if ($request->request->get( 'immediately' )=='yes')
// http://www.sos-berlin.com/mediawiki/index.php/What_is_the_difference_between_%22end%22_and_%22kill_immediately%22%3F
                 $cmd .= ' immediately="yes"';
                 $cmd .= '/>';                 
                 break;
            case 'stop_job':
            case 'unstop_job':
                // En entrée:
                //   job_id: identifiant du traitement
                $job_id = $request->request->get( 'job_id' );
                // informations du job
                list($spooler_id,$job) = $sos->getJobInfos($job_id);
                // construction de la commande
                if ($xml_command=="stop_job")
                {
                    $cmd  = '<modify_job job="'.$job.'" cmd="stop"/>';
                } elseif ($xml_command=="unstop_job")
                {
                    $cmd  = '<modify_job job="'.$job.'" cmd="unstop"/>';
                }
                break;
            case 'resume_order':
            case 'reset_order':
            case 'remove_setback':
            case 'suspend_order':
                $id = $request->get('order_id');
                
                 list($spooler_id,$order,$job_chain) = $sos->getOrderInfos($id);                 
                 if($xml_command=="suspend_order")
                {
                    $cmd = '<modify_order job_chain="'.$job_chain.'" order="'.$order.'" suspended="yes" />';
                } elseif ($xml_command=="resume_order")
                {
                    $cmd = '<modify_order job_chain="'.$job_chain.'" order="'.$order.'" suspended="no" />';
                } elseif ($xml_command=="reset_order")
                {
                    $cmd = '<modify_order job_chain="'.$job_chain.'" order="'.$order.'" action="reset" />';
                } elseif ($xml_command=="remove_setback")
                {
                    $cmd = '<modify_order job_chain="'.$job_chain.'" order="'.$order.'" setback="no" />';
                }
                
                break;	
            /*    
            case 'modify_order_prompt':
                 $order = $request->request->get( 'order' );
                 $job_chain = $request->request->get( 'job_chain' );
                 $state = $request->request->get( 'state' );
                 $cmd  = '<modify_order job_chain="'.$job_chain.'" state="'.$state.'" order="'.$order.'" suspended="no"><params><param name="scheduler_prompt" value="true"/></params></modify_order>';
                break;
            case 'remove_order':
                 $order = $request->request->get( 'order' );
                 $job_chain = $request->request->get( 'job_chain' );
                 $cmd  = '<remove_order job_chain="'.$job_chain.'" order="'.$order.'"></remove_order>';
                break;
             * 
             */
            case 'abort_spooler':
                $cmd = "<modify_spooler cmd='terminate'/>";
                break;
            case 'terminate_spooler':
                $cmd = "<modify_spooler cmd='abort_immediately'/>";
                break;
            case 'restart_spooler':
                $cmd = "<modify_spooler cmd='terminate_and_restart'/>";
                break;
            case 'pause_spooler':
                $cmd = "<modify_spooler cmd='pause'/>";
                break;
            case 'continue_spooler':
                $cmd = "<modify_spooler cmd='continue'/>";
                break;
            case 'stop_node':
            case 'unstop_node':
            case 'skip_node':
            case 'unskip_node':
                $id = $request->get('node_id');
                list($spooler_id,$order,$job_chain,$state) = $sos->getStateInfos($id); 
                switch($xml_command){
                    case "stop_node":
                        $cmd = '<job_chain_node.modify job_chain="'.$job_chain.'" state="'.$state.'" action="stop" />';
                        break;
                    case "skip_node":
                        $cmd = '<job_chain_node.modify job_chain="'.$job_chain.'" state="'.$state.'" action="next_state" />';
                        break;
                    case "unstop_node":
                    case "unskip_node":
                        $cmd = '<job_chain_node.modify job_chain="'.$job_chain.'" state="'.$state.'" action="process" />';
                        break;
                    default:
                        break;
                }
                break;
            case 'stop_chain':
            case 'unstop_chain':
                $id = $request->get('order_id');
                list($spooler_id,$job_chain,$order_id) = $sos->getJobChainInfos($id);
                // Cas particulier de la nested job chain qui n'est pas dans la DB
                if($xml_command == "stop_chain")
                {
                    $cmd = '<job_chain.modify job_chain="'.$job_chain.'" state="stopped" />';
                } 
                if($xml_command == "unstop_chain")
                {
                    $cmd = '<job_chain.modify job_chain="'.$job_chain.'" state="running" />';
                }
                break;
            default:
                $cmd = "Unknown command !!";
                print "XML Command '$xml_command' ?!";
                exit();
        }
        // Recherche les informations de connexion
        // Obsolete ! Le but est de se connecter quelque soit le moyen d'accès
        // list($protocol,$scheduler,$port,$path) = $sos->getConnectInfos($spooler_id);                
        
        if (!isset($cmd)) {
            $errorlog->Error("XML Command undefined",0,__FILE__,__LINE__,__FUNCTION__);
            print "Undefined XML Command";
            exit();
        }
        
        $result = $SOS->Command($spooler_id,$cmd );

        if (isset($result['ERROR'])) {
            if (substr($result['ERROR'],0,7) === 'CONNECT') {
                $t = $this->get('translator')->trans('Connection failed %protocol%://%host%:%port%! Please make sure the JobScheduler have started!', array('%protocol%' => $protocol,'%host%' => $scheduler,'%port%' => $port ));
                // on modifie le statut dans la base de données
                $dhtmlx = $this->container->get('arii_core.dhtmlx');
                $data = $dhtmlx->Connector('data');
                
                $sql = $this->container->get('arii_core.sql');
                $qry = $sql->Update(array('SCHEDULER_INSTANCES'))
                        .$sql->Set(array('IS_RUNNING' => 0))
                        .$sql->Where(array('SCHEDULER_ID' => $spooler_id));
                $res = $data->sql->query( $qry );
            }
            else {
                $t = $this->get('translator')->trans('Unknown error !');    
            }
            print "<font color='red'>".$this->get('translator')->trans('ERROR !')."</font>";
            print "<p>$t</p>";
            // print_r($result);
            exit();
            return new Response($t);
        }

        if (!isset($result['spooler'])) {
            print "<font color='red'>No answer!</font>";
            print_r($result);
            exit();
        }
        if (!isset($result['spooler']['answer'])) {
            print "<font color='red'>".$result."</font>";
            exit();
        }
        
        // Si c'est pas bon on sort en erreur
        if (!isset($result['spooler']['answer']['ok'])) {
            if (isset($result['spooler']['answer']['ERROR'])) {
                if (isset($result['spooler']['answer']['ERROR_attr'])) {
                    print $result['spooler']['answer']['ERROR_attr']['time'].": ";
                    print "<font color='red'>";
                    print $result['spooler']['answer']['ERROR_attr']['text'];
                    print "</font>";
                    exit();
                }
            }
        }
        
        print "<table>";
        if (isset($result['spooler']['answer_attr']['time']))
            print "<tr><th align='right'>Executed</th><td>".$result['spooler']['answer_attr']['time']."</td></tr>";
        if (isset($result['spooler']['answer']['ok']['order_attr'])){
            foreach (array('job_chain','order','created','state') as $k) {
                if (isset($result['spooler']['answer']['ok']['order_attr'][$k]))
                    print "<tr><th align='right'>$k</th><td>".$result['spooler']['answer']['ok']['order_attr'][$k]."</td></tr>";
            }
        }
        if (isset($result['spooler']['answer']['ok']['task_attr'])){
            foreach (array('job','id','start_at','state') as $k) {
                if (isset($result['spooler']['answer']['ok']['task_attr'][$k]))
                    print "<tr><th align='right'>$k</th><td>".$result['spooler']['answer']['ok']['task_attr'][$k]."</td></tr>";
            }
        }
        print "</table>";
        exit();
   }

   private function XmlWhy($result,$xml='') {
       foreach ($result as $k=>$v) {
           $img = ' image="bullet_green.png"'; $style = '';
           if (substr($k,-5)!='_attr') {
               if (substr($k,-4)=='.why') {
                    $img = ' image="eye.png"';
                    $k = substr($k,0,strpos($k,'.why'));
                    $style=' style="background-color: lightblue;"'; 
               }
               elseif ($k == 'obstacle') {
                    $k = '';
                    $img = ' image="exclamation.png"';
                    $style=' style="background-color: red;"'; 
               }
            $xml .= '<row id="'.$this->id_why++.'" open="1"'.$style.'>';
            $xml .= '<cell'.$img.'>'.$k.'</cell>';           
           }
            if (is_array($v)) {
                $xml .= $this->XmlWhy($v);
            }
            else {
                // si on a un obstacle, c'est sur les prochains
                $xml .= '<cell>'.$v.'</cell>';
            }
           if (substr($k,-5)!='_attr') {
            $xml .= '</row>';
           }
       }
       return $xml;
   }
   
   private function PurgeOrder($spooler,$order,$job_chain) {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $qry = 'delete from SCHEDULER_ORDERS
            where JOB_CHAIN="'.$job_chain.'" and SPOOLER_ID="'.$spooler.'" and ID="'.$order.'"';
        try {
            $res = $data->sql->query( $qry );
            return "<font color='green'>Order purged</font>";
        } catch (Exception $exc) {
            return "<font color='red'>Database error</font>";
        }
   }
   
    private function show_array($array, $page=''){
        if (is_array($array) == 1){          // check if input is an array
            foreach ($array as $k=>$v) {
                if ($k !=  'attr') {
                    $page .= "<blockquote>";
                    if (($p = strpos($k,'.why'))>0) {
                        $page .= "<i>".substr($k,0,$p)." ?</i>";
                    }
                    elseif ($k=='obstacle') {
                        $page .= "<font color='red'>$k</font>";
                    }
                    else {
                        $page .= $k;
                    }
                    $page .= $this->show_array($v);
                    $page .= "</blockquote>";
                }
                else {
                    $page .= $this->show_array($v);                    
                }
            }
        }  
        else{ // argument $array is not an array
            $page .= " <strong>$array</strong>";
        }
        return $page;
    }

}