<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class tool_carcastc_model
 *
 * @package   tool_carcastc
 * @copyright 2021, Carlos Castillo <carlos.castillo@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_carcastc;

defined('MOODLE_INTERNAL') || die();

use context_course;

/**
 * Class tool_carcastc_model to handle all plugin logic
 *
 * @package   tool_carcastc
 * @copyright 2021, Carlos Castillo <carlos.castillo@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_carcastc_model {


    /**
     * Get existing row
     *
     * @param array $params optional params
     * @param int $strictness
     * @return \stdClass|bool return row object if found and false when not found
     */
    public static function get_row(array $params = [], int $strictness = MUST_EXIST) {
        global $DB;

        return $DB->get_record('tool_carcastc', $params, '*', $strictness);
    }

    /**
     * Get rows based on custom SQL query
     *
     * @param string $sql
     * @param array $params optional params
     * @param int $strictness
     * @return \stdClass|bool return row object if found and false when not found
     */
    public static function get_rows_sql(string $sql = '', array $params = [], int $strictness = MUST_EXIST) {
        global $DB;

        return $DB->get_record_sql($sql, $params, $strictness);
    }

    /**
     * Save row
     *
     * @param \stdClass $form form object
     * @param bool $returnid true if we need return row id inserted
     * @return bool return true if row is saved or updated
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function save_row(\stdClass $form, bool $returnid = true) {
        global $DB;

        $context = context_course::instance($form->courseid);
        $editoroptions = ['trusttext' => true, 'subdirs' => true, 'maxfiles' => -1, 'maxbytes' => 0, 'context' => $context];

        try {
            if (isset($form->id) && $form->id) {

                // Update the description if is sent in form.
                if (isset($form->description_editor)) {
                    $form = file_postupdate_standard_editor($form, 'description',
                            $editoroptions, $editoroptions['context'], 'tool_carcastc', 'rowfile', $form->id);
                }

                $row = [
                        'id' => $form->id,
                        'name' => $form->name,
                        'completed' => $form->completed,
                        'description' => $form->description,
                        'descriptionformat' => $form->descriptionformat,
                        'timemodified' => time()
                ];
                $rowid = $DB->update_record('tool_carcastc', (object) $row);

                // Trigger event.
                self::trigger_event('updated', (object)['id' => $row['id'], 'courseid' => $form->courseid]);

            } else {
                $row = [
                        'courseid' => $form->courseid,
                        'name' => $form->name,
                        'completed' => $form->completed,
                        'priority' => 0,
                        'timecreated' => time(),
                        'timemodified' => time()
                ];

                $rowid = $DB->insert_record('tool_carcastc', $row, $returnid);

                // After insert row update the description and save the files.
                if (isset($form->description_editor)) {
                    $form = file_postupdate_standard_editor($form, 'description',
                            $editoroptions, $editoroptions['context'], 'tool_carcastc', 'rowfile', $rowid);

                    $updatedrow = ['id' => $rowid, 'description' => $form->description,
                            'descriptionformat' => $form->descriptionformat];
                    $DB->update_record('tool_carcastc', (object) $updatedrow);
                }

                // Trigger event.
                self::trigger_event('created', (object)['id' => $rowid, 'courseid' => $form->courseid]);

            }

            return $rowid;
        } catch (\dml_exception $e) {
            throw new \coding_exception($e->getMessage());
        }
    }

    /**
     * Get existing row
     *
     * @param array $params optional params
     * @return \bool return true if row is deleted
     */
    public static function delete_row(array $params = []) {
        global $DB;

        if (!$row = self::get_row(['id' => $params['id']], IGNORE_MISSING)) {
            return false;
        }

        $DB->delete_records('tool_carcastc', $params);

        // Trigger event.
        self::trigger_event('deleted', $row, true);

        return true;
    }

    /**
     * Trigger events
     *
     * @param string $event event to triggered
     * @param object $row row object
     * @param bool $snapshot true if need create snapshot
     * @return \void
     */
    public static function trigger_event(string $event, object $row, bool $snapshot = false) {

        // Trigger events.
        $event = call_user_func("\\tool_carcastc\\event\\row_".$event. "::create", [
                'context' => context_course::instance($row->courseid),
                'objectid' => $row->id
        ]);

        if ($snapshot) {
            $event->add_record_snapshot('tool_carcastc', $row);
        }

        $event->trigger();
    }



    /**
     * Method called when course_deleted event is triggered deleting all rows created in tool_carcastc with this courseid
     *
     * @param \core\event\course_deleted $event
     */
    public static function on_course_deleted_observer(\core\event\course_deleted $event) {
        global $DB;

        $DB->delete_records('tool_carcastc', ['courseid' => $event->objectid]);
    }
}
