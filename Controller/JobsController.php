<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class JobsController extends Controller
{
    protected $images;
    protected $ColorStatus = array (
            'SUCCESS' => '#ccebc5',
            'RUNNING' => '#ffffcc',
            'FAILURE' => '#fbb4ae',
            'STOPPED' => '#FF0000',
            'QUEUED' => '#AAA',
            'STOPPING' => '#ffffcc',
            'UNKNOW' => '#BBB'
        );
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../bundles/ariicore/images/wa');          
    }

    public function indexAction()
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
        if ($step == 0) $step = 1;
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
/*        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'START_TIME' );

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
*/
        return $this->render('AriiJIDBundle:Jobs:index.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:toolbar.xml.twig',array(), $response );
    }

    public function folder_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:folder_toolbar.xml.twig',array(), $response );
    }

    public function grid_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:grid_toolbar.xml.twig',array(), $response );
    }

    public function formAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $this->render('AriiJIDBundle:Jobs:form.json.twig',array(), $response );
    }

    public function form2Action()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:form.xml.twig',array(), $response );
    }

    public function form_toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:form_toolbar.xml.twig',array(), $response );
    }

    public function grid_menuAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Jobs:grid_menu.xml.twig',array(), $response );
    }

    public function treeAction() {
        // en attendant le cache
        $request = Request::createFromGlobals();
        $ordered = $request->get('ordered');
        $stopped = $request->get('stopped');
        
        $folder = 'live';
        // $this->syncAction($folder);
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
   /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME' );
        if ($ordered!='true') {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');

        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR' ))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc'));  
        
        $res = $data->sql->query( $qry );
        $Info = array();
        $key_files = array();
        while ( $line = $data->sql->get_next($res) ) {
            $id  =  $line['ID'];
            $dir = "/".$line['SPOOLER_ID'].'/'.dirname($line['JOB_NAME']);
            if ($line['ERROR']>0) {
                if (isset($Info[$dir]['errors'])) 
                    $Info[$dir]['errors']++;
                else 
                    $Info[$dir]['errors']=1;
            }
            // On ccompte les erreurs
            $key_files[$dir] = $dir;
        }
        
        // Prend on en compte les stopped ?
            $Fields = array (
                '{spooler}'    => 'sh.SPOOLER_ID', 
                '{job_name}'   => 'sh.PATH',
                'sh.STOPPED'    => 1 );
            $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH' ))
                    .$sql->From(array('SCHEDULER_JOBS sh'))
                    .$sql->Where($Fields)
                    .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));  

              $res = $data->sql->query( $qry );
              while ( $line = $data->sql->get_next($res) ) {
                $dir = '/'.$line['SPOOLER_ID'].'/'.dirname($line['PATH']);
                if (isset($Info[$dir]['stopped'])) 
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
            
    public function gridAction($history_max=0,$ordered = 0,$stopped=1) {

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        $ordered = $request->get('chained');
        $stopped = $request->get('only_warning');

        $history = $this->container->get('arii_jid.history');
        $Jobs = $history->Jobs(0, $ordered, $stopped, false);
        
        $tools = $this->container->get('arii_core.tools');
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        $list .= '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        ksort($Jobs);
        foreach ($Jobs as $k=>$job) {
            if (isset($job['runs'])) {
                $status = $job['runs'][0]['status'];
            }
            else {
                $status = 'UNKNOW';
            } 
            $list .='<row id="'.$job['runs'][0]['dbid'].'" style="background-color: '.$this->ColorStatus[$status].'">';

            $list .='<cell>'.$job['spooler'].'</cell>';              
            $list .='<cell>'.$job['folder'].'</cell>'; 
            $list .='<cell>'.$job['name'].'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell>'.$this->images.'/'.strtolower($status).'.png</cell>'; 
            if (isset($job['runs'])) {
                $list .='<cell>'.$job['runs'][0]['start'].'</cell>'; 
                $list .='<cell>'.$job['runs'][0]['end'].'</cell>'; 
                $list .='<cell>'.$job['runs'][0]['duration'].'</cell>';
                $list .='<cell>'.$job['runs'][0]['exit'].'</cell>';
                $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt($job['runs'][0]['start'],$job['runs'][0]['end'],$status).'"/>]]></cell>'; 
                $list .='<cell>'.$job['runs'][0]['pid'].'</cell>';
                $list .='<cell>'.$this->images.'/'.strtolower($job['runs'][0]['cause']).'.png</cell>'; 
            }
            $list .='</row>';
        }
        
        $list .= "</rows>\n";
        $response->setContent( $list );
        return $response;
    }

    public function grid2Action($history_max=0,$ordered = 'false',$stopped='false') {
        $color = array (
            'SUCCESS' => '#ccebc5',
            'RUNNING' => '#ffffcc',
            'FAILURE' => '#fbb4ae',
            'STOPPED' => '#FF0000',
            'QUEUED' => '#AAA',
            'STOPPING' => '#ffffcc'
        );

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        if ($request->get('ordered')=='true') {
            $ordered = $request->get('ordered');
        }
        if ($request->get('stopped')=='true') {
            $stopped = $request->get('stopped');
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $session = $this->container->get('arii_core.session');
        $sql = $this->container->get('arii_core.sql');
        $tools = $this->container->get('arii_core.tools');
        $date = $this->container->get('arii_core.date');
        $Status = $session->get('status');

/* On stocke les états */
        // si on est en stopped
        $stopped = 'true';
        if ($stopped == 'true') {
            $Fields = array (
            '{spooler}'    => 'sh.SPOOLER_ID',
            '{job_name}'   => 'sh.PATH' );

                $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                        .$sql->From(array('SCHEDULER_JOBS sh'))
                        .$sql->Where($Fields)
                        .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));

            $res = $data->sql->query( $qry );
            $n=0;
            while ($line = $data->sql->get_next($res)) {
                 $jn = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
                 if ($line['STOPPED']=='1' ) {
                     $Stopped[$jn] = true;
                 }
                 if ($line['NEXT_START_TIME']!='' )
                     $Next[$jn] = $line['NEXT_START_TIME'];
                 $n++;
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
        
    /* On prend l'historique */
        $Fields = array (
           '{spooler}'    => 'sh.SPOOLER_ID', 
            '{job_name}'   => 'sh.JOB_NAME',
            '{error}'      => 'sh.ERROR',
            '{start_time}' => 'sh.START_TIME',
            '{!(spooler)}' => 'sh.JOB_NAME' );
        if ($ordered==0) {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }
        $qry = $sql->Select(array('sh.ID','sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME','sh.END_TIME','sh.ERROR','sh.EXIT_CODE','sh.CAUSE','sh.PID'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->Where($Fields)
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.JOB_NAME','sh.START_TIME desc'));  

        $res = $data->sql->query( $qry );
        $nb=0;
        $H = array();
        while ($line = $data->sql->get_next($res)) {
            $nb++;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($H[$id])) {
                if ($line['END_TIME']!='')
                    $H[$id]++;
            }
            else {
                $H[$id]=0;
            }
            if ($H[$id]>$history_max) {
                continue;
            }
            
            if (isset($Stopped[$id]) and ($Stopped[$id]==1)) {
                if ($line['END_TIME']=='')
                    $status = 'STOPPING';
                else
                    $status = 'STOPPED';
            }
            elseif ($line['END_TIME']=='') {
                $status = 'RUNNING';
            } // cas des historique
            elseif ($line['ERROR']>0) {
                $status = 'FAILURE';
            }
            else {
                $status = 'SUCCESS';
            }
            $list .='<row id="'.$line['ID'].'" style="background-color: '.$color[$status].'">';
            // Cas particulier pour les RUNNING
            $list .='<cell>'.$line['SPOOLER_ID'].'</cell>';              
            $list .='<cell>'.dirname($line['JOB_NAME']).'</cell>'; 
            $list .='<cell>'.basename($line['JOB_NAME']).'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell>'.$this->images.'/'.strtolower($status).'.png</cell>'; 
            if ($status=='RUNNING') {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$start.'</cell>'; 
                $list .='<cell/>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            else {
                list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],$line['END_TIME'],'', $line['SPOOLER_ID'], false  );                                     
                $list .='<cell>'.$date->ShortDate($start).'</cell>'; 
                $list .='<cell>'.$date->ShortDate($end).'</cell>'; 
                $list .='<cell>'.$duration.'</cell>';
            }
            $list .='<cell>'.$line['EXIT_CODE'].'</cell>';
            $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt($start,$end,$status).'"/>]]></cell>'; 
            $list .='<cell>'.$line['PID'].'</cell>';
            $list .='<cell>'.$this->images.'/'.strtolower($line['CAUSE']).'.png</cell>'; 
            $list .='</row>';
        }
        
    /* On prend les taches en file d'attente */
        $Fields = array (
           '{spooler}'    => 'st.SPOOLER_ID', 
            '{job_name}'   => 'st.JOB_NAME',
            '{!(spooler)}' => 'st.JOB_NAME' );
        $qry = $sql->Select(array('st.TASK_ID as ID','st.SPOOLER_ID','st.JOB_NAME','st.START_AT_TIME as START_TIME','st.TASK_XML'))
                .$sql->From(array('SCHEDULER_TASKS st'))
                .$sql->Where($Fields)
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
//                .$sql->LeftJoin('SCHEDULER_TASKS st',array('sh.ID','st.TASK_ID'))
                .$sql->OrderBy(array('st.SPOOLER_ID','st.JOB_NAME','st.START_AT_TIME desc'));  

        $res = $data->sql->query( $qry );
        $H = array();
        while ($line = $data->sql->get_next($res)) {
            $nb++;
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            
            $status = 'QUEUED';
            $list .='<row id="'.$line['ID'].'" style="background-color: '.$color[$status].'">';
            // Cas particulier pour les RUNNING
            $list .='<cell>'.$line['SPOOLER_ID'].'</cell>';              
            $list .='<cell>'.dirname($line['JOB_NAME']).'</cell>'; 
            $list .='<cell>'.basename($line['JOB_NAME']).'</cell>';           
            $list .='<cell>'.$status.'</cell>'; 
            $list .='<cell>'.$this->images.'/'.strtolower($status).'.png</cell>'; 
            list($start,$end,$next,$duration) = $date->getLocaltimes( $line['START_TIME'],gmdate("Y-M-d H:i:s"),'', $line['SPOOLER_ID'], false  );                                     
            $list .='<cell>'.$date->ShortDate($start).'</cell>'; 
            $list .='<cell/>'; 
            $list .='<cell/>';
            $list .='<cell/>';
            $list .='<cell><![CDATA[<img src="'.$this->generateUrl('png_JID_gantt').'?'.$tools->Gantt(gmdate("Y-M-d H:i:s"),$start,$status).'"/>]]></cell>'; 
            $list .='<cell/>';
            $list .='<cell>'.$this->images.'/queue.png</cell>'; 
            $list .='</row>';
        }

        if ($nb==0) {
            exit();
        }
        
        $list .= "</rows>\n";
        $response->setContent( $list );
        return $response;
    }
    
    public function pieAction($history_max=0,$ordered = 0,$only_warning=1) {        
        $color = array (
            'SUCCESS' => '#ccebc5',
            'RUNNING' => '#ffffcc',
            'FAILURE' => '#fbb4ae',
            'STOPPED' => '#FF0000',
            'QUEUED' => '#AAA',
            'STOPPING' => '#ffffcc'
        );

        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history_max = $request->get('history');
        }
        $ordered = $request->get('chained');
        $only_warning = $request->get('only_warning');

        $history = $this->container->get('arii_jid.history');
        $Jobs = $history->Jobs(0, $ordered, $only_warning, false);

        $stopped=$success=$failure=$running=0;
        foreach ($Jobs as $k=>$job) {
            if (isset($job['stopped'])) {
                $stopped += 1; 
            }
            if (isset($job['runs'][0]['status'])) {
                $status = $job['runs'][0]['status'];
                switch ($status) {
                    case 'SUCCESS':
                        $success += 1;
                        break;
                    case 'FAILURE':
                        if (!$stopped)
                            $failure += 1;
                        break;
                    case 'RUNNING':
                        $running += 1;
                        break;
                }
            }
        }
        
        $pie = '<data>';
        $pie .= '<item id="SUCCESS"><STATUS>SUCCESS</STATUS><JOBS>'.$success.'</JOBS><COLOR>#ccebc5</COLOR></item>';
        $pie .= '<item id="FAILURE"><STATUS>FAILURE</STATUS><JOBS>'.$failure.'</JOBS><COLOR>#fbb4ae</COLOR></item>';
        $pie .= '<item id="STOPPED"><STATUS>STOPPED</STATUS><JOBS>'.$stopped.'</JOBS><COLOR>red</COLOR></item>';
        $pie .= '<item id="RUNNING"><STATUS>RUNNING</STATUS><JOBS>'.$running.'</JOBS><COLOR>#ffffcc</COLOR></item>';
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
                .$sql->OrderBy(array('START_TIME desc'));

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

    
    public function pieFullAction($ordered=0)
    {
        $request = Request::createFromGlobals();
        if ($request->get('ordered')=='true') {
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
        if ($ordered!='true') {
            $Fields['{standalone}'] = 'sh.CAUSE';
        }        
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
        $pie .= '<item id="SUCCESS"><STATUS>SUCCESS</STATUS><JOBS>'.$success.'</JOBS><COLOR>#ccebc5</COLOR></item>';
        $pie .= '<item id="FAILURE"><STATUS>FAILURE</STATUS><JOBS>'.$failure.'</JOBS><COLOR>#fbb4ae</COLOR></item>';
        $pie .= '<item id="STOPPED"><STATUS>FAILURE</STATUS><JOBS>'.$stopped.'</JOBS><COLOR>red</COLOR></item>';
        $pie .= '<item id="RUNNING"><STATUS>RUNNING</STATUS><JOBS>'.$running.'</JOBS><COLOR>#ffffcc</COLOR></item>';
        $pie .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $pie );
        return $response;
    }

    public function timelineAction($history=0,$ordered=false)
    {
        $request = Request::createFromGlobals();
        if ($request->get('history')>0) {
            $history = $request->get('history');
        }
        if ($request->get('ordered')=='true') {
            $ordered = true;
        }

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->getRefDate();
        $xml = '<data>';

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{!(spooler)}' => 'JOB_NAME',
            '{spooler}'    => 'SPOOLER_ID',
            '{job_name}'   => 'JOB_NAME',
            '{error}'      => 'ERROR',
            '{start_time}' => 'START_TIME' );
        if (!$ordered) {
            $Fields['{standalone}'] = 'CAUSE';
        }

        // le passé
        $qry = $sql->Select(array('ID','SPOOLER_ID','JOB_NAME','START_TIME','END_TIME','ERROR','EXIT_CODE','CAUSE','PID'))  
                  .$sql->From(array('SCHEDULER_HISTORY'))
                  .$sql->Where($Fields)
                  .$sql->OrderBy(array('SPOOLER_ID','JOB_NAME','START_TIME desc'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            if (isset($Nb[$id])) {
                $Nb[$id]++;
            }
            else {
                 $Nb[$id]=0;
            }
            if ($Nb[$id]>$history) continue;
            $xml .= '<event id="'.$line['ID'].'">';
            $xml .= '<start_date>'.$line['START_TIME'].'</start_date>';
            $textColor='yellow';
            if ($line['END_TIME']!='') {
                $xml .= '<end_date>'.$line['END_TIME'].'</end_date>';
                if ($line['ERROR']==0) {
                   if ($line['CAUSE']=='order') {
                       $color= 'lightgreen';
                   }
                   else {
                        $color= '#ccebc5';
                   }
                }
                else {
                   if ($line['CAUSE']=='order') {
                       $color= 'lightred';
                   }
                   else {
                        $color='#fbb4ae'; 
                   }
                }
                $xml .= '<text>'.$line['JOB_NAME'].' (exit '.$line['EXIT_CODE'].')</text>';
            }
            else {
                $xml .= '<end_date>'.gmdate("Y-M-d H:i:s").'</end_date>';
                $color = '#ffffcc';
                $xml .= '<text>'.$line['JOB_NAME'].' (pid '.$line['PID'].')</text>';
            }
            $xml .= '<section_id>'.$line['SPOOLER_ID'].'</section_id>';
            $xml .= '<color>'.$color.'</color>';
          //  $xml .= '<textColor>'.$textColor.'</textColor>';
            $xml .= '</event>';
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');        
        $response->setContent( $xml );
        return $response;    
    }

}