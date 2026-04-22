<?php

declare(strict_types=1);

namespace Horde\OpenXchange\Test\Unit;

use Horde\OpenXchange\Test\Fixture\OxMockClient;
use Horde_OpenXchange_Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_OpenXchange_Events::class)]
class EventsTest extends TestCase
{
    private OxMockClient $mock;
    private const TOTAL_COLUMNS = 30;

    protected function setUp(): void
    {
        $this->mock = new OxMockClient();
    }

    private function makeEvents(array $params = []): Horde_OpenXchange_Events
    {
        return new Horde_OpenXchange_Events(array_merge(
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

    public function testConstructorAddsEventColumns(): void
    {
        $events = $this->makeEvents();
        $this->assertInstanceOf(Horde_OpenXchange_Events::class, $events);
    }

    public function testListEventsReturnsEmptyArray(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => []]);

        $events = $this->makeEvents();
        $result = $events->listEvents();

        $this->assertSame([], $result);
    }

    public function testListEventsMapsColumns(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 100,          // id
            1 => 5,            // folder_id
            4 => 'Team Meeting', // title
            5 => 1700000000000, // start
            6 => 1700003600000, // end
            7 => 'Weekly sync', // description
            23 => 'organizer@example.com', // organizer
            26 => 'Conference Room A', // location
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(5);

        $this->assertCount(1, $result);
        $event = $result[0];
        $this->assertSame(100, $event['id']);
        $this->assertSame(5, $event['folder_id']);
        $this->assertSame('Team Meeting', $event['title']);
        $this->assertSame(1700000000000, $event['start']);
        $this->assertSame(1700003600000, $event['end']);
        $this->assertSame('Weekly sync', $event['description']);
        $this->assertSame('organizer@example.com', $event['organizer']);
        $this->assertSame('Conference Room A', $event['location']);
    }

    public function testListEventsWithDateRange(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => []]);

        $start = new \Horde_Date('2024-01-01');
        $end = new \Horde_Date('2024-12-31');

        $events = $this->makeEvents();
        $result = $events->listEvents(null, $start, $end);

        $this->assertSame([], $result);
    }

    public function testListEventsMultipleResults(): void
    {
        $this->mock->addLoginResponse();

        $row1 = $this->makeRow([0 => 1, 4 => 'Event One']);
        $row2 = $this->makeRow([0 => 2, 4 => 'Event Two']);

        $this->mock->addJsonResponse(['data' => [$row1, $row2]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(1);

        $this->assertCount(2, $result);
        $this->assertSame('Event One', $result[0]['title']);
        $this->assertSame('Event Two', $result[1]['title']);
    }

    public function testGetEventReturnsData(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse([
            'data' => [
                'id' => 42,
                'title' => 'Lunch',
                'location' => 'Cafeteria',
            ],
        ]);

        $events = $this->makeEvents();
        $result = $events->getEvent(5, 42);

        $this->assertSame(42, $result['id']);
        $this->assertSame('Lunch', $result['title']);
        $this->assertSame('Cafeteria', $result['location']);
    }

    public function testGetEventWithApiError(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addErrorResponse('Object not found');

        // Base::_request assigns array to string-typed $details property
        $this->expectException(\TypeError::class);

        $events = $this->makeEvents();
        $events->getEvent(5, 999);
    }

    public function testListEventsWithFolder(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([0 => 10, 1 => 77]);
        $this->mock->addJsonResponse(['data' => [$row]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(77);

        $this->assertCount(1, $result);
        $this->assertSame(77, $result[0]['folder_id']);
    }

    public function testEventRecurrenceColumns(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 50,
            9 => 2,             // recur_type
            13 => 1,            // recur_interval
            19 => 3,            // recur_id
            21 => ['2024-01-15'], // recur_change_exceptions
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(1);

        $event = $result[0];
        $this->assertSame(2, $event['recur_type']);
        $this->assertSame(3, $event['recur_id']);
        $this->assertSame(['2024-01-15'], $event['recur_change_exceptions']);
        $this->assertSame(1, $event['recur_interval']);
    }

    public function testEventAllDayAndLocation(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 60,
            26 => 'Office',    // location (index 26)
            27 => true,        // allday (index 27)
            28 => 1,           // status (index 28)
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(1);

        $event = $result[0];
        $this->assertSame('Office', $event['location']);
        $this->assertTrue($event['allday']);
        $this->assertSame(1, $event['status']);
    }

    public function testEventTimezoneColumn(): void
    {
        $this->mock->addLoginResponse();

        $row = $this->makeRow([
            0 => 70,
            29 => 'Europe/Berlin', // timezone (index 29)
        ]);

        $this->mock->addJsonResponse(['data' => [$row]]);

        $events = $this->makeEvents();
        $result = $events->listEvents(1);

        $this->assertSame('Europe/Berlin', $result[0]['timezone']);
    }
}
