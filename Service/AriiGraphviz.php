<?php
// src/Arii/JIDBundle/Service/AriiSOS.php
 
namespace Arii\JIDBundle\Service;

class AriiGraphviz
{

    protected $images;   
    protected $tools;
    protected $sql;
    protected $dhtmlx;
    
    public function __construct ( 
            \Arii\CoreBundle\Service\AriiTools $tools,
            \Arii\CoreBundle\Service\AriiDHTMLX $db,
            \Arii\CoreBundle\Service\AriiSQL $sql ) {
        $this->tools = $tools;
        $this->dhtmlx = $db;
        $this->sql = $sql;
        $this->images = '/bundles/ariigraphviz/images/silk';
    }

    private function ColorNode($state,$error,$endtime) {
        if ($error == 'stop') {
            $color='red';
        }
        elseif ($error == 'next_state') {
            $color='orange';
        }
        elseif ($endtime=='') {
            $color='#ffffcc';
        }
        elseif ($error) {
            if (substr($state,0,1)=='!') {
                $color = 'red';
            }
            else {
                $color='#fbb4ae';
            }
        }
        else {
            $color = "#ccebc5";        
        }
        return $color;
    }
    
    public function Node($Infos=array()) {
        $res = '"/'.$Infos['JOB_CHAIN'].'/'.$Infos['STATE'].'" '; 
        if (!isset($Infos['END_TIME'])) $Infos['END_TIME']='';
        
        if (isset($Infos['ACTION']) and ($Infos['ACTION']!='')) {
            $color = $this->ColorNode($Infos['STATE'],$Infos['ACTION'],$Infos['END_TIME']);
        }
        else {
            $color = $this->ColorNode($Infos['STATE'],$Infos['ERROR'],$Infos['END_TIME']);
        }
        $res .= '[id="\N";label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" COLOR="grey" BGCOLOR="'.$color.'">';
        $res .= '<TR><TD align="left" colspan="3">'.$Infos['STATE'].'</TD></TR>';
        if (isset($Infos['ERROR']) and ($Infos['ERROR']>0)) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/error.png"/></TD><TD align="left" COLSPAN="2">'.substr($Infos['ERROR_TEXT'],15).'</TD></TR>';
        }
        if (isset($Infos['JOB_NAME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/cog.png"/></TD><TD align="left" colspan="2">'.$Infos['JOB_NAME'].'</TD></TR>';
        }
        if (isset($Infos['START_TIME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/time.png"/></TD><TD align="left" >'.$Infos['START_TIME'].'</TD><TD align="left" >'.$Infos['END_TIME'].'</TD></TR>';
        }
        if (isset($Infos['TASK_ID']))
            $res .= "</TABLE>> URL=\"javascript:parent.JobDetail(".$Infos['TASK_ID'].");\"]";
        else
            $res .= "</TABLE>>]";            
        return "$res\n";
    }
    
    public function Order($Infos=array()) {
        if ($Infos['ORDER_END_TIME']=='') {
            $color='#ffffcc';
        }
        elseif ($Infos['ERROR']) {
            $color='#fbb4ae';
        }
        else {
            $color = "#ccebc5";        
        }
        
        $tools = $this->tools;
        if (isset($Infos['ORDER_XML'])) {

            # On ouvre l'etat courant
            if (gettype($Infos['ORDER_XML'])=='object') {
                $order_xml = $tools->xml2array($Infos['ORDER_XML']->load());
            }
            else {
                $order_xml = $tools->xml2array($Infos['ORDER_XML']);
            }
            $setback = 0; $setback_time = '';
            if (isset($order_xml['order_attr']['suspended']) && $order_xml['order_attr']['suspended'] == "yes")
            {
                $order_status = "SUSPENDED";
                $color = 'red';
            }
            elseif (isset($order_xml['order_attr']['setback_count']))
            {
                $order_status = "SETBACK";
                $setback = $order_xml['order_attr']['setback_count'];
                $setback_time = $order_xml['order_attr']['setback'];
                $color = 'orange';
            }
            
            $next_time = '';
            if (isset($order_xml['order_attr']['start_time'])) {
                $next_time = $order_xml['order_attr']['start_time'];
            }
            $at = '';
            if (isset($order_xml['order_attr']['at'])) {
                $at = $order_xml['order_attr']['at'];
            }
            $hid = 0;
            if (isset($order_xml['order_attr']['history_id'])) {
                $hid = $order_xml['order_attr']['history_id'];
            }
        }
        $res = '"O.'.$Infos['ORDER_ID'].'" '; 
        $res .= '[id="\N";label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" COLOR="grey" BGCOLOR="'.$color.'">';
        $res .= '<TR><TD><IMG SRC="'.$this->images.'/lightning.png"/></TD><TD align="left">'.$Infos['ORDER_ID'].'</TD></TR>';
        if ($Infos['ORDER_TITLE']!='') {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/comment.png"/></TD><TD align="left">'.$Infos['ORDER_TITLE'].'</TD></TR>';
        }
        if (isset($Infos['ORDER_START_TIME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/time.png"/></TD><TD align="left" >'.$Infos['ORDER_START_TIME'].'</TD><TD align="left" >'.$Infos['ORDER_END_TIME'].'</TD></TR>';
        }        
        if ($Infos['ERROR']>0) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/error.png"/></TD><TD align="left">'.$Infos['ERROR_TEXT'].'</TD></TR>';
        }

        if (isset($Infos['PAYLOAD'])) {
            if (gettype($Infos['PAYLOAD'])=='object') {
                $params = $Infos['PAYLOAD']->load();
            }
            else {
                $params = $Infos['PAYLOAD'];
            }
            // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
            while (($p = strpos($params,'<variable name="'))>0) {
                $begin = $p+16;
                $end = strpos($params,'" value="',$begin);
                $var = substr($params,$begin,$end-$begin);
                $params = substr($params,$end+9);
                $end = strpos($params,'"/>');
                $val = substr($params,0,$end);
                $params = substr($params,$end+2);

                # Attention aux password !
                $val = preg_replace("/password=(.*?) /","password=**********","$val ");
                $res .= '<TR><TD><IMG SRC="'.$this->images.'/config.png"/></TD><TD align="left">'.$var.'</TD><TD align="left">'.$val.'</TD></TR>';
            }
        }
        $res .= '</TABLE>>]';
        return "$res\n";
    }

    public function Chain( $scheduler_id, $job_chain, $order_id, $Steps, $JobChains, $STATE = '', $next='', $error='' ) {

        $svg = "subgraph \"cluster$STATE\" {\n";
        $svg .= "style=filled;\n";
        if ($STATE != '') {
            $prefix = 'META:';
            $svg .= "\"$prefix$STATE\" [label=$STATE;shape=ellipse;color=black]\n";
        }
        else {            
            $prefix = '';
        }
        
        $sql = $this->sql;
        $dhtmlx = $this->dhtmlx;
        $data = $dhtmlx->Connector('data');
        
        $chain_id = $scheduler_id.'/'.$job_chain;
        $Chain = array();
        
        // On complete avec l'etat de la chaine
        $qry =  $sql->Select(array('SPOOLER_ID','PATH','STOPPED'))
                .$sql->From(array('SCHEDULER_JOB_CHAINS')) 
                .$sql->Where(array('SPOOLER_ID' => $scheduler_id, 'PATH' => $job_chain, 'STOPPED' => 1 ));

        $res = $data->sql->query( $qry );
        $Chain[$chain_id]['STOPPED']=0;
        if ($line = $data->sql->get_next($res)) {
            $Chain[$chain_id]['STOPPED']=1;
        }

        // On complete avec l'etat des steps
        $qry =  $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_STATE','ACTION'))
                .$sql->From(array('SCHEDULER_JOB_CHAIN_NODES')) 
                .$sql->Where(array('SPOOLER_ID' => $scheduler_id, 'JOB_CHAIN' => $job_chain ));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $step_id = $chain_id.'/'.$line['ORDER_STATE'];
            // Si il n'est pas dans l'historique
            if (!isset($Steps[$step_id])) {
                $Steps[$step_id]['STATE']= $line['ORDER_STATE'];
            }
            $Steps[$step_id]['ACTION']= $line['ACTION'];
        }
        
        // On complete avec les infos de l'ordre
        $qry =  $sql->Select(array('SPOOLER_ID','JOB_CHAIN','STATE','STATE_TEXT','TITLE','PAYLOAD','INITIAL_STATE','ORDER_XML'))
                .$sql->From(array('SCHEDULER_ORDERS')) 
                .$sql->Where(array('SPOOLER_ID' => $scheduler_id, 'JOB_CHAIN' => $job_chain, 'ID' => $order_id ));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {            
            $OrderInfo[$order_id]['ORDER_XML'] = $line['ORDER_XML'];
            $OrderInfo[$order_id]['PAYLOAD'] = $line['PAYLOAD'];
        }
        
        /* Chaine de Chaine */
        $search = $job_chain;
        
        $n = 0;
        while (isset($JobChains[$n])) {
            if ($JobChains[$n]['attr']['path']==$search) {
                $find = $n;
                break;
            }
            $n++; 
        }
        
        $last_next = $last_error = '';
        if (isset($find)) {
            $n = 0;
            $Node = $JobChains[$find]['job_chain_node'];
            
            // Entete
            if ($STATE != '')
                $svg .= '"'.$prefix.$STATE.'" -> "'.$job_chain.'/'.$Node[$n]['attr']['state']."\" [color=grey;style=dotted]\n";
            while (isset($Node[$n]['attr'])) {
                $s = $Node[$n]['attr']['state'];
                // bonne pratique des chaines splitées
                if (($p = strpos($s,':'))>0) {
                    $split = substr($s,0,$p);
                    $svg .=  '"'.$job_chain.'/'.$split.'" -> "'.$job_chain.'/'.$s.'" [color=yellow,style=dashed]'."\n";
                }
                
                if (isset($Node[$n]['attr']['job'])) {
                    // Noeud traité ?
                    if (isset($Steps["$scheduler_id$job_chain/$s"]))
                        $svg .= '"'.$job_chain.'/'.$Node[$n]['attr']['state']."\"\n";
                    else 
                        $svg .= '"'.$job_chain.'/'.$Node[$n]['attr']['state']."\" [label=\"$s\"]\n";
                }
                else {
                    $svg .= '"'.$job_chain.'/'.$Node[$n]['attr']['state']."\" [label=\"".$Node[$n]['attr']['state']."\";shape=ellipse;color=grey]\n";                    
                }
                if (isset($Node[$n]['attr']['next_state'])) {
                    $last_next = $job_chain.'/'.$Node[$n]['attr']['next_state'];
                    $svg .=  '"'.$job_chain.'/'.$Node[$n]['attr']['state'].'" -> "'.$last_next.'" [color=green,style=dotted]'."\n";
                }
                if (isset($Node[$n]['attr']['error_state'])) {
                    $last_error = $job_chain.'/'.$Node[$n]['attr']['error_state'];                    
                    $svg .=  '"'.$job_chain.'/'.$Node[$n]['attr']['state'].'" -> "'.$last_error.'" [color=red,style=dotted]'."\n";                    
                }
                $n++;
            }
        }

        if ($Chain[$chain_id]==1)
            $svg .= "color=red;\n";
        else 
            $svg .= "color=lightgrey;\n";
        
        $svg .= 'label="'.$job_chain."\"\n";
        
        $svg .= "}\n"; // fin de chaine
        
        if ($next != '') {
            $svg .=   '"'.$last_next.'" -> "'.$prefix.$next.'" [color=green,style=dashed]'."\n";
            $svg .=   '"'.$last_error.'" -> "'.$prefix.$error.'" [color=red,style=dashed]'."\n";
        }
        if ($next != '') {        
            $svg .= '"'.$prefix.$next."\" [label=\"$next\",shape=ellipse,color=green]\n";
            $svg .= '"'.$prefix.$error."\" [label=\"$error\",shape=ellipse,color=red]\n";
        }
        
    return $svg;
    }
    
    
}
