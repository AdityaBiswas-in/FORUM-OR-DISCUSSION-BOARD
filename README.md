# Quaddit — Forum & Discussion Board

> A lightweight, dynamic web-based discussion board built with **PHP** and **MySQL**, designed to let students and campus members start discussions, share ideas, attach images, reply to threads, and connect with their community.

---

## 📌 Project Overview

**Quaddit** is a dynamic web application created as a **PHP Mini Project (PCA 2, 5th Semester)** at **Bhawanipur Global Campus** under the guidance of **Prof. Majid Malik**.

The primary purpose of this project was to gain practical, hands-on experience in building a complete end-to-end dynamic web application from scratch — integrating frontend interfaces, server-side processing, session management, secure user authentication, and relational database management.

### Key Highlights
- **Interactive Discussions**: Create discussion threads with formatted text and image attachments.
- **Engaging Community**: Browse the central home feed, reply to ongoing discussions, and react with likes.
- **Content Management**: Users have full control to edit or delete their own posts and replies.
- **Personalized Profiles**: View user profiles, activity history (posts and replies tabs), and customize bios, profile avatars, and header banners.
- **Built-in Security**: Passwords are securely hashed with PHP's native `password_hash()` and verified using `password_verify()`.

---

## 🛠️ Technology Stack

The project relies on a clean, dependency-free tech stack with zero heavy external frameworks:

| Layer | Technology | Purpose |
|---|---|---|
| **Frontend** | **HTML5** | Semantic structure for pages, forms, buttons, and navigation |
| | **CSS3** | Responsive layout styling, colors, dark/light themes, typography, and card designs |
| | **Vanilla JavaScript** | Client-side interactions, modal popups, live image previews, and theme toggling |
| **Backend** | **PHP** | Server-side logic, routing, request processing, authentication, and database queries |
| **Database** | **MySQL** | Relational storage for user accounts, posts, images, replies, and likes |
| **Environment** | **Apache & XAMPP** | Local development environment and web server execution |
| **DB Management** | **phpMyAdmin** | Database administration, table structuring, and record inspection |

---

## 🏗️ System Architecture

Quaddit follows a classic **3-Tier Client-Server Architecture**:

```
┌────────────────────────────────────────────────────────┐
│               1. Presentation Layer (Frontend)         │
│          HTML5  •  CSS3  •  Vanilla JavaScript         │
│  (Desktop Browser, Mobile Layout, Responsive Design)   │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP Requests / Responses
                            ▼
┌────────────────────────────────────────────────────────┐
│               2. Application Layer (Backend)           │
│                 PHP  •  Apache Web Server              │
│  (Auth, Sessions, Post/Reply Handlers, Image Encoding) │
└───────────────────────────┬────────────────────────────┘
                            │ SQL Queries (MySQLi)
                            ▼
┌────────────────────────────────────────────────────────┐
│               3. Database Layer (Storage)              │
│                         MySQL                          │
│     (users, posts, post_images, replies, likes)        │
└────────────────────────────────────────────────────────┘
```

1. **Client Side (Front End)**: The visual interface where users interact with the forum — browsing discussion feeds, filling out forms, viewing media, and toggling themes.
2. **Server Side (Back End)**: PHP processes incoming HTTP requests, enforces access control via PHP sessions, executes database interactions, and renders dynamic responses.
3. **Database Layer**: MySQL reliably stores and organizes all application data, linking records together through unique primary and foreign keys.

---

## 🔄 User Workflow

```mermaid
flowchart TD
    Start([Visit Quaddit]) --> AuthCheck{Registered?}
    AuthCheck -- No --> Register[User Registration<br/>Name, Username, Email, Password]
    Register --> Login[User Login<br/>Verify credentials with password_hash]
    AuthCheck -- Yes --> Login
    Login --> HomeFeed[Home Feed<br/>Explore ongoing discussions]
    
    HomeFeed --> CreatePost[Create New Post<br/>Text + Image Attachments]
    HomeFeed --> InteractPost[Interact with Posts<br/>Like / Unlike & Threaded Replies]
    HomeFeed --> ManageOwn[Manage Own Content<br/>Edit or Delete Posts & Replies]
    HomeFeed --> ViewProfile[User Profile<br/>View activity, Edit Bio, Avatar, Banner]
    
    HomeFeed --> Logout[Sign Out<br/>Destroy session & redirect]
    Logout --> End([Session Ended])
```

---

## 🗄️ Database Design & Schema Architecture

The relational database (`quaddit`) is designed with 3 dedicated authentication tables alongside academic groups and discussion boards:

```
 ┌──────────────────────┐         ┌──────────────────────┐         ┌──────────────────────┐
 │       students       │         │       faculty        │         │        admins        │
 ├──────────────────────┤         ├──────────────────────┤         ├──────────────────────┤
 │ PK  id (10-digit)    │         │ PK  id (10-digit)    │         │ PK  id (10-digit)    │
 │     name             │         │     name             │         │     name             │
 │     email (unique)   │         │     email (unique)   │         │     email (unique)   │
 │     password (hash)  │         │     password (hash)  │         │     password (hash)  │
 │     avatar, bio      │         │     avatar, bio      │         │     avatar, bio      │
 │     created_at       │         │     created_at       │         │     created_at       │
 └──────────────────────┘         └──────────────────────┘         └──────────────────────┘
            │                                │                                │
            └──────────────────────────────┬─┴────────────────────────────────┘
                                           │ Synchronized Identity
                                           ▼
                                ┌──────────────────────┐
                                │        users         │ (Unified Relational Bridge)
                                ├──────────────────────┤
                                │ PK  id (INT auto)    │
                                │     uid (10-digit)   │
                                │     name, username   │
                                │     email, password  │
                                │     role             │
                                └──────────┬───────────┘
                                           │ 1:N
                     ┌─────────────────────┼─────────────────────┐
                     ▼                     ▼                     ▼
          ┌────────────────────┐ ┌───────────────────┐ ┌───────────────────┐
          │       groups       │ │   group_members   │ │       posts       │
          ├────────────────────┤ ├───────────────────┤ ├───────────────────┤
          │ PK  id             │ │ PK  id            │ │ PK  id            │
          │     name           │ │ FK  group_id      │ │ FK  user_id       │
          │     description    │ │ FK  user_id       │ │ FK  group_id      │
          │     status         │ │     joined_at     │ │     title         │
          │ FK  created_by     │ └───────────────────┘ │     content       │
          └────────────────────┘                       └─────────┬─────────┘
                                                                 │ 1:N
                                              ┌──────────────────┴──────────────────┐
                                              ▼                                     ▼
                                   ┌──────────────────────┐              ┌──────────────────────┐
                                   │     post_images      │              │       replies        │
                                   ├──────────────────────┤              ├──────────────────────┤
                                   │ PK  id               │              │ PK  id               │
                                   │ FK  post_id          │              │ FK  post_id          │
                                   │     image_url / data │              │ FK  user_id          │
                                   │     created_at       │              │ FK  parent_id (self) │
                                   └──────────────────────┘              │     content          │
                                                                         └──────────────────────┘
```

- **`students`**: Dedicated storage for student accounts registered with unique 10-digit student IDs.
- **`faculty`**: Dedicated storage for faculty and teacher accounts registered with unique 10-digit faculty IDs.
- **`admins`**: Dedicated storage for authorized administrator accounts registered with unique 10-digit admin IDs.
- **`authorized_admins`**: Institutional roster verifying pre-approved administrators and administrative authorization keys (`QUAD-ADMIN-2026`).
- **`users`**: Unified relational bridge syncing with registered accounts to maintain clean foreign key relationships without complex polymorphics.
- **`groups`**: Academic discussion groups (subject-wise, semester-wise, and department-wise) managed by Admins.
- **`group_members`**: Group membership records gating post publishing to verified members.
- **`posts`**: Group discussion posts with view counters, timestamps, and author associations.
- **`post_images`**: Multi-image attachments linked to discussion posts.
- **`replies`**: Threaded comments and nested replies under discussion posts.
- **`likes`**: Post and reply reactions with duplicate prevention.

---

## ✨ Features Implemented

### 1. Three Separate Registration & Authentication Portals
- **Student Portal**: Student ID (10 numeric digits), Full Name, Email, and Password.
- **Faculty Portal**: Faculty ID (10 numeric digits), Full Name, Email, and Password.
- **Admin Portal**: Admin ID (10 numeric digits), Institutional Authorization Key verification, Full Name, Email, and Password.
- Strict cross-table unique validations (rejecting duplicate IDs or emails across all three tables).
- Strict portal isolation: accounts registered in one category cannot sign in through another portal and receive clear authorization notices.

### 2. Group-Based Discussion System
- Admin controls for creating and restricting academic groups.
- User group discovery, join/leave capabilities, and membership-gated posting.
- Active vs. Restricted group status controls.

### 3. Interactive Discussion Feed & Moderation
- Central and group-filtered discussion feeds.
- Threaded replies, post interactions (likes/unlikes), and view counts.
- Dedicated Admin dashboard with platform analytics and management controls.

---

## 🚀 Getting Started (Local Setup)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache and MySQL modules).
- A modern web browser.

### Setup Instructions

1. **Clone or Copy the Project**:
   Place the project folder inside your XAMPP web root directory:
   ```
   C:\xampp\htdocs\FORUM-OR-DISCUSSION-BOARD
   ```

2. **Start Services in XAMPP Control Panel**:
   - Start **Apache** (ports `80`, `443`).
   - Start **MySQL** (port `3306`).

3. **Import Database in phpMyAdmin**:
   - Open your browser and navigate to `http://localhost/phpmyadmin`.
   - Click on the **Import** tab at the top.
   - Choose the file [`schema.sql`](file:///c:/xampp/htdocs/FORUM-OR-DISCUSSION-BOARD/schema.sql) located in the project root directory.
   - Click **Import** (or **Go**).
   - phpMyAdmin will automatically drop any old structure and set up the complete `quaddit` database with all 11 tables and initial seed data.

4. **Default Login Credentials (Ready to use)**:
   All default accounts use the password: `password123`

   | Portal | Account Type | 10-Digit ID / Email | Password | Role |
   |---|---|---|---|---|
   | **Admin** | Administrator | `9000000001` or `admin@quaddit.edu` | `password123` | System Administrator |
   | **Faculty** | Faculty / Teacher | `5000000001` or `faculty@quaddit.edu` | `password123` | Faculty Member |
   | **Student** | Student | `1000000001` or `student@quaddit.edu` | `password123` | Undergraduate Student |

   *Institutional Admin Authorization Key for new admin registrations:* `QUAD-ADMIN-2026`

5. **Launch the Application**:
   Open your browser and visit:
   ```
   http://localhost/FORUM-OR-DISCUSSION-BOARD/
   ```

---

## 👥 Project Team & Credits

- **Aditya Biswas** — BCA - B (`adityabiswas.24@bhawanipurglobal.edu.in`)
- **Prithviraj Pati** — BCA - B (`prithvirajpati.24@bhawanipurglobal.edu.in`)

**Academic Institution**: Bhawanipur Global Campus, Kolkata  
**Subject**: PCA 2 — PHP Mini Project (5th Semester)  
**Project Guide**: Prof. Majid Malik  

---

## 🎓 Conclusion

Building Quaddit provided practical experience in bridging theoretical knowledge of web development into a working real-world application. Through this project, we strengthened our understanding of core PHP server-side programming, relational data modeling in MySQL, session-based security, and building clean, responsive user interfaces.

