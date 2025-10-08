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

        $brandColor = trim($_POST['brand_color'] ?? '#2563eb');
        $institutionName = trim($_POST['institution_name'] ?? '');
        $institutionEmail = trim($_POST['institution_email'] ?? '');
        $institutionPhone = trim($_POST['institution_phone'] ?? '');
        $timezone = trim($_POST['timezone'] ?? 'UTC');
        $currency = trim($_POST['currency'] ?? 'GHS');

        upsert_setting($pdo, 'brand_color', $brandColor);
        upsert_setting($pdo, 'institution_name', $institutionName);
        upsert_setting($pdo, 'institution_email', $institutionEmail);
        upsert_setting($pdo, 'institution_phone', $institutionPhone);
        upsert_setting($pdo, 'timezone', $timezone);
        upsert_setting($pdo, 'currency', $currency);

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
];
$settings = $defaults;
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['config_key']] = $row['config_value'];
    }
} catch (Throwable $e) { /* ignore */ }

?>

<?php if ($message): ?>
  <div class="card" style="border-left:4px solid <?php echo $messageType==='success' ? '#10b981' : '#ef4444'; ?>; margin-bottom:12px;">
    <strong><?php echo htmlspecialchars(strtoupper($messageType)); ?>:</strong> <?php echo htmlspecialchars($message); ?>
  </div>
<?php endif; ?>

<div class="panel-card">
  <h3>Branding</h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
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
    <div class="grid cols-2" style="margin-top:12px;">
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
    <div class="form-actions">
      <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Save Settings</button>
    </div>
  </form>
</div>


