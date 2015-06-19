<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ProcessController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');          
    }

    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Process:index.html.twig' );
    }

    public function toolbarAction()
    {
        return $this->render('AriiJIDBundle:Process:toolbar.xml.twig' );
    }


    public function graphvizAction()
    {
        $request = Request::createFromGlobals();
        $return = 0;
        
        $tmp = $this->container->getParameter('arii_tmp');
        $this->images = $this->container->getParameter('graphviz_images');
        $this->graphviz_dot = $this->container->getParameter('graphviz_dot');
        $session = $this->getRequest()->getSession();
        $id = $request->query->get( 'id' );

        $file = '.*';
        $rankdir = 'LR';
        $splines = 'polyline';
        $show_params = 'n';
        $show_events = 'n';

        if ($request->query->get( 'splines' ))
            $splines = $request->query->get( 'splines' );
        if ($request->query->get( 'show_params' ))
            $show_params = $request->query->get( 'show_params' );
        if ($show_params == 'true') {
            $show_params = 'y';
        }
        else {            
            $show_params = 'n';
        }
        if ($request->query->get( 'show_events' ))
            $show_events = $request->query->get( 'show_events' );
        if ($show_events == 'true') {
            $show_events = 'y';
        }
        else {            
            $show_events = 'n';
        }
        
        if ($request->query->get( 'output' ))
            $output = $request->query->get( 'output' );
        else {
            $output = "svg";        
        }
        
        // on commence par recuperer le statut de l'ordre
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $SOS = $this->container->get('arii_core.sos');
        $sql = $this->container->get('arii_core.sql');

        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.ORDER_ID','soh.SPOOLER_ID','soh.TITLE as ORDER_TITLE','soh.STATE as CURRENT_STATE','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
            'sosh.TASK_ID','sosh.STATE','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT'))
        .$sql->From(array('SCHEDULER_ORDER_HISTORY soh')) 
        .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
        .$sql->Where(array('soh.HISTORY_ID' => $id ))
//                . ' where soh.HISTORY_ID in (select max(HISTORY_ID) from SCHEDULER_ORDER_HISTORY)'
        .$sql->OrderBy(array('sosh.STEP desc'));
        $res = $data->sql->query( $qry );
        // Par jour 
 
        $States = array();
        $scheduler = "scheduler";
        while ($line = $data->sql->get_next($res)) {
            $s = $line['STATE'];
            $States[$s]['NAME'] = $s;
            foreach (array('TASK_ID','STEP','START_TIME','END_TIME','ERROR','ERROR_TEXT') as $i) {
                $States[$s][$i] = $line[$i];            
            }
            if (!isset($order)) {
                $scheduler = $line['SPOOLER_ID'];
                $order = $line['ORDER_ID'];
                $OrderInfo['NAME'] = $order;
                $OrderInfo['STATE'] = $line['CURRENT_STATE']; 
                foreach (array('START_TIME','END_TIME','TITLE') as $k) {
                    $OrderInfo[$k] = $line['ORDER_'.$k]; 
                }
                foreach (array('ERROR','ERROR_TEXT') as $k) {
                    $OrderInfo[$k] = $line[$k]; 
                }
                $job_chain = $line['JOB_CHAIN'];
            }
        }
        
        // on va chercher les conditions
        // est ce qu'il est en cache ?

        list($protocol,$hostname,$port,$path) = $this->getConnectInfos($scheduler);
        $cache = $tmp.'/'.$hostname.','.$port.',job_chains,job_commands.'.$scheduler.'.xml';
        $I =  @stat( $cache );
        $modif = $I[9];
        if ((time() - $I[9])>300) {            
            $SOS = $this->container->get('arii_core.sos');
            $cmd = '<show_state what="job_chains,job_commands"/>';
            $xml = $SOS->XMLCommand($scheduler,$hostname,$port,$path,$protocol,$cmd, 'xml');
            file_put_contents($cache, $xml);
        }
        else {
            $xml = file_get_contents($cache);          
        }
        $result = $SOS->xml2array($xml,1,'value');
        $JobChains = $result['spooler']['answer']['state']['job_chains']['job_chain'];
        $n = 0;
        $search = '/'.$job_chain;
        while (isset($JobChains[$n])) {
            if ($JobChains[$n]['attr']['path']==$search) {
                $find = $n;
                break;
            }
            $n++; 
        }
        $Conds = array(); 
        if (isset($find)) {
            // print_r($JobChains[$find]);
            $n = 0;
            $Node = $JobChains[$find]['job_chain_node'];
            while (isset($Node[$n]['attr'])) {
                if (isset($Node[$n]['attr']['next_state'])) {
                    array_push($Conds,'"'.$Node[$n]['attr']['state'].'" -> "'.$Node[$n]['attr']['next_state'].'" [color=green]');
                }
                if (isset($Node[$n]['attr']['error_state'])) {
                    array_push($Conds,'"'.$Node[$n]['attr']['state'].'" -> "'.$Node[$n]['attr']['error_state'].'" [color=red]');
                }
                $n++;
            }
        }
        $data = "digraph arii {\n";
        $data .= "fontname=arial\n";
        $data .= "fontsize=8\n";
        $data .= "splines=$splines\n";
        $data .= "randkir=$rankdir\n";
        $data .= "node [shape=plaintext,fontname=arial,fontsize=8]\n";
        $data .= "edge [shape=plaintext,fontname=arial,fontsize=8,decorate=true,compound=true]\n";
        $data .= "bgcolor=transparent\n";
        $data .= $this->Order($OrderInfo);
        $data .= '"'.$order.'" -> "'.$OrderInfo['STATE'].'"'."\n";
        $data .= "subgraph \"clusterJOBCHAIN\" {\n";
        $data .= "style=filled;\n";
        $data .= "color=lightgrey;\n";
        $data .= 'label="'.$job_chain."\"\n";
        foreach (array_keys($States) as $k) {
            $s = $States[$k];
            $data .= $this->Node($States[$k]);        
        }
        foreach ($Conds as $c) {
            $data .= "$c\n";        
        }
        $data .= "}\n}\n";
/*        print "<pre>$data</pre>";
        exit();
*/        $tmpfile = $tmp.'/arii.dot';
        file_put_contents($tmpfile, $data);
        
      $cmd = '"'.$this->graphviz_dot.'" "'.$tmpfile.'" -T '.$output;
/*
print $cmd;
print `$cmd`;
exit();
*/
        $base = "/arii/images";
        if ($output == 'svg') {
            // exec($cmd,$out,$return);
            $out = `$cmd`;
            header('Content-type: image/svg+xml');
            // integration du script svgpan
            $head = strpos($out,'<g id="graph');
            $xml = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1">
<script xlink:href="/arii/js/SVGPan.js"/>
<g id="viewport"';
            $xml .= substr($out,$head+14);
            print str_replace('xlink:href="'.$this->images,'xlink:href="/arii/images/silk',$xml);
            
        }
        elseif ($output == 'pdf') {
            header('Content-type: application/pdf');
            print system($cmd);
        }
        else {
            header('Content-type: image/'.$output);
            print system($cmd);
        }
        exit();
    }

    private function Node($Infos=array()) {
        $res = '"'.$Infos['NAME'].'" '; 
        if ($Infos['END_TIME']=='') {
            $color='#ffffcc';
        }
        elseif ($Infos['ERROR']) {
            if (substr($Infos['NAME'],0,1)=='!') {
                $color = 'red';
            }
            else {
                $color='#fbb4ae';
            }
        }
        else {
            $color = "#ccebc5";        
        }
        $res .= '[id="\N";label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" COLOR="grey" BGCOLOR="'.$color.'">';
        $res .= '<TR><TD>'.$Infos['STEP'].'</TD><TD align="left" colspan="2">'.$Infos['NAME'].'</TD></TR>';
        if ($Infos['ERROR']>0) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/error.png"/></TD><TD align="left" COLSPAN="2">'.$Infos['ERROR_TEXT'].'</TD></TR>';
        }
        if (isset($Infos['JOB_NAME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/cog.png"/></TD><TD align="left" colspan="2">'.$Infos['JOB_NAME'].'</TD></TR>';
            if (isset($Infos['PARAMETERS']['sos.spooler.variable_set']['attr']['count'])) {
                $n = $Infos['PARAMETERS']['sos.spooler.variable_set']['attr']['count'];
                if (isset($Infos['PARAMETERS']['sos.spooler.variable_set']['variable']['attr'])) {
                    $Infos['PARAMETERS']['sos.spooler.variable_set']['variable'][0]['attr'] = $Infos['PARAMETERS']['sos.spooler.variable_set']['variable']['attr'];
                    $i = 1;
                }
                for($i=0;$i<$n;$i++) {
                    $v = $Infos['PARAMETERS']['sos.spooler.variable_set']['variable'][$i]['attr'];     
                    $res .= '<TR><TD><IMG SRC="'.$this->images.'/bullet_yellow.png"/></TD><TD align="left" >'.$v['name'].'</TD><TD align="left" >'.$v['value'].'</TD></TR>';            
                }
            } 
        }
        if (isset($Infos['START_TIME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/time.png"/></TD><TD align="left" >'.$Infos['START_TIME'].'</TD><TD align="left" >'.$Infos['END_TIME'].'</TD></TR>';
        }
        $res .= "</TABLE>> URL=\"javascript:parent.JobDetail(".$Infos['TASK_ID'].");\"]";
        return "$res\n";
    }
    
    private function Order($Infos=array()) {
        $res = '"'.$Infos['NAME'].'" '; 
        if ($Infos['END_TIME']=='') {
            $color='#ffffcc';
        }
        elseif ($Infos['ERROR']) {
            $color='#fbb4ae';
        }
        else {
            $color = "#ccebc5";        
        }
        $res .= '[id="\N";label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" COLOR="grey" BGCOLOR="'.$color.'">';
        $res .= '<TR><TD><IMG SRC="'.$this->images.'/lightning.png"/></TD><TD align="left">'.$Infos['NAME'].'</TD></TR>';
        if ($Infos['TITLE']!='') {
                $res .= '<TR><TD><IMG SRC="'.$this->images.'/comment.png"/></TD><TD align="left">'.$Infos['TITLE'].'</TD></TR>';
        }
        if ($Infos['ERROR']>0) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/error.png"/></TD><TD align="left">'.$Infos['ERROR_TEXT'].'</TD></TR>';
        }
        $res .= '</TABLE>>]';
        return "$res\n";
    }

   private function getConnectInfos($spooler) {
        $session = $this->container->get('arii_core.session');
	$enterprise_id = $session->getEnterpriseId(); // get the enterprise id from the session
		
       // si il n'existe pas d'entreprise
       if ($enterprise_id<0) {
           $dhtmlx = $this->container->get('arii_core.dhtmlx');
           $data = $dhtmlx->Connector('data');
           
           // on cherche le scheduler dans la base de données
           $sql = $this->container->get('arii_core.sql');
           $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','IS_RUNNING','IS_PAUSED','START_TIME'))
                   .$sql->From(array('SCHEDULER_INSTANCES'))
                   .$sql->Where(array('SCHEDULER_ID' => $spooler ));

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

       // A voir si on elargit la recherche
       // Si oui, il faut reintegrer le code dans le service CMD
       
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
            $errorlog->createLog("No scheduler or port found!",0,__FILE__,__LINE__,"Error at: ".__FILE__." function: ".__FUNCTION__." line: ".__LINE__." SCHEDULER & PORT ?!",$_SERVER['REMOTE_ADDR']);
            print "SCHEDULER & PORT ?!"; // we use the audit service here to record the errors during using the XML command
            exit();
        }
   }
}
