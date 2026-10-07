<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/scholargate_sync/enrol_rombel.php', ['id' => $course->id]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title('Enrol Class Cohort Students - ' . $course->fullname);
$PAGE->set_heading($course->fullname);

// Fetch existing class cohorts in Moodle
$cohorts = $DB->get_records_select('cohort', "idnumber LIKE 'kelas_%'", null, 'name ASC');

// Count members in cohorts
$membercounts = $DB->get_records_sql("
    SELECT cohortid, COUNT(id) as total
    FROM {cohort_members}
    GROUP BY cohortid
");

// Check cohorts already enrolled in this course
$enrolledinstances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']);
$enrolledcohortids = [];
foreach ($enrolledinstances as $inst) {
    if (!empty($inst->customint1)) {
        $enrolledcohortids[(int)$inst->customint1] = $inst;
    }
}

// Handle Form Submission
if ($data = data_submitted() && confirm_sesskey()) {
    $selectedcohorts = optional_param_array('cohort_ids', [], PARAM_INT);
    $creategroups = optional_param('create_groups', 1, PARAM_BOOL);

    if (!empty($selectedcohorts)) {
        $result = \local_scholargate_sync\course_hook_callbacks::enrol_cohorts_to_course(
            $course->id,
            $selectedcohorts,
            (bool)$creategroups
        );

        $msg = "Successfully enrolled {$result['enrolled']} Class Cohorts into the course ({$result['groups_created']} new course groups created).";
        \core\notification::success($msg);
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    } else {
        \core\notification::warning('Please check at least one class cohort to enrol.');
    }
}

echo $OUTPUT->header();
?>

<div class="scholargate-enrol-rombel-container pb-5">
    <!-- Header Card (Custom container avoiding .card background override) -->
    <div class="sg-hero-banner shadow-sm mb-4" style="border-radius: 14px; background: linear-gradient(135deg, #090d16 0%, #0f172a 60%, #1e293b 100%) !important; color: #fff !important; padding: 24px 28px; border: 1px solid #334155 !important;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <span class="badge badge-pill mb-2 px-3 py-1 font-weight-bold" style="background: rgba(59,130,246,0.3); border: 1px solid rgba(59,130,246,0.5); color: #93c5fd; font-size: 0.75rem; text-transform: uppercase;">
                    <i class="fa fa-users mr-1"></i> Quick Class Enrolment
                </span>
                <h3 class="font-weight-bold mb-1" style="color: #ffffff !important;">
                    Enrol Class Cohort Students
                </h3>
                <p class="mb-0" style="color: #cbd5e1 !important;">
                    Course: <strong class="text-white"><?php echo s($course->fullname); ?></strong> (<?php echo s($course->shortname); ?>)
                </p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?php echo new moodle_url('/course/view.php', ['id' => $course->id]); ?>" class="btn btn-outline-light btn-sm px-3">
                    <i class="fa fa-arrow-left mr-1"></i> Back to Course
                </a>
            </div>
        </div>
    </div>

    <form method="post" action="<?php echo $PAGE->url; ?>">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Control Bar -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                    <span class="font-weight-bold text-dark mr-3"><i class="fa fa-filter text-muted mr-1"></i> Quick Filter:</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-1" onclick="filterGrade('all')">All Grades</button>
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2 mb-1" onclick="filterGrade('X')">Grade X</button>
                    <button type="button" class="btn btn-sm btn-outline-info mr-2 mb-1" onclick="filterGrade('XI')">Grade XI</button>
                    <button type="button" class="btn btn-sm btn-outline-warning mr-2 mb-1" onclick="filterGrade('XII')">Grade XII</button>
                    
                    <span class="border-left mx-2 d-none d-md-inline" style="height: 24px;"></span>
                    
                    <button type="button" class="btn btn-sm btn-light border mr-2 mb-1" onclick="selectAllVisible(true)">
                        <i class="fa fa-check-square-o mr-1"></i> Select All
                    </button>
                    <button type="button" class="btn btn-sm btn-light border mb-1" onclick="selectAllVisible(false)">
                        <i class="fa fa-square-o mr-1"></i> Deselect All
                    </button>
                </div>

                <div class="d-flex align-items-center">
                    <div class="custom-control custom-switch mr-3">
                        <input type="checkbox" class="custom-control-input" id="create_groups_switch" name="create_groups" value="1" checked>
                        <label class="custom-control-label font-weight-medium small" for="create_groups_switch">
                            Automatically Create Course Groups
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary font-weight-bold px-4" style="border-radius: 8px;">
                        <i class="fa fa-user-plus mr-1"></i> Enrol Selected Cohorts
                    </button>
                </div>
            </div>
        </div>

        <!-- Rombel Grid -->
        <div class="row" id="rombel-grid">
            <?php
            foreach ($cohorts as $c):
                $cid = (int)$c->id;
                $cname = $c->name;
                $isenrolled = isset($enrolledcohortids[$cid]);
                $studentcount = isset($membercounts[$cid]) ? (int)$membercounts[$cid]->total : 0;

                $grade = 'OTHER';
                if (preg_match('/\bXII\b/i', $cname)) {
                    $grade = 'XII';
                } elseif (preg_match('/\bXI\b/i', $cname)) {
                    $grade = 'XI';
                } elseif (preg_match('/\bX\b/i', $cname)) {
                    $grade = 'X';
                }
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-3 rombel-card-col" data-grade="<?php echo s($grade); ?>">
                <div class="card h-100 border-0 shadow-sm rombel-card" style="border-radius: 12px; transition: transform 0.15s, box-shadow 0.15s; <?php echo $isenrolled ? 'background: #f0fdf4 !important; border: 1.5px solid #86efac !important;' : 'background: #ffffff !important; border: 1px solid #e2e8f0 !important;'; ?>">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge <?php echo $isenrolled ? 'badge-success' : 'badge-light border'; ?> px-2 py-1 font-weight-bold" style="font-size: 0.75rem;">
                                    <?php if ($isenrolled): ?>
                                        <i class="fa fa-check-circle mr-1"></i> Enrolled
                                    <?php else: ?>
                                        <i class="fa fa-circle-o mr-1 text-muted"></i> Not Enrolled
                                    <?php endif; ?>
                                </span>
                                <span class="small font-weight-bold text-muted">
                                    <i class="fa fa-users text-primary mr-1"></i> <?php echo $studentcount; ?> Students
                                </span>
                            </div>
                            <h6 class="font-weight-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?php echo s($cname); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Cohort: <?php echo s($c->idnumber); ?></small>
                        </div>

                        <div class="mt-3 pt-2 border-top">
                            <?php if ($isenrolled): ?>
                                <div class="text-success small font-weight-medium">
                                    <i class="fa fa-link mr-1"></i> Connected via Cohort Sync
                                </div>
                            <?php else: ?>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input rombel-checkbox" id="cohort_chk_<?php echo $cid; ?>" name="cohort_ids[]" value="<?php echo $cid; ?>">
                                    <label class="custom-control-label font-weight-medium small text-dark cursor-pointer" for="cohort_chk_<?php echo $cid; ?>">
                                        Select to Enrol
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<script>
function filterGrade(grade) {
    const cols = document.querySelectorAll('.rombel-card-col');
    cols.forEach(col => {
        if (grade === 'all' || col.getAttribute('data-grade') === grade) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

function selectAllVisible(check) {
    const cols = document.querySelectorAll('.rombel-card-col');
    cols.forEach(col => {
        if (col.style.display !== 'none') {
            const chk = col.querySelector('.rombel-checkbox');
            if (chk) chk.checked = check;
        }
    });
}
</script>

<?php
echo $OUTPUT->footer();
