<?php

declare(strict_types=1);

namespace modules\models\entities;

use modules\models\interfaces\EntityInterface;

/**
 * Class User
 *
 * Represents a user (Doctor, Staff, Admin).
 *
 * @package DashMed\Modules\Models\Entities
 * @author DashMed Team
 * @license Proprietary
 */
class User implements EntityInterface
{
    private int $id;
    private string $firstName;
    private string $lastName;
    private string $email;
    private ?string $password;
    private int $adminStatus;
    private ?int $idProfession;
    private ?string $professionLabel;
    private float $alertVolume;
    private int $alertDuration;
    private bool $alertDnd;

    public function __construct(
        int $id,
        string $firstName,
        string $lastName,
        string $email,
        int $adminStatus = 0,
        ?string $password = null,
        ?int $idProfession = null,
        ?string $professionLabel = null,
        float $alertVolume = 0.50,
        int $alertDuration = 20000,
        bool $alertDnd = false
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->adminStatus = $adminStatus;
        $this->password = $password;
        $this->idProfession = $idProfession;
        $this->professionLabel = $professionLabel;
        $this->alertVolume = $alertVolume;
        $this->alertDuration = $alertDuration;
        $this->alertDnd = $alertDnd;
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    public function getLastName(): string
    {
        return $this->lastName;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getAdminStatus(): int
    {
        return $this->adminStatus;
    }
    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function getIdProfession(): ?int
    {
        return $this->idProfession;
    }
    public function getProfessionLabel(): ?string
    {
        return $this->professionLabel;
    }
    public function getAlertVolume(): float
    {
        return $this->alertVolume;
    }
    public function getAlertDuration(): int
    {
        return $this->alertDuration;
    }
    public function getAlertDnd(): bool
    {
        return $this->alertDnd;
    }

    public function isAdmin(): bool
    {
        return $this->adminStatus === 1;
    }
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{
     *   id_user: int,
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   admin_status: int,
     *   id_profession: int|null,
     *   profession_label: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id_user' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'admin_status' => $this->adminStatus,
            'id_profession' => $this->idProfession,
            'profession_label' => $this->professionLabel,
            'alert_volume' => $this->alertVolume,
            'alert_duration' => $this->alertDuration,
            'alert_dnd' => $this->alertDnd,
        ];
    }
}
