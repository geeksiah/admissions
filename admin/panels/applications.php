<?php
// Applications panel - list with filters and pagination
// Requires $pdo available from dashboard

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR p.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a LEFT JOIN students s ON s.id=a.student_id LEFT JOIN programs p ON p.id=a.program_id $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) { $total = 0; }

$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$rows = [];
try {
    $stmt = $pdo->prepare("SELECT a.id, a.status, a.created_at, s.first_name, s.last_name, p.name AS program
        FROM applications a
        LEFT JOIN students s ON s.id=a.student_id
        LEFT JOIN programs p ON p.id=a.program_id
        $whereSql
        ORDER BY a.created_at DESC
        LIMIT $offset, $perPage");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $rows = []; }
?>

<div class="panel-card">
  <h3>Applications</h3>
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="applications">
    <select name="status" class="input" style="max-width:160px">
      <option value="">All Status</option>
      <?php foreach (["pending","under_review","approved","rejected"] as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $status===$opt?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$opt)); ?></option>
      <?php endforeach; ?>
    </select>
    <input class="input" name="q" placeholder="Search name/program" value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:10px">ID</th>
          <th style="padding:10px">Applicant</th>
          <th style="padding:10px">Program</th>
          <th style="padding:10px">Status</th>
          <th style="padding:10px">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="padding:14px" class="muted">No applications found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px">#<?php echo (int)$r['id']; ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??''))); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars($r['program'] ?? ''); ?></td>
            <td style="padding:10px"><span class="muted"><?php echo ucwords(str_replace('_',' ', $r['status'])); ?></span></td>
            <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
      <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
        <a class="btn secondary" href="?panel=applications&page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&q=<?php echo urlencode($q); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>


