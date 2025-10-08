<?php
// Settings panel - branding, institution and uploads
// Expects $pdo and session to be available from dashboard.php

if (!function_exists('upsert_setting')) {
    function upsert_setting(PDO $pdo, string $key, string $value): void {
        $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        $stmt->execute([$key, $value]);
    }
}

$message = '';
$messageType = '';

// Ensure uploads dirs
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads', 0775, true);
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads/logos', 0775, true);
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars', 0775, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF optional if using generateCSRFToken
        if (function_exists('validateCSRFToken')) {
            if (!validateCSRFToken($_POST['csrf'] ?? '')) {
                throw new RuntimeException('Invalid CSRF token');
            }
        }

        // Persist scalar settings (whitelist)
        $whitelist = [
            'brand_color','institution_name','institution_email','institution_phone','timezone','currency',
            // Payments
            'paystack_enabled','paystack_public_key','paystack_secret_key','paystack_mode',
            'flutter_enabled','flutter_public_key','flutter_secret_key','flutter_mode',
            'stripe_enabled','stripe_public_key','stripe_secret_key','stripe_mode',
            // Email/SMS
            'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption',
            'sms_provider','sms_key','sms_sender',
            // Application options
            'mode_toggle','application_fee','acceptance_fee','academic_session','voucher_required',
            'documents_required'
        ];
        foreach ($whitelist as $key) {
            if (isset($_POST[$key])) {
                upsert_setting($pdo, $key, trim((string)$_POST[$key]));
            }
        }

        // Logo upload
        if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
                throw new RuntimeException('Unsupported logo format');
            }
            $logoTarget = '/uploads/logos/logo.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $abs = $_SERVER['DOCUMENT_ROOT'] . $logoTarget;
            move_uploaded_file($_FILES['logo']['tmp_name'], $abs);
            upsert_setting($pdo, 'logo_path', $logoTarget);
        }

        // Avatar upload for current user
        if (!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name']) && isset($_SESSION['user_id'])) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
                throw new RuntimeException('Unsupported avatar format');
            }
            $avatarTarget = '/uploads/avatars/' . $_SESSION['user_id'] . '.png';
            $abs = $_SERVER['DOCUMENT_ROOT'] . $avatarTarget;
            // Normalize to PNG by moving as-is; conversion skipped for simplicity
            move_uploaded_file($_FILES['avatar']['tmp_name'], $abs);
        }

        $message = 'Settings saved successfully';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'Save failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Load current values
$defaults = [
    'brand_color' => '#2563eb',
    'logo_path' => '/uploads/logos/logo.png',
    'institution_name' => '',
    'institution_email' => '',
    'institution_phone' => '',
    'timezone' => 'UTC',
    'currency' => 'GHS',
    // Payments defaults
    'paystack_enabled' => '0', 'paystack_public_key' => '', 'paystack_secret_key' => '', 'paystack_mode' => 'test',
    'flutter_enabled' => '0', 'flutter_public_key' => '', 'flutter_secret_key' => '', 'flutter_mode' => 'test',
    'stripe_enabled' => '0', 'stripe_public_key' => '', 'stripe_secret_key' => '', 'stripe_mode' => 'test',
    // Email/SMS
    'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '', 'smtp_encryption' => 'tls',
    'sms_provider' => '', 'sms_key' => '', 'sms_sender' => '',
    // Application options
    'mode_toggle' => 'pay_after', 'application_fee' => '0', 'acceptance_fee' => '0', 'academic_session' => date('Y'),
    'voucher_required' => '0', 'documents_required' => '1'
];
$settings = $defaults;
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['config_key']] = $row['config_value'];
    }
} catch (Throwable $e) { /* ignore */ }

?>

<div id="toastArea" class="toast-area"></div>
<?php if ($message): ?>
  <script>
    document.addEventListener('DOMContentLoaded',function(){
      function makeToast(text,type){
        var area=document.getElementById('toastArea');
        var t=document.createElement('div');
        t.className='toast '+(type||'info');
        t.innerHTML='<span>'+text+'</span><button class="close" aria-label="Close">&times;</button>';
        t.querySelector('.close').onclick=function(){ t.remove(); };
        area.appendChild(t);
        setTimeout(function(){ t.remove(); },4000);
      }
      makeToast('<?php echo htmlspecialchars($message); ?>','<?php echo $messageType==='success' ? 'success' : 'error'; ?>');
    });
  </script>
<?php endif; ?>

<style>
.tabs{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.tab-btn{background:var(--card);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;cursor:pointer}
.tab-btn.active{background:var(--surface-hover)}
.tab-pane{display:none}
.tab-pane.active{display:block}
</style>

<div class="panel-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-branding">Branding</button>
    <button class="tab-btn" data-tab="tab-institution">Institution</button>
    <button class="tab-btn" data-tab="tab-payments">Payments</button>
    <button class="tab-btn" data-tab="tab-email">Email/SMS</button>
    <button class="tab-btn" data-tab="tab-options">Application Options</button>
  </div>

  <form method="post" enctype="multipart/form-data" id="settingsForm">
    <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
    <div id="tab-branding" class="tab-pane active">
      <div class="grid cols-2">
      <div>
        <label class="form-label">Brand Color</label>
        <input class="input" name="brand_color" value="<?php echo htmlspecialchars($settings['brand_color']); ?>" placeholder="#2563eb">
      </div>
      <div>
        <label class="form-label">Logo</label>
        <input type="file" name="logo" class="input">
        <?php if (!empty($settings['logo_path'])): ?>
          <div class="muted" style="margin-top:6px;">Current: <?php echo htmlspecialchars($settings['logo_path']); ?></div>
        <?php endif; ?>
      </div>
      </div>
    </div>

    <div id="tab-institution" class="tab-pane">
      <div class="grid cols-2">
      <div>
        <label class="form-label">Institution Name</label>
        <input class="input" name="institution_name" value="<?php echo htmlspecialchars($settings['institution_name']); ?>">
      </div>
      <div>
        <label class="form-label">Email</label>
        <input class="input" name="institution_email" value="<?php echo htmlspecialchars($settings['institution_email']); ?>">
      </div>
      <div>
        <label class="form-label">Phone</label>
        <input class="input" name="institution_phone" value="<?php echo htmlspecialchars($settings['institution_phone']); ?>">
      </div>
      <div>
        <label class="form-label">Timezone</label>
        <input class="input" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>">
      </div>
      <div>
        <label class="form-label">Currency</label>
        <input class="input" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>">
      </div>
      <div>
        <label class="form-label">My Avatar</label>
        <input type="file" name="avatar" class="input">
      </div>
      </div>
    </div>

    <div id="tab-payments" class="tab-pane">
      <div class="grid cols-2">
        <div>
          <h4 class="muted">Paystack</h4>
          <label class="form-label">Enable</label>
          <select class="input" name="paystack_enabled"><option value="1" <?php echo $settings['paystack_enabled']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['paystack_enabled']=='0'?'selected':''; ?>>No</option></select>
          <label class="form-label">Mode</label>
          <select class="input" name="paystack_mode"><option value="test" <?php echo $settings['paystack_mode']=='test'?'selected':''; ?>>Test</option><option value="live" <?php echo $settings['paystack_mode']=='live'?'selected':''; ?>>Live</option></select>
          <label class="form-label">Public Key</label>
          <input class="input" name="paystack_public_key" value="<?php echo htmlspecialchars($settings['paystack_public_key']); ?>">
          <label class="form-label">Secret Key</label>
          <input class="input" name="paystack_secret_key" value="<?php echo htmlspecialchars($settings['paystack_secret_key']); ?>">
        </div>
        <div>
          <h4 class="muted">Flutterwave</h4>
          <label class="form-label">Enable</label>
          <select class="input" name="flutter_enabled"><option value="1" <?php echo $settings['flutter_enabled']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['flutter_enabled']=='0'?'selected':''; ?>>No</option></select>
          <label class="form-label">Mode</label>
          <select class="input" name="flutter_mode"><option value="test" <?php echo $settings['flutter_mode']=='test'?'selected':''; ?>>Test</option><option value="live" <?php echo $settings['flutter_mode']=='live'?'selected':''; ?>>Live</option></select>
          <label class="form-label">Public Key</label>
          <input class="input" name="flutter_public_key" value="<?php echo htmlspecialchars($settings['flutter_public_key']); ?>">
          <label class="form-label">Secret Key</label>
          <input class="input" name="flutter_secret_key" value="<?php echo htmlspecialchars($settings['flutter_secret_key']); ?>">
        </div>
        <div>
          <h4 class="muted">Stripe</h4>
          <label class="form-label">Enable</label>
          <select class="input" name="stripe_enabled"><option value="1" <?php echo $settings['stripe_enabled']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['stripe_enabled']=='0'?'selected':''; ?>>No</option></select>
          <label class="form-label">Mode</label>
          <select class="input" name="stripe_mode"><option value="test" <?php echo $settings['stripe_mode']=='test'?'selected':''; ?>>Test</option><option value="live" <?php echo $settings['stripe_mode']=='live'?'selected':''; ?>>Live</option></select>
          <label class="form-label">Public Key</label>
          <input class="input" name="stripe_public_key" value="<?php echo htmlspecialchars($settings['stripe_public_key']); ?>">
          <label class="form-label">Secret Key</label>
          <input class="input" name="stripe_secret_key" value="<?php echo htmlspecialchars($settings['stripe_secret_key']); ?>">
        </div>
      </div>
    </div>

    <div id="tab-email" class="tab-pane">
      <div class="grid cols-2">
        <div>
          <h4 class="muted">SMTP</h4>
          <label class="form-label">Host</label>
          <input class="input" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
          <label class="form-label">Port</label>
          <input class="input" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
          <label class="form-label">Username</label>
          <input class="input" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user']); ?>">
          <label class="form-label">Password</label>
          <input class="input" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass']); ?>">
          <label class="form-label">Encryption</label>
          <select class="input" name="smtp_encryption"><option value="tls" <?php echo $settings['smtp_encryption']=='tls'?'selected':''; ?>>TLS</option><option value="ssl" <?php echo $settings['smtp_encryption']=='ssl'?'selected':''; ?>>SSL</option></select>
        </div>
        <div>
          <h4 class="muted">SMS</h4>
          <label class="form-label">Provider</label>
          <input class="input" name="sms_provider" value="<?php echo htmlspecialchars($settings['sms_provider']); ?>" placeholder="e.g., twilio, hubtel">
          <label class="form-label">API Key / Token</label>
          <input class="input" name="sms_key" value="<?php echo htmlspecialchars($settings['sms_key']); ?>">
          <label class="form-label">Sender</label>
          <input class="input" name="sms_sender" value="<?php echo htmlspecialchars($settings['sms_sender']); ?>">
        </div>
      </div>
    </div>

    <div id="tab-options" class="tab-pane">
      <div class="grid cols-2">
        <div>
          <h4 class="muted">Mode & Fees</h4>
          <label class="form-label">Application Mode</label>
          <select class="input" name="mode_toggle"><option value="pay_after" <?php echo $settings['mode_toggle']=='pay_after'?'selected':''; ?>>Pay during/after application</option><option value="voucher" <?php echo $settings['mode_toggle']=='voucher'?'selected':''; ?>>Voucher serial/PIN required</option></select>
          <label class="form-label">Application Fee</label>
          <input class="input" name="application_fee" value="<?php echo htmlspecialchars($settings['application_fee']); ?>">
          <label class="form-label">Acceptance Fee</label>
          <input class="input" name="acceptance_fee" value="<?php echo htmlspecialchars($settings['acceptance_fee']); ?>">
          <label class="form-label">Academic Session</label>
          <input class="input" name="academic_session" value="<?php echo htmlspecialchars($settings['academic_session']); ?>">
        </div>
        <div>
          <h4 class="muted">Requirements</h4>
          <label class="form-label">Voucher Required</label>
          <select class="input" name="voucher_required"><option value="1" <?php echo $settings['voucher_required']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['voucher_required']=='0'?'selected':''; ?>>No</option></select>
          <label class="form-label">Documents Required</label>
          <select class="input" name="documents_required"><option value="1" <?php echo $settings['documents_required']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['documents_required']=='0'?'selected':''; ?>>No</option></select>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Save Settings</button>
      <button class="btn secondary" type="button" id="applyBrand">Apply Without Refresh</button>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    var brandInput=document.querySelector('input[name="brand_color"]');
    var applyBtn=document.getElementById('applyBrand');
    if (applyBtn && brandInput) {
      applyBtn.addEventListener('click', function(){
        document.documentElement.style.setProperty('--brand', brandInput.value || '#2563eb');
        var area=document.getElementById('toastArea');
        var t=document.createElement('div');
        t.className='toast info';
        t.innerHTML='<span>Brand color applied.</span><button class="close">&times;</button>';
        t.querySelector('.close').onclick=function(){ t.remove(); };
        area.appendChild(t);
        setTimeout(function(){ t.remove(); },3000);
      });
    }
  });
  // Simple tabs
  (function(){
    const tabs=document.querySelectorAll('.tab-btn');
    const panes=document.querySelectorAll('.tab-pane');
    tabs.forEach(btn=>btn.addEventListener('click',function(){
      tabs.forEach(b=>b.classList.remove('active')); this.classList.add('active');
      const id=this.dataset.tab; panes.forEach(p=>p.classList.toggle('active', p.id===id));
    }));
  })();
</script>


