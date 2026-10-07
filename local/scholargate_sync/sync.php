<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/scholargate_sync/sync.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('ScholarGate Identity Synchronization Center');
$PAGE->set_heading('ScholarGate Identity Synchronization Center');

$action = optional_param('action', '', PARAM_ALPHAEXT);

// =========================================================================
// HANDLER AJAX ENDPOINTS (NON-BLOCKING STREAM WORKER)
// =========================================================================
if (strpos($action, 'ajax_') === 0) {
    header('Content-Type: application/json; charset=utf-8');
    if (!confirm_sesskey()) {
        echo json_encode(['ok' => false, 'error' => 'Invalid or expired sesskey.']);
        exit;
    }

    try {
        if ($action === 'ajax_sync_tp') {
            $res = \local_scholargate_sync\bulk_sync_service::sync_academic_year();
            echo json_encode($res);
            exit;
        }

        if ($action === 'ajax_sync_gtk') {
            $role = optional_param('role', 'guru', PARAM_ALPHA);
            $page = optional_param('page', 1, PARAM_INT);
            $perpage = optional_param('perpage', 50, PARAM_INT);
            $res = \local_scholargate_sync\bulk_sync_service::sync_gtk_page($role, $page, $perpage);
            echo json_encode($res);
            exit;
        }

        if ($action === 'ajax_sync_siswa') {
            $page = optional_param('page', 1, PARAM_INT);
            $perpage = optional_param('perpage', 50, PARAM_INT);
            $res = \local_scholargate_sync\bulk_sync_service::sync_siswa_page($page, $perpage);
            echo json_encode($res);
            exit;
        }

        if ($action === 'ajax_sync_mutasi_alumni') {
            $res = \local_scholargate_sync\bulk_sync_service::sync_mutasi_and_alumni();
            echo json_encode($res);
            exit;
        }

        if ($action === 'ajax_get_stats') {
            global $DB;
            $countguru = $DB->count_records_sql("
                SELECT COUNT(DISTINCT u.id)
                FROM {user} u
                JOIN {user_info_data} d ON d.userid = u.id
                JOIN {user_info_field} f ON f.id = d.fieldid
                WHERE f.shortname = 'role_sekolah' AND d.data IN ('guru', 'tendik') AND u.deleted = 0
            ");
            $countsiswa = $DB->count_records_sql("
                SELECT COUNT(DISTINCT u.id)
                FROM {user} u
                JOIN {user_info_data} d ON d.userid = u.id
                JOIN {user_info_field} f ON f.id = d.fieldid
                WHERE f.shortname = 'role_sekolah' AND d.data = 'siswa' AND u.deleted = 0
            ");
            $countmutasi = $DB->count_records_sql("
                SELECT COUNT(DISTINCT u.id)
                FROM {user} u
                JOIN {user_info_data} d ON d.userid = u.id
                JOIN {user_info_field} f ON f.id = d.fieldid
                WHERE f.shortname = 'role_sekolah' AND d.data = 'keluar' AND u.deleted = 0
            ");
            $countalumni = $DB->count_records_sql("
                SELECT COUNT(DISTINCT u.id)
                FROM {user} u
                JOIN {user_info_data} d ON d.userid = u.id
                JOIN {user_info_field} f ON f.id = d.fieldid
                WHERE f.shortname = 'role_sekolah' AND d.data = 'alumni' AND u.deleted = 0
            ");
            $countcohort = $DB->count_records_select('cohort', "idnumber LIKE 'kelas_%'");
            $activeay = get_config('local_scholargate_sync', 'active_academic_year_name') ?: 'Not synchronized';
            $lastsync = get_config('local_scholargate_sync', 'last_bulk_sync_time');

            echo json_encode([
                'ok' => true,
                'count_guru' => (int)$countguru,
                'count_siswa' => (int)$countsiswa,
                'count_mutasi' => (int)$countmutasi,
                'count_alumni' => (int)$countalumni,
                'count_cohort' => (int)$countcohort,
                'active_ay' => $activeay,
                'last_sync' => $lastsync ? userdate($lastsync) : 'Just now'
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Initial stats
global $DB;
$countguru = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {user_info_data} d ON d.userid = u.id
    JOIN {user_info_field} f ON f.id = d.fieldid
    WHERE f.shortname = 'role_sekolah' AND d.data IN ('guru', 'tendik') AND u.deleted = 0
");

$countsiswa = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {user_info_data} d ON d.userid = u.id
    JOIN {user_info_field} f ON f.id = d.fieldid
    WHERE f.shortname = 'role_sekolah' AND d.data = 'siswa' AND u.deleted = 0
");

$countcohort = $DB->count_records_select('cohort', "idnumber LIKE 'kelas_%'");
$activeay = get_config('local_scholargate_sync', 'active_academic_year_name') ?: 'Not synchronized';
$lastsync = get_config('local_scholargate_sync', 'last_bulk_sync_time');
$lastsyncstr = $lastsync ? userdate($lastsync) : 'Never';

echo $OUTPUT->header();
?>

<div class="scholargate-sync-dashboard pb-5">
    <!-- Premium High-Contrast Hero Banner (Not using .card to prevent theme override) -->
    <div class="sg-hero-banner shadow-sm mb-4" style="background: linear-gradient(135deg, #090d16 0%, #0f172a 60%, #1e293b 100%) !important; color: #ffffff !important; border-radius: 16px; border: 1px solid #334155 !important; padding: 26px 30px; position: relative; overflow: hidden;">
        <div style="position: absolute; right: -30px; top: -30px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(16,185,129,0.2) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
        <div style="position: absolute; left: 35%; bottom: -50px; width: 240px; height: 240px; background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

        <div class="d-flex align-items-center justify-content-between flex-wrap position-relative" style="z-index: 1;">
            <div>
                <div class="d-flex align-items-center mb-2">
                    <span class="badge mr-2 px-3 py-1 font-weight-bold" style="background: rgba(59,130,246,0.25); border: 1px solid rgba(59,130,246,0.5); color: #93c5fd; font-size: 0.75rem; letter-spacing: 0.5px; text-transform: uppercase;">
                        <i class="fa fa-database mr-1"></i> Master Data Integration
                    </span>
                    <span class="badge mr-2 px-3 py-1 font-weight-bold" style="background: rgba(16,185,129,0.25); border: 1px solid rgba(16,185,129,0.5); color: #6ee7b7; font-size: 0.75rem; letter-spacing: 0.5px; text-transform: uppercase;">
                        <i class="fa fa-bolt mr-1"></i> Non-Blocking Live Stream
                    </span>
                </div>
                <h3 class="font-weight-bold mb-1" style="color: #ffffff !important; letter-spacing: -0.5px; font-size: 1.55rem;">
                    ScholarGate Identity Synchronization Center
                </h3>
                <p class="mb-0" style="color: #cbd5e1 !important; max-width: 740px; font-size: 0.95rem; line-height: 1.55;">
                    Unified master data synchronization for Academic Year, Teachers, Staff, Active Students, Departures/Alumni, and Class Cohorts streamed directly from ScholarGate Identity.
                </p>
            </div>
            <div class="mt-3 mt-md-0 text-md-right">
                <div class="p-2 px-3 rounded-lg" style="background: rgba(15,23,42,0.85); border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(10px);">
                    <div class="small text-uppercase font-weight-bold" style="color: #94a3b8; font-size: 0.72rem; letter-spacing: 0.5px;">Active Academic Year</div>
                    <div class="font-weight-bold" style="color: #ffffff; font-size: 1.15rem;" id="stat-active-ay">
                        <i class="fa fa-calendar-check-o text-success mr-1"></i> <?php echo s($activeay); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Grid Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #10b981 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">Teachers & Staff</div>
                    <div class="h3 font-weight-bold text-dark mt-2 mb-0" id="card-count-guru"><?php echo number_format($countguru); ?></div>
                    <small class="text-success font-weight-medium"><i class="fa fa-check-circle mr-1"></i>Course Creator Assigned</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #3b82f6 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">Active Students</div>
                    <div class="h3 font-weight-bold text-dark mt-2 mb-0" id="card-count-siswa"><?php echo number_format($countsiswa); ?></div>
                    <small class="text-primary font-weight-medium"><i class="fa fa-users mr-1"></i>Filtered for <?php echo s($activeay); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">Class Cohorts</div>
                    <div class="h3 font-weight-bold text-dark mt-2 mb-0" id="card-count-cohort"><?php echo number_format($countcohort); ?></div>
                    <small class="text-muted font-weight-medium"><i class="fa fa-sitemap mr-1"></i>Automated Class Mapping</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">Last Synchronization</div>
                    <div class="font-weight-bold text-dark mt-2 mb-0" style="font-size: 1.1rem;" id="stat-last-sync"><?php echo s($lastsyncstr); ?></div>
                    <small class="text-muted"><i class="fa fa-clock-o mr-1"></i>Nightly Cron: 02:00 AM</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Cards Section -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fa fa-bolt text-warning mr-2"></i> On-Demand Data Synchronization
                </h5>
                <small class="text-muted">Executed in progressive background streams with live status indicators (non-freezing).</small>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fetchLiveStats(true)">
                    <i class="fa fa-refresh mr-1"></i> Refresh Stats
                </button>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <!-- Action 1: Sync TP -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="p-3 border rounded-lg h-100 d-flex flex-column justify-content-between" style="background: #fafafa; border-radius: 12px;">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-primary px-2 py-1 mr-2"><i class="fa fa-calendar"></i> Step 1</span>
                                <h6 class="font-weight-bold text-dark mb-0">Academic Year</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Fetch the current active Academic Year from ScholarGate portal and synchronize Moodle configuration.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-block font-weight-medium" onclick="runSyncTP()">
                            <i class="fa fa-refresh mr-1"></i> Sync Active Academic Year
                        </button>
                    </div>
                </div>

                <!-- Action 2: Sync GTK -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="p-3 border rounded-lg h-100 d-flex flex-column justify-content-between" style="background: #fafafa; border-radius: 12px;">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-success px-2 py-1 mr-2"><i class="fa fa-graduation-cap"></i> Step 2</span>
                                <h6 class="font-weight-bold text-dark mb-0">Teachers & Staff</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Synchronize PTK records from ScholarGate, populate NIP/NIK/PTK ID, and grant Course Creator role automatically.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-block font-weight-medium" onclick="runSyncGTK()">
                            <i class="fa fa-users mr-1"></i> Sync Teachers & Staff
                        </button>
                    </div>
                </div>

                <!-- Action 3: Sync Siswa -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="p-3 border rounded-lg h-100 d-flex flex-column justify-content-between" style="background: #fafafa; border-radius: 12px;">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-info px-2 py-1 mr-2"><i class="fa fa-address-book"></i> Step 3</span>
                                <h6 class="font-weight-bold text-dark mb-0">Students & Class Cohorts</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Stream active students for current year (~1,276 students), create class cohorts, and map each student to their class.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-info btn-block font-weight-medium" onclick="runSyncSiswa()">
                            <i class="fa fa-user-plus mr-1"></i> Sync Students & Cohorts
                        </button>
                    </div>
                </div>

                <!-- Action 4: Sync Mutasi & Alumni -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="p-3 border rounded-lg h-100 d-flex flex-column justify-content-between" style="background: #fafafa; border-radius: 12px;">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-danger px-2 py-1 mr-2"><i class="fa fa-user-times"></i> Step 4</span>
                                <h6 class="font-weight-bold text-dark mb-0">Departures & Alumni</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Handle departed/mutated students (auto-suspend accounts & remove from cohorts) and assign graduates to the Alumni cohort.
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-block font-weight-medium" onclick="runSyncMutasiAlumni()">
                            <i class="fa fa-shield mr-1"></i> Sync Departures & Alumni
                        </button>
                    </div>
                </div>

                <!-- Action 5: Sync All -->
                <div class="col-lg-8 col-md-12 mb-4">
                    <div class="p-3 border rounded-lg h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #fdfbf7 0%, #fff7ed 100%); border-color: #fed7aa !important; border-radius: 12px;">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge badge-warning px-2 py-1 mr-2 text-dark font-weight-bold"><i class="fa fa-bolt"></i> Full Orchestration</span>
                                <h6 class="font-weight-bold text-dark mb-0">Full Master Synchronization (All-In-One)</h6>
                            </div>
                            <p class="text-muted small mb-3">
                                Run the full end-to-end synchronization pipeline sequentially: Academic Year &rarr; Teachers & Staff &rarr; Students & Cohorts &rarr; Departures & Alumni.
                            </p>
                        </div>
                        <button type="button" class="btn btn-warning btn-block font-weight-bold text-dark" onclick="runSyncAll()" style="box-shadow: 0 4px 12px rgba(245,158,11,0.25);">
                            <i class="fa fa-play-circle mr-1"></i> Execute Full Synchronization
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODERN PROGRESS BAR MODAL (NON-BLOCKING BACKGROUND WORKER) -->
<!-- ========================================================================= -->
<div id="sg-sync-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div id="sg-sync-modal-card" style="background: #ffffff; border-radius: 20px; width: 100%; max-width: 680px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45); overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1);">
        
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #090d16 0%, #1e293b 100%); padding: 24px 28px; color: #fff; position: relative;">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div id="sg-modal-icon-container" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; margin-right: 14px;">
                        <i id="sg-modal-spinner" class="fa fa-refresh fa-spin text-info" style="font-size: 1.3rem;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 text-white font-weight-bold" id="sg-modal-title">Master Data Synchronization</h5>
                        <small class="text-white-50" id="sg-modal-subtitle">Processing in background stream...</small>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-sm" onclick="minimizeSyncModal()" title="Minimize to corner" style="background: rgba(255,255,255,0.1); color: #fff; border-radius: 8px;">
                        <i class="fa fa-window-minimize"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div style="padding: 28px;">
            <!-- Main Progress Bar -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="font-weight-bold text-dark" id="sg-progress-phase-label">Initializing connection...</span>
                    <span class="font-weight-bold text-primary" style="font-size: 1.15rem;" id="sg-progress-percentage">0%</span>
                </div>
                <div style="height: 16px; background: #e2e8f0; border-radius: 999px; overflow: hidden; position: relative;">
                    <div id="sg-progress-bar-fill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981 0%, #3b82f6 50%, #8b5cf6 100%); border-radius: 999px; transition: width 0.3s ease; position: relative; overflow: hidden;">
                        <div style="position: absolute; inset: 0; background-image: linear-gradient(45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: progress-stripes 1s linear infinite;"></div>
                    </div>
                </div>
            </div>

            <!-- Mini Metric Cards -->
            <div class="row mb-3">
                <div class="col-4">
                    <div class="p-2 text-center rounded border" style="background: #f8fafc;">
                        <div class="text-muted small" style="font-size: 0.72rem; text-transform: uppercase;">Batch / Page</div>
                        <div class="font-weight-bold text-dark mt-1" style="font-size: 0.95rem;" id="sg-metric-batch">-</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 text-center rounded border" style="background: #f8fafc;">
                        <div class="text-muted small" style="font-size: 0.72rem; text-transform: uppercase;">Records Processed</div>
                        <div class="font-weight-bold text-dark mt-1" style="font-size: 0.95rem;" id="sg-metric-synced">0</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 text-center rounded border" style="background: #f8fafc;">
                        <div class="text-muted small" style="font-size: 0.72rem; text-transform: uppercase;">Worker Status</div>
                        <div class="font-weight-bold text-info mt-1" style="font-size: 0.85rem;" id="sg-metric-status">Active</div>
                    </div>
                </div>
            </div>

            <!-- Live Details Ticker -->
            <div class="p-2 px-3 rounded mb-3 d-flex align-items-center justify-content-between" style="background: #f1f5f9; font-size: 0.85rem;">
                <div class="text-truncate mr-2">
                    <span class="text-muted">Latest Item:</span> 
                    <strong class="text-dark ml-1" id="sg-ticker-latest">Awaiting server response...</strong>
                </div>
                <span class="badge badge-light border text-muted px-2 py-1" id="sg-ticker-cohort">Cohort: -</span>
            </div>

            <!-- Live Terminal Log Console -->
            <div style="background: #0f172a; border-radius: 10px; padding: 12px; color: #cbd5e1; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; height: 120px; overflow-y: auto;" id="sg-sync-console-logs">
                <div class="text-muted">[System] Ready for stream background processing...</div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="background: #f8fafc; padding: 16px 28px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div class="small text-muted" id="sg-footer-status">
                <i class="fa fa-shield text-success mr-1"></i> Interactive & responsive stream.
            </div>
            <div>
                <button type="button" id="sg-btn-minimize" class="btn btn-outline-secondary btn-sm mr-2" onclick="minimizeSyncModal()">
                    <i class="fa fa-level-down mr-1"></i> Run in Background
                </button>
                <button type="button" id="sg-btn-close-modal" class="btn btn-primary btn-sm px-4" style="display: none;" onclick="closeSyncModal()">
                    <i class="fa fa-check mr-1"></i> Done & Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FLOATING MINIMIZED PILL WIDGET -->
<div id="sg-floating-pill" onclick="maximizeSyncModal()" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #0f172a; color: #fff; padding: 10px 18px; border-radius: 999px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15); cursor: pointer; align-items: center; transition: all 0.2s ease;">
    <i id="sg-pill-spinner" class="fa fa-refresh fa-spin text-info mr-2"></i>
    <span class="mr-2 small font-weight-bold" id="sg-pill-label">Sync Active</span>
    <span class="badge badge-primary px-2 py-1 font-weight-bold" id="sg-pill-percent">0%</span>
</div>

<style>
@keyframes progress-stripes {
    from { background-position: 1rem 0; }
    to { background-position: 0 0; }
}
</style>

<script>
const SESSKEY = "<?php echo sesskey(); ?>";
const AJAX_URL = "<?php echo (new moodle_url('/local/scholargate_sync/sync.php'))->out(false); ?>";

let isSyncRunning = false;

function appendConsoleLog(msg) {
    const consoleEl = document.getElementById('sg-sync-console-logs');
    if (!consoleEl) return;
    const time = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    line.innerHTML = `<span style="color: #64748b;">[${time}]</span> ${msg}`;
    consoleEl.appendChild(line);
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function showSyncModal(title, subtitle) {
    isSyncRunning = true;
    document.getElementById('sg-modal-title').innerText = title;
    document.getElementById('sg-modal-subtitle').innerText = subtitle;
    document.getElementById('sg-progress-bar-fill').style.width = '0%';
    document.getElementById('sg-progress-percentage').innerText = '0%';
    document.getElementById('sg-metric-batch').innerText = '-';
    document.getElementById('sg-metric-synced').innerText = '0';
    document.getElementById('sg-metric-status').innerText = 'Active';
    document.getElementById('sg-metric-status').className = 'font-weight-bold text-info mt-1';
    document.getElementById('sg-ticker-latest').innerText = 'Awaiting server response...';
    document.getElementById('sg-ticker-cohort').innerText = 'Cohort: -';
    document.getElementById('sg-btn-close-modal').style.display = 'none';
    document.getElementById('sg-btn-minimize').style.display = 'inline-block';
    document.getElementById('sg-modal-spinner').className = 'fa fa-refresh fa-spin text-info';

    document.getElementById('sg-sync-modal-backdrop').style.display = 'flex';
    document.getElementById('sg-floating-pill').style.display = 'none';
}

function minimizeSyncModal() {
    document.getElementById('sg-sync-modal-backdrop').style.display = 'none';
    if (isSyncRunning) {
        document.getElementById('sg-floating-pill').style.display = 'flex';
    }
}

function maximizeSyncModal() {
    document.getElementById('sg-sync-modal-backdrop').style.display = 'flex';
    document.getElementById('sg-floating-pill').style.display = 'none';
}

function closeSyncModal() {
    document.getElementById('sg-sync-modal-backdrop').style.display = 'none';
    document.getElementById('sg-floating-pill').style.display = 'none';
    fetchLiveStats();
}

function updateProgress(percent, phaseLabel, batchStr, syncedCount, latestName, latestClass) {
    percent = Math.min(100, Math.max(0, Math.round(percent)));
    document.getElementById('sg-progress-bar-fill').style.width = percent + '%';
    document.getElementById('sg-progress-percentage').innerText = percent + '%';
    if (phaseLabel) document.getElementById('sg-progress-phase-label').innerText = phaseLabel;
    if (batchStr) document.getElementById('sg-metric-batch').innerText = batchStr;
    if (syncedCount !== undefined) document.getElementById('sg-metric-synced').innerText = syncedCount;
    if (latestName) document.getElementById('sg-ticker-latest').innerText = latestName;
    if (latestClass) document.getElementById('sg-ticker-cohort').innerText = 'Cohort: ' + latestClass;

    document.getElementById('sg-pill-percent').innerText = percent + '%';
}

function markSyncFinished(successMessage) {
    isSyncRunning = false;
    updateProgress(100, 'Synchronization Completed!', 'Completed', undefined, successMessage, 'Success');
    document.getElementById('sg-metric-status').innerText = 'Done 100%';
    document.getElementById('sg-metric-status').className = 'font-weight-bold text-success mt-1';
    document.getElementById('sg-modal-spinner').className = 'fa fa-check-circle text-success';
    document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    document.getElementById('sg-btn-minimize').style.display = 'none';
    document.getElementById('sg-floating-pill').style.display = 'none';
    appendConsoleLog(`<span style="color: #10b981; font-weight: bold;">✔ ${successMessage}</span>`);
    fetchLiveStats();
}

async function fetchLiveStats(showToast = false) {
    try {
        const res = await fetch(`${AJAX_URL}?action=ajax_get_stats&sesskey=${SESSKEY}`);
        const data = await res.json();
        if (data.ok) {
            document.getElementById('card-count-guru').innerText = Number(data.count_guru).toLocaleString();
            document.getElementById('card-count-siswa').innerText = Number(data.count_siswa).toLocaleString();
            document.getElementById('card-count-cohort').innerText = Number(data.count_cohort).toLocaleString();
            document.getElementById('stat-active-ay').innerHTML = `<i class="fa fa-calendar-check-o text-success mr-1"></i> ${data.active_ay}`;
            document.getElementById('stat-last-sync').innerText = data.last_sync;
            if (showToast) {
                appendConsoleLog(`<span style="color: #38bdf8;">Dashboard statistics refreshed.</span>`);
            }
        }
    } catch (e) {
        console.error('Failed to fetch stats', e);
    }
}

async function runSyncTP() {
    showSyncModal('Sync Academic Year', 'Querying active Academic Year from portal...');
    appendConsoleLog('Querying GET /api/v1/academic-year/active...');
    updateProgress(30, 'Retrieving active academic year...');

    try {
        const res = await fetch(`${AJAX_URL}?action=ajax_sync_tp&sesskey=${SESSKEY}`);
        const data = await res.json();
        if (data.ok) {
            appendConsoleLog(`Success: ${data.name} (ID: ${data.id})`);
            markSyncFinished(`Active Academic Year configured: ${data.name}`);
        } else {
            throw new Error(data.message || 'Failed to sync academic year.');
        }
    } catch (err) {
        appendConsoleLog(`<span style="color: #ef4444;">Error: ${err.message}</span>`);
        document.getElementById('sg-metric-status').innerText = 'Failed';
        document.getElementById('sg-metric-status').className = 'font-weight-bold text-danger mt-1';
        document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    }
}

async function runSyncGTK() {
    showSyncModal('Sync Teachers & Staff', 'Pulling all PTK records and granting Course Creator role...');
    appendConsoleLog('Starting Teachers & Staff synchronization...');
    
    let totalSynced = 0;
    try {
        for (const role of ['guru', 'tendik']) {
            let page = 1, totalPages = 1;
            do {
                const label = role === 'guru' ? 'Teachers' : 'Staff';
                updateProgress((page / (totalPages + 1)) * 50, `Fetching ${label} Page ${page}...`, `${label} ${page}/${totalPages}`, totalSynced);
                const res = await fetch(`${AJAX_URL}?action=ajax_sync_gtk&role=${role}&page=${page}&sesskey=${SESSKEY}`);
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || `Failed to fetch ${role}.`);
                
                totalSynced += data.synced;
                totalPages = data.total_pages || 1;
                appendConsoleLog(`${label} batch ${page}/${totalPages}: +${data.synced} enrolled.`);
                page++;
            } while (page <= totalPages);
        }

        markSyncFinished(`Successfully synchronized ${totalSynced} Teachers & Educational Staff!`);
    } catch (err) {
        appendConsoleLog(`<span style="color: #ef4444;">Error: ${err.message}</span>`);
        document.getElementById('sg-metric-status').innerText = 'Failed';
        document.getElementById('sg-metric-status').className = 'font-weight-bold text-danger mt-1';
        document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    }
}

async function runSyncSiswa() {
    showSyncModal('Sync Students & Class Cohorts', 'Pulling active students and mapping to cohorts in batches...');
    appendConsoleLog('Starting active student & cohort synchronization...');
    
    let page = 1;
    let totalPages = 1;
    let totalSynced = 0;
    let totalItems = 0;

    try {
        do {
            const currentPercent = totalPages > 1 ? Math.round(((page - 1) / totalPages) * 100) : 5;
            updateProgress(currentPercent, `Processing Page ${page}...`, `${page} / ${totalPages}`, totalSynced);

            const res = await fetch(`${AJAX_URL}?action=ajax_sync_siswa&page=${page}&sesskey=${SESSKEY}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Failed to process student batch.');

            totalSynced += data.synced;
            totalPages = data.total_pages || 1;
            totalItems = data.total_items || totalSynced;

            const percent = Math.round((page / totalPages) * 100);
            updateProgress(percent, `Batch ${page}/${totalPages} complete`, `${page} / ${totalPages}`, `${totalSynced} / ${totalItems}`, data.latest_name, data.latest_class);
            appendConsoleLog(`Page ${page}/${totalPages}: +${data.synced} students synced (${data.latest_name || '-'} - ${data.latest_class || '-'})`);

            page++;
        } while (page <= totalPages);

        markSyncFinished(`Successfully synchronized all ${totalSynced} Students & Class Cohorts!`);
    } catch (err) {
        appendConsoleLog(`<span style="color: #ef4444;">Error: ${err.message}</span>`);
        document.getElementById('sg-metric-status').innerText = 'Failed';
        document.getElementById('sg-metric-status').className = 'font-weight-bold text-danger mt-1';
        document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    }
}

async function runSyncMutasiAlumni() {
    showSyncModal('Sync Departures & Alumni', 'Handling mutated/departed students and mapping alumni...');
    appendConsoleLog('Checking student departures (mutations) and alumni records...');
    updateProgress(20, 'Checking departures...');

    try {
        const res = await fetch(`${AJAX_URL}?action=ajax_sync_mutasi_alumni&sesskey=${SESSKEY}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed to sync departures/alumni.');

        appendConsoleLog(`Result: ${data.mutasi_count} departed accounts suspended, ${data.alumni_count} alumni reassigned.`);
        markSyncFinished(data.message || 'Departures and alumni processed successfully.');
    } catch (err) {
        appendConsoleLog(`<span style="color: #ef4444;">Error: ${err.message}</span>`);
        document.getElementById('sg-metric-status').innerText = 'Failed';
        document.getElementById('sg-metric-status').className = 'font-weight-bold text-danger mt-1';
        document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    }
}

async function runSyncAll() {
    if (!confirm('Run full orchestrated sync (Academic Year -> Teachers & Staff -> Students & Cohorts -> Departures & Alumni)? The page will not freeze and can be minimized.')) {
        return;
    }

    showSyncModal('Full Master Synchronization', 'Executing complete master synchronization pipeline...');
    appendConsoleLog('🚀 Starting Full Pipeline Orchestration...');

    try {
        // Step 1: AY
        updateProgress(5, 'Step 1/4: Synchronizing Academic Year...');
        appendConsoleLog('1. Synchronizing Active Academic Year...');
        const rTP = await (await fetch(`${AJAX_URL}?action=ajax_sync_tp&sesskey=${SESSKEY}`)).json();
        if (!rTP.ok) throw new Error('Academic Year sync failed: ' + (rTP.message || ''));
        appendConsoleLog(`✔ Active Academic Year: ${rTP.name}`);

        // Step 2: GTK
        updateProgress(15, 'Step 2/4: Synchronizing Teachers & Staff...');
        appendConsoleLog('2. Synchronizing Teachers & Staff...');
        let gtkSynced = 0;
        for (const role of ['guru', 'tendik']) {
            let p = 1, tPages = 1;
            do {
                const r = await (await fetch(`${AJAX_URL}?action=ajax_sync_gtk&role=${role}&page=${p}&sesskey=${SESSKEY}`)).json();
                if (!r.ok) throw new Error(`GTK (${role}) sync failed: ` + (r.error || ''));
                gtkSynced += r.synced;
                tPages = r.total_pages || 1;
                p++;
            } while (p <= tPages);
        }
        appendConsoleLog(`✔ Teachers & Staff synchronized: ${gtkSynced}`);

        // Step 3: Students
        updateProgress(35, 'Step 3/4: Synchronizing Students & Class Cohorts...');
        appendConsoleLog('3. Synchronizing Active Students & Class Cohorts...');
        let pSiswa = 1, tPagesSiswa = 1, siswaSynced = 0;
        do {
            const rS = await (await fetch(`${AJAX_URL}?action=ajax_sync_siswa&page=${pSiswa}&sesskey=${SESSKEY}`)).json();
            if (!rS.ok) throw new Error('Student sync failed: ' + (rS.error || ''));
            siswaSynced += rS.synced;
            tPagesSiswa = rS.total_pages || 1;
            const progressRange = 35 + Math.round((pSiswa / tPagesSiswa) * 55);
            updateProgress(progressRange, `Step 3/4: Students Batch ${pSiswa}/${tPagesSiswa}`, `${pSiswa}/${tPagesSiswa}`, siswaSynced, rS.latest_name, rS.latest_class);
            appendConsoleLog(`Page ${pSiswa}/${tPagesSiswa}: +${rS.synced} students (${rS.latest_name || '-'} - ${rS.latest_class || '-'})`);
            pSiswa++;
        } while (pSiswa <= tPagesSiswa);

        // Step 4: Departures & Alumni
        updateProgress(92, 'Step 4/4: Handling Departures & Alumni...');
        appendConsoleLog('4. Synchronizing Departures & Alumni...');
        const rMutasi = await (await fetch(`${AJAX_URL}?action=ajax_sync_mutasi_alumni&sesskey=${SESSKEY}`)).json();
        if (!rMutasi.ok) throw new Error('Departures/Alumni sync failed: ' + (rMutasi.error || ''));
        appendConsoleLog(`✔ Departures & Alumni: ${rMutasi.message}`);

        markSyncFinished(`All pipeline stages completed successfully! Total Students: ${siswaSynced}, Teachers & Staff: ${gtkSynced}`);
    } catch (err) {
        appendConsoleLog(`<span style="color: #ef4444;">Error: ${err.message}</span>`);
        document.getElementById('sg-metric-status').innerText = 'Failed';
        document.getElementById('sg-metric-status').className = 'font-weight-bold text-danger mt-1';
        document.getElementById('sg-btn-close-modal').style.display = 'inline-block';
    }
}
</script>

<?php
echo $OUTPUT->footer();
