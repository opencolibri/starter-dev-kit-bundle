<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class DatasetProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'constraints' => [new NotBlank()],
            ])
            ->add('collection', ChoiceType::class, [
                'label' => 'Collection',
                'required' => false,
                'placeholder' => 'No collection',
                'choices' => $options['collections'],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price (€)',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('image', FileType::class, [
                'label' => match (true) {
                    $options['has_image'] => 'Image (optional — keeps the current one if empty)',
                    $options['is_new'] => 'Image',
                    default => 'Image (optional — or fetch it from the dataset page, from its URL or prompt)',
                },
                // A new product needs one; an existing one without (built with --skip-images) gets it now or later.
                'required' => $options['is_new'],
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp,image/avif'],
                'constraints' => [
                    new File(maxSize: '10M'),
                    ...($options['is_new'] ? [new NotBlank(message: 'Every product needs an image.')] : []),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['collections' => [], 'has_image' => false, 'is_new' => false])
            ->setAllowedTypes('collections', 'array')
            ->setAllowedTypes('has_image', 'bool')
            ->setAllowedTypes('is_new', 'bool')
        ;
    }
}
