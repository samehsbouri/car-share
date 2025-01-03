<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Form\ReservationType; // ou App\Form\Reservation1Type selon le nom que vous avez choisi
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        // Si l'utilisateur est un admin, afficher toutes les réservations
        if ($this->isGranted('ROLE_ADMIN')) {
            $reservations = $reservationRepository->findAll();
        } else {
            // Sinon, afficher uniquement les réservations de l'utilisateur connecté
            $reservations = $reservationRepository->findBy(['passager' => $this->getUser()]);
        }

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        $reservation->setPassager($this->getUser());
        $reservation->setDateReservation(new \DateTime());
        $reservation->setStatut('en attente');

        $form = $this->createForm(Reservation1Type::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si le trajet a assez de places
            $trajet = $reservation->getTrajet();
            if ($trajet->getPlaces() >= $reservation->getNombrePlaces()) {
                // Mettre à jour le nombre de places disponibles
                $trajet->setPlaces($trajet->getPlaces() - $reservation->getNombrePlaces());

                $entityManager->persist($reservation);
                $entityManager->flush();

                $this->addFlash('success', 'Votre réservation a été effectuée avec succès !');
                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } else {
                $this->addFlash('error', 'Désolé, il n\'y a pas assez de places disponibles.');
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Reservation $reservation): Response
    {
        // Vérifier si l'utilisateur a le droit de voir cette réservation
        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette réservation.');
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur a le droit de modifier cette réservation
        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de modifier cette réservation.');
        }

        $oldNombrePlaces = $reservation->getNombrePlaces();
        $form = $this->createForm(Reservation1Type::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si ce n'est pas un admin, on ne modifie que le nombre de places
            if (!$this->isGranted('ROLE_ADMIN')) {
                $trajet = $reservation->getTrajet();
                $placesDisponibles = $trajet->getPlaces() + $oldNombrePlaces;

                if ($placesDisponibles >= $reservation->getNombrePlaces()) {
                    $trajet->setPlaces($placesDisponibles - $reservation->getNombrePlaces());
                } else {
                    $this->addFlash('error', 'Désolé, il n\'y a pas assez de places disponibles.');
                    return $this->redirectToRoute('app_reservation_edit', ['id' => $reservation->getId()]);
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Réservation modifiée avec succès !');
                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification.');
            }
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/change-status', name: 'app_reservation_change_status', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changeStatus(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier si l'utilisateur a le droit de modifier cette réservation
        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de modifier cette réservation.');
        }

        $newStatus = $request->request->get('status');
        $validStatuts = ['en_attente', 'confirmé', 'terminé', 'annulé'];

        if (!in_array($newStatus, $validStatuts)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_reservation_index');
        }

        // Si on annule la réservation, on remet les places disponibles
        if ($newStatus === 'annulé' && $reservation->getStatut() !== 'annulé') {
            $trajet = $reservation->getTrajet();
            $trajet->setPlaces($trajet->getPlaces() + $reservation->getNombrePlaces());
        }

        $reservation->setStatut($newStatus);
        $entityManager->flush();

        $this->addFlash('success', 'Le statut de la réservation a été mis à jour.');
        return $this->redirectToRoute('app_reservation_index');
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur a le droit de supprimer cette réservation
        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit de supprimer cette réservation.');
        }

        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->getPayload()->getString('_token'))) {
            // Remettre à jour le nombre de places disponibles
            $trajet = $reservation->getTrajet();
            $trajet->setPlaces($trajet->getPlaces() + $reservation->getNombrePlaces());

            $entityManager->remove($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Réservation supprimée avec succès !');
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/trajet/{id}/reserver', name: 'app_trajet_reserver', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reserver(Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur est le conducteur
        if ($trajet->getConducteur() === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas réserver votre propre trajet.');
            return $this->redirectToRoute('app_trajet_index');
        }

        if ($trajet->getPlaces() <= 0) {
            $this->addFlash('error', 'Désolé, il n\'y a plus de places disponibles.');
            return $this->redirectToRoute('app_trajet_index');
        }

        // Vérifier si l'utilisateur a déjà réservé ce trajet
        $existingReservation = $entityManager->getRepository(Reservation::class)->findOneBy([
            'trajet' => $trajet,
            'passager' => $this->getUser()
        ]);

        if ($existingReservation) {
            $this->addFlash('error', 'Vous avez déjà réservé ce trajet.');
            return $this->redirectToRoute('app_trajet_index');
        }

        $reservation = new Reservation();
        $reservation->setTrajet($trajet);
        $reservation->setPassager($this->getUser());
        $reservation->setDateReservation(new \DateTime());
        $reservation->setStatut('confirmé');
        $reservation->setNombrePlaces(1);

        $trajet->setPlaces($trajet->getPlaces() - 1);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Réservation effectuée avec succès !');
        return $this->redirectToRoute('app_trajet_index');
    }
}