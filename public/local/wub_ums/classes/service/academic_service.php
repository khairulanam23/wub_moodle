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

namespace local_wub_ums\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use core_course_category;
use local_wub_ums\repository\academic_repository;

/**
 * Service for managing WUB Academic Hierarchy and Moodle Category Tree Integration.
 *
 * Integrates University -> Faculty -> Department -> Program -> Course -> Offering -> Section
 * using Moodle's native core_course_category APIs in a 100% idempotent manner.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class academic_service {

    /** @var academic_repository */
    protected academic_repository $repository;

    /**
     * Constructor.
     *
     * @param academic_repository|null $repository
     */
    public function __construct(?academic_repository $repository = null) {
        $this->repository = $repository ?? new academic_repository();
    }

    /**
     * Synchronize and align WUB Academic Hierarchy with Moodle Course Categories.
     *
     * Idempotent Operation:
     * 1. Maps each Faculty to a top-level Moodle Category (parent = 0).
     * 2. Maps each Department to a child Moodle Category under its Faculty Category.
     * 3. Preserves all existing course assignments, enrolments, and grades.
     *
     * @return array Summary of alignment actions taken.
     */
    public function sync_hierarchy_to_moodle_categories(): array {
        global $DB, $CFG;

        $report = [
            'faculties_processed' => 0,
            'faculties_mapped' => 0,
            'departments_processed' => 0,
            'departments_reparented' => 0,
            'departments_aligned' => 0,
            'errors' => [],
        ];

        // 1. Process and Align Faculties (Top-Level Categories, parent = 0)
        $faculties = $this->repository->get_faculties();
        $facultyCatMap = []; // [faculty_id => moodle_category_id]

        foreach ($faculties as $faculty) {
            $report['faculties_processed']++;
            $catId = (int)$faculty->category_id;
            $cat = null;

            if ($catId > 0) {
                $cat = $this->repository->get_moodle_category($catId);
            }

            if (!$cat) {
                // Try locating existing category by name
                $cat = $this->repository->find_moodle_category_by_name($faculty->name, 0);
            }

            if (!$cat) {
                // Create top-level category via Moodle's native API
                $newCatData = (object)[
                    'name' => $faculty->name,
                    'parent' => 0,
                    'idnumber' => !empty($faculty->code) ? $faculty->code : 'FAC-' . $faculty->id,
                    'description' => 'Faculty Category for ' . $faculty->name,
                    'descriptionformat' => FORMAT_HTML,
                ];
                $createdCat = core_course_category::create($newCatData);
                $catId = (int)$createdCat->id;
                $this->repository->update_faculty_category((int)$faculty->id, $catId);
                $report['faculties_mapped']++;
            } else {
                $catId = (int)$cat->id;
                if ($catId !== (int)$faculty->category_id) {
                    $this->repository->update_faculty_category((int)$faculty->id, $catId);
                    $report['faculties_mapped']++;
                }
                // Ensure faculty category is at top-level (parent = 0)
                if ((int)$cat->parent !== 0) {
                    $catObj = core_course_category::get($catId);
                    $catObj->change_parent(0);
                }
            }

            $facultyCatMap[(int)$faculty->id] = $catId;
        }

        // 2. Process and Align Departments (Child Categories under their Faculty Category)
        $departments = $this->repository->get_departments();

        foreach ($departments as $dept) {
            $report['departments_processed']++;
            $deptCatId = (int)$dept->category_id;
            $facultyId = (int)$dept->faculty_id;
            $targetFacultyCatId = $facultyCatMap[$facultyId] ?? 0;

            if ($deptCatId <= 0) {
                $report['errors'][] = "Department ID {$dept->id} ({$dept->name}) has no assigned category_id.";
                continue;
            }

            $deptCat = $this->repository->get_moodle_category($deptCatId);
            if (!$deptCat) {
                $report['errors'][] = "Moodle Category ID {$deptCatId} for Department {$dept->name} does not exist.";
                continue;
            }

            // Verify if department category parent is already the faculty category
            if ($targetFacultyCatId > 0 && (int)$deptCat->parent !== $targetFacultyCatId) {
                $catObj = core_course_category::get($deptCatId);
                $catObj->change_parent($targetFacultyCatId);
                $report['departments_reparented']++;
            } else {
                $report['departments_aligned']++;
            }
        }

        return $report;
    }

    /**
     * Retrieve the comprehensive Academic Hierarchy Tree.
     *
     * @return array
     */
    public function get_full_academic_tree(): array {
        $tree = [];
        $faculties = $this->repository->get_faculties();
        $periods = $this->repository->get_academic_periods();

        foreach ($faculties as $fac) {
            $facNode = [
                'id' => (int)$fac->id,
                'name' => $fac->name,
                'code' => $fac->code,
                'category_id' => (int)$fac->category_id,
                'departments' => [],
            ];

            $departments = $this->repository->get_departments((int)$fac->id);
            foreach ($departments as $dept) {
                $deptNode = [
                    'id' => (int)$dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'category_id' => (int)$dept->category_id,
                    'programs' => [],
                    'courses' => [],
                ];

                // Attach programs
                $programs = $this->repository->get_programs((int)$dept->id);
                foreach ($programs as $prog) {
                    $deptNode['programs'][] = [
                        'id' => (int)$prog->id,
                        'name' => $prog->name,
                        'code' => $prog->code,
                        'level' => $prog->level,
                    ];
                }

                // Attach courses currently in department category
                if (!empty($dept->category_id)) {
                    $courses = $this->repository->get_courses_by_category((int)$dept->category_id);
                    foreach ($courses as $c) {
                        $offerings = $this->repository->get_course_offerings((int)$c->id);
                        $offeringNodes = [];
                        foreach ($offerings as $off) {
                            $sections = $this->repository->get_sections_by_offering((int)$off->id);
                            $offeringNodes[] = [
                                'id' => (int)$off->id,
                                'academic_year' => $off->academic_year,
                                'semester' => $off->semester,
                                'period_id' => (int)$off->period_id,
                                'sections' => array_values($sections),
                            ];
                        }

                        $deptNode['courses'][] = [
                            'id' => (int)$c->id,
                            'fullname' => $c->fullname,
                            'shortname' => $c->shortname,
                            'idnumber' => $c->idnumber,
                            'offerings' => $offeringNodes,
                        ];
                    }
                }

                $facNode['departments'][] = $deptNode;
            }

            $tree[] = $facNode;
        }

        return $tree;
    }
}
