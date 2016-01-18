<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class GanttController extends Controller
{
    public function imageAction($start='0000',$end='2359',$days=0,$status='LEGEND') {
        $request = Request::createFromGlobals();
        // Heure locale
        $start = $this->Hours($request->query->get( 'start' ));
        if ($request->query->get( 'end' )!='') {
            $end = $this->Hours($request->query->get( 'end' ));
        }
        else {
            $Now = localtime(time(), true);
            $end = $Now['tm_hour']+$Now['tm_min']/60;
        }
       // print "(($start $end))";
        if ($request->query->get( 'status' )!='')
            $status = $request->query->get( 'status' );
        if ($request->query->get( 'days' )>0)
            $days = $request->query->get( 'days' );
        if ($days>7) $days=7;
        
        $step = 20;
        $width = $step*24; $height = 16;
        $im = imagecreate($width,$height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $green = imagecolorallocate($im, 50, 120, 40);
        $red = imagecolorallocate($im, 255, 0, 0);
        $orange = imagecolorallocate($im, 255, 200, 70);
        $grey = imagecolorallocate($im, 200, 200, 200);
        imagecolortransparent($im, $white);
        header ("Content-type: image/png");
        // Legende
        if ($status == 'LEGEND') {
            $font = dirname(__FILE__) .'/../Resources/fonts/arial.ttf';
            for($i=0;$i<24;$i++) {
                imagettftext($im, 8, 0, $i*$step , 10, $black, $font, $i);
            }            
            imagepng($im);
            exit();            
        }
        $color = array( 'READY' => $green,
                        'SUCCESS' => $green,
                        'STOPPED' => $black,
                        'FAILURE' => $red,
                        'RUNNING' => $orange );
        for($i=0;$i<24;$i++) {
            imageline ($im , $i*$step , 0 , $i*$step , $height , $grey );
        }
        if (isset($color[$status]))
            $col = $color[$status];
        else
            $col = $black;
        
        if ($start>$end) {
            $h = ($height/2)-$days-2;
            imagefilledrectangle($im, $start*$step, 1, 24*$step, $h+1, $col);            
            imagefilledrectangle($im, 0,$days*2+$h+2, $end*$step, ($h+$days)*2+1, $col);
            for($i=0;$i<$days;$i++) {
                imageline($im, 0, $h+3+($i*2), $width, $h+3+($i*2), $black);
            }
        }
        elseif ($days==0) {
            imagefilledrectangle($im, $start*$step, 1, $end*$step, $height-2, $col);
        }
        else {
            $h = ($height/2)-$days-2;
            imagefilledrectangle($im, $start*$step, 1, 24*$step, $h+1, $col);            
            imagefilledrectangle($im, 0,$days*2+$h+2, $end*$step, ($h+$days)*2+1, $col );
            for($i=0;$i<$days;$i++) {
                imageline($im, 0, $h+3+($i*2), $width, $h+3+($i*2), $black );
            }
        }
        imagepng($im);        
        exit();
    }

    public function percentAction($percent=100,$color='LEGEND') {
        $request = Request::createFromGlobals();
        // Heure locale
        if ($request->query->get( 'percent' )!='') {
            $percent = $this->Hours($request->query->get( 'end' ));
        }
        if ($request->query->get( 'color' )!='')
            $color = $request->query->get( 'color' );

        $width = 100; $height = 16;
        $im = imagecreate($width,$height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $green = imagecolorallocate($im, 50, 120, 40);
        $red = imagecolorallocate($im, 255, 0, 0);
        $orange = imagecolorallocate($im, 255, 200, 70);
        $grey = imagecolorallocate($im, 200, 200, 200);
        imagecolortransparent($im, $white);
        header ("Content-type: image/png");
        $color_rgb = array( 'READY' => $green,
                        'green' => $green,
                        'black' => $black,
                        'red' => $red,
                        'orange' => $orange );
        if (isset($color_rgb[$color]))
            $col = $color_rgb[$color];
        else 
            $col = $color_rgb['black'];
        
        imagefilledrectangle($im, 0, 0, $percent, $height, $col);
        imagepng($im);        
        exit();
    }
 
    private function Hours($date) {
        return substr($date,0,2)+substr($date,2,2)/60;
    }
}
