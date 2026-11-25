<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * The catalog of an existing website, fetched then curated by the AI (sylius:dataset:build --source=existing).
 */
final class DatasetExistingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Dataset name',
                'help' => 'Building a dataset with the name of an existing one replaces its catalog.',
                'constraints' => [new NotBlank()],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Site URL to import',
                'default_protocol' => 'https',
                'help' => 'Only a site you own, or whose owner gave you their written permission: scraping may be forbidden by its terms of use, copyright or database rights.',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'Number of products to keep',
                'data' => 100,
                'attr' => ['min' => 1],
                'constraints' => [new Range(min: 1, max: 500)],
            ])
        ;
    }
}
