<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true)]
    private ?float $note_moyenne = null;

    /**
     * @var Collection<int, Vehicule>
     */
    #[ORM\OneToMany(targetEntity: Vehicule::class, mappedBy: 'proprietaire', orphanRemoval: true)]
    private Collection $vehicules;

    /**
     * @var Collection<int, Trajet>
     */
    #[ORM\OneToMany(targetEntity: Trajet::class, mappedBy: 'conducteur')]
    private Collection $locations_conducteur;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'passager')]
    private Collection $reservations_passager;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'auteur')]
    private Collection $messages_envoyes;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'destinataire')]
    private Collection $messages_recus;

    public function __construct()
    {
        $this->vehicules = new ArrayCollection();
        $this->locations_conducteur = new ArrayCollection();
        $this->reservations_passager = new ArrayCollection();
        $this->messages_envoyes = new ArrayCollection();
        $this->messages_recus = new ArrayCollection();
        $this->dateInscription = new \DateTime(); // Ajoutez cette ligne
        $this->roles = ['ROLE_USER']; // Optionnel : initialiser le rôle par défaut
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getNoteMoyenne(): ?float
    {
        return $this->note_moyenne;
    }

    public function setNoteMoyenne(?float $note_moyenne): static
    {
        $this->note_moyenne = $note_moyenne;

        return $this;
    }

    /**
     * @return Collection<int, Vehicule>
     */
    public function getVehicules(): Collection
    {
        return $this->vehicules;
    }

    public function addVehicule(Vehicule $vehicule): static
    {
        if (!$this->vehicules->contains($vehicule)) {
            $this->vehicules->add($vehicule);
            $vehicule->setProprietaire($this);
        }

        return $this;
    }

    public function removeVehicule(Vehicule $vehicule): static
    {
        if ($this->vehicules->removeElement($vehicule)) {
            // set the owning side to null (unless already changed)
            if ($vehicule->getProprietaire() === $this) {
                $vehicule->setProprietaire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Trajet>
     */
    public function getLocationsConducteur(): Collection
    {
        return $this->locations_conducteur;
    }

    public function addLocationsConducteur(Trajet $locationsConducteur): static
    {
        if (!$this->locations_conducteur->contains($locationsConducteur)) {
            $this->locations_conducteur->add($locationsConducteur);
            $locationsConducteur->setConducteur($this);
        }

        return $this;
    }

    public function removeLocationsConducteur(Trajet $locationsConducteur): static
    {
        if ($this->locations_conducteur->removeElement($locationsConducteur)) {
            // set the owning side to null (unless already changed)
            if ($locationsConducteur->getConducteur() === $this) {
                $locationsConducteur->setConducteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservationsPassager(): Collection
    {
        return $this->reservations_passager;
    }

    public function addReservationsPassager(Reservation $reservationsPassager): static
    {
        if (!$this->reservations_passager->contains($reservationsPassager)) {
            $this->reservations_passager->add($reservationsPassager);
            $reservationsPassager->setPassager($this);
        }

        return $this;
    }

    public function removeReservationsPassager(Reservation $reservationsPassager): static
    {
        if ($this->reservations_passager->removeElement($reservationsPassager)) {
            // set the owning side to null (unless already changed)
            if ($reservationsPassager->getPassager() === $this) {
                $reservationsPassager->setPassager(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getMessagesEnvoyes(): Collection
    {
        return $this->messages_envoyes;
    }

    public function addMessagesEnvoye(Avis $messagesEnvoye): static
    {
        if (!$this->messages_envoyes->contains($messagesEnvoye)) {
            $this->messages_envoyes->add($messagesEnvoye);
            $messagesEnvoye->setAuteur($this);
        }

        return $this;
    }

    public function removeMessagesEnvoye(Avis $messagesEnvoye): static
    {
        if ($this->messages_envoyes->removeElement($messagesEnvoye)) {
            // set the owning side to null (unless already changed)
            if ($messagesEnvoye->getAuteur() === $this) {
                $messagesEnvoye->setAuteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getMessagesRecus(): Collection
    {
        return $this->messages_recus;
    }

    public function addMessagesRecu(Avis $messagesRecu): static
    {
        if (!$this->messages_recus->contains($messagesRecu)) {
            $this->messages_recus->add($messagesRecu);
            $messagesRecu->setDestinataire($this);
        }

        return $this;
    }

    public function removeMessagesRecu(Avis $messagesRecu): static
    {
        if ($this->messages_recus->removeElement($messagesRecu)) {
            // set the owning side to null (unless already changed)
            if ($messagesRecu->getDestinataire() === $this) {
                $messagesRecu->setDestinataire(null);
            }
        }

        return $this;
    }
}
