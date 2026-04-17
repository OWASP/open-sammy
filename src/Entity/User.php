<?php

/**
 * This is automatically generated file using the Codific Prototizer
 * PHP version 8
 * @category PHP
 * @author   CODIFIC <info@codific.com>
 * @see     http://codific.com
 */

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Abstraction\AbstractEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Ignore;

use Symfony\Component\Security\Core\User\UserInterface;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use App\Entity\Abstraction\PasswordResetInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;


// #BlockStart number=29 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_0
use App\Entity\Abstraction\UserModifiableFieldsInterface;
use JetBrains\PhpStorm\Pure;
use App\Enum\Role;


// #BlockEnd number=29


#[ORM\Table(name: "`user`")]
#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
#[ORM\HasLifecycleCallbacks]
class User extends AbstractEntity implements BackupCodeInterface, PasswordResetInterface, TrustedDeviceInterface, TwoFactorInterface, UserInterface, PasswordAuthenticatedUserInterface
// #BlockStart number=123123 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_1
    // additional implements go here
    , UserModifiableFieldsInterface
// #BlockEnd number=123123
{

    #[ORM\Column(name: "`email`", type: Types::STRING, nullable: true)]
    protected ?string $email = "";

    #[ORM\Column(name: "`name`", type: Types::STRING, nullable: true)]
    protected ?string $name = "";

    #[ORM\Column(name: "`surname`", type: Types::STRING, nullable: true)]
    protected ?string $surname = "";

    #[ORM\Column(name: "`roles`", type: Types::JSON)]
    protected array $roles = [];

    #[ORM\Column(name: "`last_login`", type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTime $lastLogin = null;

    #[ORM\Column(name: "`external_id`", type: Types::STRING, nullable: true)]
    protected ?string $externalId = "";

    #[ORM\Column(name: "`agreed_to_terms`", type: Types::BOOLEAN)]
    protected bool $agreedToTerms = false;

    #[ORM\Column(name: "`last_changelog`", type: Types::STRING, nullable: true)]
    protected ?string $lastChangelog = "";

    #[ORM\Column(name: "`time_zone`", type: Types::STRING, nullable: true)]
    protected ?string $timeZone = "";

    #[ORM\Column(name: "`date_format`", type: Types::STRING, nullable: true)]
    protected ?string $dateFormat = "";

    #[ORM\Column(name: "`password`", type: Types::STRING, nullable: true)]
    protected ?string $password = "";

    #[ORM\Column(name: "`salt`", type: Types::STRING, nullable: true)]
    protected ?string $salt = "";

    #[Ignore]
    #[ORM\Column(name: "`secret_key`", type: Types::STRING, nullable: true)]
    protected ?string $secretKey = "";

    /**
     * Increase version to invalidate all trusted token of the user.
     */
    #[Ignore]
    #[ORM\Column(name: "`trusted_version`", type: Types::INTEGER)]
    protected int $trustedVersion = 0;

    #[Ignore]
    #[ORM\Column(name: "`backup_codes`", type: Types::JSON)]
    protected array $backupCodes = [];

    #[Ignore]
    #[ORM\Column(name: "`password_reset_hash`", type: Types::STRING, nullable: true)]
    protected ?string $passwordResetHash = "";

    #[Ignore]
    #[ORM\Column(name: "`password_reset_hash_expiration`", type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTime $passwordResetHashExpiration = null;

    #[Ignore]
    protected ?string $plaintextPasswordResetHash = null;

    #[ORM\OneToMany(mappedBy: "assignedTo", targetEntity: Stage::class, cascade: ["persist"], fetch: "LAZY", orphanRemoval: false)]
    #[ORM\OrderBy(["id" => "ASC"])]
    #[MaxDepth(1)]
    protected Collection $assignedToStages;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: Assignment::class, cascade: ["persist"], fetch: "LAZY", orphanRemoval: false)]
    #[ORM\OrderBy(["id" => "ASC"])]
    #[MaxDepth(1)]
    protected Collection $userAssignments;

    #[Ignore]
    #[ORM\OneToMany(mappedBy: "user", targetEntity: GroupUser::class, cascade: ["persist"], orphanRemoval: false)]
    protected Collection $userGroupUsers;

    public function __construct()
    {
        $this->assignedToStages = new ArrayCollection();
        $this->userAssignments = new ArrayCollection();
        $this->userGroupUsers = new ArrayCollection();
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setSurname(?string $surname): self
    {
        $this->surname = $surname;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }


    public function setLastLogin(?\DateTime $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setAgreedToTerms(bool $agreedToTerms): self
    {
        $this->agreedToTerms = $agreedToTerms;

        return $this;
    }

    public function getAgreedToTerms(): bool
    {
        return $this->agreedToTerms;
    }

    public function setLastChangelog(?string $lastChangelog): self
    {
        $this->lastChangelog = $lastChangelog;

        return $this;
    }

    public function getLastChangelog(): ?string
    {
        return $this->lastChangelog;
    }


    public function setTimeZone(?string $timeZone): self
    {
        $this->timeZone = $timeZone;

        return $this;
    }

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }

    public function setDateFormat(?string $dateFormat): self
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    /**
     * Get AssignedTo Stages
     * @return Collection<Stage>
     */
    public function getAssignedToStages(): Collection
    {
        return $this->assignedToStages;
    }

    /**
     * Add Stages Stage
     */
    public function addAssignedToStage(Stage $stage): User
    {
        $this->assignedToStages->add($stage);

        return $this;
    }

    /**
     * Get User Assignments
     * @return Collection<Assignment>
     */
    public function getUserAssignments(): Collection
    {
        return $this->userAssignments;
    }

    /**
     * Add Assignments Assignment
     */
    public function addUserAssignment(Assignment $assignment): User
    {
        $this->userAssignments->add($assignment);

        return $this;
    }

    /**
     * Get GroupUsers that are accessible via the many-to-many relationship
     * @return Collection<GroupUser>
     */
    public function getGroupUsers(): Collection
    {
        return $this->userGroupUsers;
    }

    public function addUserGroupUser(GroupUser $groupUser): User
    {
        $this->userGroupUsers->add($groupUser);

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->email;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * Erase credentials
     * @inheritDoc
     * @return void
     */
    public function eraseCredentials(): void
    {
    }

    public function setSecretKey(?string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    /**
     * Return true if the user should do two-factor authentication.
     */
    public function isGoogleAuthenticatorEnabled(): bool
    {
        return $this->getSecretKey() !== null && strlen($this->getSecretKey()) > 0;
    }

    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->email;
    }

    /**
     * Return the Google Authenticator secret
     * When an empty string is returned, the Google authentication is disabled.
     */
    public function getGoogleAuthenticatorSecret(): ?string
    {
        return $this->getSecretKey();
    }

    public function setTrustedTokenVersion(int $trustedVersion): self
    {
        $this->trustedVersion = $trustedVersion;

        return $this;
    }

    /**
     * Return version for the trusted token. Increase version to invalidate all trusted token of the user.
     */
    public function getTrustedTokenVersion(): int
    {
        return $this->trustedVersion;
    }

    /**
     * Check if it is a valid backup code.
     */
    #[Ignore]
    public function isBackupCode(string $code): bool
    {
        return in_array($code, $this->backupCodes, true);
    }

    public function setBackupCodes(array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    #[Ignore]
    public function getBackupCodes(): array
    {
        return $this->backupCodes;
    }

    public function setPasswordResetHash(?string $passwordResetHash): self
    {
        $this->passwordResetHash = $passwordResetHash;

        return $this;
    }

    #[Ignore]
    public function getPasswordResetHash(): ?string
    {
        return $this->passwordResetHash;
    }

    public function setPasswordResetHashExpiration(?\DateTime $passwordResetHashExpiration): self
    {
        $this->passwordResetHashExpiration = $passwordResetHashExpiration;

        return $this;
    }

    #[Ignore]
    public function getPasswordResetHashExpiration(): ?\DateTime
    {
        return $this->passwordResetHashExpiration;
    }

    public function setPlaintextPasswordResetHash(?string $plaintext): self
    {
        $this->plaintextPasswordResetHash = $plaintext;

        return $this;
    }

    #[Ignore]
    public function getPlaintextPasswordResetHash(): ?string
    {
        return $this->plaintextPasswordResetHash;
    }

    /**
     * This method is a copy constructor that will return a copy object (except for the id field)
     * Note that this method will not save the object
     */
    #[Ignore]
    public function getCopy(?User $clone = null): User
    {
        if ($clone === null) {
            $clone = new User();
        }
        $clone->setEmail($this->email);
        $clone->setName($this->name);
        $clone->setSurname($this->surname);
        $clone->setRoles($this->roles);
        $clone->setLastLogin($this->lastLogin);
        $clone->setExternalId($this->externalId);
        $clone->setAgreedToTerms($this->agreedToTerms);
        $clone->setLastChangelog($this->lastChangelog);
        $clone->setTimeZone($this->timeZone);
        $clone->setDateFormat($this->dateFormat);
// #BlockStart number=30 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_2

// #BlockEnd number=30

        return $clone;
    }

    /**
     * Private to string method auto generated based on the UML properties
     * This is the new way of doing things.
     */
    public function toString(): string
    {
        return "{$this->name} {$this->surname}";
    }

    /**
     * https://symfony.com/doc/current/validation.html
     * we use php version for validation!!!
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Assert\NotBlank());
        $metadata->addConstraint(new UniqueEntity(['fields' => ['email', 'deletedAt'], 'ignoreNull' => false]));
        $metadata->addPropertyConstraint('email', new Assert\Email(['mode' => 'strict']));

// #BlockStart number=31 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_3
//        to remove constraint use following code
//        unset($metadata->properties['PROPERTY']);
//        unset($metadata->members['PROPERTY']);

// #BlockEnd number=31
    }

    #[Ignore]
    public function getGeneratedFilterFields(): array
    {
        return [
            "_user.id",
            "_user.email",
            "_user.name",
            "_user.surname",
        ];
    }

    #[Ignore]
    public function getUploadFields(): array
    {
        return [

        ];
    }

    #[Ignore]
    public function getModifiableFields(): array
    {
        return [
            "email",
            "name",
            "surname",
        ];
    }

    #[Ignore]
    public function getReadOnlyFields(): array
    {
        return [
            "password",
            "salt",
            "secretKey",
            "trustedVersion",
            "backupCodes",
            "passwordResetHash",
            "passwordResetHashExpiration",
            "roles",
            "lastLogin",
            "externalId",
            "agreedToTerms",
            "lastChangelog",
            "timeZone",
            "dateFormat",
        ];
    }

    #[Ignore]
    public function getParentClasses(): array
    {
        return [

        ];
    }

    #[Ignore]
    public static array $manyToManyProperties = [
        "groupUsers" => "GroupUser",
    ];


    #[Ignore]
    public static array $childProperties = [
        "assignedToStages" => "assignedTo",
        "userAssignments" => "user",
    ];

// #BlockStart number=32 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_4

    /**
     * Return a list of all constants as strings.
     */
    #[Pure]
    public static function getAllRoles(): array
    {
        return [
            Role::ADMINISTRATOR->string(),
            Role::MANAGER->string(),
            Role::VALIDATOR->string(),
            Role::IMPROVER->string(),
            Role::EVALUATOR->string(),
            Role::AUDITOR->string(),
            Role::USER->string(),
        ];
    }

    /**
     * The toString method based on the private __toString autogenerated method
     * If necessary override.
     */
    public function __toString(): string
    {
        $result = $this->toString().' '.$this->email;

        return $result;
    }

    public function getShortName(): string
    {
        $result = $this->getName();
        if ($this->getSurname() !== null) {
            $result .= ' '.substr($this->getSurname(), 0, 1).'.';
        }
        $result .= $this->getDeletedSuffix();

        return $result;
    }

    public function getLongName(): string
    {
        $result = $this->getName().' '.$this->getSurname();
        $result .= $this->getDeletedSuffix();

        return $result;
    }

    public function getDeletedSuffix(): string
    {
        return ($this->getDeletedAt() !== null) ? ' [Deleted]' : '';
    }

    /**
     * Get user identifier.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return string[]
     */
    #[Pure]
    public static function getAllNonAdminRoles(): array
    {
        $roles = self::getAllRoles();

        return array_diff($roles, [Role::ADMINISTRATOR->string()]);
    }

    /**
     * @return string[]
     */
    #[Pure]
    public static function getAllAssignableRoles(): array
    {
        $roles = self::getAllRoles();

        return array_diff($roles, [Role::ADMINISTRATOR->string(), Role::USER->string()]);
    }

    /**
     * Invalidate a backup code.
     */
    public function invalidateBackupCode(string $code): void
    {
        $key = array_search($code, $this->backupCodes);
        if ($key !== false) {
            unset($this->backupCodes[$key]);
        }
        $this->setSecretKey('');
    }

    public function isManager(): bool
    {
        return in_array(Role::MANAGER->string(), $this->getRoles(), true);
    }

    public function isEvaluator(): bool
    {
        return in_array(Role::EVALUATOR->string(), $this->getRoles(), true);
    }

    public function isImprover(): bool
    {
        return in_array(Role::IMPROVER->string(), $this->getRoles(), true);
    }

    public function isAdmin(): bool
    {
        return in_array(Role::ADMINISTRATOR->string(), $this->getRoles(), true);
    }

    public function isValidator(): bool
    {
        return in_array(Role::VALIDATOR->string(), $this->getRoles(), true);
    }

    public function isAuditor(): bool
    {
        return in_array(Role::AUDITOR->string(), $this->getRoles(), true);
    }

    public function getUserModifiableFields(): array
    {
        return [
            'email',
            'name',
            'surname',
        ];
    }
// #BlockEnd number=32

}
