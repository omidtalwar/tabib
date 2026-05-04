# MMI Collection — Institute Management System

## Project Overview
An Institute Management System (IMS) for managing teachers, students, courses, and academic data. Built with PHP, MySQL, HTML, Tailwind CSS, and jQuery.

## Tech Stack
- **Backend:** PHP (procedural, no framework)
- **Database:** MySQL via PDO
- **Frontend:** HTML5, Tailwind CSS (CDN), jQuery
- **Server:** XAMPP (Apache + MySQL)
- **Entry point:** `http://localhost/mmicollection/`

## Roles
| Role    | Capabilities |
|---------|-------------|
| Admin   | Full access — manage teachers, students, courses, settings |
| Teacher | Own dashboard — upload materials, manage assigned students/classes |
| Student | View own data, materials uploaded by their teacher |

## Folder Structure
```
mmicollection/
├── CLAUDE.md
├── index.php                  # Root redirect to login
├── config/
│   ├── db.php                 # PDO database connection ($pdo)
│   └── config.php             # App-wide constants (BASE_URL, etc.)
├── assets/
│   ├── css/style.css          # Custom CSS (Tailwind utilities override)
│   ├── js/main.js             # Global jQuery helpers
│   └── images/                # Logos, icons
├── includes/
│   ├── header.php             # <head>, Tailwind CDN, nav
│   ├── footer.php             # Closing tags, scripts
│   ├── sidebar.php            # Role-aware sidebar
│   └── auth.php               # Session/role guard (require_role())
├── auth/
│   ├── login.php              # Login page + POST handler
│   └── logout.php             # Session destroy + redirect
├── admin/
│   ├── index.php              # Admin dashboard
│   ├── teachers/              # CRUD for teachers
│   └── students/              # CRUD for students
├── teacher/
│   ├── index.php              # Teacher dashboard
│   ├── upload.php             # Upload study materials
│   └── students.php           # View assigned students
├── student/
│   └── index.php              # Student dashboard
└── database/
    └── schema.sql             # Full DB schema (run once to set up)
```

## Database Conventions
- All tables use `id` as auto-increment primary key
- Timestamps: `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- Passwords stored as `password_hash()` (bcrypt)
- Foreign keys enforced where MySQL InnoDB is used

## Coding Conventions
- Every page that requires login starts with: `require_once '../includes/auth.php'; require_role('admin');`
- DB queries use PDO prepared statements — never string interpolation in SQL
- Tailwind classes used inline; `style.css` only for things Tailwind can't handle
- jQuery used for AJAX calls ($.ajax / $.post) and DOM interactions
- No JS frameworks beyond jQuery; keep it simple

## Security Rules
- Never expose raw PHP errors in production — use `error_reporting(0)` in config
- Sanitize all user input via `htmlspecialchars()` on output
- File uploads: validate MIME type + extension, store outside webroot or in `/uploads` with no-execute policy
- Session: regenerate ID on login (`session_regenerate_id(true)`)
