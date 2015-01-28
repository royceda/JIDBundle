<?php
// src/Arii/JODBundle/Controller/DBController.php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RSSController extends Controller
{
    public function jobsAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $qry =  $sql->Select(array('ID','SPOOLER_ID','JOB_NAME','START_TIME','END_TIME','ERROR','ERROR_TEXT','EXIT_CODE'))
                .$sql->From(array('SCHEDULER_HISTORY'))
                .$sql->Where(array('ERROR'=>1))
                .$sql->OrderBy(array('ID desc'));

        $max = 50;
        $res = $data->sql->query( $qry );
        $Infos = array();
        while ($line = $data->sql->get_next($res)) {
            $i['ID'] = $line['ID'];
            $i['JOB'] = $line['JOB_NAME'];
            $i['END_TIME'] = $line['END_TIME'];
            $i['ERROR'] = $line['ERROR'];
            $i['ERROR_TEXT'] = $line['ERROR_TEXT'];
            $i['EXIT_CODE'] = $line['EXIT_CODE'];
            $i['SPOOLER_ID'] = $line['SPOOLER_ID'];
            array_push($Infos,$i);
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:RSS:default.atom.twig', array( 'Infos' => $Infos), $response );
    }
    
    public function ordersAction()
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $sql = $this->container->get('arii_core.sql');
        $qry =  $sql->Select(array('HISTORY_ID as ID','SPOOLER_ID','JOB_CHAIN','ORDER_ID','START_TIME','END_TIME','TITLE','STATE','STATE_TEXT'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY'))
//                .$sql->Where(array('ERROR'=>1))
                .$sql->OrderBy(array('ID desc'));

        $max = 50;
        $res = $data->sql->query( $qry );
        $Infos = array();
        while ($line = $data->sql->get_next($res)) {
            $i['ID'] = $line['ID'];
            $i['JOB_CHAIN'] = $line['JOB_CHAIN'];
            $i['ORDER_ID'] = $line['ORDER_ID'];
            $i['END_TIME'] = $line['END_TIME'];
            $i['TITLE'] = $line['TITLE'];
            $i['STATE'] = $line['STATE'];
            $i['STATE_TEXT'] = $line['STATE_TEXT'];
            $i['SPOOLER_ID'] = $line['SPOOLER_ID'];
            array_push($Infos,$i);
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render('AriiJIDBundle:RSS:orders.atom.twig', array( 'Infos' => $Infos), $response );
    }

}

	

