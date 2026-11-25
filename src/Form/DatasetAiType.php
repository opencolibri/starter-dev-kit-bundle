<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * A catalog written by the AI from a description (sylius:dataset:build --source=ai).
 */
final class DatasetAiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Dataset name',
                'help' => 'Building a dataset with the name of an existing one replaces its catalog.',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Catalog description',
                'help' => 'What the shop sells: the AI writes its products, collections and prices from it.',
                'attr' => ['rows' => 4],
                'constraints' => [new NotBlank()],
            ])
            ->add('url', TextType::class, [
                'label' => 'URL of the shop (optional)',
                'required' => false,
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'Number of products (20 at most)',
                'data' => 20,
                'attr' => ['min' => 1, 'max' => 20],
                'constraints' => [new Range(min: 1, max: 20)],
            ])
            ->add('images', CheckboxType::class, [
                'label' => 'Generate the images',
                'help' => 'Uncheck to add them by hand, or to generate them later from the dataset page: the products keep their image prompt.',
                'required' => false,
                'data' => true,
            ])
        ;
    }
}
