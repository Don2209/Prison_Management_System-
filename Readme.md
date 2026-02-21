You are a senior PHP engineer and system architect.

Generate a COMPLETE and EXPANDABLE **Advanced Prison Management System** using a SIMPLE BASIC PHP STRUCTURE (no complex frameworks or fancy architecture).

The system must be modular but use a straightforward structure so it is easy to run in XAMPP.

---

## ⚙️ TECH STACK

Backend: Core PHP (procedural + reusable functions)
Database: MySQL
Frontend: HTML, CSS, Bootstrap, minimal JavaScript
Security: Sessions, password hashing, prepared statements

The project must run locally immediately after database import.

---

## 📁 BASIC FILE STRUCTURE (KEEP SIMPLE)

/prison-system
/config
db.php
config.php

```
/includes
    header.php
    footer.php
    auth.php
    functions.php

/modules
    /auth
    /dashboard
    /inmates
    /staff
    /cells
    /facilities
    /visitors
    /incidents
    /medical
    /rehabilitation
    /transport
    /inventory
    /finance
    /reports
    /settings

/assets
    /css
    /js

index.php
login.php
logout.php
```

---

## 🧩 SYSTEM MODULES & FULL FUNCTIONALITIES

1️⃣ AUTHENTICATION & RBAC

* Login / logout
* Role based access: Admin, Officer, Medical, Supervisor
* Session management
* Password reset

2️⃣ DASHBOARD

* Total inmates
* Occupancy rate
* Staff on duty
* Alerts & recent incidents
* Quick statistics cards

3️⃣ INMATE MANAGEMENT (FULL LIFECYCLE)

* Register inmate
* Biographical data
* Case details
* Sentence tracking
* Risk classification
* Photo upload
* Transfer history
* Behavior logs
* Release management

4️⃣ STAFF MANAGEMENT

* Staff profiles
* Roles & permissions
* Shift scheduling
* Attendance tracking

5️⃣ CELL & FACILITY MANAGEMENT

* Prison blocks
* Cells & capacity
* Assign inmates to cells
* Overcrowding alerts

6️⃣ VISITOR MANAGEMENT

* Visitor registration
* Visit scheduling
* Approval workflow
* Visit history

7️⃣ INCIDENT MANAGEMENT

* Log incidents
* Incident categories
* Investigation notes
* Attach evidence

8️⃣ MEDICAL & HEALTHCARE

* Medical records
* Appointments
* Medication tracking
* Mental health notes

9️⃣ REHABILITATION & PROGRAMS

* Education programs
* Vocational training
* Participation tracking
* Progress reports

🔟 TRANSPORT & COURT MANAGEMENT

* Court schedules
* Escort officers
* Vehicle assignment
* Movement logs

1️⃣1️⃣ INVENTORY & SUPPLIES

* Food supplies
* Equipment
* Stock tracking
* Low stock alerts

1️⃣2️⃣ FINANCE & INMATE ACCOUNTS

* Inmate trust accounts
* Transactions
* Commissary purchases
* Budget tracking

1️⃣3️⃣ REPORTS & ANALYTICS

* Population reports
* Incident reports
* Financial reports
* Export to PDF/CSV

1️⃣4️⃣ HUMAN RIGHTS & COMPLIANCE

* Complaint logging
* Inspection reports
* Rights violation tracking

1️⃣5️⃣ RELEASE & REINTEGRATION

* Release planning
* Parole tracking
* Reintegration notes

1️⃣6️⃣ SYSTEM SETTINGS

* User management
* Prison info settings
* Backup tools

---

## 🗄️ DATABASE REQUIREMENTS

Create FULL MySQL schema including:

* inmates
* inmate_history
* staff
* roles
* cells
* facilities
* visitors
* visits
* incidents
* medical_records
* programs
* inmate_programs
* transport_logs
* inventory
* transactions
* complaints
* reports
* users

Include:

* Primary keys
* Foreign keys
* Indexes

Provide:

1. database.sql (full schema)
2. seed.sql (sample data)

---

## 🎨 UI REQUIREMENTS

* Admin dashboard layout
* Sidebar navigation
* Tables with search
* Forms for CRUD operations
* Responsive design

---

## 🔐 SECURITY REQUIREMENTS

* PDO prepared statements
* Input validation
* Session timeout
* Role-based page protection

---

## 📄 DOCUMENTATION

Create README.md including:

1. Installation steps (XAMPP)
2. Database setup
3. Default login credentials
4. Module explanation
5. How to extend system

---

## 🎯 OUTPUT FORMAT

Generate in this order:

1️⃣ Folder structure tree
2️⃣ Database schema SQL
3️⃣ Core config files
4️⃣ Authentication module
5️⃣ Dashboard
6️⃣ Each module step by step
7️⃣ README

Do not simplify the system.
Assume this is a NATIONAL LEVEL prison system foundation.
