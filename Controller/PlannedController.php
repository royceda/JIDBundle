<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class PlannedController extends Controller
{
    protected $images;
    
    public function __construct( )
    {
          $request = Request::createFromGlobals();
          $this->images = $request->getUriForPath('/../arii/images/wa');
    }

    public function indexAction()
    {
      $arii_pro = $this->container->getParameter('arii_pro');
      if ($arii_pro === true) 
        return $this->render('AriiJIDBundle:Planned:treegrid.html.twig' );
      return $this->render('AriiJIDBundle:Planned:grid.html.twig' );
    }

    public function icalAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{job_name}'   => 'JOB',
/*            '{start_time}' => 'SCHEDULE_PLANNED',
            '{end_time}'   => 'SCHEDULE_PLANNED',
*/            'SCHEDULE_EXECUTED'   => '(null)' );

        $qry = $sql->Select(array( 'ID','SCHEDULER_ID','SCHEDULER_HISTORY_ID','SCHEDULER_ORDER_HISTORY_ID','JOB','JOB_CHAIN','ORDER_ID','SCHEDULE_PLANNED','STATUS','RESULT'))
                .$sql->From(array('DAYS_SCHEDULE'))
                .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        $Events = array();
        $Orders = array();
        $Jobs = array();
        while ($line = $data->sql->get_next($res)) {
            $event['ID'] = $line['ID'].'@'.$line['SCHEDULER_ID'];
            if ($line['JOB']!='') {
                $obj = ' JOB '.$line['JOB'];
                array_push($Jobs, $sql->AndOr(array(
                    'SPOOLER_ID' => $line['SCHEDULER_ID'],
                    'JOB_NAME' => substr($line['JOB'],1)
                        ))
                    );
               $event['TYPE'] = 'J';
               $event['DB'] = $line['SCHEDULER_ID'].$line['JOB'];
            }
            else {
                $obj = ' ORDER '.$line['ORDER_ID'].' ->'.$line['JOB_CHAIN'];
                array_push($Orders, $sql->AndOr(array(
                    'SPOOLER_ID' => $line['SCHEDULER_ID'],
                    'JOB_CHAIN' => substr($line['JOB_CHAIN'],1),
                    'ORDER_ID' => $line['ORDER_ID']
                        ))
                    );
               $event['TYPE'] = 'J';
               $event['DB'] = $line['SCHEDULER_ID'].$line['JOB_CHAIN'].'/'.$line['ORDER_ID'];
            }
            $event['SUMMARY'] = '['.$line['SCHEDULER_ID'].']'.$obj;
            $event['DTSTART'] = $line['SCHEDULE_PLANNED'];
            $event['DTEND'] = $line['SCHEDULE_PLANNED'];
            array_push($Events,$event);
        }
        
        // On va chercher les temps des jobs
        $qry = $sql->Select(array('SPOOLER_ID','JOB_NAME','avg(END_TIME-START_TIME) as DURATION'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .' where error = 0 and ('
                .implode(' OR ',$Jobs)
                .')'
                .$sql->GroupBy(array('SPOOLER_ID','JOB_NAME'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
            $Duration[$id] = round($line['DURATION']);
        }

        // On va chercher les temps des jobs
        $qry = $sql->Select(array('SPOOLER_ID','JOB_CHAIN','ORDER_ID','avg(END_TIME-START_TIME) as DURATION'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
                .' where ('
                .implode(' OR ',$Orders)
                .')'
                .$sql->GroupBy(array('SPOOLER_ID','JOB_CHAIN','ORDER_ID'));

        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            $id = $line['SPOOLER_ID'].'/'.$line['JOB_CHAIN'].'/'.$line['ORDER_ID'];
            $Duration[$id] = round($line['DURATION']);
        }

        $ical = $this->container->get('arii_core.cal');
        // $ical->VCalendar($Events);
          $ical = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
";
        foreach ($Events as $e) {
            $id = $e['ID'];
            $db = $e['DB'];
            if (isset($Duration[$db])) {
                $duration = $Duration[$db];
                $info = "($duration".'s)';
            }
            else {
                $duration = 3600;
                $info = "";
            }
            $ical .= "BEGIN:VEVENT
UID: ".$e['ID']."
DTSTAMP:" . gmdate('Ymd').'T'. gmdate('His') . "Z
DTSTART:".$this->tzformat($e['DTSTART'])."
DTEND:".$this->tzformat($e['DTSTART'],$duration)."
SUMMARY:".$e['SUMMARY']." $info
END:VEVENT
";
        }
$ical .= "END:VCALENDAR";

// set correct content-type-header
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=arii.ics');
echo $ical;
exit;
        
    }
public function tzformat($date,$plus=0) {
    if ($plus>0) {
        $time = strtotime($date);
        $date = date( 'Y-m-d H:i:s', $time + $plus);
    }
    $date = str_replace(array(' ',':','-'),array('T','',''),$date);
    return $date.'Z';
}

   public function listAction()
    {
        return $this->render('AriiJIDBundle:Planned:list.html.twig');
    }

   public function activitiesAction()
    {
        return $this->render('AriiJIDBundle:Planned:activities.html.twig');
    }
    
   public function toolbarAction()
    {
        return $this->render('AriiJIDBundle:Planned:toolbar.xml.twig');
    }

   public function toolbar_timelineAction()
    {
        return $this->render('AriiJIDBundle:Planned:toolbar_timeline.xml.twig');
    }

    public function toolbar_activitiesAction()
    {
        return $this->render('AriiJIDBundle:Planned:toolbar_activities.xml.twig');
    }

   public function menuAction()
    {
        return $this->render('AriiJIDBundle:Planned:menu.xml.twig');
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
    
        // on recalcule la date courante moins la plage de passé 
        $year = substr($ref_date, 0, 4);
        $month = substr($ref_date, 5, 2);
        $day = substr($ref_date, 8, 2);
        
        $start = substr($session->getPast(),11,2);
        $Timeline['start'] = (60/$step)*$start;
        $Timeline['js_date'] = $year.','.($month - 1).','.$day;
        
        $Timeline['step'] = 60;
        $Timeline['start'] = 0;
        
        $refresh = $session->GetRefresh();
        
        // Liste des spoolers pour cette plage
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{end_time}'   => 'SCHEDULE_EXECUTED' );

        $qry = $sql->Select(array('distinct SCHEDULER_ID as SPOOLER_ID'))
               .$sql->From(array('DAYS_SCHEDULE'))
               .$sql->where($Fields)
               .$sql->Order(array('SCHEDULER_ID'));

        $SPOOLERS = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
            array_push( $SPOOLERS,$line['SPOOLER_ID'] ); 
        }
        $Timeline['spoolers'] = $SPOOLERS;
        return $this->render('AriiJIDBundle:Planned:timeline.html.twig', array('refresh' => $refresh, 'Timeline' => $Timeline ) );
    }

    public function pieAction()
    {
       $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{job_name}'   => 'JOB',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{end_time}'   => 'SCHEDULE_PLANNED',
            '{status}'     => 'STATUS' );

        $qry = $sql->Select(array('ID','SCHEDULER_ID','SCHEDULER_HISTORY_ID','SCHEDULER_ORDER_HISTORY_ID','JOB','JOB_CHAIN','ORDER_ID','SCHEDULE_PLANNED','SCHEDULE_EXECUTED','PERIOD_BEGIN','PERIOD_END','IS_REPEAT','START_START','STATUS','RESULT'))
                .$sql->From(array('DAYS_SCHEDULE'))
                .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        $executed = $late = $waiting = 0;
        while ($line = $data->sql->get_next($res)) {
                if ($line['SCHEDULE_EXECUTED'] == '') {
                    if (strtotime($line['SCHEDULE_PLANNED'])<=time()) {
                        $late++;
                    }
                    else {
                        $waiting++;
                    }
                }
                else {
                    $executed++;
                }
        }
        $pie = "<?xml version='1.0' encoding='utf-8' ?>";
        $pie .= '<data>';
        $pie .= '<item id="1"><STATUS>EXECUTED</STATUS><JOBS>'.$executed.'</JOBS><COLOR>#749400</COLOR></item>';
        $pie .= '<item id="2"><STATUS>LATE</STATUS><JOBS>'.$late.'</JOBS><COLOR>red</COLOR></item>';
        $pie .= '<item id="3"><STATUS>WAITING</STATUS><JOBS>'.$waiting.'</JOBS><COLOR>purple</COLOR></item>';
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
            '{spooler}'    => 'SCHEDULER_ID',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{end_time}'   => 'SCHEDULE_EXECUTED' );

      $qry = $sql->Select(array('SCHEDULE_PLANNED','STATUS'))
              .$sql->From(array('DAYS_SCHEDULE'))
              .$sql->Where($Fields);
              
        $res = $data->sql->query( $qry );
        // Par jour 
        
        while ($line = $data->sql->get_next($res)) {
            # On recupere les heures
            $day = substr($line['SCHEDULE_PLANNED'],8,5);
            $Days[$day]=1;
            if ($line['STATUS']==0) {
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

    public function timeline_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('scheduler');

       $session =  $this->container->get('arii_core.session'); 
        $this->ref_date  =  $session->get('ref_date');

        $sql = $this->container->get('arii_core.sql');
        
        $Fields = array (
            '{spooler}'    => 'SPOOLER_ID',
            '{job_chain}'   => 'JOB_CHAIN',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{end_time}'   => 'SCHEDULE_EXECUTED' );

      $qry = $sql->Select(array('SCHEDULE_PLANNED','SCHEDULE_EXECUTED','JOB','JOB_CHAIN','STATUS')) 
            .$sql->From(array('DAYS_SCHEDULE'))
            .$sql->Where($Fields);

          $data->event->attach("beforeRender", array( $this, "color_rows") );
          $data->render_sql($qry,"ID","SCHEDULE_PLANNED,SCHEDULE_EXECUTED,JOB_CHAIN,color,section_id");
    }
    
    function color_rows($row){
        if ($row->get_value('SCHEDULE_EXECUTED')=='') {
            $row->set_value("color", 'orange');
            $row->set_value("SCHEDULE_EXECUTED", $this->ref_date );
        }
        elseif ($row->get_value('ERROR')>0) {
            $row->set_value("color", 'red');
        }
    }    

    public function list_xmlAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $tools = $this->container->get('arii_core.tools');   
        // on commence par le scheduler
        $sql = $this->container->get('arii_core.sql');
        $qry = $sql->Select(array('SCHEDULER_ID as SPOOLER_ID','HOSTNAME','TCP_PORT','START_TIME','IS_RUNNING','IS_PAUSED'))
               .$sql->From(array('SCHEDULER_INSTANCES'));

        $Spoolers = array();
        $res = $data->sql->query( $qry );
        while ($line = $data->sql->get_next($res)) {
                # Creation des icones
                $spooler = $line['SPOOLER_ID'];
                $Spoolers[$spooler] = $line['HOSTNAME'].'|'.$line['TCP_PORT'].'|'.$line['START_TIME'].'|'.$line['IS_RUNNING'].'|'.$line['IS_PAUSED'];
        }    
                
        $date = $this->container->get('arii_core.date');
        
        $sql = $this->container->get('arii_core.sql');
        $Fields = array (
            '{spooler}'    => 'SCHEDULER_ID',
            '{job|job_chain}'   => 'JOB|JOB_CHAIN',
            '{order_id}'   => 'ORDER_ID',
            '{start_time}' => 'SCHEDULE_PLANNED',
            '{status}'     => 'STATUS' );

        $qry = $sql->Select(array( 'ID','SCHEDULER_ID','SCHEDULER_HISTORY_ID','SCHEDULER_ORDER_HISTORY_ID','JOB','JOB_CHAIN','ORDER_ID','SCHEDULE_PLANNED','SCHEDULE_EXECUTED','PERIOD_BEGIN','PERIOD_END','IS_REPEAT','START_START','STATUS','RESULT'))
                .$sql->From(array('DAYS_SCHEDULE'))
                .$sql->Where($Fields);

        $res = $data->sql->query( $qry );
        $Type = array();
        while ($line = $data->sql->get_next($res)) {
                if ($line['JOB'] != '') {
                    $id = $line['SCHEDULER_ID'].$line['JOB'];
                    $Type[$id] = 'standalone_job';
                }
                else {
                    $id = $line['SCHEDULER_ID'].$line['JOB_CHAIN'];                   
                    $Type[$id] = 'job_chain';
                    $id .= '/'.$line['ORDER_ID'];                   
                    $Type[$id] = 'order';
                }
                $planned = $date->Date2Local($line['SCHEDULE_PLANNED'],$line['SCHEDULER_ID']);
                $id .= '/'.$planned;
                if ($line['SCHEDULE_EXECUTED']!='')
                    $executed = $date->Date2Local($line['SCHEDULE_EXECUTED'],$line['SCHEDULER_ID']);
                else 
                    $executed = '';
                if ($line['PERIOD_BEGIN']!='')
                    $period_begin = $date->Date2Local($line['PERIOD_BEGIN'],$line['SCHEDULER_ID']);
                else 
                    $period_begin = '';
                if ($line['PERIOD_END']!='')
                    $period_end = $date->Date2Local($line['PERIOD_END'],$line['SCHEDULER_ID']);
                else 
                    $period_end = '';
                $Info[$id]= $line['ID'].'|'.$line['STATUS'].'|'.$planned.'|'.$executed.'|'.$line['ORDER_ID'].'|'.$period_begin.'|'.$period_end.'|'.$line['IS_REPEAT'];
                $key_files[$id] = $id;
        }
        header("Content-type: text/xml");
        print '<?xml version="1.0" encoding="UTF-8"?>';
        print "<rows>\n";
        print '<head>
            <afterInit>
                <call command="clearAll"/>
            </afterInit>
        </head>';
        if (!isset($key_files)) {
            print "</rows>\n";
            exit();
        }
        $tree = $this->explodeTree($key_files, "/");


        $item = 0;

        print $this->Days_schedule2XML( $tree, '', $Info, $Spoolers, $Type );
        print "</rows>\n";
        exit();
        /*
        <rows> <userdata name="gud1"> userdata value 1 </userdata> <userdata name="gud2"> userdata value 2 </userdata> <row id="honda" selected="1" call="1" xmlkids="1"> <userdata name="hondaUD"> userdata value for honda </userdata> <cell image="folder.gif">Honda</cell> </row> <row id="bmw"> <cell image="folder.gif">BMW</cell> <row id="bmw1"> <userdata name="bmwUD1"> userdata value for bmw1 1 </userdata> <userdata name="bmwUD2"> userdata value for bmw1 2 </userdata> <cell image="leaf.gif">325i</cell> <cell>30,800</cell> <cell>2.5L</cell> <cell>184</cell> <cell>19</cell> <cell>27</cell> </row> <row id="bmw2"> <cell image="leaf.gif">M3 Coupe</cell> <cell>47,100</cell> <cell>3.2L</cell> <cell>333</cell> <cell>16</cell> <cell>24</cell> </row> </row> <row id="vw"> <cell image="folder.gif">Volkswagen</cell> <row id="vw1"> <cell>Colf GL 2.0</cell> <cell>15,580</cell> <cell>2.0L</cell> <cell>115</cell> <cell>24</cell> <cell>30</cell> </row> </row> <row id="mazda"> <cell image="folder.gif">Mazda</cell> <row id="mazda1"> <cell>MX-5 Miata</cell> <cell>21,868</cell> <cell>1.8L</cell> <cell>142</cell> <cell>22</cell> <cell>28</cell> </row> </row> <row id="porsche"> <cell image="folder.gif">Porsche</cell> <row id="porsche1"> <cell>Porsche 911</cell> <cell>128,200</cell> <cell>3.6L</cell> <cell>415</cell> <cell>14</cell> <cell>22</cell> <cell>4.06</cell> <cell>12.31</cell> <cell>120.63</cell> <cell>119</cell> </row> </row> </rows> 
        */      
    }
    
    function Days_schedule2XML( $leaf, $id = '', $Info, $Spoolers, $Type ) {
            if (is_array($leaf)) {
                    foreach (array_keys($leaf) as $k) {
                            $Ids = explode('/',$k);
                            $here = array_pop($Ids);
                            $i  = substr("$id/$k",1);
                            # On ne prend que l'historique
                            if (isset($Info[$i])) {
                                $cell = '';
                                list($dbid, $status, $schedule, $executed, $order, $begin, $end, $repeat ) = explode('|',$Info[$i]);
                               $status = 'SUCCESS';
                                $color = '#ccebc5';
                                $col = 'green';
                                if ($executed == '') {
                                    if (strtotime($schedule)<=time()) {
                                        $status = 'LATE';
                                        $color = '#fb8072';
                                        $col = 'red';
                                        $delay = $this->Duration(strtotime($schedule),time());
                                    }
                                    else {
                                        $status = 'WAITING';
                                        $color = '#bebada';
                                        $col='purple';
                                        $delay = $this->Duration(time(),strtotime($schedule));                                    
                                    }
                                }
                                else {
                                    $delay = $this->Duration(strtotime($executed),strtotime($schedule));
                                }
                                print '<row id="'.$dbid.'" style="background-color: '.$color.';" open="1">';
                                $cell .= '<cell image="bullet_'.$col.'.png"> '.$here.'</cell>';
                                $cell .= '<cell>'.$status.'</cell>';
                                $cell .= '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                $cell .= '<cell>'.$this->ShortDate($executed).'</cell>';
                                $cell .= '<cell>'.$delay.'</cell>';
                                $cell .= '<cell>'.$begin.'</cell>';
                                $cell .= '<cell>'.$end.'</cell>';
                                $cell .= '<cell>'.$this->FormatTime($repeat).'</cell>';
                                print $cell;
                            }
                            else {
                                    if ($id == '') {
                                        // cas du spooler 
                                        $icon = 'spooler';
                                        $col = '#ccebc5';
                                        // $open = ' open="1"';
                                        $open = '';
                                        if (isset($Spoolers[$i])) {
                                            list($hostname, $tcp_port, $start_time, $is_running, $is_paused ) = explode('|',$Spoolers[$i]);
                                            if ($is_paused==1) {
                                                $col = 'orange';
                                                $status = 'PAUSED';
                                            }
                                            elseif ($is_running==0) {
                                                $col = 'red';
                                                $status = 'STOPPED';
                                                $open = '';
                                            }
                                            else {
                                                $status = 'RUNNING';
                                            }
                                        }
                                        else {
                                            $icon = 'error';
                                        }
                                        print '<row id="'.$i.'" style="background-color: '.$col.';" open="1">';
					print '<userdata name="type">spooler</userdata>';
                                        print '<cell image="'.$icon.'.png"><![CDATA[<b> '.$here.'</b>]]></cell>';
                                        print '<cell>'.$status.'</cell>';
                                        print '<cell><![CDATA[<img src="'.$this->images.'/'.strtolower($status).'.png"/>]]></cell>';
                                        print '<cell></cell>';
                                        print '<cell></cell>';
                                        print '<cell>'.$start_time.'</cell>';
                                    }
                                    else {
                                        print '<row id="'.$i.'" open="1">';
                                        if (isset($Type[$i])) {
                                            print '<cell image="'.$Type[$i].'.png"> '.$here.'</cell>';
                                            print '<userdata name="type">'.$Type[$i].'</userdata>';
                                        }
                                        else {
                                            print '<cell image="folder.gif"> '.$here.'</cell>';
                                        }
                                    }
                            }
                           $this->Days_schedule2XML( $leaf[$k], $id.'/'.$k, $Info, $Spoolers, $Type );
                           print '</row>';
                    }
            }
    }

    // http://kevin.vanzonneveld.net/techblog/article/convert_anything_to_tree_structures_in_php/
    function explodeTree($array, $delimiter = '_', $baseval = false)
    {
      if(!is_array($array)) return false;
      $splitRE   = '/' . preg_quote($delimiter, '/') . '/';
      $returnArr = array();
      foreach ($array as $key => $val) {
        // Get parent parts and the current leaf
        $parts  = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
        $leafPart = array_pop($parts);

        // Build parent structure
        // Might be slow for really deep and large structures
        $parentArr = &$returnArr;
        foreach ($parts as $part) {
          if (!isset($parentArr[$part])) {
            $parentArr[$part] = array();
          } elseif (!is_array($parentArr[$part])) {
            if ($baseval) {
              $parentArr[$part] = array('__base_val' => $parentArr[$part]);
            } else {
              $parentArr[$part] = array();
            }
          }
          $parentArr = &$parentArr[$part];
        }

        // Add the final part to the structure
        if (empty($parentArr[$leafPart])) {
          $parentArr[$leafPart] = $val;
        } elseif ($baseval && is_array($parentArr[$leafPart])) {
          $parentArr[$leafPart]['__base_val'] = $val;
        }
      }
      return $returnArr;
    }
/******************************************************************/
    private function ShortDate($date) {
        if (substr($date,0,10)==date('Y-m-d'))
                return substr($date,11);
        return $date;
    }
    private function FormatTime($d) {
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
    
    private function Duration($start,$end = '' ) {
       if ($end == '') {
           $end = time();
       }
       $d = $end - $start;
       return $this->FormatTime($d);
    }

}
