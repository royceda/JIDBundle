<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrdersController extends Controller
{
    protected $ColorStatus = array( 
        "SUCCESS" => "#ccebc5",
        "READY" => "#ccebc5",
        "TRUE" => "#ccebc5",
        "SUSPENDED" => "red",
        "CHAIN STOP." => "red",
        "SPOOLER STOP." => "red",
        "SPOOLER PAUSED" => "#fbb4ae",
        "NODE STOP." => "red",
        "NODE SKIP." => "#ffffcc",
        "JOB STOP." => "#fbb4ae",        
        "SETBACK" => "lightsalmon",
        "RUNNING" => "#ffffcc",
        "ERROR" => "#fbb4ae",
        "WARNING" => "#fbb4ae",
        "FAILURE" => "#fbb4ae",
        "FALSE" => "#fbb4ae",
        "ENDED" => "lightblue",
        "ON REQUEST" => "lightblue",
        "FATAL" => '#fbb4ae'
        );

    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../bundles/ariicore/images/wa');          
    }

    public function indexAction()
    {
      return $this->render('AriiJIDBundle:Orders:index.html.twig' );
    }

    public function folder_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:folder_toolbar.xml.twig',array(), $response );
    }

    public function grid_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:grid_toolbar.xml.twig',array(), $response );
    }

    public function formAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $this->render('AriiJIDBundle:Orders:form.json.twig',array(), $response );
    }

    public function form2Action()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:form.xml.twig',array(), $response );
    }

    public function form_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:form_toolbar.xml.twig',array(), $response );
    }

    public function chain_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:chain_toolbar.xml.twig',array(), $response );
    }

    public function grid_menuAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:grid_menu.xml.twig',array(), $response );
    }

    public function treeAction() {
        exit();
        // en attendant le cache
        $request = Request::createFromGlobals();
        $stopped = $request->get('stopped');
        
        $folder = 'live';
        // $this->syncAction($folder);
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
   /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_chain}'   => 'sh.JOB_CHAIN',
//            '{order}'   => 'sh.ORDER_ID',
            '{start_time}' => 'sh.START_TIME' );

        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');

        $qry = $sql->Select(array('sh.HISTORY_ID','sh.SPOOLER_ID','sh.JOB_CHAIN','sh.START_TIME','sh.END_TIME','sh.STATE' ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_CHAIN','sh.START_TIME desc'));  
        
        $res = $data->sql->query( $qry );
        $Info = array();
        
        while ( $line = $data->sql->get_next($res) ) {
            $id  =  $line['HISTORY_ID'];
            $dir = "/".$line['SPOOLER_ID'].'/'.dirname($line['JOB_CHAIN']);
            if (substr($line['STATE'],0,1)=='!') {
                if (isset($Info[$dir]['errors'])) 
                    $Info[$dir]['errors']++;
                else 
                    $Info[$dir]['errors']=1;
            }
            // On ccompte les erreurs
            $key_files[$dir] = $dir;
        }
        
        // Prend on en compte les suspended ?
            $Fields = array (
                '{spooler}'    => 'sh.SPOOLER_ID', 
                '{job_chain}'   => 'sh.JOB_CHAIN' /*,
                '{order}'   => 'sh.ID'*/ );
            $qry = $sql->Select(array('sh.SPOOLER_ID','sh.JOB_CHAIN' ))
                    .$sql->From(array('SCHEDULER_ORDERS sh'))
                    .$sql->Where($Fields);  

              $res = $data->sql->query( $qry );
              while ( $line = $data->sql->get_next($res) ) {
                $dir = '/'.$line['SPOOLER_ID'].'/'.dirname($line['JOB_CHAIN']);
                if (isset($Info[$dir]['sus'])) 
                    $Info[$dir]['stopped']++;
                else 
                    $Info[$dir]['stopped']=1;
                
                // On ccompte les erreurs
                if ($stopped=='true') {
                    $key_files[$dir] = $dir;
                }            
            }
        
        // print_r($Info);
        # On remonte les erreurs
        foreach (array_keys($Info) as $dir) {
            // On calcule le nombre
            $n = 0;
            if (isset($Info[$dir]['errors']))
                $n += $Info[$dir]['errors'];
            if (isset($Info[$dir]['stopped']))
                $n += $Info[$dir]['stopped'];
            // print "(($n))";
            // si c'est superieur a 0, on le remonte
            if ($n>0) {
                $Path = explode('/',$dir);
                array_shift($Path);
                $dir = '';
                foreach ($Path as $p) {
                    $dir .= '/'.$p;
                    if (isset($Info[$dir]['total']))
                        $Info[$dir]['total'] += $n;
                    else 
                        $Info[$dir]['total'] = $n;
                }
            }            
        }
        ksort($Info);
        /*
         *     print_r($Info);
            exit();
         */
        
        $tools = $this->container->get('arii_core.tools');
        $tree = $tools->explodeTree($key_files, "/");
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<tree id='0'>\n";
        
        $list .= $this->Folder2XML( $tree, '', $Info );
        $list .= "</tree>\n";
        $response->setContent( $list );
        return $response;
    }
 
   function Folder2XML( $leaf, $id = '', $Info ) {
            $return = '';
            if (is_array($leaf)) {
                    foreach (array_keys($leaf) as $k) {
                            $Ids = explode('/',$k);
                            $here = array_pop($Ids);
                            $i  = "$id/$k";
                            # On ne prend que l'historique
                            if (isset($Info[$i]['stopped']) and (isset($Info[$i]['errors']))) {
                                $return .= '<item style="background-color: red;" id="'.$i.'" text="'.basename($i).' ['.($Info[$i]['errors']+$Info[$i]['stopped']).']" im0="folderOpen.gif">';
                            }
                            elseif (isset($Info[$i]['errors'])) {
                                $return .= '<item style="background-color: #fbb4ae;" id="'.$i.'" text="'.basename($i).' ['.$Info[$i]['errors'].']" im0="folderOpen.gif">';
                            }
                            elseif (isset($Info[$i]['stopped'])) {
                                $return .= '<item style="background-color: red;" id="'.$i.'" text="'.basename($i).' ['.$Info[$i]['stopped'].']" im0="folderOpen.gif">';
                            }
                            elseif (isset($Info[$i]['total'])) {
                                $return .= '<item id="'.$i.'" text="'.basename($i).' ['.$Info[$i]['total'].']" im0="folderOpen.gif"  open="1">';
                            }
                            else {
                                $return .=  '<item style="background-color: #ccebc5;" id="'.$i.'" text="'.basename($i).'" im0="folderClosed.gif">';
                            }
                           $return .= $this->Folder2XML( $leaf[$k], $id.'/'.$k, $Info);
                           $return .= '</item>';
                    }
            }
            return $return;
    }

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:toolbar.xml.twig',array(), $response );
    }

    public function menuAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Orders:menu.xml.twig',array(), $response );
    }

    public function docAction()
    {
        $request = Request::createFromGlobals();
        $id  = $request->query->get( 'id' );

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            'HISTORY_ID'            => $id );

        $qry = $sql->Select(array('*')) 
               .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array('SPOOLER_ID'));
        
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        $Infos['ID'] = $id;
        // print_r($Infos);
        $url = $this->container->getParameter('doc_job');
        while (($d=strpos($url,'[['))>0) {
            $e = strpos($url,']]',$d);
            $k = substr($url,$d+2,$e-$d-2);
            if (isset($Infos[$k])) {
                $v = $Infos[$k];
            }
            else {
                $v = "($k)";
            }
            $url = substr($url,0,$d).$v.substr($url,$e+2);
        }
        header('Location: '.$url);
        exit();
    }

   public function purgeAction( )
    {
        $request = Request::createFromGlobals();
        $Ids = explode('#',$request->get('order_id'));
        $id = $Ids[0];
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        // on commence par supprimer l'historique 
        $qry = $sql->Delete(array('SCHEDULER_HISTORY'))
              .' where ID in ('
                .$sql->Select(array('TASK_ID'))
                .$sql->From(array('SCHEDULER_ORDER_STEP_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id))
              .')';
        $res = $data->sql->query( $qry );
        if ($res>0)
            print $this->get('translator')->trans('Job purged');
        else 
            print $this->get('translator')->trans('ERROR !');
        
        print "<br/>";
        $qry = $sql->Delete(array('SCHEDULER_ORDER_STEP_HISTORY'))
                .$sql->Where(array('HISTORY_ID' => $id));
        $res = $data->sql->query( $qry );
        if ($res>0)
            print $this->get('translator')->trans('Steps purged');
        else 
            print $this->get('translator')->trans('ERROR !');

        print "<br/>";
        $qry = $sql->Delete(array('SCHEDULER_ORDER_HISTORY'))
              .$sql->Where(array('HISTORY_ID' => $id));        
        $res = $data->sql->query( $qry );
        if ($res>0)
            print $this->get('translator')->trans('Order purged');
        else 
            print $this->get('translator')->trans('ERROR !');
        
        exit();
    }

    public function historyAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        
        $qry = "select h.SPOOLER_ID,h.ORDER_ID,h.JOB_CHAIN 
        from SCHEDULER_ORDER_HISTORY h
        where h.HISTORY_ID=$id";
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        return $this->render('AriiJIDBundle:Orders:history.html.twig', 
            array('id' => $id, 'spooler' => $Infos['SPOOLER_ID'], 'order' => $Infos['ORDER_ID'], 'job_chain' => $Infos['JOB_CHAIN'] ) );
    }

    public function selected_ordersAction()
    {
        $request = Request::createFromGlobals();
        $ids = $request->get('ids');
        $ids_array = array();
        foreach (explode(',', $ids) as $id)
        {
            if (substr($id, 0,3)=="OR#")
            {
                array_push($ids_array, substr($id,3));
            }
        }
        $history_ids = implode(',', $ids_array);
		
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');
        
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('HISTORY_ID','ORDER_ID','JOB_CHAIN'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                ." WHERE history_id in ($history_ids) ORDER BY history_id DESC";
        
        $data->event->attach("beforeRender",array($this,"setStatus"));
        $data->render_sql($qry,"history_id","order_id,job_chain,status");
    }
	
	public function setStatus($row)
    {
        $row->set_value("status","ready");
    }
	
    public function chartsAction()
    {
        $session = $this->container->get('arii_core.session');
        
        // Une date peut etre passe en get
        $request = Request::createFromGlobals();
        if ($request->query->get( 'ref_date' )) {
            $ref_date   = $request->query->get( 'ref_date' );
            $session->setRefDate( $ref_date );
        } else {
            $ref_date   = $session->getRefDate();
        }
        $Timeline['ref_date'] = $ref_date;
        
        $past   = $session->getRefPast();
        $future = $session->getRefFuture();
        
        // On prend 24 fuseaux entre maintenant et le passe
        // on trouve le step en minute
        $step = ($future-$past)*2.5; // heure * 60 minutes / 24 fuseaux
        $Timeline['step'] = $step;
    
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['js_date'] = $year.','.($month - 1).','.$day;
        
        $refresh = $session->GetRefresh();
        
        // Liste des spoolers pour cette plage
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME' );

        $qry = 'SELECT DISTINCT SPOOLER_ID 
                FROM SCHEDULER_HISTORY
                where '.$sql->History($Fields).'
                ORDER BY SPOOLER_ID';
        
        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        
        return $this->render('AriiJIDBundle:Orders:charts.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }
    
    public function toolbar_activitiesAction()
    {
        return $this->render('AriiJIDBundle:Orders:toolbar_activities.xml.twig');
    }

    public function toolbar_timelineAction()
    {
        return $this->render('AriiJIDBundle:Orders:toolbar_timeline.xml.twig');
    }
    
    public function listAction()
    {
        return $this->render('AriiJIDBundle:Orders:list.html.twig');
    }

    public function activitiesAction()
    {
        return $this->render('AriiJIDBundle:Orders:activities.html.twig');
    }
    
    public function timelineAction()
    {
        $session = $this->container->get('arii_core.session');
        
        // Une date peut etre passe en get
        $request = Request::createFromGlobals();
        if ($request->query->get( 'ref_date' )) {
            $ref_date   = $request->query->get( 'ref_date' );
            $session->setRefDate( $ref_date );
        } else {
            $ref_date   = $session->getRefDate();
        }
        $Timeline['ref_date'] = $ref_date;
        
        $past   = $session->getRefPast();
        $future = $session->getRefFuture();
        
        // On prend 24 fuseaux entre maintenant et le passe
        // on trouve le step en minute
        $step = ($future-$past)*2.5; // heure * 60 minutes / 24 fuseaux
        $Timeline['step'] = $step;
        $Timeline['step'] = 60;
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['js_date'] = $year.','.($month - 1).','.$day;
        $Timeline['start'] = 0;
        $refresh = $session->GetRefresh();
        
        // Liste des spoolers pour cette plage
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME' );

        $qry = $sql->Select(array('DISTINCT SPOOLER_ID')) 
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('SPOOLER_ID'));

        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        return $this->render('AriiJIDBundle:Orders:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function pieAction($history_max=0,$nested=false,$only_warning=true)
    {
        $request = Request::createFromGlobals();
        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        $nested = $request->get('chained');
        $only_warning = $request->get('only_warning');

        $history = $this->container->get('arii_jid.history');
        $Orders = $history->Orders($history_max,$nested,$only_warning);
        
        foreach ($Orders as $k=>$order) {
            $status = $order['STATUS'];
            if (isset($Status[$status])) {
                $Status[$status]++;
            }
            else {
                $Status[$status]=1;
            }
        }
        
        $pie = '<data>';
        if (!isset($Status['SUCCESS'])) {
            $Status['SUCCESS']=0;
        }
        ksort($Status);
        foreach ($Status as $k=>$v) {
            $pie .= '<item id="'.$k.'"><STATUS>'.str_replace(' ','_',$k).'</STATUS><JOBS>'.$v.'</JOBS><COLOR>'.(isset($this->ColorStatus[$k])?$this->ColorStatus[$k]:'black').'</COLOR></item>';
        }
        $pie .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $pie );
        return $response;
    }

    public function barAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );

$qry = $sql->Select(array('soh.START_TIME','sosh.ERROR'))
        .$sql->From(array('SCHEDULER_ORDER_HISTORY soh')) 
        .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
        .$sql->Where($Fields) 
        .$sql->OrderBy(array('soh.START_TIME'));

        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $day = substr($line['START_TIME'],8,5);
            $Days[$day]=1;
            if ($line['ERROR']==0) {
                if (isset($HS[$day])) 
                    $HS[$day]++;
                else $HS[$day]=1;
            }
            else {
                if (isset($HF[$day])) 
                    $HF[$day]++;
                else $HF[$day]=1;
            }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= "<data>";
        if (isset($Days)) {
            foreach($Days as $i=>$v) {
                if (!isset($HS[$i])) $HS[$i]=0;
                if (!isset($HF[$i])) $HF[$i]=0;
                $bar .= '<item id="'.$i.'"><HOUR>'.substr($i,-2).'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE></item>';
            }
        }
        $bar .= "</data>";
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }

    public function gridAction($history_max=0,$nested=false,$only_warning=true,$sort='last')
    {
        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        $nested = $request->get('chained');
        $only_warning = $request->get('only_warning');
        $sort = $request->get('sort');

        $history = $this->container->get('arii_jid.history');
        $Orders = $history->Orders($history_max,$nested,$only_warning,$sort);
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .=  '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        
        // ksort($Orders);
        foreach ($Orders as $k=>$line) {
            $spooler = $line['SPOOLER_ID'];
            $status = $line['STATUS'];
             
            if (isset($this->ColorStatus[$status])) $color=$this->ColorStatus[$status];
                else $color='yellow';
            $list .=  '<row id="'.$line['HISTORY_ID'].'"  style="background-color: '.$color.';">';
            
            # Cell color pour identifier le point de blocage
            $cellcolor='';
            $list .=  '<cell'.$cellcolor.'>'.$line['SPOOLER_ID'].'</cell>';
            $cellcolor='';
            $list .=  '<cell>'.$line['FOLDER'].'</cell>';
            if ($status == 'CHAIN STOP.') $cellcolor=' style="background-color: red;"';
            $list .=  '<cell'.$cellcolor.'>'.$line['NAME'].'</cell>';
            $cellcolor='';
            $list .=  '<cell>'.$line['ORDER_ID'].'</cell>';
            $cellcolor='';
            if ($status == 'NODE STOP.') $cellcolor=' style="background-color: red;"';
            if ($status == 'NODE SKIP.') $cellcolor=' style="background-color: orange;"';
            $list .=  '<cell'.$cellcolor.'>'.$line['STATE'].'</cell>';
            $list .=  '<cell>'.$status.'</cell>';
            $list .=  '<cell>'.$line['START_TIME'].'</cell>';
            $list .=  '<cell>'.$line['END_TIME'].'</cell>';
            $list .=  '<cell>'.$line['NEXT_TIME'].'</cell>';
            $list .=  '<cell>'.$line['STATE_TEXT'].'</cell>';
            $list .=  '</row>';
        }
        
        $list .=  "</rows>\n";
        $response->setContent( $list );
        return $response;        
    }

    public function gridFullAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $tools = $this->container->get('arii_core.tools');   
        
        $date = $this->container->get('arii_core.date');
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .=  '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        $Infos = array();
        
        $sql = $this->container->get('arii_core.sql');
        
        // on commence par le scheduler
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID' );
        $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING','IS_PAUSED'))
               .$sql->From(array('SCHEDULER_INSTANCES'));

        $Spoolers = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
                # Creation des icones
                $spooler = $line['SPOOLER_ID'];
                $Spoolers[$spooler] = $line;
        } 

        // On regarde les chaines stoppés
        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            'STOPPED'  => 1 );
        
        $qry = $sql->Select( array('SPOOLER_ID','PATH as JOB_CHAIN') )
                .$sql->From( array('SCHEDULER_JOB_CHAINS') )
                .$sql->Where( $Fields );
        $res = $data->sql->query( $qry );
        $nb = 0;
        $StopChain = array();
        while ($line = $data->sql->get_next($res)) {
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $StopChain[$cn]=1;
        }

        // On regarde les nodes
        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            'action'  => '(!null)' );
        $qry = $sql->Select( array('SPOOLER_ID','JOB_CHAIN','ORDER_STATE','ACTION') )
                .$sql->From( array('SCHEDULER_JOB_CHAIN_NODES') )
                .$sql->Where( $Fields );
        $res = $data->sql->query( $qry );
        $nb = 0;
        $StopNode = $SkipNode = array();
        while ($line = $data->sql->get_next($res)) {
            $sn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ORDER_STATE'];
            if ($line['ACTION'] == 'next_state') $SkipNode[$sn]=1;
            if ($line['ACTION'] == 'stop') $StopNode[$sn]=1;
        }
        // On regarde les jobs
        // On complete avec les ordres stockés
 /*       $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            'STOPPED'  => 1 );
        $qry = $sql->Select( array('SPOOLER_ID','PATH as JOB') )
                .$sql->From( array('SCHEDULER_JOBS') )
                .$sql->Where( $Fields );
        $res = $data->sql->query( $qry );
        $nb = 0;
        $StopJob = array();
        while ($line = $data->sql->get_next($res)) {
            $jn = $line['SPOOLER_ID'].'/'.$line['JOB'];
            $StopJob[$jn]=1;
        }
*/
        // Historique des ordres
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'   => 'soh.JOB_CHAIN',
            '{start_time}' => 'soh.START_TIME' );

        $qry = $sql->Select(array('soh.HISTORY_ID','soh.TITLE','soh.SPOOLER_ID','soh.ORDER_ID','soh.JOB_CHAIN','soh.STATE as ORDER_STATE',
                    'sosh.TASK_ID','sosh.STATE','sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT','sosh.STEP',
                    'sh.JOB_NAME'))  
                  .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                  .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                  .$sql->LeftJoin('SCHEDULER_HISTORY sh',array('sosh.TASK_ID','sh.ID'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('sosh.START_TIME desc','sosh.END_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
//            $jn = $line['SPOOLER_ID'].'/'.$line['JOB'];
            $sn = $cn.'/'.$line['STATE'];
            $id = $cn.'/'.$line['ORDER_ID'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                $Nb[$id]=0;
            }
            // Chain stoppée
            if (isset($StopChain[$cn])) {
                $status = 'CHAIN STOP.';
            }
/*            elseif (isset($StopJob[$jn])) {
                $status = 'JOB STOP.';
            }
*/          elseif (isset($StopNode[$sn])) {
                $status = 'NODE STOP.';
            }
            elseif (isset($SkipNode[$sn])) {
                $status = 'NODE SKIP.';
            }
            elseif ($line['END_TIME']!='') {
                if ($line['ERROR']>0) {
                    if (substr($line['STATE'],0,1)=='!') {
                        $status = 'FATAL';
                    }
                    else {
                        $status = 'ERROR';
                    }
                }
                else {
                    $status = 'SUCCESS';  
                }
            }
            else {
                $status = 'RUNNING';
            }
            
            if ($Nb[$id]>$history) continue;
                        
            $line['DBID'] = $line['HISTORY_ID']; 
            $line['status'] = $status;
            if ($line['ERROR_TEXT']!='') {
                $line['information'] = $line['ERROR_TEXT'];
            }
            else {
                $line['information'] = $line['TITLE'];
            }
            $Infos[$id] = $line;
        }
        
        // On complete avec les ordres stockés
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            '{order_id}'  => 'ID',
/*          'created_time' => 'CREATED_TIME',
*/          '{start_time}' => 'MOD_TIME'
                );
        
        $qry = $sql->Select( array('SPOOLER_ID','JOB_CHAIN','ID as ORDER_ID','PRIORITY','STATE as ORDER_STATE','STATE_TEXT','TITLE','CREATED_TIME','MOD_TIME','ORDERING','INITIAL_STATE','ORDER_XML' ) )
                .$sql->From( array('SCHEDULER_ORDERS') )
                .$sql->Where( $Fields )
                .$sql->OrderBy( array('ORDERING desc') );
        //when we want to store the planned orders, we also need to store the job chains which the planned orders belong.
        $res = $data->sql->query( $qry );
        $nb = 0;
        while ($line = $data->sql->get_next($res)) {
            $CI = explode('/',$line['JOB_CHAIN']);
            $chain = array_pop($CI);
            $dir = implode('/',$CI);
            
            $on = $line['SPOOLER_ID'].'/'.$dir.'/'.$line['ORDER_ID'];
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            
            $id = $cn.'/'.$line['ORDER_ID'];
            
            // on pense a proteger les ORDER_ID
            $jn = $cn.'/'.str_replace('/','£',$line['ORDER_ID']);

            $order_status = 'ACTIVATED';
            if ($line['ORDER_XML']!=null)
            {
                if (gettype($line['ORDER_XML'])=='object') {
                    $order_xml = $tools->xml2array($line['ORDER_XML']->load());
                }
                else {
                    $order_xml = $tools->xml2array($line['ORDER_XML']);
                }
                $setback = 0; $setback_time = '';
                if (isset($order_xml['order_attr']['suspended']) && $order_xml['order_attr']['suspended'] == "yes")
                {
                    $order_status = "SUSPENDED";
                }
                elseif (isset($order_xml['order_attr']['setback_count']))
                {
                    $order_status = "SETBACK";
                    $setback = $order_xml['order_attr']['setback_count'];
                    $setback_time = $order_xml['order_attr']['setback'];
                }
                $next_time = '';
                if (isset($order_xml['order_attr']['start_time'])) {
                    $next_time = $order_xml['order_attr']['start_time'];
                }
                $at = '';
                if (isset($order_xml['order_attr']['at'])) {
                    $at = $date->Date2Local($order_xml['order_attr']['at'],$line['SPOOLER_ID']);
                }
                $hid = 0;
                if (isset($order_xml['order_attr']['history_id'])) {
                    $hid = $order_xml['order_attr']['history_id'];
                }
            }
            
            $line['DBID'] = 'O:'.$jn;

            if ($at==''){
                if (isset($Infos[$id]['START_TIME']))
                   $at = $Infos[$id]['START_TIME']; 
            }
            $line['START_TIME'] = $at;
            if (isset($Infos[$id]['END_TIME']))
                $line['END_TIME'] = $Infos[$id]['END_TIME'];
            else
                $line['END_TIME'] = '';
                
            $line['STEP'] = '';
            $line['status'] = $order_status;
            $line['information'] = $line['STATE_TEXT'];
            
            // Si c'est pas déja traité et statut <> ACTIVATED
            if (!isset($Nb[$id]) or ($order_status!='ACTIVATED'))          
                $Infos[$id] = $line;
        }

        $Keys = array_keys($Infos);
        sort($Keys);
        
        foreach ($Keys as $k) {
            $line = $Infos[$k];
            list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],$line['END_TIME'],'', $line['SPOOLER_ID'], false  );
            
            $spooler = $line['SPOOLER_ID'];            
            $status = $line['status'];
            if ($Spoolers[$spooler]['IS_RUNNING']!=1) {
                if ($Spoolers[$spooler]['IS_PAUSED']==1) 
                    $status = 'SPOOLER PAUSED';
                else
                    $status = 'SPOOLER STOP.';
            }

            if (isset($this->ColorStatus[$status])) $color=$this->ColorStatus[$status];
                else $color='yellow';
            $list .=  '<row id="'.$line['DBID'].'"  style="background-color: '.$color.';">';
            if (isset($line['TASK_ID']))
                $list .=  '<userdata name="TASK_ID">'.$line['TASK_ID'].'</userdata>';
            
            # Cell color pour identifier le point de blocage
            $cellcolor='';
            if ($status == 'SPOOLER STOP.') $cellcolor=' style="background-color: red;"';            
            if ($status == 'SPOOLER PAUSED') $cellcolor=' style="background-color: orange;"';            
            $list .=  '<cell'.$cellcolor.'>'.$line['SPOOLER_ID'].'</cell>';
            $cellcolor='';
            if ($status == 'CHAIN STOP.') $cellcolor=' style="background-color: red;"';
            $list .=  '<cell'.$cellcolor.'>'.$line['JOB_CHAIN'].'</cell>';
            $cellcolor='';
            $list .=  '<cell>'.$line['ORDER_ID'].'</cell>';
            $cellcolor='';
            $list .=  '<cell>'.$line['STEP'].'</cell>';  
            if ($status == 'NODE STOP.') $cellcolor=' style="background-color: red;"';
            if ($status == 'NODE SKIP.') $cellcolor=' style="background-color: orange;"';
            if (isset($line['ORDER_STATE']))
                $list .=  '<cell'.$cellcolor.'>'.$line['ORDER_STATE'].'</cell>';
            else 
                $list .=  '<cell/>';
            $list .=  '<cell>'.$status.'</cell>';
            $list .=  '<cell>'.$start.'</cell>';
            $list .=  '<cell>'.$end.'</cell>';
            $list .=  '<cell>'.$line['information'].'</cell>';
            if (isset($line['JOB_NAME'])) {
                $cellcolor='';
                // on se met en relatif par rapport à la chaine ?
                $list .=  '<cell'.$cellcolor.'>'.basename($line['JOB_NAME']).'</cell>';  
            }
            else 
                $list .= '<cell/>';
            $list .=  '</row>';
        }
        
        $list .=  "</rows>\n";
        $response->setContent( $list );
        return $response;        
    }

    public function timeline_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('scheduler');

       $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->get('ref_date');

        $sql = $this->container->get('arii_core.sql');
        
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'   => 'soh.JOB_CHAIN',
            '{error}'      => 'sosh.ERROR',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );

          $qry = 'SELECT soh.HISTORY_ID as ID, soh.SPOOLER_ID as section_id, soh.JOB_CHAIN, soh.START_TIME, soh.END_TIME, sosh.ERROR, "green" as color  
                  FROM SCHEDULER_ORDER_HISTORY soh
                  left join SCHEDULER_ORDER_STEP_HISTORY sosh
                  on soh.HISTORY_ID=sosh.HISTORY_ID
                  where '.$sql->Filter($Fields).' order by soh.SPOOLER_ID, soh.JOB_CHAIN,soh.START_TIME';  

          $data->event->attach("beforeRender", array( $this, "color_rows") );
          $data->render_sql($qry,"ID","START_TIME,END_TIME,JOB_CHAIN,color,section_id");
    }
    
    function color_rows($row){
        if ($row->get_value('END_TIME')=='') {
            $row->set_value("color", 'orange');
            $row->set_value("END_TIME", $this->ref_date );
        }
        elseif ($row->get_value('ERROR')>0) {
            $row->set_value("color", 'red');
        }
    }    

/******************************************************************/
    public function list_xmlAction($full=false,$activated=true)
    {
        $request = Request::createFromGlobals();	
        if ($request->get('activated')=='true') {
            $activated = true;
        }
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $session = $this->container->get('arii_core.session');

        $this->tools = $this->container->get('arii_core.tools');

        $sql = $this->container->get('arii_core.sql');
        $session = $this->container->get('arii_core.session');

        $tools = $this->container->get('arii_core.tools');   
        // on commence par le scheduler
        $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING','IS_PAUSED'))
               .$sql->From(array('SCHEDULER_INSTANCES'));

        $Spoolers = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
                # Creation des icones
                $spooler = $line['SPOOLER_ID'];
                $Spoolers[$spooler]['hostname'] = $line['HOSTNAME'];
                $Spoolers[$spooler]['port'] = $line['TCP_PORT'];
                $Spoolers[$spooler]['start_time'] = $line['START_TIME'];
                $Spoolers[$spooler]['is_running'] = $line['IS_RUNNING'];
                $Spoolers[$spooler]['is_paused']  = $line['IS_PAUSED'];
        }    
        
        $key_files = array();
        $ChainStopped = array();
        $JobStopped = array();
/* On stocke les états */
        $Fields = array (
            '{spooler}'    => 'sh.SPOOLER_ID',
            '{job_name}'   => 'sh.PATH' );

            $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                    .$sql->From(array('SCHEDULER_JOBS sh'))
                    .$sql->Where($Fields)
                    .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));
            
            $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
             $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
             if ($line['STOPPED']=='1' ) {
                 $JobStopped[$jn] = true;
             }
        }

        $ActionStep = array();
// Etats skippé
// A voir ce qu'on peut en faire ici        
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'PATH' );

        $qry = $sql->Select(array('SPOOLER_ID','PATH as JOB_CHAIN','STOPPED'))
                .$sql->From(array('SCHEDULER_JOB_CHAINS'))
                .$sql->Where($Fields);

        $Chain = $ChainStopped = array();
        $res = $data->sql->query( $qry );
        $nb = 0; 
        while ($line = $data->sql->get_next($res)) {
                # Creation des icones
                $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
                $cn = str_replace('//','/',$cn); 
                
                if ($line['STOPPED']==1) {
                    $ChainStopped[$cn] = 'STOPPED';
                }
        }

        $Fields = array (
            '{spooler}'    => 'sjcn.SPOOLER_ID',
            '{job_chain}'  => 'sjcn.JOB_CHAIN' );
        
        $qry = $sql->Select(array('sjcn.SPOOLER_ID','sjcn.JOB_CHAIN','sjcn.ORDER_STATE as STATE','sjcn.ACTION'))
                .$sql->From(array('SCHEDULER_JOB_CHAIN_NODES sjcn'))
                .$sql->where($Fields);

        $ActionStep = array();
        $res = $data->sql->query($qry );
        $nb = 0; 
        while ($line = $data->sql->get_next($res)) {
            # Creation des icones
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $cn = str_replace('//','/',$cn); 

            $sn = $cn.'/'.$line['STATE'];

            if(isset($line['ACTION']) && $line['ACTION']!= null){
                $ActionStep[$sn] = $line['ACTION'];
            }
        }

// Ordres en cours
// A voir ce qu'on peut en faire ici  
$Planned = $OrderStatus = array();
if ($activated) {
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            '{order_id}'  => 'ID',
/*          'created_time' => 'CREATED_TIME',
            'mod_time' => 'MOD_TIME'
*/                );

        $qry = $sql->Select( array('SPOOLER_ID','JOB_CHAIN','ID as ORDER_ID','PRIORITY','STATE','STATE_TEXT','TITLE','CREATED_TIME','MOD_TIME','ORDERING','INITIAL_STATE','ORDER_XML' ) )
                .$sql->From( array('SCHEDULER_ORDERS') )
                .$sql->Where( $Fields )
                .$sql->OrderBy( array('ORDERING desc') );
        //when we want to store the planned orders, we also need to store the job chains which the planned orders belong.
        $res = $data->sql->query( $qry );
        $nb = 0;
        while ($line = $data->sql->get_next($res)) {
            $CI = explode('/',$line['JOB_CHAIN']);
            $chain = array_pop($CI);
            $dir = implode('/',$CI);
            
            $on = $line['SPOOLER_ID'].'/'.$dir.'/'.$line['ORDER_ID'];
            $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            
            // on pense a proteger les ORDER_ID
            $jn = $cn.'/'.str_replace('/','£',$line['ORDER_ID']);

            $order_status = 'ACTIVATED';
            if ($line['ORDER_XML']!=null)
            {
                if (gettype($line['ORDER_XML'])=='object') {
                    $order_xml = $tools->xml2array($line['ORDER_XML']->load());
                }
                else {
                    $order_xml = $tools->xml2array($line['ORDER_XML']);
                }
                $setback = 0; $setback_time = '';
                if (isset($order_xml['order_attr']['suspended']) && $order_xml['order_attr']['suspended'] == "yes")
                {
                    $order_status = "SUSPENDED";
                }
                elseif (isset($order_xml['order_attr']['setback_count']))
                {
                    $order_status = "SETBACK";
                    $setback = $order_xml['order_attr']['setback_count'];
                    $setback_time = $order_xml['order_attr']['setback'];
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

            $ChainPlan[$cn]['dbid']  = '!'.$line['ORDERING'];
            $ChainPlan[$cn]['chain'] = $line['JOB_CHAIN'];
            $ChainPlan[$cn]['full']  = $cn;
            if (isset($ChainStopped[$cn])) {
                $Chain[$cn]['status'] = $ChainStopped[$cn];
            }
            else 
                $Chain[$cn]['status'] = 'ACTIVE';
            
            $jn = $on;
            $Planned[$jn]['full'] = $jn;
            $Planned[$jn]['dbid']    = $jn;
            $Planned[$jn]['chain']    = $line['JOB_CHAIN'];
            $Planned[$jn]['created']  = $line['CREATED_TIME'];
            $Planned[$jn]['mod_time'] = $line['MOD_TIME'];
            $Planned[$jn]['title']    = $line['TITLE'];
            $Planned[$jn]['initial']  = $line['INITIAL_STATE'];
            $Planned[$jn]['status']   = $order_status;
            $Planned[$jn]['next_time']= $next_time;
            $Planned[$jn]['setback']  = $setback;
            $Planned[$jn]['at'] = $at;
            $Planned[$jn]['history_id'] = $hid;
            $Planned[$jn]['spooler']    = $line['SPOOLER_ID'];
            $key_files[$jn] = $jn;
    }
}

// Ordres executés
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'  => 'JOB_CHAIN',
            '{order_id}'   => 'ORDER_ID',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );
        $qry = $sql->Select(array('sosh.TASK_ID','soh.HISTORY_ID','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.STATE','sosh.ERROR','sosh.ERROR_TEXT',
         'soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.TITLE','soh.STATE as ORDER_STATE','soh.STATE_TEXT','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
         'sh.JOB_NAME','sh.EXIT_CODE',
         'st.PARAMETERS', 'st.TASK_XML'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->LeftJoin('SCHEDULER_HISTORY sh',array('sosh.TASK_ID','sh.ID')) 
                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sosh.TASK_ID','st.TASK_ID')) 
                .$sql->where($Fields)
                .$sql->OrderBy(array('soh.HISTORY_ID desc','sosh.TASK_ID','sosh.STEP'));
        
        $Order = $Task = $GlobalChain = $GlobalOrder =  $NestedChain = $Nb = array();
        $res = $data->sql->query( $qry );
        $nb = 0; 
        $last_step = array(); // dernier heure du step de chaque order
        $order_xml = array();
        $Path = array();
        while ($line = $data->sql->get_next($res)) {
            $CI = explode('/',$line['JOB_CHAIN']);
            $chain = array_pop($CI);
            $dir = implode('/',$CI);

            $on = $line['SPOOLER_ID'].'/'.$dir.'/'.$line['ORDER_ID'];
            $key_files[$on] = $on;
            if (isset($Planned[$on])) {
                $Order[$on] = $Planned[$on];
            }
            $Order[$on]['full'] = $on;
            $Order[$on]['dbid'] = $line['HISTORY_ID'];
            $Order[$on]['title'] = $line['TITLE']; 
            $Order[$on]['state'] = $line['STATE']; 
            $Order[$on]['start_time'] = $line['ORDER_START_TIME']; 
            $Order[$on]['end_time'] = $line['ORDER_END_TIME']; 
            $Order[$on]['spooler'] = $line['SPOOLER_ID'];
            $Order[$on]['state_text'] = $line['STATE_TEXT'];
            
            // D'abord les cas de blocage
            $status = '';
            if (isset($Order[$on]['status'])) {
                if (($Order[$on]['status']=='SUSPENDED') or ($Order[$on]['status']=='SETBACK'))
                    $status = $Order[$on]['status'];
            }
            
            if ($status == '') {
                if ($line['ORDER_END_TIME']=='') {
                    $status = 'RUNNING';
                }
                elseif (substr($line['STATE'],0,1)=='!') {
                    $status = 'FATAL';    
                }
                elseif (substr($line['STATE'],0,1)=='?') {
                    if ($line['ERROR']>0) {
                        $status = 'FALSE';    
                    }
                    else {
                        $status = 'TRUE';    
                    }    
                }
                elseif ($line['ERROR']>0) {
                    $status = 'ERROR';    
                }
                else {
                    $status = 'SUCCESS';    
                }
            }
            $Order[$on]['status'] = $status;
            
            $cn = $on.'/'.$chain;
            $c = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            if (isset($Chain[$c])) {
                $Chain[$cn] = $ChainPlan[$c];
            }
            $Chain[$cn]['full'] = $cn;
            $Chain[$cn]['chain'] = $c;
            $Chain[$cn]['chain_name'] = $chain;
            $Chain[$cn]['dbid'] = $line['HISTORY_ID'];  
            if (isset($ChainStopped[$c])) {
                $Chain[$cn]['status'] = $ChainStopped[$c];
            }
            else 
                $Chain[$cn]['status'] = 'ACTIVE';

            $key_files[$cn] = $cn;
            // print_r($ChainStopped); exit();
            // job en cours
            $jn = $cn.'/'.basename($line['JOB_NAME']);
            $Job[$jn]['full'] = $jn;
            $Job[$jn]['dbid'] = $line['TASK_ID'];
            $Job[$jn]['start_time'] = $line['START_TIME'];
            $Job[$jn]['end_time'] = $line['END_TIME'];
            $Job[$jn]['state'] = $line['STATE'];
            $Job[$jn]['error_text'] = $line['ERROR_TEXT'];
            $Job[$jn]['error'] = $line['ERROR'];
            $Job[$jn]['job_name'] = $line['JOB_NAME'];
            if ($line['END_TIME']=='') {
                $status = 'RUNNING';
            }
            elseif ($line['ERROR']>0) {
                $status = 'ERROR';    
            }
            else {
                $status = 'SUCCESS';    
            }
            $Job[$jn]['status'] = $status;
            $Job[$jn]['job_state'] = 'job_state';
            $Job[$jn]['path'] = 'path';
            $Job[$jn]['spooler'] = $line['SPOOLER_ID'];
            $Job[$jn]['exit'] = $line['EXIT_CODE'];
            
            $key_files[$jn] = $jn;       
        }

        $tree = $tools->explodeTree($key_files, "/");
        header("Content-type: text/xml");
        if ($nb==0) {
            // $this->NoRecord();
        }
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print "<rows>\n";
        print '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        $session = $this->container->get('arii_core.session');
        $States = $session->get('state');
        if (!isset($States['orders'])) $States['orders'] =array();

        $this->Orders2XML( $tree, '', $Order, $Chain, $Task, $Planned, $Spoolers, $Job, $States['orders'] );
        print "</rows>\n";
        exit();
    }
    
    function Orders2XML( $leaf, $id = '', $Order, $Chain, $Task, $Planned, $Spoolers, $Job, $State  ) {
            $date = $this->container->get('arii_core.date');        
            if (is_array($leaf)) {
                    foreach (array_keys($leaf) as $k) {
                            $Ids = explode('/',$k);
                            $here = array_pop($Ids);
                            $i  = substr("$id/$k",1);
                            // par defaut
                            $open = '';  
                            if (isset($State[$i])) {
                                if ($State[$i]==1)
                                    $open = ' open="1"';
                            }
                            $cell = '';
                            if (isset($Order[$i])) {
                                // L'id est celui de SCHEDULER_ORDER_HISTORY
                                $Info = $Order[$i];
                                
                                // statut mis en base de données
                                $order_icon = 'add_order';                                
                                $color = 'black';
                                $status = $Info['status'];
                                $order_icon = 'order';
                                if (isset($Info['at']))
                                    $next_time = $Info['at'];
                                else
                                    $next_time = ''; 
                                
                                if ($status == "SUSPENDED")     $color = "red";
                                elseif ($status == "SETBACK")   $color = "lightsalmon";
                                elseif ($status == "RUNNING")  $color = '#ffffcc';
                                elseif ($status == "FAILURE")  $color = '#fbb4ae';
                                elseif ($status == "FALSE")  $color = '#fbb4ae';
                                else    $color = '#fbb4ae';

                                $dbid = $Info['dbid'];
                                if (isset($State[$dbid])) {
                                    if ($State[$dbid]==1)
                                        $open = ' open="1"';
                                }
                                $cell .= '<row id="'.$dbid.'" style="background-color: '.$color.';"'.$open.'>';
                                // Est ce qu'il est aussi planifié ?
                                $cell .= '<cell>'.$Info['full'].'</cell>';
                                if (isset($Planned[$i])) {
                                    $cell .= '<userdata name="type">order</userdata>';
                                }
                                else {
                                    // juste a la volée
                                    $cell .= '<userdata name="type">order add</userdata>';                                   
                                }
                                // attention, il y a le statique et le dynamique
                                $title = $Info['title'];
                                if ($title == '') {
                                    $cell .= '<cell image="'.$order_icon.'.png">'.' '.str_replace('£','/',$here).'</cell>';
                                }
                                else {
                                    $cell .= '<cell image="'.$order_icon.'.png"><![CDATA['.str_replace('£','/',$here).' <font color="grey">('.$title.')</font>]]></cell>';
                                }

                                $cell .= '<cell>'.$this->XMLProtect($Info['state']).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell>'.$this->images.'/'.strtolower($status).'.png</cell>';
                                list($start_str,$end_str,$next_str,$duration) = $date->getLocaltimes( $Info['start_time'], $Info['end_time'], substr($next_time,0,16), $Info['spooler'] ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                                $cell .= '<cell>'.$end_str.'</cell>';

                                $cell .= '<cell><![CDATA[';
                                if (isset($setback) && $setback>0) {
                                    $cell .= '['.$setback.']';
                                }
                                $cell .= $this->XMLProtect($Info['state_text']).']]></cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                         
                            } 
                            // Plus d'historique mais planifié
                            elseif (isset($Planned[$i])) {
                                $Next =  $Planned[$i];
                                
                                $next = $Next['at'];
                                $start_time = $Next['mod_time'];
                                $status = $Next['status'];
                                $order_icon = 'order';                                
                                if ($status=="SUSPENDED") {
                                    $color = 'red';
                                    $order_icon = 'order_suspended';
                                }
                                elseif ($status == "SETBACK")
                                {
                                    $color = "lightsalmon";
                                    $status = 'SETBACK';
                                    $start_time =  $Next['next_time'];
                                    $next = $Next['setback'];
                                }
                                else {
                                    $color = 'lightblue';
                                }    
                                $dbid = $Next['dbid'];
                                $planned_id = "O#".$dbid;
                                if (isset($State[$planned_id])) {
                                    if ($State[$planned_id]==1)
                                        $open = ' open="1"';
                                }
                                $cell .= '<row id="'.$planned_id.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$Next['full'].'</cell>';
                                $cell .= '<userdata name="type">order planned</userdata>';
                                $cell .= '<cell image="'.$order_icon.'.png">'.str_replace('£','/',$here).'</cell>';
                                
                                $cell .= '<cell>'.$this->XMLProtect($Next['initial']).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                list($start_str,$end_str,$next_str) = $date->getLocaltimes( $start_time, '', $next, $Next['spooler'] ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                                $cell .= '<cell></cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),time()).'</cell>';
                                $cell .= '<cell><![CDATA['.$Next['title'].']]></cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                               
                            }                            
                            elseif (isset($Chain[$i])) {
                                $Info = $Chain[$i];

                                $dbid = $Info['dbid'];
                                $job_chain = $Info['chain'];
                                $status = $Info['status'];

                                $color = '#ccebc5';
                                if($status == "STOPPED")
                                {
                                    $color = '#FF0000';
                                }
				$chain_id = "C#".$dbid;
                                $open = '';  
                                if (isset($State[$chain_id])) {
                                    if ($State[$chain_id]==1)
                                        $open = ' open="1"';
                                }
                                $cell .= '<row id="'.$chain_id.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$Info['full'].'</cell>';
                                $cell .= '<userdata name="type">job_chain</userdata>';
                                $cell .= '<cell image="job_chain.png"> '.$here.'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='STOPPED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                $cell .= '<cell/>';
//                                $cell .= '<cell/>';
                                $cell .= '<cell/>';
                                $cell .= '<cell/>';
                                print "$cell\n";                               
                            } 
                            elseif (isset($Job[$i])) {
                                $Info = $Job[$i];
                                $dbid = $Info['dbid'];
                                $start_time = $Info['start_time'];
                                $end_time = $Info['end_time'];
                                $state = $Info['state'];
                                $error_text = $Info['error_text'];
                                $error = $Info['error'];
                                $job_name = basename($Info['job_name']);
                                $status = $Info['status'];
                                $job_state = $Info['job_state'];
                                $path = $Info['path'];
                                $spooler = $Info['spooler'];
                                $exit = $Info['exit'];
                                
                                if($status == "SUCCESS")        $color = '#ccebc5'; 
                                elseif($status == "RUNNING")    $color = '#ffffcc';
                                elseif($status == "FAILURE")    $color = '#fbb4ae';
                                elseif($status == "ERROR")      $color = '#fbb4ae';
                                elseif ($status == "WARNING")   $color = '#fbb4ae';
                                
                        	$task_id = "0".$dbid;
                                $action_step = $path.'/'.$this->XMLProtect($state);
                                if(isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="stop"){
                                    $color = 'red';
                                }
                                elseif (isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="next_state"){
                                    $color = 'orange';
                                }
                                $cell .= '<row id="0'.$dbid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$Info['full'].'</cell>';
                                $cell .= '<userdata name="type">step_job</userdata>';
                                $cell .= '<cell image="ordered_job.png"> '.$job_name.'</cell>';
                                // Probleme du job stoppé dans une chaine (idiot)
                                // blocage total !
                                $cell .= '<cell style="background-color: '.$color.'; text-align: right;">'.$exit.'</cell>';
                                $img_status = $status;
                                if ($job_state == 'STOP'){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/bug.png"/>]]></cell>';
                                    $status = 'JOB STOP!';
                                    $stop = 'JOB!';
                                } elseif(isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="stop"){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/stopped.png"/>]]></cell>';
                                    $stop = 'STOP';
                                } elseif (isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="next_state"){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/skipped.png"/>]]></cell>';
                                    $stop = 'SKIP';
                                } else {
                                    $cell .= '<cell/>';
                                    $stop = 'ACT.';
                                }
                                                                
                                $cell .= '<cell>'.$status.'</cell>';
                                $cell .= '<cell>'.$stop.'</cell>';
                                list($start_str,$end_str) = $date->getLocaltimes( $start_time, $end_time, '', $spooler ); 
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($img_status).'.png"/>]]></cell>';
                                $cell .= '<cell>'.$start_str.'</cell>';
                                $cell .= '<cell>'.$end_str.'</cell>';
                                $cell .= '<cell>'.$this->XMLProtect($error_text).'</cell>';
                                print "$cell\n";                               
                            } 
                            elseif (isset($Task[$i])) {
                                list($dbid, $start_time, $end_time, $state, $error_text, $error, $job_name,$status,$job_state,$path,$spooler ) = explode('|',$Task[$i]);
                                if($status == "SUCCESS")
                                {
                                    $color = '#ccebc5'; 
                                } elseif($status == "RUNNING")
                                {
                                    $color = '#ffffcc';
                                } elseif($status == "FAILURE")
                                {
                                    $color = '#fbb4ae';
                                }
                                elseif ($status == "WARNING")
                                {
                                    $color = '#fbb4ae';
                                }
                                
                        	$task_id = "0".$dbid;
                                $action_step = $path.'/'.$this->XMLProtect($state);
                                if(isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="stop"){
                                    $color = 'red';
                                }
                                elseif (isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="next_state"){
                                    $color = 'orange';
                                }
                                $cell .= '<row id="0'.$dbid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>TASK</cell>';
                                $cell .= '<userdata name="type">step_job</userdata>';
                                $cell .= '<cell image="ordered_job.png"><![CDATA['.str_replace('£','/',$here).' <img src="'.$this->images.'/step.png"/> '.$job_name.']]></cell>';
                                // Probleme du job stoppé dans une chaine (idiot)
                                // blocage total !
                                $cell .= '<cell>'.$this->XMLProtect($state).'</cell>';
                                $img_status = $status;
                                if ($job_state == 'STOP'){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/bug.png"/>]]></cell>';
                                    $status = 'JOB STOP!';
                                    $stop = 'JOB!';
                                } elseif(isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="stop"){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/stopped.png"/>]]></cell>';
                                    $stop = 'STOP';
                                } elseif (isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="next_state"){
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/skipped.png"/>]]></cell>';
                                    $stop = 'SKIP';
                                } else {
                                    $cell .= '<cell/>';
                                    $stop = 'ACT.';
                                }
                                                                
                                $cell .= '<cell>'.$status.'</cell>';
                                $cell .= '<cell>'.$stop.'</cell>';
                                list($start_str,$end_str) = $date->getLocaltimes( $start_time, $end_time, '', $spooler ); 
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($img_status).'.png"/>]]></cell>';
                                $cell .= '<cell>'.$start_str.'</cell>';
                                $cell .= '<cell>'.$end_str.'</cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),strtotime($end_time)).'</cell>';
                                $cell .= '<cell>'.$error_text.'</cell>';
//                                $state_text = str_replace("<", "&lt;", $state_text);
//                                $state_text = str_replace(">", "&gt;", $state_text);
//                                $cell .= '<cell><![CDATA['.$job_name.' '.$state_text.']]></cell>';
                                print "$cell\n";                               
                            } 
                            else {
                                    if ($id == '') {
                                        // cas du spooler 
                                        $icon = 'spooler';
                                        $col = '#ccebc5';
                                        if (isset($Spoolers[$i])) {
                                            $Info = $Spoolers[$i];
                                            $hostname = $Info['hostname'];
                                            $tcp_port = $Info['port'];
                                            $start_time = $Info['start_time'];
                                            $is_running = $Info['is_running'];
                                            $is_paused = $Info['is_paused'];
                                            if ($is_paused==1) {
                                                $col = 'orange';
                                                $status = 'PAUSED';
                                            }
                                            elseif ($is_running==0) {
                                                $col = 'red';
                                                $status = 'DOWN';
                                            }
                                            else {
                                                $status = 'RUNNING';
                                            }
                                        }
                                        else {
                                            $status = 'UNKNOWN';
                                            $start_time= '';
                                            $icon = 'error';
                                        }
                                        $cell .= '<row id="'.$i.'" style="background-color: '.$col.';"'.$open.'>';
                                        $cell .= '<cell>'.$here.'</cell>';
					$cell .= '<userdata name="type">spooler</userdata>';
                                        $cell .= '<cell image="'.$icon.'.png"><![CDATA[<b> '.$here.'</b>]]></cell>';
                                        $cell .= '<cell></cell>';
                                        $cell .= '<cell></cell>';
                                        $cell .= '<cell>'.$status.'</cell>';
                                        if ($status!='DOWN') {
                                            $cell .= '<cell>ACT.</cell>';
                                        }
                                        else {
                                            $cell .= '<cell>STOP</cell>';
                                        }
                                        $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                        $cell .= '<cell>'.$start_time.'</cell>';
                                    }
                                    else {
                                        $cell .= '<row id="'.$i.'"'.$open.'>';
                                        $cell .= '<cell/>';
					$cell .= '<userdata name="type">folder</userdata>';
                                        $cell .= '<cell image="folder.gif">'.$here.'</cell>';
                                        if (isset($Path[$i])) {
                                            $cell .= '<cell>'.$Path[$i].'</cell>';
                                        }
                                    }
                                    print $cell;
                            }
                           $this->Orders2XML( $leaf[$k], $id.'/'.$k, $Order, $Chain, $Task, $Planned, $Spoolers, $Job, $State   );
                           print "</row>\n";
                    }
            }
    }
    
    private function XMLProtect ($txt) {
        $txt = utf8_encode($txt);
        $txt = str_replace('<','&lt;',$txt);
        $txt = str_replace('>','&gt;',$txt);
        return $txt;
    }
    
    public function NoRecord()
    {
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print '
        <rows><head><afterInit><call command="clearAll"/></afterInit></head>
        <row id="scheduler"><cell image="'.$this->images.'/spooler.png">No record</cell>
        </row></rows>';
        exit();
    }

    public function start_order_parametersAction()
    {
        $request = Request::createFromGlobals();
        // depend desarguments ?
        $id = $request->get('id');        
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $sql = $this->container->get('arii_core.sql');
        
        // Order direct
        if (strpos(" $id",'/')>0) {
            $Infos = explode('/',$id);
            $spooler_id = array_shift($Infos);
            $order_id = array_pop($Infos);
            $job_chain = implode('/',$Infos);
        }
        else {
            // recuperation du contexte
            $qry = $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_ID'))
                    .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                    .$sql->Where(array('HISTORY_ID' => $id));

            $res = $data->sql->query($qry);
            $line = $data->sql->get_next($res);        
            if(empty($line))
            {
                return new Response($id.' ?!');
            }
            $job_chain = $line['JOB_CHAIN'];
            $order_id = $line['ORDER_ID'];
            $spooler_id = $line['SPOOLER_ID'];
        }

        $qry = $sql->Select(array('PAYLOAD'))
                .$sql->From(array('SCHEDULER_ORDERS'))
                .$sql->Where( array (
                    'SPOOLER_ID'=> $spooler_id,
                    'ID' => $order_id,
                    'JOB_CHAIN' => $job_chain ));
        // print $qry;

        $res = $data->sql->query($qry);
        $line = $data->sql->get_next($res);
        
        if(empty($line))
        {
            return new Response('No Parameters');
        } else
        {
            $params = $line['PAYLOAD'];
            $Parameters = array();
            
            while (($p = strpos($params,'<variable name="'))>0) {
                $begin = $p+16;
                $end = strpos($params,'" value="',$begin);
                $var = substr($params,$begin,$end-$begin);
                $params = substr($params,$end+9);
                $end = strpos($params,'"/>');
                $val = substr($params,0,$end);
                $params = substr($params,$end+2);
                if (strpos(" $val",'password')>0) {
                    // a voir avec les connexions
                } 
                else {
                    $Parameters[$var] = $val;
                }
            }
            
            $response = new Response();
            $response->headers->set('Content-Type', 'text/xml');

            $list = '<?xml version="1.0" encoding="UTF-8"?>';
            $list .= "<rows>\n";
            $list .= '<head>
                <afterInit>
                    <call command="clearAll"/>
                </afterInit>
            </head>';

            foreach ($Parameters as $vr => $vl)
            {
                $list .= '<row><cell>'.$vr.'</cell><cell>'.$vl.'</cell></row>';
            }
            $list .= '</rows>';

            $response->setContent($list);
            return $response;
        }
        
    }

/******************************************************************/
/* Fonctions simples de date 
/******************************************************************/
    
    function ShortDate($date) {
        if (substr($date,0,4)=='2038') {
            return $this->get('translator')->trans('Never');
        }
        if (substr($date,0,10)==date('Y-m-d'))
                return substr($date,11);
        return $date;
    }
    
    function FormatTime($d) {
       $str = '';
       if ($d>86400) {
           $n = (int) ($d/86400);
           $d %= 86400;
           $str .= ' '.$n.'d'; 
           return $str;
       }
       if ($d>3600) {
           $n = (int) ($d/3600);
           $d %= 3600;
           $str .= ' '.$n.'h';           
           return $str;
       }
       if ($d>60) {
           $n = (int) ($d/60);
           $d %= 60;
           $str .= ' '.$n.'m';           
       }
       if ($d>0) 
        $str .= ' '.$d.'s';
       return $str;        
    }
    
    function Duration($start,$end = '' ) {
       if ($end == '') {
           $end = time();
       }
       $d = $end - $start;
       return $this->FormatTime($d);
    }


}
