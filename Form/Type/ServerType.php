<?php

namespace NS\DeployBundle\Form\Type;

use Doctrine\ORM\EntityRepository;
use NS\CatalogBundle\Entity\CategoryRepository;
use NS\CatalogBundle\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ServerType extends AbstractType
{
	/**
	 * Builds form
	 *
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder
            ->add('title', 'text', array(
                'label'    => 'Название',
                'required' => true,
            ))
            ->add('host', 'text', array(
                'label'    => 'Хост',
                'required' => true,
            ))
            ->add('user', 'text', array(
                'label'    => 'Имя пользователя',
                'required' => true,
            ))
            ->add('path', 'text', array(
                'label'    => 'Путь на сервере',
                'required' => true,
            ))
        ;
    }

	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'NS\DeployBundle\Entity\Server'
        ));
    }

	/**
	 * @return string
	 */
	public function getName()
    {
        return 'ns_deploy_server';
    }
}
