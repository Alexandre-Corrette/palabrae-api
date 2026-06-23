<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte applicatif (auth JWT).
 *
 * SÉCU / RGPD : on ne stocke JAMAIS d'identité nominative ici à des fins de
 * traçage d'erreur. Le lien vers le monde « coaching » se fait via operatorRef
 * PSEUDONYMISÉ (un matricule), jamais le nom. Voir CoachingDataVoter et
 * Deviation::getOperatorRefForCoaching().
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /** Hash du mot de passe (jamais le mot de passe en clair). */
    #[ORM\Column]
    private string $password;

    /** Référence opérateur pseudonymisée (matricule). Pas le nom. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operatorRef = null;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): string { return $this->email; }

    /**
     * Identifiant unique de sécurité. On utilise l'email.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string { return $this->password; }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function getOperatorRef(): ?string { return $this->operatorRef; }

    public function setOperatorRef(?string $operatorRef): static
    {
        $this->operatorRef = $operatorRef;

        return $this;
    }

    /**
     * Rien de sensible (mot de passe en clair, etc.) ne doit être conservé ici.
     */
    public function eraseCredentials(): void
    {
    }
}
