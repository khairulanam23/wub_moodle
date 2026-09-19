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
 * UMS-driven bulk enrolment workspace.
 *
 * Data flow: UMS programs -> batches -> paginated roster (server-side identity matching) -> Moodle course
 * selection -> verification matrix -> bounded execution chunks (<= 50 users / course / transaction).
 *
 * @module     local_bulk_enrolment/workspace
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/notification',
    'core/modal_save_cancel',
    'core/modal_events'
], function($, Ajax, Templates, Notification, ModalSaveCancel, ModalEvents) {
    'use strict';

    var CHUNK_SIZE = 50;
    var PER_PAGE = 25;

    var state = {
        programs: [],
        selectedPrograms: {},      // id -> {id, title, short}
        batchesByProgram: {},      // id -> [batch]
        selectedBatches: {},       // program id -> {title: true}
        roster: null,
        selectedStudents: {},      // key -> {key, reg_id, full_name, batch}
        filters: {search: '', batch: '', match: ''},
        page: 1,
        rosterSeq: 0,
        matrix: null,
        executing: false,
        results: null
    };

    /**
     * Escape a string for safe HTML insertion.
     *
     * @param {String} text
     * @return {String}
     */
    var esc = function(text) {
        return $('<div>').text(text === undefined || text === null ? '' : String(text)).html();
    };

    /**
     * Show a dismissible notice.
     *
     * @param {String} type success|info|warning|danger
     * @param {String} message
     * @param {String} [code]
     */
    var notice = function(type, message, code) {
        var html = '<div class="alert alert-' + esc(type) + ' alert-dismissible fade show py-2" role="alert">' +
            (code ? '<span class="badge badge-light border me-2">' + esc(code) + '</span>' : '') + esc(message) +
            '<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        $('#be-notices').append(html);
    };

    /**
     * Clear notices.
     */
    var clearNotices = function() {
        $('#be-notices').empty();
    };

    /**
     * Call one external function and return a promise.
     *
     * @param {String} name
     * @param {Object} args
     * @return {Promise}
     */
    var call = function(name, args) {
        return Ajax.call([{methodname: name, args: args}])[0];
    };

    /**
     * Report a failed AJAX call (exception from Moodle) without exposing traces.
     *
     * @param {Object} err
     */
    var reportException = function(err) {
        var message = (err && err.message) ? err.message : 'Request failed.';
        notice('danger', message, err && err.errorcode ? err.errorcode : 'MOODLE');
    };

    /**
     * Number of keys in an object.
     *
     * @param {Object} obj
     * @return {Number}
     */
    var size = function(obj) {
        return Object.keys(obj).length;
    };

    // ------------------------------------------------------------------ Programs.

    /**
     * Load programs from UMS.
     *
     * @param {Boolean} refresh
     */
    var loadPrograms = function(refresh) {
        var $list = $('#be-program-list');
        $list.attr('data-state', 'loading');
        call('local_bulk_enrolment_get_programs', {refresh: !!refresh}).then(function(res) {
            if (!res.ok) {
                $list.attr('data-state', 'error').html('<div class="text-center text-danger py-4 small">' + esc(res.error_message) + '</div>');
                notice('danger', res.error_message, res.error_code);
                return;
            }
            state.programs = res.programs;
            var ctx = {programs: res.programs.map(function(p) {
                return $.extend({}, p, {selected: !!state.selectedPrograms[p.id]});
            })};
            return Templates.render('local_bulk_enrolment/ws_program_list', ctx).then(function(html) {
                $list.attr('data-state', 'ready').html(html);
                filterPrograms();
                return null;
            });
        }).catch(reportException);
    };

    /**
     * Client-side program search.
     */
    var filterPrograms = function() {
        var term = $('#be-program-search').val().toLowerCase().trim();
        $('#be-program-list .be-item').each(function() {
            var hay = ($(this).data('search') || '').toString().toLowerCase();
            $(this).toggleClass('d-none', !(term === '' || hay.indexOf(term) !== -1));
        });
    };

    /**
     * Sync selected programs from checkboxes, then reload batches and roster.
     */
    var onProgramsChanged = function() {
        var selected = {};
        $('#be-program-list .be-program-checkbox:checked').each(function() {
            selected[$(this).val()] = {id: $(this).val(), title: $(this).data('title'), short: $(this).data('short')};
        });
        state.selectedPrograms = selected;
        Object.keys(state.selectedBatches).forEach(function(pid) {
            if (!selected[pid]) {
                delete state.selectedBatches[pid];
            }
        });
        $('#be-program-count').text(size(selected) + ' selected');
        loadBatches();
        state.page = 1;
        scheduleRoster();
    };

    // ------------------------------------------------------------------ Batches.

    /**
     * Load batches for the selected programs.
     */
    var loadBatches = function() {
        var ids = Object.keys(state.selectedPrograms);
        var $list = $('#be-batch-list');
        if (ids.length === 0) {
            state.batchesByProgram = {};
            $list.attr('data-state', 'idle').html('<div class="text-center text-muted py-4 small">Select a program to list its batches.</div>');
            $('#be-batch-count').text('0 selected');
            return;
        }
        $list.attr('data-state', 'loading');
        call('local_bulk_enrolment_get_batches', {programids: ids, refresh: false}).then(function(res) {
            if (!res.ok) {
                $list.attr('data-state', 'error').html('<div class="text-center text-danger py-4 small">' + esc(res.error_message) + '</div>');
                notice('danger', res.error_message, res.error_code);
                return;
            }
            state.batchesByProgram = {};
            res.batches.forEach(function(b) {
                if (!state.batchesByProgram[b.program_id]) {
                    state.batchesByProgram[b.program_id] = [];
                }
                state.batchesByProgram[b.program_id].push(b);
            });
            renderBatches();
            return null;
        }).catch(reportException);
    };

    /**
     * Render the batch list from state.
     */
    var renderBatches = function() {
        var groups = Object.keys(state.selectedPrograms).map(function(pid) {
            var sel = state.selectedBatches[pid] || {};
            var batches = (state.batchesByProgram[pid] || []).map(function(b) {
                return $.extend({}, b, {selected: !!sel[b.title]});
            });
            return {
                program_id: pid,
                program_title: state.selectedPrograms[pid].short || state.selectedPrograms[pid].title,
                allselected: size(sel) === 0,
                count: batches.length,
                batches: batches
            };
        });
        Templates.render('local_bulk_enrolment/ws_batch_list', {groups: groups}).then(function(html) {
            $('#be-batch-list').attr('data-state', 'ready').html(html);
            updateBatchCount();
            return null;
        }).catch(reportException);
    };

    /**
     * Update the selected-batch badge.
     */
    var updateBatchCount = function() {
        var n = 0;
        Object.keys(state.selectedBatches).forEach(function(pid) {
            n += size(state.selectedBatches[pid]);
        });
        $('#be-batch-count').text(n + ' selected');
    };

    /**
     * Handle batch checkbox changes.
     *
     * @param {Object} $cb
     */
    var onBatchToggled = function($cb) {
        var pid = String($cb.data('program'));
        var title = $cb.val();
        if (!state.selectedBatches[pid]) {
            state.selectedBatches[pid] = {};
        }
        if ($cb.prop('checked')) {
            state.selectedBatches[pid][title] = true;
        } else {
            delete state.selectedBatches[pid][title];
        }
        var $group = $cb.closest('.be-batch-group');
        $group.find('.be-batch-all').prop('checked', size(state.selectedBatches[pid]) === 0);
        updateBatchCount();
        state.page = 1;
        scheduleRoster();
    };

    /**
     * "All batches" for a program: clears the individual selection.
     *
     * @param {Object} $cb
     */
    var onBatchAllToggled = function($cb) {
        var pid = String($cb.data('program'));
        state.selectedBatches[pid] = {};
        $cb.closest('.be-batch-group').find('.be-batch-checkbox').prop('checked', false);
        $cb.prop('checked', true);
        updateBatchCount();
        state.page = 1;
        scheduleRoster();
    };

    // ------------------------------------------------------------------ Roster.

    var rosterTimer = null;

    /**
     * Debounced roster reload.
     */
    var scheduleRoster = function() {
        if (rosterTimer) {
            clearTimeout(rosterTimer);
        }
        rosterTimer = setTimeout(function() {
            loadRoster(false);
        }, 300);
    };

    /**
     * Build the selections argument for get_roster.
     *
     * @return {Array}
     */
    var selections = function() {
        return Object.keys(state.selectedPrograms).map(function(pid) {
            return {program_id: pid, batch_titles: Object.keys(state.selectedBatches[pid] || {})};
        });
    };

    /**
     * Load one roster page from UMS/Moodle.
     *
     * @param {Boolean} refresh Bypass the roster cache.
     */
    var loadRoster = function(refresh) {
        var $roster = $('#be-roster');
        var sels = selections();
        if (sels.length === 0) {
            state.roster = null;
            $roster.attr('data-state', 'idle').html('<div class="text-center text-muted py-5">Select at least one program to load students from UMS.</div>');
            $('#be-pagination').empty();
            updateRosterBadges();
            updateVerifyState();
            return;
        }
        var seq = ++state.rosterSeq;
        $roster.attr('data-state', 'loading').html('<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading students from UMS…</div>');
        call('local_bulk_enrolment_get_roster', {
            selections: sels,
            search: state.filters.search,
            batch: state.filters.batch,
            match: state.filters.match,
            program: '',
            page: state.page,
            perpage: PER_PAGE,
            refresh: !!refresh
        }).then(function(res) {
            if (seq !== state.rosterSeq) {
                return null; // Stale response.
            }
            if (!res.ok) {
                state.roster = null;
                $roster.attr('data-state', 'error').html('<div class="alert alert-danger mb-0"><strong>' + esc(res.error_code) + '</strong> — ' + esc(res.error_message) + '</div>');
                $('#be-pagination').empty();
                updateRosterBadges();
                updateVerifyState();
                return null;
            }
            state.roster = res;
            res.warnings.forEach(function(w) {
                notice('warning', w);
            });
            renderBatchFilter(res.facets.batches);
            updateRosterBadges();
            if (res.total === 0) {
                var msg = res.total_unfiltered === 0 ? 'UMS returned no students for this selection.' : 'No students match the current filter.';
                $roster.attr('data-state', 'empty').html('<div class="text-center text-muted py-5">' + esc(msg) + '</div>');
                $('#be-pagination').empty();
                updateVerifyState();
                return null;
            }
            var rows = res.rows.map(function(r) {
                return $.extend({}, r, {
                    selected: !!state.selectedStudents[r.key],
                    is_matched: r.match_status === 'MATCHED',
                    is_not_found: r.match_status === 'NOT_FOUND',
                    is_ambiguous: r.match_status === 'AMBIGUOUS'
                });
            });
            return Templates.render('local_bulk_enrolment/ws_roster_table', {rows: rows}).then(function(html) {
                if (seq !== state.rosterSeq) {
                    return null;
                }
                $roster.attr('data-state', 'ready').html(html);
                renderPagination(res.page, res.pages, res.total);
                updateVerifyState();
                return null;
            });
        }).catch(function(err) {
            if (seq === state.rosterSeq) {
                $roster.attr('data-state', 'error').html('<div class="alert alert-danger mb-0">' + esc(err.message || 'Request failed.') + '</div>');
                reportException(err);
            }
        });
    };

    /**
     * Populate the batch filter with facets.
     *
     * @param {Array} facets
     */
    var renderBatchFilter = function(facets) {
        var $sel = $('#be-roster-batch-filter');
        var current = $sel.val();
        var html = '<option value="">All batches</option>';
        facets.forEach(function(f) {
            html += '<option value="' + esc(f.title) + '">' + esc(f.title) + ' (' + f.count + ')</option>';
        });
        $sel.html(html).val(current);
        if ($sel.val() === null) {
            $sel.val('');
        }
    };

    /**
     * Update roster summary badges.
     */
    var updateRosterBadges = function() {
        var r = state.roster;
        $('#be-roster-total').text(r ? r.total_unfiltered + ' students' : '0');
        $('#be-roster-matched').text(r ? r.facets.matched : 0);
        $('#be-roster-notfound').text(r ? r.facets.not_found : 0);
        $('#be-roster-ambiguous').text(r ? r.facets.ambiguous : 0);
        $('#be-student-count').text(size(state.selectedStudents) + ' selected');
    };

    /**
     * Render pagination controls.
     *
     * @param {Number} page
     * @param {Number} pages
     * @param {Number} total
     */
    var renderPagination = function(page, pages, total) {
        if (pages <= 1) {
            $('#be-pagination').html('<span class="small text-muted">' + total + ' shown</span>');
            return;
        }
        var html = '<ul class="pagination pagination-sm mb-0">';
        var add = function(p, label, disabled, active) {
            html += '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + p + '">' + label + '</a></li>';
        };
        add(page - 1, '&laquo;', page <= 1, false);
        var start = Math.max(1, page - 2);
        var end = Math.min(pages, start + 4);
        start = Math.max(1, end - 4);
        for (var p = start; p <= end; p++) {
            add(p, p, false, p === page);
        }
        add(page + 1, '&raquo;', page >= pages, false);
        html += '</ul>';
        $('#be-pagination').html(html);
    };

    /**
     * Toggle one student in the selection.
     *
     * @param {Object} $cb
     */
    var onStudentToggled = function($cb) {
        var key = $cb.val();
        if ($cb.prop('checked')) {
            state.selectedStudents[key] = {key: key, reg_id: $cb.data('regid'), full_name: $cb.data('name'), batch: $cb.data('batch')};
        } else {
            delete state.selectedStudents[key];
        }
        updateRosterBadges();
        updateVerifyState();
    };

    // ------------------------------------------------------------------ Courses and options.

    /**
     * Client-side course filter.
     */
    var filterCourses = function() {
        var term = $('#be-course-search').val().toLowerCase().trim();
        var cat = $('#be-course-category').val();
        $('#be-course-list .be-item').each(function() {
            var $i = $(this);
            var hay = (($i.data('name') || '') + ' ' + ($i.data('shortname') || '')).toLowerCase();
            var ok = (term === '' || hay.indexOf(term) !== -1) && (cat === '' || String($i.data('category')) === cat);
            $i.toggleClass('d-none', !ok);
        });
    };

    /**
     * Selected course ids.
     *
     * @return {Array}
     */
    var selectedCourses = function() {
        return $('#be-course-list .be-course-checkbox:checked').map(function() {
            return parseInt($(this).val(), 10);
        }).get();
    };

    /**
     * Group options from the form.
     *
     * @return {Object} {mode, name, create}
     */
    var groupOptions = function() {
        return {
            mode: $('input[name="be-group-mode"]:checked').val(),
            name: $('#be-group-name').val().trim(),
            create: $('#be-group-create').prop('checked')
        };
    };

    /**
     * Scroll smoothly to target element if user does not prefer reduced motion.
     *
     * @param {Object} $el
     */
    var scrollToElement = function($el) {
        if (!$el || !$el.length) {
            return;
        }
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) {
            return;
        }
        $('html, body').animate({scrollTop: $el.offset().top - 90}, 300);
    };

    /**
     * Update the visual pipeline stepper states.
     */
    var updatePipelineStepper = function() {
        var hasStudents = size(state.selectedStudents) > 0;
        var hasCourses = selectedCourses().length > 0;
        var isVerifyingOrDone = state.matrix !== null || state.results !== null;

        $('#be-step-roster').toggleClass('active', true);
        $('#be-step-review').toggleClass('active', hasStudents);
        $('#be-step-courses').toggleClass('active', hasStudents && hasCourses);
        $('#be-step-verify').toggleClass('active', isVerifyingOrDone);
    };

    /**
     * Enable/disable the verify button.
     */
    var updateVerifyState = function() {
        var ok = size(state.selectedStudents) > 0 && selectedCourses().length > 0 && !state.executing;
        $('#be-verify').prop('disabled', !ok);
        $('#be-course-count').text(selectedCourses().length + ' selected');
        updatePipelineStepper();
    };

    // ------------------------------------------------------------------ Verification.

    /**
     * Compute the verification matrix.
     */
    var verify = function() {
        clearNotices();
        var students = Object.keys(state.selectedStudents).map(function(k) {
            var s = state.selectedStudents[k];
            return {username: s.key, reg_id: s.reg_id || '', full_name: s.full_name || ''};
        });
        var $btn = $('#be-verify').prop('disabled', true);
        $('#be-matrix-card').prop('hidden', false);
        $('#be-matrix').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifying…</div>');
        $('#be-results-card').prop('hidden', true);
        call('local_bulk_enrolment_preview_matrix', {
            courseids: selectedCourses(),
            students: students,
            roleid: parseInt($('#be-role').val(), 10) || 0,
            reactivatesuspended: $('#be-reactivate').prop('checked')
        }).then(function(res) {
            state.matrix = res;
            var statuses = {};
            res.rows.forEach(function(r) {
                statuses[r.status] = r.status_label;
            });
            var opts = '<option value="">All statuses</option>';
            Object.keys(statuses).forEach(function(s) {
                opts += '<option value="' + esc(s) + '">' + esc(statuses[s]) + '</option>';
            });
            $('#be-matrix-filter').html(opts);
            return Templates.render('local_bulk_enrolment/ws_matrix', res).then(function(html) {
                $('#be-matrix').html(html);
                $('#be-execute').prop('disabled', res.summary.ready === 0);
                $btn.prop('disabled', false);
                updatePipelineStepper();
                scrollToElement($('#be-matrix-card'));
                return null;
            });
        }).catch(function(err) {
            $btn.prop('disabled', false);
            $('#be-matrix').html('<div class="alert alert-danger mb-0">' + esc(err.message || 'Verification failed.') + '</div>');
            reportException(err);
        });
    };

    /**
     * Filter matrix rows by status.
     */
    var filterMatrix = function() {
        var v = $('#be-matrix-filter').val();
        $('#be-matrix .be-matrix-row').each(function() {
            $(this).toggleClass('d-none', !(v === '' || $(this).data('status') === v));
        });
    };

    // ------------------------------------------------------------------ Execution.

    /**
     * Build execution jobs: one per (course, group name), split into chunks.
     *
     * @return {Array} [{courseid, coursename, groupname, userids: [], users: {userid: row}}]
     */
    var buildJobs = function() {
        var g = groupOptions();
        var jobs = {};
        state.matrix.rows.forEach(function(r) {
            if (!r.is_enrollable || !r.userid) {
                return;
            }
            var groupname = '';
            if (g.mode === 'custom') {
                groupname = g.name;
            } else if (g.mode === 'batch') {
                var s = state.selectedStudents[r.key];
                groupname = s && s.batch ? s.batch : '';
            }
            var k = r.courseid + '|' + groupname;
            if (!jobs[k]) {
                jobs[k] = {courseid: r.courseid, coursename: r.coursename, groupname: groupname, userids: [], users: {}};
            }
            if (!jobs[k].users[r.userid]) {
                jobs[k].users[r.userid] = r;
                jobs[k].userids.push(r.userid);
            }
        });
        var out = [];
        Object.keys(jobs).forEach(function(k) {
            var job = jobs[k];
            for (var i = 0; i < job.userids.length; i += CHUNK_SIZE) {
                out.push({courseid: job.courseid, coursename: job.coursename, groupname: job.groupname, users: job.users, userids: job.userids.slice(i, i + CHUNK_SIZE)});
            }
        });
        return out;
    };

    /**
     * Ask for confirmation, then execute.
     */
    var confirmAndExecute = function() {
        if (!state.matrix || state.matrix.summary.ready === 0) {
            return;
        }
        var courses = {};
        var students = {};
        state.matrix.rows.forEach(function(r) {
            if (r.is_enrollable) {
                courses[r.courseid] = true;
                students[r.key] = true;
            }
        });
        var body = '<p>Enrol <strong>' + size(students) + '</strong> student(s) into <strong>' + size(courses) + '</strong> course(s)?</p>' +
            '<p class="mb-0"><strong>' + state.matrix.summary.ready + '</strong> enrolment(s) will be created or reactivated. ' +
            'Students that are already enrolled are left untouched. This action uses Moodle manual enrolment and is recorded in the site logs.</p>';
        ModalSaveCancel.create({
            title: 'Confirm bulk enrolment',
            body: body,
            buttons: {save: 'Enrol students'},
            show: true,
            removeOnClose: true
        }).then(function(modal) {
            modal.getRoot().on(ModalEvents.save, function() {
                execute();
            });
            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Execute all chunks sequentially.
     */
    var execute = function() {
        var jobs = buildJobs();
        if (jobs.length === 0) {
            return;
        }
        var g = groupOptions();
        var role = parseInt($('#be-role').val(), 10) || 0;
        var days = parseInt($('#be-duration').val(), 10) || 0;
        var now = Math.floor(Date.now() / 1000);
        var timestart = days > 0 ? now : 0;
        var timeend = days > 0 ? now + days * 86400 : 0;
        var reactivate = $('#be-reactivate').prop('checked');

        state.executing = true;
        state.results = {summary: {chunks: 0, enrolled: 0, already_enrolled: 0, reactivated: 0, failed: 0, grouped: 0}, rows: [], errors: []};
        $('#be-execute, #be-verify').prop('disabled', true);
        $('#be-results-card').prop('hidden', false);
        $('#be-results-download').prop('hidden', true);
        $('#be-progress').prop('hidden', false).find('.progress-bar').css('width', '0%').attr('aria-valuenow', 0).text('0 / ' + jobs.length);
        $('#be-results').html('<div class="text-muted small">Executing ' + jobs.length + ' chunk(s)…</div>');
        updatePipelineStepper();
        scrollToElement($('#be-results-card'));

        var badge = function(status) {
            if (status === 'ENROLLED' || status === 'REACTIVATED') {
                return 'badge-success';
            }
            if (status === 'ALREADY_ENROLLED') {
                return 'badge-info';
            }
            return 'badge-danger';
        };

        var runJob = function(index) {
            if (index >= jobs.length) {
                finish();
                return;
            }
            var job = jobs[index];
            call('local_bulk_enrolment_execute_chunk', {
                courseid: job.courseid,
                userids: job.userids,
                roleid: role,
                timestart: timestart,
                timeend: timeend,
                reactivatesuspended: reactivate,
                groupname: job.groupname,
                allowcreategroup: g.create
            }).then(function(res) {
                var s = state.results.summary;
                s.chunks++;
                s.enrolled += res.enrolled;
                s.already_enrolled += res.already_enrolled;
                s.reactivated += res.reactivated;
                s.failed += res.failed;
                s.grouped += res.grouped;
                if (res.group_error) {
                    state.results.errors.push(job.coursename + ': ' + res.group_error);
                }
                res.results.forEach(function(r) {
                    var u = job.users[r.userid] || {};
                    state.results.rows.push({
                        fullname: u.fullname || ('User #' + r.userid),
                        username: u.username || '',
                        coursename: job.coursename,
                        status: r.status,
                        message: r.message,
                        group: r.group,
                        badge_class: badge(r.status)
                    });
                });
                return null;
            }).catch(function(err) {
                state.results.summary.chunks++;
                state.results.summary.failed += job.userids.length;
                state.results.errors.push(job.coursename + ' (' + job.userids.length + ' users): ' + (err.message || 'chunk failed'));
                job.userids.forEach(function(uid) {
                    var u = job.users[uid] || {};
                    state.results.rows.push({fullname: u.fullname || ('User #' + uid), username: u.username || '', coursename: job.coursename,
                        status: 'FAILED', message: err.message || 'Chunk failed and was rolled back.', group: '', badge_class: 'badge-danger'});
                });
            }).then(function() {
                var pct = Math.round(((index + 1) / jobs.length) * 100);
                $('#be-progress .progress-bar').css('width', pct + '%').attr('aria-valuenow', pct).text((index + 1) + ' / ' + jobs.length);
                runJob(index + 1);
                return null;
            });
        };

        var finish = function() {
            state.executing = false;
            $('#be-progress .progress-bar').removeClass('progress-bar-animated');
            Templates.render('local_bulk_enrolment/ws_results', state.results).then(function(html) {
                $('#be-results').html(html);
                $('#be-results-download').prop('hidden', false);
                notice(state.results.summary.failed > 0 ? 'warning' : 'success',
                    'Execution finished: ' + state.results.summary.enrolled + ' enrolled, ' + state.results.summary.reactivated + ' reactivated, ' +
                    state.results.summary.already_enrolled + ' already enrolled, ' + state.results.summary.failed + ' failed.');
                // Re-verify so the matrix reflects the new state (idempotency check for the operator).
                verify();
                loadRoster(false);
                return null;
            }).catch(Notification.exception);
        };

        runJob(0);
    };

    /**
     * Download the results as CSV.
     */
    var downloadResults = function() {
        if (!state.results) {
            return;
        }
        var q = function(v) {
            return '"' + String(v === undefined || v === null ? '' : v).replace(/"/g, '""') + '"';
        };
        var lines = ['Student,Username,Course,Outcome,Detail,Group'];
        state.results.rows.forEach(function(r) {
            lines.push([r.fullname, r.username, r.coursename, r.status, r.message, r.group].map(q).join(','));
        });
        var blob = new Blob([lines.join('\r\n')], {type: 'text/csv;charset=utf-8;'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'bulk_enrolment_results_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    };

    // ------------------------------------------------------------------ Wiring.

    /**
     * Bind DOM events.
     */
    var bind = function() {
        var $ws = $('#be-workspace');

        // Programs.
        $('#be-program-search').on('input', filterPrograms);
        $ws.on('change', '.be-program-checkbox', onProgramsChanged);
        $('#be-program-select-visible').on('click', function() {
            $('#be-program-list .be-item:not(.d-none) .be-program-checkbox').prop('checked', true);
            onProgramsChanged();
        });
        $('#be-program-clear').on('click', function() {
            $('#be-program-list .be-program-checkbox').prop('checked', false);
            onProgramsChanged();
        });

        // Batches.
        $ws.on('change', '.be-batch-checkbox', function() {
            onBatchToggled($(this));
        });
        $ws.on('change', '.be-batch-all', function() {
            onBatchAllToggled($(this));
        });

        // Roster.
        var searchTimer = null;
        $('#be-roster-search').on('input', function() {
            clearTimeout(searchTimer);
            var v = $(this).val();
            searchTimer = setTimeout(function() {
                state.filters.search = v;
                state.page = 1;
                loadRoster(false);
            }, 350);
        });
        $('#be-roster-batch-filter').on('change', function() {
            state.filters.batch = $(this).val();
            state.page = 1;
            loadRoster(false);
        });
        $('#be-roster-match-filter').on('change', function() {
            state.filters.match = $(this).val();
            state.page = 1;
            loadRoster(false);
        });
        $('#be-roster-refresh').on('click', function() {
            clearNotices();
            loadRoster(true);
        });
        $ws.on('click', '#be-pagination a.page-link', function(e) {
            e.preventDefault();
            var p = parseInt($(this).data('page'), 10);
            if (!$(this).closest('.page-item').hasClass('disabled') && p !== state.page) {
                state.page = p;
                loadRoster(false);
            }
        });
        $ws.on('change', '.be-student-checkbox', function() {
            onStudentToggled($(this));
        });
        $ws.on('click', '.be-courses-toggle', function() {
            var $btn = $(this);
            var $list = $('#' + $btn.attr('aria-controls'));
            var open = $list.prop('hidden');
            $list.prop('hidden', !open);
            $btn.attr('aria-expanded', open ? 'true' : 'false');
            $btn.find('i').toggleClass('fa-caret-down', !open).toggleClass('fa-caret-up', open);
        });
        $('#be-select-page').on('click', function() {
            $('#be-roster .be-student-checkbox:not(:disabled)').each(function() {
                $(this).prop('checked', true);
                onStudentToggled($(this));
            });
        });
        $('#be-select-all').on('click', function() {
            if (!state.roster) {
                return;
            }
            state.roster.selectable_keys.forEach(function(s) {
                state.selectedStudents[s.key] = {key: s.key, reg_id: s.reg_id, full_name: s.full_name, batch: s.batch};
            });
            $('#be-roster .be-student-checkbox:not(:disabled)').prop('checked', true);
            updateRosterBadges();
            updateVerifyState();
        });
        $('#be-select-clear').on('click', function() {
            state.selectedStudents = {};
            $('#be-roster .be-student-checkbox').prop('checked', false);
            updateRosterBadges();
            updateVerifyState();
        });

        // Courses.
        $('#be-course-search').on('input', filterCourses);
        $('#be-course-category').on('change', filterCourses);
        $ws.on('change', '.be-course-checkbox', updateVerifyState);
        $('#be-course-clear').on('click', function() {
            $('#be-course-list .be-course-checkbox').prop('checked', false);
            updateVerifyState();
        });

        // Options.
        $('input[name="be-group-mode"]').on('change', function() {
            var mode = groupOptions().mode;
            $('#be-group-name').prop('disabled', mode !== 'custom');
            $('#be-group-create').prop('disabled', mode === 'none');
            $('#be-group-hint').text(mode === 'batch' ? 'Each student is added to a course group named exactly like their UMS batch (e.g. "74F").' : '');
        });
        $('#be-verify').on('click', verify);
        $('#be-matrix-filter').on('change', filterMatrix);
        $('#be-execute').on('click', confirmAndExecute);
        $('#be-results-download').on('click', downloadResults);
    };

    return {
        /**
         * Initialise the workspace.
         */
        init: function() {
            bind();
            loadPrograms(false);
            updateVerifyState();
        }
    };
});
