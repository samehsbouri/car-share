<?php

namespace App\Controller;

use App\Entity\Trajet;
use App\Entity\Reservation;
use App\Entity\Avis;
use App\Form\TrajetType;
use App\Repository\TrajetRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trajet')]
class TrajetController extends AbstractController
{
    #[Route('/', name: 'app_trajet_index', methods: ['GET'])]
    public function index(TrajetRepository $trajetRepository): Response
    {
        $trajets = $trajetRepository->createQueryBuilder('t')
            ->where('t.dateDepart >= :yesterday')
            ->setParameter('yesterday', new \DateTime('-24 hours'))
            ->orderBy('t.dateDepart', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('trajet/index.html.twig', [
            'trajets' => $trajets,
        ]);
    }

    #[Route('/new', name: 'app_trajet_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Vérifier si l'utilisateur a des véhicules
        if ($user->getVehicules()->isEmpty()) {
            $this->addFlash('error', 'Vous devez d\'abord ajouter un véhicule avant de pouvoir proposer un trajet.');
            return $this->redirectToRoute('app_vehicule_new');
        }

        $trajet = new Trajet();
        $trajet->setConducteur($user);

        $form = $this->createForm(TrajetType::class, $trajet, [
            'user' => $user // Passer l'utilisateur au formulaire
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($trajet);
            $entityManager->flush();

            $this->addFlash('success', 'Votre trajet a été créé avec succès.');
            return $this->redirectToRoute('app_trajet_index');
        }

        return $this->render('trajet/new.html.twig', [
            'trajet' => $trajet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_trajet_show', methods: ['GET'])]
    public function show(Trajet $trajet, ReservationRepository $reservationRepository): Response
    {
        $reservation = null;
        if ($this->getUser()) {
            $reservation = $reservationRepository->findOneBy([
                'trajet' => $trajet,
                'passager' => $this->getUser(),
                'statut' => 'confirmé'
            ]);
        }

        return $this->render('trajet/show.html.twig', [
            'trajet' => $trajet,
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_trajet_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce trajet.');
        }

        $form = $this->createForm(TrajetType::class, $trajet, [
            'user' => $this->getUser() // Passer l'utilisateur au formulaire
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le trajet a été modifié avec succès.');
            return $this->redirectToRoute('app_trajet_index');
        }

        return $this->render('trajet/edit.html.twig', [
            'trajet' => $trajet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/reserver', name: 'app_trajet_reserver', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reserver(
        Trajet $trajet,
        EntityManagerInterface $entityManager,
        ReservationRepository $reservationRepository
    ): Response {
        try {
            if ($trajet->getConducteur() === $this->getUser()) {
                throw new \Exception('Vous ne pouvez pas réserver votre propre trajet.');
            }

            if ($trajet->getPlaces() <= 0) {
                throw new \Exception('Désolé, il n\'y a plus de places disponibles pour ce trajet.');
            }

            if ($trajet->getDateDepart() < new \DateTime()) {
                throw new \Exception('Ce trajet est déjà passé.');
            }

            $existingReservation = $reservationRepository->findOneBy([
                'trajet' => $trajet,
                'passager' => $this->getUser(),
                'statut' => 'confirmé'
            ]);

            if ($existingReservation) {
                throw new \Exception('Vous avez déjà réservé ce trajet.');
            }

            $reservation = new Reservation();
            $reservation->setPassager($this->getUser());
            $reservation->setTrajet($trajet);
            $reservation->setDateReservation(new \DateTime());
            $reservation->setStatut('confirmé');
            $reservation->setNombrePlaces(1);

            $trajet->setPlaces($trajet->getPlaces() - 1);

            $entityManager->persist($reservation);
            $entityManager->persist($trajet);
            $entityManager->flush();

            $this->addFlash('success', 'Votre réservation a été effectuée avec succès !');

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_trajet_index');
    }

    #[Route('/{id}/mes-trajets', name: 'app_mes_trajets', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mesTrajets(TrajetRepository $trajetRepository): Response
    {
        $trajets = $trajetRepository->findBy(
            ['conducteur' => $this->getUser()],
            ['dateDepart' => 'DESC']
        );

        return $this->render('trajet/mes_trajets.html.twig', [
            'trajets' => $trajets,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_trajet_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce trajet.');
        }

        if ($this->isCsrfTokenValid('delete'.$trajet->getId(), $request->request->get('_token'))) {
            $entityManager->remove($trajet);
            $entityManager->flush();

            $this->addFlash('success', 'Le trajet a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_trajet_index');
    }
}