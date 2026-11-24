<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_skillupsintegration;

use local_skillupsintegration\connector\factory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verify protocol adapters with injectable synthetic providers and exercise queue slices.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(factory::class)]
#[CoversClass(mapping::class)]
#[CoversClass(http_transport::class)]
#[CoversClass(worker::class)]
final class connectors_test extends \advanced_testcase {
    /** @var string Test environment key name. */
    private const REF = 'SKILLUPS_HUB_CONNECTOR_QA';

    /**
     * Provide a transport that records credential routing and returns controlled answers.
     *
     * @param array $answers Transport fixtures
     * @return transport
     */
    private function transport(array $answers): transport {
        return new class ($answers) implements transport {
            /** @var array Request trace containing synthetic test data only. */
            public array $calls = [];

            /**
             * Keep a set of deterministic responses.
             *
             * @param array $answers Fixture queue
             */
            public function __construct(
                /** @var array Remaining deterministic fixture results. */
                private array $answers,
            ) {
            }

            /**
             * Capture a synthetic request.
             *
             * @param string $url URL
             * @param array|null $form OAuth form
             * @param string $token Test token
             * @return array
             */
            public function json(string $url, ?array $form = null, string $token = ''): array {
                $this->calls[] = [$url, $form, $token];
                $out = array_shift($this->answers);
                if ($out instanceof problem) {
                    throw $out;
                }
                return $out;
            }
        };
    }

    /**
     * Google exchanges a delegated refresh grant and maps the native Directory structure.
     */
    public function test_google_directory_and_classroom_contracts(): void {
        $this->resetAfterTest();
        $http = $this->transport([
            ['access_token' => 'directory-synthetic'],
            ['users' => [
                ['id' => 'a', 'primaryEmail' => 'a@example.invalid', 'name' => ['givenName' => 'Ana', 'familyName' => 'Doe']],
                ['id' => 'b', 'suspended' => true],
            ], 'nextPageToken' => 'second'],
            ['access_token' => 'classroom-synthetic'],
            ['courses' => [['id' => '1', 'name' => 'Classroom Synthetic']]],
        ]);
        putenv(self::REF . '=' . json_encode(['clientid' => 'id', 'clientsecret' => 'secret', 'refresh' => 'refresh']));
        try {
            $options = '{"endpoint":"my_customer","mapping":[]}';
            $source = (object)['type' => 'google', 'secretref' => self::REF, 'options' => $options];
            $driver = factory::make($source, $http);
            [$members, $next] = $driver->page('user', 'incoming+cursor');
            $this->assertCount(1, $members);
            $this->assertSame('second', $next);
            [$key, $data] = mapping::normalize('user', $members[0], $driver->defaults('user'), [], 7);
            $this->assertSame(hash('sha256', 'a'), $key);
            $this->assertSame('Ana', $data['firstname']);
            $this->assertSame('refresh_token', $http->calls[0][1]['grant_type']);
            $this->assertStringContainsString('pageToken=incoming%2Bcursor', $http->calls[1][0]);
            $this->assertSame('directory-synthetic', $http->calls[1][2]);
            [$courses] = $driver->page('course', '');
            $this->assertSame('Classroom Synthetic', $courses[0]['name']);
            $this->assertStringStartsWith('https://classroom.googleapis.com/v1/courses?', $http->calls[3][0]);
        } finally {
            putenv(self::REF);
        }
    }

    /**
     * Graph uses the correct Office and Education paths and its token cannot follow another origin.
     */
    public function test_graph_oauth_and_origin_boundary(): void {
        $this->resetAfterTest();
        $http = $this->transport([
            ['access_token' => 'graph-synthetic'],
            ['value' => [
                ['id' => '1', 'givenName' => 'Eva', 'surname' => 'Doe', 'userPrincipalName' => 'eva@example.invalid'],
            ]],
            ['access_token' => 'graph-synthetic'],
            ['value' => [['id' => 'education1', 'displayName' => 'Graph School']]],
            ['access_token' => 'graph-synthetic'],
        ]);
        putenv(self::REF . '=' . json_encode(['clientid' => 'id', 'clientsecret' => 'secret']));
        try {
            $tenant = 'c1563c50-2d4d-4f7e-a52b-971293a98632';
            $options = json_encode(['endpoint' => $tenant, 'mapping' => []]);
            $driver = factory::make((object)['type' => 'microsoft', 'secretref' => self::REF, 'options' => $options], $http);
            [$members] = $driver->page('user', '');
            [$classes] = $driver->page('course', '');
            $this->assertSame('eva@example.invalid', $members[0]['mail']);
            $this->assertSame('Graph School', $classes[0]['displayName']);
            $this->assertSame('client_credentials', $http->calls[0][1]['grant_type']);
            $this->assertStringStartsWith('https://graph.microsoft.com/v1.0/education/classes?', $http->calls[3][0]);
            try {
                $driver->page('user', 'https://other.example.invalid/v1.0/users');
                $this->fail('Unexpected continuation accepted');
            } catch (problem $failure) {
                $this->assertSame('endpoint', $failure->reason);
            }
            $this->assertCount(5, $http->calls);
        } finally {
            putenv(self::REF);
        }
    }

    /**
     * Bounded pages recover after backoff and a second work slice resumes at its continuation.
     */
    public function test_gateway_retry_and_apply_batches(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->local_skillupsintegration_hosts = ['gateway.example.invalid'];
        $id = profile::save((object)[
            'id' => 0, 'revision' => 0, 'name' => 'Academic test gateway', 'type' => 'sis',
            'enabled' => 1, 'users' => 1, 'courses' => 0, 'categoryid' => 0, 'pushuserid' => 0, 'webhook' => 0,
            'secretref' => self::REF, 'policy' => 'preserve',
            'endpoint' => 'https://gateway.example.invalid/v1',
            'mapping' => '{"user":{"firstname":{"path":"profile.name","transforms":["trim","collapse"]}}}',
        ]);
        $records = [];
        for ($i = 0; $i < 51; $i++) {
            $records[] = [
                'id' => 'a' . $i, 'username' => 'gateway_user_' . $i,
                'profile' => ['name' => '  Test   Name '],
                'lastname' => 'Sample', 'email' => "gateway$i@example.invalid",
            ];
        }
        $http = $this->transport([
            new problem('upstream', true, 40),
            ['items' => $records, 'next_cursor' => 'source.next'],
            ['items' => [], 'next_cursor' => ''],
        ]);
        putenv(self::REF . '=' . json_encode(['audience' => 'gateway.example.invalid', 'bearer' => 'fixture']));
        try {
            $runid = runs::preview($id);
            worker::process($runid, $http);
            $this->assertEquals(1, runs::get($runid)->attempts);
            $this->assertEquals('upstream', runs::get($runid)->lasterror);
            worker::process($runid, $http);
            $this->assertCount(1, $http->calls);
            $DB->set_field(store::RUN, 'nextat', time(), ['id' => $runid]);
            worker::process($runid, $http);
            $this->assertSame('queued', runs::get($runid)->state);
            worker::process($runid, $http);
            $this->assertStringContainsString('cursor=source.next', $http->calls[2][0]);
            $this->assertSame('ready', runs::get($runid)->state);
            runs::approve($runid, runs::get($runid)->sealed);
            worker::process($runid);
            $this->assertSame('applying', runs::get($runid)->state);
            $this->assertEquals(50, $DB->count_records(store::ITEM, ['runid' => $runid, 'state' => 'applied']));
            worker::process($runid);
            $this->assertSame('done', runs::get($runid)->state);
            $this->assertEquals(51, $DB->count_records(store::LINK));
            $this->assertSame('Test Name', $DB->get_field('user', 'firstname', ['username' => 'gateway_user_50']));
        } finally {
            putenv(self::REF);
        }
    }

    /**
     * Custom gateway configuration only accepts an exact approved HTTPS hostname.
     */
    public function test_endpoint_validation_rejects_untrusted_destinations(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_skillupsintegration_hosts = ['sis.example.invalid'];
        http_transport::validate_custom('https://sis.example.invalid/api/v1');
        $this->assertSame('sis.example.invalid', http_transport::hostname('https://sis.example.invalid/api/v1'));
        foreach (
            ['http://sis.example.invalid/api', 'https://127.0.0.1/api', 'https://sis.example.invalid@other.invalid/x',
            'https://sis.example.invalid:8080/api', 'https://other.example.invalid/api'] as $url
        ) {
            try {
                http_transport::validate_custom($url);
                $this->fail('Unexpected origin accepted');
            } catch (problem $failure) {
                $this->assertSame('endpoint', $failure->reason);
            }
        }
    }
}
