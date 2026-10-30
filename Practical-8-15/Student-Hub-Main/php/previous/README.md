# Practical 7: File-Based Storage Archive (JSON / CSV)

This directory isolates and preserves all implementations for **Practical 7 (PHP Form Processing with Server-Side Validation and CSV/JSON File Storage)**.

### Purpose of Separation
Starting from **Practical 8**, all StudentHub components use **MySQL relational database storage (via PHP PDO prepared statements)**. 
To prevent file-locking collisions, schema conflicts, or accidental overwrites of JSON/CSV data files during subsequent practicals (Practical 8 through 15), all Practical 7 file handlers are organized into this dedicated directory.

### Directory Contents
| File | Role | Storage Target |
| :--- | :--- | :--- |
| `process_registration_file.php` | Server-side validation, sanitization & atomic appending of student registrations | `data/registrations.json` & `data/registrations.csv` |
| `process_contact.php` | Validation and storage of campus inquiry messages | `data/contact.json` & `data/contact.csv` |
| `process_event_registration.php` | Student lookup verification and event registration file storage | `data/event_registrations.json` & `data/event_registrations.csv` |
| `view_file_records.php` | Interactive viewer for inspecting and downloading stored JSON and CSV records | Reads `data/*.json` and `data/*.csv` |

### Database Modules (Practical 8+)
All active database handlers reside in the parent directory (`../`):
* `db.php` - Central PDO connection and schema auto-recovery.
* `test_connection.php` - Connection and schema diagnostic page.
* `process_registration_db.php` - MySQL prepared statement student registration.
* `process_event_registration_db.php` - MySQL prepared statement event bookings.
* `process_contact_db.php` - MySQL prepared statement contact inquiries.
* `api_db_events.php` - Live MySQL JSON API for campus events.
