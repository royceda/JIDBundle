<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class HistoryController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');          
    }

    // Index des traitements
    // Si GPL -> Suivi
    // Si PRO -> Liste 
    public function indexAction()
    {
      $arii_pro = $this->container->getParameter('arii_pro');
      if ($arii_pro === true) 
        return $this->render('AriiJIDBundle:History:list.html.twig' );
      return $this->render('AriiJIDBundle:History:activities.html.twig' );
    }

    public function listAction()
    {
        return $this->render('AriiJIDBundle:History:list.html.twig' );
    }

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'xml/application');
        return $this->render('AriiJIDBundle:History:toolbar.xml.twig',array(), $response );
    }

    public function toolbar_timelineAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:History:toolbar_timeline.xml.twig',array(), $response );
    }

    public function activitiesAction()
    {
        return $this->render('AriiJIDBundle:History:activities.html.twig' );
    }

    public function menuAction()
    {
        return $this->render('AriiJIDBundle:History:menu.xml.twig');
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
            'ID'            => $id );

        $qry = $sql->Select(array('*')) 
               .$sql->From(array('SCHEDULER_HISTORY'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array('SPOOLER_ID'));
        
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
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

        $qry = $sql->Select('SPOOLER_ID','distinct') 
               .$sql->From('SCHEDULER_HISTORY')
               .' where '.$sql->History($Fields)
               .$sql->OrderBy(array('SPOOLER_ID'));

        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        
        return $this->render('AriiJIDBundle:History:charts.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function jobAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_NAME')) 
                .$sql->From(array('SCHEDULER_HISTORY h'))
                .$sql->Where(array('h.ID'=>$id));
        $res = $data->sql->query( $qry );
        $Infos = $data->sql->get_next($res);
        
        return $this->render('AriiJIDBundle:History:job.html.twig', 
                array('id' => $id, 'spooler' => $Infos['SPOOLER_ID'], 'job' => $Infos['JOB_NAME'] ) );
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
        // Pas de future dans l'historique
        // $future = $session->getRefFuture();
        $future = gmdate("Y-M-d H:i:s");

        // On prend 24 fuseaux entre maintenant et le passe
        // on trouve le step en minute
        $step = ($future-$past)*2.5; // heure * 60 minutes / 24 fuseaux
        if ($step == 0) $step = 1;
        $Timeline['step'] = $step;
        $Timeline['step'] = 60;
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['start']=0;
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

    $qry = $sql->Select(array('SPOOLER_ID'),'distinct') 
               .$sql->From(array('SCHEDULER_HISTORY'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array( 'SPOOLER_ID' ));

    $SPOOLERS = array();
        if ($data) {
            $res = $data->sql->query( $qry );
            while ($line = $data->sql->get_next($res)) {
                array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
            }
        }
        $Timeline['spoolers'] = $SPOOLERS;
        return $this->render('AriiJIDBundle:History:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }


    public function pieAction($ordered=0)
    {
        $request = Request::createFromGlobals();
        if ($request->get('ordered')>0) {
            $ordered = $request->get('ordered');
        }
       
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME',
            '{!(spooler)}' => 'JOB_NAME' );
        if ($ordered==0) 
            $Fields['{standalone}'] = 'CAUSE';
        else 
            $Fields['{ordered}'] = 'CAUSE';
        
        $qry = $sql->Select(array('count(ID) as NB','getdate(START_TIME) as START_TIME','getdate(END_TIME) as END_TIME','ERROR'))
        .$sql->From(array('SCHEDULER_HISTORY'))
        .$sql->Where($Fields)
        .$sql->GroupBy(array('getdate(START_TIME)','getdate(END_TIME)','ERROR'));

        $res = $data->sql->query( $qry );
        $running = $success = $failure = 0;
        while ($line = $data->sql->get_next($res)) {
            $nb = $line['NB']; 
            if ($line['END_TIME'] == '') {
                $running+=$nb;
            }
            elseif ($line['ERROR']==0) {
                $success+=$nb;
            }
            else {
                $failure+=$nb;
            }
        }
        $pie = '<data>';
        $pie .= '<item id="1"><STATUS>SUCCESS</STATUS><JOBS>'.$success.'</JOBS><COLOR>#749400</COLOR></item>';
        $pie .= '<item id="2"><STATUS>FAILURE</STATUS><JOBS>'.$failure.'</JOBS><COLOR>red</COLOR></item>';
        $pie .= '<item id="3"><STATUS>RUNNING</STATUS><JOBS>'.$running.'</JOBS><COLOR>orange</COLOR></item>';
        $pie .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $pie );
        return $response;
    }

    public function statesAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{start_time}' => 'START_TIME',
            '{end_time}'   => 'END_TIME' );

        $qry = $sql->Select(array('count(HISTORY_ID) as NB','getdate(START_TIME) as START_TIME','getdate(END_TIME) as END_TIME','substring(STATE,0,1) as STATE','ERROR'))
        .$sql->From(array('SCHEDULER_ORDER_STEP_HISTORY'))
        .$sql->Where($Fields)
        .$sql->GroupBy(array('getdate(START_TIME)','getdate(END_TIME)','ERROR','substring(STATE,0,1)'));

        $res = $data->sql->query( $qry );
        $running = $success = $failure = $errors = 0;
        while ($line = $data->sql->get_next($res)) {
            $nb = $line['NB']; 
            if ($line['END_TIME'] == '') {
                $running+=$nb;
            }
            elseif ($line['ERROR']==0) {
                $success+=$nb;
            }
            else {
                if ($line['STATE']=='!')
                    $errors+=$nb;
                else 
                    $failure+=$nb;
            }
        }
        $pie = '<data>';
        $pie .= '<item id="1"><STATUS>SUCCESS</STATUS><JOBS>'.$success.'</JOBS><COLOR>#749400</COLOR></item>';
        $pie .= '<item id="2"><STATUS>FAILURE</STATUS><JOBS>'.$failure.'</JOBS><COLOR>#fbb4ae</COLOR></item>';
        $pie .= '<item id="3"><STATUS>RUNNING</STATUS><JOBS>'.$running.'</JOBS><COLOR>orange</COLOR></item>';
        $pie .= '<item id="4"><STATUS>ERROR</STATUS><JOBS>'.$errors.'</JOBS><COLOR>red</COLOR></item>';
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
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME',
            '{job_name}'   => 'JOB_NAME');

        $qry = $sql->Select(array('ID','START_TIME','END_TIME','ERROR','CAUSE','SPOOLER_ID'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('START_TIME'));

        $res = $data->sql->query( $qry );
        // Par jour 
        // Attention on est en heure GMT, il y a donc un décalage
        $date = $this->container->get('arii_core.date');
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $day = substr($date->Date2Local($line['START_TIME'],$line['SPOOLER_ID']),8,5);
            $Days[$day]=1;
            if ($line['CAUSE']=='order') {
                if ($line['END_TIME']='') {
                    if (isset($HRO[$day])) 
                        $HRO[$day]++;
                    else $HRO[$day]=1;
                }
                else {
                    if ($line['ERROR']==0) {
                        if (isset($HSO[$day])) 
                            $HSO[$day]++;
                        else $HSO[$day]=1;
                    }
                    else {
                        if (isset($HFO[$day])) 
                            $HFO[$day]++;
                        else $HFO[$day]=1;
                    }
                }
            }
            else {
                if ($line['END_TIME']='') {
                    if (isset($HR[$day])) 
                        $HR[$day]++;
                    else $HR[$day]=1;
                }
                else {
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
            }
        }
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        if (isset($Days)) {
            foreach($Days as $i=>$v) {
                if (!isset($HS[$i])) $HS[$i]=0;
                if (!isset($HF[$i])) $HF[$i]=0;
                if (!isset($HR[$i])) $HR[$i]=0;
                if (!isset($HSO[$i])) $HSO[$i]=0;
                if (!isset($HFO[$i])) $HFO[$i]=0;
                if (!isset($HRO[$i])) $HRO[$i]=0;
                $bar .= '<item id="'.$i.'"><HOUR>'.substr($i,-2).'</HOUR>';
                $bar .= '<SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE><RUNNING>'.$HR[$i].'</RUNNING>';
                $bar .= '<SUCCESS_ORDER>'.$HSO[$i].'</SUCCESS_ORDER><FAILURE_ORDER>'.$HFO[$i].'</FAILURE_ORDER><RUNNING_ORDER>'.$HRO[$i].'</RUNNING_ORDER>';
                $bar .= '</item>';
            }
        }
        $bar .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }

/******************************************************************/ 
    public function list_xmlAction($history_max=0,$ordered = 0)
    {

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        if ($request->get('ordered')>0) {
            $ordered = $request->get('ordered');
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $session = $this->container->get('arii_core.session');
        $sql = $this->container->get('arii_core.sql');
        $Status = $session->get('status');

        $Fields = array (
        '{spooler}'    => 'SCHEDULER_ID' );
        
        // on commence par le scheduler
        $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING','IS_PAUSED'))
               .$sql->From(array('SCHEDULER_INSTANCES'))
               .$sql->Where($Fields);

        $Spoolers = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
                # Creation des icones
                $spooler = $line['SPOOLER_ID'];
                $Spoolers[$spooler] = $line['HOSTNAME'].'|'.$line['TCP_PORT'].'|'.$line['START_TIME'].'|'.$line['IS_RUNNING'].'|'.$line['IS_PAUSED'];
        }

        $key_files = array();
        $Info = array();
/* On stocke les états */
        $Fields = array (
        '{spooler}'    => 'sh.SPOOLER_ID',
        '{job_name}'   => 'sh.PATH' );

            $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                    .$sql->From(array('SCHEDULER_JOBS sh'))
                    .$sql->Where($Fields)
                    .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));
        
            $res = $data->sql->query( $qry );
            $WhereStopped = array();
        $n=0;
        while ($line = $data->sql->get_next($res)) {
             $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
             if ($line['STOPPED']=='1' ) {
                 $Stopped[$jn] = true;
/*
                Tant pis pour les oubliés, il faut passer sur JOC 
                Une autre solution est de proposer un écran dédié
 
                 $key_files[$jn] = $jn;
                 array_push( $WhereStopped,
                         $sql->AndOr( array(  "SPOOLER_ID" => $line['SPOOLER_ID'], 
                                            "JOB_NAME" => $line['JOB_NAME'] ) 
                                ) );
*/
             }
             if ($line['NEXT_START_TIME']!='' )
                 $Next[$jn] = $line['NEXT_START_TIME'];
             $n++;
        }
        
/*
        $StopID = array();
        // Trop consommateur
        if (!empty($WhereStopped)) {
            // $qry = 'select max(ID) as ID from SCHEDULER_HISTORY where concat(SPOOLER_ID,"/",JOB_NAME) in ( "'.implode('","',array_keys($Stopped)).'" ) group by SPOOLER_ID,JOB_NAME';
            $qry = $sql->Select(array('max(ID) as ID'))
                    .$sql->From(array('SCHEDULER_HISTORY'))
                    .' where '.implode(' or ',$WhereStopped)
                    .$sql->GroupBy(array('SPOOLER_ID','JOB_NAME'));

            $res = $data->sql->query( $qry ); 
            while ($line = $data->sql->get_next($res)) {
                array_push($StopID,$line['ID']);
            }
        }
*/

        /* ATTENTION !!!
         *  On peut avoir des traces dans JOBS mais plus rien dans HISTORY
         * ==> voir purge history
         */
/* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME'
//            '{end_time}'   => 'sh.END_TIME'
                );
        if ($ordered==0) {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields);

/*
            if (count($StopID)>0) {
                $qry .= ' or '.$sql->Column('sh.ID').' in ('.implode(',',$StopID).')';
            }
*/
        $qry .= $sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc'));  
        
        // optimisation en prenant l'ID
        $qry =  $sql->Select(array('min(ID) as ID'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->where(array('{start_time}' => 'START_TIME' ));
        
        $res = $data->sql->query( $qry );
        $line = $data->sql->get_next($res);
        if (isset($line['ID'])) 
            $id = $line['ID'];
        else
            $id = 0;
$time = time();        
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{!(spooler)}' => 'sh.JOB_NAME',
            'ID|>=' => $id
//            '{end_time}'   => 'sh.END_TIME'
         );
        
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields);
 
        $qry .= $sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc')); 
 
        $res = $data->sql->query( $qry );
        $nb=0;
        while ($line = $data->sql->get_next($res)) {
            $nb++;

                $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];  
                if (isset($H[$jn])) {
                    $H[$jn]++;
                }
                else {
                    $H[$jn]=0;
                }               
                
                // Cas particulier pour les RUNNING
                if ($line['END_TIME']=='') {
                    // le jn est traité 
                    $Info[$jn] = '#'.$line['CAUSE'].'|'.$line['ID'];
                    // le nouveau jn prend en compte les instances
                    $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'].'/'.$line['PID'];                                    
                } // cas des historique
                elseif ($H[$jn]>0) {
                    if ($H[$jn]>$history_max) {
                        continue;
                    }
                    else {
                         $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'].'/#'.$H[$jn]; 
                    }
               }
                    
                // Doublons ?
                //if (isset($Info[$jn]))
                //    continue;
                $status = 'SUCCESS';
                if (isset($Stopped[$jn])) {
                    $stop = 1;
                }
                else {
                    $stop = 0;
                }
                if ($line['END_TIME'] == '') {
                    $status = 'RUNNING';
                }
                elseif ($line['ERROR']>0) {
                    $status = 'FAILURE';
                }
               
                $next = '';
                if (isset($Next[$jn]))
                    $next = $Next[$jn];
                // on oublie cette notion de statut
                $Info[$jn]= $line['ID'].'|'.$line['START_TIME'].'|'.$line['END_TIME'].'|'.$line['EXIT_CODE'].'|'.$line['ERROR'].'|'.$line['CAUSE'].'|'.$next.'|'.$status.'|'.$line['SPOOLER_ID'].'|'.$line['PID'].'|'.$stop;
                $key_files[$jn] = $jn;
        }
        // print "<pre>";  print_r($tree); print "</pre>"; exit();
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        if (count($key_files)==0) {
            $response->setContent( $this->NoRecord() );
            return $response;
        }
    
        $tools = $this->container->get('arii_core.tools');
        $tree = $tools->explodeTree($key_files, "/");

        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        /*implode(' ',$status)
        SUCCESS FAILURE
        strpos(SUCCESS )*/
        $session = $this->container->get('arii_core.session');
        $States = $session->get('state');
        if (!isset($States['history'])) $States['history'] =array();

        $list .= $this->History2XML( $tree, '', $Info, $Spoolers, $States['history'] );
        $list .= "</rows>\n";
        $response->setContent( $list );
        return $response;
    }

    function History2XML( $leaf, $id = '', $Info, $Spoolers, $State  ) {
        $date = $this->container->get('arii_core.date');        
            $color = array (
                'SUCCESS' => '#ccebc5',
                'RUNNING' => '#ffffcc',
                'FAILURE' => '#fbb4ae',
                'STOPPED' => '#FF0000'
                );
            $return = '';
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
                            # On ne prend que l'historique
                            if (isset($Info[$i])) {
                                $cell = '';
                                // cas du running
                                if (substr($Info[$i],0,1)=='#') {
                                    $open = ' open="1"';
                                    list( $cause, $dbid ) = explode('|',substr($Info[$i],1));
                                    $return .= '<row id="'.$dbid.'" style="background-color: '.$color['RUNNING'].';"'.$open.'>';
                                    $job_type = 'standalone_job';
                                    if ($cause == 'order') {
                                        $job_type = 'ordered_job';
                                    }
                                    //if ($here==0) {
                                    $cell .= '<cell>'.$i.'</cell>';
                                    $cell .= '<cell image="'.$job_type.'.png">'.$here.'</cell>';
                                    $cell .= '<cell>RUNNING</cell>';
                                    $cell .= '<cell/>';
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/running.png"/>]]></cell>';
                                    $return .= $cell;
                                }
                                // cas de l'historique
                                elseif (substr($here,0,1)=='#') {
                                    list($dbid, $start_time, $end_time, $exit_code, $error, $cause, $next_start_time, $status, $spooler, $pid, $stop ) = explode('|',$Info[$i]);
                                    if ($stop==0) {
                                        $return .= '<row id="'.$dbid.'#'.$pid.'" style="background-color: '.$color[$status].';"'.$open.'>';
                                    }
                                    else {
                                        $open = ' open="1"';
                                        if ($status=='SUCCESS')
                                            $style=' style="background-color: '.$color['SUCCESS'].'; color: red"';
                                        else
                                            $style=' style="background-color: '.$color['STOPPED'].'; color: yellow"';
                                        $return .= '<row id="'.$dbid.'#'.$pid.'"'.$style.$open.'>';
                                    }
                                    $cell .= '<cell>'.$i.'</cell>';
                                    $job_type = 'standalone_job';
                                    if ($cause == 'order') {
                                        $job_type = 'ordered_job';
                                    }
                                    # Infos cachées
                                    if ($status != 'RUNNING') {
                                        $cell .= '<cell image="database.png">'.substr($here,1).'</cell>';
                                        $cell .= '<userdata name="jobtype">'.$job_type.'</userdata>';
                                        $cell .= '<cell>'.$status.'</cell>';
                                        $cell .= '<cell>ACT.</cell>';
                                        $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                    }
                                    list($start,$end,$next,$duration) = $date->getLocaltimes( $start_time, $end_time, substr($next_start_time,0,16), $spooler ); 
                                    $cell .= '<cell>'.$start.'</cell>';
                                    $cell .= '<cell>'.$end.'</cell>';
                                    $cell .= '<cell>'.$duration.'</cell>';
                                    $cell .= '<cell>'.$exit_code.'</cell>';
                                    $cell .= '<cell>'.$next.'</cell>';
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($cause).'.png"/>]]></cell>';
                                    $return .= $cell;
                                }
                                else {
                                    list($dbid, $start_time, $end_time, $exit_code, $error, $cause, $next_start_time, $status, $spooler, $pid, $stop ) = explode('|',$Info[$i]);
                                    if ($stop==0) {
                                        $return .= '<row id="'.$dbid.'#'.$pid.'" style="background-color: '.$color[$status].';"'.$open.'>';
                                    }
                                    else {
                                        $open = ' open="1"';
                                        if ($status=='SUCCESS')
                                            $style=' style="background-color: '.$color['SUCCESS'].'; color: red"';
                                        else
                                            $style=' style="background-color: '.$color['STOPPED'].'; color: yellow"';
                                        $return .= '<row id="'.$dbid.'#'.$pid.'"'.$style.$open.'>';
                                    }
                                    $cell .= '<cell>'.$i.'</cell>';
                                    $job_type = 'standalone_job';
                                    if ($cause == 'order') {
                                        $job_type = 'ordered_job';
                                    }
                                    # Infos cachées
                                    if ($status != 'RUNNING') {
                                        $cell .= '<cell image="'.$job_type.'.png">'.$here.'</cell>';
                                        $cell .= '<userdata name="jobtype">'.$job_type.'</userdata>';
                                        $cell .= '<cell>'.$status.'</cell>';
                                        if ($stop==0) {
                                            $cell .= '<cell>ACT.</cell>';
                                            $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                        }
                                        else {
                                            $cell .= '<cell>STOP</cell>';
                                            $cell .= '<cell><![CDATA[<img src="'.$this->images.'/stopped.png"/>]]></cell>';
                                        }
                                        list($start,$end,$next,$duration) = $date->getLocaltimes( $start_time, $end_time, substr($next_start_time,0,16), $spooler ); 
                                    }
                                    else { 
                                        $cell .= '<cell image="cog.png">'.$here.'</cell>';
                                        $cell .= '<userdata name="jobtype">instance</userdata>';
                                        $cell .= '<cell></cell>'; // pas de statut
                                        $cell .= '<cell/>'; // donc pas d'image
                                        $cell .= '<cell/>'; // pas de stop
                                        $end = $next = '';    
                                        date_default_timezone_set("UTC"); 
                                        $duration =  $date->FormatTime(time()-strtotime($start_time));
                                        $start = $date->ShortDate($date->Date2Local( $start_time,$spooler));
                                    }
                                    $cell .= '<cell>'.$start.'</cell>';
                                    $cell .= '<cell>'.$end.'</cell>';
                                    $cell .= '<cell>'.$duration.'</cell>';
                                    $cell .= '<cell>'.$exit_code.'</cell>';
                                    $cell .= '<cell>'.$next.'</cell>';
                                    $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($cause).'.png"/>]]></cell>';
                                    $return .= $cell;
                                }
                            }
                            else {
                                    if ($id == '') {
                                        // cas du spooler 
                                        $icon = 'spooler';
                                        $col = '#ccebc5';
                                        $status = 'UNKNOWN';
                                        if (isset($Spoolers[$i])) {
                                            list($hostname, $tcp_port, $start_time, $is_running, $is_paused ) = explode('|',$Spoolers[$i]);                                            
                                            if ($is_paused==1) {
                                                $col = 'orange';
                                                $status = 'PAUSED';
                                            }
                                            elseif ($is_running==0) {
                                                $col = 'red';
                                                $status = 'DOWN';
                                            }
                                            else {
                                                $status = 'STARTED';
                                            }
                                        }
                                        else {
                                            $icon = 'error';
                                        }
                                        $return .=  '<row id="'.$i.'"'.$open.' style="background-color: '.$col.';">';
                                        $return .= '<cell>'.$i.'</cell>';
					$return .=  '<userdata name="type">spooler</userdata>';
                                        $return .=  '<cell image="'.$icon.'.png"><![CDATA[<b> '.$here.'</b>]]></cell>';
                                        $return .=  '<cell>'.$status.'</cell>';
                                        if ($status != 'UNKNOWN') {
                                            if ($status != 'STOPPED') {
                                                $return .= '<cell>ACT.</cell>';
                                                $return .=  '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                            }
                                            else {
                                                $return .= '<cell>STOP</cell>';
                                                $return .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                            }
                                            $return .=  '<cell>'.$start_time.'</cell>';
                                        }
                                    }
                                    else {
                                        $return .=  '<row id="'.$i.'" '.$open.'>';
                                        $return .= '<cell>'.$i.'</cell>';
					$return .=  '<userdata name="type">folder</userdata>';
                                        $return .=  '<cell image="folder.gif">'.$here.'</cell>';
                                    }
                            }
                           $return .= $this->History2XML( $leaf[$k], $id.'/'.$k, $Info, $Spoolers, $State );
                           $return .= '</row>';
                    }
            }
            return $return;
    }

    public function NoRecord()
    {
        $no = '<?xml version="1.0" encoding="UTF-8"?>';
        $no .= '
    <rows><head><afterInit><call command="clearAll"/></afterInit></head>
<row id="scheduler" open="1"><cell image="spooler.png"><b>No record </b></cell>
</row></rows>';
        return $no;
    }

   public function purgeAction( )
    {
        $request = Request::createFromGlobals();
        $Ids = explode('#',$request->get('job_id'));
        $id = $Ids[0];
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Delete(array('SCHEDULER_HISTORY'))
              .$sql->Where(array('ID' => $id));
        $res = $data->sql->query( $qry );
        if ($res>0)
            print $this->get('translator')->trans('Job purged');
        else 
            print $this->get('translator')->trans('ERROR !');
        exit();
    }
}
