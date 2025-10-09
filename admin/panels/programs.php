<?php
// Programs panel - CRUD

$msg=''; $type='';
try { $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(30) DEFAULT 'active',
  prospectus_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

// Handle create/update/status
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { throw new RuntimeException('Invalid request'); }
    if ($action==='create') {
      $name = trim($_POST['name'] ?? '');
      if ($name==='') throw new RuntimeException('Program name is required');
      $st = $pdo->prepare("INSERT INTO programs(name,status) VALUES(?, 'active')");
      $st->execute([sanitizeInput($name)]);
      $msg='Program created'; $type='success';
    } elseif ($action==='update') {
      $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
      if (!$id || $name==='') throw new RuntimeException('Program and ID required');
      $st=$pdo->prepare("UPDATE programs SET name=? WHERE id=?");
      $st->execute([sanitizeInput($name),$id]);
      $msg='Program updated'; $type='success';
    } elseif ($action==='toggle') {
      $id = (int)($_POST['id'] ?? 0); $status = $_POST['status'] ?? 'active';
      $st=$pdo->prepare("UPDATE programs SET status=? WHERE id=?");
      $st->execute([$status,$id]);
      $msg='Status changed'; $type='success';
    } elseif ($action==='upload_prospectus') {
      $id=(int)($_POST['id']??0);
      if(!$id) throw new RuntimeException('Program ID required');
      if (empty($_FILES['prospectus']['name']) || !is_uploaded_file($_FILES['prospectus']['tmp_name'])) {
        throw new RuntimeException('Select a PDF');
      }
      $ext = strtolower(pathinfo($_FILES['prospectus']['name'], PATHINFO_EXTENSION));
      if ($ext!=='pdf') throw new RuntimeException('Prospectus must be PDF');
      $dir = $_SERVER['DOCUMENT_ROOT'].'/uploads/prospectus';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $fname = 'program_'.$id.'_'.time().'.pdf';
      $dest = $dir.'/'.$fname;
      move_uploaded_file($_FILES['prospectus']['tmp_name'], $dest);
      $rel = '/uploads/prospectus/'.$fname;
      $st=$pdo->prepare("UPDATE programs SET prospectus_path=? WHERE id=?");
      $st->execute([$rel,$id]);
      $msg='Prospectus uploaded'; $type='success';
    }
  } catch (Throwable $e) { $msg='Failed: '.$e->getMessage(); $type='danger'; }
}

// Fetch list
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per=10; $offset=($page-1)*$per;
$params=[]; $where=[];
if ($q!==''){ $where[]='name LIKE ?'; $params[]="%$q%"; }
if ($status!==''){ $where[]='status=?'; $params[]=$status; }
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM programs $whereSql"); $st->execute($params); $total=(int)$st->fetchColumn(); } catch(Throwable $e){}
$pages=max(1,(int)ceil($total/$per));

$rows=[]; try{ $st=$pdo->prepare("SELECT * FROM programs $whereSql ORDER BY created_at DESC LIMIT $offset,$per"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="panel-card">
  <h3>Programs</h3>
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="programs">
    <input class="input" name="q" placeholder="Search name" value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <select class="input" name="status" style="max-width:160px">
      <option value="">All</option>
      <option value="active" <?php echo $status==='active'?'selected':''; ?>>Active</option>
      <option value="inactive" <?php echo $status==='inactive'?'selected':''; ?>>Inactive</option>
    </select>
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Name</th><th style="padding:10px">Status</th><th style="padding:10px">Actions</th></tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
        <tr><td colspan="3" style="padding:14px" class="muted">No programs found.</td></tr>
        <?php else: foreach($rows as $r): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px">
            <form method="post" action="?panel=programs" style="display:flex;gap:6px;align-items:center">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <input class="input" name="name" value="<?php echo htmlspecialchars($r['name']); ?>" style="max-width:320px">
              <button class="btn secondary" type="submit"><i class="bi bi-save"></i> Save</button>
            </form>
          </td>
          <td style="padding:10px"><span class="muted"><?php echo htmlspecialchars($r['status']); ?></span></td>
          <td style="padding:10px;display:flex;gap:6px">
            <form method="post" action="?panel=programs" onsubmit="return confirm('Toggle status?')">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="status" value="<?php echo $r['status']==='active'?'inactive':'active'; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit"><i class="bi bi-toggle2-<?php echo $r['status']==='active'?'on':'off'; ?>"></i> <?php echo $r['status']==='active'?'Deactivate':'Activate'; ?></button>
            </form>
            <form method="post" action="?panel=programs" enctype="multipart/form-data" onsubmit="return confirm('Upload prospectus PDF?')">
              <input type="hidden" name="action" value="upload_prospectus">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <input class="input" type="file" name="prospectus" accept="application/pdf" style="max-width:220px">
              <button class="btn secondary" type="submit"><i class="bi bi-file-earmark-pdf"></i> Upload PDF</button>
            </form>
            <?php if (!empty($r['prospectus_path'])): ?>
              <a class="btn secondary" href="<?php echo htmlspecialchars($r['prospectus_path']); ?>" target="_blank"><i class="bi bi-eye"></i> View</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
  <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
    <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
      <a class="btn secondary" href="?panel=programs&page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel-card">
  <h3>Add Program</h3>
  <form method="post" action="?panel=programs" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <div style="min-width:260px"><label class="form-label">Program Name</label><input class="input" name="name" required></div>
    <button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Add Program</button>
  </form>
</div>


