<?php
/**
 * Owner's view of the whole salon: the day's book, the team, the branches
 * and the logins that can get into the portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$profile = Auth::requireProfile();

if (!Auth::isAdmin()) {
    header('Location: ' . url('barber/index.php'));
    exit;
}

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try again.');
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'toggle') {
            AccountDAO::setProfileActive((string) $_POST['profile_id'], !empty($_POST['activate']));
            Auth::flash('success', 'Access updated.');
        } elseif ($action === 'reset') {
            $target = AccountDAO::findProfileById((string) $_POST['profile_id']);
            $newPassword = trim((string) ($_POST['new_password'] ?? ''));

            if ($target === null) {
                Auth::flash('error', 'That login no longer exists.');
            } elseif (strlen($newPassword) < 8) {
                Auth::flash('error', 'Use at least 8 characters for the new password.');
            } else {
                AccountDAO::updateProfilePassword((string) $target['id'], $newPassword);
                Auth::flash('success', 'Password reset for ' . $target['full_name'] . '.');
            }
        } elseif ($action === 'create_login') {
            $role = (string) ($_POST['role'] ?? 'barber');
            $email = trim((string) ($_POST['email'] ?? ''));
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $fullName = trim((string) ($_POST['full_name'] ?? ''));

            // Only the roles the rest of the app actually understands: an
            // owner or manager runs the salon, a barber only sees their own.
            $roles = ['admin' => 'Owner', 'manager' => 'Manager', 'barber' => 'Barber'];

            // A barber login has to point at a team member record. If the name
            // matches someone already on the team we use that record, otherwise
            // the person joins the team as part of creating the login.
            $barber = $role === 'barber' && $fullName !== ''
                ? DB::one('SELECT * FROM barbers WHERE name = ? LIMIT 1', [$fullName])
                : null;

            if (!isset($roles[$role])) {
                Auth::flash('error', 'Choose a role for the login.');
            } elseif ($fullName === '') {
                Auth::flash('error', 'Give the login a name.');
            } elseif ($email === '' || strlen($newPassword) < 8) {
                Auth::flash('error', 'Enter an email and a password of at least 8 characters.');
            } elseif (AccountDAO::findProfileByEmail($email) !== null) {
                Auth::flash('error', 'That email already has a login.');
            } elseif ($role === 'barber' && $barber !== null && AccountDAO::findProfileForBarber((string) $barber['id']) !== null) {
                Auth::flash('error', $barber['name'] . ' already has a login. Reset that one below instead.');
            } else {
                $memberId = null;
                $joinedTeam = false;

                if ($role === 'barber') {
                    if ($barber !== null) {
                        $memberId = (string) $barber['id'];
                    } else {
                        $memberId = BarberDAO::create([
                            'name' => $fullName,
                            'role' => 'Barber',
                            'staff_kind' => 'barber',
                            'salon_id' => '',
                            'specialties' => [],
                            'years_experience' => 0,
                            'active' => true,
                        ]);

                        BarberDAO::seedWorkingWeek($memberId);
                        $joinedTeam = true;
                    }
                }

                AccountDAO::createProfile($fullName, $email, $newPassword, $role, $memberId);

                Auth::flash('success', $roles[$role] . ' login created for ' . $fullName . ($joinedTeam ? ', and they are on the team now.' : '.'));
            }
        } elseif ($action === 'create_member') {
            $name = trim((string) ($_POST['member_name'] ?? ''));
            $jobTitle = trim((string) ($_POST['member_role'] ?? ''));
            $kind = (string) ($_POST['member_kind'] ?? 'barber');
            $salonId = (string) ($_POST['member_salon'] ?? '');
            $email = trim((string) ($_POST['member_email'] ?? ''));
            $newPassword = (string) ($_POST['member_password'] ?? '');
            $loginRole = (string) ($_POST['member_login_role'] ?? 'barber');

            $kinds = ['barber', 'beautician', 'nail_technician', 'therapist'];
            $roles = ['admin' => 'Owner', 'manager' => 'Manager', 'barber' => 'Barber'];

            if ($name === '') {
                Auth::flash('error', 'Give the new team member a name.');
            } elseif (!in_array($kind, $kinds, true)) {
                Auth::flash('error', 'Choose what kind of team member this is.');
            } elseif ($email !== '' && !isset($roles[$loginRole])) {
                Auth::flash('error', 'Choose a role for their login.');
            } elseif ($email !== '' && strlen($newPassword) < 8) {
                Auth::flash('error', 'Use a password of at least 8 characters for their login.');
            } elseif ($email !== '' && AccountDAO::findProfileByEmail($email) !== null) {
                Auth::flash('error', 'That email already has a login.');
            } else {
                $memberId = BarberDAO::create([
                    'name' => $name,
                    'role' => $jobTitle,
                    'staff_kind' => $kind,
                    'salon_id' => $salonId,
                    'specialties' => [],
                    'years_experience' => 0,
                    'active' => true,
                ]);

                // A full week of 09:00-18:00, so they can be booked from today.
                BarberDAO::seedWorkingWeek($memberId);

                if ($email === '') {
                    Auth::flash('success', $name . ' is on the team. Add a portal login for them below whenever you are ready.');
                } else {
                    AccountDAO::createProfile($name, $email, $newPassword, $loginRole, $memberId);
                    Auth::flash('success', $name . ' is on the team with a ' . strtolower($roles[$loginRole]) . ' login.');
                }
            }
        }
    }

    header('Location: ' . url('admin/index.php'));
    exit;
}

$stats = [
    'today' => (int) (DB::one("SELECT COUNT(*) AS t FROM appointments WHERE DATE(starts_at) = CURDATE() AND status <> 'cancelled'")['t'] ?? 0),
    'upcoming' => (int) (DB::one("SELECT COUNT(*) AS t FROM appointments WHERE ends_at > NOW() AND status IN ('pending','confirmed')")['t'] ?? 0),
    'customers' => (int) (DB::one('SELECT COUNT(*) AS t FROM customers')['t'] ?? 0),
    'revenue' => (int) (DB::one("SELECT COALESCE(SUM(price_pence_snapshot),0) AS t FROM appointments WHERE status = 'completed' AND starts_at >= (CURRENT_DATE - INTERVAL 30 DAY)")['t'] ?? 0),
    'messages' => (int) (DB::one('SELECT COALESCE(SUM(barber_unread),0) AS t FROM conversations')['t'] ?? 0),
];

$today = DB::all(
    "SELECT a.*, b.name AS barber_name, s.name AS service_name, c.name AS customer_name, c.phone AS customer_phone
     FROM appointments a
     JOIN barbers b ON b.id = a.barber_id
     JOIN services s ON s.id = a.service_id
     JOIN customers c ON c.id = a.customer_id
     WHERE DATE(a.starts_at) = CURDATE()
     ORDER BY a.starts_at"
);

$team = DB::all(
    'SELECT b.*, s.name AS branch_name, p.email AS login_email, p.is_active AS login_active
     FROM barbers b
     LEFT JOIN salons s ON s.id = b.salon_id
     LEFT JOIN profiles p ON p.barber_id = b.id
     ORDER BY b.display_order, b.name'
);

$logins = AccountDAO::allProfiles();
$branches = BranchDAO::all(false);

// The team, grouped under the branch each person works from.
$teamByBranch = [];
foreach ($branches as $branch) {
    $teamByBranch[(string) $branch['id']] = ['name' => (string) $branch['name'], 'members' => []];
}

$unassigned = ['name' => 'No branch yet', 'members' => []];

foreach ($team as $member) {
    $branchId = (string) ($member['salon_id'] ?? '');
    if ($branchId !== '' && isset($teamByBranch[$branchId])) {
        $teamByBranch[$branchId]['members'][] = $member;
    } else {
        $unassigned['members'][] = $member;
    }
}

$teamByBranch = array_values(array_filter($teamByBranch, static fn (array $group): bool => $group['members'] !== []));
if ($unassigned['members'] !== []) {
    $teamByBranch[] = $unassigned;
}

$pageTitle = 'Salon overview';
$portal = [
    'kind' => 'staff',
    'name' => $profile['full_name'],
    'root' => 'admin',
    'subtitle' => 'Owner',
    'nav' => [
        'index.php' => 'Salon overview',
        'reports.php' => 'Reports',
        'services.php' => 'Treatments',
    ],
];
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Salon overview</h1>
<p class="lede">Everything across every branch, at a glance.</p>

<div class="stat-row">
  <div class="stat"><p class="stat-label">Today</p><p class="stat-value"><?= $stats['today'] ?></p></div>
  <div class="stat"><p class="stat-label">Upcoming</p><p class="stat-value"><?= $stats['upcoming'] ?></p></div>
  <div class="stat"><p class="stat-label">Customers</p><p class="stat-value"><?= $stats['customers'] ?></p></div>
  <div class="stat"><p class="stat-label">Last 30 days</p><p class="stat-value"><?= e(money($stats['revenue'])) ?></p></div>
  <div class="stat"><p class="stat-label">Unread chat</p><p class="stat-value"><?= $stats['messages'] ?></p></div>
</div>

<section class="panel">
  <h2>Today's book</h2>
  <?php if ($today === []): ?>
    <p class="muted">Nothing booked today.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($today as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?> &middot; <?= e($appointment['service_name']) ?> &middot; <?= e($appointment['barber_name']) ?></p>
            <p class="record-meta"><?= e($appointment['customer_name']) ?> &middot; <?= e($appointment['customer_phone']) ?> &middot; <?= e(money((int) $appointment['price_pence_snapshot'])) ?></p>
          </div>
          <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel" data-list-scope="team">
  <div class="panel-head">
    <h2>The team</h2>
    <span class="muted"><?= count($team) ?> people</span>
  </div>

  <div class="list-toolbar">
    <label class="visually-hidden" for="team-search">Search the team</label>
    <input type="search" id="team-search" data-list-search="team" placeholder="Search a name, branch or email&hellip;" autocomplete="off">
  </div>

  <?php foreach ($teamByBranch as $group): ?>
    <div class="list-group" data-list-group data-list-section data-page-size="10">
      <h3 class="list-group-title">
        <span><?= e($group['name']) ?></span>
        <span class="muted"><?= count($group['members']) ?> <?= count($group['members']) === 1 ? 'person' : 'people' ?></span>
      </h3>
      <ul class="record-list">
        <?php foreach ($group['members'] as $member): ?>
          <li class="record" data-list-item
              data-search="<?= e($group['name'] . ' ' . $member['name'] . ' ' . staff_kind_label((string) $member['staff_kind']) . ' ' . ((string) ($member['login_email'] ?? '') !== '' ? (string) $member['login_email'] : 'no login')) ?>">
            <div>
              <p class="record-title"><a class="text-link" href="<?= e(url('barber.php?slug=' . urlencode((string) $member['slug']))) ?>"><?= e($member['name']) ?></a></p>
              <p class="record-meta"><?= e(staff_kind_label((string) $member['staff_kind'])) ?> &middot; <?= e((string) ($member['login_email'] ?? '') !== '' ? (string) $member['login_email'] : 'no login yet') ?></p>
            </div>
            <div class="record-side">
              <span class="badge <?= (int) $member['active'] === 1 ? 'badge-done' : 'badge-offline' ?>"><?= (int) $member['active'] === 1 ? 'Active' : 'Hidden' ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="muted" data-list-empty hidden>No one in this branch matches that search.</p>

      <div class="list-pager" data-list-pager hidden>
        <button type="button" class="btn btn-small btn-outline" data-page-prev>Previous</button>
        <span class="muted" data-page-status></span>
        <button type="button" class="btn btn-small btn-outline" data-page-next>Next</button>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="muted" data-scope-empty hidden>No one matches that search.</p>
</section>

<section class="panel">
  <h2>Branches</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Branch</th><th>Address</th><th>Phone</th><th>Flagship</th></tr></thead>
      <tbody>
        <?php foreach ($branches as $branch): ?>
          <tr>
            <td><?= e($branch['name']) ?></td>
            <td><?= e($branch['address_line_1']) ?>, <?= e($branch['city']) ?> <?= e($branch['postcode']) ?></td>
            <td><?= e($branch['phone']) ?></td>
            <td><?= (int) $branch['is_primary'] === 1 ? 'Yes' : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted"><a class="text-link" href="<?= e(url('branches.php')) ?>">Open the public branch finder</a></p>
</section>

<section class="panel">
  <h2>Add a team member</h2>
  <p class="muted">They show up on the public team page and in the booking form straight away, on a Monday to Saturday rota they can change from their own portal. Leave the email blank if you just want them on the team for now.</p>
  <form method="post" class="inline-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create_member">
    <input type="text" name="member_name" placeholder="Full name" required>
    <input type="text" name="member_role" placeholder="Job title, e.g. Senior Barber">
    <select name="member_kind">
      <option value="barber">Barber</option>
      <option value="beautician">Beauty</option>
      <option value="nail_technician">Nails</option>
      <option value="therapist">Massage</option>
    </select>
    <select name="member_salon">
      <option value="">Branch&hellip;</option>
      <?php foreach ($branches as $branch): ?>
        <option value="<?= e($branch['id']) ?>"><?= e($branch['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="email" name="member_email" placeholder="Work email, if they need a login">
    <input type="password" name="member_password" placeholder="Password for that login" minlength="8">
    <select name="member_login_role">
      <option value="barber">Barber login</option>
      <option value="manager">Manager login</option>
      <option value="admin">Owner login</option>
    </select>
    <button class="btn btn-primary" type="submit">Add team member</button>
  </form>
</section>

<section class="panel" data-list-scope="logins">
  <div class="panel-head">
    <h2>Portal logins</h2>
    <span class="muted"><?= count($logins) ?> logins</span>
  </div>

  <div class="list-toolbar">
    <label class="visually-hidden" for="login-search">Search portal logins</label>
    <input type="search" id="login-search" data-list-search="logins" placeholder="Search a name, email or role&hellip;" autocomplete="off">
  </div>

  <div data-list-group data-page-size="10">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Person</th><th>Email</th><th>Role</th><th>Last seen</th><th>Access</th><th>Reset password</th></tr></thead>
        <tbody>
          <?php foreach ($logins as $login): ?>
            <tr data-list-item data-search="<?= e((string) $login['full_name'] . ' ' . (string) $login['email'] . ' ' . (string) $login['role'] . ' ' . role_label((string) $login['role'])) ?>">
              <td><?= e($login['full_name']) ?></td>
              <td><?= e((string) $login['email']) ?></td>
              <td><?= e(role_label((string) $login['role'])) ?></td>
              <td><?= e($login['last_login_at'] !== null ? date('j M H:i', strtotime((string) $login['last_login_at'])) : 'never') ?></td>
              <td>
                <?php if ((string) $login['id'] !== (string) $profile['id']): ?>
                  <form method="post" class="inline-form inline-form-tight">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="profile_id" value="<?= e($login['id']) ?>">
                    <input type="hidden" name="activate" value="<?= (int) $login['is_active'] === 1 ? '0' : '1' ?>">
                    <button class="btn btn-small <?= (int) $login['is_active'] === 1 ? 'btn-quiet' : 'btn-primary' ?>" type="submit">
                      <?= (int) $login['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                    </button>
                  </form>
                <?php else: ?>
                  <span class="muted">you</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" class="inline-form inline-form-tight">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="reset">
                  <input type="hidden" name="profile_id" value="<?= e($login['id']) ?>">
                  <input type="password" name="new_password" placeholder="New password" minlength="8" required>
                  <button class="btn btn-small btn-outline" type="submit">Set</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="muted" data-list-empty hidden>No logins match that search.</p>

    <div class="list-pager" data-list-pager hidden>
      <button type="button" class="btn btn-small btn-outline" data-page-prev>Previous</button>
      <span class="muted" data-page-status></span>
      <button type="button" class="btn btn-small btn-outline" data-page-next>Next</button>
    </div>
  </div>
</section>

<section class="panel">
  <h2>Add a portal login</h2>
  <p class="muted">An owner or manager login sees the whole salon. A barber login only ever sees that person's own page - if the name is new to the team, they are added to it as you create the login.</p>
  <form method="post" class="inline-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create_login">
    <select name="role" required>
      <option value="barber">Barber &mdash; own rota and appointments</option>
      <option value="manager">Manager &mdash; full owner access</option>
      <option value="admin">Owner &mdash; full owner access</option>
    </select>
    <input type="text" name="full_name" placeholder="Full name" autocomplete="off" required>
    <input type="email" name="email" placeholder="work@email" required>
    <input type="password" name="new_password" placeholder="Password" minlength="8" required>
    <button class="btn btn-primary" type="submit">Create login</button>
  </form>
</section>

<script src="<?= e(asset('js/admin-lists.js')) ?>" defer></script>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>
