<?php
/**
 * Add Inmate – Enhanced UI
 */
$pageTitle = 'Add New Inmate';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('create', 'inmates');

$facilities = getAccessibleFacilities();
$currentFid = getCurrentFacility();
$superAdmin = isSuperAdmin();
?>

<!-- ── Page Styles ─────────────────────────────────────────── -->
<style>
/* ── layout ── */
.add-inmate-wrap{display:flex;gap:1.5rem;align-items:flex-start;padding:1.5rem 0 3rem}

/* ── sticky left nav ── */
.form-nav{position:sticky;top:80px;width:220px;flex-shrink:0}
.form-nav-inner{background:#161b22;border:1px solid #21262d;border-radius:12px;overflow:hidden}
.form-nav-header{padding:1rem 1.25rem .75rem;border-bottom:1px solid #21262d}
.form-nav-header span{font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#8b949e}
.nav-step{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.25rem;color:#8b949e;cursor:pointer;transition:all .2s;border-left:3px solid transparent;font-size:.875rem}
.nav-step:hover{color:#e6edf3;background:rgba(255,255,255,.04)}
.nav-step.active{color:#388bfd;background:rgba(56,139,253,.08);border-left-color:#388bfd}
.nav-step.done{color:#3fb950}
.nav-step.done .step-num{background:#3fb950;color:#0d1117}
.nav-step .step-num{width:22px;height:22px;border-radius:50%;background:#21262d;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.nav-step.active .step-num{background:#388bfd;color:#fff}
.nav-step .step-label{font-weight:500;white-space:nowrap}

/* ── avatar preview ── */
.avatar-preview-wrap{text-align:center;padding:1.25rem 1.25rem .75rem;border-top:1px solid #21262d;margin-top:.5rem}
.avatar-preview{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#1f6feb,#388bfd);display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;color:#fff;margin:0 auto .5rem;border:2px solid #21262d;transition:all .3s}
.avatar-name-preview{font-size:.8rem;color:#8b949e;word-break:break-word;line-height:1.3}

/* ── main form area ── */
.form-main{flex:1;min-width:0}
.form-section{display:none}
.form-section.active{display:block;animation:sectionIn .25s ease}
@keyframes sectionIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── section card ── */
.section-card{background:#161b22;border:1px solid #21262d;border-radius:12px;margin-bottom:1.25rem;overflow:hidden}
.section-card-header{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-bottom:1px solid #21262d;background:rgba(255,255,255,.02)}
.section-card-header .sec-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.875rem;flex-shrink:0}
.section-card-header h3{margin:0;font-size:.95rem;font-weight:600;color:#e6edf3}
.section-card-header p{margin:0;font-size:.75rem;color:#8b949e}
.section-card-body{padding:1.25rem}

/* ── form grid ── */
.fgrid{display:grid;gap:1rem}
.fgrid-2{grid-template-columns:1fr 1fr}
.fgrid-3{grid-template-columns:1fr 1fr 1fr}
.fgrid-4{grid-template-columns:1fr 1fr 1fr 1fr}
@media(max-width:900px){.fgrid-3,.fgrid-4{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.fgrid-2,.fgrid-3,.fgrid-4{grid-template-columns:1fr}}

/* ── inputs ── */
.field-wrap{display:flex;flex-direction:column;gap:.35rem}
.field-label{font-size:.78rem;font-weight:600;color:#8b949e;letter-spacing:.02em;display:flex;align-items:center;gap:.35rem}
.field-label .req{color:#f85149}
.field-label .field-hint{font-size:.72rem;color:#6e7681;font-weight:400;margin-left:auto}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:.55rem .85rem;font-size:.875rem;width:100%;transition:border-color .2s,box-shadow .2s;outline:none;appearance:none}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi:hover:not(:focus){border-color:#6e7681}
.fi.is-valid{border-color:#3fb950}
.fi.is-invalid{border-color:#f85149;box-shadow:0 0 0 3px rgba(248,81,73,.12)}
.fi-textarea{resize:vertical;min-height:90px;font-family:inherit}

/* ── select styling ── */
select.fi{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:12px;padding-right:2.25rem;cursor:pointer}

/* ── risk selector ── */
.risk-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem}
.risk-opt{position:relative}
.risk-opt input{position:absolute;opacity:0;width:0}
.risk-opt label{display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.75rem .5rem;border:2px solid #21262d;border-radius:10px;cursor:pointer;transition:all .2s;text-align:center;background:#0d1117}
.risk-opt label .risk-icon{font-size:1.25rem}
.risk-opt label .risk-name{font-size:.72rem;font-weight:600}
.risk-opt label .risk-desc{font-size:.65rem;color:#6e7681}
.risk-opt input:checked + label.risk-low{border-color:#3fb950;background:rgba(63,185,80,.08);color:#3fb950}
.risk-opt input:checked + label.risk-medium{border-color:#d29922;background:rgba(210,153,34,.08);color:#d29922}
.risk-opt input:checked + label.risk-high{border-color:#f85149;background:rgba(248,81,73,.08);color:#f85149}
.risk-opt input:checked + label.risk-critical{border-color:#b91c1c;background:rgba(185,28,28,.12);color:#ff6b6b;box-shadow:0 0 0 4px rgba(185,28,28,.2)}

/* ── status selector ── */
.status-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem}
.status-opt{position:relative}
.status-opt input{position:absolute;opacity:0;width:0}
.status-opt label{display:flex;align-items:center;gap:.5rem;padding:.65rem .9rem;border:2px solid #21262d;border-radius:8px;cursor:pointer;transition:all .2s;background:#0d1117;font-size:.8rem;font-weight:500}
.status-opt label .si{font-size:1rem}
.status-opt input:checked + label{border-color:#388bfd;background:rgba(56,139,253,.1);color:#58a6ff}

/* ── info banner ── */
.info-banner{background:rgba(56,139,253,.08);border:1px solid rgba(56,139,253,.2);border-radius:8px;padding:.75rem 1rem;font-size:.8rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.6rem;margin-bottom:1rem}

/* ── section progress bar ── */
.progress-bar-wrap{background:#161b22;border:1px solid #21262d;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.25rem}
.progress-steps{display:flex;align-items:center;gap:0}
.pstep{display:flex;align-items:center;flex:1}
.pstep-dot{width:28px;height:28px;border-radius:50%;border:2px solid #30363d;background:#0d1117;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#6e7681;transition:all .3s;flex-shrink:0;cursor:pointer}
.pstep-dot.active{border-color:#388bfd;background:#388bfd;color:#fff;box-shadow:0 0 0 4px rgba(56,139,253,.2)}
.pstep-dot.done{border-color:#3fb950;background:#3fb950;color:#0d1117}
.pstep-line{flex:1;height:2px;background:#21262d;transition:background .3s}
.pstep-line.done{background:#3fb950}
.pstep:last-child .pstep-line{display:none}

/* ── bottom action bar ── */
.form-actions{display:flex;align-items:center;justify-content:space-between;background:#161b22;border:1px solid #21262d;border-radius:12px;padding:.875rem 1.25rem;gap:1rem;position:sticky;bottom:0;z-index:10}
.form-actions .step-info{font-size:.8rem;color:#6e7681}
.form-actions .step-info strong{color:#e6edf3}
.btn-nav{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.1rem;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;border:none;transition:all .2s}
.btn-back{background:#21262d;color:#8b949e}.btn-back:hover{background:#30363d;color:#e6edf3}
.btn-next{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff}.btn-next:hover{opacity:.9;transform:translateY(-1px)}
.btn-submit{background:linear-gradient(135deg,#1a7f37,#2ea043);color:#fff}.btn-submit:hover{opacity:.9;transform:translateY(-1px)}

/* ── toast ── */
#toast{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);background:#161b22;border:1px solid #21262d;border-radius:10px;padding:.75rem 1.25rem;font-size:.85rem;color:#e6edf3;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:9999;opacity:0;transition:all .3s;pointer-events:none;white-space:nowrap}
#toast.show{opacity:1;transform:translateX(-50%) translateY(-4px)}
#toast.t-err{border-color:#f85149;color:#ff6b6b}
#toast.t-ok{border-color:#3fb950;color:#3fb950}

/* ── page header ── */
.page-header-add{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;gap:1rem}
.page-header-add h1{font-size:1.35rem;font-weight:700;color:#e6edf3;margin:0}
.breadcrumb-trail{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.35rem;margin-bottom:.35rem}
.breadcrumb-trail a{color:#388bfd;text-decoration:none}.breadcrumb-trail a:hover{text-decoration:underline}

/* ── helpers ── */
.calc-badge{display:inline-flex;align-items:center;gap:.35rem;background:#21262d;border-radius:6px;padding:.3rem .65rem;font-size:.76rem;color:#8b949e;margin-top:.35rem}
.calc-badge strong{color:#e6edf3}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header-add">
  <div>
    <div class="breadcrumb-trail">
      <a href="<?php echo APP_URL; ?>/modules/inmates/index.php"><i class="bi bi-people"></i> Inmates</a>
      <i class="bi bi-chevron-right" style="font-size:.65rem"></i>
      <span>Add New</span>
    </div>
    <h1><i class="bi bi-person-plus-fill" style="color:#388bfd;margin-right:.4rem"></i>Add New Inmate</h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/inmates/index.php" class="btn-nav btn-back">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<!-- ── Progress Bar ─────────────────────────────────────────── -->
<div class="progress-bar-wrap">
  <div class="progress-steps">
    <div class="pstep" id="ps1">
      <div class="pstep-dot active" onclick="goStep(1)">1</div>
      <div class="pstep-line" id="pl1"></div>
    </div>
    <div class="pstep" id="ps2">
      <div class="pstep-dot" onclick="goStep(2)">2</div>
      <div class="pstep-line" id="pl2"></div>
    </div>
    <div class="pstep" id="ps3">
      <div class="pstep-dot" onclick="goStep(3)">3</div>
      <div class="pstep-line" id="pl3"></div>
    </div>
    <div class="pstep" id="ps4">
      <div class="pstep-dot" onclick="goStep(4)">4</div>
      <div class="pstep-line"></div>
    </div>
  </div>
</div>

<!-- ── Two-Column Layout ─────────────────────────────────────── -->
<div class="add-inmate-wrap">

  <!-- Left Nav -->
  <aside class="form-nav">
    <div class="form-nav-inner">
      <div class="form-nav-header"><span>Form Sections</span></div>
      <div class="nav-step active" onclick="goStep(1)" id="ns1">
        <div class="step-num">1</div>
        <div>
          <div class="step-label">Personal Info</div>
          <div style="font-size:.7rem;color:#6e7681">Name, DOB, Gender</div>
        </div>
      </div>
      <div class="nav-step" onclick="goStep(2)" id="ns2">
        <div class="step-num">2</div>
        <div>
          <div class="step-label">Legal &amp; Class.</div>
          <div style="font-size:.7rem;color:#6e7681">Status, Risk, Sentence</div>
        </div>
      </div>
      <div class="nav-step" onclick="goStep(3)" id="ns3">
        <div class="step-num">3</div>
        <div>
          <div class="step-label">Health &amp; Background</div>
          <div style="font-size:.7rem;color:#6e7681">Medical, Religion</div>
        </div>
      </div>
      <div class="nav-step" onclick="goStep(4)" id="ns4">
        <div class="step-num">4</div>
        <div>
          <div class="step-label">Emergency Contact</div>
          <div style="font-size:.7rem;color:#6e7681">Next of Kin</div>
        </div>
      </div>

      <!-- Avatar Preview -->
      <div class="avatar-preview-wrap">
        <div class="avatar-preview" id="avatarPreview">?</div>
        <div class="avatar-name-preview" id="avatarName">Enter name above</div>
      </div>
    </div>
  </aside>

  <!-- Right Form -->
  <div class="form-main">
    <form id="addInmateForm" novalidate>

      <!-- ══ STEP 1: Personal Info ══════════════════════════════ -->
      <div class="form-section active" id="sec1">

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-person-badge"></i></div>
            <div>
              <h3>Inmate Identification</h3>
              <p>Core identity fields and record ID</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="info-banner">
              <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:.05rem"></i>
              <span>Fields marked <strong style="color:#f85149">*</strong> are required. Inmate ID is auto-generated but can be edited.</span>
            </div>

            <?php if($superAdmin && count($facilities) > 1): ?>
            <div class="fgrid fgrid-2" style="margin-bottom:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-building"></i> Facility <span class="req">*</span></label>
                <select name="facility_id" class="fi" id="facilitySelect" required>
                  <?php foreach($facilities as $f): ?>
                  <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-hash"></i> Inmate ID <span class="req">*</span></label>
                <input type="text" name="inmate_id" id="inmateId" class="fi" placeholder="INM-2026-001" required>
              </div>
            </div>
            <?php else: ?>
            <div class="fgrid fgrid-2" style="margin-bottom:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-hash"></i> Inmate ID <span class="req">*</span></label>
                <input type="text" name="inmate_id" id="inmateId" class="fi" placeholder="INM-2026-001" required>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-card-text"></i> National / ID Number</label>
                <input type="text" name="national_id" class="fi" placeholder="e.g. NRC 123456/10/1">
              </div>
            </div>
            <?php endif; ?>

            <div class="fgrid fgrid-3">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-person"></i> First Name <span class="req">*</span></label>
                <input type="text" name="first_name" id="firstName" class="fi" placeholder="John" required>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-person"></i> Middle Name</label>
                <input type="text" name="middle_name" class="fi" placeholder="Optional">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-person"></i> Last Name <span class="req">*</span></label>
                <input type="text" name="last_name" id="lastName" class="fi" placeholder="Doe" required>
              </div>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-person-vcard"></i></div>
            <div>
              <h3>Demographics</h3>
              <p>Date of birth, gender and identifiers</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="fgrid fgrid-3" style="margin-bottom:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-calendar3"></i> Date of Birth <span class="req">*</span></label>
                <input type="date" name="date_of_birth" id="dob" class="fi" required>
                <div class="calc-badge" id="ageDisplay" style="display:none"><i class="bi bi-clock"></i> Age: <strong id="ageVal">—</strong></div>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-gender-ambiguous"></i> Gender <span class="req">*</span></label>
                <select name="gender" class="fi" required>
                  <option value="">— Select —</option>
                  <option value="MALE">Male</option>
                  <option value="FEMALE">Female</option>
                  <option value="OTHER">Other</option>
                </select>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-globe2"></i> Nationality</label>
                <input type="text" name="nationality" class="fi" placeholder="e.g. Zambian">
              </div>
            </div>
            <div class="fgrid fgrid-2">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-geo-alt"></i> Place of Birth</label>
                <input type="text" name="place_of_birth" class="fi" placeholder="City / District">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-house"></i> Home Address</label>
                <input type="text" name="home_address" class="fi" placeholder="Street, City">
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ══ STEP 2: Legal & Classification ════════════════════ -->
      <div class="form-section" id="sec2">

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(248,81,73,.12);color:#f85149"><i class="bi bi-shield-exclamation"></i></div>
            <div>
              <h3>Legal Status</h3>
              <p>Custody type and admission details</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="field-wrap" style="margin-bottom:1rem">
              <label class="field-label"><i class="bi bi-journal-check"></i> Status <span class="req">*</span></label>
              <div class="status-grid">
                <div class="status-opt">
                  <input type="radio" name="status" id="st_remand" value="REMAND">
                  <label for="st_remand"><span class="si">⏳</span> Remand</label>
                </div>
                <div class="status-opt">
                  <input type="radio" name="status" id="st_convicted" value="CONVICTED" checked>
                  <label for="st_convicted"><span class="si">⚖️</span> Convicted</label>
                </div>
                <div class="status-opt">
                  <input type="radio" name="status" id="st_pending" value="PENDING_TRIAL">
                  <label for="st_pending"><span class="si">📋</span> Pending Trial</label>
                </div>
              </div>
            </div>

            <div class="fgrid fgrid-3">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-calendar-check"></i> Admission Date <span class="req">*</span></label>
                <input type="date" name="admission_date" id="admissionDate" class="fi" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-hourglass-split"></i> Sentence (months) <span class="field-hint">Auto-calculates end</span></label>
                <input type="number" name="sentence_length_months" id="sentenceMonths" class="fi" placeholder="e.g. 36" min="0">
                <div class="calc-badge" id="endDateDisplay" style="display:none"><i class="bi bi-calendar-x"></i> Release: <strong id="endDateVal">—</strong></div>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-calendar3-event"></i> Expected Release Date</label>
                <input type="date" name="expected_release_date" id="releaseDate" class="fi">
              </div>
            </div>

            <div class="fgrid fgrid-2" style="margin-top:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-file-earmark-text"></i> Case Number</label>
                <input type="text" name="case_number" class="fi" placeholder="e.g. CR/001/2026">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-geo"></i> Court / Jurisdiction</label>
                <input type="text" name="court" class="fi" placeholder="e.g. Lusaka High Court">
              </div>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(210,153,34,.12);color:#d29922"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
              <h3>Risk Classification</h3>
              <p>Determines housing, supervision and restrictions</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="field-wrap" style="margin-bottom:1rem">
              <label class="field-label"><i class="bi bi-speedometer2"></i> Risk Level <span class="req">*</span></label>
              <div class="risk-grid">
                <div class="risk-opt">
                  <input type="radio" name="risk_classification" id="rk_low" value="LOW" checked>
                  <label for="rk_low" class="risk-low">
                    <span class="risk-icon">🟢</span>
                    <span class="risk-name">Low</span>
                    <span class="risk-desc">Minimal threat</span>
                  </label>
                </div>
                <div class="risk-opt">
                  <input type="radio" name="risk_classification" id="rk_medium" value="MEDIUM">
                  <label for="rk_medium" class="risk-medium">
                    <span class="risk-icon">🟡</span>
                    <span class="risk-name">Medium</span>
                    <span class="risk-desc">Moderate risk</span>
                  </label>
                </div>
                <div class="risk-opt">
                  <input type="radio" name="risk_classification" id="rk_high" value="HIGH">
                  <label for="rk_high" class="risk-high">
                    <span class="risk-icon">🔴</span>
                    <span class="risk-name">High</span>
                    <span class="risk-desc">Significant risk</span>
                  </label>
                </div>
                <div class="risk-opt">
                  <input type="radio" name="risk_classification" id="rk_crit" value="MAXIMUM">
                  <label for="rk_crit" class="risk-critical">
                    <span class="risk-icon">💀</span>
                    <span class="risk-name">Maximum</span>
                    <span class="risk-desc">Extreme danger</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="field-wrap">
              <label class="field-label"><i class="bi bi-file-earmark-break"></i> Offence / Charge</label>
              <input type="text" name="offence" class="fi" placeholder="e.g. Armed Robbery, Drug Trafficking…">
            </div>
          </div>
        </div>

      </div>

      <!-- ══ STEP 3: Health & Background ═══════════════════════ -->
      <div class="form-section" id="sec3">

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(56,189,248,.12);color:#38bdf8"><i class="bi bi-heart-pulse"></i></div>
            <div>
              <h3>Health &amp; Medical</h3>
              <p>Pre-existing conditions and physical description</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="fgrid fgrid-2" style="margin-bottom:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-droplet-half"></i> Blood Group</label>
                <select name="blood_group" class="fi">
                  <option value="">— Unknown —</option>
                  <option>A+</option><option>A-</option>
                  <option>B+</option><option>B-</option>
                  <option>AB+</option><option>AB-</option>
                  <option>O+</option><option>O-</option>
                </select>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-rulers"></i> Height (cm)</label>
                <input type="number" name="height_cm" class="fi" placeholder="e.g. 175" min="50" max="250">
              </div>
            </div>

            <div class="field-wrap" style="margin-bottom:1rem">
              <label class="field-label">
                <i class="bi bi-clipboard2-pulse"></i> Medical Conditions / Allergies
                <span class="field-hint" id="mc_hint">0 / 500</span>
              </label>
              <textarea name="medical_conditions" id="medConditions" class="fi fi-textarea" maxlength="500"
                placeholder="List any pre-existing conditions, allergies, medications currently taken…"></textarea>
            </div>

            <div class="field-wrap">
              <label class="field-label">
                <i class="bi bi-eye"></i> Physical Marks / Tattoos
                <span class="field-hint" id="pm_hint">0 / 300</span>
              </label>
              <textarea name="physical_marks" id="physMarks" class="fi fi-textarea" style="min-height:75px" maxlength="300"
                placeholder="Scars, tattoos or other identifying marks…"></textarea>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(168,85,247,.12);color:#a855f7"><i class="bi bi-bookmarks"></i></div>
            <div>
              <h3>Background</h3>
              <p>Education, religion and employment details</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="fgrid fgrid-3">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-mortarboard"></i> Education Level</label>
                <select name="education_level" class="fi">
                  <option value="">— Select —</option>
                  <option value="NONE">None</option>
                  <option value="PRIMARY">Primary</option>
                  <option value="SECONDARY">Secondary</option>
                  <option value="TERTIARY">Tertiary / College</option>
                  <option value="UNIVERSITY">University+</option>
                </select>
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-stars"></i> Religion</label>
                <input type="text" name="religion" class="fi" placeholder="e.g. Christian, Muslim…">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-briefcase"></i> Occupation Before</label>
                <input type="text" name="occupation" class="fi" placeholder="Previous occupation">
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ══ STEP 4: Emergency Contact ═════════════════════════ -->
      <div class="form-section" id="sec4">

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(240,136,62,.12);color:#f0883e"><i class="bi bi-telephone-inbound"></i></div>
            <div>
              <h3>Next of Kin / Emergency Contact</h3>
              <p>Person to be contacted in case of emergency</p>
            </div>
          </div>
          <div class="section-card-body">
            <div class="fgrid fgrid-2" style="margin-bottom:1rem">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-person-lines-fill"></i> Full Name</label>
                <input type="text" name="next_of_kin_name" class="fi" placeholder="Contact's full name">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-diagram-3"></i> Relationship</label>
                <select name="next_of_kin_relationship" class="fi">
                  <option value="">— Relationship —</option>
                  <option value="PARENT">Parent</option>
                  <option value="SPOUSE">Spouse</option>
                  <option value="SIBLING">Sibling</option>
                  <option value="CHILD">Child</option>
                  <option value="RELATIVE">Other Relative</option>
                  <option value="FRIEND">Friend</option>
                  <option value="GUARDIAN">Guardian</option>
                </select>
              </div>
            </div>

            <div class="fgrid fgrid-3">
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-telephone"></i> Phone</label>
                <input type="tel" name="next_of_kin_phone" class="fi" placeholder="+260 977 000 000">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-envelope"></i> Email</label>
                <input type="email" name="next_of_kin_email" class="fi" placeholder="contact@example.com">
              </div>
              <div class="field-wrap">
                <label class="field-label"><i class="bi bi-geo-alt"></i> Address</label>
                <input type="text" name="next_of_kin_address" class="fi" placeholder="City, District">
              </div>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-card-header">
            <div class="sec-icon" style="background:rgba(63,185,80,.12);color:#3fb950"><i class="bi bi-check2-all"></i></div>
            <div>
              <h3>Review &amp; Submit</h3>
              <p>Verify details before saving the record</p>
            </div>
          </div>
          <div class="section-card-body">
            <div id="reviewPanel" style="display:grid;gap:.6rem;font-size:.85rem"></div>
          </div>
        </div>

      </div>

    </form><!-- /form -->

    <!-- ── Bottom Action Bar ──────────────────────── -->
    <div class="form-actions">
      <div class="step-info">Step <strong id="stepLabel">1</strong> of <strong>4</strong></div>
      <div style="display:flex;gap:.6rem;align-items:center">
        <button class="btn-nav btn-back" id="btnBack" onclick="prevStep()" style="display:none">
          <i class="bi bi-chevron-left"></i> Back
        </button>
        <button class="btn-nav btn-next" id="btnNext" onclick="nextStep()">
          Next <i class="bi bi-chevron-right"></i>
        </button>
        <button class="btn-nav btn-submit" id="btnSubmit" onclick="submitForm()" style="display:none">
          <i class="bi bi-check-lg"></i> Add Inmate
        </button>
      </div>
    </div>

  </div><!-- /form-main -->
</div><!-- /add-inmate-wrap -->

<div id="toast"></div>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<script>
const APP_URL      = '<?php echo APP_URL; ?>';
const FACILITY_ID  = <?php echo json_encode($currentFid); ?>;
const IS_SUPER     = <?php echo $superAdmin ? 'true' : 'false'; ?>;
const TOTAL_STEPS  = 4;
let currentStep    = 1;

/* ─ Step utilities ─────────────────────────────────────────── */
function goStep(n) {
  if (n < 1 || n > TOTAL_STEPS) return;

  document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec' + n).classList.add('active');

  // nav steps
  document.querySelectorAll('.nav-step').forEach((el, i) => {
    el.classList.remove('active', 'done');
    if (i + 1 === n) el.classList.add('active');
    else if (i + 1 < n) el.classList.add('done');
    const num = el.querySelector('.step-num');
    if (i + 1 < n) num.innerHTML = '<i class="bi bi-check-lg" style="font-size:.65rem"></i>';
    else num.textContent = i + 1;
  });

  // progress dots
  for (let i = 1; i <= TOTAL_STEPS; i++) {
    const dot = document.querySelector('#ps' + i + ' .pstep-dot');
    dot.classList.remove('active', 'done');
    if (i === n) dot.classList.add('active');
    else if (i < n) { dot.classList.add('done'); dot.innerHTML = '<i class="bi bi-check-lg" style="font-size:.65rem"></i>'; }
    else dot.textContent = i;
    if (i < TOTAL_STEPS) document.getElementById('pl' + i).classList.toggle('done', i < n);
  }

  document.getElementById('stepLabel').textContent = n;
  document.getElementById('btnBack').style.display   = n > 1 ? '' : 'none';
  document.getElementById('btnNext').style.display   = n < TOTAL_STEPS ? '' : 'none';
  document.getElementById('btnSubmit').style.display = n === TOTAL_STEPS ? '' : 'none';

  if (n === TOTAL_STEPS) buildReview();
  currentStep = n;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function nextStep() { goStep(currentStep + 1); }
function prevStep() { goStep(currentStep - 1); }

/* ─ Avatar preview ─────────────────────────────────────────── */
function updateAvatar() {
  const f = (document.getElementById('firstName')?.value || '').trim();
  const l = (document.getElementById('lastName')?.value  || '').trim();
  const initials = ((f[0] || '') + (l[0] || '')).toUpperCase() || '?';
  document.getElementById('avatarPreview').textContent = initials;
  document.getElementById('avatarName').textContent = [f, l].filter(Boolean).join(' ') || 'Enter name above';
}
document.getElementById('firstName')?.addEventListener('input', updateAvatar);
document.getElementById('lastName')?.addEventListener('input', updateAvatar);

/* ─ Age calculator ─────────────────────────────────────────── */
document.getElementById('dob')?.addEventListener('change', function() {
  const age = Math.floor((Date.now() - new Date(this.value)) / (365.25 * 86400000));
  const el  = document.getElementById('ageDisplay');
  if (!isNaN(age) && age >= 0 && age < 130) {
    document.getElementById('ageVal').textContent = age + ' years';
    el.style.display = 'inline-flex';
  } else { el.style.display = 'none'; }
});

/* ─ Sentence → release date ────────────────────────────────── */
function calcRelease() {
  const months  = parseInt(document.getElementById('sentenceMonths')?.value) || 0;
  const admDate = document.getElementById('admissionDate')?.value;
  if (months > 0 && admDate) {
    const d = new Date(admDate);
    d.setMonth(d.getMonth() + months);
    document.getElementById('releaseDate').value  = d.toISOString().split('T')[0];
    document.getElementById('endDateVal').textContent = d.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
    document.getElementById('endDateDisplay').style.display = 'inline-flex';
  }
}
document.getElementById('sentenceMonths')?.addEventListener('input', calcRelease);
document.getElementById('admissionDate')?.addEventListener('change', calcRelease);

/* ─ Auto-generate inmate ID ─────────────────────────────────── */
(function() {
  const yr   = new Date().getFullYear();
  const rand = String(Math.floor(Math.random() * 900) + 100);
  const el   = document.getElementById('inmateId');
  if (el && !el.value) el.value = `INM-${yr}-${rand}`;
})();

/* ─ Char counters ───────────────────────────────────────────── */
['medConditions:mc_hint', 'physMarks:pm_hint'].forEach(pair => {
  const [inputId, hintId] = pair.split(':');
  const el = document.getElementById(inputId);
  const hn = document.getElementById(hintId);
  if (!el || !hn) return;
  el.addEventListener('input', () => hn.textContent = `${el.value.length} / ${el.maxLength}`);
});

/* ─ Review panel ────────────────────────────────────────────── */
function buildReview() {
  const data = Object.fromEntries(new FormData(document.getElementById('addInmateForm')));
  const rows = [
    ['Inmate ID',      data.inmate_id],
    ['Full Name',      [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ')],
    ['Date of Birth',  data.date_of_birth],
    ['Gender',         data.gender],
    ['Nationality',    data.nationality || '—'],
    ['Status',         data.status],
    ['Risk Level',     data.risk_classification],
    ['Admission Date', data.admission_date],
    ['Sentence',       data.sentence_length_months ? data.sentence_length_months + ' months' : '—'],
    ['Release Date',   data.expected_release_date || '—'],
    ['Offence',        data.offence || '—'],
    ['Medical',        data.medical_conditions ? '✓ Recorded' : '—'],
    ['Next of Kin',    data.next_of_kin_name || '—'],
  ];
  document.getElementById('reviewPanel').innerHTML = rows.map(([k, v]) => `
    <div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .6rem;background:#0d1117;border-radius:6px;border:1px solid #21262d">
      <span style="color:#8b949e;width:140px;flex-shrink:0;font-size:.78rem">${k}</span>
      <span style="color:#e6edf3;font-weight:500">${v || '<span style="color:#484f58">—</span>'}</span>
    </div>`).join('');
}

/* ─ Submit ──────────────────────────────────────────────────── */
async function submitForm() {
  const data = Object.fromEntries(new FormData(document.getElementById('addInmateForm')));

  const required = ['inmate_id','first_name','last_name','date_of_birth','gender','status','admission_date'];
  const missing  = required.filter(k => !(data[k] || '').trim());
  if (missing.length) {
    showToast('Please fill in: ' + missing.map(s => s.replace(/_/g,' ')).join(', '), 'err');
    return;
  }

  if (!IS_SUPER) data.facility_id = FACILITY_ID;

  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

  try {
    const resp = await fetch(`${APP_URL}/api/inmates/`, {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify(data)
    });
    const json = await resp.json();

    if (json.success) {
      showToast('Inmate added successfully!', 'ok');
      setTimeout(() => window.location.href = `${APP_URL}/modules/inmates/index.php`, 1200);
    } else {
      showToast(json.message || 'Failed to add inmate', 'err');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg"></i> Add Inmate';
    }
  } catch (err) {
    showToast('Network error: ' + err.message, 'err');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Add Inmate';
  }
}

/* ─ Toast ───────────────────────────────────────────────────── */
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'show' + (type === 'err' ? ' t-err' : type === 'ok' ? ' t-ok' : '');
  clearTimeout(t._to);
  t._to = setTimeout(() => t.className = '', 3500);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
