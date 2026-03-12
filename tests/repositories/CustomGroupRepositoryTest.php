<?php

declare(strict_types=1);

namespace Tests\Models\Repositories;

use PHPUnit\Framework\TestCase;
use modules\models\repositories\CustomGroupRepository;
use PDO;

class CustomGroupRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CustomGroupRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("CREATE TABLE custom_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            color TEXT DEFAULT '#3b82f6',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE custom_group_indicators (
            group_id INTEGER,
            indicator_id TEXT,
            grid_x INTEGER,
            grid_y INTEGER,
            grid_w INTEGER,
            grid_h INTEGER,
            PRIMARY KEY (group_id, indicator_id)
        )");

        $this->pdo->exec("CREATE TABLE parameter_reference (
            parameter_id TEXT PRIMARY KEY,
            display_name TEXT,
            category TEXT
        )");

        $this->pdo->exec("CREATE TABLE user_parameter_order (
            id_user INTEGER,
            parameter_id TEXT,
            grid_x INTEGER,
            grid_y INTEGER,
            grid_w INTEGER,
            grid_h INTEGER,
            PRIMARY KEY (id_user, parameter_id)
        )");

        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('bpm', 'Heart Rate', 'vital')");
        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('spo2', 'SpO2', 'vital')");
        $this->pdo->exec("INSERT INTO parameter_reference VALUES ('temp', 'Temperature', 'vital')");

        $this->repository = new CustomGroupRepository($this->pdo);
    }

    public function testCreateGroupReturnsId(): void
    {
        $id = $this->repository->createGroup(1, 'My Group');
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateGroupWithCustomColor(): void
    {
        $id = $this->repository->createGroup(1, 'Red Group', '#ff0000');
        $group = $this->repository->getGroupById($id, 1);
        $this->assertNotNull($group);
        $this->assertEquals('#ff0000', $group['color']);
    }

    public function testGetGroupsByUser(): void
    {
        $this->repository->createGroup(1, 'Group A');
        $this->repository->createGroup(1, 'Group B');
        $this->repository->createGroup(2, 'Other User Group');

        $groups = $this->repository->getGroupsByUser(1);
        $this->assertCount(2, $groups);
    }

    public function testGetGroupByIdReturnsNullForWrongUser(): void
    {
        $id = $this->repository->createGroup(1, 'My Group');
        $group = $this->repository->getGroupById($id, 99);
        $this->assertNull($group);
    }

    public function testAddIndicatorAndGetIndicatorsByGroup(): void
    {
        $groupId = $this->repository->createGroup(1, 'Test Group');
        $this->repository->addIndicator($groupId, 'bpm');
        $this->repository->addIndicator($groupId, 'spo2');

        $indicators = $this->repository->getIndicatorsByGroup($groupId);
        $this->assertCount(2, $indicators);
        $this->assertContains('bpm', $indicators);
        $this->assertContains('spo2', $indicators);
    }

    public function testDeleteGroup(): void
    {
        $id = $this->repository->createGroup(1, 'To Delete');
        $this->repository->deleteGroup($id, 1);

        $group = $this->repository->getGroupById($id, 1);
        $this->assertNull($group);
    }

    public function testDeleteGroupIgnoresWrongUser(): void
    {
        $id = $this->repository->createGroup(1, 'Protected');
        $this->repository->deleteGroup($id, 99);

        $group = $this->repository->getGroupById($id, 1);
        $this->assertNotNull($group);
    }

    public function testGroupNameExists(): void
    {
        $this->repository->createGroup(1, 'Unique Name');

        $this->assertTrue($this->repository->groupNameExists(1, 'Unique Name'));
        $this->assertFalse($this->repository->groupNameExists(1, 'Other Name'));
        $this->assertFalse($this->repository->groupNameExists(2, 'Unique Name'));
    }

    public function testGetAllParameterReferences(): void
    {
        $params = $this->repository->getAllParameterReferences();
        $this->assertCount(3, $params);
    }

    public function testUpdateGroup(): void
    {
        $groupId = $this->repository->createGroup(1, 'Original');
        $this->repository->addIndicator($groupId, 'bpm');

        $this->repository->updateGroup($groupId, 1, 'Updated', '#00ff00', ['spo2', 'temp']);

        $group = $this->repository->getGroupById($groupId, 1);
        $this->assertEquals('Updated', $group['name']);
        $this->assertEquals('#00ff00', $group['color']);

        $indicators = $this->repository->getIndicatorsByGroup($groupId);
        $this->assertCount(2, $indicators);
        $this->assertContains('spo2', $indicators);
        $this->assertContains('temp', $indicators);
        $this->assertNotContains('bpm', $indicators);
    }

    public function testUpdateGroupIgnoresWrongUser(): void
    {
        $groupId = $this->repository->createGroup(1, 'Original');
        $this->repository->updateGroup($groupId, 99, 'Hacked', '#000', []);

        $group = $this->repository->getGroupById($groupId, 1);
        $this->assertEquals('Original', $group['name']);
    }

    public function testSaveGroupLayout(): void
    {
        $groupId = $this->repository->createGroup(1, 'Layout Test');
        $this->repository->addIndicator($groupId, 'bpm');

        $this->repository->saveGroupLayout($groupId, [
            ['id' => 'bpm', 'x' => 2, 'y' => 1, 'w' => 6, 'h' => 4],
        ]);

        $stmt = $this->pdo->prepare("SELECT grid_x, grid_y, grid_w, grid_h FROM custom_group_indicators WHERE group_id = ? AND indicator_id = ?");
        $stmt->execute([$groupId, 'bpm']);
        $row = $stmt->fetch();

        $this->assertEquals(2, $row['grid_x']);
        $this->assertEquals(1, $row['grid_y']);
        $this->assertEquals(6, $row['grid_w']);
        $this->assertEquals(4, $row['grid_h']);
    }
}
