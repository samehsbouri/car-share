<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Entity\Reservation;
use App\Entity\Avis;
use App\Form\UserType;
use App\Form\TrajetType;
use App\Form\VehiculeType;
use App\Form\ReservationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        // Compter le nombre total de chaque entité
        $userCount = $this->entityManager->getRepository(User::class)->count([]);
        $trajetCount = $this->entityManager->getRepository(Trajet::class)->count([]);
        $vehiculeCount = $this->entityManager->getRepository(Vehicule::class)->count([]);
        $reservationCount = $this->entityManager->getRepository(Reservation::class)->count([]);
        $avisCount = $this->entityManager->getRepository(Avis::class)->count([]);

        // Récupérer les derniers utilisateurs (5 plus récents)
        $recentUsers = $this->entityManager->getRepository(User::class)
            ->findBy([], ['id' => 'DESC'], 5);

        // Récupérer les derniers trajets (5 plus récents)
        $recentTrajets = $this->entityManager->getRepository(Trajet::class)
            ->findBy([], ['dateDepart' => 'DESC'], 5);

        // Récupérer les dernières réservations (5 plus récentes)
        $recentReservations = $this->entityManager->getRepository(Reservation::class)
            ->findBy([], ['id' => 'DESC'], 5);

        // Récupérer les derniers avis (5 plus récents)
        $recentAvis = $this->entityManager->getRepository(Avis::class)
            ->findBy([], ['dateAvis' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'userCount' => $userCount,
            'trajetCount' => $trajetCount,
            'vehiculeCount' => $vehiculeCount,
            'reservationCount' => $reservationCount,
            'avisCount' => $avisCount,
            'recentUsers' => $recentUsers,
            'recentTrajets' => $recentTrajets,
            'recentReservations' => $recentReservations,
            'recentAvis' => $recentAvis,
        ]);
    }

    #[Route('/avis', name: 'app_admin_avis')]
    public function avis(): Response
    {
        $avis = $this->entityManager->getRepository(Avis::class)->findAll();
        return $this->render('admin/avis/index.html.twig', [
            'avis' => $avis,
        ]);
    }

    // Méthodes pour supprimer des éléments


    #[Route('/admin/user/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user): Response
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs', name: 'app_admin_users')]
    public function users(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();
        return $this->render('admin/users.html.twig', [ // Modifié ici
            'users' => $users,
        ]);
    }

    #[Route('/avis/{id}', name: 'app_admin_avis_show', methods: ['GET'])]
    public function showAvis(Avis $avis): Response
    {
        return $this->render('admin/avis/show.html.twig', [
            'avis' => $avis,
        ]);
    }

    #[Route('/avis/{id}/delete', name: 'app_admin_avis_delete', methods: ['POST'])]
    public function deleteAvis(Request $request, Avis $avis): Response
    {
        try {
            if ($this->isCsrfTokenValid('delete'.$avis->getId(), $request->request->get('_token'))) {
                // Récupérer le destinataire avant de supprimer l'avis
                $destinataire = $avis->getDestinataire();

                // Supprimer l'avis
                $this->entityManager->remove($avis);
                $this->entityManager->flush();

                // Recalculer la note moyenne du destinataire
                $allAvis = $this->entityManager->getRepository(Avis::class)
                    ->findBy(['destinataire' => $destinataire]);

                if (!empty($allAvis)) {
                    $totalNotes = array_reduce($allAvis, function($carry, $avis) {
                        return $carry + $avis->getNote();
                    }, 0);
                    $moyenne = $totalNotes / count($allAvis);
                    $destinataire->setNoteMoyenne($moyenne);
                } else {
                    $destinataire->setNoteMoyenne(null);
                }

                $this->entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Avis supprimé avec succès'
                    ]);
                }

                $this->addFlash('success', 'Avis supprimé avec succès');
                return $this->redirectToRoute('app_admin_avis');
            }

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 400);
            }

            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('app_admin_avis');

        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
                ], 500);
            }

            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_avis');
        }
    }

    #[Route('/trajets', name: 'app_admin_trajets')]
    public function trajets(EntityManagerInterface $entityManager): Response
    {
        $trajets = $entityManager->getRepository(Trajet::class)->findAll();
        return $this->render('admin/trajet/index.html.twig', [
            'trajets' => $trajets,
        ]);
    }

    #[Route('/vehicules', name: 'app_admin_vehicules')]
    public function vehicules(): Response
    {
        $vehicules = $this->entityManager->getRepository(Vehicule::class)->findAll();
        return $this->render('admin/vehicules.html.twig', [
            'vehicules' => $vehicules,
        ]);
    }

    #[Route('/reservations', name: 'app_admin_reservations')]
    public function reservations(): Response
    {
        $reservations = $this->entityManager->getRepository(Reservation::class)->findAll();
        return $this->render('admin/reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    // Méthodes pour créer de nouveaux éléments
    #[Route('/user/new', name: 'app_admin_user_new')]
    public function newUser(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form->createView()
        ]);
    }
    #[Route('/user/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function showUser(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/vehicule/new', name: 'app_admin_vehicule_new')]
    public function newVehicule(Request $request): Response
    {
        $vehicule = new Vehicule();
        $form = $this->createForm(VehiculeType::class, $vehicule);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload de l'image
            $imageFile = $form->get('imageFile')->getData(); // Changé de 'photo' à 'imageFile'
            if ($imageFile) {
                // Générer un nom unique pour le fichier
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                // Obtenir le chemin du répertoire où les images seront stockées
                $uploadsDirectory = $this->getParameter('vehicules_directory'); // Changé pour utiliser le bon paramètre

                // Vérifier si le répertoire existe
                if (!file_exists($uploadsDirectory)) {
                    mkdir($uploadsDirectory, 0777, true);
                }

                // Déplacer le fichier dans le répertoire
                $imageFile->move($uploadsDirectory, $newFilename);

                // Mettre à jour le nom de l'image dans l'entité
                $vehicule->setImage($newFilename); // Changé de setPhoto à setImage
            }

            // Persister l'entité
            $this->entityManager->persist($vehicule);
            $this->entityManager->flush();

            $this->addFlash('success', 'Véhicule créé avec succès');
            return $this->redirectToRoute('app_admin_vehicules');
        }

        return $this->render('admin/vehicule/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/vehicule/{id}', name: 'app_admin_vehicule_show', methods: ['GET'])]
    public function showVehicule(Vehicule $vehicule): Response
    {
        return $this->render('admin/vehicule/show.html.twig', [
            'vehicule' => $vehicule,
        ]);
    }

    #[Route('/admin/reservation/{id}', name: 'app_admin_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('admin/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/trajet/new', name: 'app_admin_trajet_new')]
    public function newTrajet(Request $request, EntityManagerInterface $entityManager): Response
    {
        $trajet = new Trajet();
        $form = $this->createForm(TrajetType::class, $trajet);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($trajet);
            $entityManager->flush();
            $this->addFlash('success', 'Trajet créé avec succès');
            return $this->redirectToRoute('app_admin_trajets');
        }

        return $this->render('admin/trajet/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    // Méthodes pour modifier des éléments
    #[Route('/user/{id}/edit', name: 'app_admin_user_edit')]
    public function editUser(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion du mot de passe
                if ($user->getPassword()) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
                    $user->setPassword($hashedPassword);
                }

                // Gestion de l'upload de photo
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    // Générer un nom unique pour le fichier
                    $newFilename = uniqid().'.'.$photoFile->guessExtension();

                    // Obtenir le chemin complet du répertoire
                    $uploadsDirectory = $this->getParameter('uploads_directory');

                    // Vérifier si le répertoire existe
                    if (!file_exists($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0777, true);
                    }

                    // Déplacer le fichier
                    $photoFile->move(
                        $uploadsDirectory,
                        $newFilename
                    );

                    // Supprimer l'ancienne photo si elle existe
                    $oldPhoto = $user->getPhoto();
                    if ($oldPhoto) {
                        $oldPhotoPath = $uploadsDirectory.'/'.$oldPhoto;
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    // Mettre à jour le nom de la photo dans l'entité
                    $user->setPhoto($newFilename);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur modifié avec succès');
                return $this->redirectToRoute('app_admin_users');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification: ' . $e->getMessage());
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/admin/trajet/{id}/edit', name: 'app_admin_trajet_edit')]
    public function editTrajet(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                if (!$request->isXmlHttpRequest()) {
                    $this->addFlash('success', 'Trajet modifié avec succès');
                    return $this->redirectToRoute('app_admin_trajets');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification du trajet');
            }
        }

        return $this->render('admin/trajet/edit.html.twig', [
            'trajet' => $trajet,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reservation/new', name: 'app_admin_reservation_new')]
    public function newReservation(Request $request): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Réservation créée avec succès');
            return $this->redirectToRoute('app_admin_reservations');
        }

        return $this->render('admin/reservation/new.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation
        ]);
    }

    // Modifiez la route admin comme ceci
    #[Route('/admin/trajets/create', name: 'app_admin_trajet_create')]
    public function createTrajet(Request $request, EntityManagerInterface $entityManager): Response
    {
        $trajet = new Trajet();
        $form = $this->createForm(TrajetType::class, $trajet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($trajet);
                $entityManager->flush();

                if (!$request->isXmlHttpRequest()) {
                    $this->addFlash('success', 'Trajet créé avec succès');
                    return $this->redirectToRoute('app_admin_trajets');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du trajet');
            }
        }

        return $this->render('admin/trajet/new.html.twig', [
            'trajet' => $trajet,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/trajet/{id}/delete', name: 'app_admin_trajet_delete', methods: ['POST'])]
    public function deleteTrajet(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Vérifier le token CSRF
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('delete' . $trajet->getId(), $submittedToken)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 400);
            }

            // Supprimer le trajet
            $entityManager->remove($trajet);
            $entityManager->flush();

            // Renvoyer une réponse JSON en cas de succès
            return $this->json([
                'success' => true,
                'message' => 'Trajet supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            // Renvoyer une réponse JSON en cas d'erreur
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/trajet/{id}', name: 'app_admin_trajet_show', methods: ['GET'])]
    public function showTrajet(Trajet $trajet): Response
    {
        return $this->render('admin/trajet/show.html.twig', [
            'trajet' => $trajet,
        ]);
    }

    #[Route('/vehicule/{id}/edit', name: 'app_admin_vehicule_edit')]
    public function editVehicule(Request $request, Vehicule $vehicule): Response
    {
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload d'image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                // Supprimer l'ancienne image si elle existe
                if ($vehicule->getImage()) {
                    $oldImagePath = $this->getParameter('vehicules_directory') . '/' . $vehicule->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Générer un nouveau nom de fichier unique
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                try {
                    // Déplacer le nouveau fichier
                    $imageFile->move(
                        $this->getParameter('vehicules_directory'),
                        $newFilename
                    );

                    // Mettre à jour le nom de l'image dans l'entité
                    $vehicule->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image');
                }
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Véhicule modifié avec succès');
            return $this->redirectToRoute('app_admin_vehicules');
        }

        return $this->render('admin/vehicule/edit.html.twig', [
            'vehicule' => $vehicule,
            'form' => $form->createView()
        ]);
    }

    #[Route('/reservation/{id}/edit', name: 'app_admin_reservation_edit')]
    public function editReservation(Request $request, Reservation $reservation): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Réservation modifiée avec succès');
            return $this->redirectToRoute('app_admin_reservations');
        }

        return $this->render('admin/reservation/edit.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation
        ]);
    }


    #[Route('/vehicule/{id}/delete', name: 'app_admin_vehicule_delete')]
    public function deleteVehicule(Vehicule $vehicule): Response
    {
        $this->entityManager->remove($vehicule);
        $this->entityManager->flush();

        $this->addFlash('success', 'Véhicule supprimé avec succès');
        return $this->redirectToRoute('app_admin_vehicules');
    }

    #[Route('/reservation/{id}/delete', name: 'app_admin_reservation_delete')]
    public function deleteReservation(Reservation $reservation): Response
    {
        $this->entityManager->remove($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Réservation supprimée avec succès');
        return $this->redirectToRoute('app_admin_reservations');
    }
}