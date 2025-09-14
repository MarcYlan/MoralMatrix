<?php
// view_student.php

include '../config.php';
include '../includes/header.php';

$servername = $database_settings['servername'];
$username   = $database_settings['username'];
$password   = $database_settings['password'];
$dbname     = $database_settings['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['student_id'])) {
    die("No student selected.");
}

$student_id = $_GET['student_id'];

// ---------- Fetch student ----------
$sql  = "SELECT * FROM student_account WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result  = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// ---------- Fetch violations ----------
$violations = [];
$sqlv = "SELECT violation_id, offense_category, offense_type, offense_details, description, reported_at
         FROM student_violation
         WHERE student_id = ?
         ORDER BY reported_at DESC, violation_id DESC";
$stmtv = $conn->prepare($sqlv);
$stmtv->bind_param("s", $student_id);
$stmtv->execute();
$resv = $stmtv->get_result();
while ($row = $resv->fetch_assoc()) {
    $violations[] = $row;
}
$stmtv->close();

$conn->close();

// Root-absolute path for this folder (e.g., /MoralMatrix/ccdu)
$selfDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');

// Helpers
function e($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function pretty($s){
  // Normalize underscores/dashes & collapse spaces, then Title Case
  $s = str_replace(['_', '-'], ' ', (string)$s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return ucwords($s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Profile</title>
  <link rel="stylesheet" href="/MoralMatrix/css/global.css">
</head>
<body>

<!-- Sidebar toggle (hamburger) -->
<button id="sidebarToggle" class="sidebar-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">☰</button>

<!-- Off-canvas sidebar -->
<aside id="sidebar" class="sidebar" aria-hidden="true">
  <div class="sidebar-header">
    <span>Menu</span>
    <button id="sidebarClose" class="sidebar-close" aria-label="Close">✕</button>
  </div>

  <div id="pageButtons" class="sidebar-links">
    <?php include 'page_buttons.php' ?>
  </div>
</aside>

<!-- Backdrop for the sidebar -->
<div id="sidebarBackdrop" class="sidebar-backdrop hidden"></div>

<div class="right-container">
  <?php if ($student): ?>
    <div class="profile">
  <div class="profile-left">
    <img src="<?= !empty($student['photo']) ? '../admin/uploads/'.e($student['photo']) : 'placeholder.png' ?>" alt="Profile">
    <p><strong>Student ID:</strong> <?= e($student['student_id']) ?></p>
  </div>

  <h2 class="profile-name">
    <?= e(trim($student['first_name'].' '.$student['middle_name'].' '.$student['last_name'])) ?>
  </h2>

  <div class="profile-meta">
    <p><strong>Course:</strong> <?= e($student['course']) ?></p>
    <p><strong>Year Level:</strong> <?= e($student['level']) ?></p>
    <p><strong>Section:</strong> <?= e($student['section']) ?></p>
    <p><strong>Institute:</strong> <?= e($student['institute']) ?></p>
    <p><strong>Guardian:</strong> <?= e($student['guardian']) ?> (<?= e($student['guardian_mobile']) ?>)</p>
    <p><strong>Email:</strong> <?= e($student['email']) ?></p>
    <p><strong>Mobile:</strong> <?= e($student['mobile']) ?></p>
  </div>
</div>
  <?php else: ?>
    <p>Student not found.</p>
  <?php endif; ?>

  <div class="">
    <div class="add-violation-btn">
      <a class="btn" href="<?= $selfDir ?>/add_violation.php?student_id=<?= urlencode($student_id) ?>">Add Violation</a>
    </div>

    <div class="violationHistory-container" id="violationHistory">
      <?php if (empty($violations)): ?>
        <p>No Violations Recorded.</p>
      <?php else: ?>
        <div class="cards-grid">
          <?php foreach ($violations as $v):
            $catRaw = (string)($v['offense_category'] ?? '');
            $typeRaw = (string)($v['offense_type'] ?? '');
            $cat  = e($catRaw);
            $type = e($typeRaw);
            $desc = e($v['description'] ?? '');

            // Robust date display (avoid 1970 if value is bad)
            $date = '—';
            if (!empty($v['reported_at'])) {
              $ts = strtotime($v['reported_at']);
              if ($ts) $date = date('M d, Y h:i A', $ts);
            }

            // Build chips from offense_details (JSON array or string)
            $chips = [];
            if (!empty($v['offense_details'])) {
              $decoded = json_decode($v['offense_details'], true);
              if (is_array($decoded)) {
                foreach ($decoded as $d) {
                  if (is_scalar($d)) $chips[] = e(pretty($d));
                }
              } elseif (is_string($v['offense_details'])) {
                $chips[] = e(pretty($v['offense_details']));
              }
            }

            $href = $selfDir . "/violation_view.php?id=" . urlencode($v['violation_id']) . "&student_id=" . urlencode($student_id);
          ?>
            <a class="profile-card" data-violation-link href="<?= e($href) ?>">
              <img src="<?= $selfDir ?>/violation_photo.php?id=<?= urlencode($v['violation_id']) ?>" alt="Evidence" onerror="this.style.display='none'">
              <div class="info">
                <p><strong>Category: </strong>
                  <span class="badge badge-<?= strtolower($catRaw) ?>"><?= e(pretty($catRaw)) ?></span>
                </p>
                <p><strong>Type:</strong> <?= e(pretty($typeRaw)) ?></p>

                <?php if (!empty($chips)): ?>
                  <p><strong>Details:</strong>
                    <?php foreach ($chips as $c): ?>
                      <span class="chip"><?= $c ?></span>
                    <?php endforeach; ?>
                  </p>
                <?php endif; ?>

                <p><strong>Reported:</strong> <?= e($date) ?></p>

                <?php if (!empty($desc)): ?>
                  <p><strong>Description:</strong> <?= $desc ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Backdrop -->
<div id="violationBackdrop" class="modal-backdrop hidden" aria-hidden="true"></div>

<!-- Modal -->
<div id="violationModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="violationModalTitle" aria-hidden="true">
  <h2 id="violationModalTitle" class="sr-only">Violation details</h2>
  <button type="button" class="modal-close" id="violationClose" aria-label="Close">✕</button>
  <div id="violationContent" class="modal-content">
    <!-- violation_view.php?modal=1 will be injected here -->
  </div>
</div>

<script>
  // Ensure hidden state on load (avoids FOUC in fast navigations)
  window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('violationBackdrop')?.classList.add('hidden');
    document.getElementById('violationModal')?.classList.add('hidden');
    document.body.classList.remove('modal-open');
  });

  (function () {
    const backdrop = document.getElementById('violationBackdrop');
    const modal    = document.getElementById('violationModal');
    const content  = document.getElementById('violationContent');
    const btnClose = document.getElementById('violationClose');

    function openModalWith(url) {
      fetch(url, { credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error('Failed to load violation'); return r.text(); })
        .then(html => {
          content.innerHTML = html;
          backdrop.classList.remove('hidden');
          modal.classList.remove('hidden');
          backdrop.setAttribute('aria-hidden', 'false');
          modal.setAttribute('aria-hidden', 'false');
          document.body.classList.add('modal-open');
          if (!history.state || history.state.modalOpen !== true) {
            history.pushState({ modalOpen: true }, '');
          }
        })
        .catch(err => alert('Unable to load violation: ' + err.message));
    }

    function closeModal() {
      backdrop.classList.add('hidden');
      modal.classList.add('hidden');
      backdrop.setAttribute('aria-hidden', 'true');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
      if (history.state && history.state.modalOpen === true) history.back();
    }

    // Intercept clicks ONLY on links that opted-in via data-violation-link
    document.addEventListener('click', function (e) {
      const link = e.target.closest('a[data-violation-link]');
      if (!link) return;

      // allow new-tab/middle-click
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

      e.preventDefault();
      const url = link.href + (link.href.includes('?') ? '&' : '?') + 'modal=1';
      openModalWith(url);
    });

    btnClose.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // Handle back/forward (when the modal was pushed into history)
    window.addEventListener('popstate', function () {
      if (!backdrop.classList.contains('hidden') || !modal.classList.contains('hidden')) {
        backdrop.classList.add('hidden');
        modal.classList.add('hidden');
        backdrop.setAttribute('aria-hidden', 'true');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
      }
    });
  })();

(function(){
  const sidebar   = document.getElementById('sidebar');
  const openBtn   = document.getElementById('sidebarToggle');
  const closeBtn  = document.getElementById('sidebarClose');
  const backdrop  = document.getElementById('sidebarBackdrop');

  function openSidebar(){
    sidebar.classList.add('open');
    sidebar.setAttribute('aria-hidden','false');
    openBtn.setAttribute('aria-expanded','true');
    backdrop.classList.remove('hidden');
    document.body.classList.add('modal-open'); // reuse scroll lock
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden','true');
    openBtn.setAttribute('aria-expanded','false');
    backdrop.classList.add('hidden');
    document.body.classList.remove('modal-open');
  }

  openBtn.addEventListener('click', openSidebar);
  closeBtn.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') closeSidebar(); });
})();

</script>
</body>
</html>
