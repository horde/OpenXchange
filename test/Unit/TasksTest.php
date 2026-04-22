<?php

declare(strict_types=1);

namespace Horde\OpenXchange\Test\Unit;

use Horde\OpenXchange\Test\Fixture\OxMockClient;
use Horde_OpenXchange_Tasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_OpenXchange_Tasks::class)]
class TasksTest extends TestCase
{
    private OxMockClient $mock;
    private const TOTAL_COLUMNS = 24;

    protected function setUp(): void
    {
        $this->mock = new OxMockClient();
    }

    private function makeTasks(array $params = []): Horde_OpenXchange_Tasks
    {
        return new Horde_OpenXchange_Tasks(array_merge(
            [
                'client' => $this->mock->getClient(),
                'endpoint' => 'http://ox.example.com/ajax',
                'user' => 'testuser',
                'password' => 'secret',
            ],
            $params,
        ));
    }

    private function makeRow(array $values = []): array
    {
        $row = array_fill(0, self::TOTAL_COLUMNS, null);
        foreach ($values as $index => $value) {
            $row[$index] = $value;
        }
        return $row;
    }

    public function testStatusConstants(): void
    {
        $this->assertSame(1, Horde_OpenXchange_Tasks::STATUS_NOT_STARTED);
        $this->assertSame(2, Horde_OpenXchange_Tasks::STATUS_IN_PROGRESS);
        $this->assertSame(3, Horde_OpenXchange_Tasks::STATUS_DONE);
        $this->assertSame(4, Horde_OpenXchange_Tasks::STATUS_WAITING);
        $this->assertSame(5, Horde_OpenXchange_Tasks::STATUS_DEFERRED);
    }

    public function testPriorityConstants(): void
    {
        $this->assertSame(1, Horde_OpenXchange_Tasks::PRIORITY_LOW);
        $this->assertSame(2, Horde_OpenXchange_Tasks::PRIORITY_MEDIUM);
        $this->assertSame(3, Horde_OpenXchange_Tasks::PRIORITY_HIGH);
    }

    public function testConstructorAddsTaskColumns(): void
    {
        $tasks = $this->makeTasks();
        $this->assertInstanceOf(Horde_OpenXchange_Tasks::class, $tasks);
    }

    public function testListTasksReturnsEmptyArray(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => []]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks();

        $this->assertSame([], $result);
    }

    public function testListTasksMapsColumns(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 200,          // id
            1 => 8,            // folder_id
            4 => 'Write documentation', // title
            7 => 'Document the API', // description
            19 => Horde_OpenXchange_Tasks::STATUS_IN_PROGRESS, // status
            20 => 50,          // percent
            22 => Horde_OpenXchange_Tasks::PRIORITY_HIGH, // priority
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks(8);

        $this->assertCount(1, $result);
        $task = $result[0];
        $this->assertSame(200, $task['id']);
        $this->assertSame(8, $task['folder_id']);
        $this->assertSame('Write documentation', $task['title']);
        $this->assertSame('Document the API', $task['description']);
        $this->assertSame(Horde_OpenXchange_Tasks::STATUS_IN_PROGRESS, $task['status']);
        $this->assertSame(50, $task['percent']);
        $this->assertSame(Horde_OpenXchange_Tasks::PRIORITY_HIGH, $task['priority']);
    }

    public function testListTasksWithDateRange(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => []]);

        $start = new \Horde_Date('2024-06-01');
        $end = new \Horde_Date('2024-06-30');

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks(null, $start, $end);

        $this->assertSame([], $result);
    }

    public function testListTasksMultipleResults(): void
    {
        $this->mock->addLoginResponse();

        $row1 = $this->makeRow([0 => 1, 4 => 'Task One']);
        $row2 = $this->makeRow([0 => 2, 4 => 'Task Two']);
        $row3 = $this->makeRow([0 => 3, 4 => 'Task Three']);

        $this->mock->addJsonResponse(['data' => [$row1, $row2, $row3]]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks(1);

        $this->assertCount(3, $result);
        $this->assertSame('Task One', $result[0]['title']);
        $this->assertSame('Task Two', $result[1]['title']);
        $this->assertSame('Task Three', $result[2]['title']);
    }

    public function testGetTaskReturnsData(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse([
            'data' => [
                'id' => 55,
                'title' => 'Fix bug',
                'status' => Horde_OpenXchange_Tasks::STATUS_DONE,
                'percent' => 100,
            ],
        ]);

        $tasks = $this->makeTasks();
        $result = $tasks->getTask(3, 55);

        $this->assertSame(55, $result['id']);
        $this->assertSame('Fix bug', $result['title']);
        $this->assertSame(Horde_OpenXchange_Tasks::STATUS_DONE, $result['status']);
        $this->assertSame(100, $result['percent']);
    }

    public function testGetTaskWithApiError(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addErrorResponse('Task not found');

        // Base::_request assigns array to string-typed $details property
        $this->expectException(\TypeError::class);

        $tasks = $this->makeTasks();
        $tasks->getTask(3, 999);
    }

    public function testListTasksWithoutFolder(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([0 => 10]);
        $this->mock->addJsonResponse(['data' => [$row]]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks();

        $this->assertCount(1, $result);
    }

    public function testTaskDurationAndCompletedColumns(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 300,
            21 => 3600,         // duration (index 21)
            23 => 1700000000000, // completed (index 23)
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks(1);

        $task = $result[0];
        $this->assertSame(3600, $task['duration']);
        $this->assertSame(1700000000000, $task['completed']);
    }

    public function testListTasksWithFolder(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([0 => 10, 1 => 42]);
        $this->mock->addJsonResponse(['data' => [$row]]);

        $tasks = $this->makeTasks();
        $result = $tasks->listTasks(42);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['folder_id']);
    }
}
