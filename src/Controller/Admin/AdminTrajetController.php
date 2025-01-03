<?php

namespace App\Controller\Admin;

use App\Entity\Trajet;
use App\Form\TrajetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/trajets')]
#[IsGranted('ROLE_ADMIN')]
class AdminTrajetController extends AbstractController
{
    #[Route('/', name: 'app_admin_trajets_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $trajets = $entityManager->getRepository(Trajet::class)->findAll();
        return $this->render('admin/trajet/index.html.twig', [
            'trajets' => $trajets,
        ]);
    }

    #[Route('/new', name: 'app_admin_trajets_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $trajet = new Trajet();
        // Définir l'utilisateur connecté comme conducteur
        $trajet->setConducteur($this->getUser());

        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($trajet);
            $entityManager->flush();

            $this->addFlash('success', 'Trajet créé avec succès');
            return $this->redirectToRoute('app_admin_trajets_index');
        }

        return $this->render('admin/trajet/new.html.twig', [
            'trajet' => $trajet,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_trajets_show', methods: ['GET'])]
    public function show(Trajet $trajet): Response
    {
        return $this->render('admin/trajet/show.html.twig', [
            'trajet' => $trajet,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_trajets_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Trajet modifié avec succès');
            return $this->redirectToRoute('app_admin_trajets_index');
        }

        return $this->render('admin/trajet/edit.html.twig', [
            'trajet' => $trajet,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_trajets_delete', methods: ['POST'])]
    public function delete(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete', $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Token CSRF invalide'
            ], 400);
        }

        try {
            $entityManager->remove($trajet);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Trajet supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }
    }

    // Méthode utilitaire pour vérifier si un trajet peut être supprimé
    private function canDeleteTrajet(Trajet $trajet): bool
    {
        // Vérifier si le trajet a des réservations
        if (!$trajet->getReservations()->isEmpty()) {
            return false;
        }

        // Ajouter d'autres conditions si nécessaire
        // Par exemple, vérifier si le trajet n'est pas déjà commencé
        $now = new \DateTime();
        if ($trajet->getDateDepart() <= $now) {
            return false;
        }

        return true;
    }
}