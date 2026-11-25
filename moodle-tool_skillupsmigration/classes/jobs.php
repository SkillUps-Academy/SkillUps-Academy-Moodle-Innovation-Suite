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

namespace tool_skillupsmigration;

/**
 * Persist preview, simulation, confirmation and cancellation of migration batches.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class jobs {
    /**
     * Inventory a small batch and persist the exact selections reviewed by the operator.
     *
     * @param string $origin Registry key
     * @param int $category Destination category
     * @param array $rows Source ID => new short name
     * @param array $options Selected features
     * @param int $actor Authorized local operator
     * @return int Job ID
     */
    public static function create(string $origin, int $category, array $rows, array $options, int $actor): int {
        global $DB;
        support::category($category, $actor);
        $options = inventory::options($options);
        if (
            !$rows || count($rows) > 10 ||
            (count($rows) > 1 && ($options['sections'] || $options['activities'] || $options['staff']))
        ) {
            support::fail('selection');
        }
        $connector = client::probe($origin);
        $identity = client::identity($origin);
        $names = [];
        $prepared = [];
        foreach ($rows as $source => $name) {
            if (
                (int)$source < 1 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/D', $name) ||
                isset($names[strtolower($name)]) || $DB->record_exists('course', ['shortname' => $name])
            ) {
                support::fail('duplicate');
            }
            $names[strtolower($name)] = true;
            $data = client::call($origin, 'inventory', ['course' => $source, 'options' => self::origin_options($options)]);
            if (
                ($data['id'] ?? 0) != $source || ($data['version'] ?? 0) !== $connector['version'] ||
                !isset($data['sections'], $data['modules'], $data['format'], $data['name'])
            ) {
                support::fail('remoteresponse');
            }
            $prepared[] = [
                'sourceid' => $source, 'shortname' => $name, 'courseid' => 0, 'requestkey' => bin2hex(random_bytes(24)),
                'state' => 'planned', 'options' => support::encode($options),
                'inventory' => support::encode($data),
                'report' => support::encode(['issues' => archive::compatibility($data, $options)]),
                'digest' => '', 'updated' => time(),
            ];
        }
        return support::transaction(static function () use ($DB, $actor, $origin, $identity, $category, $prepared): int {
            $id = $DB->insert_record(support::JOB, (object)[
                'actor' => $actor, 'endpoint' => $origin, 'originhash' => $identity, 'categoryid' => $category,
                'state' => 'draft', 'created' => time(), 'updated' => time(), 'wave' => 0,
            ]);
            foreach ($prepared as $row) {
                $DB->insert_record(support::ITEM, (object)($row + ['jobid' => $id]));
            }
            support::audit($id, 0, $actor, 'created');
            return (int)$id;
        });
    }

    /**
     * Export only the source side of explicitly mapped identities.
     *
     * @param array $opts Full local plan
     * @return array Source-safe options
     */
    public static function origin_options(array $opts): array {
        $opts['staff'] = array_map(static fn($pair) => [
            'sourceuser' => $pair['sourceuser'], 'targetuser' => $pair['sourceuser'],
            'sourcerole' => $pair['sourcerole'], 'targetrole' => $pair['sourcerole'],
        ], $opts['staff']);
        return $opts;
    }

    /**
     * Transition a job only after a native confirmation form submits the current wave.
     *
     * @param int $id Job ID
     * @param string $action User action
     * @param int $wave Expected wave
     * @param int $actor Current Moodle account
     */
    public static function change(int $id, string $action, int $wave, int $actor): void {
        global $DB;
        $job = support::lock('job:' . $id, static function () use ($DB, $id, $action, $wave, $actor): \stdClass {
            return support::transaction(static function () use ($DB, $id, $action, $wave, $actor): \stdClass {
                $job = support::get(support::JOB, $id);
                support::category((int)$job->categoryid, $actor);
                if ($job->actor != $actor && !is_siteadmin($actor) || $job->wave != $wave) {
                    support::fail('stale');
                }
                $items = $DB->get_records(support::ITEM, ['jobid' => $id]);
                if ($action === 'simulate' && $job->state === 'draft') {
                    $job->state = 'preflight';
                } else if ($action === 'execute' && $job->state === 'review') {
                    foreach ($items as $item) {
                        if (!in_array($item->state, ['ready', 'done'], true)) {
                            support::fail('blockedbatch');
                        }
                    }
                    support::category((int)$job->categoryid, (int)$job->actor);
                    $job->state = 'running';
                } else if ($action === 'cancel' && !in_array($job->state, ['complete', 'cancelled'], true)) {
                    foreach ($items as $item) {
                        if (!in_array($item->state, ['done', 'blocked', 'uncertain'], true)) {
                            $item->state = $item->state === 'restoring' ? 'uncertain' : 'cancelled';
                            $DB->update_record(support::ITEM, $item);
                        }
                    }
                    $job->state = 'cancelled';
                } else if ($action === 'retry' && in_array($job->state, ['review', 'complete'], true)) {
                    $count = 0;
                    foreach ($items as $item) {
                        if ($item->state === 'blocked' && !$item->courseid) {
                            processor::clean($item);
                            $item->requestkey = bin2hex(random_bytes(24));
                            $item->state = 'planned';
                            $item->exportid = 0;
                            $item->bytes = 0;
                            $item->digest = '';
                            $item->attempts = 0;
                            $item->nexttry = 0;
                            $item->updated = time();
                            $DB->update_record(support::ITEM, $item);
                            $count++;
                        }
                    }
                    if (!$count) {
                        support::fail('nothingtoretry');
                    }
                    $job->state = 'preflight';
                } else {
                    support::fail('stale');
                }
                $job->wave++;
                $job->updated = time();
                $DB->update_record(support::JOB, $job);
                support::audit($id, 0, $actor, $action);
                return $job;
            });
        });
        if (in_array($job->state, ['preflight', 'running'], true)) {
            processor::queue($job);
        }
    }
}
