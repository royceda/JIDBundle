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
        
        $tmp = sys_get_temp_dir();
        $images = '/bundles/ariigraphviz/images/silk';
        $images_path = $this->get('kernel')->getRootDir().'/../web'.$images;
        $images_url = $this->container->get('templating.helper.assets')->getUrl($images);        

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
        
        $sql = $this->container->get('arii_core.sql');

        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.ORDER_ID','soh.SPOOLER_ID','soh.TITLE as ORDER_TITLE','soh.STATE as CURRENT_STATE','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
            'sosh.TASK_ID','sosh.STATE','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT'))
        .$sql->From(array('SCHEDULER_ORDER_HISTORY soh')) 
        .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
        .$sql->Where(array('soh.HISTORY_ID' => $id ))
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
        
        $cache = $tmp.'/'.$scheduler.',job_chains,job_commands.'.$scheduler.'.xml';
        $I =  @stat( $cache );
        $modif = $I[9];
        $SOS = $this->container->get('arii_jid.sos');
        if ((time() - $I[9])>300) {            
            $cmd = '<show_state what="job_chains,job_commands"/>';
            $xml = $SOS->Command($scheduler,$cmd, 'xml');
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
            header('Content-type: image/svg+xml; charset=utf-8"');
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

}
