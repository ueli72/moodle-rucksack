<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

/**
 * External functions for rucksack module
 */
class local_rucksack_external extends external_api {

    /**
     * Returns description of rucksack_get_all_badges parameters
     *
     * @return external_function_parameters
     */
    public static function get_all_badges_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Returns description of rucksack_get_all_badges return values
     *
     * @return external_single_structure
     */
    public static function get_all_badges_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Badge ID'),
                    'name' => new external_value(PARAM_TEXT, 'Badge name'),
                    'description' => new external_value(PARAM_RAW, 'Badge description'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'issuername' => new external_value(PARAM_TEXT, 'Issuer name'),
                    'issuerurl' => new external_value(PARAM_URL, 'Issuer URL'),
                    'issuercontact' => new external_value(PARAM_TEXT, 'Issuer contact', VALUE_OPTIONAL),
                    'status' => new external_value(PARAM_INT, 'Badge status'),
                    'imageurl' => new external_value(PARAM_URL, 'Badge image URL')
                )
            )
        );
    }

    /**
     * Get all enabled badges
     *
     * @return array of badges
     */
    public static function get_all_badges() {
        global $DB, $CFG;
        
        require_once($CFG->libdir . '/badgeslib.php');
        
        // Check capability
        $context = context_system::instance();
        self::validate_context($context);
        
        // Get all enabled badges - only return badges that actually exist
        $sql = "SELECT * FROM {badge} WHERE status != :status_archived";
        $badges = $DB->get_records_sql($sql, ['status_archived' => BADGE_STATUS_ARCHIVED]);
        
        $result = [];
        foreach ($badges as $badge) {
            // Get badge image URL
            $badgeimage = new moodle_url('/badges/image.php', array('id' => $badge->id, 'hash' => $badge->imagehash));
            
            $result[] = [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'timecreated' => $badge->timecreated,
                'timemodified' => $badge->timemodified,
                'issuername' => $badge->issuername,
                'issuerurl' => $badge->issuerurl,
                'issuercontact' => $badge->issuercontact,
                'status' => $badge->status,
                'imageurl' => $badgeimage->out(false)
            ];
        }
        
        return $result;
    }

    /**
     * Returns description of get_user_badge_awards parameters
     *
     * @return external_function_parameters
     */
    public static function get_user_badge_awards_parameters() {
        return new external_function_parameters([
            'badgeids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Badge ID'),
                'Array of badge IDs to filter (optional)',
                VALUE_DEFAULT,
                []
            )
        ]);
    }

    /**
     * Returns description of get_user_badge_awards return values
     *
     * @return external_single_structure
     */
    public static function get_user_badge_awards_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'username' => new external_value(PARAM_TEXT, 'Username', VALUE_OPTIONAL),
                    'firstname' => new external_value(PARAM_TEXT, 'First name', VALUE_OPTIONAL),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name', VALUE_OPTIONAL),
                    'email' => new external_value(PARAM_TEXT, 'Email address', VALUE_OPTIONAL),
                    'badges' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'badgeid' => new external_value(PARAM_INT, 'Badge ID'),
                                'badgename' => new external_value(PARAM_TEXT, 'Badge name', VALUE_OPTIONAL),
                                'dateissued' => new external_value(PARAM_INT, 'Date issued (timestamp)'),
                                'dateexpire' => new external_value(PARAM_INT, 'Date expire (timestamp)', VALUE_OPTIONAL)
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Get all users with their badge awards
     * Returns only users who actually have badge awards - no test data generation
     *
     * @param array $badgeids Optional array of badge IDs to filter
     * @return array of users with their badges
     */
    public static function get_user_badge_awards($badgeids = []) {
        global $DB, $CFG;
        
        require_once($CFG->libdir . '/badgeslib.php');
        
        // Check capability
        $context = context_system::instance();
        self::validate_context($context);
        
        // Validate parameters
        $params = self::validate_parameters(self::get_user_badge_awards_parameters(), [
            'badgeids' => $badgeids
        ]);
        
        try {
            // Check if any badge awards exist at all
            $issued_count = $DB->count_records('badge_issued');
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Debug: Found $issued_count badge awards in database\n", FILE_APPEND);
            
            // If no badge awards exist, return empty array - no test data generation
            if ($issued_count == 0) {
                file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Debug: No badge awards found, returning empty array\n", FILE_APPEND);
                return [];
            }
            
            // Build WHERE condition for badge filter
            $badgefilter = '';
            $params_sql = [];
            if (!empty($params['badgeids'])) {
                list($insql, $inparams) = $DB->get_in_or_equal($params['badgeids'], SQL_PARAMS_NAMED, 'badgeid');
                $badgefilter = " AND bi.badgeid $insql";
                $params_sql = $inparams;
            }
            
            // Main query for users with badge awards
            // Only return actual existing awards, no test data
            // No status filter - include all badge awards regardless of badge status
            $sql = "SELECT bi.userid, bi.badgeid, bi.dateissued, bi.dateexpire,
                           u.username, u.firstname, u.lastname, u.email,
                           b.name as badgename
                    FROM {badge_issued} bi
                    JOIN {user} u ON u.id = bi.userid  
                    JOIN {badge} b ON b.id = bi.badgeid";
            
            // Add WHERE clause if badge filter is specified
            if (!empty($badgefilter)) {
                $sql .= " WHERE 1=1" . $badgefilter;
            }
            
            $sql .= " ORDER BY u.lastname, u.firstname, bi.dateissued DESC";
            
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - SQL Query: " . $sql . "\n", FILE_APPEND);
            
            $records = $DB->get_records_sql($sql, $params_sql);
            
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Debug: Found " . count($records) . " badge award records\n", FILE_APPEND);
            
            // Format results by user - only include users with valid data
            $userdata = [];
            foreach ($records as $record) {
                // Skip records with missing essential data
                if (empty($record->userid) || empty($record->badgeid)) {
                    continue;
                }
                
                $userid = $record->userid;
                
                if (!isset($userdata[$userid])) {
                    $userdata[$userid] = [
                        'userid' => (int)$record->userid,
                        'username' => $record->username ?: '',
                        'firstname' => $record->firstname ?: '',
                        'lastname' => $record->lastname ?: '',
                        'email' => $record->email ?: '',
                        'badges' => []
                    ];
                }
                
                
                $userdata[$userid]['badges'][] = [
                    'badgeid' => (int)$record->badgeid,
                    'badgename' => $record->badgename ?: '',
                    'dateissued' => (int)$record->dateissued,
                    'dateexpire' => $record->dateexpire ? (int)$record->dateexpire : null
                ];
            }
            
            $result = array_values($userdata);
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Debug: Returning " . count($result) . " users with badges\n", FILE_APPEND);
            
            return $result;

        } catch (Exception $e) {
            // Log error for debugging
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Error: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents('/tmp/rucksack_debug.log', date('Y-m-d H:i:s') . " - Rucksack Error Stack: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            // Return empty array instead of test data on error
            return [];
        }
    }

    // ============================
    // Configuration sets API
    // ============================

    public static function create_config_set_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Configuration set name'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Optional competency framework filter', VALUE_DEFAULT, 0),
            'badges' => new external_multiple_structure(
                new external_single_structure([
                    'badgeid' => new external_value(PARAM_INT, 'Badge ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'visible' => new external_value(PARAM_INT, 'Visible (1/0)', VALUE_DEFAULT, 1),
                ]),
                'Badge configurations',
                VALUE_DEFAULT,
                []
            ),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'competencyid' => new external_value(PARAM_INT, 'Competency ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'visible' => new external_value(PARAM_INT, 'Visible (1/0)', VALUE_DEFAULT, 1),
                ]),
                'Competency configurations',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function create_config_set($name, $description, $frameworkid, $badges, $competencies) {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_config_set_parameters(), [
            'name' => $name,
            'description' => $description,
            'frameworkid' => $frameworkid,
            'badges' => $badges,
            'competencies' => $competencies,
        ]);

        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $now = time();
        $config = new stdClass();
        $config->name = $params['name'];
        $config->description = $params['description'];
        $config->frameworkid = $params['frameworkid'] ?: null;
        $config->createdby = $USER->id;
        $config->timecreated = $now;
        $config->timemodified = $now;
        $configid = $DB->insert_record('local_rucksack_config', $config);

        foreach ($params['badges'] as $badge) {
            $record = new stdClass();
            $record->configid = $configid;
            $record->badgeid = $badge['badgeid'];
            $record->sortorder = $badge['sortorder'];
            $record->visible = $badge['visible'];
            $DB->insert_record('local_rucksack_config_badge', $record);
        }

        foreach ($params['competencies'] as $comp) {
            $record = new stdClass();
            $record->configid = $configid;
            $record->competencyid = $comp['competencyid'];
            $record->sortorder = $comp['sortorder'];
            $record->visible = $comp['visible'];
            $DB->insert_record('local_rucksack_config_comp', $record);
        }

        return ['id' => $configid, 'status' => 'ok'];
    }

    public static function create_config_set_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Created config set ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }

    public static function update_config_set_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Configuration set ID'),
            'name' => new external_value(PARAM_TEXT, 'Configuration set name', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'frameworkid' => new external_value(PARAM_INT, 'Optional competency framework filter', VALUE_DEFAULT, -1),
            'badges' => new external_multiple_structure(
                new external_single_structure([
                    'badgeid' => new external_value(PARAM_INT, 'Badge ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'visible' => new external_value(PARAM_INT, 'Visible (1/0)', VALUE_DEFAULT, 1),
                ]),
                'Badge configurations',
                VALUE_DEFAULT,
                []
            ),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'competencyid' => new external_value(PARAM_INT, 'Competency ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'visible' => new external_value(PARAM_INT, 'Visible (1/0)', VALUE_DEFAULT, 1),
                ]),
                'Competency configurations',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function update_config_set($id, $name, $description, $frameworkid, $badges, $competencies) {
        global $DB;

        $params = self::validate_parameters(self::update_config_set_parameters(), [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'frameworkid' => $frameworkid,
            'badges' => $badges,
            'competencies' => $competencies,
        ]);

        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $config = $DB->get_record('local_rucksack_config', ['id' => $params['id']]);
        if (!$config) {
            throw new moodle_exception('nosuchconfig', 'local_rucksack');
        }

        if ($params['name'] !== '') {
            $config->name = $params['name'];
        }
        $config->description = $params['description'];
        if ($params['frameworkid'] >= 0) {
            $config->frameworkid = $params['frameworkid'] ?: null;
        }
        $config->timemodified = time();
        $DB->update_record('local_rucksack_config', $config);

        if (!empty($params['badges'])) {
            $DB->delete_records('local_rucksack_config_badge', ['configid' => $config->id]);
            foreach ($params['badges'] as $badge) {
                $record = new stdClass();
                $record->configid = $config->id;
                $record->badgeid = $badge['badgeid'];
                $record->sortorder = $badge['sortorder'];
                $record->visible = $badge['visible'];
                $DB->insert_record('local_rucksack_config_badge', $record);
            }
        }

        if (!empty($params['competencies'])) {
            $DB->delete_records('local_rucksack_config_comp', ['configid' => $config->id]);
            foreach ($params['competencies'] as $comp) {
                $record = new stdClass();
                $record->configid = $config->id;
                $record->competencyid = $comp['competencyid'];
                $record->sortorder = $comp['sortorder'];
                $record->visible = $comp['visible'];
                $DB->insert_record('local_rucksack_config_comp', $record);
            }
        }

        return ['id' => $config->id, 'status' => 'ok'];
    }

    public static function update_config_set_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Updated config set ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }

    public static function delete_config_set_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Configuration set ID'),
        ]);
    }

    public static function delete_config_set($id) {
        global $DB;

        $params = self::validate_parameters(self::delete_config_set_parameters(), ['id' => $id]);
        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $config = $DB->get_record('local_rucksack_config', ['id' => $params['id']]);
        if (!$config) {
            throw new moodle_exception('nosuchconfig', 'local_rucksack');
        }

        $DB->delete_records('local_rucksack_config_badge', ['configid' => $config->id]);
        $DB->delete_records('local_rucksack_config_comp', ['configid' => $config->id]);
        $DB->delete_records('local_rucksack_user_config', ['configid' => $config->id]);
        $DB->delete_records('local_rucksack_config', ['id' => $config->id]);

        return ['status' => 'ok'];
    }

    public static function delete_config_set_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }

    public static function get_config_sets_parameters() {
        return new external_function_parameters([]);
    }

    public static function get_config_sets() {
        global $DB;

        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $records = $DB->get_records('local_rucksack_config', [], 'name');
        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'id' => $record->id,
                'name' => $record->name,
                'description' => $record->description,
                'frameworkid' => $record->frameworkid,
                'createdby' => $record->createdby,
                'timecreated' => $record->timecreated,
                'timemodified' => $record->timemodified,
            ];
        }
        return $result;
    }

    public static function get_config_sets_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Config ID'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'frameworkid' => new external_value(PARAM_INT, 'Framework ID'),
                'createdby' => new external_value(PARAM_INT, 'Created by'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ])
        );
    }

    public static function get_config_set_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Configuration set ID'),
        ]);
    }

    public static function get_config_set($id) {
        global $DB;

        $params = self::validate_parameters(self::get_config_set_parameters(), ['id' => $id]);
        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $config = $DB->get_record('local_rucksack_config', ['id' => $params['id']]);
        if (!$config) {
            throw new moodle_exception('nosuchconfig', 'local_rucksack');
        }

        $badges = $DB->get_records('local_rucksack_config_badge', ['configid' => $config->id]);
        $comps = $DB->get_records('local_rucksack_config_comp', ['configid' => $config->id]);
        $users = $DB->get_records('local_rucksack_user_config', ['configid' => $config->id]);

        return [
            'id' => $config->id,
            'name' => $config->name,
            'description' => $config->description,
            'frameworkid' => $config->frameworkid,
            'createdby' => $config->createdby,
            'timecreated' => $config->timecreated,
            'timemodified' => $config->timemodified,
            'badges' => array_values($badges),
            'competencies' => array_values($comps),
            'assignedusers' => array_values($users),
        ];
    }

    public static function get_config_set_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Config ID'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'description' => new external_value(PARAM_RAW, 'Description'),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID'),
            'createdby' => new external_value(PARAM_INT, 'Created by'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            'badges' => new external_multiple_structure(
                new external_single_structure([
                    'badgeid' => new external_value(PARAM_INT, 'Badge ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'visible' => new external_value(PARAM_INT, 'Visible'),
                ])
            ),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'competencyid' => new external_value(PARAM_INT, 'Competency ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'visible' => new external_value(PARAM_INT, 'Visible'),
                ])
            ),
            'assignedusers' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'configid' => new external_value(PARAM_INT, 'Config ID'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                ])
            ),
        ]);
    }

    public static function assign_config_set_parameters() {
        return new external_function_parameters([
            'configid' => new external_value(PARAM_INT, 'Configuration set ID'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'List of user IDs'
            ),
        ]);
    }

    public static function assign_config_set($configid, $userids) {
        global $DB;

        $params = self::validate_parameters(self::assign_config_set_parameters(), [
            'configid' => $configid,
            'userids' => $userids,
        ]);

        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $config = $DB->get_record('local_rucksack_config', ['id' => $params['configid']]);
        if (!$config) {
            throw new moodle_exception('nosuchconfig', 'local_rucksack');
        }

        $now = time();
        foreach ($params['userids'] as $userid) {
            if ($DB->record_exists('local_rucksack_user_config', ['userid' => $userid, 'configid' => $config->id])) {
                continue;
            }
            $record = new stdClass();
            $record->userid = $userid;
            $record->configid = $config->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_rucksack_user_config', $record);
        }

        return ['status' => 'ok'];
    }

    public static function assign_config_set_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }

    public static function unassign_config_set_parameters() {
        return new external_function_parameters([
            'configid' => new external_value(PARAM_INT, 'Configuration set ID'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'List of user IDs'
            ),
        ]);
    }

    public static function unassign_config_set($configid, $userids) {
        global $DB;

        $params = self::validate_parameters(self::unassign_config_set_parameters(), [
            'configid' => $configid,
            'userids' => $userids,
        ]);

        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        foreach ($params['userids'] as $userid) {
            $DB->delete_records('local_rucksack_user_config', [
                'userid' => $userid,
                'configid' => $params['configid'],
            ]);
        }

        return ['status' => 'ok'];
    }

    public static function unassign_config_set_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
        ]);
    }

    public static function get_user_config_set_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function get_user_config_set($userid) {
        global $DB;

        $params = self::validate_parameters(self::get_user_config_set_parameters(), ['userid' => $userid]);
        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        $record = local_rucksack_get_user_config($params['userid']);
        if (!$record) {
            return ['id' => 0, 'name' => '', 'description' => ''];
        }

        return [
            'id' => $record->id,
            'name' => $record->name,
            'description' => $record->description,
            'frameworkid' => $record->frameworkid,
        ];
    }

    public static function get_user_config_set_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Config ID'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'description' => new external_value(PARAM_RAW, 'Description'),
            'frameworkid' => new external_value(PARAM_INT, 'Framework ID'),
        ]);
    }

    public static function get_earned_badges_parameters() {
        return new external_function_parameters([
            'userhash' => new external_value(PARAM_TEXT, 'Encrypted user token'),
        ]);
    }

    public static function get_earned_badges($userhash) {
        global $PAGE;

        $params = self::validate_parameters(self::get_earned_badges_parameters(), ['userhash' => $userhash]);
        $payload = local_rucksack_decrypt($params['userhash']);
        $userid = is_array($payload) ? $payload['u'] : 0;
        $setid = is_array($payload) ? $payload['s'] : 0;
        if ($userid <= 0) {
            throw new moodle_exception('invaliduser', 'local_rucksack');
        }

        $PAGE->set_context(context_system::instance());
        $renderer = $PAGE->get_renderer('local_rucksack');
        $renderable = new \local_rucksack\output\earned_badges($userid, $setid);
        $data = $renderable->export_for_template($renderer);

        return [
            'username' => $data->username,
            'firstname' => $data->firstname,
            'lastname' => $data->lastname,
            'date' => $data->date,
            'userhash' => $data->userhash,
            'pdfurl' => $data->pdfurl,
            'nodata' => !empty($data->nodata),
            'plansjson' => json_encode($data->plans ?? []),
        ];
    }

    public static function get_earned_badges_returns() {
        return new external_single_structure([
            'username' => new external_value(PARAM_TEXT, 'User full name'),
            'firstname' => new external_value(PARAM_TEXT, 'First name'),
            'lastname' => new external_value(PARAM_TEXT, 'Last name'),
            'date' => new external_value(PARAM_TEXT, 'Date'),
            'userhash' => new external_value(PARAM_TEXT, 'Encrypted user token'),
            'pdfurl' => new external_value(PARAM_URL, 'PDF URL'),
            'nodata' => new external_value(PARAM_BOOL, 'No data flag'),
            'plansjson' => new external_value(PARAM_RAW, 'Plans as JSON string'),
        ]);
    }

    public static function get_earned_badges_html_parameters() {
        return new external_function_parameters([
            'userhash' => new external_value(PARAM_TEXT, 'Encrypted user token'),
        ]);
    }

    public static function get_earned_badges_html($userhash) {
        global $PAGE;

        $params = self::validate_parameters(self::get_earned_badges_html_parameters(), ['userhash' => $userhash]);
        $payload = local_rucksack_decrypt($params['userhash']);
        $userid = is_array($payload) ? $payload['u'] : 0;
        $setid = is_array($payload) ? $payload['s'] : 0;
        if ($userid <= 0) {
            throw new moodle_exception('invaliduser', 'local_rucksack');
        }

        $PAGE->set_context(context_system::instance());
        $renderer = $PAGE->get_renderer('local_rucksack');
        $renderable = new \local_rucksack\output\earned_badges($userid, $setid);
        $data = $renderable->export_for_template($renderer);
        return ['html' => $renderer->render_earned_badges_data($data, $setid)];
    }

    public static function get_earned_badges_html_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered HTML'),
        ]);
    }

    public static function get_earned_badges_pdf_url_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function get_earned_badges_pdf_url($userid) {
        $params = self::validate_parameters(self::get_earned_badges_pdf_url_parameters(), ['userid' => $userid]);
        self::validate_context(context_system::instance());
        require_capability('local/rucksack:manageconfigs', context_system::instance());

        global $CFG;
        $hash = local_rucksack_encrypt($params['userid'], 0);
        return [
            'userhash' => $hash,
            'pdfurl' => $CFG->wwwroot . '/local/rucksack/pdf.php?user=' . urlencode($hash),
        ];
    }

    public static function get_earned_badges_pdf_url_returns() {
        return new external_single_structure([
            'userhash' => new external_value(PARAM_TEXT, 'Encrypted user token'),
            'pdfurl' => new external_value(PARAM_URL, 'PDF URL'),
        ]);
    }
}
