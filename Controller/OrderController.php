<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller {

    public function toolbar_startAction() {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Order:toolbar_start.xml.twig", array(), $response);
    }

    public function formAction() {
        // recherche des infos 
        $request = Request::createFromGlobals();
        $id = $request->get('id');

        // Recherche des informations dans la base de données
        // Nécessaire pour la securité, inutile de faire un accès direct !
        $sos = $this->container->get('arii_jid.sos');
        // planifié ou historique ?
        list($spooler_id, $order_id, $job_chain) = $sos->getOrderInfos($id);
        list($protocol, $scheduler, $port, $path) = $sos->getConnectInfos($spooler_id);
        
        // On recupere les informations du job chain
        $cmd = '<show_job_chain job_chain="' . $job_chain . '"/>';
        $SOS = $this->container->get('arii_core.sos');
        $result = $SOS->XMLCommand($spooler_id, $scheduler, $port, $path, $protocol, $cmd, 'attr');
        if (isset($result['ERROR'])) {
            print($result['ERROR']);
            exit();
        }

        $job_chain = $result['spooler']['answer']['job_chain']['job_chain_node'];
        if (!isset($job_chain[0])) {
            $job_chain[0] = $job_chain;
        }
        $n = 0;
        $title = $action = $current_state = '';
        $State = array();
        while (isset($job_chain[$n])) {
            $job_chain_node = $job_chain[$n];
            
            // on garde les étapes
            $state = $job_chain_node['attr']['state'];
            if (!isset($State{$state})) {
                $State{$state}=1;
            }

            // on prend les infos de l'ordre
            if (isset($job_chain_node['order_queue']['order']['attr']['id'])) {
                $order = $job_chain_node['order_queue']['order']['attr'];
                if ($order['id'] == $order_id) {
                    $current_state = $order['state'];
                    if (isset($order['title'])) $title = $order['title'];
                    if (isset($order['suspended']) and ($order['suspended']=='yes')) $action = 'suspended';
                }
            }
            $n++;
        }

        $OptionsS = array();
        foreach (array_keys($State) as $k) {
            $opt = 'text: "'.$k.'", value: "'.$k.'"';
            if ($k == $current_state) $opt .= ', selected: true';
            array_push($OptionsS, "{ $opt }" );
        }
        $OptionsE = array('{ text: "", value: "", selected: true }' );
        foreach (array_keys($State) as $k) {
            array_push($OptionsE, '{ text: "'.$k.'", value: "'.$k.'" }' );
        }
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $json = '[
        { type: "settings", inputWidth: 400, position: "label-left", labelWidth: 100, labelAlign: "left", position: "label-left", offsetLeft: 10 },
        {   type: "hidden", 
            name: "command",     
            value: "add_order"
        },                
        {type:"fieldset", name:"order", label:"' . $this->get('translator')->trans('Order') . '", inputWidth:"595", list:[                
        {   type: "input",
            name: "order_id",
            label: "' . $this->get('translator')->trans('Order ID') . '",
            value: "' . $order_id . '"
        },
        {
            type: "input",
            name: "title",
            label: "' . $this->get('translator')->trans('Title') . '",
            value: "' . $title . '"
        } 
        ] },
        {type:"fieldset", name:"state", label:"' . $this->get('translator')->trans('Step') . '", inputWidth:"595", list:[                
        {  type: "select",
           name: "start_state", inputWidth:"300",
           label: "' . $this->get('translator')->trans('Start state') . '",
           options: [ '.implode(', ',$OptionsS).' ]
        },
        {  type: "select",
           name: "end_state",  inputWidth:"300",
           label: "' . $this->get('translator')->trans('End state') . '",
           options: [ '.implode(', ',$OptionsE).' ]
        }
        ] },
        {type:"fieldset", name:"status", label:"' . $this->get('translator')->trans('Action') . '", inputWidth:"595", list:[                
            {  type: "radio",
               name: "action",
               label: "' . $this->get('translator')->trans('Activate') . '", 
               value: "",
               position:"label-right"'.($action=='activated'?', checked: true':'').'
            },
            {type: "newcolumn" },    
            {  type: "radio",
               name: "action",
               label: "' .($action=='suspended'?"<font color='red'>":'').$this->get('translator')->trans('Suspend').($action=='suspended'?"</font>":'') . '",
               value: "suspended",
               position:"label-right"'.($action=='suspended'?', checked: true':'').'
            },
            {type: "newcolumn" },    
            {  type: "radio",
               name: "action",
               label: "' . $this->get('translator')->trans('Reset') . '", 
               value: "reset",
                position:"label-right"        
            },
            {type: "newcolumn" },    
            {  type: "checkbox",
               name: "setback",
               label: "' .($action=='setback'?"<font color='red'>":'') . $this->get('translator')->trans('Setback').($action=='setback'?"</font>":'') . '",
               value: "setback",
               position:"label-right"'.($action=='setback'?', checked: true':'').'       
            } 
            ] }
    ]';
        $response->setContent($json);
        return $response;
    }

    public function paramsAction() {
        // recherche des infos 
        $request = Request::createFromGlobals();
        $id = $request->get('id');

        // Recherche des informations dans la base de données
        // Nécessaire pour la securité, inutile de faire un accès direct !
        $sos = $this->container->get('arii_jid.sos');
        list($spooler_id, $order_id, $job_chain) = $sos->getOrderInfos($id);
        list($protocol, $scheduler, $port, $path) = $sos->getConnectInfos($spooler_id);

        // On recupere les informations du job chain
        $cmd = '<show_order order="' . $order_id . '" job_chain="' . $job_chain . '" what="payload"/>';
        $SOS = $this->container->get('arii_core.sos');
        $result = $SOS->XMLCommand($spooler_id, $scheduler, $port, $path, $protocol, $cmd, 'attr');

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        $list = '<?xml version="1.0" encoding="UTF-8"?>';
        $list .= "<rows>\n";
        if (isset($result['spooler']['answer']['order']['payload']['params']['param'])) {
            if (!isset($result['spooler']['answer']['order']['payload']['params']['param'][0])) {
                $result['spooler']['answer']['order']['payload']['params']['param'][0] = $result['spooler']['answer']['order']['payload']['params']['param'];
            }
            $n = 0;
            while (isset($result['spooler']['answer']['order']['payload']['params']['param'][$n])) {
                $param = $result['spooler']['answer']['order']['payload']['params']['param'][$n]['attr'];
                $list .= "<row><cell>".$param['name']."</cell><cell>".$param['value']."</cell></row>";
                $n++;
            }
        }
        $list .= "</rows>\n";
        $response->setContent($list);
        return $response;
    }

    public function toolbar_paramsAction()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');
        return $this->render("AriiJIDBundle:_Order:toolbar_params.xml.twig",array(), $response );
    }

}
