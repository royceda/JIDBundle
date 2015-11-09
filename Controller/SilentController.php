<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SilentController extends Controller
{
    protected $packages;

    public function __construct() {
        $this->packages = '../src/Arii/JIDBundle/Resources/packages';
    }
    
    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Silent:index.html.twig');
    }

    public function ribbonAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        
        return $this->render('AriiJIDBundle:Silent:ribbon.json.twig',array(), $response );
    }

    public function toolbarAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Silent:toolbar.xml.twig',array(), $response);
    }

    public function generateAction()
    {
        $tmp = sys_get_temp_dir();
        
        // Quel OS
        $Value = array();
        $os = $_POST['os_target'];
        foreach (array('installpath','userpath') as $f) {
            $Value[$f] = $_POST[$os.'_'.$f];
        }
        
        // quel base de donnees
        $database = $_POST['databaseDbms'];
        foreach (array('databaseHost','databaseHost','databaseUser','databasePassword','databaseSchema','databasePort','connector') as $f) {
            if (isset($_POST[$database.'_'.$f]))
                $Value[$f] = $_POST[$database.'_'.$f];
            else 
                $Value[$f] = '';
        }
        
        // On crrige les données brutes
        // on ouvre le fichier d'installation generique
        $install = file_get_contents( $this->packages.'/scheduler_install.xml' );
        $Lines = explode("\n",$install);
        $Err = array();
        $New = array();
        foreach ($Lines as $l) {
            while (($p=strpos(' '.$l,'{{'))>0) {
                if (($e = strpos($l,'}}',$p))==0) {
                    print "<font color='red'>".substr($l,$p)."</font>";
                    file_put_contents( $tmp.'/scheduler_install.xml', implode("\n",$New) );
                    exit();
                }
                $var = substr($l,$p+1,$e-$p-1);
                if (isset($Value[$var])) {
                    $val = $Value[$var];
                }
                elseif (isset($_POST[$var])) {
                    $val = $_POST[$var];
                }
                else {
                    $val = "[$var]";
                    array_push($Err,$var);
                }
                $l = substr($l,0,$p-1).$val.substr($l,$e+2);
            }
            array_push($New, $l);
        }
        file_put_contents( 'tmp/scheduler_install.xml', implode("\n",$New) );        
        if (count($Err)>0) {
            file_put_contents( $workspace.'/scheduler_install.err', implode("\n",$Err) );
        }        
        return $this->Directory($workspace); 
    }

    // a factoriser avec la fonction précédente
    // pour l'instant on genere les 2 en meme temps
    public function directAction()
    {
        // Quel OS
        $Value = array();
        if (isset($_POST['os_target']))
            $os = $_POST['os_target'];
        else {
            print "?!";
            exit();
            
        }
        foreach (array('installpath','userpath') as $f) {
            $Value[$f] = $_POST[$os.'_'.$f];
        }
        
        // quel base de donnees
        $database = $_POST['databaseDbms'];
        foreach (array('databaseHost','databaseHost','databaseUser','databasePassword','databaseSchema','databasePort','connector','connectorJTDS','connectorMaria') as $f) {
            if (isset($_POST[$database.'_'.$f]))
                $Value[$f] = $_POST[$database.'_'.$f];
            else 
                $Value[$f] = '';
        }
        
        // On crrige les données brutes
        // on ouvre le fichier d'installation generique   
        $install = @file_get_contents( $this->packages.'/scheduler_install.xml' );
        if ($install =='') {
            print "Package not found !".$this->packages;
            exit();
        }
        $Lines = explode("\n",$install);
        $Err = array();
        $New = array();
        foreach ($Lines as $l) {
            while (($p=strpos(' '.$l,'{{'))>0) {
                if (($e = strpos($l,'}}',$p))==0) {
                    print "<font color='red'>".substr($l,$p)."</font>";
                    // file_put_contents( $workspace.'/users/scheduler_install.xml', implode("\n",$New) );
                    exit();
                }
                $var = substr($l,$p+1,$e-$p-1);
                if (isset($Value[$var])) {
                    $val = $Value[$var];
                }
                elseif (isset($_POST[$var])) {
                    $val = $_POST[$var];
                }
                else {
                    $val = "[$var]";
                    array_push($Err,$var);
                }
                $l = substr($l,0,$p-1).'[[['.$val.']]]'.substr($l,$e+2);
            }
            array_push($New, $l);
        }
        $res = implode("\n",$New);
        
/*        file_put_contents( 'tmp/scheduler_install.xml',
                str_replace(
                    array("[[[","]]]"),
                    array('',''),
                    $res));
*/        
        header('Content-Type: text/xml');
        print str_replace(
                    array('<','>'," ","\t","\n","[[[","]]]"),
                    array('&lt;','&gt;','&nbsp;','&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;','<br/>','<font color="blue">','</font>'),
                    $res);
        exit();
    }

    public function Directory($dir) {
        
        // $xml =  '<data><item name="scheduler_install.xml" type="files"></item></data>';
        $xml = '<data>';
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_dir("$dir/$file")) continue;
                    $stat = stat("$dir/$file");
                    $xml .= '<item name="'.$file.'" type="file">';
                    $xml .= '<filesize>'.$stat['size'].'</filesize>';
                    $xml .= '<modifdate>'.$stat['mtime'].'</modifdate>';
                    $xml .= '</item>';
                }
                closedir($dh);
            }
        }
        $xml .= '</data>';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $response->setContent($xml); 
        return $response;
    }
    public function filesAction()
    {
        header('Content-type: text/xml');
        print file_get_contents( $this->workspace.'/scheduler_install.xml' );
        exit();
    }
    
}
