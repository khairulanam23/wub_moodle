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
 * Controller for the August 2026 UMS Student Synchronization Workspace.
 *
 * @module     local_bulk_enrolment/sync_workspace
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    var SyncWorkspace = {
        init: function() {
            this.bindEvents();
            this.updateSelectionCount();
        },

        bindEvents: function() {
            var self = this;

            // When program changes, dynamically update batch dropdown.
            $('#sync-program-select').on('change', function() {
                var progId = $(this).val();
                self.loadBatchesForProgram(progId);
            });

            // Table search filter input.
            $('#sync-table-search').on('keyup', function() {
                var term = $(this).val().toLowerCase().trim();
                $('#sync-table tbody tr').each(function() {
                    var rowText = $(this).text().toLowerCase();
                    if (rowText.indexOf(term) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Select all checkbox.
            $('#chk-select-all-sync').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('#sync-table tbody tr:visible .sync-student-checkbox:not(:disabled)').prop('checked', isChecked);
                self.updateSelectionCount();
            });

            // Individual checkbox change.
            $('#sync-table').on('change', '.sync-student-checkbox', function() {
                self.updateSelectionCount();
            });
        },

        loadBatchesForProgram: function(programId) {
            var $batchSelect = $('#sync-batch-select');
            $batchSelect.empty().append($('<option value="0">All Batches</option>'));

            if (!programId) {
                return;
            }

            // Batches are identified by TITLE by the UMS roster API, so the option value is the title.
            Ajax.call([{
                methodname: 'local_bulk_enrolment_get_batches',
                args: {programids: [String(programId)], refresh: false}
            }])[0].then(function(res) {
                if (!res.ok) {
                    Notification.addNotification({message: res.error_code + ' — ' + res.error_message, type: 'error'});
                    return;
                }
                $.each(res.batches, function(i, b) {
                    var label = b.title + (b.shift ? ' (' + b.shift + ')' : '');
                    $batchSelect.append($('<option></option>').attr('value', b.title).text(label));
                });
            }).catch(Notification.exception);
        },

        updateSelectionCount: function() {
            var count = $('#sync-table tbody .sync-student-checkbox:checked').length;
            $('#sync-selected-badge').text(count + ' selected');
            $('#btn-submit-sync').prop('disabled', (count === 0));
        }
    };

    return SyncWorkspace;
});
