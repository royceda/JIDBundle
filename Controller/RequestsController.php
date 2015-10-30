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

        $res = $data->sql->query($value['sql']['oracle']);
        $date = $this->container->get('arii_core.date');
        $nb=0;
        $value['columns'] = explode(',',$value['header']);
        while ($line = $data->sql->get_next($res))
        {
            $r = array();
            foreach ($value['columns'] as $h) {
                if (isset($line[$h])) {
                    $val = $line[$h];
                    // on a un statut ?
                    if ($h == 'STATUS')
                        $value['status'] = $val;
                }
                else  $val = '';
                array_push($r,$val);
            }
            $nb++;
            $value['line'][$nb] = $r;
        }
        $value['count'] = $nb;
        return $this->render('AriiJIDBundle:Requests:bootstrap.html.twig', array('result' => $value));
    }

}