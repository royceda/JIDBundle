<?php
// src/Arii/JODBundle/Controller/DBController.php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class FormController extends Controller
{
    public function start_jobAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('sh.JOB_NAME','sh.SPOOLER_ID','sh.PARAMETERS','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('sh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array('sh.ID'=> $id));

        $res = $data->sql->query( $qry );
        $Parameters = array();
        $line = $data->sql->get_next($res);
        if ( ! isset($line['JOB_NAME']) ) {
            print "$id ?!";
            exit();
        }
// <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
        $params = $line['PARAMETERS'];
        $nb_parameters=0;
        $P = array();
        while (($p = strpos($params,'<variable name="'))>0) {
            $begin = $p+16;
            $end = strpos($params,'" value="',$begin);
            $var = substr($params,$begin,$end-$begin);
            $params = substr($params,$end+9);
            $end = strpos($params,'"/>');
            $val = substr($params,0,$end);
            $params = substr($params,$end+2);
                    if (strpos(" $val",'password')>0) {
                        // a voir avec les connexions
                    } 
                    else {
            $Parameters[$var] = $val;
            array_push($P,$var);
            $nb_parameters++;
                    }

        }
        $line['nb_parameters'] = $nb_parameters;
        $line['parameters'] = implode('|',$P);        
        $line['PARAMETERS'] = $Parameters;
        return $this->render('AriiJIDBundle:Form:start_job.html.twig', $line );
    }

    public function kill_jobAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('sh.ID,sh.JOB_NAME','sh.SPOOLER_ID','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('sh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array('sh.ID' => $id));
        
        $res = $data->sql->query( $qry );
        $Parameters = array();
        $line = $data->sql->get_next($res);
        if ( ! isset($line['JOB_NAME']) ) {
            print "$id ?!";
            exit();
        }
        return $this->render('AriiJIDBundle:Form:kill_job.html.twig', $line );
    }

    public function stop_jobAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('sh.ID','sh.JOB_NAME','sh.SPOOLER_ID','sh.ERROR_TEXT','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('sh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array('sh.ID' => $id));

        $res = $data->sql->query( $qry );
        $Parameters = array();
        $line = $data->sql->get_next($res);
        if ( ! isset($line['JOB_NAME']) ) {
            print "$id ?!";
            exit();
        }
        $line['ERROR_TEXT'] = str_replace('"','\"',$line['ERROR_TEXT']);
        $line['ERROR_TEXT'] = str_replace("\n",' ',$line['ERROR_TEXT']);
       
        return $this->render('AriiJIDBundle:Form:stop_job.html.twig', $line );
    }

    public function unstop_jobAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('sh.ID','sh.JOB_NAME','sh.SPOOLER_ID','sh.ERROR_TEXT','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_HISTORY sh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('sh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array( 'sh.ID' => $id ));

        $res = $data->sql->query( $qry );
        $Parameters = array();
        $line = $data->sql->get_next($res);
        if ( ! isset($line['JOB_NAME']) ) {
            print "$id ?!";
            exit();
        }
        $line['ERROR_TEXT'] = str_replace('"','\"',$line['ERROR_TEXT']);
        $line['ERROR_TEXT'] = str_replace("\n",' ',$line['ERROR_TEXT']);
       
        return $this->render('AriiJIDBundle:Form:unstop_job.html.twig', $line );
    }
    
    public function start_orderAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');  
        $qry = $sql->Select(array('soh.job_chain','soh.order_id','soh.spooler_id','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('soh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array('soh.HISTORY_ID' => $id));

        $res = $data->sql->query($qry);
        $line = $data->sql->get_next($res);
        $order_id = $line['order_id'];
        
        $qry2 = $sql->Select(array('JOB_CHAIN','SPOOLER_ID'))
                .$sql->From(array('SCHEDULER_ORDERS'))
                .$sql->Where(array('ID' => $order_id )) ;
        $res2 = $data->sql->query($qry2);
        $line2 = $data->sql->get_next($res2);
        
        if($line2 == "")
        {
            return $this->render("AriiJIDBundle:Form:add_order.html.twig",$line);
        }
        
        if (!isset($line['order_id']))
        {
            print "order_id ?!";
            exit();
        }
        
        return $this->render("AriiJIDBundle:Form:start_order.html.twig",$line);
        
    }
    
    public function add_orderAction()
    {
        $request = Request::createFromGlobals();
        $id = $request->query->get( 'id' );
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        $sql = $this->container->get('arii_core.sql');          
        $qry = $sql->Select(array('soh.JOB_CHAIN','soh.SPOOLER_ID','si.HOSTNAME','si.TCP_PORT'))
                .$sql->From(array('SCHEDULER_ORDER_HISTORY soh'))
                .$sql->LeftJoin('SCHEDULER_INSTANCES si',array('soh.SPOOLER_ID','si.SCHEDULER_ID'))
                .$sql->Where(array('soh.HISTORY_ID' => $id));
        
        $res = $data->sql->query($qry);
        $line = $data->sql->get_next($res);
        
        return $this->render("AriiJIDBundle:Form:add_order.html.twig",$line);
        
    }
    
    public function select_start_stateAction()
    {
        $request = Request::createFromGlobals();
        $job_chain = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('ORDER_STATE'))
                .$sql->From(array('SCHEDULER_JOB_CHAIN_NODES'))
                .$sql->Where(array('JOB_CHAIN' => $job_chain));
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('select');
        $data->render_sql($qry,'JOB_CHAIN','ORDER_STATE,ORDER_STATE');
    }
    
    public function select_end_stateAction()
    {
        $request = Request::createFromGlobals();
        $job_chain = $request->get('id');
        $sql = $this->container->get('arii_core.sql');                  
        $qry = $sql->Select(array('ORDER_STATE'))
                .$sql->From(array('SCHEDULER_JOB_CHAIN_NODES'))
                .$sql->Where(array('JOB_CHAIN' => $job_chain));
        
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('select');
        $data->render_sql($qry,'JOB_CHAIN','ORDER_STATE,ORDER_STATE');
    }
}

	

