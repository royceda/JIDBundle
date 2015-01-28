<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CmdController extends Controller
{
    // utilise par qui ?
    public function startjobAction( $id )
    {
        $dhtmlx = $this->container->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');

        $qry = "select h.ID,h.SPOOLER_ID,h.JOB_NAME,h.START_TIME,h.END_TIME,h.CAUSE,h.STEPS,h.EXIT_CODE,h.ERROR,h.ERROR_CODE,h.ERROR_TEXT,h.PARAMETERS,    
oh.JOB_CHAIN,oh.STATE,oh.ORDER_ID,oh.TITLE,
osh.TASK_ID,
HOSTNAME,TCP_PORT,UDP_PORT 
from SCHEDULER_HISTORY h
left join SCHEDULER_ORDER_STEP_HISTORY osh
on h.ID=osh.TASK_ID
left join SCHEDULER_ORDER_HISTORY oh
on osh.HISTORY_ID=oh.HISTORY_ID
left join SCHEDULER_INSTANCES i
on h.SPOOLER_ID=i.SCHEDULER
where h.ID=$id";

        // $data->event->attach("beforeRender","color_rows");
        $res = $data->sql->query( $qry );
        if ($Infos = $data->sql->get_next($res)) {

            # Parametres
            if ($Infos['PARAMETERS']) {
                $params = $Infos['PARAMETERS'];
                // <sos.spooler.variable_set count="5" estimated_byte_count="413"><variable name="db_class" value="SOSMySQLConnection"/><variable name="db_driver" value="com.mysql.jdbc.Driver"/><variable name="db_password" value=""/><variable name="db_url" value="jdbc:mysql://localhost:3306/scheduler"/><variable name="db_user" value="root"/></sos.spooler.variable_set>
                while (($p = strpos($params,'<variable name="'))>0) {
                    $begin = $p+16;
                    $end = strpos($params,'" value="',$begin);
                    $var = substr($params,$begin,$end-$begin);
                    $params = substr($params,$end+9);
                    $end = strpos($params,'"/>');
                    $val = substr($params,0,$end);
                    $params = substr($params,$end+2);
                    
                    // on conserve si il n'est pas pas question de connexion
                    if (strpos(" $val",'password')>0) {
                        // a voir avec les connexions
                    } 
                    else {
                            $Value[$var]="(($val))";
                    }
                }
                $Infos['PARAMETERS'] = $Value;
            }
            $result = $this->send_command('<start_job job="'.$Infos['JOB_NAME'].'" at="now"/>', $Infos['HOSTNAME'], $Infos['TCP_PORT']);
            $Infos['RESULT'] = $result;
            return $this->render('AriiJIDBundle:Ajax:start_job.html.twig', $Infos);
        }
        else {
            print "id $id not found !";
            exit();
        }
    }
    
    function send_command( $cmd,  $host='localhost', $port=4444 ) {
            //open Socket
            if ( $socket = fsockopen( $host, $port, $errno,$errstr, 15) ) {

              //build command
    /*          $cmd = '<add_jobs><job name="myJob" title = "my first job"> <script';
              $cmd += 'language="shell">  <![CDATA[dir c:\temp  ]]>  </script> ';     
              $cmd += '<run_time> <period ';     
              $cdm += 'single_start="18:00"/></run_time></job></add_jobs>';
     */    
              //send command
                fputs( $socket, $cmd );
                $answer = ''; $s = '';
                while ( !preg_match( '/<\/spooler>/', $s) && !preg_match( '/<ok[\/]?>/', $s) ) {
                    $s = fgets($socket, 2048);
                    if (strlen($s) == 0) { break; }
                    $answer .= $s;
                    $s = substr($answer, strlen($answer)-20);

                    # Un caractere 0 peut se trouver a la fin de la chaine
                    if (substr($answer, -1) == chr(0)) {
                        $answer = substr($answer, 0, -1);
                        break;
                    }
                }
                $answer = trim($answer);
                fclose( $socket);
                return $this->xml2array($answer);
            }
        return array();
    }
    
    function xml2array($contents, $get_attributes=1, $priority = 'tag') {
        if(!$contents) return array();

        if(!function_exists('xml_parser_create')) {
            //print "'xml_parser_create()' function not found!";
            return array();
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if(!$xml_values) return;//Hmm...

        //Initializations
        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();

        $current = &$xml_array; //Refference

        //Go through the tags.
        $repeated_tag_index = array();//Multiple tags with same name will be turned into an array
        foreach($xml_values as $data) {
            unset($attributes,$value);//Remove existing values, or there will be trouble

            //This command will extract these variables into the foreach scope
            // tag(string), type(string), level(int), attributes(array).
            extract($data);//We could use the array by itself, but this cooler.

            $result = array();
            $attributes_data = array();

            if(isset($value)) {
                if($priority == 'tag') $result = $value;
                else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
            }

            //Set the attributes too.
            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                }
            }

            //See tag status and do the needed.
            if($type == "open") {//The starting of the tag '<tag>'
                $parent[$level-1] = &$current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag.'_'.$level] = 1;

                    $current = &$current[$tag];

                } else { //There was another element with the same tag name

                    if(isset($current[$tag][0])) {//If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {//This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag.'_'.$level] = 2;

                        if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }

                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }

            } elseif($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if(!isset($current[$tag])) { //New Key
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;

                } else { //If taken, put all things inside a list(array)
                    if(isset($current[$tag][0]) and is_array($current[$tag])) {//If it is already an array...

                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;

                        if($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;

                    } else { //If it is not an array...
                        $current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if($priority == 'tag' and $get_attributes) {
                            if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well

                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }

                            if($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
                    }
                }

            } elseif($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level-1];
            }
        }

        return($xml_array);
    }  
}
