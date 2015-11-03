<?php
// src/Arii/JIDBundle/Service/AriiSOS.php
 
namespace Arii\JIDBundle\Service;

class AriiSOS
{
    protected $session;
    protected $db;
    protected $sql;
    protected $audit;
    protected $log;

    public function __construct ( 
            \Arii\CoreBundle\Service\AriiSession $session, 
            \Arii\CoreBundle\Service\AriiDHTMLX $db, 
            \Arii\CoreBundle\Service\AriiSQL $sql,
            \Arii\CoreBundle\Service\AriiAudit $audit,  
            \Arii\CoreBundle\Service\AriiLog $log
            ) {
        $this->session =    $session;
        $this->db =         $db;
        $this->sql =        $sql;
        $this->audit =      $audit;
        $this->log =        $log;
    }

    // Nouvelle fonction pour les futures méthodes de connexion
    // Utilisation de la command Jid au lieu de XMLCommandCore
    public function Command($spooler,$cmd, $priority = 'tag')
    {   
        // Ne devrait pas arriver
        if ($spooler=='') {
            print "Spooler ?!";
            exit();
        }
                
        // Informations du spooler
        // pour l'instant on ne gère pas la haute dispo
        // Pour le REST
        $engine = $this->session->getSpoolerByName($spooler);
        if (isset($engine[0]['rest'])) {
            $rest = $engine[0]['rest'];
            if (isset($engine[0]['rest']['protocol']))  $protocol = $engine[0]['rest']['protocol'];
            else                                        $protocol = 'http';
            if (isset($engine[0]['rest']['path']))      $path = $engine[0]['rest']['path'];
            else                                        $path = '/';
            $host = $engine[0]['rest']['host'];
            $port = $engine[0]['rest']['port'];
            $method = 'CURL';
        }
        
        // Nouveauté 1.7
        $url = $protocol."://".$host.":".$port;        
        if ($method=='CURL') {
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_POST,1);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$cmd);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            //execute post
            $content = curl_exec($ch);
            if ($content === FALSE) {
                printf("cUrl error (#%d): %s<br/>$spooler<br/>$url\n", curl_errno($ch),
                htmlspecialchars(curl_error($ch)));
                $this->audit->AuditLog($host, $cmd, "ERROR","JID", sprintf("cUrl error (#%d): %s<br>\n", curl_errno($ch),
                htmlspecialchars(curl_error($ch))));
                $this->log->createLog("!ERROR: Can not Open URL: ".$url, 0, __FILE__, __LINE__, "ERROR at: ".__FILE__." function: ".__FUNCTION__." line: ".__LINE__.": !ERROR: Connection failed! Please make sure the JobScheduler have started!", $_SERVER['REMOTE_ADDR']);
                return array('ERROR'=>'CONNECT ON '.$url);
            }
            curl_close($ch);

            if ($priority=='xml') {
                return $content;
            }
            
        }
        else {
            if ($this->method=='POST') {
                $opts = array (
                        'http' => array (
                          'method' => "POST",
                          'header'=>"Content-Type: text/xml\r\n",
                          'content' => $cmd          
                //             'header' => $auth,
                //            'user_agent' => RESTClient :: USER_AGENT,
                        )
                );
                $context = stream_context_create($opts);
                $fp = @fopen($url,'r', false, $context);
            } 
            else {
                $url .= $path.\rawurlencode($cmd);
                $fp = @fopen($url,'r');
            }
            
            if (!$fp)
            {
                $this->audit->AuditLog($host, $cmd, "ERROR","JID", "!ERROR: Can not Open URL: ".$url);
                $this->log->createLog("!ERROR: Can not Open URL: ".$url, 0, __FILE__, __LINE__, "ERROR at: ".__FILE__." function: ".__FUNCTION__." line: ".__LINE__.": !ERROR: Connection failed! Please make sure the JobScheduler have started!", $_SERVER['REMOTE_ADDR']);
                return array('ERROR'=>'CONNECT ON '.$url);
            }
            $content = "";
            while (!feof($fp))
            {
                $content .= fgets($fp);
            }
            if ($priority=='xml') {
                return $content;
            }
        }
        
        // probleme de trim
        if ($content=='') return '';
        $msg = '';
        if (strlen($content)<1024) {
            $msg = $content;
        }
        else {
            $msg = "...";
        }
        $this->audit->AuditLog($host, $cmd, "SUCCESS","JID", $msg);
        
        $result = $this->xml2array($content, 1, $priority);
        if ($result != null)
        {
            return $result;
        }
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
        $qry = $sql->Select(array('JOB_NAME','SPOOLER_ID','PARAMETERS'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('ID' => $job_id));
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
        $qry = $sql->Select(array('JOB_NAME','SPOOLER_ID','PARAMETERS'))
                .$sql->From(array('SCHEDULER_TASKS'))
                .$sql->Where(array('TASK_ID' => $job_id));
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
        $qry = $sql->Select(array('JOB_CHAIN','SPOOLER_ID','ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));
        
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        return array($line['SPOOLER_ID'],$line['ORDER_ID'],$line['JOB_CHAIN']);
   }

   public function getJobChainInfos($id) {   
        $dhtmlx = $this->db;
        $data = $dhtmlx->Connector('data');

        $sql = $this->sql;
        $qry = $sql->Select(array('JOB_CHAIN','SPOOLER_ID','ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->Where(array('HISTORY_ID' => $id));

        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        return array($line['SPOOLER_ID'],$line['JOB_CHAIN'],$line['ORDER_ID']);
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
        $engine = $this->session->getSpoolerByName($spooler);
        
        // Pas de haute dispo pour l'instant
        return array($protocol,$hostname,$port,$path); 
        
        // on cherche le scheduler dans la base de données
        // obsolete ! Le job sos berlin n'est pas assez précis
        // un host local ne permet pas de retrouver une interface.
        /* 
            $dhtmlx = $this->db;
            $data = $dhtmlx->Connector('data');
         * 
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
        */
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

    function xml2array($contents, $get_attributes=1, $priority = 'tag') {
        if(!$contents) return array();

        if(!function_exists('xml_parser_create')) {
            //print "'xml_parser_create()' function not found!";
            return array();
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if(!$xml_values) return;//Hmm...

        //Initializations
        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();

        $current = &$xml_array; //Refference

        //Go through the tags.
        $repeated_tag_index = array();//Multiple tags with same name will be turned into an array
        foreach($xml_values as $data) {
            unset($attributes,$value);//Remove existing values, or there will be trouble

            //This command will extract these variables into the foreach scope
            // tag(string), type(string), level(int), attributes(array).
            extract($data);//We could use the array by itself, but this cooler.

            $result = array();
            $attributes_data = array();

            if(isset($value)) {
                if($priority == 'tag') $result = $value;
                else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
            }

            //Set the attributes too.
            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                }
            }

            //See tag status and do the needed.
            if($type == "open") {//The starting of the tag '<tag>'
                $parent[$level-1] = &$current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag.'_'.$level] = 1;

                    $current = &$current[$tag];

                } else { //There was another element with the same tag name

                    if(isset($current[$tag][0])) {//If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {//This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag.'_'.$level] = 2;

                        if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }

                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }

            } elseif($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if(!isset($current[$tag])) { //New Key
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;

                } else { //If taken, put all things inside a list(array)
                    if(isset($current[$tag][0]) and is_array($current[$tag])) {//If it is already an array...

                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;

                        if($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;

                    } else { //If it is not an array...
                        $current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if($priority == 'tag' and $get_attributes) {
                            if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well

                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }

                            if($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
                    }
                }

            } elseif($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level-1];
            }
        }

        return($xml_array);
    } 

}
