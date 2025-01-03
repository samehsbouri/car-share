<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Trajet;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/avis')]
#[IsGranted('ROLE_USER')]
class AvisController extends AbstractController
{
    #[Route('/', name: 'app_avis_index')]
    public function index(EntityManagerInterface $entityManager, AvisRepository $avisRepository): Response
    {
        $user = $this->getUser();

        // Récupérer les avis donnés par l'utilisateur
        $avis = $avisRepository->findBy(['auteur' => $user]);

        // Récupérer les réservations terminées
        $reservationsTerminees = $entityManager->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.trajet', 't')
            ->leftJoin('t.conducteur', 'c')
            ->leftJoin('App\Entity\Avis', 'a', 'WITH', 'a.trajet = t AND a.auteur = :user')
            ->where('r.passager = :user')
            ->andWhere('r.statut = :statut')
            ->andWhere('a.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('statut', 'terminé')
            ->orderBy('t.dateDepart', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('avis/index.html.twig', [
            'avis' => $avis,
            'reservationsTerminees' => $reservationsTerminees,
        ]);
    }

    #[Route('/reservation/{id}/terminer', name: 'app_reservation_terminer')]
    public function terminerReservation(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur est le passager de la réservation
        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette réservation.');
        }

        // Vérifier si la réservation peut être marquée comme terminée
        if ($reservation->getStatut() !== 'confirmé') {
            $this->addFlash('error', 'Cette réservation ne peut pas être marquée comme terminée.');
            return $this->redirectToRoute('app_avis_index');
        }

        $reservation->setStatut('terminé');
        $entityManager->flush();

        $this->addFlash('success', 'La réservation a été marquée comme terminée.');
        return $this->redirectToRoute('app_avis_index');
    }

    #[Route('/trajet/{id}/nouveau', name: 'app_avis_new')]
    public function new(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur a déjà laissé un avis pour ce trajet
        $existingAvis = $entityManager->getRepository(Avis::class)->findOneBy([
            'auteur' => $this->getUser(),
            'trajet' => $trajet
        ]);

        if ($existingAvis) {
            $this->addFlash('error', 'Vous avez déjà laissé un avis pour ce trajet.');
            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
        }

        $avis = new Avis();
        $avis->setAuteur($this->getUser());
        $avis->setDestinataire($trajet->getConducteur());
        $avis->setTrajet($trajet);
        $avis->setDateAvis(new \DateTime());

        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($avis);

            // Mettre à jour la note moyenne du conducteur
            $this->updateConducteurRating($trajet->getConducteur(), $entityManager);

            $entityManager->flush();

            $this->addFlash('success', 'Votre avis a été enregistré avec succès !');
            return $this->redirectToRoute('app_avis_index');
        }

        return $this->render('avis/new.html.twig', [
            'trajet' => $trajet,
            'form' => $form->createView(),
        ]);
    }

    private function updateConducteurRating(User $conducteur, EntityManagerInterface $entityManager): void
    {
        $avisRepository = $entityManager->getRepository(Avis::class);
        $allAvis = $avisRepository->findBy(['destinataire' => $conducteur]);

        if (count($allAvis) > 0) {
            $totalNotes = array_reduce($allAvis, function($carry, $avis) {
                return $carry + $avis->getNote();
            }, 0);

            $moyenneNotes = $totalNotes / count($allAvis);
            $conducteur->setNoteMoyenne($moyenneNotes);
        }
    }
}