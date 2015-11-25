<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChainsController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
    }

    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Chains:index.html.twig');
    }
    
    public function menuAction()
    {
        return $this->render('AriiJIDBundle:Chains:menu.xml.twig');
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
        
        return $this->render('AriiJIDBundle:Chains:history.html.twig', 
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
        
        return $this->render('AriiJIDBundle:Chains:charts.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }
    
    public function toolbarAction()
    {
        return $this->render('AriiJIDBundle:Chains:toolbar.xml.twig');
    }

    public function toolbar_activitiesAction()
    {
        return $this->render('AriiJIDBundle:Chains:toolbar_activities.xml.twig');
    }

    public function toolbar_timelineAction()
    {
        return $this->render('AriiJIDBundle:Chains:toolbar_timeline.xml.twig');
    }
    
    public function listAction()
    {
        return $this->render('AriiJIDBundle:Chains:list.html.twig');
    }

    public function activitiesAction()
    {
        return $this->render('AriiJIDBundle:Chains:activities.html.twig');
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
        return $this->render('AriiJIDBundle:Chains:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function pieAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );

        $qry = $sql->Select(array('soh.START_TIME','soh.END_TIME','sosh.ERROR'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('soh.START_TIME'));

        $res = $data->sql->query( $qry );
        $running = $success = $failure = 0;
        while ($line = $data->sql->get_next($res)) {
            if ($line['END_TIME'] == '') {
                $running++;
            }
            elseif ($line['ERROR']==0) {
                $success++;
            }
            else {
                $failure++;
            }
        }
        $pie = "<?xml version='1.0' encoding='utf-8' ?>";
        $pie .= "<data>";
        $pie .= '<item id="1"><STATUS>SUCCESS</STATUS><JOBS>'.$success.'</JOBS><COLOR>#749400</COLOR></item>';
        $pie .= '<item id="2"><STATUS>FAILURE</STATUS><JOBS>'.$failure.'</JOBS><COLOR>red</COLOR></item>';
        $pie .= '<item id="3"><STATUS>RUNNING</STATUS><JOBS>'.$running.'</JOBS><COLOR>orange</COLOR></item>';
        $pie .= "</data>";
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $pie );
        return $response;
//        return $this->render('AriiJIDBundle:Menu:global.xml.twig', array( 'update' => $refresh, 'database' => $database ), $response);
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

    public function lastAction($history=0)
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{start_time}' => 'soh.START_TIME',
            '{end_time}'   => 'soh.END_TIME' );

        $qry = $sql->Select(array('soh.HISTORY_ID','soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.TITLE','soh.STATE as ORDER_STATE','soh.STATE_TEXT','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
                  'sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.STATE','sosh.ERROR','sosh.ERROR_TEXT'  ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                .$sql->where($Fields)
                .$sql->OrderBy(array('soh.HISTORY_ID desc','sosh.TASK_ID','sosh.STEP'));

        $res = $data->sql->query( $qry );
         header("Content-type: text/xml");
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print "<rows>\n";
        print '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        $Fields = array (
            '{spooler}'    => 'soh.SPOOLER_ID',
            '{job_chain}'   => 'soh.JOB_CHAIN',
            '{start_time}' => 'soh.START_TIME' );

        $qry = $sql->Select(array('soh.HISTORY_ID','soh.TITLE','soh.SPOOLER_ID','soh.ORDER_ID','soh.JOB_CHAIN','soh.STATE',
                    'sosh.START_TIME','sosh.END_TIME','sosh.ERROR','sosh.ERROR_TEXT','sosh.STEP'))  
                  .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                  .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('sosh.START_TIME desc','sosh.END_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ORDER_ID'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($line['END_TIME']!='') {
                if ($line['ERROR']>0) {
                    if (substr($line['STATE'],0,1)=='!') {
                        $status = 'FATAL';
                        $color = 'red';
                    }
                    else {
                        $status = 'ERROR';
                        $color  = '#fbb4ae';
                    }
                }
                else {
                    $status = 'SUCCESS';  
                    $color  = '#ccebc5';
                }
            }
            else {
                $status = 'RUNNING';
                $color  = '#ffffcc';
            }
            
            if ($Nb[$id]>$history) continue;
            print '<row id="'.$line['HISTORY_ID'].'"  style="background-color: '.$color.';">';
            print '<cell>'.$line['SPOOLER_ID'].'</cell>';
            print '<cell>'.$line['JOB_CHAIN'].'</cell>';
            print '<cell>'.$line['ORDER_ID'].'</cell>';
            print '<cell>'.$line['STEP'].'</cell>';
            print '<cell>'.$line['STATE'].'</cell>';
            print '<cell>'.$status.'</cell>';
            print '<cell>'.$line['START_TIME'].'</cell>';
            print '<cell>'.$line['END_TIME'].'</cell>';
            if ($line['ERROR_TEXT']!='') {
                $information = $line['ERROR_TEXT'];
            }
            else {
                $information = $line['TITLE'];
            }
            print '<cell>'.$information.'</cell>';
            print '</row>';
        }
        print "</rows>\n";
        exit();
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
    public function list_xmlAction($full=false,$activated=false)
    {
        $request = Request::createFromGlobals();
	
/*
        if ($request->get('steps')=='true') {
            $full = true;
        }
        if ($request->get('activated')=='true') {
            $activated = true;
        }
*/        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

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
                $Spoolers[$spooler]['is_paused'] = $line['IS_PAUSED'];
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
                    $ChainStopped[$cn] = $line['STOPPED'];
                     // on garde la trace
                    $key_files[$cn] = $cn;
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
                    $key_files[$sn] = $sn;
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
                # Creation des icones
                $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
                $cn = str_replace('//','/',$cn); 
                // on pense a proteger les ORDER_ID
                $jn = $cn.'/'.str_replace('/','£',$line['ORDER_ID']);
                $chain_status = "SUCCESS";
                if(isset($ChainStopped[$cn]))
                {
                    $chain_status = "STOPPED";
                }
                
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

                $Chain[$cn]['dbid'] = '!'.$line['ORDERING'];
                $Chain[$cn]['chain'] = $line['JOB_CHAIN'];
                $Chain[$cn]['status'] = $chain_status;
                
                $key_files[$cn] = $cn;
                $Planned[$jn]['dbid'] = $jn;
                $Planned[$jn]['chain'] = $line['JOB_CHAIN'];
                $Planned[$jn]['created'] = $line['CREATED_TIME'];
                $Planned[$jn]['mod_time'] = $line['MOD_TIME'];
                $Planned[$jn]['title'] = $line['TITLE'];
                $Planned[$jn]['state'] = $line['INITIAL_STATE'];
                $Planned[$jn]['status'] = $order_status;
                $Planned[$jn]['next_time'] = $next_time;
                $Planned[$jn]['setback'] = $setback;
                $Planned[$jn]['setback_time'] = $setback_time;
                $Planned[$jn]['at'] = $at;
                $Planned[$jn]['history_id'] = $hid;
                $Planned[$jn]['spooler'] = $line['SPOOLER_ID'];
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
        if ($full) {
            $qry = $sql->Select(array('sosh.TASK_ID as ID','soh.HISTORY_ID','sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.STATE','sosh.ERROR','sosh.ERROR_TEXT',
             'soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.TITLE','soh.STATE as ORDER_STATE','soh.STATE_TEXT','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
             'sh.JOB_NAME'))
                    .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                    .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                    .$sql->LeftJoin('SCHEDULER_HISTORY sh',array('sosh.TASK_ID','sh.ID')) 
                    .$sql->where($Fields)
                    .$sql->OrderBy(array('soh.HISTORY_ID desc','sosh.TASK_ID','sosh.STEP'));
        }
        else {
            $qry = $sql->Select(array('soh.HISTORY_ID','soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.TITLE','soh.STATE as ORDER_STATE','soh.STATE_TEXT','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
                      'sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.STATE','sosh.ERROR','sosh.ERROR_TEXT'  ))
                    .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                    .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                    .$sql->where($Fields)
                    .$sql->OrderBy(array('soh.HISTORY_ID desc','sosh.TASK_ID','sosh.STEP'));
        }
            $qry = $sql->Select(
                    array('soh.HISTORY_ID', 'soh.SPOOLER_ID', 'soh.JOB_CHAIN', 'soh.ORDER_ID', 'soh.TITLE','soh.STATE as ORDER_STATE','soh.STATE_TEXT','soh.START_TIME as ORDER_START_TIME','soh.END_TIME as ORDER_END_TIME',
                      'sosh.STEP','sosh.START_TIME','sosh.END_TIME','sosh.STATE','sosh.ERROR','sosh.ERROR_TEXT',
                      'sh.JOB_NAME'  ))
                    
                    .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                    .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY sosh',array('soh.HISTORY_ID','sosh.HISTORY_ID'))
                    .$sql->LeftJoin('SCHEDULER_HISTORY sh',array('sosh.TASK_ID','sh.ID')) 
                    .$sql->where($Fields)
                    .$sql->OrderBy(array('soh.SPOOLER_ID','soh.JOB_CHAIN','soh.ORDER_ID','soh.HISTORY_ID','sosh.TASK_ID','sosh.STEP'));
            
// print $qry;exit();
        $Order = $Task = $GlobalChain = $GlobalOrder =  $NestedChain = $Nb = array();
        $res = $data->sql->query( $qry );
        $nb = 0; 
        $last_step = array(); // dernier heure du step de chaque order
        $order_xml = array();
        $Path = array();
        while ($line = $data->sql->get_next($res)) {
            // Cas particulier du '#'
            if (($p = strpos($line['ORDER_ID'],'.'))>0) {
                $chain = substr($line['ORDER_ID'],0,$p);
                $order = substr($line['ORDER_ID'],$p+1); 
                // nested chain
                $nc = $line['SPOOLER_ID'].'/'.dirname($line['JOB_CHAIN']).'/'.$chain;
                $no = $nc.'/'.$order;
                $cn = $no.'/'.basename($line['JOB_CHAIN']);
                // redondant comme le JOC original
                // $jn = $cn.'/'.str_replace('/','£',$line['ORDER_ID']);
                $jno = str_replace('//','/',$line['SPOOLER_ID'].'/'.$line['JOB_CHAIN']).'/'.str_replace('/','£',$line['ORDER_ID']);
                $nested = true;
            }
            else {
                $cn = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
                $cn = str_replace('//','/',$cn);
                $jn = $cn.'/'.str_replace('/','£',$line['ORDER_ID']);
                $nested = false;
            }
            
            
            if ($full) { 
                $sn = $jn.'/'.$line['STEP'];
            }
            // on conserve le chemin
            $p = dirname($cn);

            if ($full) {
                # step trop ancien ?
                if (isset($last_step[$jn]) and ($line['START_TIME']<$last_step[$jn])) {
                   continue;
                }
                $last_step[$jn] = $line['START_TIME'];

                # step deja traité ?
                if (isset($key_files[$sn]))
                        continue;
            }
            # On prends les n derniers ordres pour un job_chain
            if (isset($Nb[$cn])) {
                $Nb[$cn]++;
                // ou dela 50 ca n'a pas de sens
                if ($Nb[$cn]>50) continue;
            }
            else {
                $Nb[$cn]=0;
            }
            $nb++;

            $chain_status = "ACTIVE";
            if(isset($ChainStopped[$cn]))
            {
                $chain_status = "STOPPED";
            }

            $job_status = 'UNKNOWN';
            if ($full) {
                $job_status = "SUCCESS";
                if($line['END_TIME']=="")
                {
                    $job_status = "RUNNING";
                } 
                elseif ($line['ERROR']>0)
                {
                    if (substr($line['STATE'],1)=='!') {
                        $job_status = "FATAL";
                        // on conserve le nombre d'erreur
                        if (isset($Nb_err[$jn])) {
                             $Nb_err[$jn]++;
                        }
                        else {
                             $Nb_err[$jn] = 1;
                        }
                    }
                    elseif (substr($line['STATE'],1)=='?') {
                        $job_status = "FALSE";
                    }
                }
                else {
                    if (substr($line['STATE'],1)=='?') {
                        $job_status = "TRUE";
                    }                
                }
            }
            
            $order_status = "SUCCESS";            
            if($line['ORDER_END_TIME']=="") {
                // on a le cas du job bloqué
                if ($job_status=='STOPPED') {
                    $order_status = "WAITING";                    
                }
                else {
                    $order_status = "RUNNING";
                }
            } 
            elseif($line['ERROR'] > 0) {
                $order_status = "FAILURE";
            }
            // Infos sur le job_chain
            if (isset($Nb_err[$jn])) {
                $nb_err = $Nb_err[$jn];
            }
            else {
                $nb_err = 0;
            }
            
            if ($full) {
                $joid = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
                $job_state = 'ACT.';
                if (isset($JobStopped[$joid])) {
                    $job_state = 'STOP';
                }
            }
            // Infos sur l'order
            //for the job chain, i think we don't need too much information
            if ($nested) {
                $nested_chain_status = $chain_status;
                if(isset($ChainStopped[$cn]))
                    $nested_chain_status = "STOPPED";
                $GlobalChain[$nc]['dbid'] = $line['HISTORY_ID'];
                $GlobalChain[$nc]['chain'] = $chain;
                $GlobalChain[$nc]['status'] = $nested_chain_status;
                
                $GlobalOrder[$no]['dbid'] = $line['HISTORY_ID'];
                $GlobalOrder[$no]['chain'] = $chain;
                $GlobalOrder[$no]['start_time'] = $line['ORDER_START_TIME'];
                $GlobalOrder[$no]['end_time'] = $line['ORDER_END_TIME'];
                $GlobalOrder[$no]['title'] = $line['TITLE'];
                $GlobalOrder[$no]['state'] = $line['ORDER_STATE'];
                $GlobalOrder[$no]['state_text'] = $line['STATE_TEXT'];
                $GlobalOrder[$no]['error'] = $line['ERROR'];
                $GlobalOrder[$no]['status'] = $order_status;
                $GlobalOrder[$no]['nb_err'] = $nb_err;
                $GlobalOrder[$no]['spooler'] = $line['SPOOLER_ID'];
                
                if (isset($Planned[$jno])) {
                    $Planned[$cn] = $Planned[$jno];
                }
                // La nested chain doit intégrer les informations de l'ordre
                $NestedChain[$cn]['dbid'] = $line['HISTORY_ID'];
                $NestedChain[$cn]['chain'] = $line['JOB_CHAIN'];
                $NestedChain[$cn]['start_time'] = $line['ORDER_START_TIME'];
                $NestedChain[$cn]['end_time'] = $line['ORDER_END_TIME'];
                $NestedChain[$cn]['title'] = '';
                $NestedChain[$cn]['state'] = $line['ORDER_STATE'];
                $NestedChain[$cn]['state_text'] = $line['STATE_TEXT'];
                $NestedChain[$cn]['error'] = $line['ERROR'];
                $NestedChain[$cn]['status'] = $order_status;
                $NestedChain[$cn]['nb_err'] = $nb_err;
                $NestedChain[$cn]['spooler'] = $line['SPOOLER_ID'];
            }
            else {
                // normalement c'est inutile
                // NestedChain = Chain + Order;
                $Chain[$cn]['dbid'] = $line['HISTORY_ID'];
                $Chain[$cn]['chain'] = $line['JOB_CHAIN'];
                $Chain[$cn]['status'] = $chain_status;
                
                $Order[$jn]['dbid'] = $line['HISTORY_ID'];
                $Order[$jn]['job_chain'] = $line['JOB_CHAIN'];
                $Order[$jn]['start_time'] = $line['ORDER_START_TIME'];
                $Order[$jn]['end_time'] = $line['ORDER_END_TIME'];
                $Order[$jn]['title'] = $line['TITLE'];
                $Order[$jn]['state'] = $line['ORDER_STATE'];
                $Order[$jn]['state_text'] = $line['STATE_TEXT'];
                $Order[$jn]['error'] = $line['ERROR'];
                $Order[$jn]['status'] = $order_status;
                $Order[$jn]['nb_err'] = $nb_err;
                $Order[$jn]['spooler'] = $line['SPOOLER_ID'];
            }
            $key_files[$cn] = $cn;
            $key_files[$jn] = $jn;
            if ($full) {
                $Task[$sn]['dbid'] = $line['ID'];
                $Task[$sn]['start_time'] = $line['START_TIME'];
                $Task[$sn]['end_time'] = $line['END_TIME'];
                $Task[$sn]['state'] = $line['STATE'];
                $Task[$sn]['error_text'] = $line['ERROR_TEXT'];
                $Task[$sn]['error'] = $line['ERROR'];
                $Task[$sn]['job_name'] = $line['JOB_NAME'];
                $Task[$sn]['status'] = $job_status;
                $Task[$sn]['job_state'] = $job_state;
                $Task[$sn]['chain'] = $cn;
                $Task[$sn]['spooler'] = $line['SPOOLER_ID'];
                $key_files[$sn] = $sn;
            }
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
        if (!isset($States['chains'])) $States['chains'] =array();
        $this->Orders2XML( $tree, '', $Order, $Chain, $Task,$Planned, $Nb, $ChainStopped, $JobStopped, $ActionStep, $Spoolers, $GlobalChain, $GlobalOrder, $NestedChain, $Path, $States['chains']);
        print "</rows>\n";
        exit();
    }
    
    function Orders2XML( $leaf, $id = '', $Order, $Chain, $Task,$Planned, $Nb, $ChainStopped, $JobStopped, $ActionStep, $Spoolers, $GlobalChain, $GlobalOrder, $NestedChain, $Path, $State ) {
            $date = $this->container->get('arii_core.date');        
            if (is_array($leaf)) {
                    foreach (array_keys($leaf) as $k) {
                            $Ids = explode('/',$k);
                            $here = array_pop($Ids);
                            $i  = substr("$id/$k",1);
                            # Est ce que c'est un ordre ?
                            // par defaut
                            $open = $cell = '';  
                            // On retrouve l'historique
                            if (isset($GlobalOrder[$i])) {
                                // L'id est celui de SCHEDULER_ORDER_HISTORY
                                $Info = $GlobalOrder[$i];
                                $order_id = $Info['dbid'];
                                $job_chain= $Info['chain'];
                                $start_time=$Info['start_time'];
                                $end_time = $Info['end_time'];
                                $title =    $Info['title'];
                                $state =    $Info['state'];
                                $state_text=$Info['state_text'];
                                $error =    $Info['error'];
                                $status =   $Info['status'];
                                $nb_err =   $Info['nb_err'];
                                $spooler =  $Info['spooler'];
                                // $order_id = str_replace('£','/',$order_id);
                                if (isset($Planned[$i])) {
                                    $order_icon = 'order';
                                }
                                else {
                                    $order_icon = 'add_order';
                                }

                                // statut mis en base de données
                                $next = $next_time = '';
                                $internal_status = '';
                                if (isset($Planned[$i])) {
                                    // $order_id = 'O#'.$dbid; 
                                    $order_icon = 'order';
                                    $InfoPlan = $Planned[$i]; 
                                    $dbid =         $InfoPlan['dbid'];
                                    $job_chain =    $InfoPlan['chain'];
                                    $created_time = $InfoPlan['created'];
                                    $mod_time =     $InfoPlan['mod_time'];
                                    $title =        $InfoPlan['title'];
                                    $initial_state =$InfoPlan['state'];
                                    $internal_status=$InfoPlan['status'];
                                    $next_time      =$InfoPlan['next_time'];
                                    $setback =      $InfoPlan['setback'];
                                    $setback_time = $InfoPlan['setback_time'];
                                    $at =           $InfoPlan['at'];
                                    $hid =          $InfoPlan['history_id'];
                                    $spooler =      $InfoPlan['spooler'];

                                    $next = $at;
                                    // print '<userdata name="next">'.$dbid.'</userdata>';
                                }
                                else {
                                     $at = '';   
                                }
                                
                                $color = 'black';
                                if ($status == 'RUNNING') {
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                    else {
                                         $color = 'orange';
                                         $next_time = $at;
                                    }
                                }
                                else {
                                    if($status == "SUCCESS")
                                    {
                                        $color = '#ccebc5'; 
                                        if ($nb_err>0) {
                                            // warning
                                            $status = 'WARNING';
                                            $color = '#ffffcc'; 
                                        }
                                    } elseif($status == "RUNNING")
                                    {
                                        $color = '#ffffcc';
                                    } elseif($status == "FAILURE")
                                    {
                                        $color = '#fbb4ae';
                                    } 
                                    // si suspendu, on garde quand meme la couleur de fond
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                }
                                
                                $rowid = 'G#'.$order_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }
                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
                                // Est ce qu'il est aussi planifié ?
                                if (isset($Planned[$i])) {
                                    $cell .= '<userdata name="type">order</userdata>';
                                }
                                else {
                                    // juste a la volée
                                    $cell .= '<userdata name="type">order add</userdata>';                                   
                                }
                                // attention, il y a le statique et le dynamique
                                if ($title == '') {
                                    $cell .= '<cell image="'.$order_icon.'.png"> '.' '.str_replace('£','/',$here).'</cell>';
                                }
                                else {
                                    $cell .= '<cell image="'.$order_icon.'.png"><![CDATA['.str_replace('£','/',$here).' <font color="grey">('.$title.')</font>]]></cell>';
                                }
                                $cell .= '<cell> '.$this->XMLProtect($state).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                list($start_str,$end_str,$next_str,$duration) = $date->getLocaltimes( $start_time, $end_time, substr($next_time,0,16), $spooler ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                              $cell .= '<cell>'.$end_str.'</cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),strtotime($end_time)).'</cell>';
                                $cell .= '<cell>';
                                if (isset($setback) && $setback>0) {
                                    $cell .= '['.$setback.']';
                                }
                                $cell .= $this->XMLProtect($state_text).'</cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                               
                            } 
                            elseif (isset($Order[$i])) {
                                $Info = $Order[$i];
                                $order_id =     $Info['dbid'];
                                $job_chain =    $Info['job_chain'];
                                $start_time =   $Info['start_time'];
                                $end_time =     $Info['end_time'];
                                $title =        $Info['title'];
                                $state =        $Info['state'];
                                $state_text =   $Info['state_text'];
                                $error =        $Info['error'];
                                $status =       $Info['status'];
                                $nb_err =       $Info['nb_err'];
                                $spooler =      $Info['spooler'];

                                // $order_id = str_replace('£','/',$order_id);
                                if (isset($Planned[$i])) {
                                    $order_icon = 'order';
                                }
                                else {
                                    $order_icon = 'add_order';
                                }

                                // statut mis en base de données
                                $next = $next_time = '';
                                $internal_status = '';
                                if (isset($Planned[$i])) {
                                    // $order_id = 'O#'.$dbid; 
                                    $order_icon = 'order';
                                    $InfoPlan = $Planned[$i]; 
                                    $dbid =         $InfoPlan['dbid'];
                                    $job_chain =    $InfoPlan['chain'];
                                    $created_time = $InfoPlan['created'];
                                    $mod_time =     $InfoPlan['mod_time'];
                                    $title =        $InfoPlan['title'];
                                    $initial_state =$InfoPlan['state'];
                                    $internal_status=$InfoPlan['status'];
                                    $next_time      =$InfoPlan['next_time'];
                                    $setback =      $InfoPlan['setback'];
                                    $setback_time = $InfoPlan['setback_time'];
                                    $at =           $InfoPlan['at'];
                                    $hid =          $InfoPlan['history_id'];
                                    $spooler =      $InfoPlan['spooler'];
                                    
                                    $next = $at;
                                }
                                else {
                                     $at = '';   
                                }
                                
                                $color = 'black';
                                if ($status == 'RUNNING') {
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                    else {
                                         $color = 'orange';
                                         $next_time = $at;
                                    }
                                }
                                else {
                                    if($status == "SUCCESS")
                                    {
                                        $color = '#ccebc5'; 
                                        if ($nb_err>0) {
                                            // warning
                                            $status = 'WARNING';
                                            $color = '#ffffcc'; 
                                        }
                                    } elseif($status == "RUNNING")
                                    {
                                        $color = '#ffffcc';
                                    } elseif($status == "FAILURE")
                                    {
                                        $color = '#fbb4ae';
                                    } 
                                    // si suspendu, on garde quand meme la couleur de fond
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $color = '#fbb4ae';
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                }
                                $rowid = $order_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }
                                $cell .= '<row id="'.$order_id.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
                                // Est ce qu'il est aussi planifié ?
                                if (isset($Planned[$i])) {
                                    $cell .=  '<userdata name="type">order</userdata>';
                                }
                                else {
                                    // juste a la volée
                                    $cell .=  '<userdata name="type">order add</userdata>';                                   
                                }
                                // attention, il y a le statique et le dynamique
                                if ($title == '') {
                                    $cell .= '<cell image="'.$order_icon.'.png">'.' '.str_replace('£','/',$here).'</cell>';
                                }
                                else {
                                    $cell .= '<cell image="'.$order_icon.'.png"><![CDATA['.str_replace('£','/',$here).' <font color="grey">('.$title.')</font>]]></cell>';
                                    // print $title; exit();
                                }
                                
                                $cell .= '<cell> '.$this->XMLProtect($state).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                list($start_str,$end_str,$next_str,$duration) = $date->getLocaltimes( $start_time, $end_time, substr($next_time,0,16), $spooler ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                              $cell .= '<cell>'.$end_str.'</cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),strtotime($end_time)).'</cell>';
                                $cell .= '<cell>';
                                if (isset($setback) && $setback>0) {
                                    $cell .= '['.$setback.']';
                                }
                                $cell .= $this->XMLProtect($state_text).'</cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                               
                            } 
                            elseif (isset($GlobalChain[$i])) {
                                $Info = $GlobalChain[$i];
                                $dbid =     $Info['dbid'];
                                $job_chain= $Info['chain'];
                                $status =   $Info['status'];
                                
                                $color = '#ccebc5';
                                if($status == "STOPPED")
                                {
                                    $color = '#FF0000';
                                }                                
				$chain_id = "N#".$dbid;
                                $rowid = $chain_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }

                                $cell .= '<row id="'.$chain_id.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
                                $cell .= '<userdata name="type">nested_job_chain</userdata>';
                                $cell .= '<cell image="global_chain.png"> '.$here.'</cell>';
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
                            elseif (isset($NestedChain[$i])) {
                                // L'id est celui de SCHEDULER_ORDER_HISTORY
                                $Info = $NestedChain[$i];
                                $order_id =     $Info['dbid'];
                                $job_chain =    $Info['chain'];
                                $start_time =   $Info['start_time'];
                                $end_time =     $Info['end_time'];
                                $title =        $Info['title'];
                                $state =        $Info['state'];
                                $state_text =   $Info['state_text'];
                                $error =        $Info['error'];
                                $status =       $Info['status'];
                                $nb_err =       $Info['nb_err'];
                                $spooler =      $Info['spooler'];

                                // $order_id = str_replace('£','/',$order_id);
                                if (isset($Planned[$i])) {
                                    $order_icon = 'order';
                                }
                                else {
                                    $order_icon = 'job_chain';
                                }

                                // statut mis en base de données
                                $next = $next_time = '';
                                $internal_status = '';
                                if (isset($Planned[$i])) {
                                    // $order_id = 'O#'.$dbid; 
                                    $order_icon = 'order';
                                    $InfoPlan = $Planned[$i]; 
                                    $dbid =         $InfoPlan['dbid'];
                                    $job_chain =    $InfoPlan['chain'];
                                    $created_time = $InfoPlan['created'];
                                    $mod_time =     $InfoPlan['mod_time'];
                                    $title =        $InfoPlan['title'];
                                    $initial_state =$InfoPlan['state'];
                                    $internal_status=$InfoPlan['status'];
                                    $next_time      =$InfoPlan['next_time'];
                                    $setback =      $InfoPlan['setback'];
                                    $setback_time = $InfoPlan['setback_time'];
                                    $at =           $InfoPlan['at'];
                                    $hid =          $InfoPlan['history_id'];
                                    $spooler =      $InfoPlan['spooler'];

                                    $next = $at;
                                    // print '<userdata name="next">'.$dbid.'</userdata>';
                                }
                                else {
                                     $at = '';   
                                }
                                
                                $color = 'black';
                                if ($status == 'RUNNING') {
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $color = "lightsalmon";
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                    else {
                                         $color = 'orange';
                                         $next_time = $at;
                                    }
                                }
                                else {
                                    if($status == "SUCCESS")
                                    {
                                        $color = '#ccebc5'; 
                                        if ($nb_err>0) {
                                            // warning
                                            $status = 'WARNING';
                                            $color = '#ffffcc'; 
                                        }
                                        else {
                                            $open = '';
                                        }
                                    } elseif($status == "RUNNING")
                                    {
                                        $color = '#ffffcc';
                                    } elseif($status == "FAILURE")
                                    {
                                        $color = '#fbb4ae';
                                    } 
                                    // si suspendu, on garde quand meme la couleur de fond
                                    if ($internal_status == "SUSPENDED")
                                    {
                                        $status = 'SUSPENDED';
                                        $order_icon = 'order_suspended';
                                    }
                                    elseif ($internal_status == "SETBACK")
                                    {
                                        $status = 'SETBACK';
                                        $start_time =  $next_time;
                                        $next = $setback_time;
                                    }
                                }
                                $rowid = $order_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }                                
                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
                                // Est ce qu'il est aussi planifié ?
                                if (isset($Planned[$i])) {
                                    $cell .=  '<userdata name="type">order</userdata>';
                                }
                                else {
                                    // juste a la volée
                                    $cell .=  '<userdata name="type">order add</userdata>';                                   
                                }
                                // attention, il y a le statique et le dynamique
                                if ($title == '') {
                                    $cell .= '<cell image="'.$order_icon.'.png">'.' '.str_replace('£','/',$here).'</cell>';
                                }
                                else {
                                    $cell .= '<cell image="'.$order_icon.'.png"><![CDATA['.str_replace('£','/',$here).' <font color="grey">('.$title.')</font>]]></cell>';
                                }

                                $cell .= '<cell>'.$this->XMLProtect($state).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                list($start_str,$end_str,$next_str,$duration) = $date->getLocaltimes( $start_time, $end_time, substr($next_time,0,16), $spooler ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                              $cell .= '<cell>'.$end_str.'</cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),strtotime($end_time)).'</cell>';
                                $cell .= '<cell>';
                                if (isset($setback) && $setback>0) {
                                    $cell .= '['.$setback.']';
                                }
                                $cell .= $this->XMLProtect($state_text).'</cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                               
                            } 
                            elseif (isset($Chain[$i])) {
                                $Info = $Chain[$i];
                                $dbid =     $Info['dbid'];
                                $job_chain= $Info['chain'];
                                $status =   $Info['status'];

                                $color = '#ccebc5';
                                if($status == "STOPPED")
                                {
                                    $color = '#FF0000';
                                }
				$chain_id = "C#".$dbid;
                                $rowid = $chain_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }

                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
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
                            elseif (isset($Task[$i])) {
                                $Info = $Task[$i];
                                $dbid =         $Info['dbid'];
                                $start_time=    $Info['start_time'];
                                $end_time =     $Info['end_time'];
                                $state =        $Info['state'];
                                $error_text =   $Info['error_text'];
                                $error =        $Info['error'];
                                $job_name =     $Info['job_name'];
                                $status =       $Info['status'];
                                $job_state =    $Info['job_state'];
                                $path =         $Info['chain'];
                                $spooler =      $Info['spooler'];
                                
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
                                $rowid = $task_id;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }                                
                                $action_step = $path.'/'.$this->XMLProtect($state);
                                if(isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="stop"){
                                    $color = 'red';
                                }
                                elseif (isset($ActionStep[$action_step]) && $ActionStep[$action_step]=="next_state"){
                                    $color = 'orange';
                                }
                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
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
                            elseif (isset($ActionStep[$i])) {
                                $sta = $ActionStep[$i];
                                $status = "";
                                $color = "#ccebc5";
                                if ($sta == 'next_state') {
                                    $status = "SKIPPED";
                                    $color = "orange";
                                }
                                elseif($sta == 'stop') {
                                    $status = "STOPPED";
                                    $color = "red";
                                }
                                $rowid = 'S#'.$this->XMLProtect($i);
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }                                
                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';">';
                                $cell .= '<cell>'.$i.'</cell>';
                                $cell .= '<userdata name="type">action_step</userdata>';
                                $cell .= '<cell image="skipped_step.png"><![CDATA['.$here.']]></cell>';
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
                                $cell .= '<cell/>';
                                $cell .= '<cell/>';
                                $cell .= '<cell/>';
                                print "$cell\n";                               
                            }  
                            // Plus d'historique mais planifié
                            elseif (isset($Planned[$i])) {
                                    $InfoPlan = $Planned[$i]; 
                                    $dbid =         $InfoPlan['dbid'];
                                    $job_chain =    $InfoPlan['chain'];
                                    $created_time = $InfoPlan['created'];
                                    $mod_time =     $InfoPlan['mod_time'];
                                    $title =        $InfoPlan['title'];
                                    $initial_state =$InfoPlan['state'];
                                    $status=        $InfoPlan['status'];
                                    $next_time      =$InfoPlan['next_time'];
                                    $setback =      $InfoPlan['setback'];
                                    $setback_time = $InfoPlan['setback_time'];
                                    $at =           $InfoPlan['at'];
                                    $hid =          $InfoPlan['history_id'];
                                    $spooler =      $InfoPlan['spooler'];
                                    
                                $next = $at;
                                $start_time = $mod_time;
                                $order_icon = 'order';
                                if ($status=="SUSPENDED") {
                                    $color = 'red';
                                    $order_icon = 'order_suspended';
                                }
                                elseif ($status == "SETBACK")
                                {
                                    $color = "lightsalmon";
                                    $status = 'SETBACK';
                                    $start_time =  $next_time;
                                    $next = $setback_time;
                                }
                                else {
                                    $color = 'lightblue';
                                }    
                                $rowid = 'O#'.$dbid;
                                if (isset($State[$rowid])) {
                                    if ($State[$rowid]==1)
                                        $open = ' open="1"';
                                }                                
                                $cell .= '<row id="'.$rowid.'" style="background-color: '.$color.';"'.$open.'>';
                                $cell .= '<cell>'.$i.'</cell>';
                                $cell .= '<userdata name="type">order planned</userdata>';
                                // attention, il y a le statique et le dynamique
                                if ($title == '') {
                                    $cell .= '<cell image="'.$order_icon.'.png">'.' '.str_replace('£','/',$here).'</cell>';
                                }
                                else {
                                    $cell .= '<cell image="'.$order_icon.'.png"><![CDATA['.str_replace('£','/',$here).' <font color="grey">('.$title.')</font>]]></cell>';
                                    // print $title; exit();
                                }
                                $planned_id = "O#".$dbid;

                                $cell .= '<cell>'.$this->XMLProtect($initial_state).'</cell>';
                                $cell .= '<cell/>';
                                $cell .= '<cell>'.$status.'</cell>';
                                if ($status!='SUSPENDED') {
                                    $cell .= '<cell>ACT.</cell>';
                                }
                                else {
                                    $cell .= '<cell>STOP</cell>';
                                }
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                list($start_str,$end_str,$next_str) = $date->getLocaltimes( $start_time, '', $next, $spooler ); 
                                $cell .= '<cell>'.$start_str.'</cell>';
                                $cell .= '<cell></cell>';
//                                $cell .= '<cell>'.$this->Duration(strtotime($start_time),time()).'</cell>';
                                $cell .= '<cell>'.$next_str.'</cell>';
                                print "$cell\n";                               
                            }                            
                            else {
                                    if ($id == '') {
                                        // cas du spooler 
                                        $icon = 'spooler';
                                        $col = '#ccebc5';
                                        // $open = ' open="1"';
                                        if (isset($Spoolers[$i])) {
                                            $Info = $Spoolers[$i];
                                            $hostname =     $Info['hostname'];
                                            $tcp_port =     $Info['port'];
                                            $start_time=    $Info['start_time'];
                                            $is_running =   $Info['is_running'];
                                            $is_paused =    $Info['is_paused'];

                                            if ($is_paused==1) {
                                                $col = 'orange';
                                                $status = 'PAUSED';
                                            }
                                            elseif ($is_running==0) {
                                                $col = 'red';
                                                $status = 'DOWN';
                                                $open = '';
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
                                        $rowid = $i;
                                        if (isset($State[$rowid])) {
                                            if ($State[$rowid]==1)
                                                $open = ' open="1"';
                                        }    

                                        $cell .= '<row id="'.$rowid.'" style="background-color: '.$col.';"'.$open.'>';
                                        $cell .= '<cell/>';
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
                                        print $cell;
                                    }
                                    else {
                                        $rowid = $i;
                                        if (isset($State[$rowid])) {
                                            if ($State[$rowid]==1)
                                                $open = ' open="1"';
                                        }                                                                      
                                        $cell .= '<row id="'.$i.'"'.$open.'>';
                                        $cell .= '<cell/>';
					$cell .= '<userdata name="type">folder</userdata>';
                                        $cell .= '<cell image="folder.gif">'.$here.'</cell>';
                                        if (isset($Path[$i])) {
                                            $cell .= '<cell>'.$Path[$i].'</cell>';
                                        }
                                        print $cell;
                                    }
                            }
                           $this->Orders2XML( $leaf[$k], $id.'/'.$k, $Order, $Chain, $Task,$Planned, $Nb, $ChainStopped, $JobStopped, $ActionStep, $Spoolers, $GlobalChain, $GlobalOrder, $NestedChain, $Path, $State );
                           print "</row>\n";
                    }
            }
    }
    
    private function XMLProtect ($txt) {
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
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_chain}'   => 'sh.JOB_CHAIN',
//            '{order}'   => 'sh.ORDER_ID',
            '{start_time}' => 'sh.START_TIME' );

        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');

        $qry = $sql->Select(array('sh.ORDER_ID','sh.HISTORY_ID','sh.SPOOLER_ID','sh.JOB_CHAIN','sh.START_TIME','sh.END_TIME','sh.STATE' ))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_CHAIN','sh.START_TIME desc'));  
   
        $res = $data->sql->query( $qry );
        $Chains = $Orders = array();
        
        while ( $line = $data->sql->get_next($res) ) {
            $id  =  $line['HISTORY_ID'];
            $chain = "/".$line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'];
            $dir = $chain.'/'.$line['ORDER_ID'];
            
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
                '{job_chain}'   => 'PATH' /*,
                '{order}'   => 'sh.ID'*/ );
            $qry = $sql->Select(array('SPOOLER_ID','PATH' ))
                    .$sql->From(array('SCHEDULER_JOB_CHAINS'))
                    .$sql->Where($Fields);  

              $res = $data->sql->query( $qry );
              while ( $line = $data->sql->get_next($res) ) {
                $dir = '/'.$line['SPOOLER_ID'].'/'.$line['PATH'];
                
                $Chains[$dir]='STOPPED';
            }
                
        /*
             print_r($Info);
            exit();
        */
        
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
                                    $return .= '<item style="background-color: #red;" id="'.$i.'" text="'.basename($i).'" im0="job_chain.png" im1="job_chain.png" open="1">';
                                else
                                    $return .= '<item id="'.$i.'" text="'.basename($i).'" im0="job_chain.png" im1="job_chain.png" open="1">';
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
                                $return .= '<item'.$style.' id="O:'.$Orders[$i]['HISTORY_ID'].'" text="'.basename($i).$detail.'" im0="order.png" im1="order.png">';
                            }
                            elseif ($id == '' ) {
                                
                                $return .= '<item id="'.$i.'" text="'.basename($i).'" im0="cog.png" im1="cog.png"  open="1">';
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


}
