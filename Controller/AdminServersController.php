<?php

namespace NS\DeployBundle\Controller;

use NS\DeployBundle\Entity\Server;
use NS\DeployBundle\Entity\ServerRepository;
use NS\DeployBundle\Form\Type\ServerType;
use NS\DeployBundle\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminServersController extends Controller
{
	/**
	 * @return Response
	 */
	public function indexAction()
	{
        /** @var ServerRepository $serverRepository */
        $serverRepository = $this->getDoctrine()->getRepository('NSDeployBundle:Server');
        $servers = $serverRepository->findAll();

		return $this->render('NSDeployBundle:AdminServers:index.html.twig', array(
			'servers' => $servers,
		));
	}

    /**
     * @param Request $request
     * @return Response
     */
    public function formAction(Request $request)
    {
        $server = new Server();
        $id = $request->query->get('id');
        if ($id) {
            /** @var ServerRepository $serverRepository */
            $serverRepository = $this->getDoctrine()->getRepository('NSDeployBundle:Server');
            $server = $serverRepository->find($id);
        }

        $form = $this->createForm(new ServerType(), $server);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->getDoctrine()->getManager()->persist($server);
            $this->getDoctrine()->getManager()->flush();
            return $this->back();
        }

        return $this->render('NSAdminBundle:Layout:col1-form.html.twig', array(
            'form'  => $form->createView(),
            'title' => 'Серверы',
        ));
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function deleteAction(Request $request)
    {
        /** @var ServerRepository $serverRepository */
        $serverRepository = $this->getDoctrine()->getRepository('NSDeployBundle:Server');
        $server = $serverRepository->find($request->query->get('id'));

        if ($server) {
            $this->getDoctrine()->getManager()->remove($server);
            $this->getDoctrine()->getManager()->flush();
        }

        return $this->back();
    }

	/**
	 * @return RedirectResponse
	 */
	private function back()
	{
		return $this->redirect($this->generateUrl(
			'ns_admin_bundle', array(
				'adminBundle'     => 'NSDeployBundle',
				'adminController' => 'servers',
				'adminAction'     => 'index',
			)
		));
	}
}
