<?php

require ELEMENTARY_ROOT . '/admin/views/layout.php';

$user = Auth::user();

// Handle AJAX quick-save for display_name and timezone
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax && verify_csrf()) {
    header('Content-Type: application/json');
    $field = $_POST['field'] ?? '';
    if ($field === 'display_name') {
        $value = trim($_POST['value'] ?? '');
        Database::update('users', ['display_name' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        Logger::log('profile_updated', $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($field === 'timezone') {
        $value = $_POST['value'] ?? 'system';
        Database::update('users', ['timezone' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        Logger::log('profile_updated', $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($field === 'email') {
        $email = trim($_POST['value'] ?? '');
        $emailConfirm = trim($_POST['value_confirm'] ?? '');
        if ($email !== $emailConfirm) {
            echo json_encode(['success' => false, 'error' => 'Email addresses do not match.']);
            exit;
        }
        $emailError = Validator::validateEmail($email);
        if ($emailError) {
            echo json_encode(['success' => false, 'error' => $emailError]);
            exit;
        }
        Database::update('users', ['email' => $email, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        Logger::log('profile_updated', $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($field === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $password = $_POST['value'] ?? '';
        $passwordConfirm = $_POST['value_confirm'] ?? '';
        if (!password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'error' => __t('current_password_incorrect')]);
            exit;
        }
        if ($password !== $passwordConfirm) {
            echo json_encode(['success' => false, 'error' => __t('password_mismatch')]);
            exit;
        }
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Password cannot be empty.']);
            exit;
        }
        Database::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        Logger::log('profile_updated', $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($field === 'logout_session') {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $result = Auth::revokeSession((int) $user['id'], $sessionId);
        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => 'Session not found.']);
            exit;
        }
        Logger::log('session_revoked', $user['id'], 'Session ID: ' . $sessionId);
        echo json_encode(['success' => true, 'redirect' => $result['redirect']]);
        exit;
    }
    if ($field === 'logout_all_sessions') {
        Auth::revokeAllSessions((int) $user['id']);
        Logger::log('sessions_revoked_all', $user['id']);
        echo json_encode(['success' => true, 'redirect' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid field']);
    exit;
}

$sessions = Auth::getSessions((int) $user['id']);
$currentSessionToken = Auth::getCurrentSessionToken();

ob_start();
?>
<div class="profile-page-header">
    <h1 class="h3 mb-0"><?= esc($user['username']) ?></h1>
    <a href="<?= admin_url('logout') ?>" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-box-arrow-right me-1"></i><?= __t('logout') ?>
    </a>
</div>
<?= admin_flash() ?>

<div class="profile-grid">
    <div class="profile-settings-grid">
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="bi bi-person-badge card-icon"></i>
                <span class="card-title"><?= __t('display_name') ?></span>
            </div>
            <div class="dashboard-card-body profile-card-body">
                <input type="text" id="display_name_input" class="form-control" value="<?= esc($user['display_name'] ?? '') ?>" placeholder="<?= esc($user['username']) ?>">
                <div class="form-text"><?= __t('display_name_placeholder') ?></div>
                <div class="form-text" id="display_name_status"></div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="bi bi-globe2 card-icon"></i>
                <span class="card-title"><?= __t('timezone') ?></span>
            </div>
            <div class="dashboard-card-body profile-card-body">
                <select id="timezone_select" class="form-select">
                    <option value="system" <?= $user['timezone'] === 'system' ? 'selected' : '' ?>><?= __t('use_system_timezone') ?></option>
                    <?php foreach (timezone_identifiers_list() as $tz): ?>
                        <option value="<?= esc($tz) ?>" <?= $user['timezone'] === $tz ? 'selected' : '' ?>><?= esc($tz) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="timezone_status"></div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="bi bi-envelope card-icon"></i>
                <span class="card-title"><?= __t('email') ?></span>
            </div>
            <div class="dashboard-card-body profile-card-body">
                <div class="fw-semibold mb-3"><?= esc($user['email']) ?></div>
                <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
                    <?= __t('change_email') ?>
                </button>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="bi bi-shield-lock card-icon"></i>
                <span class="card-title"><?= __t('password') ?></span>
            </div>
            <div class="dashboard-card-body profile-card-body">
                <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal">
                    <?= __t('change_password') ?>
                </button>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <i class="bi bi-laptop card-icon"></i>
            <span class="card-title"><?= __t('login_sessions') ?></span>
            <?php if (!empty($sessions)): ?>
                <button type="button" class="header-action" id="logoutAllSessionsBtn">
                    <?= __t('log_out_all_sessions') ?>
                </button>
            <?php endif; ?>
        </div>
        <div class="dashboard-card-body">
            <?php if (empty($sessions)): ?>
                <span class="recent-item" style="color:#999;"><?= __t('no_other_sessions') ?></span>
            <?php else: ?>
                <div id="sessionsList">
                    <?php foreach ($sessions as $session): ?>
                        <?php $isCurrent = $currentSessionToken && $session['session_token'] === $currentSessionToken; ?>
                        <div class="recent-item session-item" role="button" tabindex="0" data-session-id="<?= (int) $session['id'] ?>">
                            <div class="session-item-details">
                                <span class="session-browser"><?= esc(Auth::parseBrowser($session['user_agent'])) ?></span>
                                <?php if ($isCurrent): ?>
                                    <span class="badge text-bg-primary session-current ms-1"><?= __t('current_session') ?></span>
                                <?php endif; ?>
                                <span class="session-meta"><?= esc($session['ip_address']) ?></span>
                            </div>
                            <span class="session-logout-label"><?= __t('log_out_session') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-text px-3 pb-2" id="sessions_status"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Change Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="emailForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailModalLabel"><?= __t('change_email') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __t('email') ?></label>
                        <input type="email" id="email_new" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __t('confirm_email') ?></label>
                        <input type="email" id="email_confirm" class="form-control" required>
                    </div>
                    <div id="emailError" class="text-danger mb-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="passwordForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel"><?= __t('change_password') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __t('current_password') ?></label>
                        <input type="password" id="password_current" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __t('new_password') ?></label>
                        <input type="password" id="password_new" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __t('confirm_password') ?></label>
                        <input type="password" id="password_confirm" class="form-control" required autocomplete="new-password">
                    </div>
                    <div id="passwordError" class="text-danger mb-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
                    <button type="submit" class="btn btn-dark"><?= __t('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = '<?= csrf_token() ?>';
    const baseUrl = '<?= admin_url('profile') ?>';
    const loginUrl = '<?= admin_url('login') ?>';

    function ajaxPost(field, value) {
        const statusEl = document.getElementById(field + '_status');
        statusEl.textContent = 'Saving...';
        statusEl.className = 'form-text text-muted';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('field', field);
        formData.append('value', value);

        fetch(baseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                statusEl.textContent = 'Saved';
                statusEl.className = 'form-text text-success';
            } else {
                statusEl.textContent = 'Error saving';
                statusEl.className = 'form-text text-danger';
            }
            setTimeout(function() { statusEl.textContent = ''; }, 3000);
        })
        .catch(function() {
            statusEl.textContent = 'Error saving';
            statusEl.className = 'form-text text-danger';
        });
    }

    var displayInput = document.getElementById('display_name_input');
    var typing = false;
    displayInput.addEventListener('focus', function() { typing = true; });
    displayInput.addEventListener('blur', function() {
        var self = this;
        setTimeout(function() {
            if (typing && self.value !== self.dataset.original) {
                ajaxPost('display_name', self.value);
                self.dataset.original = self.value;
            }
            typing = false;
        }, 200);
    });
    displayInput.dataset.original = displayInput.value;

    var tzSelect = document.getElementById('timezone_select');
    tzSelect.addEventListener('change', function() {
        ajaxPost('timezone', this.value);
    });

    // Email modal
    var emailForm = document.getElementById('emailForm');
    emailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var emailNew = document.getElementById('email_new').value.trim();
        var emailConfirm = document.getElementById('email_confirm').value.trim();
        var errorEl = document.getElementById('emailError');
        errorEl.style.display = 'none';

        if (emailNew !== emailConfirm) {
            errorEl.textContent = 'Email addresses do not match.';
            errorEl.style.display = 'block';
            return;
        }

        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('field', 'email');
        formData.append('value', emailNew);
        formData.append('value_confirm', emailConfirm);

        fetch(baseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                errorEl.textContent = data.error || 'Error saving';
                errorEl.style.display = 'block';
            }
        })
        .catch(function() {
            errorEl.textContent = 'Error saving';
            errorEl.style.display = 'block';
        });
    });

    // Password modal
    var passwordForm = document.getElementById('passwordForm');
    passwordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var passwordCurrent = document.getElementById('password_current').value;
        var passwordNew = document.getElementById('password_new').value;
        var passwordConfirm = document.getElementById('password_confirm').value;
        var errorEl = document.getElementById('passwordError');
        errorEl.style.display = 'none';

        if (passwordNew !== passwordConfirm) {
            errorEl.textContent = 'Passwords do not match.';
            errorEl.style.display = 'block';
            return;
        }

        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('field', 'password');
        formData.append('current_password', passwordCurrent);
        formData.append('value', passwordNew);
        formData.append('value_confirm', passwordConfirm);

        fetch(baseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
                passwordForm.reset();
                errorEl.textContent = 'Password updated successfully.';
                errorEl.className = 'text-success mb-2';
                errorEl.style.display = 'block';
                setTimeout(function() { errorEl.style.display = 'none'; }, 3000);
            } else {
                errorEl.textContent = data.error || 'Error saving';
                errorEl.style.display = 'block';
            }
        })
        .catch(function() {
            errorEl.textContent = 'Error saving';
            errorEl.style.display = 'block';
        });
    });

    function postSessionAction(field, sessionId) {
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('field', field);
        if (sessionId) {
            formData.append('session_id', sessionId);
        }

        return fetch(baseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(function(r) { return r.json(); });
    }

    function logoutSession(sessionId) {
        var statusEl = document.getElementById('sessions_status');
        if (statusEl) {
            statusEl.textContent = '';
        }

        postSessionAction('logout_session', sessionId).then(function(data) {
            if (data.redirect) {
                window.location.href = loginUrl;
                return;
            }
            if (data.success) {
                var item = document.querySelector('.session-item[data-session-id="' + sessionId + '"]');
                if (item) {
                    item.remove();
                }
                if (statusEl) {
                    statusEl.textContent = <?= json_encode(__t('session_logged_out')) ?>;
                    statusEl.className = 'form-text px-3 pb-2 text-success';
                }
                var list = document.getElementById('sessionsList');
                if (list && !list.children.length) {
                    location.reload();
                }
            } else if (statusEl) {
                statusEl.textContent = data.error || 'Error';
                statusEl.className = 'form-text px-3 pb-2 text-danger';
            }
        });
    }

    document.querySelectorAll('.session-item').forEach(function(item) {
        item.addEventListener('click', function() {
            logoutSession(this.getAttribute('data-session-id'));
        });
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                logoutSession(this.getAttribute('data-session-id'));
            }
        });
    });

    var logoutAllBtn = document.getElementById('logoutAllSessionsBtn');
    if (logoutAllBtn) {
        logoutAllBtn.addEventListener('click', function() {
            postSessionAction('logout_all_sessions').then(function(data) {
                if (data.success && data.redirect) {
                    window.location.href = loginUrl;
                }
            });
        });
    }
})();
</script>
<?php
admin_layout(__t('profile'), ob_get_clean());
