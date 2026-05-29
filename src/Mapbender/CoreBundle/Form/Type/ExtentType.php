<?php

namespace Mapbender\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExtentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static function ($modelData) {
                if ($modelData === null || $modelData === '') {
                    return '';
                }
                if (is_array($modelData)) {
                    return implode(',', array_map(static fn ($v) => (string)$v, $modelData));
                }
                return (string)$modelData;
            },
            static function ($viewData) {
                if ($viewData === null || $viewData === '') {
                    return null;
                }
                if (is_array($viewData)) {
                    return $viewData;
                }
                $parts = preg_split('/[\s,;]+/', trim((string)$viewData));
                $parts = array_values(array_filter($parts, static fn ($v) => $v !== ''));
                return array_map(static fn ($v) => is_numeric($v) ? (float)$v : $v, $parts);
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'placeholder' => '7.03, 50.71, 7.17, 50.76',
            ],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
