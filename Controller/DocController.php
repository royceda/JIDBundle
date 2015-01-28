<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DocController extends Controller
{
    public function defaultAction()
    {
        return $this->render('AriiJIDBundle:Doc:default.html.twig' );
    }  
}
