<?php
// Students panel - directory with simple add form

$message = '';$messageType='';
try { $pdo->exec("CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100), last_name VARCHAR(100), email VARCHAR(150), phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add_student'){
  try{
    $fn=trim($_POST['first_name']??'');
    $ln=trim($_POST['last_name']??'');
    $em=trim($_POST['email']??'');
    $ph=trim($_POST['phone']??'');
    if($fn===''){ throw new RuntimeException('First name required'); }
    $stmt=$pdo->prepare("INSERT INTO students(first_name,last_name,email,phone) VALUES(?,?,?,?)");
    $stmt->execute([$fn,$ln,$em,$ph]);
    $message='Student added successfully';$messageType='success';
  }catch(Throwable $e){ $message='Failed: '.$e->getMessage();$messageType='danger'; }
}

$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage=10; $offset=($page-1)*$perPage;
$where=''; $params=[]; if($q!==''){ $where='WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $params=["%$q%","%$q%","%$q%"]; }

$total=0; try{ $st=$pdo->prepare("SELECT COUNT(*) FROM students $where"); $st->execute($params); $total=(int)$st->fetchColumn(); }catch(Throwable $e){ $total=0; }
$pages=max(1,(int)ceil($total/$perPage));

$rows=[]; try{ $st=$pdo->prepare("SELECT * FROM students $where ORDER BY created_at DESC LIMIT $offset,$perPage"); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){ $rows=[]; }
?>

<?php if($message): ?>
  <div class="card" style="border-left:4px solid <?php echo $messageType==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="panel-card">
  <h3>Students</h3>
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="students">
    <input class="input" name="q" placeholder="Search name/email" value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <button class="btn" type="submit"><i class="bi bi-search"></i> Search</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Name</th><th style="padding:10px">Email</th><th style="padding:10px">Phone</th><th style="padding:10px">Created</th></tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="4" style="padding:14px" class="muted">No students found.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px"><?php echo htmlspecialchars(($r['first_name']??'').' '.($r['last_name']??'')); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars($r['email']??''); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars($r['phone']??''); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
  <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
    <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
      <a class="btn secondary" href="?panel=students&page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel-card">
  <h3>Add Student</h3>
  <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <input type="hidden" name="action" value="add_student">
    <div><label class="form-label">First Name</label><input class="input" name="first_name" required></div>
    <div><label class="form-label">Last Name</label><input class="input" name="last_name"></div>
    <div><label class="form-label">Email</label><input class="input" name="email"></div>
    <div><label class="form-label">Phone</label><input class="input" name="phone"></div>
    <div style="grid-column:1/-1"><button class="btn" type="submit"><i class="bi bi-person-plus"></i> Add Student</button></div>
  </form>
</div>


