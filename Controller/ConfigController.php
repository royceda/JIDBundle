<?php

namespace Arii\JIDBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ConfigController extends Controller
{
    public function indexAction()
    {
        return $this->render('AriiJIDBundle:Config:index.html.twig' );
    }

    public function configAction()
    {
        return $this->render('AriiJIDBundle:Config:config.html.twig');
    }

}
