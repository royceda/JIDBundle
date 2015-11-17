<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Parser;

class RequestsController extends Controller
{
    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Requests:index.html.twig');
    }
    
    public function treeAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= '<tree id="0">
                    <item id="runtimes" text="Runtimes"/>
                 </tree>';

        $response->setContent( $list );
        return $response;        
    }

    public function listAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= '<rows>';
        
        $yaml = new Parser();
        $lang = $this->getRequest()->getLocale();
        $basedir = '../src/Arii/JIDBundle/Resources/views/Requests/'.$lang;
        if ($dh = @opendir($basedir)) {
            while (($file = readdir($dh)) !== false) {
                if (substr($file,-4) == '.yml') {
                    $content = file_get_contents("$basedir/$file");
                    $v = $yaml->parse($content);
                    $list .= '<row id="'.substr($file,0,strlen($file)-4).'"><cell>'.$v['title'].'</cell></row>';
                }
            }
        }
        $list .= '</rows>';

        $response->setContent( $list );
        return $response;        
    }
    
    // Temps d'exécution trop long
    public function summaryAction()
    {
        $lang = $this->getRequest()->getLocale();
        $basedir = '../src/Arii/JIDBundle/Resources/views/Requests/'.$lang;

        $yaml = new Parser();
        $value['title'] = $this->get('translator')->trans('Summary');
        $value['description'] = $this->get('translator')->trans('List of requests');
        $value['columns'] = array(
            $this->get('translator')->trans('Title'),
            $this->get('translator')->trans('Description') );
        
        $nb=0;
        if ($dh = @opendir($basedir)) {
            while (($file = readdir($dh)) !== false) {
                if (substr($file,-4)=='.yml') {
                    $content = file_get_contents("$basedir/$file");
                    $v = $yaml->parse($content);
                    $nb++;
                    $value['line'][$nb] = array($v['title'],$v['description']);
                }
            }
        }
        $value['count'] = $nb;
        return $this->render('AriiJIDBundle:Requests:bootstrap.html.twig', array('result' => $value));
    }
    
    public function resultAction()
    {
        $lang = $this->getRequest()->getLocale();
        $request = Request::createFromGlobals();
        if ($request->query->get( 'request' ))
            $req=$request->query->get( 'request');
        else {
            print "Request ?!";
            exit();
        }
                        
        // cas de l'appel direct
        if ($request->query->get( 'dbname' )) {
            $instance=$request->query->get( 'dbname');

            $session = $this->container->get('arii_core.session');
            $engine = $session->setDatabaseByName($instance,'waae');            
        }
        
        if (!isset($req)) return $this->summaryAction();
        
        $page = '../src/Arii/JIDBundle/Resources/views/Requests/'.$lang.'/'.$req.'.yml';
        $content = file_get_contents($page);
        
        $yaml = new Parser();
        try {
            $value = $yaml->parse($content);
            
        } catch (ParseException $e) {
            $error = array( 'text' =>  "Unable to parse the YAML string: %s<br/>".$e->getMessage() );
            return $this->render('AriiJIDBundle:Requests:ERROR.html.twig', array('error' => $error));
        }

        $sql = $this->container->get('arii_core.sql');
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        # on prend la base par défaut 
        $session = $this->container->get('arii_core.session');     
        $db = $session->getDatabase();
        switch($db['driver']) {
            case 'postgre':
            case 'postgres':
                $driver = 'postgres';
                break;
            case 'mysql':
            case 'mysqli':
                $driver = 'mysql';
                break;
            case 'oci8':
            case 'oracle':
            case 'pdo_oci':
                $driver = 'oracle';
                break;
            default:
                $driver = $db['driver'];
        }
                
        if (!isset($value['sql'][$driver])) {
            print "$driver ?!";
            exit();
        }
        
        $res = $data->sql->query($value['sql'][$driver]);
        $date = $this->container->get('arii_core.date');
        $nb=0;
        // On cree le tableau des consoles et des formats
        $value['columns'] = $Format = array();
        foreach (explode(',',$value['header']) as $c) {
            if (($p = strpos($c,'('))>0) {
                $h = substr($c,0,$p);
                $Format[$h] = substr($c,$p+1,strpos($c,')',$p)-$p-1);
                $c = $h;
            }
            array_push($value['columns'],$c);
        }
        // bibliothèques
        $date = $this->container->get('arii_core.date');   
        while ($line = $data->sql->get_next($res))
        {
            $r = array();
            $status = 'unknown';
            foreach ($value['columns'] as $h) {
                if (isset($line[$h])) {
                    // format special
                    $value['status'] = '';
                    if (isset($Format[$h])) {
                        switch ($Format[$h]) {
                            case 'timestamp':
                                $val = $date->Time2Local($line[$h]);
                                break;
                            case 'duration':
                                $val = $date->FormatTime($line[$h]);
                                break;
                            case 'br':
                                $val = str_replace(array("\t","\n"),array("     ","<br/>"),$line[$h]);
                                break;
                            default:
                                $val = $line[$h].'('.$Format[$h].')';
                                break;
                        }
                    }
                    else {
                        $val = $line[$h];
                    }
                }
                else  $val = '';
                array_push($r,$val);
            }
            $nb++;
            $value['lines'][$nb]['cells'] = $r;
            $value['lines'][$nb]['status'] = $status;
         }
        $value['count'] = $nb;
        return $this->render('AriiJIDBundle:Requests:bootstrap.html.twig', array('result' => $value ));
    }

}