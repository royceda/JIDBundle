<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class CronController extends Controller
{
    protected $storage;
        
    public function indexAction()   
    {
        return $this->render('AriiJIDBundle:Cron:index.html.twig' );
    }
    
    public function ribbonAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        return $this->render('AriiJIDBundle:Cron:ribbon.json.twig',array(), $response );
    }

    public function convertAction()   
    {
        $workspace = $this->container->getParameter('workspace');
        $this->storage = "$workspace/cron"; 

        // $cron_cmd="20 0 * * 0  \$HOME/bin/otrs.DeleteCache.pl --expired >> /dev/null";
        $request = Request::createFromGlobals();
        $crontab  = $request->get( 'crontab' );

        @mkdir($this->storage);
        // debug
        // $crontab = file_get_contents($this->storage."/crontest.txt");
        $error = '';
        $n = 0; $l=0;
        foreach (explode("\n",$crontab) as $cron) {
            $l++;
            $cron = trim($cron);
            if (($cron=='') or (substr($cron,0,1)=='#')) continue;
            
            $CronInfos = $this->GetCronInfos($cron);
            if (empty($CronInfos)) {
                $error .= "[$l] $cron\n";
                continue;
            }
            $xml = $this->CreateXml($CronInfos);
            file_put_contents($this->storage.'/'.$CronInfos['name'].'.job.xml',$xml);
            file_put_contents($this->storage.'/'.'error.log',$error);
            $n++;
        }
        print $n;
        exit();      
    }
    
     public function dirlistAction() 
     {
        $files = $this->container->get('arii_core.files');
        $xml = $files->DirList( "cron" );   
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent( $xml );
        return $response;
    }

    public function job_viewAction()
    {
        $request = Request::createFromGlobals();
        $file  = $request->get( 'file' );
        
        $files = $this->container->get('arii_core.files');
        print $files->XMLView( "cron/$file" );   
        exit();
    }

    public function uploadAction()   
    {
        $files = $this->container->get('arii_core.files');
        print $files->Upload( "cron", "crontab.txt" );   
        exit();
    }

    public function formAction()   
    {
        $workspace = $this->container->getParameter('workspace');
        $cache = "$workspace/cron"; 
        $file = $cache.'/crontab.txt';
        $crontab = file_get_contents($file);
        print $crontab;
        exit();
    }

    public function task_schedulerAction()   
    {
        $file = file_get_contents("D:\www\arii\srv22.alltasks.xml");
        
        # Nettoyage des balise <?
       while (($d = strpos($file,'<?'))>0) {
           $e = strpos($file,'?>',$d);           
           $file = substr($file,0,$d).substr($file,$e+2);           
        }
        $tools = $this->container->get('arii_core.tools');
        $Result = $tools->xml2array( $file , 1, 'attribute');
        
        $n = 0;
        while (isset($Result['Tasks']['Task'][$n])) {
           $Task = $Result['Tasks']['Task'][$n];
           print_r($Task);
           $n++;
        }
        exit();
        return $this->render('AriiCoreBundle:Cron:task_scheduler.html.twig');
    }

    public function ConverterAction() {
        $file = file_get_contents("D:\www\arii\srv22.alltasks.xml");
        
        # Nettoyage des balise <?
       while (($d = strpos($file,'<?'))>0) {
           $e = strpos($file,'?>',$d);           
           $file = substr($file,0,$d).substr($file,$e+2);           
        }
        $tools = $this->container->get('arii_core.tools');
        $Result = $tools->xml2array( $file , 1, 'attribute');
        
        $n = 0;
        while (isset($Result['Tasks']['Task'][$n])) {
           $Task = $Result['Tasks']['Task'][$n];
           print_r($Task);
           $n++;
        }
        exit();
    }

    private function GetCronInfos($cron) {
        
        $Line = explode(" ",$cron,6);
        // il faut au moins 4 elements
        if (!isset($Line[3])) return;
        
        list($min,$hour,$dom,$mon,$dow,$cmd) = $Line;
        $Minutes = $this->getArray($min,0,59);
        $Hours = $this->getArray($hour,0,23);
        $DayOfMonth = $this->getArray($dom,1,31);
        $Months = $this->getArray($mon,1,12);
        $DayOfWeek = $this->getArray($dow,0,7);
        
        // on tente une extraction du nom dans la commande
        $name = trim($cmd);
        if (($p=strpos($name," "))>0) $name = substr($name,0,$p);
        $name = basename($name);
        
        return (    array(  
            'minutes'=>$Minutes, 
            'hours' => $Hours,
            'days_of_week' => $DayOfWeek,
            'days_of_month' => $DayOfMonth,
            'months' => $Months,            
            'script' =>$cmd,
            'name' => $name ) );
    }

    function  CreateXml($CronInfos){
        $xml="<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>\n\n\n";
        $xml.="<job>\n";
        $xml.=" <script language=\"shell\">\n";
        $xml.="     <![CDATA[\n";
        $xml.= $CronInfos['script'];
        $xml.="\n       ]]>\n";
        $xml.=" </script>\n";
        $xml.="\n <run_time>\n";
        
        if ($CronInfos['months'][0] != '*') {
            $xml.= $this->AddMonths($CronInfos);
        }
        elseif ($CronInfos['days_of_month'][0] != '*') {
            $xml.= $this->AddDaysOfMonth($CronInfos);
        }
        elseif ($CronInfos['days_of_week'][0] != '*') {
            $xml.= $this->AddDaysOfWeek($CronInfos);
        }
        else {
            $xml.= $this->AddHours($CronInfos);
        }
        $xml.=" </run_time>\n";
        $xml.="</job>";
        return $xml;  
    }

    private function AddHours($CronInfos) {
        $Hours = $CronInfos['hours'];
        $Minutes = $CronInfos['minutes'];
        $H = array();
        if ($Hours[0]== '*') {
            for($i=0;$i<24;$i++) {
                array_push($H,$i);
            }
        }
        else {
            $H = $Hours;
        }
        $M = array();
        if ($Minutes[0]== '*') {
            for($i=0;$i<24;$i++) {
                array_push($M,$i);
            }
        }
        else {
            $M = $Minutes;
        }
        $XML = array();
        foreach ($Hours as $h) {
            foreach ($Minutes as $m) {
                array_push($XML, '<period single_start="'.sprintf("%02d:%02d",$h,$m).'"/>');
            }
        }
        return implode("\n",$XML)."\n";
    }

    private function AddDaysOfWeek($CronInfos) {
        if ($CronInfos['days_of_week']=='*') return;
        $xml = "<weekdays>\n";
        $xml .= "<day day=\"".implode(" ",$CronInfos['days_of_week'])."\">\n";
        $xml .= $this->AddHours($CronInfos);
        $xml .= "</day>\n";        
        $xml .= "</weekdays>\n";
        return "$xml\n";
    }

    private function AddDaysOfMonth($CronInfos) {
        if ($CronInfos['days_of_month']=='*') return;
        $xml = "<monthdays>\n";
        $xml .= "<day day=\"".implode(" ",$CronInfos['days_of_month'])."\">\n";
        $xml.= $this->AddHours($CronInfos);
        $xml .= "</day>\n";        
        $xml .= "</monthdays>\n";
        return "$xml\n";
    }

    private function AddMonths($CronInfos) {
        if ($CronInfos['months']=='*') return;
        $xml = "<month month=\"".implode(" ",$CronInfos['months'])."\">\n";
        if ($CronInfos['days_of_month'][0] != '*') {
            $xml.= $this->AddDaysOfMonth($CronInfos);
        }
        elseif ($CronInfos['days_of_week'][0] != '*') {
            $xml.= $this->AddDaysOfWeek($CronInfos);
        }
        else {
            $xml.= $this->AddHours($CronInfos);
        }        $xml .= "</month>";
        return "$xml\n";
    }
    
    private function getArray($str,$begin,$end) {
        $items = array();
        $s=1;
        foreach (explode(",",$str) as $l) {
            if (strpos($l,'/')>0) {
                list($l,$s) = explode('/',$l);
            }
            if ($l == '*')  {
                if ($s == 1) {
                    array_push($items,'*');
                }
                else {
                    for($i=$begin;$i<=$end;$i+=$s)   {
                         array_push($items,$i);
                    }
                }
            }
            elseif (strpos($l,'-')>0) {
                list($b,$e) = explode('-',$l);
                for($i=$b;$i<=$e;$i++)   {
                    if($i%$s == 0){
                        array_push($items,$i);
                    }
                }					
            }
            else{
                array_push($items,$l);
            }
        }
        sort($items);
        return array_unique($items);	
    }
   
}
