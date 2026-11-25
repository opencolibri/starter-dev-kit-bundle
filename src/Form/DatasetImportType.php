<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * A zip in the layout of a downloaded dataset: dataset.yaml, products.yaml, collections.yaml and media/.
 */
final class DatasetImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Zip file',
                'help' => 'A dataset downloaded from a sales kit or exported with "castor sylius:dataset:export", or a zip holding products.yaml, collections.yaml, dataset.yaml and a media/ directory.',
                'attr' => ['accept' => '.zip,application/zip'],
                'constraints' => [new NotBlank(), new File(maxSize: '512M')],
            ])
            ->add('dataset', TextType::class, [
                'label' => 'Slug (optional)',
                'help' => 'Defaults to the directory of the zip, or to the slug of the dataset name.',
                'required' => false,
                'constraints' => [new Regex('/^[a-z0-9][a-z0-9-]*$/', 'Lowercase letters, digits and dashes only.')],
            ])
            ->add('replace', CheckboxType::class, [
                'label' => 'Replace the dataset of the same slug',
                'required' => false,
            ])
        ;
    }
}
