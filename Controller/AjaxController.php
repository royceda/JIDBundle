<?php
// src/Arii/JODBundle/Controller/DBController.php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxController extends Controller
{
    public function job_infoAction()
    {
        $request = Request::createFromGlobals();
        $Infos = array(  );
        $id = $request->query->get( 'id' );
        if ($id != '') {
            $dhtmlx = $this->container->get('arii_core.dhtmlx');
            $data = $dhtmlx->Connector('data');

            $sql = "select  * from SCHEDULER_HISTORY where id = $id";
            
            $res = $data->sql->query( $sql );
            $line = $data->sql->get_next($res);
            if ($line['END_TIME'] == '') {
                 $Infos['status'] = 'RUNNING';
            }
            elseif ($line['ERROR']==0) {
                $Infos['status'] = 'SUCCESS';
            }
            else {
                $Infos['status'] = 'FAILURE';
            }
            
            $Job = explode('/',$line['JOB_NAME']);
                
            $Infos['job_name'] = array_pop($Job);
            $Infos['location'] = implode('/',$Job);
            $Infos['error_text'] = $line['ERROR_TEXT'];
            $Infos['exit_code'] = $line['EXIT_CODE'];
            $Infos['error'] = $line['ERROR'];
            $Infos['cause'] = $line['CAUSE'];
            $Infos['start_time'] = $line['START_TIME'];
            $Infos['end_time'] = $line['END_TIME'];
            $Infos['spooler_id'] = $line['SPOOLER_ID'];
            $Infos['pid'] = $line['PID'];
            
            $params = $line['PARAMETERS'];
            $Infos['parameters'] = '';
            // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
            while (($p = strpos($params,'<variable name="'))>0) {
                $begin = $p+16;
                $end = strpos($params,'" value="',$begin);
                $var = substr($params,$begin,$end-$begin);
                $params = substr($params,$end+9);
                $end = strpos($params,'"/>');
                $val = substr($params,0,$end);
                $params = substr($params,$end+2);
                $Infos['parameters'] = "$var: $val\n";
            }

        }
        header('Content-type: text/xml');
        print "<data>";
        foreach ($Infos as $k=>$v) {
            print "<$k>$v</$k>";
        }
        print "</data>";
        exit();
    }
}

	

