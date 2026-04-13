# Resume Analyzer

A PHP-based resume analysis web application built as a college project. Upload your resume, select the skills you want to be evaluated against, and instantly receive a score, skill gap report, and actionable improvement suggestions.

---

## Features

- **Drag & Drop Upload** — supports PDF, DOCX, and TXT (max 2 MB)
- **Custom Skill Picker** — choose any combination of skills from the database to analyse against
- **Skill Management** — add, rename, and delete skills directly from the UI (no DB tools needed)
- **Instant Score** — weighted score out of 100 based on skill match, resume sections, action verbs, length, and contact info
- **Skill Gap Report** — see exactly which skills were found and which are missing
- **Section Detection** — checks for Education, Experience, Projects, Skills, and Contact sections
- **Action Verb Analysis** — detects strong resume action words (developed, built, led, etc.)
- **Improvement Suggestions** — personalised tips based on analysis results
- **Recent Analyses** — history table of the last 10 analyses

---

## Tech Stack

| Layer        | Technology              |
|--------------|-------------------------|
| Backend      | PHP 8+                  |
| Database     | MySQL (via PDO)         |
| Frontend     | Bootstrap 5 + Inter font|
| PDF Parsing  | smalot/pdfparser        |
| Icons        | Bootstrap Icons         |
| Server       | Apache (XAMPP)          |

---

## Project Structure

```
resume-analyzer/
├── analyzer/
│   ├── analyze.php        # Upload handler & analysis orchestrator
│   ├── parser.php         # PDF / DOCX / TXT text extractor
│   └── skills_api.php     # CRUD API for skill management
├── assets/
│   ├── css/style.css      # Custom stylesheet
│   └── js/app.js          # Drop-zone, skill picker, modal logic
├── config/
│   └── constants.php      # DB credentials, upload settings
├── includes/
│   ├── db.php             # PDO connection helper
│   └── functions.php      # All analysis & DB functions
├── uploads/               # Uploaded resume files (git-ignored)
├── vendor/                # Composer dependencies
├── index.php              # Home page (upload + skill picker)
├── result.php             # Analysis result page
├── schema.sql             # Database schema + seed data
└── composer.json
```

---

## Setup

### Requirements
- XAMPP (Apache + MySQL + PHP 8+)
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/mdjunaidhaneef-glitch/resume_analyzer.git
   cd resume_analyzer
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Import the database**
   - Open phpMyAdmin → Import → select `schema.sql`
   - Or via CLI:
     ```bash
     mysql -u root < schema.sql
     ```

4. **Configure database credentials**
   - Open `config/constants.php` and update if needed:
     ```php
     define('LOCALHOST',    'localhost');
     define('DB_USERNAME',  'root');
     define('DB_PASSWORD',  '');
     define('DB_NAME',      'resume_analyzer');
     define('SITEURL',      'http://localhost/resume-analyzer/');
     ```

5. **Start XAMPP** (Apache + MySQL) and visit:
   ```
   http://localhost/resume-analyzer/
   ```

---

## Database Schema

| Table          | Purpose                                      |
|----------------|----------------------------------------------|
| `job_roles`    | Predefined roles (Backend, Frontend, etc.)   |
| `skills_master`| Master list of all skills                    |
| `role_skills`  | Many-to-many: which skills belong to a role  |
| `resumes`      | Stores each uploaded resume and its analysis |

---

## Usage

1. Open the app in your browser
2. Drag & drop or click to upload your resume (PDF/DOCX/TXT)
3. Click skills from the pool to select what to analyse against
4. Click **Analyze Resume**
5. View your score, matched/missing skills, section report, and suggestions

To manage skills: click **Manage Skills** → add, rename, or delete skills live without a page reload.

---

## Security

- File type validated by extension **and** MIME type (finfo)
- Executable extensions blocked (`.php`, `.exe`, `.sh`, etc.)
- All DB queries use **PDO prepared statements**
- All HTML output escaped with `htmlspecialchars`
- Uploaded files stored with random names, outside web-accessible paths

---

## License

This project is for educational/college purposes.
