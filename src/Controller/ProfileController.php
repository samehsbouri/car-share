<?php

namespace App\Controller;

use App\Form\UserProfileType;
use App\Form\ChangePasswordType;
use App\Entity\Trajet;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Récupération des statistiques des trajets futurs
        $trajetsFutursCount = $entityManager->getRepository(Trajet::class)
            ->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.conducteur = :user')
            ->andWhere('t.dateDepart > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération du nombre total de trajets proposés
        $trajetsProposesCount = $entityManager->getRepository(Trajet::class)
            ->count(['conducteur' => $user]);

        // Récupération du nombre de réservations
        $reservationsCount = $entityManager->getRepository(Reservation::class)
            ->count(['passager' => $user]);

        $stats = [
            'trajetsProposesCount' => $trajetsProposesCount,
            'reservationsCount' => $reservationsCount,
            'trajetsFutursCount' => $trajetsFutursCount
        ];

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'stats' => $stats
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                    // Vérification du type de fichier
                    $allowedMimeTypes = ['image/jpeg', 'image/png'];
                    if (!in_array($photoFile->getMimeType(), $allowedMimeTypes)) {
                        throw new \Exception('Le type de fichier n\'est pas autorisé. Utilisez JPG ou PNG.');
                    }

                    // Vérification de la taille (2Mo max)
                    $maxSize = 2 * 1024 * 1024;
                    if ($photoFile->getSize() > $maxSize) {
                        throw new \Exception('La taille du fichier ne doit pas dépasser 2 Mo.');
                    }

                    // Suppression de l'ancienne photo
                    if ($user->getPhoto()) {
                        $oldPhotoPath = $this->getParameter('uploads_directory').'/'.$user->getPhoto();
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    // Upload de la nouvelle photo
                    try {
                        $photoFile->move(
                            $this->getParameter('uploads_directory'),
                            $newFilename
                        );
                        $user->setPhoto($newFilename);
                    } catch (FileException $e) {
                        throw new \Exception('Une erreur est survenue lors de l\'upload de l\'image.');
                    }
                }

                $entityManager->flush();
                $this->addFlash('success', 'Votre profil a été mis à jour avec succès');

                return $this->redirectToRoute('app_profile');

            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/api/change-password', name: 'app_profile_change_password_api', methods: ['POST'])]
    public function changePasswordApi(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $user = $this->getUser();

            if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
                throw new \Exception('Données manquantes');
            }

            // Vérification du mot de passe actuel
            if (!$passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 400);
            }

            // Hashage et enregistrement du nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/change-password', name: 'app_profile_change_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$passwordHasher->isPasswordValid($user, $form->get('currentPassword')->getData())) {
                    throw new \Exception('Le mot de passe actuel est incorrect');
                }

                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $form->get('newPassword')->getData()
                    )
                );

                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès');
                return $this->redirectToRoute('app_profile');

            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/mes-trajets', name: 'app_mes_trajets')]
    public function mesTrajets(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $now = new \DateTime();

        // Trajets en tant que conducteur
        $qbConducteur = $entityManager->getRepository(Trajet::class)
            ->createQueryBuilder('t')
            ->where('t.conducteur = :user')
            ->orderBy('t.dateDepart', 'DESC')
            ->setParameter('user', $user);

        $trajetsEnTantQueConducteur = $qbConducteur->getQuery()->getResult();

        // Trajets en tant que passager
        $qbPassager = $entityManager->getRepository(Trajet::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.reservations', 'r')
            ->where('r.passager = :user')
            ->orderBy('t.dateDepart', 'DESC')
            ->setParameter('user', $user);

        $trajetsEnTantQuePassager = $qbPassager->getQuery()->getResult();

        // Statistiques
        $trajetsFuturs = array_filter($trajetsEnTantQueConducteur, function($trajet) use ($now) {
            return $trajet->getDateDepart() > $now;
        });

        $trajetsPasses = array_filter($trajetsEnTantQueConducteur, function($trajet) use ($now) {
            return $trajet->getDateDepart() <= $now;
        });

        $stats = [
            'trajetsPassesCount' => count($trajetsPasses),
            'trajetsFutursCount' => count($trajetsFuturs),
            'reservationsCount' => count($trajetsEnTantQuePassager)
        ];

        return $this->render('profile/mes_trajets.html.twig', [
            'trajetsEnTantQueConducteur' => $trajetsEnTantQueConducteur,
            'trajetsEnTantQuePassager' => $trajetsEnTantQuePassager,
            'stats' => $stats,
            'user' => $user
        ]);
    }

    #[Route('/mes-reservations', name: 'app_mes_reservations')]
    public function mesReservations(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $reservations = $entityManager->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.passager = :user')
            ->orderBy('r.dateReservation', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $this->render('profile/mes_reservations.html.twig', [
            'reservations' => $reservations,
            'user' => $user
        ]);
    }
}