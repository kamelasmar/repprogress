<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'log_weight') {
        $log_date = trim($_POST['logged_date'] ?? '');
        $log_weight = trim($_POST['weight_kg'] ?? '');
        if (!$log_date || !$log_weight) {
            flash('Date and weight are required.', 'error');
            header("Location: weight.php"); exit;
        }
        $st = $db->prepare("INSERT INTO weight_log (logged_date, weight_kg, body_fat_pct, muscle_mass_pct, notes, user_id)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg), body_fat_pct=VALUES(body_fat_pct), muscle_mass_pct=VALUES(muscle_mass_pct), notes=VALUES(notes)");
        $st->execute([
            $log_date,
            $log_weight,
            $_POST['body_fat_pct'] ?: null,
            $_POST['muscle_mass_pct'] ?: null,
            $_POST['notes'] ?: null,
            $uid
        ]);
        flash('Body composition logged!');
        header("Location: weight.php"); exit;
    }
    if ($action === 'delete_weight') {
        $db->prepare("DELETE FROM weight_log WHERE id=? AND user_id=?")->execute([$_POST['id'], $uid]);
        flash('Entry deleted.');
        header("Location: weight.php"); exit;
    }
}

$st = $db->prepare("SELECT * FROM weight_log WHERE user_id=? ORDER BY logged_date ASC");
$st->execute([$uid]);
$all = $st->fetchAll();

$latest = $all ? end($all) : null;
$first  = $all ? $all[0] : null;
$delta_7 = null;
$last7 = array_filter($all, fn($r) => $r['logged_date'] >= date('Y-m-d', strtotime('-7 days')));
if (count($last7) >= 2) {
    $delta_7 = round(end($last7)['weight_kg'] - reset($last7)['weight_kg'], 1);
}

// Body comp deltas (latest vs previous entry)
$prev = count($all) >= 2 ? $all[count($all) - 2] : null;
$fat_delta = ($latest && $prev && $latest['body_fat_pct'] && $prev['body_fat_pct'])
    ? round($latest['body_fat_pct'] - $prev['body_fat_pct'], 1) : null;
$muscle_delta = ($latest && $prev && $latest['muscle_mass_pct'] && $prev['muscle_mass_pct'])
    ? round($latest['muscle_mass_pct'] - $prev['muscle_mass_pct'], 1) : null;

// Chart data
$chart_labels = json_encode(array_column($all, 'logged_date'));
$chart_weight = json_encode(array_map('floatval', array_column($all, 'weight_kg')));

// Body comp chart data (only entries that have values)
$comp_entries = array_filter($all, fn($r) => $r['body_fat_pct'] || $r['muscle_mass_pct']);
$comp_labels  = json_encode(array_column($comp_entries, 'logged_date'));
$comp_fat     = json_encode(array_map(fn($r) => $r['body_fat_pct'] ? (float)$r['body_fat_pct'] : null, array_values($comp_entries)));
$comp_muscle  = json_encode(array_map(fn($r) => $r['muscle_mass_pct'] ? (float)$r['muscle_mass_pct'] : null, array_values($comp_entries)));

render_head('Body Composition', 'weight');
?>

<div class="page-header">
  <div class="page-title">Body Composition</div>
  <div class="page-sub">Track weight, body fat, and muscle mass over time</div>
</div>

<div class="grid-4 mb-5">
  <div class="metric">
    <div class="metric-label">Current Weight</div>
    <div class="metric-value"><?= $latest ? number_format($latest['weight_kg'],1).' kg' : '—' ?></div>
    <div class="metric-sub"><?= $latest ? date('M j', strtotime($latest['logged_date'])) : 'No data yet' ?></div>
  </div>
  <div class="metric">
    <div class="metric-label">Body Fat</div>
    <div class="metric-value"><?= ($latest && $latest['body_fat_pct']) ? number_format($latest['body_fat_pct'],1).'%' : '—' ?></div>
    <?php if ($fat_delta !== null): ?>
    <div class="metric-sub <?= $fat_delta <= 0 ? 'metric-up' : 'metric-down' ?>"><?= $fat_delta > 0 ? '+' : '' ?><?= $fat_delta ?>% vs prev</div>
    <?php else: ?>
    <div class="metric-sub">no trend yet</div>
    <?php endif; ?>
  </div>
  <div class="metric">
    <div class="metric-label">Muscle Mass</div>
    <div class="metric-value"><?= ($latest && $latest['muscle_mass_pct']) ? number_format($latest['muscle_mass_pct'],1).'%' : '—' ?></div>
    <?php if ($muscle_delta !== null): ?>
    <div class="metric-sub <?= $muscle_delta >= 0 ? 'metric-up' : 'metric-down' ?>"><?= $muscle_delta > 0 ? '+' : '' ?><?= $muscle_delta ?>% vs prev</div>
    <?php else: ?>
    <div class="metric-sub">no trend yet</div>
    <?php endif; ?>
  </div>
  <div class="metric">
    <div class="metric-label">7-Day Delta</div>
    <div class="metric-value <?= $delta_7 !== null ? ($delta_7 <= 0 ? 'metric-up' : 'metric-down') : '' ?>">
      <?= $delta_7 !== null ? ($delta_7 > 0 ? '+' : '').$delta_7.' kg' : '—' ?>
    </div>
    <div class="metric-sub">weight change</div>
  </div>
</div>

<!-- Charts -->
<div class="grid-2 mb-5">
  <div class="card">
    <div class="card-title">Weight Trend</div>
    <?php if (count($all) >= 2): ?>
    <canvas id="wChart" height="200"></canvas>
    <?php else: ?>
    <div class="empty"><p>Log at least 2 entries to see your trend.</p></div>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-title">Body Composition Trend</div>
    <?php if (count($comp_entries) >= 2): ?>
    <canvas id="compChart" height="200"></canvas>
    <?php else: ?>
    <div class="empty"><p>Log body fat or muscle mass to see composition trends.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <!-- Log form -->
  <div class="card">
    <div class="card-title">Log Body Composition</div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log_weight">
      <div class="form-group">
        <label>Date</label>
        <input type="date" name="logged_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label>Weight (kg) <span class="text-red">*</span></label>
        <input type="number" name="weight_kg" step="0.1" min="30" max="300" placeholder="80.5" required>
      </div>
      <div class="form-row form-row-2">
        <div class="form-group">
          <label>Body Fat %</label>
          <input type="number" name="body_fat_pct" step="0.1" min="1" max="60" placeholder="18.5">
        </div>
        <div class="form-group">
          <label>Muscle Mass %</label>
          <input type="number" name="muscle_mass_pct" step="0.1" min="10" max="70" placeholder="42.0">
        </div>
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <input type="text" name="notes" placeholder="e.g. morning, fasted, DEXA scan">
      </div>
      <button type="submit" class="btn btn-primary">Save</button>
    </form>
  </div>

  <!-- History -->
  <div class="card">
    <div class="card-title">History</div>
    <?php if ($all): ?>
    <div class="overflow-x-auto">
    <table>
      <thead><tr><th>Date</th><th>Weight</th><th>Fat %</th><th>Muscle %</th><th>Notes</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($all) as $r): ?>
      <tr>
        <td class="whitespace-nowrap"><?= date('M j, Y', strtotime($r['logged_date'])) ?></td>
        <td><strong><?= number_format($r['weight_kg'],1) ?> kg</strong></td>
        <td><?= $r['body_fat_pct'] ? number_format($r['body_fat_pct'],1).'%' : '—' ?></td>
        <td><?= $r['muscle_mass_pct'] ? number_format($r['muscle_mass_pct'],1).'%' : '—' ?></td>
        <td class="text-muted text-[13px]"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
        <td>
          <form method="post" class="inline" x-data x-on:submit="if(!confirm('Delete this entry?')) $event.preventDefault()">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_weight">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-danger btn-sm">&times;</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty"><p>No entries yet. Log your first body composition above.</p></div>
    <?php endif; ?>
  </div>
</div>

<?php if (count($all) >= 2 || count($comp_entries) >= 2): ?>
<script>
<?php if (count($all) >= 2): ?>
new Chart(document.getElementById('wChart'), {
  type: 'line',
  data: { labels: <?= $chart_labels ?>, datasets: [{
    label: 'kg', data: <?= $chart_weight ?>,
    borderColor:'#5b9fd6', backgroundColor:'rgba(91,159,214,0.12)',
    fill:true, tension:0.4, pointRadius:4, pointBackgroundColor:'#5b9fd6'
  }]},
  options: { responsive:true, plugins:{legend:{display:false}},
    scales:{ y:{grid:{color:'rgba(255,255,255,0.06)'}}, x:{grid:{display:false}} } }
});
<?php endif; ?>
<?php if (count($comp_entries) >= 2): ?>
new Chart(document.getElementById('compChart'), {
  type: 'line',
  data: { labels: <?= $comp_labels ?>, datasets: [
    {
      label: 'Body Fat %', data: <?= $comp_fat ?>,
      borderColor:'#d4924a', backgroundColor:'rgba(212,146,74,0.1)',
      fill:false, tension:0.4, pointRadius:4, pointBackgroundColor:'#d4924a', spanGaps:true
    },
    {
      label: 'Muscle Mass %', data: <?= $comp_muscle ?>,
      borderColor:'#1D9E75', backgroundColor:'rgba(29,158,117,0.1)',
      fill:false, tension:0.4, pointRadius:4, pointBackgroundColor:'#1D9E75', spanGaps:true
    }
  ]},
  options: { responsive:true,
    plugins:{legend:{display:true, position:'top', labels:{boxWidth:12, font:{size:12}}}},
    scales:{ y:{grid:{color:'rgba(255,255,255,0.06)'}}, x:{grid:{display:false}} } }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php render_foot(); ?>
