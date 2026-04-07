<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

// Redirect if no API key configured
if (!openai_api_key_configured()) {
    flash('AI workout builder is not available. Ask your admin to configure the OpenAI API key.', 'error');
    header('Location: plan_manager.php');
    exit;
}

// ── POST: Generate plan via OpenAI ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $form = [
            'plan_name'    => trim($_POST['plan_name'] ?? ''),
            'goal'         => in_array($_POST['goal'] ?? '', ['strength','hypertrophy','mobility','general_fitness']) ? $_POST['goal'] : 'general_fitness',
            'experience'   => in_array($_POST['experience'] ?? '', ['beginner','intermediate','advanced']) ? $_POST['experience'] : 'intermediate',
            'days_per_week'=> max(1, min(7, (int)($_POST['days_per_week'] ?? 3))),
            'equipment'    => in_array($_POST['equipment'] ?? '', ['full_gym','home_gym','minimal']) ? $_POST['equipment'] : 'full_gym',
            'duration'     => in_array($_POST['duration'] ?? '', ['30','45','60','90']) ? $_POST['duration'] : '60',
            'focus_areas'  => array_values(array_intersect($_POST['focus_areas'] ?? [], ['Upper Body','Lower Body','Core','Full Body','Mobility'])),
            'details'      => trim($_POST['details'] ?? ''),
        ];

        // Validate
        if (!$form['plan_name'] || mb_strlen($form['plan_name']) > 100) {
            flash('Plan name is required and must be under 100 characters.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Length check on details
        if ($form['details'] && mb_strlen($form['details']) > 500) {
            flash('Additional details must be under 500 characters.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Profanity check on user text
        if (contains_profanity($form['plan_name']) || ($form['details'] && contains_profanity($form['details']))) {
            flash('Please remove inappropriate language.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Build exercise library context
        $exercises = $db->query("SELECT name, muscle_group FROM exercises WHERE status='approved' ORDER BY muscle_group, name")->fetchAll();
        $lib_lines = [];
        foreach ($exercises as $ex) {
            $lib_lines[] = $ex['name'] . ' (' . $ex['muscle_group'] . ')';
        }
        $library_text = implode("\n", $lib_lines);

        // Build prompts
        $system_prompt = <<<PROMPT
You are a professional fitness coach. Generate a training plan as a JSON object.

RULES:
- Return ONLY a valid JSON object. No markdown, no commentary, no medical advice.
- Use neutral, professional language in all exercise names and coach tips.
- Prefer exercises from the EXISTING LIBRARY below when they fit. You may create new exercises when needed.
- Each day must have sections. Use ONLY from this list: "Cardio Warm-Up", "Mobility", "Stretching", "Core Block A", "Activation", "Main Work", "Functional", "Finisher", "Core Block B", "Cool-Down", "Reset".
- Sets must be an integer. Reps can be a string like "10-12" or "30 sec" or "5 each side".
- Coach tips should be one sentence, focused on form cues.

EXISTING EXERCISE LIBRARY:
{$library_text}

REQUIRED JSON SCHEMA:
{
  "days": [
    {
      "day_label": "Day 1",
      "day_title": "e.g. Upper Body Push",
      "sections": [
        {
          "name": "Section Name",
          "exercises": [
            {
              "name": "Exercise Name",
              "muscle_group": "Muscle Group",
              "sets": 3,
              "reps": "10-12",
              "coach_tip": "One sentence form cue."
            }
          ]
        }
      ]
    }
  ]
}
PROMPT;

        $focus_text = $form['focus_areas'] ? implode(', ', $form['focus_areas']) : 'balanced';
        $equip_map = ['full_gym' => 'Full gym with machines and free weights', 'home_gym' => 'Home gym with dumbbells, bench, pull-up bar', 'minimal' => 'Minimal equipment / bodyweight only'];
        $goal_map = ['strength' => 'Strength', 'hypertrophy' => 'Hypertrophy / muscle building', 'mobility' => 'Mobility and flexibility', 'general_fitness' => 'General fitness'];

        $user_prompt = "Create a {$form['days_per_week']}-day training plan.\n";
        $user_prompt .= "Goal: " . ($goal_map[$form['goal']] ?? $form['goal']) . "\n";
        $user_prompt .= "Experience: " . ucfirst($form['experience']) . "\n";
        $user_prompt .= "Equipment: " . ($equip_map[$form['equipment']] ?? $form['equipment']) . "\n";
        $user_prompt .= "Session duration: {$form['duration']} minutes\n";
        $user_prompt .= "Focus areas: {$focus_text}\n";
        if ($form['details']) {
            $user_prompt .= "Additional details: {$form['details']}\n";
        }

        // Call OpenAI
        $result = call_openai($system_prompt, $user_prompt);

        if (!$result || empty($result['days']) || !is_array($result['days'])) {
            flash('AI returned an unexpected response. Please try again.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Validate: at least 1 day with at least 1 exercise
        $has_exercise = false;
        foreach ($result['days'] as $day) {
            foreach (($day['sections'] ?? []) as $sec) {
                if (!empty($sec['exercises'])) { $has_exercise = true; break 2; }
            }
        }
        if (!$has_exercise) {
            flash('AI returned an empty plan. Please try again with different inputs.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Match exercises and build preview data
        $preview_days = [];
        foreach ($result['days'] as $i => $day) {
            $day_label = mb_substr(sanitize_ai_text($day['day_label'] ?? ('Day ' . ($i + 1))), 0, 20);
            $day_title = mb_substr(sanitize_ai_text($day['day_title'] ?? ('Training Day ' . ($i + 1))), 0, 60);
            $sections = [];
            foreach (($day['sections'] ?? []) as $sec) {
                $sec_name = sanitize_ai_text($sec['name'] ?? 'Main Work');
                $exercises_out = [];
                foreach (($sec['exercises'] ?? []) as $ex) {
                    $ex_name = sanitize_ai_text($ex['name'] ?? '');
                    $ex_muscle = sanitize_ai_text($ex['muscle_group'] ?? '');
                    $ex_tip = isset($ex['coach_tip']) ? sanitize_ai_text($ex['coach_tip']) : null;
                    if (!$ex_name) continue;

                    // Check if it exists before matching (for preview labels)
                    $chk = $db->prepare("SELECT id FROM exercises WHERE LOWER(name) = LOWER(?) AND status='approved' LIMIT 1");
                    $chk->execute([$ex_name]);
                    $exact = $chk->fetch();

                    $chk2 = null;
                    if (!$exact) {
                        $chk2 = $db->prepare("SELECT id FROM exercises WHERE LOWER(name) LIKE LOWER(?) AND status='approved' ORDER BY CHAR_LENGTH(name) ASC LIMIT 1");
                        $chk2->execute(['%' . $ex_name . '%']);
                        $chk2 = $chk2->fetch();
                    }
                    $is_new = !$exact && !$chk2;

                    $yt_url = '';
                    if ($exact || $chk2) {
                        $eid = $exact ? $exact['id'] : $chk2['id'];
                        $yt = $db->prepare("SELECT youtube_url FROM exercises WHERE id=?");
                        $yt->execute([$eid]);
                        $yt_url = $yt->fetchColumn() ?: '';
                    }
                    if (!$yt_url) {
                        $yt_url = 'https://www.youtube.com/results?search_query=' . urlencode($ex_name . ' tutorial form');
                    }

                    $exercises_out[] = [
                        'name'         => $ex_name,
                        'muscle_group' => $ex_muscle,
                        'sets'         => max(1, (int)($ex['sets'] ?? 3)),
                        'reps'         => sanitize_ai_text((string)($ex['reps'] ?? '10-12')),
                        'coach_tip'    => $ex_tip,
                        'is_new'       => $is_new,
                        'youtube_url'  => $yt_url,
                    ];
                }
                if ($exercises_out) {
                    $sections[] = ['name' => $sec_name, 'exercises' => $exercises_out];
                }
            }
            if ($sections) {
                $preview_days[] = ['day_label' => $day_label, 'day_title' => $day_title, 'sections' => $sections];
            }
        }

        unset($_SESSION['ai_form']);
        $_SESSION['ai_preview'] = [
            'form' => $form,
            'days' => $preview_days,
        ];
        header('Location: ai_builder.php?step=preview');
        exit;
    }

    if ($action === 'accept') {
        $preview = $_SESSION['ai_preview'] ?? null;
        if (!$preview) {
            flash('No plan to accept. Please generate one first.', 'error');
            header('Location: ai_builder.php'); exit;
        }

        $form = $preview['form'];
        $days = $preview['days'];

        $db->beginTransaction();
        try {
            // Create the plan
            $start = date('Y-m-d');
            $weeks = 8;
            $end = date('Y-m-d', strtotime("+{$weeks} weeks"));
            $goal_label = ucfirst(str_replace('_', ' ', $form['goal']));
            $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active, user_id) VALUES (?,?,1,?,?,?,0,?)")
               ->execute([$form['plan_name'], 'AI-generated plan: ' . $goal_label, $weeks, $start, $end, $uid]);
            $plan_id = (int)$db->lastInsertId();

            // Create days and exercises
            foreach ($days as $d_idx => $day) {
                $db->prepare("INSERT INTO plan_days (plan_id, day_label, day_title, day_order) VALUES (?,?,?,?)")
                   ->execute([$plan_id, $day['day_label'], $day['day_title'], $d_idx + 1]);

                foreach ($day['sections'] as $s_idx => $sec) {
                    foreach ($sec['exercises'] as $e_idx => $ex) {
                        $exercise_id = match_exercise($db, $ex['name'], $ex['muscle_group'], $ex['coach_tip'], $uid);
                        $db->prepare("INSERT INTO plan_exercises (plan_id, day_label, exercise_id, section, section_order, sort_order, sets_target, reps_target) VALUES (?,?,?,?,?,?,?,?)")
                           ->execute([$plan_id, $day['day_label'], $exercise_id, $sec['name'], $s_idx, $e_idx + 1, $ex['sets'], $ex['reps']]);
                    }
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            flash('Failed to create plan. Please try again.', 'error');
            header('Location: ai_builder.php?step=preview'); exit;
        }

        unset($_SESSION['ai_preview'], $_SESSION['ai_form']);
        flash('Plan created! Customise it in the builder.');
        header("Location: plan_builder.php?plan_id=$plan_id");
        exit;
    }
}

// ── Determine which step to show ────────────────────────────────────────────
$step = $_GET['step'] ?? 'form';
$form = $_SESSION['ai_form'] ?? ($_SESSION['ai_preview']['form'] ?? []);
// Clear form session after reading
if ($step === 'form') unset($_SESSION['ai_preview']);

render_head('AI Workout Builder', 'plans');
?>

<?php if ($step === 'form'): ?>
<!-- ── AI FORM ────────────────────────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-title">AI Workout Builder</div>
  <div class="page-sub">Answer a few questions and AI will generate a starting plan for you to customise</div>
</div>

<div class="card" style="max-width:640px">
  <form method="post" id="ai-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate">

    <div class="form-group">
      <label>Plan Name</label>
      <input type="text" name="plan_name" value="<?= htmlspecialchars($form['plan_name'] ?? '') ?>" placeholder="e.g. AI Hypertrophy Phase 1" required>
    </div>

    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Goal</label>
        <select name="goal">
          <?php foreach (['strength'=>'Strength','hypertrophy'=>'Hypertrophy','mobility'=>'Mobility','general_fitness'=>'General Fitness'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($form['goal'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Experience Level</label>
        <select name="experience">
          <?php foreach (['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($form['experience'] ?? 'intermediate') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row form-row-3">
      <div class="form-group">
        <label>Days per Week</label>
        <select name="days_per_week">
          <?php for ($d = 1; $d <= 7; $d++): ?>
          <option value="<?= $d ?>" <?= (int)($form['days_per_week'] ?? 3) === $d ? 'selected' : '' ?>><?= $d ?> day<?= $d > 1 ? 's' : '' ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Equipment</label>
        <select name="equipment">
          <?php foreach (['full_gym'=>'Full Gym','home_gym'=>'Home Gym','minimal'=>'Minimal / Bodyweight'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($form['equipment'] ?? 'full_gym') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Session Duration</label>
        <select name="duration">
          <?php foreach (['30'=>'30 min','45'=>'45 min','60'=>'60 min','90'=>'90 min'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($form['duration'] ?? '60') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Focus Areas</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
        <?php
        $focus_opts = ['Upper Body','Lower Body','Core','Full Body','Mobility'];
        $selected_focus = $form['focus_areas'] ?? [];
        foreach ($focus_opts as $fo):
        ?>
        <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;padding:6px 12px;background:var(--bg3);border:1px solid var(--border2);border-radius:8px">
          <input type="checkbox" name="focus_areas[]" value="<?= $fo ?>" <?= in_array($fo, $selected_focus) ? 'checked' : '' ?>
            style="width:auto;accent-color:var(--accent);-webkit-appearance:checkbox;appearance:checkbox">
          <?= $fo ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label>Additional Details <span style="font-weight:400;color:var(--muted2)">(optional)</span></label>
      <textarea name="details" rows="3" maxlength="500" placeholder="e.g. Bad left knee, prefer dumbbells over barbells, want extra hip mobility work..."><?= htmlspecialchars($form['details'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn btn-primary" id="ai-submit-btn">Generate Plan</button>
      <a href="plan_manager.php" class="btn btn-ghost btn-sm">Cancel</a>
    </div>
  </form>
</div>

<script>
document.getElementById('ai-form').addEventListener('submit', function() {
    var btn = document.getElementById('ai-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Generating your plan...';
});
</script>

<?php elseif ($step === 'preview' && isset($_SESSION['ai_preview'])): ?>
<?php $preview = $_SESSION['ai_preview']; $pform = $preview['form']; $pdays = $preview['days']; ?>
<!-- ── PREVIEW ────────────────────────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-title">Preview: <?= htmlspecialchars($pform['plan_name']) ?></div>
  <div class="page-sub">
    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pform['goal']))) ?> &middot;
    <?= (int)$pform['days_per_week'] ?> days/week &middot;
    <?= htmlspecialchars(ucfirst($pform['experience'])) ?> &middot;
    <?= htmlspecialchars($pform['duration']) ?> min sessions
  </div>
</div>

<?php foreach ($pdays as $d_idx => $day): ?>
<div class="card" style="margin-bottom:1rem">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;cursor:pointer" onclick="this.parentElement.querySelector('.day-content').classList.toggle('collapsed')">
    <?= day_pill($day['day_label']) ?>
    <span style="font-size:16px;font-weight:700;color:var(--text)"><?= htmlspecialchars($day['day_title']) ?></span>
  </div>
  <div class="day-content">
    <?php foreach ($day['sections'] as $sec): ?>
    <div class="section-hdr"><?= htmlspecialchars($sec['name']) ?></div>
    <table>
      <thead>
        <tr>
          <th>Exercise</th>
          <th style="width:70px">Sets</th>
          <th style="width:90px">Reps</th>
          <th style="width:60px">Video</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sec['exercises'] as $ex): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span style="font-weight:600"><?= htmlspecialchars($ex['name']) ?></span>
              <?php if ($ex['is_new']): ?>
              <span class="badge" style="background:var(--warn-dim);color:var(--warn-text)">New</span>
              <?php else: ?>
              <span class="badge" style="background:var(--green-dim);color:var(--green-text)">Library</span>
              <?php endif; ?>
            </div>
            <?php if ($ex['coach_tip']): ?>
            <div class="coach-tip"><?= htmlspecialchars($ex['coach_tip']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= $ex['sets'] ?></td>
          <td><?= htmlspecialchars($ex['reps']) ?></td>
          <td><a href="<?= htmlspecialchars($ex['youtube_url']) ?>" target="_blank" class="btn-yt">YT</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div style="display:flex;gap:10px;align-items:center;margin-top:1.5rem">
  <form method="post" style="display:inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="accept">
    <button type="submit" class="btn btn-primary">Accept &amp; Open Builder</button>
  </form>
  <a href="ai_builder.php" class="btn btn-ghost btn-sm">Regenerate</a>
  <a href="plan_manager.php" class="btn btn-ghost btn-sm">Cancel</a>
</div>

<style>
.day-content.collapsed { display: none; }
</style>

<?php endif; ?>
<?php render_foot(); ?>
