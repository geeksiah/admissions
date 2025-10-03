<?php
// Handle avatar upload
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'student_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                // Update user record with avatar path
                $avatarPath = 'uploads/avatars/' . $fileName;
                $userModel->update($_SESSION['user_id'], ['avatar' => $avatarPath]);
                
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Avatar updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
                
                // Add JavaScript to update avatar dynamically
                echo '<script>
                        setTimeout(function() {
                            // Update avatar in profile section
                            const profileContainer = document.querySelector(".card-body .mb-3");
                            if (profileContainer) {
                                // Check if there\'s already an img
                                let profileImg = profileContainer.querySelector("img");
                                if (profileImg) {
                                    profileImg.src = "../' . $avatarPath . '?t=' . time() . '";
                                } else {
                                    // Replace initials div with img
                                    const initialsDiv = profileContainer.querySelector(".rounded-circle");
                                    if (initialsDiv) {
                                        initialsDiv.outerHTML = \'<img src="../' . $avatarPath . '?t=' . time() . '" alt="Profile Picture" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">\';
                                    }
                                }
                            }
                            
                            // Update avatar in top bar - convert initials to image
                            const topBarAvatar = document.querySelector(".user-avatar");
                            if (topBarAvatar) {
                                // Check if it already has an img
                                let topBarImg = topBarAvatar.querySelector("img");
                                if (topBarImg) {
                                    topBarImg.src = "../' . $avatarPath . '?t=' . time() . '";
                                } else {
                                    // Convert initials to image
                                    const currentContent = topBarAvatar.innerHTML;
                                    topBarAvatar.innerHTML = \'<img src="../' . $avatarPath . '?t=' . time() . '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">\';
                                }
                            }
                            
                            // Also update any other avatar instances
                            const allAvatars = document.querySelectorAll("[data-bs-toggle=\\"dropdown\\"]");
                            allAvatars.forEach(avatar => {
                                if (avatar.classList.contains("user-avatar")) {
                                    const img = avatar.querySelector("img");
                                    if (img) {
                                        img.src = "../' . $avatarPath . '?t=' . time() . '";
                                    }
                                }
                            });
                        }, 500);
                      </script>';
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>Failed to upload avatar. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>Invalid file type. Please upload JPG, PNG, or GIF files only.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
    }
}

// Handle profile updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if ($firstName && $lastName && $email) {
        try {
            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($userModel->update($_SESSION['user_id'], $updateData)) {
                // Update session data
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $email;
                
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Profile updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>Failed to update profile. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>Error updating profile: ' . htmlspecialchars($e->getMessage()) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($currentPassword && $newPassword && $confirmPassword) {
        if ($newPassword !== $confirmPassword) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>New passwords do not match.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        } elseif (strlen($newPassword) < 6) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>New password must be at least 6 characters long.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        } else {
            try {
                // Verify current password
                $user = $userModel->getById($_SESSION['user_id']);
                if (password_verify($currentPassword, $user['password'])) {
                    $updateData = [
                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($userModel->update($_SESSION['user_id'], $updateData)) {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>Password changed successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>Failed to change password. Please try again.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>';
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i>Current password is incorrect.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>Error changing password: ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-12">
        <h5 class="mb-0">
            <i class="bi bi-person me-2"></i>
            My Profile
        </h5>
        <p class="text-muted mt-1 mb-0">Manage your personal information and account settings.</p>
    </div>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-person-lines-fill me-2"></i>
                    Personal Information
                </h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Account Settings -->
    <div class="col-lg-4 mb-3">
        <!-- Profile Picture -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-camera me-2"></i>
                    Profile Picture
                </h6>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="../<?php echo htmlspecialchars($currentUser['avatar']); ?>" 
                             alt="Profile Picture" class="rounded-circle" 
                             style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                             style="width: 120px; height: 120px; font-size: 2rem;">
                            <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="d-inline">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="mb-3">
                        <input type="file" class="form-control form-control-sm" name="avatar" accept="image/*" id="avatarInput">
                        <small class="form-text text-muted">JPG, PNG, or GIF (max 2MB)</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Upload Photo
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-shield-lock me-2"></i>
                    Account Security
                </h6>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-primary btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="bi bi-key me-2"></i>Change Password
                </button>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Account Status:</strong><br>
                        <span class="badge bg-success">Active</span>
                    </small>
                </div>
                
                <div class="mt-2">
                    <small class="text-muted">
                        <strong>Member Since:</strong><br>
                        <?php echo date('M j, Y', strtotime($currentUser['created_at'] ?? 'now')); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Application Statistics -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Application Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-primary mb-0"><?php echo $stats['total']; ?></h5>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-success mb-0"><?php echo $stats['approved']; ?></h5>
                        <small class="text-muted">Approved</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-key me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
