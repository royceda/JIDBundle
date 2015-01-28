<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class EventsController extends Controller
{
    public function indexAction()   
    {
        return $this->render('AriiJIDBundle:Events:index.html.twig' );
    }

    public function activitiesAction()   
    {
        return $this->render('AriiJIDBundle:Events:activities.html.twig' );
    }

    public function toolbar_activitiesAction()
    {
        return $this->render('AriiJIDBundle:Events:toolbar_activities.xml.twig');
    }

    public function toolbar_timelineAction()
    {
        return $this->render('AriiJIDBundle:Events:toolbar_timeline.xml.twig');
    }

    public function pieAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('chart');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{start_time}' => 'CREATED',
            '{end_time}'   => 'EXPIRES' );

$qry = $sql->Select(array('EVENT_CLASS','count(ID) as NB'))
        .$sql->From(array('SCHEDULER_EVENTS'))
        .$sql->Where($Fields)
        .$sql->GroupBy(array('EVENT_CLASS'));
        return $data->render_sql($qry,"EVENT_CLASS","EVENT_CLASS,NB");
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
            '{start_time}' => 'CREATED',
            '{end_time}'   => 'CREATED' );

        $qry = $sql->Select(array('DISTINCT SPOOLER_ID')) 
                .$sql->From(array('SCHEDULER_EVENTS'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('SPOOLER_ID'));

        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        return $this->render('AriiJIDBundle:Events:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function barAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'sh1.SPOOLER_ID',
            '{start_time}' => 'sh1.START_TIME',
            '{end_time}'   => 'sh1.END_TIME' );

        $qry = $sql->Select(array('sh1.ID','sh1.START_TIME','sh1.END_TIME','sh1.ERROR'))
        .$sql->From(array('SCHEDULER_HISTORY sh1'))
        .$sql->Where($Fields)
        .$sql->OrderBy('sh1.END_TIME');

        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $day = substr($line['START_TIME'],8,5);
            $Days[$day]=1;
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
        $bar = "<?xml version='1.0' encoding='utf-8' ?>";
        $bar .= '<data>';
        if (isset($Days)) {
            foreach($Days as $i=>$v) {
                if (!isset($HS[$i])) $HS[$i]=0;
                if (!isset($HF[$i])) $HF[$i]=0;
                if (!isset($HR[$i])) $HR[$i]=0;
                $bar .= '<item id="'.$i.'"><HOUR>'.substr($i,-2).'</HOUR><SUCCESS>'.$HS[$i].'</SUCCESS><FAILURE>'.$HF[$i].'</FAILURE><RUNNING>'.$HR[$i].'</RUNNING></item>';
            }
        }
        $bar .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $bar );
        return $response;
    }
/******************************************************************/
    public function detailAction()
    {
        $request = Request::createFromGlobals();
        $id = intval($request->query->get( 'id' ));

        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $sql = $this->container->get('arii_core.sql');
        $data = $dhtmlx->Connector('data');
        
        $qry = $sql->Select(array('ID','EVENT_CLASS','EVENT_ID','CREATED','EXPIRES',
                    'SPOOLER_ID','REMOTE_SCHEDULER_HOST','REMOTE_SCHEDULER_PORT',
                    'JOB_CHAIN','ORDER_ID','JOB_NAME','EXIT_CODE'))
                .$sql->From(array('SCHEDULER_EVENTS'))
                .$sql->Where(array('ID'=>$id));
        try {
            $res = $data->sql->query( $qry );
        } catch (Exception $exc) {
            echo $exc;
            exit();
        }
        $Infos = $data->sql->get_next($res);

        if (!$Infos) {
            echo "$id ?!";
            exit();
        }
        return $this->render('AriiJIDBundle:Events:detail.html.twig', $Infos);
    }
/******************************************************************/
    public function eventsAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('grid');

        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('ID','SPOOLER_ID','JOB_CHAIN','ORDER_ID','JOB_NAME','EVENT_CLASS','EVENT_ID','EXIT_CODE','CREATED'))
               .$sql->From(array('SCHEDULER_EVENTS'))
               .$sql->OrderBy(array('EVENT_CLASS','EVENT_ID'));

        $res = $data->sql->query( $qry );
        header("Content-type: text/xml");
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print "<rows>\n";
        print '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        $IDS = '';
        $last = '';
        while ($line = $data->sql->get_next($res)) {
            # Creation des icones
            if ($line['EVENT_CLASS'] != $last) {
                if ($IDS!='') {
                    print '<row id="'.$last.'" open="1"><cell image="event.png">'.$last.'</cell>';
                    print $IDS;
                    print '</row>';
                    $IDS = '';
                }
            }
            $IDS .= '<row id="'.$line['ID'].'"><cell image="bullet_green.png">'.$line['EVENT_ID'].'</cell>';
            $IDS .= '<cell>'.$line['SPOOLER_ID'].'</cell>';
            $IDS .= '<cell>'.$line['JOB_CHAIN'].'</cell>';
            $IDS .= '<cell>'.$line['ORDER_ID'].'</cell>';
            $IDS .= '<cell>'.$line['JOB_NAME'].'</cell>';
            $IDS .= '<cell>'.$line['EXIT_CODE'].'</cell>';
            $IDS .= '<cell>'.$line['CREATED'].'</cell>';
            $IDS .= '</row>';
            $last = $line['EVENT_CLASS'];
        }
        if ($IDS!='') {
            print '<row id="'.$last.'" open="1"><cell image="event.png">'.$last.'</cell>'.$IDS.'</row>';
        }
        print "</rows>\n";
        exit();
    }
    
    public function lastAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $date = $this->container->get('arii_core.date');
        
        $qry = $sql->Select(array('ID','SPOOLER_ID','JOB_CHAIN','ORDER_ID','JOB_NAME','EVENT_CLASS','EVENT_ID','EXIT_CODE','CREATED'))
               .$sql->From(array('SCHEDULER_EVENTS'))
               .$sql->OrderBy(array('CREATED desc'));

        $res = $data->sql->query( $qry );
        header("Content-type: text/xml");
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print "<rows>\n";
        print '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        while ($line = $data->sql->get_next($res)) {
            if ($line['EXIT_CODE']==0) {
                $color = '#ccebc5';
            }
            else {
                $color = '#fbb4ae';
            }
            $IDS = '<row id="'.$line['ID'].'" style="background-color: '.$color.';">';
            $IDS .= '<cell>'.$line['SPOOLER_ID'].'</cell>';
            $IDS .= '<cell>'.$line['EVENT_CLASS'].'</cell>';
            $IDS .= '<cell>'.$line['EVENT_ID'].'</cell>';
            $IDS .= '<cell>'.$line['JOB_CHAIN'].'</cell>';
            $IDS .= '<cell>'.$line['ORDER_ID'].'</cell>';
            $IDS .= '<cell>'.$line['JOB_NAME'].'</cell>';
            $IDS .= '<cell>'.$line['EXIT_CODE'].'</cell>';
            $IDS .= '<cell>'.$line['CREATED'].'</cell>';
            $IDS .= '</row>';
            print $IDS;
        }
        print "</rows>\n";
        exit();
    }

}
