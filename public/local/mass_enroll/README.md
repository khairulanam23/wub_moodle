# Moodle Bulk Enrollment
The bulk enrollment plugin provide multiple user & course enrol at a time. Also, if the user is already enrolled in the selected course, you could not enroll the user a second time.

This plugin can be used to show the user's external information using the REST API which will come from different software. For example, University management system software information at your university can be shown here.
#### Developed By World University of Bangladesh
https://wub.edu.bd/

# Installation
## Install from moodle.org
* go to https://moodle.org/plugins/view.php?plugin=enrol_bulk_enrollment and use the "Install now" Button

## Install with git
* use a command line interface of your choice on the destination system (server with moodle installation)
* switch to the moodle enrol folder: cd /path/to/moodle/enrol/
* git clone https://github.com/ProFarjan/moodle_bulk_enrollment.git
* navigate on your moodle page to admin --> notifivations and follow the instructions

# REST API Instruction (NOT REQUIRED)
* UMS API URL Return External User Details
<pre>
    <code>

    REQUEST PARAMETER (POST REQUEST)
    {
        email: 'email1,email2,email3', // email address must be @ before text
        X-API-KEY: 'YOUR_SECURE_API_KEY', // settings defined
        username: 'REST_API_USERNAME', // settings defined
        password: 'REST_API_PASSWORD', // settings defined
    }


    RESPONSE

    {
        status: success|failure
        message: {
            StudentDetails: [
                {
                    id: 1, // (int) required
                    department_id: 1, // (int) required
                    program_id: 1, // (int) required
                    batch_id: 65A, // (string) required
                    username: 'hellow', // (string) required
                    student_status: 0, // (num) required // 0-Active, 4-Graduated,5-suspended/Access denied,6-Inactive/Withdraw, 7-Dismissed, 8-Dropped
                    .............................
                },{
                    .............................
                    .............................
                    .............................
                }
            ]
        }
    }
    </code>
</pre>


* UMS Program API Return All Programs
<pre>
    <code>

    REQUEST PARAMETER (GET REQUEST)
    {
        X-API-KEY: 'YOUR_SECURE_API_KEY', // settings defined
        username: 'REST_API_USERNAME', // settings defined
        password: 'REST_API_PASSWORD', // settings defined
    }


    RESPONSE (Return All Programs)

    {
        status: success|failure
        message: [
            {
                id: 1, // (int) required
                title: 1, // (string) required
                .............................
            },{
                .............................
                .............................
                .............................
            }
        ]
    }
    </code>
</pre>

*  UMS Batch API Return Batch ID Wise Batches Details
<pre>
    <code>

    REQUEST URL (GET REQUEST)
    http://BASE_URL/{BATCH_ID}?X-API-KEY=YOUR_SECURE_API_KEY


    RESPONSE
    {
        status: success|failure
        message: [
            {
                id: 1, // (int) required
                batch_title: '50B', // (string) required
                program_id: 1, // (num) required
                .............................
            },{
                .............................
                .............................
                .............................
            }
        ]
    }
    </code>
</pre>



