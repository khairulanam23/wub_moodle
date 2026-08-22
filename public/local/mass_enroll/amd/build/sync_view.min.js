define(['jquery'], function($) {
    'use strict';

    function escapeHtml(str) {
        if (!str) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getUmsStatusLabel(code) {
        var map = {0: 'Active', 4: 'Graduated', 5: 'Suspended', 6: 'Inactive', 7: 'Dismissed', 8: 'Dropped'};
        return map[code] || 'Active';
    }

    function getStatusBadgeHtml(code, label) {
        code = parseInt(code, 10);
        var bg = '#64748b';
        if (code === 0) {
            bg = '#10b981'; // Active - green
        } else if (code === 4) {
            bg = '#3b82f6'; // Graduated - blue
        } else if (code === 5) {
            bg = '#ef4444'; // Suspended - red
        } else if (code === 6) {
            bg = '#f59e0b'; // Inactive - amber
        } else if (code === 7) {
            bg = '#881337'; // Dismissed - dark red
        } else if (code === 8) {
            bg = '#6b7280'; // Dropped - gray
        }

        return '<span class="badge" style="background:' + bg + '; color:#fff; padding:4px 8px; font-weight:500; border-radius:8px;">' + escapeHtml(label) + '</span>';
    }

    function setTableBodyTr(obj) {
        var isExisting = (obj.is_existing === true || obj.sync_state === 'synced');
        var safeUn = (obj.username || '').replace(/[^a-zA-Z0-9_-]/g, '_');
        var name = obj.full_name || ((obj.firstname || '') + ' ' + (obj.lastname || ''));
        if (!name.trim()) {
            name = obj.username || 'Student';
        }

        var statusCode = obj.status_code !== undefined ? obj.status_code : 0;
        var statusLabel = obj.status_label || getUmsStatusLabel(statusCode);

        var payloadStr = JSON.stringify({
            username: obj.username,
            full_name: name,
            firstname: obj.firstname,
            lastname: obj.lastname,
            email: obj.email,
            program_id: obj.program_id,
            batch_id: obj.batch_id
        }).replace(/"/g, '&quot;');

        var tr = '<tr>';

        // Checkbox: disabled for synced students; enabled for unsynced
        tr += '<td class="text-center">';
        if (isExisting) {
            tr += '<input type="checkbox" id="chk_' + safeUn + '" name="user[]" value="' + payloadStr + '" disabled style="accent-color:var(--wub-primary, #0f6cbf); width:16px; height:16px; cursor:not-allowed; opacity: 0.4;" />';
        } else {
            tr += '<input type="checkbox" id="chk_' + safeUn + '" name="user[]" value="' + payloadStr + '" checked style="accent-color:var(--wub-primary, #0f6cbf); width:16px; height:16px; cursor:pointer;" />';
        }
        tr += '</td>';

        // Student Name & Username
        tr += '<td>';
        tr += '<strong style="color:var(--wub-primary, #0f6cbf); font-size: 14px;">' + escapeHtml(name) + '</strong>';
        if (obj.username || obj.stud_id) {
            tr += '<br/><small class="text-muted"><i class="fa fa-id-card-o me-1"></i>' + escapeHtml(obj.stud_id || obj.username) + '</small>';
        }
        tr += '</td>';

        // Email
        tr += '<td><span style="color:#334155; font-size:13px;"><i class="fa fa-envelope-o me-1 text-muted"></i>' + escapeHtml(obj.email || '') + '</span></td>';

        // UMS Status Badge
        tr += '<td class="text-center">' + getStatusBadgeHtml(statusCode, statusLabel) + '</td>';

        // Local DB Sync Status Badge
        tr += '<td class="text-end" id="sync_cell_' + safeUn + '">';
        if (isExisting) {
            tr += '<span class="badge badge-success" style="background:var(--wub-green, #07c78c); color:#fff; padding:4px 10px; border-radius:12px;"><i class="fa fa-check me-1"></i> Sync</span>';
        } else {
            tr += '<span class="badge badge-secondary" style="background:#64748b; color:#fff; padding:4px 10px; border-radius:12px;"><i class="fa fa-times-circle me-1"></i> Not Sync</span>';
        }
        tr += '</td>';

        tr += '</tr>';
        return tr;
    }

    function stopLadda(l, mSec) {
        var delay = mSec || 500;
        setTimeout(function() {
            if (l) {
                l.stop();
            }
        }, delay);
    }

    function showAlert(type, title, text, html) {
        if (typeof window.Swal !== 'undefined') {
            var opts = { icon: type, title: title };
            if (html) {
                opts.html = html;
            } else {
                opts.text = text;
            }
            window.Swal.fire(opts);
        } else {
            alert(title + ': ' + (text || ''));
        }
    }

    function init() {
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'classic',
                width: '100%'
            });
        }

        $('#program').on('change select2:select', function(e) {
            var programId = $(this).val() || (e && e.params && e.params.data ? e.params.data.id : '');
            var batchSelect = $('#batch');
            batchSelect.empty().append('<option value="">All Batches</option>');
            if (programId) {
                $.ajax({
                    url: '/local/mass_enroll/api.php',
                    method: 'POST',
                    data: {
                        program_id: programId,
                        sesskey: (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : '')
                    },
                    success: function(res) {
                        try {
                            var batches = (typeof res === 'string') ? JSON.parse(res) : res;
                            if (Array.isArray(batches)) {
                                for (var i = 0; i < batches.length; i++) {
                                    var b = batches[i];
                                    var bVal = (typeof b === 'object') ? (b.batch_title || b.title || b.id) : b;
                                    var bLbl = (typeof b === 'object') ? (b.batch_title || b.title || b.id) : b;
                                    batchSelect.append(new Option(bLbl, bVal, false, false));
                                }
                            }
                        } catch (err) {}
                        batchSelect.trigger('change');
                    }
                });
            } else {
                batchSelect.trigger('change');
            }
        });

        $('#search_programs').on('click', function() {
            var l = (typeof window.Ladda !== 'undefined') ? window.Ladda.create(this) : null;
            if (l) { l.start(); }

            var program = $('#program').val();
            var batch = $('#batch').val() || '0';

            if (program) {
                $.ajax({
                    url: '/local/mass_enroll/api.php',
                    method: 'POST',
                    data: {
                        sync_program: program,
                        batch: batch,
                        sesskey: (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : '')
                    },
                    success: function(res) {
                        try {
                            var obj = (typeof res === 'string') ? JSON.parse(res) : res;
                            var tr = '';
                            var newCount = 0;
                            var existingCount = 0;

                            if (Array.isArray(obj)) {
                                for (var i = 0; i < obj.length; i++) {
                                    var o = obj[i];
                                    tr += setTableBodyTr(o);
                                    if (o.is_existing || o.selectable === false) {
                                        existingCount++;
                                    } else {
                                        newCount++;
                                    }
                                }
                            } else if (typeof obj === 'object') {
                                Object.entries(obj).forEach(function(entry) {
                                    var item = entry[1];
                                    tr += setTableBodyTr(item);
                                    if (item.is_existing || item.selectable === false) {
                                        existingCount++;
                                    } else {
                                        newCount++;
                                    }
                                });
                            }
                            stopLadda(l);
                            var tableBody = $('#sync_table');
                            tableBody.empty();
                            if (tr) {
                                tableBody.append(tr);
                                $('#sync_summary_badge').html('<span class="text-success fw-bold">' + existingCount + ' Sync (In Local DB)</span> | <span class="text-muted fw-bold">' + newCount + ' Not Sync (Only in UMS)</span>');
                            } else {
                                tableBody.append('<tr><td colspan="5" class="text-center text-muted p-4">No student records returned from UMS for this selection.</td></tr>');
                                $('#sync_summary_badge').text('');
                            }
                            $("#output").show();
                            $('#sync_btn').show();
                        } catch (e) {
                            stopLadda(l, 1000);
                        }
                    },
                    error: function(err) {
                        stopLadda(l, 1000);
                        showAlert('error', 'Error', 'Failed to connect to UMS synchronization service. Code: ' + err.status);
                    }
                });
            } else {
                stopLadda(l, 1500);
                showAlert('warning', 'Select Program', 'Please select a program to synchronize.');
            }
        });

        // Select all checkbox
        $('#select_all_sync').on('change click', function() {
            var status = $(this).is(":checked");
            $('#sync_table input:checkbox').each(function() {
                var d = $(this).prop('disabled') || $(this).attr('disabled') === 'disabled';
                if (!d) {
                    $(this).prop('checked', status);
                }
            });
        });

        // Filter sync table
        $('#sync_search_box').on('keyup input', function() {
            var filter = $(this).val().toLowerCase();
            $('#sync_table tr').each(function() {
                var text = $(this).text();
                if (text.toLowerCase().indexOf(filter) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Bulk sync submission
        $("#bulk_sync").on('submit', function(e) {
            e.preventDefault();
            var checkedBoxes = $('#sync_table input:checkbox:checked:not(:disabled)');
            if (checkedBoxes.length === 0) {
                showAlert('info', 'No Selection', 'Please select at least one student from the local database to synchronize.');
                return;
            }

            var submitBtn = document.querySelector('#sync_btn');
            var l = (typeof window.Ladda !== 'undefined' && submitBtn) ? window.Ladda.create(submitBtn) : null;
            if (l) { l.start(); }

            $.ajax({
                url: '/local/mass_enroll/api.php',
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                data: new FormData(this),
                success: function(res) {
                    try {
                        var obj = (typeof res === 'string') ? JSON.parse(res) : res;
                        if (obj && obj.status === 'success') {
                            var createdCnt = (obj.created) ? obj.created.length : 0;
                            var skippedCnt = (obj.skipped) ? obj.skipped.length : 0;

                            if (Array.isArray(obj.created)) {
                                for (var i = 0; i < obj.created.length; i++) {
                                    var u = obj.created[i];
                                    var safeUn = (u.username || '').replace(/[^a-zA-Z0-9_-]/g, '_');
                                    var cell = $('#sync_cell_' + safeUn);
                                    if (cell.length) {
                                        cell.html('<span class="badge badge-success" style="background:var(--wub-green, #07c78c); color:#fff; padding:4px 10px; border-radius:12px;"><i class="fa fa-check me-1"></i> Sync</span>');
                                    }
                                    var chk = $('#chk_' + safeUn);
                                    if (chk.length) {
                                        chk.prop('checked', false).prop('disabled', true).css({'cursor': 'not-allowed', 'opacity': '0.4'});
                                    }
                                }
                            }

                            showAlert('success', 'Synchronization Complete', null, 'Successfully synchronized <strong>' + createdCnt + '</strong> student record(s) in local database.' + (skippedCnt > 0 ? '<br/><small class="text-muted">' + skippedCnt + ' student(s) not in local DB were skipped.</small>' : ''));
                        } else {
                            showAlert('warning', 'Synchronization Warning', obj.message || 'Operation completed with warnings.');
                        }
                    } catch (err) {
                        showAlert('info', 'Processed', 'Student records synchronized.');
                    }
                    stopLadda(l);
                },
                error: function(err) {
                    stopLadda(l, 2000);
                    showAlert('error', 'Error', 'Failed to synchronize student records.');
                }
            });
        });
    }

    return {
        init: init
    };
});
