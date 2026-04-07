-- Kamel's Workout Tracker — Versioned Plan Schema
-- Run via install.php (browser installer)

CREATE DATABASE IF NOT EXISTS fittrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fittrack;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS sets_log;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS plan_exercises;
DROP TABLE IF EXISTS plan_days;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS exercises;
DROP TABLE IF EXISTS weight_log;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Exercise Library (permanent, never deleted) ──────────────────────────────
-- Pure movement library. Day/plan assignments live in plan_exercises.
CREATE TABLE exercises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  muscle_group VARCHAR(50) NOT NULL,
  is_mobility TINYINT(1) DEFAULT 0,
  is_core TINYINT(1) DEFAULT 0,
  is_functional TINYINT(1) DEFAULT 0,
  cardio_type ENUM('none','steady_state','hiit') DEFAULT 'none',
  youtube_url VARCHAR(255),
  coach_tip TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Training Plans ────────────────────────────────────────────────────────────
CREATE TABLE plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  phase_number INT DEFAULT 1,
  weeks_duration INT DEFAULT 8,
  start_date DATE,
  end_date DATE,
  is_active TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Plan Days ────────────────────────────────────────────────────────────────
-- Which days exist in a plan, and what they're called.
CREATE TABLE plan_days (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  day_label VARCHAR(20) NOT NULL,     -- 'Day 1', 'Day 2', etc.
  day_title VARCHAR(60) NOT NULL,     -- 'Lower Body', 'Push', etc.
  day_order TINYINT DEFAULT 0,
  week_day VARCHAR(10),               -- 'Tue', 'Wed', etc. (recommended schedule)
  cardio_type ENUM('none','steady_state','hiit') DEFAULT 'none',
  cardio_description VARCHAR(200),
  UNIQUE KEY unique_plan_day (plan_id, day_label),
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- ── Plan Exercises ────────────────────────────────────────────────────────────
-- Assigns exercises to a plan+day, with targets and left/right config.
-- Deleting or editing this never touches logged session data.
CREATE TABLE plan_exercises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  day_label VARCHAR(20) NOT NULL,
  exercise_id INT NOT NULL,
  section VARCHAR(100) DEFAULT 'Main Work',
  section_order TINYINT DEFAULT 0,
  sort_order INT DEFAULT 0,
  sets_target INT DEFAULT 3,
  reps_target VARCHAR(30) DEFAULT '10-12',   -- can be '10-12', '30 sec', '30m', etc.
  sets_left INT DEFAULT 0,                    -- extra sets for left side (0 = same as right)
  reps_left_bonus INT DEFAULT 0,              -- extra reps per left set
  is_left_priority TINYINT(1) DEFAULT 0,
  both_sides TINYINT(1) DEFAULT 0,
  notes TEXT,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
  FOREIGN KEY (exercise_id) REFERENCES exercises(id)
);

-- ── Sessions ─────────────────────────────────────────────────────────────────
-- Historical data. plan_id records which plan was active at time of logging.
-- Never deleted when switching plans.
CREATE TABLE sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_date DATE NOT NULL,
  day_label VARCHAR(20) NOT NULL,
  title VARCHAR(100) NOT NULL,
  plan_id INT NULL,                   -- which plan this was logged under
  duration_min INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
);

-- ── Sets Log ─────────────────────────────────────────────────────────────────
CREATE TABLE sets_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  exercise_id INT NOT NULL,
  set_number INT NOT NULL DEFAULT 1,
  reps INT,
  weight_kg DECIMAL(6,2),
  duration_sec INT,
  side ENUM('left','right','both') DEFAULT 'both',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (exercise_id) REFERENCES exercises(id)
);

-- ── Weight Log ───────────────────────────────────────────────────────────────
CREATE TABLE weight_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  logged_date DATE NOT NULL,
  weight_kg DECIMAL(5,2) NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_date (logged_date)
);

-- ════════════════════════════════════════════════════════════════════════════
-- EXERCISE LIBRARY SEED
-- ════════════════════════════════════════════════════════════════════════════

INSERT INTO exercises (name,muscle_group,is_mobility,is_core,is_functional,cardio_type,youtube_url,coach_tip) VALUES
-- Cardio
('Rowing Machine — Steady State','Cardio',0,0,1,'steady_state','https://www.youtube.com/results?search_query=Rowing+Machine+—+Steady+State+tutorial+form','Zone 2 — conversational pace. 22–24 spm. Great hip hinge activation and lat primer.'),
('Rowing Machine — Power Intervals','Cardio',0,0,1,'hiit','https://www.youtube.com/results?search_query=rowing+machine+intervals+HIIT+tutorial','HIIT: 250m hard / 90 sec rest × 5. Benchmark your 250m split time each session.'),
('Ski Erg — Intervals','Cardio',0,0,1,'hiit','https://www.youtube.com/results?search_query=Ski+Erg+—+Intervals+tutorial+form','HIIT: 20 sec hard / 40 sec easy. No cervical load. Activates triceps, lats, serratus.'),
('Stationary Bike — Steady State','Cardio',0,0,0,'steady_state','https://www.youtube.com/results?search_query=stationary+bike+zone+2+cardio+tutorial','Zone 2 — low resistance. Weekly easy cardio. Legs active, CNS recovering.'),
('Battle Ropes — Alternating Waves','Full Body',0,0,1,'hiit','https://www.youtube.com/results?search_query=battle+ropes+alternating+waves+HIIT+tutorial','30 sec on / 30 sec off. Shoulder stabilisers including serratus. No cervical load.'),
-- Hip Mobility
('90/90 Hip Switch','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=90+90+hip+switch+mobility','Non-negotiable daily habit. 60 sec each side. Breathe into the back hip.'),
('Hip CARs','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=hip+controlled+articular+rotations+CARs','Controlled articular rotations. Active, not passive. Own every degree of range.'),
('World Greatest Stretch','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=worlds+greatest+stretch+tutorial','Thoracic rotation + hip flexor + hamstring in one flow. No barbell, no load.'),
('Cossack Squat','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=cossack+squat+lateral+hip+mobility+tutorial','Lateral hip mobility + adductor length. Hold 2 sec at the bottom of each rep.'),
('Hip Flexor Lunge + Rotation','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=hip+flexor+lunge+rotation+stretch','Reach opposite arm overhead at end of each rep. Opens thoracic spine.'),
('Pigeon Pose','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=pigeon+pose+hip+stretch+tutorial','Breathe through it. Figure-four on back if too intense. 90 sec each side.'),
('Couch Stretch','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=couch+stretch+hip+flexor+tutorial','Posterior tilt pelvis for full stretch. Against wall. 90 sec each side.'),
('Deep Squat + Thoracic Rotation','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=deep+squat+thoracic+rotation+mobility','Open chest in hole. Hands behind head. 3 sec hold at each rotation.'),
('Lat Prayer Stretch on Foam Roller','Lats',1,0,0,'none','https://www.youtube.com/results?search_query=lat+stretch+foam+roller+tutorial','Arm extended. Feel the lat lengthening before you load it.'),
('Supine Psoas Release','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=supine+psoas+release+hip+flexor+tutorial','Slow diaphragmatic breathing. Let hip sink into floor.'),
('Frog Stretch','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=frog+stretch+hip+inner+groin+tutorial','Knees wide, feet turned out. Rock gently forward and back.'),
('Lateral Lunge + Adductor Stretch','Hips',1,0,0,'none','https://www.youtube.com/results?search_query=lateral+lunge+adductor+stretch+tutorial','Shift weight slowly, pause at depth.'),
-- Core
('Dead Bug','Core',0,1,0,'none','https://www.youtube.com/results?search_query=dead+bug+exercise+core+tutorial','Spine flat to floor. Cervical neutral — look up not forward. 3×10 each side.'),
('McGill Curl-Up','Core',0,1,0,'none','https://www.youtube.com/results?search_query=mcgill+curl+up+safe+spinal+flexion','Safe cervical flexion. Head lifts only — neck does not crane. Hands under lumbar.'),
('Bird Dog','Core',0,1,0,'none','https://www.youtube.com/results?search_query=bird+dog+exercise+core+stability','Opposite arm-leg. 2 sec hold. Zero lumbar rotation. Cervical neutral.'),
('Pallof Press — Tall Kneeling','Core',0,1,0,'none','https://www.youtube.com/results?search_query=pallof+press+kneeling+anti+rotation+core','Anti-rotation. Left serratus + core integrated. Hold 2 sec at extension.'),
('Plank — Forearm','Core',0,1,0,'none','https://www.youtube.com/results?search_query=forearm+plank+core+tutorial','Rigid body. No sagging hips. Cervical in line with spine.'),
('Copenhagen Plank','Core',0,1,0,'none','https://www.youtube.com/results?search_query=Copenhagen+Plank+tutorial+form','Inner thigh + lateral core. Track left vs right strength gap.'),
('Ab Wheel Rollout','Core',0,1,0,'none','https://www.youtube.com/results?search_query=ab+wheel+rollout+core+anti+extension+tutorial','From knees. Go to point of no lumbar arch. Anti-extension core.'),
('Side Plank','Core',0,1,0,'none','https://www.youtube.com/results?search_query=side+plank+tutorial+lateral+core','Both sides. 30 sec each. Hips stacked. Cervical neutral.'),
('Side Plank + Hip Dip','Core',0,1,0,'none','https://www.youtube.com/results?search_query=side+plank+hip+dip+lateral+core+tutorial','Both sides. 10 dips each. Lateral core endurance.'),
('Cable Crunch','Core',0,1,0,'none','https://www.youtube.com/results?search_query=cable+crunch+core+tutorial','Cable above, kneel. Crunch elbows to knees. Cervical neutral — head follows trunk.'),
('Suitcase Carry','Core',0,1,1,'none','https://www.youtube.com/results?search_query=suitcase+carry+core+lateral+tutorial','Lateral core + lat integration. Left hand priority. Hips level.'),
-- Lower Body — Machines
('Leg Press (Machine)','Legs',0,0,0,'none','https://www.youtube.com/results?search_query=leg+press+machine+form+tutorial','Feet hip-width, mid-platform. No bar on shoulders. Full ROM without locking knees.'),
('Leg Curl — Lying (Machine)','Hamstrings',0,0,0,'none','https://www.youtube.com/results?search_query=lying+leg+curl+machine+form','Both sides + single leg. Control the eccentric (3 sec down).'),
('Leg Extension (Machine)','Quads',0,0,0,'none','https://www.youtube.com/results?search_query=leg+extension+machine+tutorial','Both sides + single leg. Identify quad strength asymmetry.'),
('Hip Abductor Machine','Hips',0,0,0,'none','https://www.youtube.com/results?search_query=hip+abductor+machine+tutorial','Controls lateral hip stability. Both sides.'),
('Hip Adductor Machine','Hips',0,0,0,'none','https://www.youtube.com/results?search_query=hip+adductor+machine+tutorial','Inner thigh + groin. Seat upright, no forward lean. Both sides.'),
('Glute Bridge — Machine or Band','Glutes',0,0,0,'none','https://www.youtube.com/results?search_query=Glute+Bridge+—+Machine+or+Band+tutorial+form','No bar on hips/shoulders. Drive through heels. Squeeze at top 2 sec.'),
('Calf Raise — Seated Machine','Calves',0,0,0,'none','https://www.youtube.com/results?search_query=seated+calf+raise+machine+tutorial','Seated = soleus-dominant. Full range. Pause at bottom.'),
('Goblet Squat (DB at Chest)','Legs',0,0,1,'none','https://www.youtube.com/results?search_query=goblet+squat+dumbbell+form+tutorial','DB at chest not shoulders. Cervical-safe. Elbows inside knees.'),
('Bulgarian Split Squat (DBs at Sides)','Legs',0,0,0,'none','https://www.youtube.com/results?search_query=Bulgarian+Split+Squat+(DBs+at+Sides)+tutorial+form','DBs at sides — no bar on shoulder. Front foot out, shin vertical.'),
-- Functional / Full Body
('Kettlebell Deadlift','Full Body',0,0,1,'none','https://www.youtube.com/results?search_query=kettlebell+deadlift+form+tutorial','Hinge not squat. Lats engaged, protect armpits. No axial spinal load.'),
('Kettlebell Swing — Two Hand','Full Body',0,0,1,'none','https://www.youtube.com/results?search_query=Kettlebell+Swing+—+Two+Hand+tutorial+form','Hip hinge power. Drive hips forward. Lat activation at top.'),
('Kettlebell Swing — Single Arm','Full Body',0,0,1,'none','https://www.youtube.com/results?search_query=Kettlebell+Swing+—+Single+Arm+tutorial+form','Same hip drive. Serratus stabilises shoulder at top of swing.'),
('Sled Push','Full Body',0,0,1,'none','https://www.youtube.com/results?search_query=sled+push+form+legs+tutorial','Low handles, body at 45°. Drive from legs. No cervical spine load.'),
('Farmer Carry — Single Arm','Lats',0,0,1,'none','https://www.youtube.com/results?search_query=Farmer+Carry+—+Single+Arm+tutorial+form','Lat + serratus stabilise shoulder girdle under load. Walk tall.'),
('Overhead Plate Carry','Triceps',0,0,1,'none','https://www.youtube.com/results?search_query=Overhead+Plate+Carry+tutorial+form','Plate overhead. Serratus + tricep under sustained load. Walk tall.'),
-- Push / Chest / Serratus
('Serratus Wall Slide','Serratus Anterior',0,0,0,'none','https://www.youtube.com/results?search_query=serratus+wall+slide+activation+tutorial','Slow controlled protraction. Feel serratus fire on the push out.'),
('Pec Deck / Machine Fly','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=pec+deck+machine+fly+tutorial','Both sides then single arm. Machine is safer on cervical than cables.'),
('Chest Press Machine — Seated','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=Chest+Press+Machine+—+Seated+tutorial+form','Bilateral then unilateral. Seat adjusts so no cervical strain.'),
('Single-Arm DB Floor Press','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=single+arm+dumbbell+floor+press+tutorial','Floor limits ROM safely. Drive shoulder into floor. Pure neural reconnection.'),
('Push-Up with Scapular Protraction Hold','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=push+up+scapular+protraction+serratus+tutorial','At top: reach forward actively. Serratus key here.'),
('Landmine Press','Chest',0,0,1,'none','https://www.youtube.com/results?search_query=Landmine+Press+tutorial+form','Arcing path recruits pec + serratus. No cervical load.'),
('Cable Crossover — Low to High','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=Cable+Crossover+—+Low+to+High+tutorial+form','Pec minor + serratus pattern. Light, controlled, squeeze at peak.'),
('Cable Push-Out (Pallof)','Serratus Anterior',0,1,0,'none','https://www.youtube.com/results?search_query=Cable+Push-Out+(Pallof)+tutorial+form','Anti-rotation + serratus. Hold 1-2 sec at extension.'),
('Serratus Punch — Standing Cable','Serratus Anterior',0,0,0,'none','https://www.youtube.com/results?search_query=serratus+punch+cable+standing+tutorial','Arm at chest, punch forward with full protraction.'),
-- Pull / Lats
('Straight-Arm Cable Pulldown','Lats',0,0,0,'none','https://www.youtube.com/results?search_query=Straight-Arm+Cable+Pulldown+tutorial+form','Cue: pull elbow to hip pocket. Sensory feedback work.'),
('Cable Lat Pulldown — Single Arm','Lats',0,0,0,'none','https://www.youtube.com/results?search_query=single+arm+cable+lat+pulldown+tutorial','Drive elbow down and back. Initiate with lat not bicep.'),
('Seated Cable Row — Single Arm','Lats',0,0,0,'none','https://www.youtube.com/results?search_query=single+arm+seated+cable+row+tutorial','Brace core. Squeeze at top — 1 sec pause. Control eccentric.'),
('Assisted Pull-Up / Ring Row','Lats',0,0,0,'none','https://www.youtube.com/results?search_query=assisted+pull+up+ring+row+tutorial','Full dead hang at bottom. Key neural input for left lat.'),
('TRX Row with Protraction','Serratus Anterior',0,0,0,'none','https://www.youtube.com/results?search_query=TRX+Row+with+Protraction+tutorial+form','Horizontal pull. Rear delt + serratus integrated.'),
('Single-Arm Lat Pullover','Lats',0,0,1,'none','https://www.youtube.com/results?search_query=single+arm+lat+pullover+cable+tutorial','Full arc from overhead to hip. Lat + pec + tricep integrated.'),
('Passive Dead Hang','Lats',1,0,0,'none','https://www.youtube.com/results?search_query=dead+hang+bar+spinal+decompression+tutorial','Decompresses cervical + thoracic spine. Left lat + serratus lengthened.'),
-- Triceps
('Overhead Cable Extension','Triceps',0,0,0,'none','https://www.youtube.com/results?search_query=Overhead+Cable+Extension+tutorial+form','Long head activation. Full stretch at top. Very light neural signal work.'),
('Single-Arm Cable Tricep Pushdown','Triceps',0,0,0,'none','https://www.youtube.com/results?search_query=Single-Arm+Cable+Tricep+Pushdown+tutorial+form','Elbow fixed at your side. Contract hard at the bottom.'),
('Tricep Dip Machine','Triceps',0,0,0,'none','https://www.youtube.com/results?search_query=tricep+dip+machine+tutorial','Machine version — no cervical loading. Full ROM.'),
('Single-Arm Overhead DB Press','Triceps',0,0,0,'none','https://www.youtube.com/results?search_query=single+arm+overhead+dumbbell+press+tutorial','Tricep + serratus co-activate at lockout. Control descent.'),
('Half-Kneeling Single-Arm Press','Chest',0,0,0,'none','https://www.youtube.com/results?search_query=half+kneeling+single+arm+press+tutorial','Half kneeling demands hip stability. Double training stimulus.'),
-- Recovery
('Box Breathing 4-4-4-4','Recovery',0,0,0,'none','https://www.youtube.com/results?search_query=box+breathing+4+4+4+4+technique+tutorial','5 minutes. Down-regulates nervous system post-session. Non-negotiable.');

-- ════════════════════════════════════════════════════════════════════════════
-- PHASE 1 PLAN — Weeks 1–8: Reconnection & Baseline
-- ════════════════════════════════════════════════════════════════════════════

INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active)
VALUES (
  'Phase 1 — Reconnection',
  'Weeks 1–8. Focus: neural pathway re-establishment on left side, machine-based lower body (cervical safe), hip mobility daily, core 2× daily. Left loads 30–40% lighter than right. Priority: feel the contraction, not the weight.',
  1, 8,
  CURDATE(),
  DATE_ADD(CURDATE(), INTERVAL 56 DAY),
  1
);

SET @p1 = LAST_INSERT_ID();

-- Plan Days
INSERT INTO plan_days (plan_id, day_label, day_title, day_order, week_day, cardio_type, cardio_description) VALUES
(@p1,'Day 1','Lower Body',1,'Tue','steady_state','Rowing Machine 10 min — Zone 2 warm-up'),
(@p1,'Day 2','Push',2,'Wed','hiit','Ski Erg HIIT — 20s hard / 40s easy × 6 rounds'),
(@p1,'Day 3','Pull',3,'Fri','steady_state','Rowing Machine 10 min — Zone 2, lat primer'),
(@p1,'Day 4','Arms & Functional',4,'Sat','hiit','Ski Erg HIIT — 20s hard / 40s easy × 8 rounds'),
(@p1,'Day 5','Full Body + Mobility',5,'Mon','steady_state','Stationary Bike 15 min — Zone 2, easy recovery');

-- Helper: get exercise IDs by name
-- DAY 1 — LOWER BODY
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 1',id,'Cardio Warm-Up',1,1,1,'10 min',0,0,0,0,'Zone 2 — 22–24 spm' FROM exercises WHERE name='Rowing Machine — Steady State';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Hip Mobility',2,1,3,'60 sec',0,0,0,0 FROM exercises WHERE name='90/90 Hip Switch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Hip Mobility',2,2,3,'8 each',0,0,0,0 FROM exercises WHERE name='Hip CARs';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Hip Mobility',2,3,3,'8 each',0,0,0,0 FROM exercises WHERE name='World Greatest Stretch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Core Block A',3,1,3,'10 each',0,0,0,0 FROM exercises WHERE name='Dead Bug';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Core Block A',3,2,3,'8',0,0,0,0 FROM exercises WHERE name='McGill Curl-Up';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Core Block A',3,3,3,'10 each',0,0,0,0 FROM exercises WHERE name='Bird Dog';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,1,3,'12-15',0,1,0,0 FROM exercises WHERE name='Leg Press (Machine)';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,2,3,'12',0,1,0,0 FROM exercises WHERE name='Leg Curl — Lying (Machine)';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,3,3,'12',0,1,0,0 FROM exercises WHERE name='Leg Extension (Machine)';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,4,3,'15',0,1,0,0 FROM exercises WHERE name='Hip Abductor Machine';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,5,3,'15',0,1,0,0 FROM exercises WHERE name='Hip Adductor Machine';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Main Work — Machines',4,6,3,'12',0,1,0,0 FROM exercises WHERE name='Glute Bridge — Machine or Band';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Functional',5,1,4,'15',0,0,0,0 FROM exercises WHERE name='Kettlebell Deadlift';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Functional',5,2,3,'30m',1,1,1,0 FROM exercises WHERE name='Farmer Carry — Single Arm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Core Block B',6,1,3,'12 each',1,1,1,0 FROM exercises WHERE name='Pallof Press — Tall Kneeling';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Core Block B',6,2,3,'45 sec',0,0,0,0 FROM exercises WHERE name='Plank — Forearm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Cool-Down',7,1,2,'90 sec',0,0,0,0 FROM exercises WHERE name='Couch Stretch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 1',id,'Cool-Down',7,2,2,'90 sec',0,0,0,0 FROM exercises WHERE name='Pigeon Pose';

-- DAY 2 — PUSH
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 2',id,'Cardio Warm-Up',1,1,1,'6 rounds',0,0,0,0,'20s hard / 40s easy' FROM exercises WHERE name='Ski Erg — Intervals';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Hip Mobility',2,1,3,'60 sec',0,0,0,0 FROM exercises WHERE name='90/90 Hip Switch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Core Block A',3,1,3,'10 each',0,0,0,0 FROM exercises WHERE name='Dead Bug';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Core Block A',3,2,3,'20 sec each',0,1,0,0 FROM exercises WHERE name='Copenhagen Plank';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Activation',4,1,3,'12',1,1,1,2 FROM exercises WHERE name='Serratus Wall Slide';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Activation',4,2,3,'15',1,1,1,2 FROM exercises WHERE name='Pec Deck / Machine Fly';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Main Work',5,1,3,'10-12',1,1,1,0 FROM exercises WHERE name='Chest Press Machine — Seated';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Main Work',5,2,4,'10',1,0,2,0 FROM exercises WHERE name='Single-Arm DB Floor Press';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Main Work',5,3,3,'10',1,1,1,0 FROM exercises WHERE name='Landmine Press';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Main Work',5,4,3,'8+5s hold',0,0,0,0 FROM exercises WHERE name='Push-Up with Scapular Protraction Hold';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Finisher',6,1,3,'15',1,1,1,2 FROM exercises WHERE name='Cable Crossover — Low to High';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Core Block B',7,1,3,'8',0,0,0,0 FROM exercises WHERE name='Ab Wheel Rollout';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 2',id,'Core Block B',7,2,3,'30 sec each',0,1,0,0 FROM exercises WHERE name='Side Plank';

-- DAY 3 — PULL
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 3',id,'Cardio Warm-Up',1,1,1,'10 min',0,0,0,0,'22–24 spm' FROM exercises WHERE name='Rowing Machine — Steady State';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Hip Mobility',2,1,2,'90 sec each',1,1,0,0 FROM exercises WHERE name='Lat Prayer Stretch on Foam Roller';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Hip Mobility',2,2,2,'10 each',0,0,0,0 FROM exercises WHERE name='Lateral Lunge + Adductor Stretch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Core Block A',3,1,3,'10 each',0,0,0,0 FROM exercises WHERE name='Bird Dog';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Core Block A',3,2,3,'10 each',0,0,0,0 FROM exercises WHERE name='Dead Bug';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Activation',4,1,3,'15',1,1,1,2 FROM exercises WHERE name='Straight-Arm Cable Pulldown';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Main Work',5,1,4,'12',1,1,1,2 FROM exercises WHERE name='Cable Lat Pulldown — Single Arm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Main Work',5,2,4,'10',1,1,1,0 FROM exercises WHERE name='Seated Cable Row — Single Arm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Main Work',5,3,3,'8',1,0,0,0 FROM exercises WHERE name='Assisted Pull-Up / Ring Row';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Main Work',5,4,3,'12',0,0,0,0 FROM exercises WHERE name='TRX Row with Protraction';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Functional',6,1,4,'15',0,0,0,0 FROM exercises WHERE name='Kettlebell Swing — Two Hand';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Functional',6,2,3,'12 each',1,1,1,0 FROM exercises WHERE name='Kettlebell Swing — Single Arm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Cool-Down',7,1,3,'30 sec',1,0,0,0 FROM exercises WHERE name='Passive Dead Hang';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Core Block B',8,1,3,'12 each',0,0,0,0 FROM exercises WHERE name='Cable Crunch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 3',id,'Core Block B',8,2,3,'30m each',1,1,1,0 FROM exercises WHERE name='Suitcase Carry';

-- DAY 4 — ARMS & FUNCTIONAL
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 4',id,'Cardio Warm-Up',1,1,1,'8 rounds',0,0,0,0,'20s hard / 40s easy' FROM exercises WHERE name='Ski Erg — Intervals';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Hip Mobility',2,1,2,'90 sec',0,0,0,0 FROM exercises WHERE name='Supine Psoas Release';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Hip Mobility',2,2,2,'60 sec',0,0,0,0 FROM exercises WHERE name='Frog Stretch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Core Block A',3,1,3,'8',0,0,0,0 FROM exercises WHERE name='McGill Curl-Up';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Core Block A',3,2,3,'20 sec each',0,1,0,0 FROM exercises WHERE name='Side Plank + Hip Dip';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Activation',4,1,3,'15',1,1,1,2 FROM exercises WHERE name='Overhead Cable Extension';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Activation',4,2,3,'15',1,1,1,0 FROM exercises WHERE name='Serratus Punch — Standing Cable';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Main Work',5,1,3,'10',1,1,1,0 FROM exercises WHERE name='Single-Arm Overhead DB Press';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Main Work',5,2,3,'12',1,1,1,2 FROM exercises WHERE name='Single-Arm Cable Tricep Pushdown';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Main Work',5,3,3,'10',0,0,0,0 FROM exercises WHERE name='Tricep Dip Machine';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Functional',6,1,4,'15',0,0,0,0 FROM exercises WHERE name='Kettlebell Swing — Two Hand';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Functional',6,2,1,'6 rounds',0,0,0,0 FROM exercises WHERE name='Battle Ropes — Alternating Waves';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Functional',6,3,3,'30m each',1,1,1,0 FROM exercises WHERE name='Farmer Carry — Single Arm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Functional',6,4,3,'20m',0,0,0,0 FROM exercises WHERE name='Sled Push';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Core Block B',7,1,3,'45 sec',0,0,0,0 FROM exercises WHERE name='Plank — Forearm';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 4',id,'Core Block B',7,2,3,'12 each',1,1,1,0 FROM exercises WHERE name='Pallof Press — Tall Kneeling';

-- DAY 5 — FULL BODY + MOBILITY
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 5',id,'Cardio Warm-Up',1,1,1,'15 min',0,0,0,0,'Zone 2 — easy pace' FROM exercises WHERE name='Stationary Bike — Steady State';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Deep Mobility Reset',2,1,1,'10 min',0,0,0,0 FROM exercises WHERE name='90/90 Hip Switch';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Deep Mobility Reset',2,2,3,'10 each',0,0,0,0 FROM exercises WHERE name='Deep Squat + Thoracic Rotation';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Deep Mobility Reset',2,3,3,'10 each',0,0,0,0 FROM exercises WHERE name='Cossack Squat';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Core Block A',3,1,3,'10 each',0,0,0,0 FROM exercises WHERE name='Dead Bug';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Core Block A',3,2,3,'10 each',0,0,0,0 FROM exercises WHERE name='Bird Dog';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Integrated Strength',4,1,3,'12 each',1,1,1,0 FROM exercises WHERE name='Pallof Press — Tall Kneeling';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Integrated Strength',4,2,3,'15',1,1,1,2 FROM exercises WHERE name='Cable Crossover — Low to High';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Integrated Strength',4,3,3,'12',1,1,1,0 FROM exercises WHERE name='Single-Arm Lat Pullover';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Integrated Strength',4,4,4,'15',0,0,0,0 FROM exercises WHERE name='Kettlebell Deadlift';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus,notes)
SELECT @p1,'Day 5',id,'Integrated Strength',4,5,1,'5×250m',0,0,0,0,'250m hard / 90 sec rest' FROM exercises WHERE name='Rowing Machine — Power Intervals';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Cool-Down',5,1,3,'30 sec',1,0,0,0 FROM exercises WHERE name='Passive Dead Hang';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Reset',6,1,1,'5 min',0,0,0,0 FROM exercises WHERE name='Box Breathing 4-4-4-4';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Core Block B',7,1,3,'8',0,0,0,0 FROM exercises WHERE name='Ab Wheel Rollout';
INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,is_left_priority,both_sides,sets_left,reps_left_bonus)
SELECT @p1,'Day 5',id,'Core Block B',7,2,3,'20 sec each',0,1,0,0 FROM exercises WHERE name='Copenhagen Plank';

-- Sample weight data
INSERT INTO weight_log (logged_date, weight_kg) VALUES
(DATE_SUB(CURDATE(), INTERVAL 28 DAY), 82.0),
(DATE_SUB(CURDATE(), INTERVAL 21 DAY), 81.6),
(DATE_SUB(CURDATE(), INTERVAL 14 DAY), 81.2),
(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 80.8),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 80.5);
