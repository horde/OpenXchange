<?php

declare(strict_types=1);

namespace Horde\OpenXchange\Test\Unit;

use Horde\OpenXchange\Test\Fixture\OxMockClient;
use Horde_OpenXchange_Contacts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_OpenXchange_Exception;
use Horde_Perms;
use LogicException;

#[CoversClass(Horde_OpenXchange_Contacts::class)]
class ContactsTest extends TestCase
{
    private OxMockClient $mock;

    protected function setUp(): void
    {
        $this->mock = new OxMockClient();
    }

    private function makeContacts(array $params = []): Horde_OpenXchange_Contacts
    {
        return new Horde_OpenXchange_Contacts(array_merge(
            [
                'client' => $this->mock->getClient(),
                'endpoint' => 'http://ox.example.com/ajax',
                'user' => 'testuser',
                'password' => 'secret',
            ],
            $params,
        ));
    }

    public function testConstructorDefaults(): void
    {
        $contacts = new Horde_OpenXchange_Contacts();
        $this->assertInstanceOf(Horde_OpenXchange_Contacts::class, $contacts);
    }

    public function testConstructorWithCustomEndpoint(): void
    {
        $contacts = $this->makeContacts(['endpoint' => 'http://custom.example.com/api']);
        $this->assertInstanceOf(Horde_OpenXchange_Contacts::class, $contacts);
    }

    public function testLoginSetsSession(): void
    {
        $this->mock->addLoginResponse('my-session');
        $this->mock->addJsonResponse(['data' => []]);

        $contacts = new Horde_OpenXchange_Contacts([
            'client' => $this->mock->getClient(),
        ]);
        $contacts->login('alice', 'password123');

        $result = $contacts->listContacts(42);
        $this->assertIsArray($result);
    }

    public function testLoginThrowsWithoutCredentials(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('User name or password missing');

        $contacts = new Horde_OpenXchange_Contacts([
            'client' => $this->mock->getClient(),
        ]);
        $contacts->listContacts();
    }

    public function testLogoutWithoutSessionIsNoop(): void
    {
        $contacts = $this->makeContacts();
        $contacts->logout();
        $this->assertTrue(true);
    }

    public function testLogoutClearsSession(): void
    {
        $this->mock->addLoginResponse('session-to-clear');
        $this->mock->addJsonResponse([]);
        $this->mock->addLoginResponse('new-session');
        $this->mock->addJsonResponse(['data' => []]);

        $contacts = $this->makeContacts();
        $contacts->login('user', 'pass');
        $contacts->logout();

        $result = $contacts->listContacts(1);
        $this->assertIsArray($result);
    }

    public function testGetUserReturnsData(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse([
            'data' => ['login_info' => 'alice', 'display_name' => 'Alice'],
        ]);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');
        $user = $contacts->getUser(5);

        $this->assertSame('alice', $user['login_info']);
        $this->assertSame('Alice', $user['display_name']);
    }

    public function testGetGroupReturnsFullResponse(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse([
            'name' => 'admins',
            'id' => 3,
        ]);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');
        $group = $contacts->getGroup(3);

        $this->assertSame('admins', $group['name']);
    }

    public function testGetConfigReturnsTrue(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => 'true']);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');

        $this->assertTrue($contacts->getConfig('some/setting'));
    }

    public function testGetConfigReturnsFalse(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => 'false']);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');

        $this->assertFalse($contacts->getConfig('some/setting'));
    }

    public function testGetConfigReturnsNull(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => 'null']);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');

        $this->assertNull($contacts->getConfig('some/setting'));
    }

    public function testGetConfigReturnsArbitraryValue(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => 'Europe/Berlin']);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');

        $this->assertSame('Europe/Berlin', $contacts->getConfig('timezone'));
    }

    public function testListContactsMapsColumns(): void
    {
        $this->mock->addLoginResponse();

        $numColumns = 105;
        $row = array_fill(0, $numColumns, null);
        $row[0] = 42;
        $row[3] = 'uid-123';
        $row[4] = 'Display Name';
        $row[5] = 'John';
        $row[6] = 'Doe';
        $row[59] = 'john@example.com';

        $this->mock->addJsonResponse(['data' => [$row]]);

        $contacts = $this->makeContacts();
        $result = $contacts->listContacts(10);

        $this->assertCount(1, $result);
        $contact = $result[0];
        $this->assertSame(42, $contact['id']);
        $this->assertSame('uid-123', $contact['uid']);
        $this->assertSame('Display Name', $contact['name']);
        $this->assertSame('John', $contact['firstname']);
        $this->assertSame('Doe', $contact['lastname']);
        $this->assertSame('john@example.com', $contact['email']);
    }

    public function testListContactsWithoutFolder(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addJsonResponse(['data' => []]);

        $contacts = $this->makeContacts();
        $result = $contacts->listContacts();

        $this->assertSame([], $result);
    }

    public function testListContactsMultipleRows(): void
    {
        $this->mock->addLoginResponse();

        $numColumns = 105;
        $row1 = array_fill(0, $numColumns, null);
        $row1[0] = 1;
        $row2 = array_fill(0, $numColumns, null);
        $row2[0] = 2;
        $row3 = array_fill(0, $numColumns, null);
        $row3[0] = 3;

        $this->mock->addJsonResponse(['data' => [$row1, $row2, $row3]]);

        $contacts = $this->makeContacts();
        $result = $contacts->listContacts(5);

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertSame(3, $result[2]['id']);
    }

    public function testApiErrorThrowsException(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addErrorResponse('Permission denied');

        $this->expectException(Horde_OpenXchange_Exception::class);
        $this->expectExceptionMessage('Permission denied');

        $contacts = $this->makeContacts();
        $contacts->listContacts(10);
    }

    public function testNonJsonResponseThrowsException(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addRawResponse('Not JSON at all', 500);

        $this->expectException(Horde_OpenXchange_Exception::class);

        $contacts = $this->makeContacts();
        $contacts->listContacts(10);
    }

    public function testSessionReusedAcrossCalls(): void
    {
        $this->mock->addLoginResponse('reused-session');
        $this->mock->addJsonResponse(['data' => []]);
        $this->mock->addJsonResponse(['data' => []]);

        $contacts = $this->makeContacts();
        $first = $contacts->listContacts(1);
        $second = $contacts->listContacts(2);

        $this->assertIsArray($first);
        $this->assertIsArray($second);
    }

    public function testListResourcesWithPermissions(): void
    {
        $this->mock->addLoginResponse();

        $this->mock->addJsonResponse([
            'data' => [
                'private' => [
                    [
                        10,
                        'My Contacts',
                        [
                            [
                                'entity' => 1,
                                'bits' => 0b0000010_0000010_0000010_0000001,
                                'group' => false,
                            ],
                        ],
                        true,
                    ],
                ],
            ],
        ]);

        $this->mock->addJsonResponse([
            'data' => ['login_info' => 'alice'],
        ]);

        $contacts = $this->makeContacts();
        $resources = $contacts->listResources();

        $this->assertArrayHasKey(10, $resources);
        $this->assertSame('My Contacts', $resources[10]['label']);
        $this->assertTrue($resources[10]['default']);
        $this->assertArrayHasKey('user', $resources[10]['hordePermission']);
        $this->assertArrayHasKey('alice', $resources[10]['hordePermission']['user']);
    }

    public function testListResourcesPublicType(): void
    {
        $this->mock->addLoginResponse();

        $this->mock->addJsonResponse([
            'data' => [
                'public' => [
                    [
                        20,
                        'Shared Contacts',
                        [],
                        false,
                    ],
                ],
            ],
        ]);

        $contacts = $this->makeContacts();
        $resources = $contacts->listResources(Horde_OpenXchange_Contacts::RESOURCE_PUBLIC);

        $this->assertArrayHasKey(20, $resources);
        $this->assertSame('Shared Contacts', $resources[20]['label']);
        $this->assertFalse($resources[20]['default']);
    }

    public function testListResourcesWithGroupPermission(): void
    {
        $this->mock->addLoginResponse();

        $this->mock->addJsonResponse([
            'data' => [
                'private' => [
                    [
                        30,
                        'Team Contacts',
                        [
                            [
                                'entity' => 5,
                                'bits' => 0b0000010_0000010_0000010_0000001,
                                'group' => true,
                            ],
                        ],
                        false,
                    ],
                ],
            ],
        ]);

        $this->mock->addJsonResponse([
            'name' => 'editors',
            'id' => 5,
        ]);

        $contacts = $this->makeContacts();
        $resources = $contacts->listResources();

        $this->assertArrayHasKey('group', $resources[30]['hordePermission']);
        $this->assertArrayHasKey('editors', $resources[30]['hordePermission']['group']);
    }

    public function testPermissionBitsShowOnly(): void
    {
        $this->mock->addLoginResponse();

        $this->mock->addJsonResponse([
            'data' => [
                'private' => [
                    [
                        40,
                        'Minimal',
                        [
                            [
                                'entity' => 1,
                                'bits' => 1,
                                'group' => false,
                            ],
                        ],
                        false,
                    ],
                ],
            ],
        ]);

        $this->mock->addJsonResponse([
            'data' => ['login_info' => 'bob'],
        ]);

        $contacts = $this->makeContacts();
        $resources = $contacts->listResources();

        $perm = $resources[40]['hordePermission']['user']['bob'];
        $this->assertSame(Horde_Perms::SHOW, $perm & Horde_Perms::SHOW);
        $this->assertSame(0, $perm & Horde_Perms::READ);
        $this->assertSame(0, $perm & Horde_Perms::EDIT);
        $this->assertSame(0, $perm & Horde_Perms::DELETE);
    }

    public function testPermissionBitsFullAccess(): void
    {
        $bits = (2 << 0) | (2 << 7) | (2 << 14) | (2 << 21);
        $this->mock->addLoginResponse();

        $this->mock->addJsonResponse([
            'data' => [
                'private' => [
                    [
                        50,
                        'Full Access',
                        [
                            [
                                'entity' => 1,
                                'bits' => $bits,
                                'group' => false,
                            ],
                        ],
                        false,
                    ],
                ],
            ],
        ]);

        $this->mock->addJsonResponse([
            'data' => ['login_info' => 'admin'],
        ]);

        $contacts = $this->makeContacts();
        $resources = $contacts->listResources();

        $perm = $resources[50]['hordePermission']['user']['admin'];
        $this->assertSame(Horde_Perms::SHOW, $perm & Horde_Perms::SHOW);
        $this->assertSame(Horde_Perms::READ, $perm & Horde_Perms::READ);
        $this->assertSame(Horde_Perms::EDIT, $perm & Horde_Perms::EDIT);
        $this->assertSame(Horde_Perms::DELETE, $perm & Horde_Perms::DELETE);
    }

    public function testCookieFromLoginIsStored(): void
    {
        $this->mock->addJsonResponse(
            ['session' => 'sess-cookie-test'],
            200,
            ["Set-Cookie: JSESSIONID=abc123; Path=/; Expires=Thu, 31 Dec 2037 23:59:59 GMT"],
        );

        $numColumns = 105;
        $row = array_fill(0, $numColumns, null);
        $row[0] = 1;
        $this->mock->addJsonResponse(['data' => [$row]]);

        $contacts = $this->makeContacts();
        $result = $contacts->listContacts(1);

        $this->assertCount(1, $result);
    }

    public function testExpiredCookieIsDropped(): void
    {
        $this->mock->addJsonResponse(
            ['session' => 'sess-expired'],
            200,
            ["Set-Cookie: OLD=expired; Expires=Thu, 01 Jan 2000 00:00:00 GMT"],
        );
        $this->mock->addJsonResponse(['data' => []]);

        $contacts = $this->makeContacts();
        $result = $contacts->listContacts(1);

        $this->assertSame([], $result);
    }

    public function testLogoutAcceptsEmpty200Response(): void
    {
        $this->mock->addLoginResponse();
        $this->mock->addRawResponse('', 200);

        $contacts = $this->makeContacts();
        $contacts->login('testuser', 'secret');
        $contacts->logout();

        $this->assertTrue(true);
    }

    public function testResourceConstants(): void
    {
        $this->assertSame('private', Horde_OpenXchange_Contacts::RESOURCE_PRIVATE);
        $this->assertSame('public', Horde_OpenXchange_Contacts::RESOURCE_PUBLIC);
    }
}
