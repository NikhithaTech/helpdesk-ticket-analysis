<?php
session_start();
/*
  index.php - Helpdesk CRUD single file (auto-detect DB columns)
  DB: helpdesk_db
  Default login: admin / admin
*/

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'helpdesk_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("DB connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// Schema definitions (labels + preferred cols) - first column treated as PK for each dataset
$schemas = [
    'agents' => [
        'label' => 'Agents',
        'cols' => ['Agent_ID','Agent_Name','Skillset','Experience_Level','Team','Average_Response_Timins','Average_Resolution_Timehrs']
    ],
    'feedback' => [
        'label' => 'Feedback',
        'cols' => ['Feedback_ID','Ticket_ID','User_ID','Feedback_Date','Rating','Comments','CSAT_Score']
    ],
    'tickets' => [
        'label' => 'Tickets',
        'cols' => ['Ticket_ID','User_ID','Department','Category','Priority','Status','Created_Date','Closed_Date','SLA_Breach_Flag']
    ],
    'ticket_assignments' => [
        'label' => 'Ticket Assignments',
        'cols' => ['Assignment_ID','Ticket_ID','Assigned_Agent_ID','Assigned_Date','Resolution_Date','Reassign_Count','Current_Assignment_Flag']
    ],
    'users' => [
        'label' => 'Users',
        'cols' => ['User_ID','Name','Email','Contact_Number','Department','Location','User_Type']
    ]
];

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function redirect($u){ header("Location: $u"); exit; }

// ----------------- New helper: read actual DB column names -----------------
function get_table_columns_from_db($mysqli, $table) {
    $safe = $mysqli->real_escape_string($table);
    $cols = [];
    $res = $mysqli->query("SHOW COLUMNS FROM `{$safe}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        $res->free();
    }
    return $cols;
}

// --------------- Auth ---------------
$login_err = '';
if (isset($_POST['login'])) {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === 'admin' && $p === 'admin') {
        $_SESSION['user'] = 'admin';
        redirect('index.php');
    } else $login_err = 'Invalid username or password.';
}
if (isset($_GET['logout'])) { session_destroy(); redirect('index.php'); }
$logged_in = isset($_SESSION['user']) && $_SESSION['user'] === 'admin';

// --------------- CRUD handling ---------------
$notice = '';
if ($logged_in && isset($_REQUEST['table']) && array_key_exists($_REQUEST['table'], $schemas)) {
    $table = $_REQUEST['table'];

    // Prefer real DB columns. Fall back to schema list if SHOW COLUMNS fails.
    $db_cols = get_table_columns_from_db($mysqli, $table);
    if (!empty($db_cols)) {
        $cols = $db_cols;
    } else {
        $cols = $schemas[$table]['cols'];
    }
    $pk = $cols[0] ?? null;

    // CREATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
        $vals_in = [];
        foreach ($cols as $c) {
            $v = array_key_exists($c, $_POST) ? trim($_POST[$c]) : null;
            if ($v === '') $v = null;
            $vals_in[$c] = $v;
        }

        // if PK looks like id and left blank, omit it (allow auto-increment)
        $exclude_pk = false;
        if ($pk !== null && (stripos($pk, 'id') !== false || preg_match('/_id$/i', $pk)) && ($vals_in[$pk] === null)) {
            $exclude_pk = true;
        }

        $insert_cols = []; $insert_vals = [];
        foreach ($cols as $c) {
            if ($exclude_pk && $c === $pk) continue;
            $insert_cols[] = $c;
            $insert_vals[] = $vals_in[$c];
        }

        if (count($insert_cols) === 0) {
            $notice = "Create error: no columns to insert.";
        } else {
            $placeholders = implode(',', array_fill(0, count($insert_cols), '?'));
            $colnames = implode(',', array_map(function($c){ return "`$c`"; }, $insert_cols));
            $sql = "INSERT INTO `$table` ($colnames) VALUES ($placeholders)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($insert_vals));
                $bind_params = [];
                $bind_params[] = & $types;
                for ($i = 0; $i < count($insert_vals); $i++) $bind_params[] = & $insert_vals[$i];
                if (count($insert_vals) > 0) call_user_func_array([$stmt, 'bind_param'], $bind_params);
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect("index.php?table=$table&msg=created");
                } else {
                    $notice = "Create error: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $notice = "Prepare error: " . $mysqli->error;
            }
        }
    }

    // UPDATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && isset($_POST[$pk])) {
        $id = $_POST[$pk];
        $pairs = []; $vals = [];
        foreach ($cols as $c) {
            if ($c === $pk) continue;
            $pairs[] = "`$c` = ?";
            $val = array_key_exists($c, $_POST) ? trim($_POST[$c]) : null;
            if ($val === '') $val = null;
            $vals[] = $val;
        }
        $vals[] = $id;
        $sql = "UPDATE `$table` SET ".implode(', ',$pairs)." WHERE `$pk` = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $types = str_repeat('s', count($vals));
            $bind_params = [];
            $bind_params[] = & $types;
            for ($i = 0; $i < count($vals); $i++) $bind_params[] = & $vals[$i];
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            if ($stmt->execute()) { $stmt->close(); redirect("index.php?table=$table&msg=updated"); }
            else { $notice = "Update error: ".$stmt->error; $stmt->close(); }
        } else $notice = "Prepare error: ".$mysqli->error;
    }

    // DELETE
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
        if ($stmt) {
            $stmt->bind_param('s', $id);
            if ($stmt->execute()) { $stmt->close(); redirect("index.php?table=$table&msg=deleted"); }
            else { $notice = "Delete error: ".$stmt->error; $stmt->close(); }
        } else $notice = "Prepare error: " . $mysqli->error;
    }
}

// --------------- Fetch helpers (useful for general listing/search) ---------------
function fetch_rows($mysqli, $table, $cols, $limit=500, $search_q = null, $exact = false){
    // If cols is empty, try to get from DB
    if (empty($cols)) {
        $safe = $mysqli->real_escape_string($table);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$safe}`");
        $cols = [];
        if ($res) { while ($r = $res->fetch_assoc()) $cols[] = $r['Field']; $res->free(); }
        if (empty($cols)) return [];
    }

    $pk = $cols[0];
    $sql = "SELECT * FROM `$table` ORDER BY `$pk` DESC LIMIT " . intval($limit);
    $rows = [];
    $res = $mysqli->query($sql);
    if (!$res) return [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->close();

    if ($search_q === null || $search_q === '') return $rows;

    $q_raw = (string)$search_q;
    $filtered = [];
    foreach ($rows as $row) {
        $matched = false;
        foreach ($row as $val) {
            if ($val === null) continue;
            $val_s = (string)$val;
            if ($exact) {
                if (mb_strtolower(trim($val_s), 'UTF-8') === mb_strtolower(trim($q_raw), 'UTF-8')) { $matched = true; break; }
            } else {
                if (mb_stripos($val_s, $q_raw) !== false) { $matched = true; break; }
            }
        }
        if ($matched) $filtered[] = $row;
    }
    return $filtered;
}

function fetch_one($mysqli, $table, $pk, $id){
    $stmt = $mysqli->prepare("SELECT * FROM `$table` WHERE `$pk` = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row;
}

// --------------- Toast message ---------------
$toast_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') $toast_msg = 'Record created successfully.';
    elseif ($_GET['msg'] === 'updated') $toast_msg = 'Record updated successfully.';
    elseif ($_GET['msg'] === 'deleted') $toast_msg = 'Record deleted successfully.';
    else $toast_msg = h($_GET['msg']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Helpdesk Admin — CRUD</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --db-color: linear-gradient(90deg,#ff7a18,#af002d);
      --table-bg: #fff8e6;
      --accent: #0d6efd;
      --card-shadow: 0 8px 20px rgba(13,110,253,0.08);
    }
    body { background: #f3f6fb; }
    .topbar { background: #0d47a1; color: #fff; }
    .brand-db {
      display:inline-block;
      padding:6px 12px;
      border-radius:10px;
      background: linear-gradient(90deg,#ff7a18,#af002d);
      color:#fff;
      font-weight:700;
      box-shadow: 0 6px 18px rgba(175,0,45,0.12);
    }
    .table-name {
      display:inline-block;
      padding:6px 10px;
      border-radius:8px;
      background: var(--table-bg);
      color:#7a4b00;
      font-weight:700;
      border:1px solid #ffe4b5;
    }
    .card-modern { border-radius:12px; box-shadow: var(--card-shadow); }
    .nav .nav-link { color:#fff7; }
    .nav .nav-link.active { background: rgba(255,255,255,0.12); border-radius:8px; color:#fff; }
    .form-control-sm { padding: .375rem .5rem; }
    .table-responsive { max-height:420px; overflow:auto; }
  </style>
</head>
<body>
<nav class="navbar topbar navbar-expand-lg py-3">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
      <span class="brand-db">Database: <?php echo h($DB_NAME); ?></span>
      <span class="ms-3 text-white-50">Helpdesk CRUD</span>
    </div>
    <div class="ms-auto">
      <?php if($logged_in): ?>
        <span class="text-white me-3">Signed in as <strong>admin</strong></span>
        <a class="btn btn-outline-light btn-sm" href="index.php?logout=1">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container my-4">
<?php if (!$logged_in): ?>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card card-modern p-3">
        <div class="card-body">
          <h4 class="mb-3">Sign in</h4>
          <?php if ($login_err): ?><div class="alert alert-danger"><?php echo h($login_err); ?></div><?php endif; ?>
          <form method="post" class="row g-3">
            <div class="col-12"><input name="username" class="form-control" value="admin" placeholder="Username"></div>
            <div class="col-12"><input type="password" name="password" class="form-control" value="admin" placeholder="Password"></div>
            <div class="col-12"><button name="login" class="btn btn-primary">Sign in</button></div>
          </form>
          <small class="text-muted">Demo login: admin / admin</small>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card card-modern p-3">
        <h6 class="mb-2">Datasets</h6>
        <nav class="nav flex-column">
          <?php foreach($schemas as $t=>$meta): ?>
            <a class="nav-link <?php if(isset($_GET['table']) && $_GET['table'] === $t) echo 'active'; ?>"
               href="index.php?table=<?php echo h($t); ?>">
               <span class="table-name"><?php echo h($meta['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>

    <div class="col-md-9">
      <?php if (!isset($_GET['table'])): ?>
        <div class="card card-modern p-3">
          <h5>Welcome to Helpdesk Admin</h5>
          <p class="text-muted">Select a dataset on the left to manage records (Create / Read / Update / Delete).</p>
          <div class="row">
            <?php foreach($schemas as $t=>$meta): ?>
              <div class="col-sm-6 col-md-4 mb-2">
                <div class="card p-2">
                  <div class="d-flex justify-content-between">
                    <div>
                      <div class="fw-bold"><?php echo h($meta['label']); ?></div>
                      <div class="small text-muted"><?php echo count($meta['cols']); ?> columns</div>
                    </div>
                    <div><a class="btn btn-sm btn-outline-primary" href="index.php?table=<?php echo h($t); ?>">Open</a></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else:
        $table = $_GET['table'];
        if (!array_key_exists($table, $schemas)) { echo '<div class="alert alert-warning">Unknown table</div>'; }
        // Use DB columns if available (ensured earlier in CRUD handling); if not, fallback to schema
        $db_cols = get_table_columns_from_db($mysqli, $table);
        $cols = !empty($db_cols) ? $db_cols : $schemas[$table]['cols'];
        $pk = $cols[0] ?? null;
        $action = $_GET['action'] ?? '';
        $edit_row = null;
        if ($action === 'edit' && isset($_GET['id']) && $pk) $edit_row = fetch_one($mysqli, $table, $pk, $_GET['id']);

        // search query - exact match enforced by default
        $q = trim($_GET['q'] ?? '');
        $exact_flag = true; // exact match always
        $rows = fetch_rows($mysqli, $table, $cols, 500, $q, $exact_flag);
      ?>
        <div class="card card-modern p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="table-name"><?php echo h($schemas[$table]['label']); ?></div>
              <div class="small text-muted mt-1">Table: <strong><?php echo h($table); ?></strong></div>
            </div>
            <div class="d-flex">
              <form class="me-2 d-flex align-items-center" method="get" action="index.php">
                <input type="hidden" name="table" value="<?php echo h($table); ?>">
                <div class="input-group input-group-sm">
                  <input type="search" name="q" class="form-control form-control-sm" placeholder="Search this table (exact, case-insensitive)..." value="<?php echo h($q); ?>">
                  <button class="btn btn-outline-secondary btn-sm" type="submit">Search</button>
                  <a class="btn btn-outline-secondary btn-sm" href="index.php?table=<?php echo h($table); ?>" title="Clear">Clear</a>
                </div>
              </form>
              <a class="btn btn-outline-secondary btn-sm me-2" href="index.php">Dashboard</a>
              <a class="btn btn-outline-primary btn-sm" href="index.php?table=<?php echo h($table); ?>">Refresh</a>
            </div>
          </div>
        </div>

        <!-- Create / Edit form -->
        <div class="card card-modern p-3 mb-3">
          <div class="card-body">
            <h6><?php echo $edit_row ? 'Edit Record' : 'Create New Record'; ?></h6>
            <?php if ($notice): ?><div class="alert alert-warning"><?php echo h($notice); ?></div><?php endif; ?>
            <form method="post" class="row g-3">
              <?php foreach($cols as $c):
                $val = $edit_row[$c] ?? '';
                $readonly = ($edit_row && $c === $pk) ? 'readonly' : '';
                $type = 'text';
                if (stripos($c,'date')!==false) $type='date';
                elseif (stripos($c,'email')!==false) $type='email';
                elseif (stripos($c,'contact')!==false || stripos($c,'phone')!==false) $type='tel';
                elseif (stripos($c,'score')!==false || stripos($c,'count')!==false || stripos($c,'total')!==false || stripos($c,'average')!==false) $type='number';
              ?>
                <div class="col-md-6">
                  <label class="form-label"><?php echo h($c); ?></label>
                  <input class="form-control form-control-sm" name="<?php echo h($c); ?>" value="<?php echo h($val); ?>" type="<?php echo $type; ?>" <?php echo $readonly; ?>>
                </div>
              <?php endforeach; ?>
              <div class="col-12">
                <?php if ($edit_row): ?>
                  <input type="hidden" name="action" value="update">
                  <button class="btn btn-primary btn-sm">Update</button>
                  <a class="btn btn-secondary btn-sm" href="index.php?table=<?php echo h($table); ?>">Cancel</a>
                <?php else: ?>
                  <input type="hidden" name="action" value="create">
                  <button class="btn btn-success btn-sm">Create</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>

        <!-- Records list -->
        <div class="card card-modern p-3">
          <div class="table-responsive">
            <table class="table table-hover table-sm">
              <thead class="table-light">
                <tr>
                  <?php
                    if (!empty($cols)) {
                      foreach ($cols as $c) echo "<th>".h($c)."</th>";
                    } else {
                      if (!empty($rows)) {
                        foreach (array_keys($rows[0]) as $c) echo "<th>".h($c)."</th>";
                      }
                    }
                  ?>
                  <th style="width:140px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="<?php echo max(1,count($cols))+1; ?>">No records found.</td></tr>
                <?php else: foreach($rows as $r): ?>
                  <tr>
                    <?php
                      if (!empty($cols)) {
                        foreach ($cols as $c) echo '<td>'.h($r[$c] ?? '').'</td>';
                      } else {
                        foreach ($r as $v) echo '<td>'.h($v).'</td>';
                      }
                    ?>
                    <td>
                      <a class="btn btn-sm btn-primary" href="index.php?table=<?php echo h($table); ?>&action=edit&id=<?php echo urlencode($r[$pk]); ?>">Edit</a>
                      <a class="btn btn-sm btn-danger" href="index.php?table=<?php echo h($table); ?>&action=delete&id=<?php echo urlencode($r[$pk]); ?>" onclick="return confirm('Delete this record?');">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; // end table view ?>
    </div>
  </div>

<?php endif; // logged in ?>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:2000">
  <div id="toastEl" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastBody"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const toastMsg = <?php echo json_encode($toast_msg ?? ''); ?>;
if (toastMsg) {
  document.getElementById('toastBody').innerText = toastMsg;
  const t = new bootstrap.Toast(document.getElementById('toastEl'));
  t.show();
}
</script>
</body>
</html>
