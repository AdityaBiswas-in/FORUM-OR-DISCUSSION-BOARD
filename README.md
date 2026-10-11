# CampusForum - College Discussion Board & Community

A modern, fast, and responsive discussion board web application built for college campuses using **PHP & MySQL**. Streamlined into a simple, single-file architecture in `index.php`.

---

## 🌟 Key Features (All in `index.php`)

1. **All-in-One Discussion Board**:
   - **Post Discussions**: Create threads with titles, topics, and up to 4 image attachments.
   - **See Posts**: Live feed with search by keyword and topic pill filters (# Programming, # College, # Campus Life, # Events, # Career).
   - **Edit Discussions**: Edit your own posts via inline controls or modal dialog.
   - **Delete Discussions**: Delete posts with one click (and automatic cleanup of uploaded images).
   - **Reply on Post**: Interactive inline reply box directly under each post card without leaving the page.
   - **Edit & Delete Replies**: Edit your replies inline or delete them directly on the feed.
   - **Toggle Save (Bookmarks)**: Instant bookmarking with 1-click filter to view saved discussions (`index.php?view=saved`).
   - **Interactive Likes**: Real-time heart likes with instant count updates.

2. **Clean & Modern X/Twitter-Style UI**:
   - Dark mode & Light mode toggle with persistent local storage.
   - User profile dropdown with secure sign-out.
   - Fullscreen image lightbox preview.

---

## 📁 Simple Folder Structure

The project has been rewritten into a simple, clean, and consolidated structure:

```text
FORUM-OR-DISCUSSION-BOARD/
│
├── index.php                 # All-in-one discussion board (Post, View, Edit, Delete, Reply, Edit Reply, Save)
├── db.php                    # Simple database connection & session setup
├── database.sql              # MySQL database schema and seed data
├── login.php                 # User login page
├── register.php              # User registration page
├── logout.php                # Sign out and session termination
├── README.md                 # Project documentation
│
└── assets/                   # Static frontend assets
    ├── css/
    │   ├── style.css         # Dark & Light theme styling
    │   └── auth.css          # Auth screen styles
    ├── js/
    │   └── main.js           # Interactive scripts (theme, modals, lightbox, AJAX)
    ├── images/               # Default avatars and icons
    └── uploads/              # Uploaded photos
```

---

## 🚀 How to Run Locally with XAMPP

1. **Place Project in XAMPP**:

   ```text
   C:\xampp\htdocs\FORUM-OR-DISCUSSION-BOARD\
   ```

2. **Start Apache & MySQL**:
   Open XAMPP Control Panel and start **Apache** and **MySQL**.

3. **Open in Browser**:
   Navigate to:
   ```text
   http://localhost/FORUM-OR-DISCUSSION-BOARD/index.php
   ```
   The database `campus_forum` will automatically connect or create tables on first run via `db.php`.
