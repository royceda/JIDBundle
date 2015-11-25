<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessesController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
    }

    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Processes:index.html.twig');
    }

    public function treeAction() {
        // en attendant le cache
        $request = Request::createFromGlobals();
        $stopped = $request->get('stopped');
        
        $folder = 'live';
        // $this->syncAction($folder);
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
   /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'SPOOLER_ID', 
            '{job_chain}'   => 'JOB_CHAIN',
            '{start_time}' => 'START_TIME',
            'ORDER_ID'   => '%.%' );

        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');

        $qry = $sql->Select(array('ORDER_ID','HISTORY_ID','SPOOLER_ID','JOB_CHAIN','START_TIME','END_TIME','STATE' ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('SPOOLER_ID','JOB_CHAIN','START_TIME desc'));  
   
        $res = $data->sql->query( $qry );
        $Chains = $Orders = array();
        
        while ( $line = $data->sql->get_next($res) ) {

            $id  =  $line['HISTORY_ID'];
            // La chaine est le prefix de l'ordre
            list($job_chain,$order) = explode('.',$line['ORDER_ID']);
            $chain = "/".$line['SPOOLER_ID'].'/'.dirname($line['JOB_CHAIN']).'/'.$job_chain;
            
            $dir = $chain.'/'.$order;
            
            if (!isset($Chains[$chain])) {
                $key_files[$chain] = $chain;
                $Chains[$chain]=1; 
            }
            
            if (isset($Orders[$dir])) continue;
            $Orders[$dir] = $line; 
            
            // On ccompte les erreurs
            $key_files[$dir] = $dir;
        }

        // Prend on en compte les suspended ?
            $Fields = array (
                '{spooler}'    => 'SPOOLER_ID', 
                '{job_chain}'   => 'PATH',
                'STOPPED'   => 1 );
            $qry = $sql->Select(array('SPOOLER_ID','PATH' ))
                    .$sql->From(array('SCHEDULER_JOB_CHAINS'))
                    .$sql->Where($Fields);  

              $res = $data->sql->query( $qry );
              while ( $line = $data->sql->get_next($res) ) {
                $dir = '/'.$line['SPOOLER_ID'].'/'.$line['PATH'];
                $Chains[$dir]='STOPPED';
            }
        
        $tools = $this->container->get('arii_core.tools');
        $tree = $tools->explodeTree($key_files, "/");
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<tree id='0'>\n";
        
        $list .= $this->Folder2XML( $tree, '', $Chains, $Orders );
        $list .= "</tree>\n";
        $response->setContent( $list );
        return $response;
    }
 
   function Folder2XML( $leaf, $id = '', $Chains, $Orders ) {
            $return = '';
            if (is_array($leaf)) {
                    foreach (array_keys($leaf) as $k) {
                            $Ids = explode('/',$k);
                            $here = array_pop($Ids);
                            $i  = "$id/$k";
                            # On ne prend que l'historique
                            // Chains ?
                            if (isset($Chains[$i])) {
                                if ($Chains[$i]=='STOPPED')
                                    $return .= '<item style="background-color: red;" id="'.$i.'" text="'.basename($i).'" im1="job_chain.png" im0="job_chain.png"  open="1">';
                                else 
                                    $return .= '<item id="'.$i.'" text="'.basename($i).'" im1="job_chain.png" im0="job_chain.png"  open="1">';
                                    
                            }
                            elseif (isset($Orders[$i])) {
                                $detail = ' ('.$Orders[$i]['STATE'].')';
                                if (substr($Orders[$i]['STATE'],0,1)=='!') {
                                    $style =  ' style="background-color: red;"';
                                }
                                else {
                                    // Statut
                                    switch ($Orders[$i]['STATE']) {
                                        case 'SUCCESS':
                                            $style =  ' style="background-color: #ccebc5;"';
                                            break;
                                        case 'FAILURE':
                                            $style =  ' style="background-color: #fbb4ae;"';
                                            break;
                                        default:
                                            $style = '';
                                            break;
                                    }
                                    $detail = '';
                                }
                                $return .= '<item'.$style.' id="O:'.$Orders[$i]['HISTORY_ID'].'" text="'.basename($i).$detail.'" im0="order.png">';
                            }
                            elseif ($id == '' ) {                                
                                $return .= '<item id="'.$i.'" text="'.$id.basename($i).'" im0="cog.png" im1="cog.png"  open="1">';
                            }
                            else {
                                $return .=  '<item id="'.$i.'" text="'.basename($i).'" im0="folderClosed.gif">';
                            }
                           $return .= $this->Folder2XML( $leaf[$k], $id.'/'.$k, $Chains, $Orders);
                           $return .= '</item>';
                    }
            }
            return $return;
    }

    // version synthetique
    public function stepsAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->get('id');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');                  
        $date = $this->container->get('arii_core.date');        
        
        $data = $dhtmlx->Connector('data');        

        // On recupere le contexte
        $qry = $sql->Select(array(  'ORDER_ID' ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array( 'HISTORY_ID' => $id ));
        $res = $data->sql->query( $qry );
        $State = array();
        if ($line = $data->sql->get_next($res)) {
            $order_id = $line['ORDER_ID'];
        }
        else {
            // l'ordre a disparu ?!
            exit();
        }
        
        $qry = $sql->Select(array(  'soh.SPOOLER_ID','soh.JOB_CHAIN',
                                    'sosh.HISTORY_ID','sosh.STEP','sosh.TASK_ID','sosh.STATE','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_CODE','sosh.ERROR_TEXT'))
                .$sql->From(array('SCHEDULER_ORDER_STEP_HISTORY sosh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY soh',array('sosh.HISTORY_ID','soh.HISTORY_ID'))
                .$sql->Where(array( 'soh.HISTORY_ID>=' => $id,  'soh.ORDER_ID' => $order_id  ));
        
        $res = $data->sql->query( $qry );
        $State = array();
        while ($line = $data->sql->get_next($res)) {
            $scheduler_id = $line['SPOOLER_ID'];
            $job_chain = $line['JOB_CHAIN'];
            $chain_id = $scheduler_id.'/'.$line['JOB_CHAIN'];
            $state_id = $chain_id.'/'.$line['STATE'];
            
            $State[$state_id] = $line;
            $State[$state_id]['JOB_CHAIN'] =  basename($job_chain);
            $State[$state_id]['ACTION'] = '';
        }
        
        // Etat des noeuds
        $qry =  $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_STATE','ACTION'))
                .$sql->From(array('SCHEDULER_JOB_CHAIN_NODES')) 
                .$sql->Where(array('SPOOLER_ID' => $scheduler_id, 'JOB_CHAIN' => $job_chain ));

        $res = $data->sql->query( $qry );

        while ($line = $data->sql->get_next($res)) {
            $step_id = $chain_id.'/'.$line['ORDER_STATE'];
            // Si non defini 
            if (!isset($State[$step_id])) {
                $State[$step_id]['STATE']= $line['ORDER_STATE'];
                $State[$step_id]['TASK_ID']= $line['ORDER_STATE'];
                $State[$step_id]['STEP'] = '?';
            }
            $State[$step_id]['ACTION']= $line['ACTION'];
        }
        
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
        }
        
        $xml = "<?xml version='1.0' encoding='utf-8' ?>";
        $xml .= "<rows>";
        $xml .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';        
        foreach ($State as $state_id=>$line) { 
            $s = $line['STATE'];
            
            if ($line['ACTION']=='stop') {
                $color = "red";
                $status = "STOPPED";
            }
            elseif ($line['ACTION']=='next_state') {
                $color = "orange";
                $status = "SKIPPED";
            }
            elseif ($line['END_TIME']=='') {
                $color = "#ffffcc";
                $status = "RUNNING";
            }
            elseif ($line['ERROR']>0) {
                $color = "#fbb4ae";
                $status = "FAILURE";
            }
            else {
                $color = "#ccebc5";
                $status = "SUCCESS";
            }
            if (isset($line['ERROR_CODE'])) 
                $line['ERROR_CODE'] = substr($line['ERROR_CODE'],15);
            else 
                $line['ERROR_CODE'] = '';
            $xml .= "<row id='".$line['TASK_ID']."' bgColor='$color'>";
            $xml .= "<cell>".$line['JOB_CHAIN']."</cell>";
            $xml .= "<cell>".$line['STEP']."</cell>";
            $xml .= "<cell><![CDATA[".$line['STATE']."]]></cell>";
            $xml .= "<cell><![CDATA[".$status."]]></cell>";
            if (isset($line['START_TIME'])) {
                $line['START_TIME'] = $date->ShortDate( $date->Date2Local( $line['START_TIME'],  $line['SPOOLER_ID'] ) );
                $xml .= "<cell><![CDATA[".$line['START_TIME']."]]></cell>";
                $xml .= "<cell><![CDATA[".$line['END_TIME']."]]></cell>";
            }
            else {
                $xml .= "<cell/><cell/>";
            }
            if (isset($line['ERROR'])) {
                $xml .= "<cell><![CDATA[".$line['ERROR']."]]></cell><cell><![CDATA[]]></cell>";
                $xml .= "<cell><![CDATA[".$line['ERROR_CODE']."]]></cell>";
                $xml .= "<cell><![CDATA[".$line['ERROR_TEXT']."]]></cell>";
                $xml .= "<cell/><cell/><cell/>";
            }
            $xml .= "</row>";
        }
        $xml .= "</rows>";
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $xml );
        return $response;        
    }

    // Chaque noeud est un step
    public function graphvizAction()
    {
        $request = Request::createFromGlobals();
        $return = 0;
        
        $tmp = sys_get_temp_dir();
        $images = '/bundles/ariigraphviz/images/silk';
        $this->images = $this->get('kernel')->getRootDir().'/../web'.$images;
        $images_url = $this->container->get('templating.helper.assets')->getUrl($images);        

        $this->graphviz_dot = $this->container->getParameter('graphviz_dot');
        $session = $this->getRequest()->getSession();
        $id = $request->query->get( 'id' );

        if ($id==0) exit();
        
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
        $date = $this->container->get('arii_core.date');
        $sql = $this->container->get('arii_core.sql');

        // On recupere le contexte
        $qry = $sql->Select(array(  'ORDER_ID' ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where(array( 'HISTORY_ID' => $id ));
        $res = $data->sql->query( $qry );
        $State = array();
        if ($line = $data->sql->get_next($res)) {
            $order_id = $line['ORDER_ID'];
        }
        else {
            // l'ordre a disparu ?!
            exit();
        }
        list($META_CHAIN,$ORDER)=explode('.',$order_id);
        
        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.ORDER_ID','soh.SPOOLER_ID','soh.TITLE as ORDER_TITLE','soh.STATE as CURRENT_STATE','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
            'sosh.TASK_ID','sosh.STATE','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT'))
        .$sql->From(array('SCHEDULER_ORDER_HISTORY soh')) 
        .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
        .$sql->Where(array( 'soh.HISTORY_ID>=' => $id,  'soh.ORDER_ID' => $order_id  ))
        .$sql->OrderBy(array('sosh.TASK_ID'));

        $res = $data->sql->query( $qry );
        $Steps = $OrderInfo = array();
        $job_chain='UNKNOWN ?';
        while ($line = $data->sql->get_next($res)) {
            $scheduler_id = $line['SPOOLER_ID'];
            $chain_id = $scheduler_id.'/'.$line['JOB_CHAIN'];            

            $line['START_TIME']=$date->ShortDate( $date->Date2Local($line['START_TIME'],$scheduler_id));
            $line['END_TIME']=$date->ShortDate( $date->Date2Local($line['END_TIME'],$scheduler_id));
            
            // Ordres
            $order = $line['ORDER_ID'];
            $order_id = $chain_id.'/'.$line['ORDER_ID'];

            $step_id = $chain_id.'/'.$line['STATE'];                    
            $Steps[$step_id] = $line;

            $job_chain = $line['JOB_CHAIN'];
            $OrderInfo[$order_id] = $line;             
        }

        $svg = "digraph arii {\n";
        $svg .= "fontname=arial\n";
        $svg .= "fontsize=12\n";
        $svg .= "splines=$splines\n";
        $svg .= "randkir=$rankdir\n";
        $svg .= "node [shape=plaintext,fontname=arial,fontsize=8]\n";
        $svg .= "edge [shape=plaintext,fontname=arial,fontsize=8]\n";
        $svg .= "bgcolor=transparent\n";

        // Dessin des étapes
        $last = '';
        $etape=0;
        foreach ($Steps as $step_id=>$line) {
            
            $s='/'.$line['JOB_CHAIN'].'/'.$line['STATE'];
            
            $svg .= $this->Node($line);
            $Done[$s]=1; 
            
            if ($last !='') 
                $svg .= "\"$last\" -> \"$s\" [label=$etape,color=$color]\n";
            // On relie avec le noeud précédent
            // donc la couleur est pour le prochain lien
            if (!isset($line['ERROR'])) {
                $color= "grey";
            }
            elseif ($line['ERROR']==0 )
                $color= "green";
            else 
                $color= "red";
                
            $last = $s;
            $etape++;
        }
        $svg .= $this->Order($OrderInfo[$order_id]);
        $svg .= '"O.'.$OrderInfo[$order_id]['ORDER_ID'].'" -> "META:'.$OrderInfo[$order_id]['CURRENT_STATE'].'" [style=dashed]'."\n";        

        $current = '/'.$job_chain.'/'.$OrderInfo[$order_id]['STATE'];
        if (!isset($Done[$current])) {       
            $svg .= "\"$current\" [shape=record,color=$color,style=filled,fillcolor=\"".$this->ColorNode($current,$OrderInfo[$order_id]['ERROR'],$OrderInfo[$order_id]['END_TIME'])."\"]\n";    
            // on le relie au dernier
            $svg .= "\"$last\" -> \"$current\" [label=$etape,shape=ellipse,color=$color]\n";
        }
        else {
            $svg .= "\"$last\" -> \"META:".$OrderInfo[$order_id]['CURRENT_STATE']."\" [label=$etape,shape=ellipse,color=$color]\n";
        }

        // Schema de base 
        $cache = $tmp.'/'.$scheduler_id.',job_chains,job_commands.'.$scheduler_id.'.xml';
        $I =  @stat( $cache );
        $modif = $I[9];
        $SOS = $this->container->get('arii_jid.sos');
        if ((time() - $I[9])>300) {            
            $cmd = '<show_state what="job_chains,job_commands"/>';
            $xml = $SOS->Command($scheduler_id,$cmd, 'xml');
            file_put_contents($cache, $xml);
        }
        else {
            $xml = file_get_contents($cache);          
        }
        $result = $SOS->xml2array($xml,1,'value');
        $JobChains = $result['spooler']['answer']['state']['job_chains']['job_chain'];
        
        // On retrouve la chaine principale
        $n = 0;
        $search = '/'.dirname($job_chain).'/'.$META_CHAIN;
        while (isset($JobChains[$n])) {
            if ($JobChains[$n]['attr']['path']==$search) {
                $find = $n;
                break;
            }
            $n++; 
        }
        
       $svg .= "subgraph \"clusterMETACHAIN\" {\n";
        
        // print_r($JobChains[$n]);
        $MetaChain = $JobChains[$n];
        
        $n = 0;
        while (isset($MetaChain['job_chain_node'][$n]['attr'])) {
            $Infos = $MetaChain['job_chain_node'][$n]['attr'];
            $state = $Infos['state'];
            
            if (isset($Infos['next_state']))
                $next = $Infos['next_state'];
            else 
                $next = '';
            if (isset($Infos['error_state']))
                $error = $Infos['error_state'];
            else
                $error = '';
            
            if (isset($Infos['job_chain'])) {
                $chain = $Infos['job_chain'];
                $Node[$state] = $chain;
                $svg .= $this->Chain($scheduler_id, $chain, $state, $order_id, $Steps, $JobChains, $next, $error );  
            }
            else {
                $Node[$state] = $state;
            }
            $n++;
        }
        
        // Noeuds de fin
//        $svg .= '"META:('.$next.")\"\n";
//        $svg .= '"META:'.$error."\" [label=$error]\n";
            
        $svg .= 'label="'.$META_CHAIN."\"\n";                                        
        $svg .= "}\n";

        $svg .= "}\n"; // fin de graph

        $tmpfile = $tmp.'/arii.dot';
/*
        print "<pre>$svg</pre>";
        exit();
 */
        file_put_contents($tmpfile, $svg);

      $cmd = '"'.$this->graphviz_dot.'" "'.$tmpfile.'" -T '.$output;
/*
print $cmd;
print `$cmd`;
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

    private function Chain( $scheduler_id, $job_chain, $STATE, $order_id, $Steps, $JobChains, $next, $error ) {

        $svg = "subgraph \"cluster$STATE\" {\n";
        $svg .= "style=filled;\n";
        $svg .= "\"META:$STATE\" [label=$STATE;shape=ellipse;color=black]\n";
        
        $sql = $this->container->get('arii_core.sql');
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
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
        
        $Done = array(); // Noeuds traités

        $n = 0;
        $search = $job_chain;
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
            $svg .= '"META:'.$STATE.'" -> "'.$job_chain.'/'.$Node[$n]['attr']['state']."\" [color=grey;style=dotted]\n";
            while (isset($Node[$n]['attr'])) {
                if (isset($Node[$n]['attr']['job'])) {
                    $svg .= '"'.$job_chain.'/'.$Node[$n]['attr']['state']."\"\n";
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
        
        $svg .=   '"'.$last_next.'" -> "META:'.$next.'" [color=green,style=dashed]'."\n";
        $svg .=   '"'.$last_error.'" -> "META:'.$error.'" [color=red,style=dashed]'."\n";

        $svg .= '"META:'.$next."\" [label=\"$next\",shape=ellipse,color=green]\n";
        $svg .= '"META:'.$error."\" [label=\"$error\",shape=ellipse,color=red]\n";
        
    return $svg;
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
    private function Node($Infos=array()) {
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
    
    private function Order($Infos=array()) {
        if ($Infos['ORDER_END_TIME']=='') {
            $color='#ffffcc';
        }
        elseif ($Infos['ERROR']) {
            $color='#fbb4ae';
        }
        else {
            $color = "#ccebc5";        
        }
        
        $tools = $this->container->get('arii_core.tools');
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

}
