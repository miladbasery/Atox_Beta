# Atox Beta (Twitter Clone)

A lightweight, high-performance monolithic social media platform and Twitter clone built using native PHP, MySQL, and vanilla frontend technologies. The project supports micro-blogging (tweets), threaded discussions, group interactions, academic pamphlet sharing (Jozves), and community clubs (Kanoons).

---

##  Features

### Core Social Networking
* **Micro-blogging (Tweets):** Create tweets, support for retweets, and interactive nested comments.
* **Social Graph:** Robust Follow/Unfollow mechanics and user blocking capabilities.
* **Real-time Metrics:** Tracks views, likes, and engagement metrics natively.
* **User Profiles & Resumes:** Comprehensive profile customizable settings, including structured JSON skill management and resume attachments.

###  Communities & Groups (Kanoons)
* **Kanoon Hubs:** Create and manage exclusive associations or student clubs.
* **Project Showcase:** Link developmental or community projects directly to their respective Kanoon, complete with dedicated image galleries and GitHub integrations.
* **Jozve Share (Pamphlets):** An academic extension allowing users to upload, categorize, and review educational materials and hand-written notes by term and language.

###  Chat & Infrastructure
* **Direct & Group Messaging:** Persistent chat rooms featuring reply tracking, read receipts, and message edit/delete states.
* **Security Infrastructure:** Native OTP verification requests, throttled login attempts, and structured user session auditing.

---

##  Tech Stack

* **Backend:** PHP (Native / Procedural & OOP Blend)
* **Database:** MySQL (Relational schema balancing `InnoDB` for transactional data safety and `MyISAM` for read-heavy entities)
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla ES6)

---

##  Database Architecture

The system utilizes a relational database management system (`atox_db`) with highly optimized indexing for relational lookups (e.g., social graphs and parent-child tweet structures).

### Entity-Relationship Overview
* **Identity Layer:** `users`, `user_sessions`, `login_attempts`, `otp_requests`
* **Social Layer:** `tweets`, `likes`, `follows`, `blocks`, `reports`
* **Academic/Community Layer:** `kanoons`, `kanoon_members`, `projects`, `jozves`, `jozve_groups`
* **Communication Layer:** `conversations`, `participants`, `messages`

---

##  Local Installation & Setup

Follow these steps to deploy and run the application locally on your machine.

### Prerequisites
Ensure you have a local AMP server stack installed:
* [XAMPP](https://www.apachefriends.org/) (Recommended), WampServer, or Laragon.
* PHP 7.4 or higher.
* MySQL 5.7 / MariaDB 10.4 or higher.

### Step 1: Clone and Move the Project
Clone the repository into your server's root directory (e.g., `htdocs` for XAMPP or `www` for WampServer).

```bash
cd /path/to/xampp/htdocs
git clone [https://github.com/miladbasery/atox_beta.git](https://github.com/miladbasery/atox_beta.git]) Atox
```
Step 2: Database Initialization
Start Apache and MySQL via your XAMPP Control Panel.

Open your browser and navigate to http://localhost/phpmyadmin/.

Click on New on the left sidebar to create a new database. Name it atoxcomp_simpleblog and set the collation to utf8mb4_general_ci.

Select the newly created database, go to the Import tab.

Click Choose File, select the provided .sql file containing your schema dump, and click Import / Go.

Step 3: Configure Environment Variables
Locate your database connection configuration file (typically config.php, db.php, or .env inside your project root) and update the parameters to match your local setup:
```
#set the DB
http://localhost/phpmyadmin
#create DB / import localhost.db file

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'atox_db');
```
Step 4: Run the Application
Open your preferred web browser and enter the local server address:
```
Code snippet
http://localhost/atox
```
📝 License & Copyright
Copyright © 2026 Milad Basery. All rights reserved.

Development and architectural design of this codebase are exclusively authored by Milad Basery.

Open Source Grant: While copyright ownership remains exclusive, permission is hereby granted to copy, modify, distribute, and study this software for educational, personal, or open-source purposes. Forking and rebuilding are highly encouraged!
