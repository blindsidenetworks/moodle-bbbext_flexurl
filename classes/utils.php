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
namespace bbbext_flexurl;

use core_course\external\course_module_summary_exporter;
use core_course\external\course_summary_exporter;
use mod_bigbluebuttonbn\instance;

/**
 * Utility class
 *
 * @package   bbbext_flexurl
 * @copyright 2023 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Laurent David (laurent@call-learning.fr)
 */
class utils {
    /**
     * Types of actions
     */
    const ACTION_CODES = [
        'create' => 2,
        'join' => 1,
        'all' => 8,
    ];
    /**
     * Types of additional parameters
     */
    public const PARAM_TYPES = [
        'eventtype' => PARAM_INT,
        'paramname' => PARAM_ALPHANUMEXT,
        'paramvalue' => PARAM_RAW,
    ];

    /**
     * Get option group for parameters.
     *
     * @return array
     */
    public static function get_options_for_parameters(): array {
        $parametertypes = self::get_parameter_types();
        $options = [];
        $selectedptypes = explode(',', get_config('bbbext_flexurl', 'available_info'));
        $selectedptypes = array_map('trim', $selectedptypes);
        foreach ($parametertypes as $key => $value) {
            if (in_array($key, $selectedptypes)) {
                $options = array_merge($options, self::get_fields_for_parameter($key));
            }
        }
        ksort($options);
        // Now add a marker so we know they are placeholder for values.
        $options = array_combine(array_map(function($key) {
            return '%' . $key . '%';
        }, array_keys($options)), $options);
        return $options;
    }

    /**
     * Get parameter types
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_parameter_types(): array {
        return [
            'activityinfo' => get_string('activity_info', 'bbbext_flexurl'),
            'courseinfo' => get_string('course_info', 'bbbext_flexurl'),
            'user' => get_string('user_info', 'bbbext_flexurl'),
        ];
    }

    /**
     * Get fields for parameter
     *
     * @param string $key
     * @return array
     */
    public static function get_fields_for_parameter(string $key): array {
        if (method_exists(self::class, 'get_' . $key . '_fields')) {
            $fields = call_user_func(self::class . '::get_' . $key . '_fields');
            ksort($fields);
            return array_combine($fields, $fields);
        } else {
            return [];
        }
    }

    /**
     * Get value for field
     *
     * @param string $field
     * @param instance $instance
     * @return string
     */
    public static function get_value_for_field(string $field, instance $instance): string {
        // Split the string before the first dot (this will be the type) and the other will be the name of the field.
        [$fieldtype, $fieldname] = explode('.', $field);
        if (method_exists(self::class, 'get_' . $fieldtype . '_value')) {
            return call_user_func(self::class . '::get_' . $fieldtype . '_value', $fieldname, $instance);
        } else {
            return '';
        }
    }
    /**
     * Get value for this parameter
     *
     * @param string $rawvalue
     * @param instance $instance
     * @return string
     */
    public static function get_real_value(string $rawvalue, instance $instance): string {
        // First check if there is a marker for a placeholder.
        if (strpos($rawvalue, '%') === 0) {
            $field = substr($rawvalue, 1, -1);
            return self::get_value_for_field($field, $instance);
        } else {
            return $rawvalue;
        }
    }
    /**
     * Get user fields prefixed by user.
     *
     * @param string $fieldname
     * @param instance $instance
     * @return string
     */
    public static function get_user_value(string $fieldname, instance $instance): string {
        global $USER;
        $fields = self::get_user_fields();
        if (!in_array('user.' . $fieldname, $fields)) {
            return '';
        }
        $userwithfields = \core_user::get_user($USER->id);
        return $userwithfields->$fieldname ?? '';
    }

    /**
     * Get user fields prefixed by user.
     *
     * For now we only return simple fields located in core_user definition.
     *
     * @return string[]
     */
    public static function get_user_fields() {
        $userfields = \core_user\fields::get_identity_fields(\context_system::instance());
        $userfields = array_merge($userfields, \core_user\fields::get_name_fields());
        sort($userfields);
        return array_map(
            function($field) {
                return "user.{$field}";
            },
            $userfields
        );
    }

    /**
     * Course information
     *
     * @param string $fieldname
     * @param instance $instance
     * @return string
     */
    public static function get_courseinfo_value(string $fieldname, instance $instance) {
        global $PAGE;
        $fields = self::get_courseinfo_fields();
        if (!in_array('courseinfo.' . $fieldname, $fields)) {
            return '';
        }
        $exporter =
            new course_summary_exporter($instance->get_course(), ['context' => $instance->get_context()->get_course_context()]);
        $coursedata = $exporter->export($PAGE->get_renderer('core'));
        return $coursedata->$fieldname ?? '';
    }

    /**
     * Course information
     *
     * @return string[]
     */
    public static function get_courseinfo_fields() {
        return array_map(
            function($field) {
                return "courseinfo.{$field}";
            },
            array_keys(course_summary_exporter::read_properties_definition())
        );
    }

    /**
     * Activity information
     *
     * @param string $fieldname
     * @param instance $instance
     * @return string
     */
    public static function get_activityinfo_value(string $fieldname, instance $instance) {
        global $PAGE;
        $fields = self::get_activityinfo_fields();
        if (!in_array('activityinfo.' . $fieldname, $fields)) {
            return '';
        }
        $exporter = new course_module_summary_exporter(null, ['cm' => $instance->get_cm()]);
        $moduledata = $exporter->export($PAGE->get_renderer('core'));
        return $moduledata->$fieldname ?? '';
    }

    /**
     * Activity information
     *
     * @return string[]
     */
    public static function get_activityinfo_fields() {
        return array_map(
            function($field) {
                return "activityinfo.{$field}";
            },
            array_keys(course_module_summary_exporter::read_properties_definition())
        );
    }

    /**
     * Type of event : create or join
     *
     * @return array
     */
    public static function get_option_for_eventtype() {
        return [
            self::ACTION_CODES['create'] => get_string('event_create', 'bbbext_flexurl'),
            self::ACTION_CODES['join'] => get_string('event_join', 'bbbext_flexurl'),
            self::ACTION_CODES['all'] => get_string('event_all', 'bbbext_flexurl'),
        ];
    }
}
