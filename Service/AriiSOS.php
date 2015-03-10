<?php
// src/Arii/JIDBundle/Service/AriiSOS.php
 
namespace Arii\JIDBundle\Service;

class AriiSOS
{
    protected $db;
    protected $sql;
    
    public function __construct (  
            \Arii\CoreBundle\Service\AriiDHTMLX $db, 
            \Arii\CoreBundle\Service\AriiSQL $sql ) {
        $this->db = $db;
        $this->sql = $sql;
    }

/*********************************************************************
 * Informations de connexions
 *********************************************************************/

   public function getJobInfos($job_id) {   
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');
       
        // le job_id peut avoir une tâche
        if (($p = strpos($job_id,'#'))>0) {
            $job_id = substr($job_id,0,$p);
        }
        $sql = $this->sql;
        $qry = $sql->Select(array('sh.JOB_NAME','sh.SPOOLER_ID','sh.PARAMETERS'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where(array('sh.id' => $job_id));
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);

        return array($line['SPOOLER_ID'],$line['JOB_NAME'],$line['PARAMETERS']);        
   }

   public function getTaskInfos($job_id) {   
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');
       
        // le job_id peut avoir une tâche
        if (($p = strpos($job_id,'#'))>0) {
            $job_id = substr($job_id,0,$p);
        }
        $sql = $this->sql;
        $qry = $sql->Select(array('st.JOB_NAME','st.SPOOLER_ID','st.PARAMETERS'))
                .$sql->From(array('SCHEDULER_TASKS st'))
                .$sql->Where(array('st.TASK_ID' => $job_id));
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);

        return array($line['SPOOLER_ID'],$line['JOB_NAME'],$line['PARAMETERS']);        
   }

   public function getOrderInfos($id) {   
       // Si on commence par !, c'est activé mais non historisé
       if (substr($id,0,1)=='!') {
           return $this->getJobChainInfos(substr($id,1));
       }
       
       $dhtmlx = $this->db;
       $data = $dhtmlx->Connector('data');

        // indexé ?
        if (substr($id,0,2)=='O:') {
            $Infos = explode('/',substr($id,2));
            $spooler = array_shift($Infos);
            $order = array_pop($Infos);
            $job_chain = implode('/',$Infos);
            return array($spooler,$order,$job_chain,'');
        }
        // si l'id est une chaine, c'est dans le SCHEDULER_ORDERS
        elseif (strpos($id,'/')>0) {
            $Infos = explode('/',$id);
            $spooler = array_shift($Infos);
            $order = array_pop($Infos);
            $job_chain = implode('/',$Infos);
            return array($spooler,$order,$job_chain,'');
        }
        
        $sql = $this->sql;
        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.SPOOLER_ID','soh.ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->Where(array('soh.history_id' => $id));
        
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        return array($line['SPOOLER_ID'],$line['ORDER_ID'],$line['JOB_CHAIN']);
   }

   public function getJobChainInfos($id) {   
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');

        $sql = $this->sql;
        $qry = $sql->Select(array('ID','JOB_CHAIN','SPOOLER_ID'))
                .$sql->From(array('SCHEDULER_ORDERS'))
                .$sql->Where(array('ORDERING' => $id));

        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        return array($line['SPOOLER_ID'],$line['ID'],$line['JOB_CHAIN']);
   }

   public function getStateInfos($id) {   
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');

        // si l'id est une chaine, c'est dans le SCHEDULER_ORDERS_STEP_HISTORY
        if (strpos($id,'/')>0) {
            $Infos = explode('/',$id);
            $spooler = array_shift($Infos);
            $state = array_pop($Infos);
            $job_chain = implode('/',$Infos);
            return array($spooler,'',$job_chain,$state);
        }
        
        $sql = $this->sql;
        $qry = $sql->Select(array('sosh.STATE','soh.JOB_CHAIN','soh.SPOOLER_ID','soh.ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_STEP_HISTORY sosh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY soh',array('sosh.HISTORY_ID','soh.HISTORY_ID'))
                .$sql->Where(array('sosh.TASK_ID'=>$id)); 
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        return array($line['SPOOLER_ID'],$line['ORDER_ID'],$line['JOB_CHAIN'],$line['STATE']);
   }

   public function getConnectInfos($spooler) {
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');

        // on cherche le scheduler dans la base de données
        $sql = $this->sql;
        $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','IS_RUNNING','IS_PAUSED','START_TIME'))
                .$sql->From(array('SCHEDULER_INSTANCES'))
                .$sql->Where(array('SCHEDULER_ID' => $spooler ))
                .$sql->OrderBy(array('ID desc'));

        $res = $data->sql->query( $qry );
        // pourrais etre en parametre si besoin
        $protocol = "http"; $path = "";
        while ($line = $data->sql->get_next($res)) {
            $scheduler = $line['SPOOLER_ID'];
            $hostname = $line['HOSTNAME'];
            $port = $line['TCP_PORT'];
            $start_time = $line['TCP_PORT'];
            if ($line['IS_RUNNING']!=1) {
                // on tente un update ?
            }
            return array($protocol,$hostname,$port,$path);  
        }
        // sinon on regarde dans les parametres
                      
   }
   
   // a voir pour le mode multi-entreprise
   // doit etre pris en charge directement dans la session
   public function getConnectInfos2($spooler) {
        $session = $this->container->get('arii_core.session');
	$enterprise_id = $session->getEnterpriseId(); // get the enterprise id from the session
		
       // si il n'existe pas d'entreprise
       if ($enterprise_id<0) {
           $dhtmlx = $this->db;
           $data = $dhtmlx->Connector('data');
           
           // on cherche le scheduler dans la base de données
           $sql = $this->sql;
           $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','IS_RUNNING','IS_PAUSED','START_TIME'))
                   .$sql->From(array('SCHEDULER_INSTANCES'))
                   .$sql->Where(array('SCHEDULER_ID' => $spooler ))
                   .$sql->OrderBy(array('ID desc'));

           $res = $data->sql->query( $qry );
           // pourrais etre en parametre si besoin
           $protocol = "http"; $path = "";
           while ($line = $data->sql->get_next($res)) {
               $scheduler = $line['SPOOLER_ID'];
               $hostname = $line['HOSTNAME'];
               $port = $line['TCP_PORT'];
               $start_time = $line['TCP_PORT'];
               if ($line['IS_RUNNING']!=1) {
                   // on tente un update ?
               }
               return array($protocol,$hostname,$port,$path);  
           }
           // sinon on regarde dans les parametres
           
           
           // return array('http','localhost','4444','/');
       }
       
       // sinon on retrouve le spooler dans la base de données
       $qry = "SELECT ac.interface as HOSTNAME,ac.port as TCP_PORT,ac.path,an.protocol 
        from ARII_SPOOLER asp
        LEFT JOIN ARII_CONNECTION ac
        ON asp.connection_id=ac.id
        LEFT JOIN ARII_NETWORK an
        ON ac.network_id=an.id
        where asp.name='".$spooler."' 
        and asp.site_id in (select site.id from ARII_SITE site where site.enterprise_id='$enterprise_id')"; // we should use ac.interface as HOSTNAME

        if ($line['protocol'] == "osjs")
        {
            $protocol = "http";
        } elseif($line['protocol'] == "sosjs")
        {
            $protocol = "https";
        }
        if ((!isset($scheduler)) or ($scheduler == "") or ($port=="")) {
            $errorlog = $this->container->get('arii_core.log');
            $errorlog->createLog("No scheduler or port found!",0,__FILE__,__LINE__,"Error at: ".__FILE__." function: ".__FUNCTION__." line: ".__LINE__." $spooler ?!",$_SERVER['REMOTE_ADDR']);
            print "$spooler ?!"; // we use the audit service here to record the errors during using the XML command
            exit();
        }
   }
   
}
