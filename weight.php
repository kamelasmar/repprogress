<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'log_weight') {
        $st = $db->prepare("INSERT INTO weight_log (logged_date, weight_kg, notes) VALUES (?,?,?) ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg), notes=VALUES(notes)");
        $st->execute([$_POST['logged_date'], $_POST['weight_kg'], $_POST['notes'] ?: null]);
        flash('Weight logged!');
        header("Location: weight.php"); exit;
    }
    if ($action === 'delete_weight') {
        $db->prepare("DELETE FROM weight_log WHERE id=?")->execute([$_POST['id']]);
        flash('Entry deleted.');
        header("Location: weight.php"); exit;
    }
}

$all = $db->query("SELECT * FROM weight_log ORDER BY logged_date ASC")->fetchAll();
$latest    = $all ? end($all) : null;
$first     = $all ? $all[0] : null;
$total_del = ($latest && $first) ? round($latest['weight_kg'] - $first['weight_kg'], 1) : null;
$last7     = array_filter($all, fn($r) => $r['logged_date'] >= date('Y-m-d', strtotime('-7 days')));
$delta_7   = count($last7) >= 2 ? round(end($last7)['weight_kg'] - reset($last7)['weight_kg'], 1) : null;

$chart_labels = json_encode(array_column($all, 'logged_date'));
$chart_data   = json_encode(array_map('floatval', array_column($all, 'weight_kg')));

render_head('Weight Tracker', 'weight');
?>

<div class="page-header">
  <div class="page-title">Weight Tracker</div>
  <div class="page-sub">Monitor body weight trends over time</div>
</div>

<div class="grid-3" style="margin-bottom:1.25rem">
  <div class="metric">
    <div class="metric-label">Current Weight</div>
    <div class="metric-value"><?= $latest ? number_format($latest['weight_kg'],1).' kg' : '—' ?></div>
    <div class="metric-sub"><?= $latest ? date('M j', strtotime($latest['logged_date'])) : 'No data yet' ?></div>
  </div>
  <div class="metric">
    <div class="metric-label">Total Change</div>
    <div class="metric-value <?= $total_del !== null ? ($total_del <= 0 ? 'metric-up' : 'metric-down') : '' ?>">
      <?= $total_del !== null ? ($total_del > 0 ? '+' : '').$total_del.' kg' : '—' ?>
    </div>
    <div class="metric-sub">since <?= $first ? date('M j, Y', strtotime($first['logged_date'])) : '—' ?></div>
  </div>
  <div class="metric">
    <div class="metric-label">Last 7 Days</div>
    <div class="metric-value <?= $delta_7 !== null ? ($delta_7 <= 0 ? 'metric-up' : 'metric-down') : '' ?>">
      <?= $delta_7 !== null ? ($delta_7 > 0 ? '+' : '').$delta_7.' kg' : '—' ?>
    </div>
    <div class="metric-sub">weekly delta</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-title">Weight Trend</div>
    <?php if (count($all) >= 2): ?>
    <canvas id="wChart" height="200"></canvas>
    <?php else: ?>
    <div class="empty"><p>Log at least 2 entries to see your trend.</p></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-title">Log Weight</div>
      <form method="post">
        <input type="hidden" name="action" value="log_weight">
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="logged_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Weight (kg)</label>
          <input type="number" name="weight_kg" step="0.1" min="30" max="300" placeholder="80.5" required>
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <input type="text" name="notes" placeholder="e.g. morning, fasted">
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title">History</div>
      <?php if ($all): ?>
      <table>
        <thead><tr><th>Date</th><th>Weight</th><th>Notes</th><th></th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($all) as $r): ?>
        <tr>
          <td><?= date('M j, Y', strtotime($r['logged_date'])) ?></td>
          <td><strong><?= number_format($r['weight_kg'],1) ?> kg</strong></td>
          <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="delete_weight">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-danger btn-sm">×</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty"><p>No entries yet. Log your first weight above.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (count($all) >= 2): ?>
<script>
new Chart(document.getElementById('wChart'), {
  type: 'line',
  data: { labels: <?= $chart_labels ?>, datasets: [{
    label: 'kg', data: <?= $chart_data ?>,
    borderColor:'#5b9fd6', backgroundColor:'rgba(91,159,214,0.12)',
    fill:true, tension:0.4, pointRadius:4, pointBackgroundColor:'#5b9fd6'
  }]},
  options: { responsive:true, plugins:{legend:{display:false}},
    scales:{ y:{grid:{color:'rgba(255,255,255,0.06)'}}, x:{grid:{display:false}} } }
});
</script>
<?php endif; ?>

<?php render_foot(); ?>
