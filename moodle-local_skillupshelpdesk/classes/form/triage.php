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

namespace local_skillupshelpdesk\form;

use context_system;
use local_skillupshelpdesk\access;
use local_skillupshelpdesk\catalog;
use local_skillupshelpdesk\tickets;

/**
 * Moodle Form for assigning and classifying a ticket.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class triage extends \moodleform {
    /**
     * Build current and available selections for the scoped staff actor.
     */
    public function definition(): void {
        $form = $this->_form;
        $ticket = $this->_customdata['ticket'];
        foreach (tickets::DIMENSIONS as $kind) {
            $field = $kind . 'id';
            $choices = catalog::options($kind);
            $choices[$ticket->$field] ??= catalog::label(catalog::entry($kind, (int)$ticket->$field, false));
            $form->addElement('select', $field, access::text('kind_' . $kind), $choices);
            $form->setType($field, PARAM_INT);
            $form->setDefault($field, $ticket->$field);
        }
        if (access::can('supervise')) {
            $form->addElement('select', 'assigneeid', access::text('kind_assignee'), $this->technicians());
            $form->setType('assigneeid', PARAM_INT);
            $form->setDefault('assigneeid', $ticket->assigneeid);
        }
        $this->add_action_buttons(false, access::text('savetriage'));
    }

    /**
     * Find candidates with the support capability. Current ineligible assignments remain visible.
     *
     * @return array
     */
    private function technicians(): array {
        $ticket = $this->_customdata['ticket'];
        $candidates = get_users_by_capability(context_system::instance(), access::CAP . 'work', 'u.*', 'u.lastname ASC', 0, 500);
        $candidates += get_admins();
        $choices = [0 => access::text('unassigned')];
        foreach ($candidates as $candidate) {
            if (access::can('work', $candidate)) {
                $choices[$candidate->id] = fullname($candidate) . ' (#' . $candidate->id . ')';
            }
        }
        if ($ticket->assigneeid && !isset($choices[$ticket->assigneeid])) {
            $choices[$ticket->assigneeid] = access::text('unavailableuser');
        }
        return $choices;
    }
}
