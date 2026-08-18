<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to E-STRANGE login if session is not active
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$sso_user_id = $_SESSION['user_id'];
$sso_username = $_SESSION['username'] ?? 'User';
$sso_name = $_SESSION['name'] ?? $sso_username;
$sso_role = $_SESSION['role'] ?? 'student';

// Connect to database if not already connected
require_once(__DIR__ . '/../_config.php');

function renderSSOHeader($activePage = 'chat', $title = 'Chat Assistant') {
    global $sso_name, $sso_role, $sso_user_id;
    $backLink = ($sso_role === 'student') ? '../student_dashboard.php' : (($sso_role === 'lecturer') ? '../lecturer_dashboard.php' : '../admin_dashboard.php');
    ?>
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
            AI
          </div>
          <div class="min-w-0">
            <div class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
              <span><?= htmlspecialchars($title) ?></span>
              <span class="text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">S-SPARC AI</span>
            </div>
            <div class="text-xs text-slate-500 truncate max-w-md">
              User: <strong class="text-slate-800"><?= htmlspecialchars($sso_name) ?></strong> &mdash; Role: <strong class="text-slate-800"><?= htmlspecialchars(ucfirst($sso_role)) ?></strong>
            </div>
          </div>
        </div>
        <nav class="flex shrink-0 items-center gap-1.5 text-xs font-medium">
          <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'home') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="index.php">Home</a>
          <?php if ($sso_role === 'student'): ?>
            <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'courses') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="courses.php">Courses</a>
            <?php if ($activePage !== 'courses' && !empty($_SESSION['assessment_id']) && !empty($_SESSION['current_course_id'])): ?>
              <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'chat') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="chat.php">Chat Assistant</a>
              <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'gamification') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="gamification.php">Gamification</a>
              <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'environmental_impact') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="environmental_impact.php">Eco-Metrics</a>
            <?php endif; ?>
          <?php else: ?>
            <?php if ($sso_role === 'admin'): ?>
              <a class="inline-flex h-8 items-center rounded-lg px-3 transition text-slate-600 hover:bg-slate-100 hover:text-slate-900" href="../admin_ssparc_config.php">AI Config</a>
            <?php endif; ?>
            <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'gamification') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="gamification.php">Gamification Standings</a>
            <a class="inline-flex h-8 items-center rounded-lg px-3 transition <?= ($activePage === 'environmental_impact') ? 'bg-[#00A0A5] text-white font-semibold shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>" href="environmental_impact.php">Eco-Metrics</a>
          <?php endif; ?>
          <a class="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-slate-700 hover:border-slate-400 hover:bg-slate-50 transition shadow-xs" href="<?= $backLink ?>">Back to E-STRANGE</a>
          <form class="m-0" action="../index.php" method="post">
            <input type="hidden" name="logout" value="logout">
            <button class="inline-flex h-8 items-center rounded-lg border border-red-200 bg-red-50/50 px-3 text-red-700 hover:bg-red-100 hover:border-red-300 transition shadow-xs font-semibold" type="submit">Logout</button>
          </form>
        </nav>
      </div>
    </header>
    <?php
}

?>
