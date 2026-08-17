# WUB Auth Student Due & Penalty Restriction Gate (`local_wub_auth_penalty`)

The `local_wub_auth_penalty` plugin is a modular, high-security authorization gate and student financial penalty calculation engine for the World University of Bangladesh (WUB) eLearning portal.

## Architectural Overview

```text
wub_auth_penalty/
│
├── version.php                         # Moodle plugin version metadata
├── settings.php                        # Admin settings (API credentials, URLs, due thresholds)
├── lib.php                             # Procedural facade & integration hooks
├── README.md                           # Plugin documentation
│
├── lang/
│   └── en/
│       └── local_wub_auth_penalty.php  # English language strings
│
└── classes/
    └── service/
        ├── student_api.php            # Encapsulated cURL client (Digest/Basic + X-API-KEY)
        ├── authentication.php         # Moodle + WUB Student Portal API authentication & sync
        ├── due_calculator.php         # Institutional due calculation engine (-100 buffer, monthly installments, program dates)
        └── penalty_checker.php        # Access enforcement, wub_permission bypass, exemptions & caching
```

## Modular Components

### 1. API Communication (`service/student_api.php`)
- Dedicated cURL network client.
- Encapsulates Digest/Basic HTTP authentication and `X-API-KEY` query & header injection.
- Implements UMS API endpoints:
  - `students/student_login/`
  - `students/student_payment_info/{username}`
  - `students/email_number_wise_student_details/{identifier}`
  - `payments/student_fees_details/{username}`

### 2. Student Authentication (`service/authentication.php`)
- Multi-tiered login flow:
  1. Internal Moodle password check (`validate_internal_user_password`).
  2. Fallback check against WUB Student Portal API (`checkStudentPortalPassword`).
  3. Automatic Moodle password synchronization upon successful Student Portal login.

### 3. Due Calculation Engine (`service/due_calculator.php`)
- Preserves all institutional business rules:
  - Baseline buffer deduction: `remaining_deus - 100` BDT.
  - Monthly installment adjustment rules.
  - Program-specific semester start dates:
    - Program IDs `[324, 351, 359, 360, 363, 352, 361, 362, 313]` -> `'09-10'` (Sept 10).
    - Other programs default -> `'08-15'` (Aug 15).
  - System date comparison against designated program start dates.

### 4. Access Enforcement & Penalty Checker (`service/penalty_checker.php`)
- Evaluation order:
  1. Site administrators & teachers exempt (`allowed = true`).
  2. Active special permission (`wub_permission` valid until 23:59:59) bypasses due checking.
  3. 10-minute session cache lookup (`wub_due_status_$userid`).
  4. Query UMS payment API & evaluate due threshold (`due > 100` BDT).
  5. Error redirect code generation (`msg=0`, `msg=1`, `msg=2`).

## Admin Settings

Configuration parameters accessible via **Site Administration > Plugins > Local plugins > WUB Auth Student Due & Penalty Restriction Gate**:
- `api_url`: UMS REST API base URL.
- `api_username`: Digest/Basic username.
- `api_password`: Digest/Basic password.
- `api_x_api_key`: `X-API-KEY` secret.
- `due_threshold`: Maximum allowable student due (default 100.00 BDT).
