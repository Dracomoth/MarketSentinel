<?php
/**
 * User Information & Profile View
 * 
 * Screen layout follows the structured card sections from the mockup:
 * - Profile Avatar box with centered dashed dropzone & 50/50 Browse / Remove buttons
 * - Personal Information with 2-column fields, @ prefix username, and inline status badges
 * - Change Password with "Generate Password" generator and eye visibility toggles
 * - Bottom Cancel and Save Changes actions
 * 
 * Styled with BasePHP's native clean light theme.
 */

$pageTitle = "My User Profile - " . htmlspecialchars($appName ?? 'BasePHP', ENT_QUOTES, 'UTF-8');
require_once dirname(__DIR__) . '/services/LayoutService.php';
$layoutContext = LayoutService::getContext($currentUser);
$initials = $layoutContext['initials'] ?? LayoutService::getUserInitials($currentUser);
$avatarPath = $currentUser['avatar_path'];
$fsAvatar = null;
if (!empty($avatarPath)) {
    $cleanPath = ltrim($avatarPath, '/');
    $realPath = dirname(__DIR__, 2) . '/' . $cleanPath;
    if (file_exists($realPath)) {
        $fsAvatar = '/' . $cleanPath . '?t=' . filemtime($realPath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <div class="profile-page-wrapper">
            <div class="profile-card">
                <!-- Card Header -->
                <div class="profile-card-header">
                    <div class="profile-header-left">
                        <div class="profile-header-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div>
                            <h1 class="profile-card-title">My User Profile</h1>
                            <p class="profile-card-subtitle">View and update your personal information, credentials, and avatar</p>
                        </div>
                    </div>
                    <a href="/" class="profile-close-btn" title="Back to Dashboard" aria-label="Close profile">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </a>
                </div>

                <!-- Form & Sections -->
                <form method="POST" action="/profile" enctype="multipart/form-data" class="profile-card-body" id="profile-form">
                    <!-- Hidden input to signal avatar removal -->
                    <input type="hidden" name="remove_avatar" id="input_remove_avatar" value="0">
                    <!-- Hidden Real File Input -->
                    <input type="file" 
                           name="avatar_file" 
                           id="avatar_file_input" 
                           accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" 
                           style="display: none;">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" style="background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;">
                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <!-- Section 1: PROFILE AVATAR -->
                    <div class="profile-section-box">
                        <div class="profile-section-header">
                            <h2 class="profile-section-title">Profile Avatar</h2>
                        </div>

                        <div class="profile-avatar-dropzone" id="avatar-dropzone">
                            <img src="<?= htmlspecialchars($fsAvatar ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="Avatar" 
                                 id="avatar-preview-img" 
                                 style="<?= empty($fsAvatar) ? 'display: none;' : '' ?>"
                                 onerror="this.style.display='none'; document.getElementById('avatar-preview-initials').style.display='flex';">
                            
                            <span class="avatar-initials-fallback" 
                                  id="avatar-preview-initials" 
                                  style="<?= !empty($fsAvatar) ? 'display: none;' : 'display: flex;' ?>">
                                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="profile-avatar-buttons">
                            <button type="button" class="btn-profile-browse" id="btn-browse-avatar">
                                Browse
                            </button>
                            <button type="button" class="btn-profile-remove" id="btn-remove-avatar">
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- Section 2: PERSONAL INFORMATION -->
                    <div class="profile-section-box">
                        <div class="profile-section-header">
                            <h2 class="profile-section-title">Personal Information</h2>
                        </div>

                        <div class="profile-grid-2">
                            <div class="profile-field">
                                <label for="first_name" class="profile-field-label">
                                    First Name <span class="req-star">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? $currentUser['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="profile-input">
                            </div>

                            <div class="profile-field">
                                <label for="last_name" class="profile-field-label">
                                    Last Name <span class="req-star">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? $currentUser['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="profile-input">
                            </div>
                        </div>

                        <div class="profile-grid-2">
                            <div class="profile-field">
                                <label for="username" class="profile-field-label">
                                    Username <span class="req-star">*</span>
                                </label>
                                <div class="profile-prefix-input-box">
                                    <span class="profile-prefix-symbol">@</span>
                                    <input type="text" id="username" name="username" required 
                                           value="<?= htmlspecialchars($_POST['username'] ?? $currentUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                           class="profile-input" 
                                           pattern="[a-zA-Z0-9_.-]{3,30}" 
                                           title="3 to 30 characters: letters, numbers, underscores, dots, or dashes">
                                </div>
                                <span class="profile-hint-text">Used to sign in along with your email.</span>
                            </div>

                            <div class="profile-field">
                                <label for="email" class="profile-field-label">
                                    Email Address <span class="req-star">*</span>
                                </label>
                                <input type="email" id="email" name="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? $currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                       class="profile-input">
                                <span class="profile-hint-text">Account notifications & login identifier.</span>
                            </div>
                        </div>

                        <!-- Badges Row -->
                        <div class="profile-badges-row">
                            <div class="profile-badge-item">
                                <span>Assigned Role:</span>
                                <span class="badge-role-light">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    <?= htmlspecialchars($currentUser['role_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="profile-badge-item">
                                <span>Account Status:</span>
                                <span class="badge-status-light">
                                    <span class="status-dot"></span> Active Account
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: CHANGE PASSWORD -->
                    <div class="profile-section-box">
                        <div class="profile-section-header">
                            <h2 class="profile-section-title">Change Password</h2>
                            <button type="button" class="btn-generate-pw-light" id="btn-generate-password" title="Generate strong random password">
                                <span>🎲</span> Generate Password
                            </button>
                        </div>

                        <p class="profile-hint-text" style="margin-bottom: 0.85rem;">
                            Leave both fields empty to keep your existing password unchanged.
                        </p>

                        <div class="profile-grid-2">
                            <div class="profile-field">
                                <label for="new_password" class="profile-field-label">New Password</label>
                                <div class="profile-pw-wrapper">
                                    <input type="password" id="new_password" name="new_password" 
                                           autocomplete="new-password" placeholder="Minimum 8 characters" 
                                           class="profile-input">
                                    <button type="button" class="btn-pw-toggle-light" id="btn-toggle-new-pw" aria-label="Toggle password visibility">
                                        <svg class="eye-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="profile-field">
                                <label for="confirm_password" class="profile-field-label">Confirm New Password</label>
                                <div class="profile-pw-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           autocomplete="new-password" placeholder="Repeat new password" 
                                           class="profile-input">
                                    <button type="button" class="btn-pw-toggle-light" id="btn-toggle-confirm-pw" aria-label="Toggle password visibility">
                                        <svg class="eye-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="profile-actions-bar">
                        <a href="/" class="btn-profile-cancel">Cancel</a>
                        <button type="submit" class="btn-profile-save" id="btn-save-profile">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var fileInput = document.getElementById('avatar_file_input');
            var browseBtn = document.getElementById('btn-browse-avatar');
            var removeBtn = document.getElementById('btn-remove-avatar');
            var removeInput = document.getElementById('input_remove_avatar');
            var previewImg = document.getElementById('avatar-preview-img');
            var previewInitials = document.getElementById('avatar-preview-initials');

            // Avatar Upload Browse
            if (browseBtn && fileInput) {
                browseBtn.addEventListener('click', function() {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function() {
                    if (fileInput.files && fileInput.files[0]) {
                        var file = fileInput.files[0];
                        if (file.size > 5 * 1024 * 1024) {
                            alert('The selected image is larger than 5MB. Please choose a smaller file.');
                            fileInput.value = '';
                            return;
                        }

                        var reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                            if (previewInitials) previewInitials.style.display = 'none';
                            if (removeInput) removeInput.value = '0';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Avatar Remove
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    var hasImage = (previewImg && previewImg.style.display !== 'none' && previewImg.src && previewImg.src !== '' && previewImg.src !== window.location.href);
                    var hasFile = (fileInput && fileInput.files && fileInput.files.length > 0);

                    if (hasImage || hasFile) {
                        if (!confirm('Are you sure you want to remove your avatar picture?')) {
                            return;
                        }
                    }

                    if (fileInput) fileInput.value = '';
                    if (removeInput) removeInput.value = '1';
                    if (previewImg) {
                        previewImg.src = '';
                        previewImg.style.display = 'none';
                    }
                    if (previewInitials) previewInitials.style.display = 'flex';
                });
            }

            // Password Generator
            var generateBtn = document.getElementById('btn-generate-password');
            if (generateBtn) {
                generateBtn.addEventListener('click', function() {
                    var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
                    var generated = '';
                    for (var i = 0; i < 12; i++) {
                        generated += chars.charAt(Math.floor(Math.random() * chars.length));
                    }

                    var newPw = document.getElementById('new_password');
                    var confPw = document.getElementById('confirm_password');
                    if (newPw && confPw) {
                        newPw.value = generated;
                        confPw.value = generated;
                        newPw.type = 'text';
                        confPw.type = 'text';
                        setEyeIcon('btn-toggle-new-pw', true);
                        setEyeIcon('btn-toggle-confirm-pw', true);
                    }
                });
            }

            // Password Visibility Toggles
            function setEyeIcon(btnId, isVisible) {
                var btn = document.getElementById(btnId);
                if (!btn) return;
                if (isVisible) {
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                } else {
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                }
            }

            function bindEyeToggle(btnId, inputId) {
                var btn = document.getElementById(btnId);
                var input = document.getElementById(inputId);
                if (btn && input) {
                    btn.addEventListener('click', function() {
                        var willShow = input.type === 'password';
                        input.type = willShow ? 'text' : 'password';
                        setEyeIcon(btnId, willShow);
                    });
                }
            }

            bindEyeToggle('btn-toggle-new-pw', 'new_password');
            bindEyeToggle('btn-toggle-confirm-pw', 'confirm_password');
        });
    </script>
</body>
</html>
