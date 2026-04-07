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
            'goal'         => $_POST['goal'] ?? 'general_fitness',
            'experience'   => $_POST['experience'] ?? 'intermediate',
            'days_per_week'=> max(1, min(7, (int)($_POST['days_per_week'] ?? 3))),
            'equipment'    => $_POST['equipment'] ?? 'full_gym',
            'duration'     => $_POST['duration'] ?? '60',
            'focus_areas'  => $_POST['focus_areas'] ?? [],
            'details'      => trim($_POST['details'] ?? ''),
        ];

        // Validate
        if (!$form['plan_name']) {
            flash('Plan name is required.', 'error');
            $_SESSION['ai_form'] = $form;
            header('Location: ai_builder.php'); exit;
        }

        // Profanity check on free-text
        if ($form['details'] && contains_profanity($form['details'])) {
            flash('Please remove inappropriate language from the additional details field.', 'error');
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
- Each day must have sections (e.g. "Warm-Up", "Main Work", "Cool-Down"). Use sections appropriate to the goal.
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
            $day_label = sanitize_ai_text($day['day_label'] ?? ('Day ' . ($i + 1)));
            $day_title = sanitize_ai_text($day['day_title'] ?? ('Training Day ' . ($i + 1)));
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

        $_SESSION['ai_preview'] = [
            'form' => $form,
            'days' => $preview_days,
        ];
        header('Location: ai_builder.php?step=preview');
        exit;
    }
}

// ── Determine which step to show ────────────────────────────────────────────
$step = $_GET['step'] ?? 'form';
$form = $_SESSION['ai_form'] ?? [];
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
            style="width:auto;accent-color:var(--accent)">
          <?= $fo ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label>Additional Details <span style="font-weight:400;color:var(--muted2)">(optional)</span></label>
      <textarea name="details" rows="3" placeholder="e.g. Bad left knee, prefer dumbbells over barbells, want extra hip mobility work..."><?= htmlspecialchars($form['details'] ?? '') ?></textarea>
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

<?php endif; ?>
<?php render_foot(); ?>
