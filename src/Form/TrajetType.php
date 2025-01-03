<?php

namespace App\Form;

use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrajetType extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user']; // Récupérer l'utilisateur depuis les options

        $builder
            ->add('dateDepart', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date de départ'
            ])
            ->add('villeDepart', TextType::class, [
                'label' => 'Ville de départ'
            ])
            ->add('villeArrivee', TextType::class, [
                'label' => 'Ville d\'arrivée'
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix'
            ])
            ->add('places', NumberType::class, [
                'label' => 'Nombre de places'
            ])
            ->add('conducteur', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getPrenom() . ' ' . $user->getNom();
                },
                'placeholder' => 'Sélectionnez un conducteur',
                'label' => 'Conducteur',
                'required' => true,
                'mapped' => true,
                'data' => $user // Définir l'utilisateur comme valeur par défaut
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            $trajet = $event->getData();

            $form->add('vehicule', EntityType::class, [
                'class' => Vehicule::class,
                'choice_label' => function (Vehicule $vehicule) {
                    return $vehicule->getMarque() . ' ' . $vehicule->getModele() . ' (' . $vehicule->getImmatriculation() . ')';
                },
                'query_builder' => function ($repository) use ($user) {
                    $qb = $repository->createQueryBuilder('v');
                    if ($user) {
                        $qb->where('v.proprietaire = :user')
                            ->setParameter('user', $user);
                    }
                    return $qb;
                },
                'placeholder' => 'Sélectionnez un véhicule',
                'label' => 'Véhicule',
                'required' => true,
            ]);
        });

        $builder->get('conducteur')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm()->getParent();
            $conducteur = $event->getData();

            if ($form) {
                $form->add('vehicule', EntityType::class, [
                    'class' => Vehicule::class,
                    'choice_label' => function (Vehicule $vehicule) {
                        return $vehicule->getMarque() . ' ' . $vehicule->getModele() . ' (' . $vehicule->getImmatriculation() . ')';
                    },
                    'query_builder' => function ($repository) use ($conducteur) {
                        $qb = $repository->createQueryBuilder('v');
                        if ($conducteur) {
                            $qb->where('v.proprietaire = :conducteur')
                                ->setParameter('conducteur', $conducteur);
                        }
                        return $qb;
                    },
                    'placeholder' => 'Sélectionnez un véhicule',
                    'label' => 'Véhicule',
                    'required' => true,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trajet::class,
            'user' => null, // Définir l'option 'user' avec une valeur par défaut
        ]);
    }
}