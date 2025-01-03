<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security; // Correction ici : bon namespace pour Security

class ReservationType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        $builder
            ->add('dateReservation', DateTimeType::class, [
                'label' => 'Date de réservation',
                'widget' => 'single_text',
                'disabled' => !$isAdmin,
                'attr' => ['class' => 'form-control']
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'En attente' => 'en_attente',
                    'Confirmé' => 'confirmé',
                    'Refusé' => 'refusé',
                    'Annulé' => 'annulé',
                    'Terminé' => 'terminé'
                ],
                'disabled' => !$isAdmin,
                'attr' => ['class' => 'form-control']
            ])
            ->add('nombrePlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1
                ],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Positive(),
                    new \Symfony\Component\Validator\Constraints\NotBlank(),
                ]
            ])
            ->add('trajet', EntityType::class, [
                'class' => Trajet::class,
                'choice_label' => function ($trajet) {
                    return $trajet->getVilleDepart() . ' → ' . $trajet->getVilleArrivee() . ' le ' . $trajet->getDateDepart()->format('d/m/Y H:i');
                },
                'disabled' => !$isAdmin,
                'attr' => ['class' => 'form-control']
            ])
            ->add('passager', EntityType::class, [
                'class' => User::class,
                'choice_label' => function ($user) {
                    return $user->getPrenom() . ' ' . $user->getNom();
                },
                'disabled' => !$isAdmin,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'validation_groups' => ['Default'],
        ]);
    }
}