<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions - MarketSentinel Wealth Advisory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/layout/header.php'; ?>
    <?php require __DIR__ . '/layout/sidebar.php'; ?>

    <main class="container page-content">
        <div class="page-header">
            <div>
                <h1>Roles & Permissions</h1>
                <p class="text-muted">Manage system roles and configure screen access permissions per device.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" onclick="toggleRoleCreateForm()">+ New Role</button>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Create Role Form (Hidden by default, shown on button click or validation error) -->
        <div class="card form-card" id="create-role-card" style="display: <?= !empty($showCreateModal) ? 'block' : 'none' ?>;">
            <div class="card-header-row">
                <h2>Create New Role</h2>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleRoleCreateForm()">Close</button>
            </div>
            <form method="POST" action="/roles" class="form">
                <input type="hidden" name="action" value="create_role">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Role Name *</label>
                        <input type="text" id="name" name="name" required placeholder="e.g. Editor, Manager, Auditor">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" placeholder="Brief explanation of role scope">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Role</button>
                    <button type="button" class="btn btn-outline" onclick="toggleRoleCreateForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- 2-Column Layout: Roles List on Left, Permissions Editor on Right -->
        <div class="roles-layout">
            <!-- Left Sidebar: Roles List -->
            <aside class="roles-sidebar">
                <div class="card sidebar-card">
                    <h3 class="sidebar-title">Roles</h3>
                    <div class="roles-list">
                        <?php foreach ($roles as $r): ?>
                            <?php 
                            $isSelected = ($selectedRole && (int)$selectedRole['id'] === (int)$r['id']); 
                            ?>
                            <div class="role-item <?= $isSelected ? 'active' : '' ?>">
                                <a href="/roles?role_id=<?= (int)$r['id'] ?>" class="role-link">
                                    <span class="role-name">
                                        <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($r['is_super'])): ?>
                                            <span class="badge badge-primary">SuperAdmin</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge user-count-badge" title="Assigned Users">
                                        <?= (int) $r['user_count'] ?> <?= (int)$r['user_count'] === 1 ? 'user' : 'users' ?>
                                    </span>
                                </a>

                                <?php if (empty($r['is_super'])): ?>
                                    <form method="POST" action="/roles" class="role-delete-form" 
                                          onsubmit="return confirm('Are you sure you want to permanently delete role \'<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>\'?');">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-xs btn-danger" title="Delete Role">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- Right Panel: Permissions Matrix for Selected Role -->
            <section class="roles-content">
                <?php if ($selectedRole): ?>
                    <div class="card">
                        <div class="permissions-header">
                            <div>
                                <h2>Permissions for: <?= htmlspecialchars($selectedRole['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <?php if (!empty($selectedRole['description'])): ?>
                                    <p class="text-muted"><?= htmlspecialchars($selectedRole['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($selectedRole['is_super'])): ?>
                            <div class="alert alert-info">
                                <strong>SuperAdmin Role:</strong> SuperAdmins always have full unrestricted (Write) access across all screens. These permissions are permanent and cannot be modified.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/roles">
                            <input type="hidden" name="action" value="save_permissions">
                            <input type="hidden" name="role_id" value="<?= (int)$selectedRole['id'] ?>">

                            <div class="table-responsive">
                                <table class="table permissions-table">
                                    <thead>
                                        <tr>
                                            <th>Screen</th>
                                            <th>Category</th>
                                            <th>
                                                <div class="col-header-bulk">
                                                    <span>Access PC</span>
                                                    <?php if (empty($selectedRole['is_super'])): ?>
                                                        <select class="bulk-select" onchange="setAllColumn('pc', this.value)">
                                                            <option value="">Set All...</option>
                                                            <option value="write">Write</option>
                                                            <option value="read">Read</option>
                                                            <option value="none">None</option>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                            </th>
                                            <th>
                                                <div class="col-header-bulk">
                                                    <span>Access Other Devices</span>
                                                    <?php if (empty($selectedRole['is_super'])): ?>
                                                        <select class="bulk-select" onchange="setAllColumn('other', this.value)">
                                                            <option value="">Set All...</option>
                                                            <option value="write">Write</option>
                                                            <option value="read">Read</option>
                                                            <option value="none">None</option>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($screens)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No screens found in database.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($screens as $screen): ?>
                                                <?php 
                                                $sid = (int) $screen['id'];
                                                $perm = $rolePermissions[$sid] ?? null;
                                                $pcVal = !empty($selectedRole['is_super']) ? 'write' : ($perm['access_pc'] ?? 'none');
                                                $otherVal = !empty($selectedRole['is_super']) ? 'write' : ($perm['access_other'] ?? 'none');
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($screen['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <span class="screen-key text-muted">(<?= htmlspecialchars($screen['screen_key'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge"><?= htmlspecialchars($screen['category'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></span>
                                                    </td>
                                                    <td>
                                                        <select name="perms[<?= $sid ?>][access_pc]" 
                                                                class="form-control-sm perm-select perm-pc"
                                                                <?= !empty($selectedRole['is_super']) ? 'disabled' : '' ?>>
                                                            <option value="write" <?= $pcVal === 'write' ? 'selected' : '' ?>>Write (Full Access)</option>
                                                            <option value="read" <?= $pcVal === 'read' ? 'selected' : '' ?>>Read Only</option>
                                                            <option value="none" <?= $pcVal === 'none' ? 'selected' : '' ?>>None (Forbidden)</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="perms[<?= $sid ?>][access_other]" 
                                                                class="form-control-sm perm-select perm-other"
                                                                <?= !empty($selectedRole['is_super']) ? 'disabled' : '' ?>>
                                                            <option value="write" <?= $otherVal === 'write' ? 'selected' : '' ?>>Write (Full Access)</option>
                                                            <option value="read" <?= $otherVal === 'read' ? 'selected' : '' ?>>Read Only</option>
                                                            <option value="none" <?= $otherVal === 'none' ? 'selected' : '' ?>>None (Forbidden)</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (empty($selectedRole['is_super'])): ?>
                                <div class="form-actions" style="margin-top: 1.5rem;">
                                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <p class="text-muted">No role selected. Please create or select a role from the left sidebar.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/layout/footer.php'; ?>

    <script>
        function toggleRoleCreateForm() {
            var formCard = document.getElementById('create-role-card');
            if (formCard.style.display === 'none' || formCard.style.display === '') {
                formCard.style.display = 'block';
                document.getElementById('name').focus();
            } else {
                formCard.style.display = 'none';
            }
        }

        function setAllColumn(colType, value) {
            if (!value) return;
            var selector = colType === 'pc' ? '.perm-pc' : '.perm-other';
            var selects = document.querySelectorAll(selector);
            selects.forEach(function(s) {
                if (!s.disabled) {
                    s.value = value;
                }
            });
        }
    </script>
</body>
</html>
