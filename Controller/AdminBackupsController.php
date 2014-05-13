<?php

namespace NS\DeployBundle\Controller;

use NS\DeployBundle\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

class AdminBackupsController extends Controller
{
	/**
	 * @return Response
	 */
	public function indexAction()
	{
        /** @var BackupService $backupService */
        $backupService = $this->get('ns_deploy.service.backup');
		return $this->render('NSDeployBundle:AdminBackups:index.html.twig', array(
			'backups' => $backupService->getBackups(),
		));
	}

	/**
	 * @return Response
	 */
	public function deleteAction()
	{
		// edit mode
		if (isset($_GET['id'])) {
            /** @var BackupService $backupService */
            $backupService = $this->get('ns_deploy.service.backup');
            $backups = $backupService->getBackups();

            if (empty($backups[$_GET['id']])) {
                return $this->back();
            }
            unlink($backups[$_GET['id']]->getPathname());
		}

		return $this->back();
	}

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function restoreAction(Request $request)
    {
        /** @var BackupService $backupService */
        $backupService = $this->get('ns_deploy.service.backup');
        $backups = $backupService->getBackups();
        if (empty($backups[$_GET['id']])) {
            return $this->back();
        }
        $backupService->restore($backups[$_GET['id']]->getPathname());
        return $this->back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('dump', 'checkbox', array(
                'label'    => 'Сохранить данные из БД',
                'required' => false,
            ))
            ->add('app', 'checkbox', array(
                'label'    => 'Сохранить директорию «app»',
                'required' => false,
            ))
            ->add('parameters', 'checkbox', array(
                'label'    => 'Сохранить параметры (parameters.yml)',
                'required' => false,
            ))
            ->add('src', 'checkbox', array(
                'label'    => 'Сохранить директорию «src»',
                'required' => false,
            ))
            ->add('vendor', 'checkbox', array(
                'label'    => 'Сохранить директорию «vendor»',
                'required' => false,
            ))
            ->add('web', 'checkbox', array(
                'label'    => 'Сохранить директорию «web»',
                'required' => false,
            ))
            ->add('upload', 'checkbox', array(
                'label'    => 'Сохранить директорию «upload»',
                'required' => false,
            ))
            ->getForm()
        ;

        $form->handleRequest($request);
        if ($form->isValid()) {
            $flags = $form->getData();

            /** @var BackupService $backupService */
            $backupService = $this->get('ns_deploy.service.backup');

            set_time_limit(0);
            $backupService->create($flags['dump'], $flags['app'], $flags['parameters'], $flags['src'],
                $flags['vendor'], $flags['web'], $flags['upload']);

            return $this->back();
        }

        return $this->render('NSDeployBundle:AdminBackups:create.html.twig', array(
            'form' => $form->createView(),
        ));
    }

	/**
	 * @return RedirectResponse
	 */
	private function back()
	{
		return $this->redirect($this->generateUrl(
			'ns_admin_bundle', array(
				'adminBundle'     => 'NSDeployBundle',
				'adminController' => 'backups',
				'adminAction'     => 'index',
			)
		));
	}
}
