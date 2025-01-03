<?php
// src/Form/AvisType.php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'choices' => [
                    '5 étoiles - Excellent' => 5,
                    '4 étoiles - Très bien' => 4,
                    '3 étoiles - Bien' => 3,
                    '2 étoiles - Moyen' => 2,
                    '1 étoile - À améliorer' => 1
                ],
                'expanded' => true,
                'label' => 'Votre note',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez donner une note']),
                ]
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre commentaire',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez laisser un commentaire']),
                    new Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'Le commentaire doit faire au moins {{ limit }} caractères',
                        'maxMessage' => 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
                    ])
                ],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez votre expérience...'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
        ]);
    }
}