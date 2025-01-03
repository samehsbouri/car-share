<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateReservation = null;

    #[ORM\Column(length: 255)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?int $nombrePlaces = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trajet $trajet = null;

    #[ORM\ManyToOne(inversedBy: 'reservations_passager')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $passager = null;

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
        $this->statut = 'en_attente'; // Statut par défaut
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): ?\DateTimeInterface
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $validStatuts = ['en_attente', 'confirmé', 'refusé', 'annulé', 'terminé']; // Ajout de 'terminé'
        if (!in_array($statut, $validStatuts)) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->statut = $statut;
        return $this;
    }

    public function getNombrePlaces(): ?int
    {
        return $this->nombrePlaces;
    }

    public function estTerminee(): bool
    {
        return $this->statut === 'terminé';
    }
    public function setNombrePlaces(int $nombrePlaces): static
    {
        if ($nombrePlaces <= 0) {
            throw new \InvalidArgumentException('Le nombre de places doit être supérieur à 0');
        }
        $this->nombrePlaces = $nombrePlaces;
        return $this;
    }

    public function getTrajet(): ?Trajet
    {
        return $this->trajet;
    }

    public function setTrajet(?Trajet $trajet): static
    {
        $this->trajet = $trajet;
        return $this;
    }

    public function getPassager(): ?User
    {
        return $this->passager;
    }

    public function setPassager(?User $passager): static
    {
        $this->passager = $passager;
        return $this;
    }

    /**
     * Calcule le prix total de la réservation
     */
    public function getPrixTotal(): float
    {
        if (!$this->trajet) {
            return 0.0;
        }
        return $this->trajet->getPrix() * $this->nombrePlaces;
    }

    /**
     * Vérifie si la réservation peut être annulée
     */
    public function peutEtreAnnulee(): bool
    {
        if (!$this->dateReservation || !$this->trajet) {
            return false;
        }

        $now = new \DateTime();
        $departTrajet = $this->trajet->getDateDepart();

        // On peut annuler jusqu'à 24h avant le départ
        $limiteDateAnnulation = clone $departTrajet;
        $limiteDateAnnulation->modify('-24 hours');

        return $now <= $limiteDateAnnulation;
    }

    /**
     * Vérifie si la réservation est active (ni annulée ni refusée)
     */
    public function estActive(): bool
    {
        return !in_array($this->statut, ['annulé', 'refusé']);
    }

    /**
     * Vérifie si la réservation est confirmée
     */
    public function estConfirmee(): bool
    {
        return $this->statut === 'confirmé';
    }
}