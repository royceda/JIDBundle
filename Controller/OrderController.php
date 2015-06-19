<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller {
    
    protected $images;   
    
    public function indexAction()
    {
       $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_CHAIN','h.ORDER_ID')) 
                .$sql->From(array('SCHEDULER_ORDER_HISTORY h'))
                .$sql->Where(array('h.HISTORY_ID'=>$id));
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);

        return $this->render('AriiJIDBundle:Order:index.html.twig', 
                array('id' => $id, 'spooler' => $Infos['SPOOLER_ID'], 'chain' => $Infos['JOB_CHAIN'], 'order' => $Infos['ORDER_ID'] ) );
    }

    public function historyPageAction()
    {
       $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_CHAIN','h.ORDER_ID')) 
                .$sql->From(array('SCHEDULER_ORDER_HISTORY h'))
                .$sql->Where(array('h.HISTORY_ID'=>$id))
                .$sql->OrderBy(array('h.HISTORY_ID desc'))
                .$sql->Limit(array(50));
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        return $this->render('AriiJIDBundle:Order:history.html.twig', 
                array('id' => $id, 'spooler' => $Infos['SPOOLER_ID'], 'chain' => $Infos['JOB_CHAIN'], 'order' => $Infos['ORDER_ID'] ) );
    }

    public function toolbar_startAction() {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Order:toolbar_start.xml.twig", array(), $response);
    }

    public function stepsAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('soh.SPOOLER_ID','sosh.HISTORY_ID','sosh.STEP','sosh.TASK_ID','sosh.STATE','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_CODE','sosh.ERROR_TEXT'))
                .$sql->From(array('SCHEDULER_ORDER_STEP_HISTORY sosh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY soh',array('sosh.HISTORY_ID','soh.HISTORY_ID'))
                .$sql->Where(array('sosh.HISTORY_ID' => $id));

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');
        $data->event->attach("beforeRender",array($this,"steps_render"));
        $data->render_sql($qry,'TASK_ID','STEP,STATE,START_TIME,END_TIME,ERROR,ERROR_CODE,ERROR_TEXT');
    }

    function steps_render ($data){
        $svcdate = $this->container->get('arii_core.date');        
        $data->set_value('START_TIME', 
                $svcdate->ShortDate( $svcdate->Date2Local( $data->get_value('START_TIME'), $data->get_value('SPOOLER_ID') ) ) );
        if ($data->get_value('END_TIME')=='') {
            $data->set_row_color("#ffffcc");
        }
        elseif ($data->get_value('ERROR')>0) {
            $data->set_row_color("#fbb4ae");
        }
        else {
            $data->set_row_color("#ccebc5");
        }
        $data->set_value('ERROR_CODE',substr($data->get_value('ERROR_CODE'),15));
    }

    public function historyAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');   
        
        // Recherche des informations
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $qry = $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));
        $data = $dhtmlx->Connector('data');
        $res = $data->sql->query( $qry );
        if ($line = $data->sql->get_next($res)) {
            $spooler = $line['SPOOLER_ID'];
            $job_chain = $line['JOB_CHAIN'];
            $order = $line['ORDER_ID'];
        }
        else {
            exit();
        }
        $qry = $sql->Select(array('soh.HISTORY_ID','soh.STATE','soh.STATE_TEXT','soh.START_TIME','soh.END_TIME','soh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->Where(array('soh.SPOOLER_ID' => $spooler,'soh.JOB_CHAIN' => $job_chain,'soh.ORDER_ID' => $order))
                .$sql->OrderBy(array('soh.HISTORY_ID desc'))
                .$sql->Limit(50);

        $data2 = $dhtmlx->Connector('grid');
        $data2->event->attach("beforeRender",array($this,"grid_render"));        
        $data2->render_sql($qry,'HISTORY_ID','START_TIME,END_TIME,STATE,ERROR,MESSAGE');
    }
    
    function grid_render ($data){
        if ($data->get_value('ERROR')==0) {
            $data->set_row_color("#ccebc5");
        }
        else {
            $data->set_row_color("#fbb4ae");
        }
        if ($data->get_value('STATE_TEXT')!='')
            $msg = '['.$data->get_value('STATE_TEXT').']';
        else 
            $msg = '';
        if ($data->get_value('ERROR_TEXT')!='')
            $err = substr($data->get_value('ERROR_TEXT'),15);
        else 
            $err = '';
        $data->set_value('MESSAGE', implode(' ', array(  $err, $msg )));
    }


    public function formAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('HISTORY_ID','JOB_CHAIN','ORDER_ID','SPOOLER_ID','TITLE','STATE','STATE_TEXT','START_TIME','END_TIME'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('form');
        $data->event->attach("beforeRender",array($this,"form_render"));
        $data->render_sql($qry,'HISTORY_ID','FOLDER,HISTORY_ID,STATUS,JOB_CHAIN,ORDER_ID,SPOOLER_ID,TITLE,STATE,STATE_TEXT,START_TIME,END_TIME');
    }

    function form_render ($data){
        $data->set_value('FOLDER',dirname($data->get_value('JOB_CHAIN'))); 
        $data->set_value('NAME',basename($data->get_value('JOB_CHAIN'))); 
        if ($data->get_value('END_TIME')=='') {
            $data->set_value('STATUS','RUNNING');
        }
        elseif (substr($data->get_value('STATE'),0,1)=='!') {
            $data->set_value('STATUS','FATAL');            
        }
        else {
            $data->set_value('STATUS','SUCCESS');            
        }
    }

    public function paramsAction() {
        // recherche des infos 
        $request = Request::createFromGlobals();
        $id = $request->get('id');

        // Recherche des informations dans la base de données
        // Nécessaire pour la securité, inutile de faire un accès direct !
        $sos = $this->container->get('arii_jid.sos');
        list($spooler_id, $order_id, $job_chain) = $sos->getOrderInfos($id);
        list($protocol, $scheduler, $port, $path) = $sos->getConnectInfos($spooler_id);

        // On recupere les informations du job chain
        $cmd = '<show_order order="' . $order_id . '" job_chain="' . $job_chain . '" what="payload"/>';
        $SOS = $this->container->get('arii_core.sos');
        $result = $SOS->XMLCommand($spooler_id, $scheduler, $port, $path, $protocol, $cmd, 'attr');

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        if (isset($result['spooler']['answer']['order']['payload']['params']['param'])) {
            if (!isset($result['spooler']['answer']['order']['payload']['params']['param'][0])) {
                $result['spooler']['answer']['order']['payload']['params']['param'][0] = $result['spooler']['answer']['order']['payload']['params']['param'];
            }
            $n = 0;
            while (isset($result['spooler']['answer']['order']['payload']['params']['param'][$n])) {
                $param = $result['spooler']['answer']['order']['payload']['params']['param'][$n]['attr'];
                $list .= "<row><cell>".$param['name']."</cell><cell>".$param['value']."</cell></row>";
                $n++;
            }
        }
        $list .= "</rows>\n";
        $response->setContent($list);
        return $response;
    }

    public function logAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('HISTORY_ID','JOB_CHAIN','ORDER_ID','SPOOLER_ID','TITLE','STATE','STATE_TEXT','START_TIME','END_TIME'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));
        
        $data = $dhtmlx->Connector('data');
        $qry = $sql->Select(array('HISTORY_ID','LOG','END_TIME','SPOOLER_ID','ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));

        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            exit();
        }
        $svcdate = $this->container->get('arii_core.date');

        while ($Infos = $data->sql->get_next($res))
        {
            $spooler = $Infos['SPOOLER_ID'];
            if($Infos['END_TIME'] == '')
            {
                $query = "select ID,ORDER_XML from SCHEDULER_ORDERS where ID='".$Infos['ORDER_ID']."'";
                $result = $data->sql->query($query);
                $order = $data->sql->get_next($result);
                $xml = simplexml_load_string($order['ORDER_XML']);
                $logs = trim((string)$xml->log);
                $log = gzdeflate($logs,9);
                $Res = explode("\n",@gzinflate($log));
            } 
            else{
               if (gettype($Infos['LOG'])=='object') {
                     $Res = explode("\n",gzinflate ( mb_substr($Infos['LOG']->load(), 10, -8) ));
               }
               else {
                     $Res = explode("\n",gzinflate ( mb_substr($Infos['LOG'], 10, -8) ));
               }
            }
    
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<rows>';
            $xml .= '<head><afterInit><call command="clearAll"/></afterInit></head>';
            foreach ($Res as $l) {
                $date = substr($l,0,23);
                $type = rtrim(substr($l,29,8));
                $info = mb_substr($l,38);
                $task = $type;
                $error = '';
                if (mb_substr($info,0,6)==='(Task ') {
                    $type = 'Task';
                    $p = strpos($info,')');
                    $task = substr($info,6,$p-6);
                    $info = mb_substr($info,$p+2);
                }
                if (substr($info,0,10)==='SCHEDULER-') {
                    $error = substr($info,10,3);
                    $info = mb_substr($info, 14);
                }
                
                // coloration
                $bgcolor='';
                if ($type == '[info]') {
                    $bgcolor = ' style="background-color: lightblue;"';
                }
                elseif ($type == '[ERROR]') {
                    $bgcolor = ' style="background-color: red; color: yellow;"';
                }
                
                $xml .= "<row$bgcolor>";
                $logtime = $svcdate->ShortDate( $svcdate->Date2Local( $date, $spooler ));
                $xml .= '<cell>'.$logtime.'</cell>';
//                $xml .= '<cell>'.$type.'</cell>';
                $xml .= '<cell>'.$task.'</cell>';
                $xml .= '<cell><![CDATA['.htmlspecialchars($info, ENT_IGNORE, 'UTF-8').']]></cell>';
                $xml .= '<cell>'.$error.'</cell>';
//                $xml .= '<cell>'.$l.'</cell>';
                $xml .= '</row>';
            }
            $xml .= '</rows>';
            
            $response = new Response();
            $response->headers->set('Content-Type', 'text/xml');
            $response->setContent( $xml );
            return $response;
        }
    }

    public function chartAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get('id');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');
        $data =$dhtmlx->Connector('data');
        $qry = $sql->Select(array('soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->Where(array('soh.HISTORY_ID'=>$id));

        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        $spooler_id = $Infos['SPOOLER_ID'];
        $order_id = $Infos['ORDER_ID'];
        $job_chain = $Infos['JOB_CHAIN'];
        print $spooler_id;
        $chart = $dhtmlx->Connector('chart');
        $qry2 = $sql->Select(array('soh.HISTORY_ID','sosh.TASK_ID','soh.ORDER_ID','soh.START_TIME','soh.END_TIME','sosh.ERROR'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->Where(array('soh.SPOOLER_ID' => $spooler_id,
                        'soh.ORDER_ID' => $order_id,
                        'soh.JOB_CHAIN' => $job_chain))
                .$sql->OrderBy(array('soh.START_TIME DESC'));
        
        $chart->event->attach("beforeRender",array( $this, "render_order_chart"));
        $chart->render_sql($qry2,'ID',"START,DURATION,COLOR");
    }
    
    public function render_order_chart($row)
    {
        $start = strtotime($row->get_value("START_TIME"));
	$end = $row->get_value("END_TIME");
	$row->set_value("DURATION",strtotime($end)-$start );
        
        $error = $row->get_value("ERROR");
        if($error == 0)
        {
            $row->set_value("COLOR", "#749400");
        }
        else 
        {
            $row->set_value("COLOR", "red");
        }
        $row->set_value("START", $start);
        $row->set_value("ID", $row->get_value("HISTORY_ID"));
    }

    // Chaque noeud est un step
    public function graphvizAction()
    {
        $request = Request::createFromGlobals();
        $return = 0;
        
        $tmp = $this->container->getParameter('arii_tmp');
        $images = '/bundles/ariigraphviz/images/silk';
        $this->images = $this->get('kernel')->getRootDir().'/../web'.$images;
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
        
        $SOS = $this->container->get('arii_core.sos');
        $sql = $this->container->get('arii_core.sql');

        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.ORDER_ID','soh.SPOOLER_ID','soh.TITLE as ORDER_TITLE','soh.STATE as CURRENT_STATE','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
            'sosh.TASK_ID','sosh.STATE','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT'))
        .$sql->From(array('SCHEDULER_ORDER_HISTORY soh')) 
        .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
        .$sql->Where(array('soh.HISTORY_ID' => $id ))
        .$sql->OrderBy(array('sosh.STEP'));

        $svg = "digraph arii {\n";
        $svg .= "fontname=arial\n";
        $svg .= "fontsize=12\n";
        $svg .= "splines=$splines\n";
        $svg .= "randkir=$rankdir\n";
        $svg .= "node [shape=plaintext,fontname=arial,fontsize=8]\n";
        $svg .= "edge [shape=plaintext,fontname=arial,fontsize=8]\n";
        $svg .= "bgcolor=transparent\n";
        $svg .= "subgraph \"clusterJOBCHAIN\" {\n";
        $svg .= "style=filled;\n";
        $svg .= "color=lightgrey;\n";
        $last = '';        
        $res = $data->sql->query( $qry );
        $Done = array(); // Noeuds traités
        $OrderInfo = array();
        $job_chain='UNKNOWN ?';
        $etape=0;
        $date = $this->container->get('arii_core.date');
        while ($line = $data->sql->get_next($res)) {

            $s = $line['STATE'];
//            if (!isset($OrderInfo['NAME'])) {
                $scheduler = $line['SPOOLER_ID'];
                $order = $line['ORDER_ID'];
                $OrderInfo['NAME'] = $order;
                $OrderInfo['STATE'] = $line['CURRENT_STATE']; 
                $OrderInfo['TITLE'] = $line['ORDER_TITLE']; 
                $OrderInfo['ERROR'] = $line['ERROR']; 
                $OrderInfo['ERROR_TEXT'] = $line['ERROR_TEXT'];  
                $OrderInfo['END_TIME'] = $line['ORDER_END_TIME']; 
                $job_chain = $line['JOB_CHAIN'];
//            }
            $line['START_TIME']=$date->ShortDate( $date->Date2Local($line['START_TIME'],$scheduler));
            $line['END_TIME']=$date->ShortDate( $date->Date2Local($line['END_TIME'],$scheduler));
                    
            $svg .= $this->Node($line);
            $Done[$s]=1; 
            $step = $line['STEP'];
            if ($last !='') 
                $svg .= "\"$last\" -> \"$s\" [label=$etape,color=$color]\n";
            // On relie avec le noeud précédent
            // donc la couleur est pour le prochain lien
            if ($line['ERROR']==0 )
                $color= "green";
            else 
                $color= "red";
                
            $last = $s;
            $etape++;
        }

        $svg .= 'label="'.$job_chain."\"\n";
        // current states ?
        // est ce que le noeud de l'ordre est un noeud ?
        // si non, end node
        $current = $OrderInfo['STATE'];
        if (!isset($Done[$current])) {       
            $svg .= "\"$current\" [shape=record,color=$color,style=filled,fillcolor=\"".$this->ColorNode($current,$OrderInfo['ERROR'],$OrderInfo['END_TIME'])."\"]\n";    
            // on le relie au dernier
            $svg .= "\"$last\" -> \"$current\" [label=$etape,color=$color]\n";
        }
        $svg .= "}\n"; // fin de chaine
        $svg .= $this->Order($OrderInfo);
        $svg .= '"'.$order.'" -> "'.$OrderInfo['STATE'].'" [style=dashed]'."\n";        
        $svg .= "}\n"; // fin de graph
        
        $tmpfile = $tmp.'/arii.dot';
        file_put_contents($tmpfile, $svg);

      $cmd = '"'.$this->graphviz_dot.'" "'.$tmpfile.'" -T '.$output;
/*
print $cmd;
print `$cmd`;
exit();
*/
        // $base = "/arii/images";
        if ($output == 'svg') {
            // exec($cmd,$out,$return);
            $out = `$cmd`;
            header('Content-type: image/svg+xml');
            // integration du script svgpan
            $head = strpos($out,'<g id="graph');
            $xml = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1">
<script xlink:href="'.$this->container->get('templating.helper.assets')->getUrl("bundles/ariigraphviz/js/SVGPan.js").'"/>
<g id="viewport"';
            $xml .= substr($out,$head+14);
            print str_replace('xlink:href="'.$this->images,'xlink:href="'.$images_url,$xml);
            
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

    private function ColorNode($state,$error,$endtime) {
        if ($endtime=='') {
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
    private function Node($Infos=array()) {
        $res = '"'.$Infos['STATE'].'" '; 
        $color = $this->ColorNode($Infos['STATE'],$Infos['ERROR'],$Infos['END_TIME']);
        $res .= '[id="\N";label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" COLOR="grey" BGCOLOR="'.$color.'">';
        $res .= '<TR><TD align="left" colspan="3">'.$Infos['STATE'].'</TD></TR>';
        if ($Infos['ERROR']>0) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/error.png"/></TD><TD align="left" COLSPAN="2">'.substr($Infos['ERROR_TEXT'],15).'</TD></TR>';
        }
        if (isset($Infos['JOB_NAME'])) {
            $res .= '<TR><TD><IMG SRC="'.$this->images.'/cog.png"/></TD><TD align="left" colspan="2">'.$Infos['JOB_NAME'].'</TD></TR>';
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
