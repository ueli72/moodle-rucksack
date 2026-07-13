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

namespace local_rucksack\output;

defined('MOODLE_INTERNAL') || die;

require_once("{$GLOBALS['CFG']->libdir}/badgeslib.php");

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Renderable for earned badges with competency hierarchy.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class earned_badges implements renderable, templatable {

    /** @var int */
    protected $userid;

    /** @var stdClass|null */
    protected $config;

    /** @var array */
    protected $configbadges;

    /** @var array */
    protected $configcomps;

    /**
     * Constructor.
     *
     * @param int $userid
     */
    public function __construct($userid) {
        $this->userid = (int)$userid;
        $this->config = local_rucksack_get_user_config($this->userid);
        if ($this->config) {
            $this->configbadges = local_rucksack_get_config_badges($this->config->id);
            $this->configcomps = local_rucksack_get_config_competencies($this->config->id);
        } else {
            $this->configbadges = [];
            $this->configcomps = [];
        }
    }

    /**
     * Export data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $CFG;

        $data = new stdClass();
        $user = $DB->get_record('user', ['id' => $this->userid]);
        if ($user) {
            $data->firstname = $user->firstname;
            $data->lastname = $user->lastname;
            $data->username = fullname($user);
        }
        $data->date = date('d.m.Y');
        $data->wwwroot = $CFG->wwwroot;
        $data->userhash = local_rucksack_encrypt($this->userid);

        $logourl = local_rucksack_get_logo_url();
        if ($logourl) {
            $data->logourl = $logourl->out();
        }

        $earned = badges_get_user_badges($this->userid);
        if (empty($earned)) {
            $data->nodata = true;
            return $data;
        }

        // Build badge data and collect competency ids.
        $badgeinfos = [];
        $competencyids = [];
        foreach ($earned as $badge) {
            $badgeid = (int)$badge->id;

            // Apply config filter at badge level if configured.
            if (isset($this->configbadges[$badgeid]) && empty($this->configbadges[$badgeid]->visible)) {
                continue;
            }

            $competencyidsforbadge = $this->get_badge_competency_ids($badgeid);
            if (empty($competencyidsforbadge)) {
                $competencyidsforbadge = [0]; // Free badges / no competency.
            }

            foreach ($competencyidsforbadge as $compid) {
                $competencyids[$compid] = $compid;
            }

            $badgeobj = new \core_badges\badge($badgeid);
            $context = $badgeobj->get_context();
            $imageurl = \moodle_url::make_pluginfile_url(
                $context->id,
                'badges',
                'badgeimage',
                $badgeid,
                '/',
                'f1',
                false
            );

            $badgeinfos[] = [
                'badgeid' => $badgeid,
                'badgename' => $badge->name,
                'description' => local_rucksack_format_description($badge->description),
                'badgeurl' => $imageurl->out(),
                'competencyids' => $competencyidsforbadge,
                'dateissued' => $badge->dateissued,
                'sortorder' => $this->configbadges[$badgeid]->sortorder ?? 0,
            ];
        }

        // Build competency hierarchy.
        $competencies = $this->get_competency_hierarchy($competencyids);

        // Group badges by competency.
        $tree = [];
        foreach ($badgeinfos as $binfo) {
            foreach ($binfo['competencyids'] as $compid) {
                $tree = $this->add_badge_to_competency($tree, $competencies, $compid, $binfo);
            }
        }

        // Sort competencies and badges.
        $tree = $this->sort_tree($tree);

        $data->plans = $this->build_template_data($tree);
        $data->haspdf = true;
        $data->pdfurl = $CFG->wwwroot . '/local/rucksack/pdf.php?user=' . urlencode($data->userhash);
        $data->showpdfbutton = true;

        // Prepare plan-level flattened display used by both screen and PDF.
        $plans = [];
        foreach ($data->plans as $plan) {
            $plans[] = $this->prepare_plan_for_display($plan);
        }
        $data->plans = $plans;

        return $data;
    }

    /**
     * Get competency IDs linked to a badge via criteria.
     *
     * @param int $badgeid
     * @return array
     */
    protected function get_badge_competency_ids($badgeid) {
        global $DB;
        $sql = "SELECT bcp.value
                  FROM {badge_criteria} bc
                  JOIN {badge_criteria_param} bcp ON bcp.critid = bc.id
                 WHERE bc.badgeid = ?
                   AND bc.criteriatype = ?";
        return $DB->get_fieldset_sql($sql, [$badgeid, BADGE_CRITERIA_TYPE_COMPETENCY]);
    }

    /**
     * Get competency hierarchy data for the given competency IDs.
     *
     * @param array $competencyids
     * @return array keyed by competency id
     */
    protected function get_competency_hierarchy($competencyids) {
        global $DB;
        if (empty($competencyids) || (count($competencyids) === 1 && isset($competencyids[0]))) {
            return [];
        }

        // Collect all parent IDs from paths.
        $allids = [];
        foreach ($competencyids as $id) {
            if ($id <= 0) {
                continue;
            }
            $allids[$id] = $id;
        }

        if (empty($allids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($allids), SQL_PARAMS_NAMED);
        $sql = "SELECT id, shortname, description, parentid, path, sortorder, competencyframeworkid as frameworkid
                  FROM {competency}
                 WHERE id $insql";
        $records = $DB->get_records_sql($sql, $inparams);

        // Add parent competencies.
        $parents = [];
        foreach ($records as $record) {
            $pathids = array_filter(explode('/', trim($record->path, '/')));
            foreach ($pathids as $pid) {
                $pid = (int)$pid;
                if ($pid > 0 && !isset($records[$pid])) {
                    $parents[$pid] = $pid;
                }
            }
        }

        if (!empty($parents)) {
            list($insql2, $inparams2) = $DB->get_in_or_equal(array_keys($parents), SQL_PARAMS_NAMED, 'p');
            $sql2 = "SELECT id, shortname, description, parentid, path, sortorder, competencyframeworkid as frameworkid
                       FROM {competency}
                      WHERE id $insql2";
            $parentrecords = $DB->get_records_sql($sql2, $inparams2);
            foreach ($parentrecords as $pid => $prec) {
                $records[$pid] = $prec;
            }
        }

        // Add framework names.
        $frameworkids = [];
        foreach ($records as $record) {
            if ($record->frameworkid) {
                $frameworkids[$record->frameworkid] = $record->frameworkid;
            }
        }
        if (!empty($frameworkids)) {
            $frameworks = $DB->get_records_list('competency_framework', 'id', array_keys($frameworkids), '', 'id, shortname');
        } else {
            $frameworks = [];
        }

        $result = [];
        foreach ($records as $record) {
            $result[$record->id] = [
                'id' => $record->id,
                'shortname' => $record->shortname,
                'description' => $record->description,
                'parentid' => $record->parentid,
                'path' => $record->path,
                'sortorder' => $record->sortorder,
                'frameworkid' => $record->frameworkid,
                'frameworkname' => $frameworks[$record->frameworkid]->shortname ?? '',
                'configsortorder' => $this->configcomps[$record->id]->sortorder ?? $record->sortorder,
                'configvisible' => $this->configcomps[$record->id]->visible ?? 1,
            ];
        }

        return $result;
    }

    /**
     * Add a badge to the competency tree structure.
     *
     * @param array $tree
     * @param array $competencies
     * @param int $compid
     * @param array $binfo
     * @return array
     */
    protected function add_badge_to_competency($tree, $competencies, $compid, $binfo) {
        if ($compid <= 0 || !isset($competencies[$compid])) {
            // Free badge or no competency info.
            if (!isset($tree['free'])) {
                $tree['free'] = [
                    'frameworkid' => 0,
                    'frameworkname' => get_string('otherbadges', 'local_rucksack'),
                    'competencies' => [],
                ];
            }
            $tree['free']['badges'][$binfo['badgeid']] = $binfo;
            return $tree;
        }

        $comp = $competencies[$compid];
        $frameworkid = $comp['frameworkid'] ?: 0;
        $frameworkname = $comp['frameworkname'] ?: get_string('otherbadges', 'local_rucksack');

        if (!isset($tree[$frameworkid])) {
            $tree[$frameworkid] = [
                'frameworkid' => $frameworkid,
                'frameworkname' => $frameworkname,
                'competencies' => [],
            ];
        }

        // Build full path from root to competency.
        $pathids = array_filter(explode('/', trim($comp['path'], '/')));
        $current = &$tree[$frameworkid]['competencies'];
        foreach ($pathids as $pid) {
            $pid = (int)$pid;
            if (!isset($current[$pid])) {
                $cinfo = $competencies[$pid] ?? [
                    'id' => $pid,
                    'shortname' => get_string('unknowncompetency', 'local_rucksack'),
                    'description' => '',
                    'parentid' => 0,
                    'path' => '/',
                    'sortorder' => 0,
                    'frameworkid' => $frameworkid,
                    'frameworkname' => $frameworkname,
                    'configsortorder' => $this->configcomps[$pid]->sortorder ?? 0,
                    'configvisible' => $this->configcomps[$pid]->visible ?? 1,
                ];
                $current[$pid] = [
                    'info' => $cinfo,
                    'children' => [],
                    'badges' => [],
                ];
            }
            $current = &$current[$pid]['children'];
        }

        // Add the target competency itself.
        if (!isset($current[$compid])) {
            $current[$compid] = [
                'info' => $comp,
                'children' => [],
                'badges' => [],
            ];
        }
        $current[$compid]['badges'][$binfo['badgeid']] = $binfo;

        return $tree;
    }

    /**
     * Sort the competency tree.
     *
     * @param array $tree
     * @return array
     */
    protected function sort_tree($tree) {
        // Sort frameworks by name.
        uasort($tree, function ($a, $b) {
            return strcmp($a['frameworkname'], $b['frameworkname']);
        });

        foreach ($tree as $key => $framework) {
            $tree[$key]['competencies'] = $this->sort_competencies($framework['competencies']);
        }

        return $tree;
    }

    /**
     * Sort competencies recursively.
     *
     * @param array $competencies
     * @return array
     */
    protected function sort_competencies($competencies) {
        uasort($competencies, function ($a, $b) {
            $oa = $a['info']['configsortorder'];
            $ob = $b['info']['configsortorder'];
            if ($oa != $ob) {
                return $oa <=> $ob;
            }
            return strcmp($a['info']['shortname'], $b['info']['shortname']);
        });

        foreach ($competencies as $id => $comp) {
            // Sort badges within this competency.
            uasort($competencies[$id]['badges'], function ($a, $b) {
                if ($a['sortorder'] != $b['sortorder']) {
                    return $a['sortorder'] <=> $b['sortorder'];
                }
                return strcmp($a['badgename'], $b['badgename']);
            });

            if (!empty($comp['children'])) {
                $competencies[$id]['children'] = $this->sort_competencies($comp['children']);
            }
        }

        return $competencies;
    }

    /**
     * Build the flat template data structure from the tree.
     *
     * @param array $tree
     * @return array
     */
    protected function build_template_data($tree) {
        $plans = [];
        foreach ($tree as $framework) {
            $plan = [
                'plan' => $framework['frameworkname'],
                'planid' => 'framework_' . $framework['frameworkid'],
                'sub0' => [],
            ];
            if (!empty($framework['badges'])) {
                $plan['planbadges'] = array_values($framework['badges']);
            }
            $plan['sub0'] = $this->build_competency_level($framework['competencies'], 0);
            if (!empty($plan['sub0']) || !empty($plan['planbadges'])) {
                $plans[] = $plan;
            }
        }
        return $plans;
    }

    /**
     * Build one level of competency data for the template.
     *
     * @param array $competencies
     * @param int $depth
     * @return array
     */
    protected function build_competency_level($competencies, $depth) {
        $items = [];
        foreach ($competencies as $comp) {
            if (empty($comp['info']['configvisible'])) {
                continue;
            }

            $item = [
                'subname' => $comp['info']['shortname'],
                'subid' => $comp['info']['id'],
                'description' => $comp['info']['description'],
            ];

            if (!empty($comp['badges'])) {
                $item['badges' . $depth] = array_values($comp['badges']);
            }

            if (!empty($comp['children'])) {
                $subdepth = $depth + 1;
                $item['sub' . $subdepth] = $this->build_competency_level($comp['children'], $subdepth);
            }

            $items[] = $item;
        }
        return $items;
    }

    /**
     * Export data in a flat structure suitable for PDF rendering.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_pdf(renderer_base $output) {
        return $this->export_for_template($output);
    }

    /**
     * Prepare a plan for display: flatten competency hierarchy and build a single
     * dense badge grid for the whole plan.
     *
     * @param array $plan
     * @return stdClass
     */
    protected function prepare_plan_for_display($plan) {
        $planobj = (object) $plan;
        $planobj->sections = $this->flatten_competencies($plan['sub0'] ?? [], 1);
        return $planobj;
    }

    /**
     * Flatten nested competencies into a list with level info.
     *
     * @param array $competencies
     * @param int $level
     * @return array
     */
    protected function flatten_competencies($competencies, $level) {
        $sections = [];
        foreach ($competencies as $comp) {
            $ownbadges = [];
            for ($i = 0; $i <= 5; $i++) {
                $key = 'badges' . $i;
                if (!empty($comp[$key])) {
                    $ownbadges = array_merge($ownbadges, $comp[$key]);
                }
            }

            // Determine if this section has children.
            $haschildren = false;
            for ($i = 1; $i <= 5; $i++) {
                $key = 'sub' . $i;
                if (!empty($comp[$key])) {
                    $haschildren = true;
                    break;
                }
            }

            // Recurse into children.
            $childsections = [];
            if ($haschildren) {
                for ($i = 1; $i <= 5; $i++) {
                    $key = 'sub' . $i;
                    if (!empty($comp[$key])) {
                        $childsections = array_merge($childsections, $this->flatten_competencies($comp[$key], $level + 1));
                    }
                }
            }

            // Collect badges from immediate leaf children and hoist them to this
            // section so badges within the same category appear side by side.
            $leafbadges = [];
            $seen = [];
            foreach ($childsections as $child) {
                if (empty($child['haschildren']) && !empty($child['badgerows'])) {
                    foreach ($child['badgerows'] as $row) {
                        foreach ($row as $badge) {
                            $badgeid = $badge['badgeid'] ?? null;
                            if ($badgeid === null || isset($seen[$badgeid])) {
                                continue;
                            }
                            $seen[$badgeid] = true;
                            $leafbadges[] = $badge;
                        }
                    }
                }
            }

            // Remove badges from leaf children so they are not duplicated, and
            // drop child sections that have become empty (no badges and no children).
            $filteredchildsections = [];
            foreach ($childsections as $child) {
                if (!empty($child['haschildren'])) {
                    $filteredchildsections[] = $child;
                } else {
                    if (!empty($child['badgerows'])) {
                        unset($child['badgerows']);
                        unset($child['hasbadges']);
                    }
                    if (!empty($child['hasbadges']) || !empty($child['haschildren'])) {
                        $filteredchildsections[] = $child;
                    }
                }
            }
            $childsections = $filteredchildsections;

            $badges = array_merge($ownbadges, $leafbadges);

            $section = [
                'level' => $level,
                'name' => $comp['subname'],
                'description' => $comp['description'] ?? '',
                'haschildren' => $haschildren,
            ];
            if (!empty($badges)) {
                $section['badgerows'] = $this->build_badge_rows($badges);
                $section['hasbadges'] = true;

                // Hide the title of a leaf section if it is redundant with the
                // badge name(s) it contains.
                if (!$haschildren) {
                    $section['hidetitle'] = $this->is_title_redundant($comp['subname'], $badges);
                }
            }
            // Keep sections that have badges or that have children (group headers).
            if (!empty($badges) || !empty($childsections)) {
                $sections[] = $section;
            }
            $sections = array_merge($sections, $childsections);
        }
        return $sections;
    }

    /**
     * Determine if a competency title is redundant with the badge names it contains.
     *
     * A title is considered redundant if it exactly matches at least one badge name
     * and there is only one badge in the section.
     *
     * @param string $title
     * @param array $badges
     * @return bool
     */
    protected function is_title_redundant($title, $badges) {
        if (count($badges) !== 1) {
            return false;
        }
        return trim($title) === trim($badges[0]['badgename'] ?? '');
    }

    /**
     * Build rows of two badges each for table rendering.
     *
     * @param array $badges
     * @return array
     */
    protected function build_badge_rows($badges) {
        $rows = [];
        $badges = array_values($badges);
        for ($i = 0; $i < count($badges); $i += 2) {
            $row = [$badges[$i]];
            if (isset($badges[$i + 1])) {
                $row[] = $badges[$i + 1];
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
