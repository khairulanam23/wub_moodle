define(['jquery'], function($) {
    'use strict';

    var btnTxt = "bulk_enrol_btn";
    var uri = "/local/mass_enroll/request_enrolled.php";
    var existsStd = [];
    var userList = null;
    var courseList = null;
    var selectedCourses = [];
    var selectedUsers = [];

    function updateSelectedUsersListEmptyMessage() {
        if (selectedUsers.length === 0) {
            $('#selected-users-list-empty').text('No users selected');
        } else {
            $('#selected-users-list-empty').text('');
        }
    }

    function toggleUser(el) {
        var $el = $(el);
        var id = $el.attr('data-id');
        var email = $el.attr('data-email') || '';
        var name = $el.attr('data-name') || '';

        $el.toggleClass('selected');

        var idStr = String(id);
        var index = selectedUsers.indexOf(idStr);

        if (index > -1) {
            selectedUsers.splice(index, 1);
            $('.selected-user-item-' + idStr).remove();
        } else {
            selectedUsers.push(idStr);
            $('.selected-users-list').append(
                '<li class="selected-user-item selected-user-item-' + idStr + '">' +
                name + ' - ' + email +
                '<img class="selected-user-item-close" src="/course/icons/close.png" style="cursor:pointer; margin-left:8px;">' +
                '</li>'
            );

            $('.selected-user-item-' + idStr).off('click').on('click', function() {
                $(this).remove();
                var idx = selectedUsers.indexOf(idStr);
                if (idx > -1) {
                    selectedUsers.splice(idx, 1);
                }
                $el.removeClass('selected');
                $('input[name="users"]').val(selectedUsers.join(','));
                updateSelectedUsersListEmptyMessage();
            });
        }

        $('input[name="users"]').val(selectedUsers.join(','));
        updateSelectedUsersListEmptyMessage();
    }

    function toggleCourse(el) {
        var $el = $(el);
        var id = String($el.attr('data-id'));

        $el.toggleClass('selected');

        var index = selectedCourses.indexOf(id);
        if (index > -1) {
            selectedCourses.splice(index, 1);
        } else {
            selectedCourses.push(id);
        }

        $('input[name="courses"]').val(selectedCourses.join(','));
    }

    function selectionCheckCourses(isChecked) {
        selectedCourses = [];
        $('#list_courses li').each(function() {
            var $li = $(this);
            var id = String($li.data('id') || $li.attr('data-id'));
            if (isChecked) {
                $li.addClass('selected');
                if (id) {
                    selectedCourses.push(id);
                }
            } else {
                $li.removeClass('selected');
            }
        });
        if (isChecked) {
            $("#courses").val(selectedCourses.join(','));
        } else {
            $("#courses").val("");
            selectedCourses = [];
        }
    }

    function selectionCheckUsers(isChecked) {
        selectedUsers = [];
        $('#list_users li').each(function() {
            var $li = $(this);
            var id = String($li.data('id') || $li.attr('data-id'));
            if (isChecked) {
                $li.addClass('selected');
                if (id) {
                    selectedUsers.push(id);
                }
            } else {
                $li.removeClass('selected');
            }
        });
        if (isChecked) {
            $("#users").val(selectedUsers.join(','));
        } else {
            $("#users").val("");
            selectedUsers = [];
        }
        updateSelectedUsersListEmptyMessage();
    }

    function showAlert(type, title, text) {
        if (typeof window.Swal !== 'undefined') {
            window.Swal.fire({
                icon: type,
                title: title,
                text: text
            });
        } else {
            alert(title + ': ' + text);
        }
    }

    function init() {
        var optionsUsers = {
            valueNames: [
                'full_name',
                'student_email',
                'dept',
                'course_code',
                {data: ['id', 'email', 'name']}
            ],
            item: '<li class="user-item student-${id} col-xs-12 selected">' +
                  '<div style="width: 100%; overflow: hidden; display: flex; align-items: center; justify-content: space-between;">' +
                      '<div style="padding: 0 0 0 10px; flex-grow: 1; overflow: hidden;">' +
                          '<p class="full_name name col-xs-12" style="margin-bottom: 2px; font-weight: 600;"></p>' +
                          '<p class="student_email email col-xs-12" style="text-align: left; font-style: italic; font-size: 12px; margin-bottom: 2px;"></p>' +
                          '<p class="dept col-xs-12" style="font-weight: 400; margin-bottom: 0;"></p>' +
                      '</div>' +
                      '<div style="padding-right: 10px; flex-shrink: 0; text-align: right;">' +
                          '<span class="course_code badge bg-primary text-white" style="font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 4px; display: inline-block;"></span>' +
                      '</div>' +
                  '</div>' +
                  '</li>'
        };

        var optionsCourses = {
            valueNames: [
                'fullname',
                'shortname',
                {data: ['id']}
            ],
            item: '<li class="course-item selected"><span class="fullname"></span> - (<span class="shortname"></span>)</li>'
        };

        if (typeof window.List !== 'undefined') {
            if ($('#users_list').length) {
                userList = new window.List('users_list', optionsUsers);
            }
            if ($('#courses_list').length) {
                courseList = new window.List('courses_list', optionsCourses);
            }
        }

        if ($.fn.select2) {
            $('.select2').select2({
                theme: "classic",
                width: '100%'
            });
        }

        // Program dropdown change
        $('#program_id').on('change select2:select', function(e) {
            var programId = $(this).val() || (e && e.params && e.params.data ? e.params.data.id : '');
            var bs = $('#batch_id');
            bs.empty();
            bs.append(new Option('Choose Batch', '', true, true));
            if (!programId) {
                bs.trigger('change');
                return;
            }
            $.ajax({
                url: '/local/mass_enroll/api.php',
                method: 'POST',
                data: {
                    program_id: programId,
                    sesskey: (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : '')
                },
                success: function(res) {
                    if (res) {
                        var batches = (typeof res === 'string') ? JSON.parse(res) : res;
                        if (Array.isArray(batches)) {
                            for (var i = 0; i < batches.length; i++) {
                                var batch = batches[i];
                                var title = (typeof batch === 'object') ? (batch.batch_title || batch.title || batch.id || batch) : batch;
                                var val = (typeof batch === 'object') ? (batch.batch_title || batch.title || batch.id || batch) : batch;
                                bs.append(new Option(title, val, false, false));
                            }
                        }
                    }
                    bs.trigger('change');
                },
                error: function(err) {
                    showAlert('error', 'Oops...', 'System Error! Error Code: ' + err.status);
                }
            });
        });

        // Search Courses click
        $("#search_courses").on('click', function() {
            var l = (typeof window.Ladda !== 'undefined') ? window.Ladda.create(this) : null;
            if (l) { l.start(); }

            var category = ($("#courses_category").val() || '').trim();
            var enStart = ($("#en_start").val() || '').trim();
            var enEnd = ($("#en_end").val() || '').trim();

            if (category && enStart && enEnd) {
                $.ajax({
                    url: "/local/mass_enroll/api.php",
                    data: {
                        'category_id': category,
                        sesskey: (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : '')
                    },
                    method: 'POST',
                    success: function(res) {
                        if (courseList) {
                            courseList.clear();
                        }
                        selectedCourses = [];
                        if (res) {
                            var courses = (typeof res === 'string') ? JSON.parse(res) : res;
                            if (courses.error) {
                                showAlert('error', 'Error', courses.error);
                                if (l) { l.stop(); }
                                return;
                            }
                            var coursesFmList = [];
                            var inputVal = [];
                            for (var i in courses) {
                                if (courses.hasOwnProperty(i)) {
                                    var course = courses[i];
                                    coursesFmList.push({
                                        fullname: course.fullname,
                                        shortname: course.shortname,
                                        id: course.id
                                    });
                                    inputVal.push(course.id);
                                    selectedCourses.push(String(course.id));
                                }
                            }
                            $("#courses").val(inputVal.join(','));
                            if (courseList) {
                                courseList.add(coursesFmList);
                            }
                            $("#courses_category").find(':selected').attr('disabled', 'disabled');
                            $("#courses_category").val("").trigger('change');
                        }
                        if (l) { l.stop(); }
                    },
                    error: function(err) {
                        showAlert('error', 'Oops...', 'System Error! Error Code: ' + err.status);
                        setTimeout(function() { if (l) { l.stop(); } }, 1000);
                    }
                });
            } else {
                showAlert('warning', 'Empty Fields', 'Please Select Course Category & Enrolment Dates.');
                setTimeout(function() { if (l) { l.stop(); } }, 1000);
            }
        });

        // Search Students click
        $("#search_students").on('click', function() {
            var l = (typeof window.Ladda !== 'undefined') ? window.Ladda.create(this) : null;
            if (l) { l.start(); }

            var programId = ($("#program_id").val() || '').trim();
            var batchId = ($("#batch_id").val() || '').trim();
            var emails = ($("#emails").val() || '').trim();

            if (programId) {
                $.ajax({
                    url: "/local/mass_enroll/api.php",
                    data: {
                        program: programId,
                        batch: batchId,
                        emails: emails,
                        sesskey: (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : '')
                    },
                    method: 'POST',
                    success: function(res) {
                        if (userList) {
                            userList.clear();
                        }
                        existsStd = [];
                        selectedUsers = [];
                        if (res) {
                            var students = (typeof res === 'string') ? JSON.parse(res) : res;
                            var studentsFmList = [];
                            for (var i in students) {
                                if (students.hasOwnProperty(i)) {
                                    var student = students[i];
                                    var sId = student.id || student.username || ('std_' + i);
                                    var fname = student.firstname || student.name || student.full_name || student.username || 'Student';
                                    var lname = student.lastname || '';
                                    var sEmail = student.email || student.student_email || (student.username ? student.username + '@student.wub.edu.bd' : '');
                                    var prog = student.program_id || student.program_name || programId;
                                    var bt = student.batch_id || student.mother_batch || batchId;

                                    var courseCode = '';
                                    var cDetails = student.enrollCourseDetails || student.enroll_course_details || student.courseDetails || student.courseCode || student.course_code;
                                    if (cDetails) {
                                        if (typeof cDetails === 'string') {
                                            courseCode = cDetails;
                                        } else if (Array.isArray(cDetails)) {
                                            var codes = cDetails.map(function(c) {
                                                if (typeof c === 'object' && c !== null) {
                                                    return c.courseCode || c.course_code || c.code || c.title || '';
                                                }
                                                return String(c || '');
                                            }).filter(Boolean);
                                            courseCode = codes.join(', ');
                                        } else if (typeof cDetails === 'object') {
                                            courseCode = cDetails.courseCode || cDetails.course_code || cDetails.code || cDetails.title || '';
                                        }
                                    }
                                    if (!courseCode && (student.courseCode || student.course_code)) {
                                        courseCode = student.courseCode || student.course_code;
                                    }

                                    if (!existsStd.includes(sId)) {
                                        studentsFmList.push({
                                            full_name: (fname + ' ' + lname).trim(),
                                            student_email: sEmail,
                                            name: (fname + ' ' + lname).trim(),
                                            email: sEmail,
                                            dept: prog + ' (' + bt + ')',
                                            course_code: courseCode ? courseCode : '',
                                            id: sId
                                        });
                                        existsStd.push(sId);
                                    }
                                    selectedUsers.push(String(sId));
                                }
                            }
                            $("#users").val(selectedUsers.join(','));
                            if (userList) {
                                userList.add(studentsFmList);
                            }
                        }
                        if (l) { l.stop(); }
                    },
                    error: function(err) {
                        showAlert('error', 'Oops...', 'System Error! Error Code: ' + err.status);
                        setTimeout(function() { if (l) { l.stop(); } }, 1000);
                    }
                });
            } else {
                showAlert('warning', 'Empty Fields', 'Please Select Program & Batch.');
                setTimeout(function() { if (l) { l.stop(); } }, 1000);
            }
        });

        // Event delegation for user item clicks
        $('#list_users').on('click', '.user-item', function() {
            toggleUser(this);
        });

        // Event delegation for course item clicks
        $('#list_courses').on('click', '.course-item', function() {
            toggleCourse(this);
        });

        // Check all courses checkbox
        $('#check_all_courses').on('change', function() {
            selectionCheckCourses($(this).is(':checked'));
        });

        // Check all students checkbox
        $('#check_all_students').on('change', function() {
            selectionCheckUsers($(this).is(':checked'));
        });

        // Prevent enter submitting on search box
        $('#users_list .search').on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        });

        // Form submission
        $("#bulk_enrolled").on('submit', function(e) {
            e.preventDefault();
            this.blur();

            var submitBtn = document.querySelector("#" + btnTxt);
            var l = (typeof window.Ladda !== 'undefined' && submitBtn) ? window.Ladda.create(submitBtn) : null;
            if (l) { l.start(); }

            var form = new FormData(this);
            if (!form.has('sesskey') && typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) {
                form.append('sesskey', M.cfg.sesskey);
            }
            var course = $("#courses").val();
            var users = $("#users").val();

            if (course && users) {
                $.ajax({
                    url: uri,
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: form,
                    type: "POST",
                    success: function(res) {
                        $("#output_users").html(res);
                        $("#output_users").show();
                        $("#selected_users").hide();
                        setTimeout(function() {
                            if (l) { l.stop(); }
                            uri = "/local/mass_enroll/submit_enrolled.php";
                            $("#" + btnTxt).hide();
                            btnTxt = "bulk_enrol_btn_submit";
                            $("#" + btnTxt).show();
                        }, 500);
                    },
                    error: function(err) {
                        showAlert('error', 'Oops...', 'System Error! Error Code: ' + err.status);
                        setTimeout(function() { if (l) { l.stop(); } }, 1500);
                    }
                });
            } else {
                showAlert('warning', 'Selection Required', 'Please select at least one course and student!');
                setTimeout(function() { if (l) { l.stop(); } }, 1500);
            }
        });
    }

    return {
        init: init
    };
});
