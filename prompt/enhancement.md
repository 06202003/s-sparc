# Comprehensive System Enhancement Specification: E-STRANGE & S-SPARC AI

This document serves as the master engineering prompt, design system guideline, and technical specification for modernizing the **E-STRANGE** academic plagiarism detection platform and deeply integrating the **S-SPARC AI** coding assistant and sustainability ecosystem.

---

## 1. Core Global Directives & Design Philosophy

1. **Strict Language Standard (100% English)**:
   - All user-facing text across both E-STRANGE and S-SPARC (navigation bars, dashboard cards, table headers, form labels, modals, alert messages, prompt templates, buttons, charts, and reports) must be written in professional, natural English.
   - No mixed Indonesian/English text in the user interface.

2. **Zero AI Slop & Zero Emojis**:
   - Strictly avoid all decorative emojis (e.g., no robot heads, leaves, medals, or lightbulbs).
   - Use clean, minimalist SVG line icons (Tailwind Heroicons / Lucide style) or crisp font icons.
   - Avoid generic, bloated, or cartoonish AI-slop layouts. Every component must look intentional, crisp, and academic.

3. **Strict Information Architecture & Role Scoping**:
   - **Student View**: Students must only see data relevant to them (enrolled courses, active assignments due, their own submissions, personal token usage, personal gamification points, and environmental footprint).
   - **Lecturer View**: Lecturers see their authored courses, assignment management, class submission overviews, cohort similarity matrices, and class sustainability analytics.
   - **Admin View**: Administrators have access to global user management, system audit logs, course enrollment sync, and batch operations.
   - **Clutter Elimination**: Never display raw debug logs, unnecessary database IDs, internal stack traces, or developer scratch tools to standard users.

4. **Design System & Typography**:
   - **Color Palette**: Tailwind Light Mode baseline using slate neutrals (`from-slate-50 via-slate-100 to-slate-200`), pure white surface cards (`bg-white`), crisp slate borders (`border-slate-200`), slate-900 primary buttons and accents, and emerald/cyan/rose semantic indicators.
   - **Typography**: Google Font **Manrope** or **Inter** (weights: 400, 500, 600, 700) for high legibility across data-dense tables and code blocks.
   - **Dropdowns**: Every select dropdown across the entire platform must utilize **Select2** styled with the Tailwind Light theme (height 36–42px, slate border, smooth focus ring, clean search field).
   - **Markdown Rendering**: All AI responses and chat bubbles must be parsed via **Marked.js** and sanitized with **DOMPurify** to render formatted text (`**bold**`, `*italic*`, lists, headers, inline `<code>`) without raw markdown syntax characters.

---

## 2. S-SPARC AI Enhancement Specifications

### 2.1 Academic Context Verification Gate (`ssparc/courses.php`)
* **Trigger**: Accessed whenever a user clicks `S-SPARC AI` in the main navbar, or when an active session lacks course/assessment context.
* **Strict Enrollment Filter**:
  * For students, query only courses where `enrollment.student_id = :user_id` and `course.is_active = 1`.
  * If a student is not enrolled in any course, display a clean empty-state message explaining that course enrollment is required.
  * For lecturers/admins, list their authored courses or all active courses.
* **Dynamic Active Due Assessment Filtering**:
  * When a course is selected via Select2, dynamically populate the assessment dropdown with only active assignments satisfying E-STRANGE's due window:
    $$\text{submission\_open\_time} \le \text{NOW}() \quad \text{AND} \quad (\text{submission\_close\_time} > \text{NOW}() \ \lor \ \text{allow\_late\_submission} = 1)$$
* **Assessment Detail Preview Card**:
  * Real-time preview showing assignment description, submission deadline, and allowed file extensions (e.g., `.py`, `.java`, `.cpp`, `.sql`).
* **Context Persistence**:
  * Upon submission, store verified `current_course_id`, `current_course`, `assessment_id`, and `current_assessment` in the PHP session before redirecting to `chat.php`.

### 2.2 Chatbot Coding Assistant (`ssparc/chat.php`)
* **Verified Context Sub-Header**:
  * Prominent sub-navigation displaying the active class and assignment, a *Change Context* button (linking back to `courses.php`), *Prompting Tips* modal trigger, *New Chat*, and *Clear History*.
* **Inference Toolbar**:
  * Select2 dropdowns for Programming Language (Auto-detect, Python, JavaScript, Java, C, C++, Go, PHP, SQL) and Response Mode (*Code only*, *Summary short*, *Summary + Code + Explanation*).
* **Prompting Enhancements**:
  * One-click suggestion pills for common tasks (Factorial, Top 10 SQL, REST Endpoint, CSV Processing).
  * Sidebar with quick structured prompt templates (Function/Algorithm Implementation, Error Debugging, Code Refactoring & Optimization, Pytest Unit Test Generation).
* **Fenced Code Blocks**:
  * Dark-themed syntax containers (`bg-slate-900`), language header pill, and a responsive *Copy* button with feedback state.
* **Token Usage & Free Retrieval Card**:
  * Live monitoring of Dynamic Threshold, GPT tokens consumed, and current efficiency points.
  * Explicit notice that Semantic Retrieval from the database ($\ge 90\%$ similarity) is completely **FREE (0 Tokens)**.

### 2.3 Gamification Dashboard & Leaderboard (`ssparc/gamification.php`)
* **Weekly Token Usage & Threshold Chart**:
  * Chart.js Stacked Bar Chart displaying:
    1. **Used Tokens (GPT)**: Red bars representing billable inferencing.
    2. **Remaining Quota**: Cyan bars representing available allowance.
    3. **Threshold Limit**: Dashed slate-900 horizontal line representing the dynamic cutoff.
  * Period toggle (*Current Week* vs *All Time*) and course filter dropdown powered by Select2.
* **Scientific Points Calculation Formula**:
  * Clearly document the backend quota rule:
    $$\text{Threshold} = 1.10 \times \text{Average Peer Tokens for Same Assessment}$$
    $$\text{Points} = \begin{cases} 100.0 & \text{if } \text{Tokens Used} \le \text{Threshold} \\ \max\left(0, 100.0 - \frac{\text{Tokens Used} - \text{Threshold}}{\text{Threshold}} \times 100\right) & \text{if } \text{Tokens Used} > \text{Threshold} \end{cases}$$
* **Class & Assessment Leaderboard**:
  * Data-driven table with course and assessment filters.
  * Visual rank indicators (Gold, Silver, Bronze badges for top 3).
  * Distinct highlighted blue row for the currently authenticated student.
  * Green gradient pill badge for final calculated points.

### 2.4 Environmental Impact & Eco-Metrics (`ssparc/environmental_impact.php`)
* **Multi-Parameter Filter Bar**:
  * Select2 dropdowns for Timeframe (7 days, 30 days, 90 days, 1 year), Data Scope (*All My Activity*, *Per Course*, *Per Assessment*), and dynamic course/assessment selectors.
* **4 Primary Sustainability KPI Cards**:
  1. Total Energy Consumption ($kWh$ and $Wh$)
  2. Carbon Footprint ($kg\ CO_2e$)
  3. Cooling Water Footprint ($L$ / $mL$)
  4. Daily Average Energy Consumption ($kWh/\text{day}$)
* **Real-World Visual Equivalents Grid**:
  * Smartphone full charges ($12\ Wh$)
  * 9W LED bulb operational hours
  * Electric kettle boil cycles ($1500\ W$)
  * Gasoline passenger car travel ($0.192\ kg\ CO_2/km$)
  * Tree carbon absorption equivalent days ($21\ kg\ CO_2/\text{year}$)
  * Standard shower minutes ($9\ L/\text{min}$)
* **Timeseries Footprint Trend**:
  * Interactive multi-axis line chart tracking daily energy, carbon, and water footprint progression.
* **Scientific Methodology & Cloud Metrics**:
  * Grid Emission Factor: $0.384\ g\ CO_2e/Wh$
  * Power Usage Effectiveness (PUE): $1.12$
  * Water Usage Effectiveness (WUE Site): $0.30\ mL/Wh$
  * Grid Water Factor (WUE Source): $4.35\ mL/Wh$
  * Tiered Token Energy Model:
    * $\le 400\ \text{tokens}$: $0.0021775\ Wh/\text{token}$
    * $401\text{--}2000\ \text{tokens}$: $0.0015805\ Wh/\text{token}$
    * $> 2000\ \text{tokens}$: $0.00042026\ Wh/\text{token}$

---

## 3. E-STRANGE Modernization Specifications

### 3.1 Framework & Layout Transformation
1. **Migration from Bootstrap to Tailwind CSS**:
   - Replace legacy Bootstrap classes (`container`, `row`, `col-md-*`, `navbar-default`, `btn-primary`, `table-striped`, `panel`) with clean Tailwind utilities.
   - Standardize on modern flexbox/grid containers (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`, `grid grid-cols-1 md:grid-cols-3 gap-6`).
2. **Unified Role-Based Header (`_config.php`)**:
   - Modern sticky navigation bar with blurred background (`bg-white/80 backdrop-blur border-b border-slate-200`).
   - Clean brand identity: **E-STRANGE** with subtitle *Academic Code Intelligence*.
   - **Student Navigation**: Dashboard, My Courses, Peer Review, S-SPARC AI, Eco-Metrics, Account, Logout.
   - **Lecturer Navigation**: Dashboard, Manage Courses, Create Assignment, Plagiarism Matrix, Peer Review Setup, Sustainability Stats, Account, Logout.
   - **Admin Navigation**: System Dashboard, User Accounts, Academic Term Sync, Server Metrics, Logout.
   - Active route indicator with slate-900 pills and subtle hover transitions.

### 3.2 DataTables Overhaul
* **Styling & Layout**:
  - Replace raw HTML tables with modern styled DataTables.
  - Light slate table headers with uppercase 11px tracking (`text-xs font-bold text-slate-500 uppercase tracking-wider`).
  - Subtle row dividers (`divide-y divide-slate-100`) and zebra striping (`even:bg-slate-50/50 hover:bg-slate-50`).
  - Integrated search box with rounded borders and clear icon.
  - Compact, modern pagination buttons with active page in slate-900.
* **Plagiarism & Similarity Visualizer**:
  - Similarity score badges with clear semantic coloring:
    * $< 30\%$: Emerald badge (*Low Similarity*)
    * $30\%\text{--}69\%$: Amber badge (*Moderate Similarity*)
    * $\ge 70\%$: Rose badge (*High Suspicion / Potential Plagiarism*)
  - Side-by-side code diff modal with syntax highlighting for inspecting flagged pairs.

### 3.3 Dashboard Enhancements
1. **Student Dashboard (`student_dashboard.php`)**:
   - Summary stat cards: Enrolled Courses, Active Tasks Due, Completed Submissions, AI Tokens Used.
   - Table of active assignments due with countdown timers, description preview modals, and direct submission upload buttons.
   - Recent submission history with grading status and peer-review access.
2. **Lecturer Dashboard (`lecturer_dashboard.php`)**:
   - Course overview cards showing enrollment count, assignment count, and average similarity score.
   - Quick action bar: *Create New Assessment*, *Trigger Plagiarism Scan*, *Export Gradebook (CSV)*.
   - Plagiarism heatmap/matrix summarizing pairwise similarity across the student cohort.
3. **Authentication & Session (`index.php`, `_nosessionchecker.php`)**:
   - Sleek, centered split-screen or card-based login page with clear role selection, demo credentials helper, and validation alerts.

---

## 4. Backend & Database Architecture

1. **Unified Database (`db_semantic_final`)**:
   - Single MariaDB/MySQL database holding both E-STRANGE tables (`user`, `course`, `assessment`, `enrollment`, `submission`, `suspicion`, `peer_review`, etc.) and S-SPARC tables (`code_embeddings`, `chat_history`, `environmental_impact_logs`, `session_tokens`, `gpt_jobs`, `users`, `user_courses`).
2. **Single Sign-On (SSO) & Session Bridge**:
   - PHP session stores verified user data (`$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['role']`, `$_SESSION['name']`).
   - Frontend passes `X-User-ID` header to FastAPI backend on `http://127.0.0.1:5000`.
   - FastAPI validates user context and returns personalized course, assessment, gamification, and footprint data.
3. **Database Relational Integrity**:
   - All student course queries must join `course` via `enrollment` on `enrollment.student_id = user.user_id`.
   - All assessment queries must respect active due dates and course relations.

---

## 5. File Cleanup & Repository Sanitization

Remove legacy temporary files, redundant scripts, unused prototypes, and debug dumps to maintain a lean, production-grade workspace:
* Deprecate standalone `frontend/` prototype folder after confirming all features are fully migrated to `estrange/v2/v2/ssparc/`.
* Remove one-off scratch scripts in root (`test_chat.php`, `test_dashboards.php`, `import_estrange.py`, `import_by_table.py`) once migration is sealed.
* Ensure `.gitignore` ignores `__pycache__`, virtual environments, and temporary PHP session files.

---

## 6. Implementation & Verification Checklist

- [x] Unify database schema in `db_semantic_final` with all 841 E-STRANGE users, 17 courses, and 243 assessments.
- [x] Configure dedicated port `8088` for E-STRANGE PHP and port `5000` for FastAPI backend.
- [x] Build Tailwind Light Mode interface for S-SPARC (`_sso_bridge.php`, `courses.php`, `chat.php`, `gamification.php`, `environmental_impact.php`).
- [x] Enforce strict student enrollment filtering (only enrolled courses visible).
- [x] Enforce active assessment due filtering (`submission_open_time < NOW() < submission_close_time`).
- [x] Integrate Select2 across all dropdowns on all S-SPARC pages.
- [x] Integrate Marked.js and DOMPurify for full markdown formatting (`**bold**`, `*italic*`, code blocks, lists).
- [ ] Modernize core E-STRANGE pages (`student_dashboard.php`, `lecturer_dashboard.php`, `admin_dashboard.php`, `login.php`, `_config.php`) with Tailwind CSS.
- [ ] Standardize all UI text to 100% English.
- [ ] Upgrade all DataTables with custom Tailwind styling and export features.