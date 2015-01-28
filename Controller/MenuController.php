<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends Controller
{
    public function globalAction()
    {
        $user_session = $this->container->get('arii_core.session');
        
        // Database
        $dblist = $user_session->GetDatabases();
        $dbname = $user_session->GetDatabase();
        $database = array();
        foreach ($dblist as $db) {
            array_push($database, array('id'=>$db['id'], 'text' => $db['name'], 'checked'=> ($db['name'] == $dbname['name']) ));
        }
        
        // Refresh 
        $ref = $user_session->GetRefresh();        
        foreach (array(0,5,30,60,300,900,1800,3600) as $r) {
            $Check[$r]=0;
        }
        $Check[$ref]=1;
        // refresh actuel
        $refresh = array();
        array_push($refresh, array('id'=>0, 'text' => 'Freeze', 'checked'=>$Check[0]));
        array_push($refresh, array('id'=>5, 'text' => '5s', 'checked'=>$Check[5]));
        array_push($refresh, array('id'=>30, 'text' => '30s', 'checked'=>$Check[30]));
        array_push($refresh, array('id'=>60, 'text' => '1min', 'checked'=>$Check[60]));
        array_push($refresh, array('id'=>300, 'text' => '5min', 'checked'=>$Check[300]));
        array_push($refresh, array('id'=>900, 'text' => '15min', 'checked'=>$Check[900]));
        array_push($refresh, array('id'=>1800, 'text' => '30min', 'checked'=>$Check[1800]));
        array_push($refresh, array('id'=>3600, 'text' => '1h', 'checked'=>$Check[3600]));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:Menu:global.xml.twig', array( 'update' => $refresh, 'database' => $database ), $response);
    }

    public function plannedAction()
    {
        return $this->render('AriiJIDBundle:Menu:planned.html.twig');
    }
    
    public function messagesAction()
    {
        return $this->render('AriiJIDBundle:Menu:messages.html.twig');
    }
}
