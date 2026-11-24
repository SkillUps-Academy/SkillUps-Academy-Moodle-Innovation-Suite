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

namespace local_skillupsintegration\connector;

use local_skillupsintegration\problem;
use local_skillupsintegration\transport;
use stdClass;

/**
 * Shared transport, source options and page boundary checks.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements driver {
    /**
     * Construct a configured adapter with an injectable transport.
     *
     * @param stdClass $source Connector record
     * @param array $options Source settings
     * @param transport $http Transport
     */
    public function __construct(
        /** @var stdClass Source connector. */
        protected readonly stdClass $source,
        /** @var array Validated source configuration. */
        protected readonly array $options,
        /** @var transport JSON transport. */
        protected readonly transport $http,
    ) {
    }

    /**
     * Declare the supported integration commands.
     *
     * {@inheritdoc}
     */
    public function capabilities(): array {
        return ['user', 'course', 'pull'];
    }

    /**
     * Return provider mappings for the requested entity.
     *
     * {@inheritdoc}
     */
    public function defaults(string $entity): array {
        return [];
    }

    /**
     * Validate a remote response page.
     *
     * @param mixed $records Row list
     * @param mixed $cursor Next page reference
     * @return array
     */
    protected function result(mixed $records, mixed $cursor): array {
        if (!is_array($records) || !array_is_list($records) || !is_string($cursor) || strlen($cursor) > 8000) {
            throw new problem('response');
        }
        if (count($records) > 100) {
            throw new problem('limit');
        }
        return [$records, $cursor];
    }
}
