<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DatasetCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('parent', ChoiceType::class, [
                'label' => 'Shown under (optional)',
                'help' => 'A sub-collection appears under its parent in the shop menu.',
                'required' => false,
                'placeholder' => 'Top level',
                'choices' => $options['parents'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('image', FileType::class, [
                'label' => $options['has_image'] ? 'Image (optional — keeps the current one if empty)' : 'Image (optional)',
                'help' => 'Shown on the category tiles of the homepage.',
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp,image/avif'],
                'constraints' => [new File(maxSize: '10M')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['parents' => [], 'has_image' => false])
            ->setAllowedTypes('parents', 'array')
            ->setAllowedTypes('has_image', 'bool')
        ;
    }
}
