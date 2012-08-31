<?php

namespace Mapbender\ManagerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Mapbender\ManagerBundle\Form\Type\BaseElementType;

class ApplicationType extends AbstractType {
    public function getName() {
        return 'application';
    }

    // TODO: Switch to setDefaultOptions (before Symfony 2.3)
    public function getDefaultOptions() {
        return array(
            'available_templates' => array());
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder
            // Base data
            ->add('title', 'text', array(
                'attr' => array(
                    'title' => 'The application title, as shown in the browser '
                        . 'title bar and in lists.')))
            ->add('slug', 'text', array(
                'attr' => array(
                    'title' => 'The slug is based on the title and used in the '
                        . 'application URL.')))
            ->add('description', 'textarea', array(
                'required' => false,
                'attr' => array(
                    'title' => 'The description is used in overview lists.')))
            ->add('template', 'choice', array(
                'choices' => $options['available_templates'],
                'attr' => array(
                    'title' => 'The HTML template used for this '
                    .'application.')))

            // Security
            ->add('published', 'checkbox', array(
                'required' => false,
                'label' => 'Published'))
            ->add('owner', 'entity', array(
                'class' => 'FOMUserBundle:User',
                'property' => 'username',
                'label' => 'Owner'))
            ->add('roles', 'entity', array(
                'class' => 'FOMUserBundle:Role',
                'expanded' => true,
                'multiple' => true,
                'property' => 'title',
                'label' => 'Roles'));
    }
}
