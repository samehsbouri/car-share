<?php

namespace App\Form;

use App\Entity\Vehicule;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;

class VehiculeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez la marque du véhicule'
                ]
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le modèle du véhicule'
                ]
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez l\'année du véhicule',
                    'min' => 1900,
                    'max' => date('Y')
                ]
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez la couleur du véhicule'
                ]
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez l\'immatriculation',
                    'maxlength' => 20
                ]
            ])
            ->add('nombrePlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le nombre de places',
                    'min' => 1
                ]
            ])
            ->add('proprietaire', EntityType::class, [
                'class' => User::class,
                'choice_label' => function ($user) {
                    return $user->getPrenom() . ' ' . $user->getNom();
                },
                'label' => 'Propriétaire',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du véhicule',
                'mapped' => false,  // Ce champ n'est pas mappé directement à une propriété de l'entité
                'required' => false, // Le champ n'est pas obligatoire
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M', // Limiter la taille du fichier à 2 Mo
                        'mimeTypes' => [
                            'image/jpeg',  // Types MIME autorisés
                            'image/png',
                            'image/jpg'
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, JPEG ou PNG)',
                    ])
                ],
                'help' => 'Formats acceptés : JPG, JPEG, PNG. Taille maximale : 2 Mo' // Message d'aide pour l'utilisateur
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Vehicule::class,
            'is_admin' => false // Ajout d'une option pour savoir si l'utilisateur est un administrateur
        ]);
    }
}
