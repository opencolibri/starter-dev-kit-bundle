<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A dataset written by hand: its name, description and suggested theme. Creating one, or changing one built here.
 */
final class DatasetSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Dataset name',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
        ;

        if (null !== $options['themes']) {
            $builder->add('theme', ChoiceType::class, [
                'label' => 'Suggested theme (optional)',
                'help' => 'The theme this catalog is made for, shown on its page.',
                'required' => false,
                'placeholder' => 'None',
                'choices' => array_combine(
                    array_map(static fn(string $theme): string => ucwords(str_replace('_', ' ', $theme)), $options['themes']),
                    $options['themes'],
                ),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('themes', null)
            ->setAllowedTypes('themes', ['null', 'string[]'])
        ;
    }
}
