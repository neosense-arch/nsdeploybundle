<?php

namespace NS\DeployBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('NSDeployBundle:Default:index.html.twig', array('name' => $name));
    }
}
