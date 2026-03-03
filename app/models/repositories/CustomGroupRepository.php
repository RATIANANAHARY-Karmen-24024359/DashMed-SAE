<?php

declare(strict_types=1);

namespace modules\models\repositories;

use modules\models\BaseRepository;
use PDO;

/**
 * Class CustomGroupRepository
 *
 * Manages custom indicator groups created by users.
 *
 * @package DashMed\Modules\Models\Repositories
 * @author DashMed Team
 * @license Proprietary
 */
class CustomGroupRepository extends BaseRepository
{
    /**
     * Constructor
     *
     * @param PDO|null $pdo
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct($pdo);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Creates a custom group for a user.
     *
     * @param int    $userId
     * @param string $name
     * @return int Inserted group ID
     */
    public function createGroup(int $userId, string $name): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO custom_groups (name, user_id) VALUES (:name, :user_id)'
        );
        $st->execute([':name' => $name, ':user_id' => $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Adds an indicator to a group.
     *
     * @param int    $groupId
     * @param string $parameterId
     * @return void
     */
    public function addIndicator(int $groupId, string $parameterId): void
    {
        $st = $this->pdo->prepare(
            'INSERT IGNORE INTO custom_group_indicators (group_id, indicator_id)
             VALUES (:group_id, :indicator_id)'
        );
        $st->execute([':group_id' => $groupId, ':indicator_id' => $parameterId]);
    }

    /**
     * Returns all groups belonging to a user.
     *
     * @param int $userId
     * @return array<int, array{id: int, name: string, created_at: string}>
     */
    public function getGroupsByUser(int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, name, created_at
             FROM custom_groups
             WHERE user_id = :user_id
             ORDER BY created_at ASC'
        );
        $st->execute([':user_id' => $userId]);
        /** @var array<int, array{id: int, name: string, created_at: string}> */
        return $st->fetchAll();
    }

    /**
     * Returns indicator IDs for a given group.
     *
     * @param int $groupId
     * @return array<int, string>
     */
    public function getIndicatorsByGroup(int $groupId): array
    {
        $st = $this->pdo->prepare(
            'SELECT indicator_id
             FROM custom_group_indicators
             WHERE group_id = :group_id'
        );
        $st->execute([':group_id' => $groupId]);
        return array_column($st->fetchAll(), 'indicator_id');
    }

    /**
     * Deletes a group (only if it belongs to the user).
     *
     * @param int $groupId
     * @param int $userId
     * @return void
     */
    public function deleteGroup(int $groupId, int $userId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM custom_groups WHERE id = :id AND user_id = :user_id'
        );
        $st->execute([':id' => $groupId, ':user_id' => $userId]);
    }

    /**
     * Checks if a group name already exists for the user.
     *
     * @param int    $userId
     * @param string $name
     * @return bool
     */
    public function groupNameExists(int $userId, string $name): bool
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM custom_groups WHERE user_id = :user_id AND name = :name'
        );
        $st->execute([':user_id' => $userId, ':name' => $name]);
        return (int) $st->fetchColumn() > 0;
    }

    /**
     * Returns all available parameter references (id + display_name + category).
     *
     * @return array<int, array{parameter_id: string, display_name: string, category: string}>
     */
    public function getAllParameterReferences(): array
    {
        $st = $this->pdo->prepare(
            'SELECT parameter_id, display_name, category
             FROM parameter_reference
             ORDER BY category ASC, display_name ASC'
        );
        $st->execute();
        /** @var array<int, array{parameter_id: string, display_name: string, category: string}> */
        return $st->fetchAll();
    }
}
