(function(){
  // Cross-tab logout sync and session polling for Admin Dashboard
  function wireLogoutBroadcast(){
      var logoutForms = Array.prototype.slice.call(document.querySelectorAll('form'));
      logoutForms.forEach(function(f){
          if (f.__logoutWired) return;
          var hasLogoutButton = !!f.querySelector('button[name="logout"],input[name="logout"]');
          if (hasLogoutButton) {
              f.addEventListener('submit', function(){
                  try { localStorage.setItem('MMTVTC_LOGOUT', String(Date.now())); } catch(e) {}
              }, { capture: true });
              f.__logoutWired = true;
          }
      });
  }
  window.addEventListener('storage', function(ev){
      if (!ev) return;
      if ((ev.key === 'MMTVTC_LOGOUT' && ev.newValue) || ev.key === 'logout_timestamp') {
          try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'storage_logout', page: window.location.href, info: { key: ev.key } }) }); } catch(_){ }
          try { window.location.replace('index.php'); } catch(e) { window.location.href = 'index.php'; }
      }
  });
  // Cookie-based logout flag check (every 1s)
  var __logoutCookieInterval = setInterval(function(){
      try {
          if (document.cookie.indexOf('MMTVTC_LOGOUT_FLAG=1') !== -1) {
              clearInterval(__logoutCookieInterval);
              try { window.location.replace('index.php'); } catch(e) { window.location.href = 'index.php'; }
          }
      } catch(e) {}
  }, 1000);
  function startSessionPolling(){
      var POLL_MS = 7000;
      function check(){
          fetch('apis/session_status.php', { credentials: 'same-origin', cache: 'no-store' })
              .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
              .then(function(j){ if (!j || !j.authenticated) { try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'session_invalid', page: window.location.href, info: j }) }); } catch(_){ } try { window.location.replace('index.php'); } catch(e) { window.location.href = 'index.php'; } } })
              .catch(function(err){ try { console.error('session_status failed', err); } catch(_) {} try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'session_check_error', page: window.location.href, info: String(err) }) }); } catch(_){ } });
      }
      setInterval(check, POLL_MS);
      document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'visible') { check(); } });
      window.addEventListener('pageshow', function(e){ if (e && e.persisted) { check(); } });
  }
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function(){ wireLogoutBroadcast(); startSessionPolling(); });
  } else { wireLogoutBroadcast(); startSessionPolling(); }
  function handleLogoutClick(){
      try { localStorage.setItem('logout_timestamp', String(Date.now())); localStorage.removeItem('logout_timestamp'); } catch(_) {}
  }
  document.addEventListener('DOMContentLoaded', function(){
      var btnA = document.getElementById('logout-btn');
      var link = document.querySelector('a[href="../auth/logout.php"]');
      if (btnA) { btnA.addEventListener('click', handleLogoutClick, { capture: true }); }
      if (link) { link.addEventListener('click', handleLogoutClick, { capture: true }); }
  });
})();
// Jobs: Add, List, and UI binding for Admin Dashboard
// --- Global bootstrap: ensure credentials + CSRF on admin API calls, and provide safe stubs ---
(function(){
try {
  // Safe stubs to avoid hard failures if modules load later
  if (typeof window.initializeCareerAnalyticsAdmin !== 'function') {
    window.initializeCareerAnalyticsAdmin = function(){ /* no-op until charts module loads */ };
  }
  if (typeof window.displayErrorMessage !== 'function') {
    window.displayErrorMessage = function(msg){ try{ console.error(msg); }catch(e){} };
  }

  var originalFetch = window.fetch.bind(window);
  function sameOrigin(u){ try{ var url=new URL(u, window.location.origin); return url.origin===window.location.origin; }catch(e){ return true; } }
  function getCsrf(){
    try{
      var el = document.getElementById('csrf_token');
      if (el && el.value) return el.value;
      // Try meta tag fallback
      var meta = document.querySelector('meta[name="csrf-token"]');
      if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
      // Try cookie fallback
      var m = document.cookie.match(/(?:^|; )csrf_token=([^;]+)/);
      if (m) return decodeURIComponent(m[1]);
    }catch(e){}
    return '';
  }
  // Minimal helper functions only; avoid mutating request bodies globally

  window.fetch = function(resource, options){
    options = options || {};
    // Keep credentials for same-origin
    if (!options.credentials && sameOrigin(resource)) { options.credentials = 'same-origin'; }
    // Ensure headers object
    if (!options.headers) { options.headers = {}; }
    // Add CSRF header for same-origin requests only; do NOT mutate body
    if (sameOrigin(resource)) {
      var token = getCsrf();
      if (token && !options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
        options.headers['X-CSRF-Token'] = token;
      }
      // For FormData, avoid forcing Content-Type; let the browser set it
      if (options.body instanceof FormData) {
        try {
          if (options.headers instanceof Headers) { options.headers.delete('Content-Type'); }
          else { delete options.headers['Content-Type']; delete options.headers['content-type']; }
        } catch(e) {}
      }
    }
    return originalFetch(resource, options);
  };
} catch(e) { /* swallow bootstrap errors */ }
})();

(function(){
  function bindAddJobsForm(){
      var form = document.getElementById('addJobsForm');
      if(!form) return;
      form.addEventListener('submit', function(e){
          e.preventDefault();
          var fd = new FormData(form);

          // Loading state on submit button
          var submitBtn = form.querySelector('button[type="submit"], .modal-btn.confirm');
          var originalHtml = submitBtn ? submitBtn.innerHTML : '';
          var originalDisabled = submitBtn ? submitBtn.disabled : false;
          if (submitBtn) {
              submitBtn.disabled = true;
              submitBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.6);border-top-color:#fff;border-radius:50%;margin-right:8px;vertical-align:-2px;animation:spin .8s linear infinite;"></span>Adding...';
          }
          // Optionally disable form fields to prevent edits during submit
          var disabledEls = [];
          try {
              disabledEls = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
              disabledEls.forEach(function(el){ el.disabled = true; });
          } catch(_e){}

          fetch('apis/jobs_handler.php', {method:'POST', body: fd, credentials:'same-origin'})
              .then(function(r){ return r.json(); })
              .then(function(j){
                  if(!j || j.success !== true){ alert((j && j.message) || 'Failed to add job'); return; }
                  closeAddJobsModal();
                  form.reset();
                  loadJobs(); // This will refresh jobs and update dropdowns
              })
              .catch(function(){ alert('Network error'); })
              .finally(function(){
                  // Restore button and inputs
                  if (submitBtn) {
                      submitBtn.disabled = originalDisabled;
                      submitBtn.innerHTML = originalHtml;
                  }
                  disabledEls.forEach(function(el){ try { el.disabled = false; } catch(_e){} });
              });
      });
      var cancelBtn = document.getElementById('cancelAddJobs');
      if(cancelBtn){ cancelBtn.addEventListener('click', function(){ closeAddJobsModal(); }); }
      var addBtn = document.getElementById('addJobsBtn');
      if(addBtn){ addBtn.addEventListener('click', function(){ openAddJobsModal(); }); }
  }

  function openAddJobsModal(){
      var m = document.getElementById('addJobsModal'); if(m) m.style.display = 'block';
  }
  function closeAddJobsModal(){
      var m = document.getElementById('addJobsModal'); if(m) m.style.display = 'none';
  }

  function renderJobs(jobs){
      var grid = document.querySelector('#job-matching .job-cards-grid');
      if(!grid) return;
      grid.innerHTML = jobs.map(function(job){
          return (
              '<div class="job-card" data-industry="' + escapeHtml(job.industry || '') + '" data-location="' + escapeHtml(job.location || '') + '" data-experience="' + escapeHtml(job.experience || '') + '">'
            + '  <div class="job-header">'
            + '    <h3 class="job-title">'+ escapeHtml(job.title) +'</h3>'
            + '    <div class="job-actions">'
            + '      <button class="edit-btn" data-id="'+ String(job.id) +'">'
            + '        <i class="fas fa-edit"></i>'
            + '      </button>'
            + '      <button class="delete-btn" data-id="'+ String(job.id) +'">'
            + '        <i class="fas fa-trash"></i>'
            + '      </button>'
            + '    </div>'
            + '  </div>'
            + '  <div class="job-details">'
            + (job.course ? ('    <p><strong>Course:</strong> ' + escapeHtml(job.course) + '</p>') : '')
            + '    <p><strong>Company:</strong> ' + escapeHtml(job.company) + '</p>'
            + '    <div class="job-info">'
            + '      <div class="job-info-item"><i class="fas fa-map-marker-alt"></i><span>' + escapeHtml(job.location ? job.location.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) : '—') + '</span></div>'
            + '      <div class="job-info-item"><i class="fas fa-dollar-sign"></i><span>' + escapeHtml(job.salary || '—') + '</span></div>'
            + '      <div class="job-info-item"><i class="fas fa-clock"></i><span>' + escapeHtml(job.experience || '—') + '</span></div>'
            + '    </div>'
            + '    <p class="job-description">' + escapeHtml(job.description || '') + '</p>'
            + '  </div>'
            + '</div>'
          );
      }).join('');
      
      // Attach edit listeners to the newly created job cards
      // Use a small delay to ensure editJobManager is initialized
      setTimeout(function() {
          if (typeof editJobManager !== 'undefined' && editJobManager && editJobManager.attachEditListeners) {
              editJobManager.attachEditListeners();
          } else if (typeof window.attachEditListener === 'function') {
              // Fallback: attach listeners individually
              var editButtons = grid.querySelectorAll('.edit-btn');
              editButtons.forEach(function(button) {
                  window.attachEditListener(button);
              });
          }
      }, 100);
  }

  function bindDeleteJobs(){
      var grid = document.querySelector('#job-matching .job-cards-grid');
      if(!grid) return;
      grid.addEventListener('click', function(e){
          var btn = e.target && (e.target.closest ? e.target.closest('.delete-btn') : null);
          if(!btn) return;
          var id = btn.getAttribute('data-id');
          if(!id) return;
          
          // Use custom modal instead of default confirm dialog
          var deleteJobModal = document.getElementById('deleteJobModal');
          var confirmDeleteJobBtn = document.getElementById('confirmDeleteJobBtn');
          var cancelDeleteJobBtn = document.getElementById('cancelDeleteJobBtn');
          
          if(deleteJobModal) {
              deleteJobModal.style.display = 'flex';
              
              // Set up one-time event listeners for this deletion
              var handleConfirm = function() {
                  var fd = new URLSearchParams();
                  fd.append('act', 'delete_job');
                  fd.append('id', id);
                  fetch('apis/jobs_handler.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: fd.toString() })
                      .then(function(r){ return r.json(); })
                      .then(function(j){
                          if(!j || j.success !== true){ 
                              alert((j && j.message) || 'Failed to delete job'); 
                              return; 
                          }
                          loadJobs(); // This will refresh jobs and update dropdowns
                      })
                      .catch(function(){ alert('Network error'); })
                      .finally(function(){
                          deleteJobModal.style.display = 'none';
                          // Clean up event listeners
                          confirmDeleteJobBtn.removeEventListener('click', handleConfirm);
                          cancelDeleteJobBtn.removeEventListener('click', handleCancel);
                      });
              };
              
              var handleCancel = function() {
                  deleteJobModal.style.display = 'none';
                  // Clean up event listeners
                  confirmDeleteJobBtn.removeEventListener('click', handleConfirm);
                  cancelDeleteJobBtn.removeEventListener('click', handleCancel);
              };
              
              // Remove any existing listeners to prevent duplicates
              confirmDeleteJobBtn.removeEventListener('click', handleConfirm);
              cancelDeleteJobBtn.removeEventListener('click', handleCancel);
              
              // Add new listeners
              confirmDeleteJobBtn.addEventListener('click', handleConfirm);
              cancelDeleteJobBtn.addEventListener('click', handleCancel);
          }
      });
  }

  function escapeHtml(s){
      s = String(s==null?'':s);
      return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
  }

  // Live data container for jobs (no sample fallback)
  var allJobs = [];

  function loadJobs(){
      // Load from API only
      fetch('apis/jobs_handler.php', {credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(j){ 
              if(j && j.success && Array.isArray(j.data)) { allJobs = j.data; }
              renderJobs(allJobs);
              updateJobCount();
              populateFilterDropdowns();
          })
          .catch(function(){
              // On error, show empty (no placeholder hard-codes)
              allJobs = [];
              renderJobs(allJobs);
              updateJobCount();
              populateFilterDropdowns();
          });
  }

  function updateJobCount(){
      const jobCountElement = document.querySelector('#job-matching .job-count')
      if (jobCountElement) {
          const totalJobs = allJobs.length
          jobCountElement.textContent = `${totalJobs} job${totalJobs !== 1 ? 's' : ''} available`
      }
  }

  function populateFilterDropdowns() {
      // Extract unique values from job data
      const locations = [...new Set(allJobs.map(job => job.location).filter(Boolean))]
      const experiences = [...new Set(allJobs.map(job => job.experience).filter(Boolean))]

      // Populate Location dropdown
      const locationFilter = document.getElementById('locationFilter')
      if (locationFilter) {
          const currentValue = locationFilter.value
          locationFilter.innerHTML = '<option value="">All Locations</option>'
          
          locations.sort().forEach(location => {
              const option = document.createElement('option')
              option.value = location
              option.textContent = location.split('-').map(word => 
                  word.charAt(0).toUpperCase() + word.slice(1)
              ).join(' ')
              locationFilter.appendChild(option)
          })
          
          // Restore previous selection if it still exists
          if (currentValue && locations.includes(currentValue)) {
              locationFilter.value = currentValue
          }
      }

      // Populate Experience dropdown
      const experienceFilter = document.getElementById('experienceFilter')
      if (experienceFilter) {
          const currentValue = experienceFilter.value
          experienceFilter.innerHTML = '<option value="">All Experience Levels</option>'
          
          // Sort experiences in logical order
          const experienceOrder = ['entry', '1-2', '3-5', '5+']
          const sortedExperiences = experiences.sort((a, b) => {
              const aIndex = experienceOrder.indexOf(a)
              const bIndex = experienceOrder.indexOf(b)
              if (aIndex === -1 && bIndex === -1) return a.localeCompare(b)
              if (aIndex === -1) return 1
              if (bIndex === -1) return -1
              return aIndex - bIndex
          })
          
          sortedExperiences.forEach(experience => {
              const option = document.createElement('option')
              option.value = experience
              
              // Format experience text
              let displayText = experience
              if (experience === 'entry') displayText = 'Entry Level'
              else if (experience === '1-2') displayText = '1-2 years'
              else if (experience === '3-5') displayText = '3-5 years'
              else if (experience === '5+') displayText = '5+ years'
              
              option.textContent = displayText
              experienceFilter.appendChild(option)
          })
          
          // Restore previous selection if it still exists
          if (currentValue && experiences.includes(currentValue)) {
              experienceFilter.value = currentValue
          }
      }

      console.log('Filter dropdowns populated with:', {
          locations: locations.length,
          experiences: experiences.length
      })
  }

  if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', function(){ bindAddJobsForm(); bindDeleteJobs(); loadJobs(); });
  } else { bindAddJobsForm(); bindDeleteJobs(); loadJobs(); }
})();

// --- Modal and Dashboard Logic ---
document.addEventListener("DOMContentLoaded", () => {

// Make sure the function is available globally
window.editTrainee = editTrainee;
// Removed exports for recently added trainees UI

console.log('Trainee edit functionality loaded - exact match to announcements');

// Logout Modal Logic
const logoutBtn = document.getElementById("logoutBtn");
const logoutModal = document.getElementById("logoutModal");
const cancelLogout = document.getElementById("cancelLogout");
if (logoutBtn && logoutModal && cancelLogout) {
  logoutBtn.addEventListener("click", () => {
    logoutModal.style.display = "flex";
    const modalContent = logoutModal.querySelector(".modal-content");
    if (modalContent) {
      modalContent.classList.remove("popOut");
      modalContent.style.animation = "scaleIn 0.25s";
    }
  });
  cancelLogout.addEventListener("click", () => {
    const modalContent = logoutModal.querySelector(".modal-content");
    if (modalContent) {
      modalContent.style.animation = "popOut 0.25s";
      modalContent.classList.add("popOut");
      modalContent.addEventListener(
        "animationend",
        function handler() {
          logoutModal.style.display = "none";
          modalContent.classList.remove("popOut");
          modalContent.removeEventListener("animationend", handler);
        }
      );
    } else {
      logoutModal.style.display = "none";
    }
  });
  // Hide modal on overlay click (optional)
  logoutModal.addEventListener("click", (e) => {
    if (e.target === logoutModal) {
      const modalContent = logoutModal.querySelector(".modal-content");
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s";
        modalContent.classList.add("popOut");
        modalContent.addEventListener(
          "animationend",
          function handler() {
            logoutModal.style.display = "none";
            modalContent.classList.remove("popOut");
            modalContent.removeEventListener("animationend", handler);
          }
        );
      } else {
        logoutModal.style.display = "none";
      }
    }
  });
}

// Add Jobs Modal Logic
const addJobsBtn = document.getElementById("addJobsBtn");
const addJobsModal = document.getElementById("addJobsModal");
const cancelAddJobs = document.getElementById("cancelAddJobs");
const addJobsForm = document.getElementById("addJobsForm");

// NC2 Validation Modal Logic
// Button to trigger NC2 modal
const validateNc2Btn = document.getElementById("validateNc2Btn");
// Modal elements for NC2 validation
const nc2ValidationModal = document.getElementById("nc2ValidationModal");
const closeNc2Validation = document.getElementById("closeNc2Validation");
const nc2RequestsList = document.getElementById("nc2RequestsList");
const viewNc2History = document.getElementById("viewNc2History");
const viewNc2Pending = document.getElementById("viewNc2Pending");
const nc2ModalHeadingText = document.getElementById("nc2ModalHeadingText");

if (addJobsBtn && addJobsModal && cancelAddJobs && addJobsForm) {
  // Show modal when Add Jobs button is clicked
  addJobsBtn.addEventListener("click", () => {
    addJobsModal.style.display = "flex";
    const modalContent = addJobsModal.querySelector(".modal-content");
    if (modalContent) {
      modalContent.classList.remove("popOut");
      modalContent.style.animation = "scaleIn 0.25s";
    }
  });

  // Hide modal when Cancel button is clicked
  cancelAddJobs.addEventListener("click", () => {
    const modalContent = addJobsModal.querySelector(".modal-content");
    if (modalContent) {
      modalContent.style.animation = "popOut 0.25s";
      modalContent.classList.add("popOut");
      modalContent.addEventListener(
        "animationend",
        function handler() {
          addJobsModal.style.display = "none";
          modalContent.classList.remove("popOut");
          modalContent.removeEventListener("animationend", handler);
        }
      );
    } else {
      addJobsModal.style.display = "none";
    }
  });

  // Hide modal on overlay click
  addJobsModal.addEventListener("click", (e) => {
    if (e.target === addJobsModal) {
      const modalContent = addJobsModal.querySelector(".modal-content");
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s";
        modalContent.classList.add("popOut");
        modalContent.addEventListener(
          "animationend",
          function handler() {
            addJobsModal.style.display = "none";
            modalContent.classList.remove("popOut");
            modalContent.removeEventListener("animationend", handler);
          }
        );
      } else {
        addJobsModal.style.display = "none";
      }
    }
  });

// Add Jobs Form Submission (Replace the existing one in your script.js)
  addJobsForm.addEventListener("submit", function (e) {
    e.preventDefault();
    // Handled by the primary submit handler at the top of this file
    // Avoid duplicating DOM-only job card creation which doesn't persist to DB
    return;
  });
}

// Open/Close NC2 Validation Modal
if (validateNc2Btn && nc2ValidationModal) {
  // Show modal and load pending requests
  validateNc2Btn.addEventListener("click", () => {
    nc2ValidationModal.style.display = "flex";
    const modalContent = nc2ValidationModal.querySelector(".modal-content");
    if (modalContent) {
      modalContent.classList.remove("popOut");
      modalContent.style.animation = "scaleIn 0.25s";
    }
    // Load pending NC2 requests whenever modal opens
    loadPendingNc2Requests();
  });

  // Close via button
  if (closeNc2Validation) {
    closeNc2Validation.addEventListener("click", () => closeNc2Modal());
  }

  // History button: always show history
  if (viewNc2History && nc2RequestsList) {
    viewNc2History.addEventListener("click", async () => {
      if (nc2ModalHeadingText) nc2ModalHeadingText.textContent = 'History';
      nc2RequestsList.setAttribute('data-view-history', '1');
      nc2RequestsList.innerHTML = '<div style="padding:16px; color: var(--muted-foreground);">Loading history…</div>';

      try {
        const res = await fetch('apis/nc2_validation.php?action=history', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json = await res.json();
        const items = Array.isArray(json?.data) ? json.data : [];

        if (!items.length) {
          nc2RequestsList.innerHTML = '<div style="padding:16px; color: var(--muted-foreground);">No history found.</div>';
          return;
        }

        const rows = items.map((r) => {
          const applicant = escapeHtml(r.student_name || r.applicant_name || r.name || 'Student');
          const job = escapeHtml(r.course || r.job_title || r.job || 'NC2 Submission');
          const decidedAt = escapeHtml(r.confirmed_at || r.updated_at || r.created_at || '');
          const status = String(r.status || '').toLowerCase(); // 'approved' | 'rejected'
          const badgeColor = status === 'approved' || status === 'confirm' || status === 'confirmed' ? 'var(--green-600)' : 'var(--red-500)';
          const statusText = status === 'approved' || status === 'confirm' || status === 'confirmed' ? 'Approved' : 'Rejected';
          return `
            <div class="data-row" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border);">
              <div style=\"flex:2;min-width:180px;\">
                <div style=\"font-weight:600;\">${applicant}</div>
                <div style=\"font-size:.85rem;color:var(--muted-foreground);\">${job}</div>
              </div>
              <div style=\"flex:1;min-width:140px;color:var(--muted-foreground);\">${decidedAt}</div>
              <div style=\"display:flex;gap:.5rem;align-items:center;\">
                <span style=\"display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .5rem;border-radius:999px;border:1px solid var(--border);color:${badgeColor};\">\n                  <i class=\"fas ${statusText === 'Approved' ? 'fa-check' : 'fa-times'}\"></i>${statusText}\n                </span>
              </div>
            </div>`;
        }).join('');

        nc2RequestsList.innerHTML = rows;
      } catch (err) {
        console.error('Failed to load NC2 history', err);
        nc2RequestsList.innerHTML = '<div style="padding:16px; color:#ef4444;">Failed to load history. Please try again.</div>';
      }
    });
  }

  // Pending button: go back to confirm/reject list
  if (viewNc2Pending && nc2RequestsList) {
    viewNc2Pending.addEventListener("click", async () => {
      if (nc2ModalHeadingText) nc2ModalHeadingText.textContent = 'Confirm/Reject NC2 Validation';
      nc2RequestsList.removeAttribute('data-view-history');
      await loadPendingNc2Requests();
    });
  }

  // Close on overlay click
  nc2ValidationModal.addEventListener("click", (e) => {
    if (e.target === nc2ValidationModal) {
      closeNc2Modal();
    }
  });
}

// Helper: close NC2 modal with animation
function closeNc2Modal() {
  if (!nc2ValidationModal) return;
  const modalContent = nc2ValidationModal.querySelector(".modal-content");
  if (modalContent) {
    modalContent.style.animation = "popOut 0.25s";
    modalContent.classList.add("popOut");
    modalContent.addEventListener(
      "animationend",
      function handler() {
        nc2ValidationModal.style.display = "none";
        modalContent.classList.remove("popOut");
        modalContent.removeEventListener("animationend", handler);
      }
    );
  } else {
    nc2ValidationModal.style.display = "none";
  }
}

// Load pending NC2 requests from server and render list
async function loadPendingNc2Requests() {
  if (!nc2RequestsList) return;
  // Initial state while loading
  nc2RequestsList.innerHTML = '<div style="padding:16px; color: var(--muted-foreground);">Loading pending requests…</div>';

  try {
    // Load pending NC2 requests
    const res = await fetch('apis/nc2_validation.php?action=pending', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const json = await res.json();
    const requests = Array.isArray(json?.data) ? json.data : (Array.isArray(json?.requests) ? json.requests : []);

    if (!requests.length) {
      nc2RequestsList.innerHTML = '<div style="padding:16px; color: var(--muted-foreground);">No pending NC2 validation requests.</div>';
      return;
    }

    // Render requests as rows with Confirm/Reject controls
    const rows = requests.map((r) => {
      const id = String(r.id ?? '');
      const applicant = escapeHtml(r.student_name || r.applicant_name || r.name || 'Student');
      const job = escapeHtml(r.course || r.job_title || r.job || 'NC2 Submission');
      const submitted = escapeHtml(r.submitted_at || r.created_at || '');
      const link = escapeHtml(r.nc2_link || '');
      return `
        <div class="data-row" data-request-id="${id}" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border);">
          <div style="flex:2;min-width:180px;">
            <div style="font-weight:600;">${applicant}</div>
            <div style="font-size:.85rem;color:var(--muted-foreground);">${job}</div>
            ${link ? `<div style=\"font-size:.8rem;word-break:break-all;margin-top:4px;\"><a href=\"${link}\" target=\"_blank\" rel=\"noopener\">View NC2 Link</a></div>` : ''}
          </div>
          <div style="flex:1;min-width:140px;color:var(--muted-foreground);">${submitted}</div>
          <div style="display:flex;gap:.5rem;">
            <button class="modal-btn confirm" data-action="confirm" data-id="${id}"><i class="fas fa-check" style="margin-right:.35rem;"></i>Confirm</button>
            <button class="modal-btn cancel" data-action="reject" data-id="${id}"><i class="fas fa-times" style="margin-right:.35rem;"></i>Reject</button>
          </div>
        </div>`;
    }).join('');

    nc2RequestsList.innerHTML = rows;

    // Attach action handlers
    nc2RequestsList.querySelectorAll('button[data-action]')?.forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        const action = btn.getAttribute('data-action');
        const reqId = btn.getAttribute('data-id');
        if (!action || !reqId) return;
        await handleNc2Action(action, reqId);
      });
    });
  } catch (err) {
    console.error('Failed to load pending NC2 requests', err);
    nc2RequestsList.innerHTML = '<div style="padding:16px; color:#ef4444;">Failed to load requests. Please try again.</div>';
  }
}

// Handle Confirm/Reject actions
async function handleNc2Action(action, requestId) {
  try {
    const csrfToken = getCSRFToken ? getCSRFToken() : '';
    const params = new URLSearchParams();
    params.set('action', action === 'confirm' ? 'confirm' : 'reject');
    params.set('id', String(requestId));
    if (csrfToken) params.set('csrf_token', csrfToken);

    const headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch('apis/nc2_validation.php', {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: params.toString(),
    });
    const json = await res.json();
    if (json?.success) {
      // Remove row from UI
      const row = nc2RequestsList?.querySelector(`[data-request-id="${CSS.escape(String(requestId))}"]`);
      if (row) row.remove();
      // Optional: show toast
      if (typeof showNotificationToast === 'function') {
        showNotificationToast('Request ' + (action === 'confirm' ? 'confirmed' : 'rejected') + ' successfully', 'success');
      }
      // If list empty after action, show empty state
      if (!nc2RequestsList?.querySelector('[data-request-id]')) {
        nc2RequestsList.innerHTML = '<div style="padding:16px; color: var(--muted-foreground);">No pending NC2 validation requests.</div>';
      }
    } else {
      throw new Error(json?.message || 'Operation failed');
    }
  } catch (err) {
    console.error('NC2 action failed', err);
    if (typeof showNotificationToast === 'function') {
      showNotificationToast('Failed to ' + action + ' request', 'error');
    }
  }
}

// --- End Modal and Dashboard Logic ---
});

document.addEventListener("DOMContentLoaded", () => {
// Global state
let activeSection = "dashboard"
let sidebarCollapsed = false // This will now primarily control desktop collapsed state
let sidebarMobileOpen = false // New state for mobile sidebar visibility
let notificationOpen = false

// Initialize dashboard
const initializeDashboard = () => {
  initializeSidebar()
  initializeNavigation()
  initializeNotifications()
  initializeTabs()
  initializeFilters()
  initializeTheme()
  initializeLogout() // Initialize logout button
  initializeCareerAnalyticsAdmin()

  // Show initial section
  showSection("dashboard")
}

// Sidebar functionality
function initializeSidebar() {
  const sidebarToggleDesktop = document.getElementById("sidebarToggle") // Footer toggle (desktop)
  const sidebarToggleMobile = document.getElementById("mobileSidebarToggle") // Header toggle (mobile)
  const sidebar = document.getElementById("sidebar")
  const mainContent = document.getElementById("mainContent")

  const applySidebarState = () => {
    if (window.innerWidth <= 768) {
      // Mobile view
      if (sidebarMobileOpen) {
        sidebar?.classList.add("show") // Show mobile sidebar
        if (mainContent) mainContent.style.marginLeft = "0" // Main content full width
      } else {
        sidebar?.classList.remove("show") // Hide mobile sidebar
        if (mainContent) mainContent.style.marginLeft = "0" // Main content full width
      }
      sidebar?.classList.remove("collapsed") // Ensure desktop collapsed class is off
    } else {
      // Desktop/Tablet view
      sidebar?.classList.remove("show") // Ensure mobile show class is off
      if (sidebarCollapsed) {
        sidebar?.classList.add("collapsed") // Apply desktop collapsed state
        if (mainContent) mainContent.style.marginLeft = "5rem"
      } else {
        sidebar?.classList.remove("collapsed") // Apply desktop expanded state
        if (mainContent) mainContent.style.marginLeft = "16rem"
      }
    }
  }

  const toggleDesktopSidebar = () => {
    sidebarCollapsed = !sidebarCollapsed
    applySidebarState()
  }

  const toggleMobileSidebar = () => {
    sidebarMobileOpen = !sidebarMobileOpen
    applySidebarState()
  }

  sidebarToggleDesktop?.addEventListener("click", toggleDesktopSidebar)
  sidebarToggleMobile?.addEventListener("click", toggleMobileSidebar)

  // Handle initial load and resize
  const handleResize = () => {
    // Reset states based on current screen size
    if (window.innerWidth <= 768) {
      // On mobile, default to hidden sidebar
      sidebarMobileOpen = false
      sidebarCollapsed = false // Desktop collapsed state doesn't apply
    } else {
      // On desktop/tablet, default to expanded sidebar (or read from cookie if implemented)
      sidebarCollapsed = false // Default to expanded
      sidebarMobileOpen = false // Mobile state doesn't apply
    }
    applySidebarState()
  }

  window.addEventListener("resize", handleResize)
  handleResize() // Initial call

  // Cleanup (for a single-page app, this would be important if components were dynamically added/removed)
  // For a full page load, these listeners persist until the page is navigated away from.
}

// Navigation functionality
function initializeNavigation() {
  const navItems = document.querySelectorAll(".nav-item")

  navItems.forEach((item) => {
    item.addEventListener("click", function () {
      const section = this.dataset.section
      if (section) {
        showSection(section)
        updateActiveNav(section)
        // Close mobile sidebar after navigation
        if (window.innerWidth <= 768) {
          const sidebar = document.getElementById("sidebar")
          sidebar?.classList.remove("show")
          sidebarMobileOpen = false // Update state
        }
      }
    })
  })
}

function showSection(sectionName) {
  // Hide all sections
  document.querySelectorAll(".page-section").forEach((section) => {
    section.classList.remove("active")
  })

  // Show active section
  const activeSectionElement = document.getElementById(sectionName)
  if (activeSectionElement) {
    activeSectionElement.classList.add("active")
    activeSectionElement.style.animationDelay = "0ms"
  }

  // Update global state
  activeSection = sectionName // Update local variable

  // Update header title to reflect current section
  updateHeaderTitle(sectionName)
  
  // Initialize section-specific functionality
  if (sectionName === 'graduates') {
    // Populate graduate course filter when graduates section is shown
    populateGraduateCourseFilterFromAPI();
  }
  
  if (sectionName === 'add-trainees') {
    // Refresh trainee course dropdown when Add Trainees section is accessed
    console.log('Add Trainees section accessed, refreshing course dropdown...');
    loadCoursesFromDatabase();
  }
}

function updateActiveNav(sectionName) {
  // Remove active class from all nav items
  document.querySelectorAll(".nav-item").forEach((item) => {
    item.classList.remove("active")
  })

  // Add active class to current nav item
  const activeNavItem = document.querySelector(`[data-section="${sectionName}"]`)
  if (activeNavItem) {
    activeNavItem.classList.add("active")
  }
}

// Update the main header title based on the active section
function updateHeaderTitle(sectionName) {
  const headerTitleEl = document.querySelector('.main-header .main-title')
  if (!headerTitleEl) return

  // Prefer the nav label text (e.g., "Dashboard", "Career Analytics")
  const navLabel = document.querySelector(`.nav-item[data-section="${sectionName}"] .nav-text`)
  const navText = navLabel ? navLabel.textContent.trim() : ''

  if (navText) {
    headerTitleEl.textContent = navText
    return
  }

  // Fallback to the section's visible title if available
  const sectionEl = document.getElementById(sectionName)
  const sectionTitleEl = sectionEl ? sectionEl.querySelector('.section-title') : null
  const sectionText = sectionTitleEl ? sectionTitleEl.textContent.trim() : ''
  headerTitleEl.textContent = sectionText || 'MMTVTC Admin Dashboard'
}

// Notification functionality
function initializeNotifications() {
  const notificationBell = document.getElementById("notificationBell")
  const notificationDropdown = document.getElementById("notificationDropdown")
  const notificationClose = document.getElementById("notificationClose")

  function showDropdown() {
    if (!notificationDropdown) return;
    notificationDropdown.classList.remove("hide");
    notificationDropdown.classList.add("show");
    notificationDropdown.style.display = "block";
    notificationBell?.classList.add("active");
  }

  function hideDropdown() {
    if (!notificationDropdown) return;
    notificationDropdown.classList.remove("show");
    notificationDropdown.classList.add("hide");
    notificationBell?.classList.remove("active");
    // Wait for animation to finish before hiding
    function onAnimEnd() {
      notificationDropdown.classList.remove("hide");
      notificationDropdown.style.display = "none";
      notificationDropdown.removeEventListener("animationend", onAnimEnd);
    }
    notificationDropdown.addEventListener("animationend", onAnimEnd);
  }

  notificationBell?.addEventListener("click", () => {
    notificationOpen = !notificationOpen;
    if (notificationOpen) {
      showDropdown();
    } else {
      hideDropdown();
    }
  });

  notificationClose?.addEventListener("click", () => {
    notificationOpen = false;
    hideDropdown();
  });

  // Close notifications when clicking outside
  document.addEventListener("click", (event) => {
    if (!event.target.closest(".notification-container")) {
      if (notificationOpen) {
        notificationOpen = false;
        hideDropdown();
      }
    }
  });
}

// Tabs functionality
function initializeTabs() {
  const tabs = document.querySelectorAll(".tab")

  tabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      const tabName = this.dataset.tab

      // Remove active class from all tabs
      tabs.forEach((t) => t.classList.remove("active"))

      // Add active class to clicked tab
      this.classList.add("active")

      // Here you could add functionality to show different tab content
      console.log(`Switched to ${tabName} tab`)
    })
  })
}

// Filters functionality
function initializeFilters() {
  const clearFiltersBtn = document.querySelector(".clear-filters-btn")
  const filterSelects = document.querySelectorAll(".filter-select")

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", () => {
      filterSelects.forEach((select) => {
        select.value = ""
      })
      console.log("Filters cleared")
      
      // Show all job cards when filters are cleared
      const jobCards = document.querySelectorAll('#job-matching .job-cards-grid .job-card')
      jobCards.forEach(card => {
        card.style.display = 'block'
      })
      
      // Remove no results message if it exists
      const grid = document.querySelector('#job-matching .job-cards-grid')
      const noResultsMsg = grid.querySelector('.no-results-message')
      if (noResultsMsg) {
        noResultsMsg.remove()
      }
      
      // Update job count
      const jobCountElement = document.querySelector('#job-matching .job-count')
      if (jobCountElement) {
        jobCountElement.textContent = `${jobCards.length} job${jobCards.length !== 1 ? 's' : ''} found`
      }
    })
  }

  // Add filter change listeners with debouncing
  let filterTimeout
  filterSelects.forEach((select) => {
    select.addEventListener("change", () => {
      clearTimeout(filterTimeout)
      filterTimeout = setTimeout(() => {
        applyFilters()
      }, 100) // Small delay to prevent excessive filtering
    })
  })
}

function applyFilters() {
  const filters = {}
  document.querySelectorAll(".filter-select").forEach((select) => {
    if (select.value) {
      filters[select.name || "filter"] = select.value
    }
  })

  console.log("Applying filters:", filters)

  // Filter job cards based on selected filters
  const jobCards = document.querySelectorAll('#job-matching .job-cards-grid .job-card')
  let visibleCount = 0

  jobCards.forEach(card => {
    let shouldShow = true

    // Check location filter
    if (filters.location && shouldShow) {
      const cardLocation = card.getAttribute('data-location')
      if (cardLocation !== filters.location) {
        shouldShow = false
      }
    }

    // Check experience filter
    if (filters.experience && shouldShow) {
      const cardExperience = card.getAttribute('data-experience')
      if (cardExperience !== filters.experience) {
        shouldShow = false
      }
    }

    // Show/hide the card
    if (shouldShow) {
      card.style.display = 'block'
      visibleCount++
    } else {
      card.style.display = 'none'
    }
  })

  // Update job count display if it exists
  const jobCountElement = document.querySelector('#job-matching .job-count')
  if (jobCountElement) {
    jobCountElement.textContent = `${visibleCount} job${visibleCount !== 1 ? 's' : ''} found`
  }

  // Show "no results" message if no jobs match
  const grid = document.querySelector('#job-matching .job-cards-grid')
  let noResultsMsg = grid.querySelector('.no-results-message')
  
  if (visibleCount === 0) {
    if (!noResultsMsg) {
      noResultsMsg = document.createElement('div')
      noResultsMsg.className = 'no-results-message'
      noResultsMsg.style.cssText = `
        grid-column: 1 / -1;
        text-align: center;
        padding: 2rem;
        color: #666;
        font-size: 1.1rem;
      `
      noResultsMsg.innerHTML = `
        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
        <p>No jobs found matching your filters.</p>
        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Try adjusting your search criteria.</p>
      `
      grid.appendChild(noResultsMsg)
    }
  } else if (noResultsMsg) {
    noResultsMsg.remove()
  }
}

// Theme functionality
function initializeTheme() {
  const themeToggle = document.getElementById("themeToggle")
  // Initialize based on current attribute or class
  let isDarkMode = (document.body.getAttribute("data-theme") === "dark") || document.body.classList.contains("dark-theme")

  // Sync initial state to ensure CSS variables apply globally
  if (isDarkMode) {
    document.body.setAttribute("data-theme", "dark")
    document.body.classList.add("dark-theme")
  } else {
    document.body.removeAttribute("data-theme")
    document.body.classList.remove("dark-theme")
  }

  themeToggle?.addEventListener("click", function () {
    isDarkMode = !isDarkMode

    const icon = this.querySelector("i")
    if (icon) {
      // Add spin animation
      icon.classList.add("theme-icon-spin")

      // Change icon after half the spin for a smooth transition
      setTimeout(() => {
        if (isDarkMode) {
          icon.className = "fas fa-sun theme-icon-spin"
          document.body.setAttribute("data-theme", "dark")
          document.body.classList.add("dark-theme")
        } else {
          icon.className = "fas fa-moon theme-icon-spin"
          document.body.removeAttribute("data-theme")
          document.body.classList.remove("dark-theme")
        }
      }, 350)

      // Remove spin animation after it completes
      setTimeout(() => {
        icon.classList.remove("theme-icon-spin")
      }, 700) // Match animation duration
    } else {
      // Fallback: toggle immediately if icon not found
      if (isDarkMode) {
        document.body.setAttribute("data-theme", "dark")
        document.body.classList.add("dark-theme")
      } else {
        document.body.removeAttribute("data-theme")
        document.body.classList.remove("dark-theme")
      }
    }

    console.log(`Theme switched to ${isDarkMode ? "dark" : "light"} mode`)
  })
}

// Logout functionality
function initializeLogout() {
  // No JS needed; handled by modal in HTML
  // (Removed all alert or popup for logout)
}

// Admin Career Analytics moved to graduates_charts.js

// Industry charts moved to industry_charts.js

// Search functionality
function initializeSearch() {
  const searchInputs = document.querySelectorAll(".search-input")

  searchInputs.forEach((input) => {
    input.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase()
      console.log("Searching for:", searchTerm)

      // Here you would implement the actual search logic
      // For example, filtering table rows or cards
    })
  })
}

// Enhanced Job card actions with Edit functionality
let currentJobCardBeingEdited = null; // Track which job card is being edited
let originalJobData = {}; // Store original data for comparison

// Initialize edit job functionality
function initializeEditJobFunctionality() {
  const editJobModal = document.getElementById("editJobModal");
  const cancelEditJob = document.getElementById("cancelEditJob");
  const editJobForm = document.getElementById("editJobForm");

  // Function to extract job data from a job card
  function extractJobData(jobCard) {
    const jobTitle = jobCard.querySelector(".job-title")?.textContent || "";
    const companyName = jobCard.querySelector(".job-details p strong")?.parentNode?.textContent?.replace("Company:", "").trim() || "";
    const location = jobCard.querySelector(".job-info-item .fa-map-marker-alt")?.parentNode?.querySelector("span")?.textContent || "";
    const salary = jobCard.querySelector(".job-info-item .fa-dollar-sign")?.parentNode?.querySelector("span")?.textContent || "";
    const experience = jobCard.querySelector(".job-info-item .fa-clock")?.parentNode?.querySelector("span")?.textContent || "";
    const description = jobCard.querySelector(".job-description")?.textContent || "";

    return {
      jobTitle,
      companyName,
      location,
      salary,
      experience,
      description
    };
  }

  // Function to populate edit form with current data
  function populateEditForm(jobData) {
    document.getElementById("editJobTitle").value = jobData.jobTitle;
    document.getElementById("editCompanyName").value = jobData.companyName;
    document.getElementById("editLocation").value = jobData.location;
    document.getElementById("editSalary").value = jobData.salary;
    document.getElementById("editExperience").value = jobData.experience;
    document.getElementById("editDescription").value = jobData.description;
  }

  // Function to update job card with new data
  function updateJobCard(jobCard, newData) {
    // Update job title
    const jobTitleElement = jobCard.querySelector(".job-title");
    if (jobTitleElement) jobTitleElement.textContent = newData.jobTitle;

    // Update company name
    const companyElement = jobCard.querySelector(".job-details p strong");
    if (companyElement && companyElement.parentNode) {
      companyElement.parentNode.innerHTML = `<strong>Company:</strong> ${newData.companyName}`;
    }

    // Update location
    const locationElement = jobCard.querySelector(".job-info-item .fa-map-marker-alt")?.parentNode?.querySelector("span");
    if (locationElement) locationElement.textContent = newData.location;

    // Update salary
    const salaryElement = jobCard.querySelector(".job-info-item .fa-dollar-sign")?.parentNode?.querySelector("span");
    if (salaryElement) salaryElement.textContent = newData.salary;

    // Update experience
    const experienceElement = jobCard.querySelector(".job-info-item .fa-clock")?.parentNode?.querySelector("span");
    if (experienceElement) experienceElement.textContent = newData.experience;

    // Update description
    const descriptionElement = jobCard.querySelector(".job-description");
    if (descriptionElement) descriptionElement.textContent = newData.description;

    // Add a brief highlight animation to show the card was updated
    jobCard.style.animation = "pulse 0.5s ease-out";
    setTimeout(() => {
      jobCard.style.animation = "";
    }, 500);
  }

  // Function to check if data has changed
  function hasDataChanged(originalData, newData) {
    return JSON.stringify(originalData) !== JSON.stringify(newData);
  }

  // Add event listeners to all existing edit buttons
  function attachEditListeners() {
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(button => {
      // Remove existing listeners to prevent duplicates
      button.removeEventListener("click", handleEditClick);
      button.addEventListener("click", handleEditClick);
    });
  }

  // Handle edit button click
  function handleEditClick(event) {
    const jobCard = event.target.closest(".job-card");
    if (!jobCard) return;

    currentJobCardBeingEdited = jobCard;
    originalJobData = extractJobData(jobCard);
    
    // Populate form with current data
    populateEditForm(originalJobData);
    
    // Show modal
    if (editJobModal) {
      editJobModal.style.display = "flex";
      const modalContent = editJobModal.querySelector(".modal-content");
      if (modalContent) {
        modalContent.classList.remove("popOut");
        modalContent.style.animation = "scaleIn 0.25s";
      }
    }
  }

  // Hide modal function
  function hideEditModal() {
    if (editJobModal) {
      const modalContent = editJobModal.querySelector(".modal-content");
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s";
        modalContent.classList.add("popOut");
        modalContent.addEventListener(
          "animationend",
          function handler() {
            editJobModal.style.display = "none";
            modalContent.classList.remove("popOut");
            modalContent.removeEventListener("animationend", handler);
          }
        );
      } else {
        editJobModal.style.display = "none";
      }
    }
    currentJobCardBeingEdited = null;
    originalJobData = {};
  }

  // Cancel button event listener
  if (cancelEditJob) {
    cancelEditJob.addEventListener("click", hideEditModal);
  }

  // Hide modal on overlay click
  if (editJobModal) {
    editJobModal.addEventListener("click", (e) => {
      if (e.target === editJobModal) {
        hideEditModal();
      }
    });
  }

  // Form submission handler
  if (editJobForm) {
    editJobForm.addEventListener("submit", function (e) {
      e.preventDefault();
      
      if (!currentJobCardBeingEdited) return;

      const formData = new FormData(editJobForm);
      const newData = {
        jobTitle: formData.get("jobTitle"),
        companyName: formData.get("companyName"),
        location: formData.get("location"),
        salary: formData.get("salary"),
        experience: formData.get("experience"),
        description: formData.get("description")
      };

      // Check if data has actually changed
      if (hasDataChanged(originalJobData, newData)) {
        // Get the job ID from the current job card
        const jobId = currentJobCardBeingEdited.querySelector('.edit-btn')?.getAttribute('data-id');
        
        if (!jobId) {
          console.error('Job ID not found');
          return;
        }

        // Prepare form data for API call
        const updateFormData = new FormData();
        updateFormData.append('action', 'update_job');
        updateFormData.append('id', jobId);
        updateFormData.append('jobTitle', newData.jobTitle);
        updateFormData.append('companyName', newData.companyName);
        updateFormData.append('location', newData.location);
        updateFormData.append('salary', newData.salary);
        updateFormData.append('experience', newData.experience);
        updateFormData.append('description', newData.description);

        // Send update request to database
        fetch('apis/jobs_handler.php', {
          method: 'POST',
          body: updateFormData,
          credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            // Update the job card with new data
            updateJobCard(currentJobCardBeingEdited, newData);
            console.log("Job updated successfully in database:", newData.jobTitle);
            
            // Show success message
            if (window.dashboardFunctions && window.dashboardFunctions.showToast) {
              window.dashboardFunctions.showToast("Job updated successfully!", "success");
            }
          } else {
            console.error('Failed to update job:', result.message);
            alert('Failed to update job: ' + (result.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error updating job:', error);
          alert('Network error while updating job');
        });
      } else {
        console.log("No changes detected, keeping original data");
      }

      // Hide modal
      hideEditModal();
    });
  }

  // Initialize listeners for existing buttons
  attachEditListeners();

  // Return function to attach listeners to new buttons (for dynamically created job cards)
  return {
    attachEditListeners,
    handleEditClick
  };
}

// Initialize the edit functionality
const editJobManager = initializeEditJobFunctionality();

// Make edit functionality available globally for new job cards
window.attachEditListener = function(editButton) {
  if (editButton && editJobManager) {
    editButton.addEventListener("click", editJobManager.handleEditClick);
  }
};

// Enhanced job actions with edit and delete functionality
function initializeJobActionsWithEdit() {
  const editBtns = document.querySelectorAll(".edit-btn");
  const deleteBtns = document.querySelectorAll(".delete-btn");
  const deleteJobModal = document.getElementById("deleteJobModal");
  const confirmDeleteJobBtn = document.getElementById("confirmDeleteJobBtn");
  const cancelDeleteJobBtn = document.getElementById("cancelDeleteJobBtn");
  let jobCardToDelete = null;

  // Edit buttons are now handled by the edit functionality above
  // Delete button functionality remains the same
  deleteBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      jobCardToDelete = this.closest(".job-card");
      if (deleteJobModal) {
        deleteJobModal.style.display = "flex";
      }
    });
  });

  if (cancelDeleteJobBtn && deleteJobModal) {
    cancelDeleteJobBtn.addEventListener("click", () => {
      deleteJobModal.style.display = "none";
      jobCardToDelete = null;
    });
  }

  if (confirmDeleteJobBtn && deleteJobModal) {
    confirmDeleteJobBtn.addEventListener("click", () => {
      if (jobCardToDelete) {
        jobCardToDelete.classList.add("deleting");
        jobCardToDelete.addEventListener("animationend", () => {
          jobCardToDelete.remove();
        });
        deleteJobModal.style.display = "none";
      }
    });
  }

  // Hide modal on overlay click
  if (deleteJobModal) {
    deleteJobModal.addEventListener("click", (e) => {
      if (e.target === deleteJobModal) {
        deleteJobModal.style.display = "none";
        jobCardToDelete = null;
      }
    });
  }
}

// Action buttons functionality
function initializeActionButtons() {
  const actionBtns = document.querySelectorAll(".action-btn")

  actionBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const btnText = this.textContent?.trim()
      console.log(`${btnText} clicked`)

      // Here you would implement specific actions
      // For example, opening modals, exporting data, etc.
    })
  })
}

// Utility functions
function animateValue(element, start, end, duration) {
  let startTimestamp = null
  const step = (timestamp) => {
    if (!startTimestamp) startTimestamp = timestamp
    const progress = Math.min((timestamp - startTimestamp) / duration, 1)
    const value = Math.floor(progress * (end - start) + start)
    element.textContent = value.toString()
    if (progress < 1) {
      window.requestAnimationFrame(step)
    }
  }
  window.requestAnimationFrame(step)
}

function showToast(message, type = "info") {
  const toast = document.createElement("div")
  toast.className = `toast toast-${type}`
  toast.textContent = message

  // Style the toast - positioned lower in header area
  toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        animation: slideIn 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    `

  // Set background color based on type
  switch (type) {
    case "success":
      toast.style.background = "var(--green-500)"
      break
    case "error":
      toast.style.background = "var(--destructive)"
      break
    case "warning":
      toast.style.background = "var(--orange-500)"
      break
    default:
      toast.style.background = "var(--blue-500)"
  }

  document.body.appendChild(toast)

  // Auto remove after 3 seconds
  setTimeout(() => {
    toast.style.animation = "slideOut 0.3s ease-out forwards"
    setTimeout(() => {
      document.body.removeChild(toast)
    }, 300)
  }, 3000)
}

// Add additional animation styles (already in styles.css, but keeping for context if needed)
const additionalStyles = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`

// Only append if not already present to avoid duplicates
if (!document.head.querySelector("style[data-v0-added]")) {
  const styleSheet = document.createElement("style")
  styleSheet.textContent = additionalStyles
  styleSheet.setAttribute("data-v0-added", "true") // Mark it as added by v0
  document.head.appendChild(styleSheet)
}

// Initialize additional features when DOM is loaded
const initializeAdditionalFeatures = () => {
  initializeSearch()
  initializeJobActionsWithEdit()
  initializeActionButtons()

  // Animate stat values on page load
  setTimeout(() => {
    const statValues = document.querySelectorAll(".stat-value")
    statValues.forEach((stat) => {
      const finalValue = Number.parseInt(stat.textContent || "0")
      animateValue(stat, 0, finalValue, 1000)
    })
  }, 500)
}

// Export functions for external use (if needed globally)
window.dashboardFunctions = {
  showSection,
  updateActiveNav,
  showToast,
  animateValue,
}

initializeDashboard()
initializeAdditionalFeatures()
})

// Announcement

document.addEventListener("DOMContentLoaded", () => {
// Character counter for announcement content
const announcementContent = document.getElementById("announcementContent");
const charCount = document.getElementById("charCount");

if (announcementContent && charCount) {
  announcementContent.addEventListener("input", function() {
    const currentLength = this.value.length;
    charCount.textContent = currentLength;
    
    // Change color based on character limit
    if (currentLength > 900) {
      charCount.style.color = "var(--destructive)";
    } else if (currentLength > 750) {
      charCount.style.color = "var(--orange-500)";
    } else {
      charCount.style.color = "var(--muted-foreground)";
    }
    
    // Prevent exceeding 1000 characters
    if (currentLength > 1000) {
      this.value = this.value.substring(0, 1000);
      charCount.textContent = "1000";
    }
  });
}

// SINGLE Form submission handler - FIXED VERSION
const announcementForm = document.getElementById("announcementForm");
if (announcementForm) {
  announcementForm.addEventListener("submit", function(e) {
    e.preventDefault();
    
    console.log('Form submitted');
    
    const formData = new FormData(this);
    formData.append('action', 'publish_announcement');
    

    const csrfToken = document.getElementById('csrf_token');
    if (csrfToken && csrfToken.value) {
      formData.append('csrf_token', csrfToken.value);
      console.log('CSRF token added');
    }
    
    // Log form data for debugging
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
      console.log(key + ': ' + value);
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
    submitBtn.disabled = true;
    
    fetch('apis/announcement_handler.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      console.log('Response status:', response.status, response.statusText);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
      }
      
      return response.text();
    })
    .then(text => {
      console.log('Raw response:', text);
      
      try {
        const data = JSON.parse(text);
        console.log('Parsed JSON response:', data);
        
        if (data.success) {
          // SINGLE SUCCESS NOTIFICATION
          showNotification('Announcement published successfully!', 'success');
          
          // Reset form
          announcementForm.reset();
          if (charCount) charCount.textContent = '0';
          
          // Clear saved draft
          localStorage.removeItem('announcementDraft');
          
          // Load recent announcements to show the new one
          loadRecentAnnouncements();
          
        } else {
          showNotification(data.message || 'Failed to publish announcement', 'error');
        }
      } catch (jsonError) {
        console.error('JSON parse error:', jsonError);
        console.error('Response was not valid JSON:', text);
        showNotification('Server returned invalid response. Check console for details.', 'error');
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      showNotification('Network error: ' + error.message, 'error');
    })
    .finally(() => {
      // ALWAYS reset button state
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    });
  });
}


// Save draft button handler
const saveDraftBtn = document.getElementById("saveDraftBtn");
if (saveDraftBtn) {
  saveDraftBtn.addEventListener("click", function() {
    const formData = new FormData(announcementForm);
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
      draftData[key] = value;
    }
    
    draftData.saved = new Date().toISOString();
    
    localStorage.setItem("announcementDraft", JSON.stringify(draftData));
    showNotification("Draft saved successfully", "success");
  });
}

loadSavedDraft();

loadRecentAnnouncements();
});

// SINGLE Notification function
function showNotification(message, type = 'info') {
// Remove any existing notifications first
const existingNotifications = document.querySelectorAll('.notification');
existingNotifications.forEach(notif => notif.remove());

// Create notification element
const notification = document.createElement('div');
notification.className = `notification notification-${type}`;
notification.style.cssText = `
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 5px;
  color: white;
  font-weight: bold;
  z-index: 9999;
  min-width: 300px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  animation: slideInRight 0.3s ease-out;
`;

// Set background color based on type
switch(type) {
  case 'success':
    notification.style.backgroundColor = '#4CAF50';
    break;
  case 'error':
    notification.style.backgroundColor = '#f44336';
    break;
  case 'warning':
    notification.style.backgroundColor = '#ff9800';
    break;
  default:
    notification.style.backgroundColor = '#2196F3';
}

notification.textContent = message;
document.body.appendChild(notification);

// Remove notification after 4 seconds
setTimeout(() => {
  if (notification.parentNode) {
    notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }
}, 4000);
}


function loadRecentAnnouncements() {
const formData = new FormData();
formData.append('action', 'get_announcements');


const csrfToken = document.getElementById('csrf_token');
if (csrfToken && csrfToken.value) {
  formData.append('csrf_token', csrfToken.value);
}

fetch('apis/announcement_handler.php', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(data => {
  if (data.success && data.announcements) {
    displayRecentAnnouncements(data.announcements, data.server_now);
  } else {
    console.error('Failed to load announcements:', data.message);
  }
})
.catch(error => {
  console.error('Error loading announcements:', error);
});
}


function displayRecentAnnouncements(announcements, serverNow) {
const announcementsList = document.querySelector(".announcements-list");
if (!announcementsList) return;

// Clear existing announcements
announcementsList.innerHTML = '';

if (announcements.length === 0) {
  announcementsList.innerHTML = '<p style="text-align: center; color: var(--muted-foreground); padding: 2rem;">No announcements yet.</p>';
  return;
}

// Limit to the three most recent announcements
const topThree = announcements.slice(0, 3);

topThree.forEach(announcement => {
  const card = createAnnouncementCard(announcement, serverNow);
  announcementsList.appendChild(card);
});
}

// Function to create announcement card
function createAnnouncementCard(announcement, serverNow) {
const card = document.createElement("div");
card.className = "announcement-card";
card.dataset.announcementId = announcement.id;

// Time display removed per request; retain parsing helpers for other uses

card.innerHTML = `
  <div class="announcement-header">
    <div class="announcement-meta">
      <span class="announcement-type ${announcement.type}">${announcement.type}</span>
      <span class="announcement-priority ${announcement.priority || 'normal'}">${announcement.priority || 'normal'}</span>
    </div>
    <div class="announcement-actions">
      <button class="edit-announcement-btn" title="Edit">
        <i class="fas fa-edit"></i>
      </button>
      <button class="delete-announcement-btn" title="Delete">
        <i class="fas fa-trash"></i>
      </button>
    </div>
  </div>
  <h4 class="announcement-title">${announcement.title}</h4>
  <p class="announcement-preview">${announcement.content.substring(0, 120)}${announcement.content.length > 120 ? '...' : ''}</p>
  <div class="announcement-stats">
    <span><i class="fas fa-eye"></i> 0 views</span>
    <span><i class="fas fa-users"></i> ${announcement.audience || 'all'}</span>
    <span class="announcement-status ${announcement.is_active ? 'active' : 'inactive'}">
      ${announcement.is_active ? 'Active' : 'Inactive'}
    </span>
  </div>
`;

// Add event listeners
const editBtn = card.querySelector(".edit-announcement-btn");
const deleteBtn = card.querySelector(".delete-announcement-btn");

editBtn.addEventListener("click", () => editAnnouncement(announcement));
deleteBtn.addEventListener("click", () => deleteAnnouncement(announcement.id, card));

return card;
}

// Function to format time ago
function formatTimeAgo(date, nowRef) {
const now = nowRef instanceof Date ? nowRef : new Date();
const diffInSeconds = Math.floor((now - date) / 1000);

if (diffInSeconds < 60) return 'Just now';
if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
return `${Math.floor(diffInSeconds / 86400)} days ago`;
}

// Parse a MySQL DATETIME string (YYYY-MM-DD HH:MM:SS) as local time.
// This avoids browsers interpreting the value as UTC and showing ~8h offsets.
function parseMySQLLocalDateTime(value) {
if (!value || typeof value !== 'string') return new Date(NaN);
// Support values that may already be ISO-like; replace space with 'T' for consistent parsing
const normalized = value.replace(' ', 'T');
// Build using parts to force local time without timezone conversion
const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})/);
if (match) {
  const year = parseInt(match[1], 10);
  const month = parseInt(match[2], 10) - 1; // JS months 0-11
  const day = parseInt(match[3], 10);
  const hour = parseInt(match[4], 10);
  const minute = parseInt(match[5], 10);
  const second = parseInt(match[6], 10);
  return new Date(year, month, day, hour, minute, second);
}
// Fallback to Date constructor
return new Date(normalized);
}

// Function to edit announcement
function editAnnouncement(announcement) {
// Populate form with announcement data
document.getElementById("announcementTitle").value = announcement.title;
document.getElementById("announcementType").value = announcement.type;
document.getElementById("priorityLevel").value = announcement.priority || 'normal';
document.getElementById("targetAudience").value = announcement.audience || 'all';
document.getElementById("announcementContent").value = announcement.content;

// Expiry field removed from UI

// Update character count
const charCount = document.getElementById("charCount");
if (charCount) charCount.textContent = announcement.content.length;

// Scroll to form
document.querySelector(".announcement-form-container").scrollIntoView({ 
  behavior: "smooth" 
});

showNotification("Announcement loaded for editing", "info");
}

// Function to delete announcement
function deleteAnnouncement(announcementId, card) {
const deleteModal = document.createElement("div");
deleteModal.className = "modal-overlay";
deleteModal.style.display = "flex";

deleteModal.innerHTML = `
  <div class="modal-content">
    <h2>Delete Announcement</h2>
    <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
    <div style="margin-top:2rem;">
      <button type="button" class="modal-btn confirm" id="confirmDeleteBtn">
        Yes, Delete
      </button>
      <button type="button" class="modal-btn cancel" id="cancelDeleteBtn">
        Cancel
      </button>
    </div>
  </div>
`;

document.body.appendChild(deleteModal);

// Add event listeners
const confirmBtn = deleteModal.querySelector('#confirmDeleteBtn');
const cancelBtn = deleteModal.querySelector('#cancelDeleteBtn');

confirmBtn.addEventListener('click', () => {
  // Show loading
  confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
  confirmBtn.disabled = true;
  
  // Send delete request
  const formData = new FormData();
  formData.append('action', 'delete_announcement');
  formData.append('id', announcementId);
  
  const csrfToken = document.getElementById('csrf_token');
  if (csrfToken && csrfToken.value) {
    formData.append('csrf_token', csrfToken.value);
  }
  
  fetch('apis/announcement_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove card with animation
      card.style.animation = "slideOut 0.3s ease-out forwards";
      setTimeout(() => {
        card.remove();
      }, 300);
      
      showNotification("Announcement deleted successfully", "success");
    } else {
      showNotification(data.message || "Failed to delete announcement", "error");
    }
  })
  .catch(error => {
    console.error('Delete error:', error);
    showNotification("Error deleting announcement", "error");
  })
  .finally(() => {
    deleteModal.remove();
  });
});

cancelBtn.addEventListener('click', () => {
  deleteModal.remove();
});

// Close on overlay click
deleteModal.addEventListener("click", (e) => {
  if (e.target === deleteModal) {
    deleteModal.remove();
  }
});
}


// Function to load saved draft
function loadSavedDraft() {
const savedDraft = localStorage.getItem("announcementDraft");
if (savedDraft) {
  try {
    const draftData = JSON.parse(savedDraft);
    
    // Check if user wants to load the draft
    if (confirm('You have a saved draft. Would you like to load it?')) {
      // Populate form with draft data
      if (draftData.title) document.getElementById("announcementTitle").value = draftData.title;
      if (draftData.type) document.getElementById("announcementType").value = draftData.type;
      if (draftData.priority) document.getElementById("priorityLevel").value = draftData.priority;
      if (draftData.audience) document.getElementById("targetAudience").value = draftData.audience;
      if (draftData.content) {
        document.getElementById("announcementContent").value = draftData.content;
        // Update character count
        const charCount = document.getElementById("charCount");
        if (charCount) charCount.textContent = draftData.content.length;
      }
      // Expiry field removed from UI
      
      showNotification("Draft loaded from previous session", "info");
    }
  } catch (error) {
    console.error("Error loading draft:", error);
    localStorage.removeItem("announcementDraft");
  }
}
}

// Add CSS animations
const additionalStyles = `
@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideOutRight {
  from {
    opacity: 1;
    transform: translateX(0);
  }
  to {
    opacity: 0;
    transform: translateX(100%);
  }
}

@keyframes slideOut {
  from {
    opacity: 1;
    transform: translateX(0);
  }
  to {
    opacity: 0;
    transform: translateX(-100%);
  }
}

.announcement-status.active {
  color: var(--green-500);
}

.announcement-status.inactive {
  color: var(--red-500);
}
`;

// Add styles if not already present
if (!document.head.querySelector("style[data-announcement-styles]")) {
const styleSheet = document.createElement("style");
styleSheet.textContent = additionalStyles;
styleSheet.setAttribute("data-announcement-styles", "true");
document.head.appendChild(styleSheet);
}

// Edit/Add Notifications Functionality (Add this to your script.js)

// Get CSRF token
function getCSRFToken() {
  const csrfInput = document.getElementById('csrf_token');
  if (csrfInput && csrfInput.value) return csrfInput.value;
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta && meta.content) return meta.content;
  return '';
}

// Load notifications from database when page loads
async function loadNotifications() {
  try {
      const response = await fetch('apis/notifications_handler.php', {
          method: 'GET'
      });

      const result = await response.json();

      if (result.success) {
          displayNotifications(result.data);
          updateNotificationBadge(result.data.length);
          updateDashboardNotifications(result.data);
      } else {
          console.error('Failed to load notifications:', result.error);
      }
  } catch (error) {
      console.error('Error loading notifications:', error);
  }
}

// Display notifications in the current notifications list
function displayNotifications(notifications) {
  const notificationsList = document.getElementById('currentNotificationsList');
  if (!notificationsList) return;

  if (notifications.length === 0) {
      notificationsList.innerHTML = '<p class="no-notifications">No notifications found.</p>';
      return;
  }

  // Show only the latest 3 notifications
  const latest = notifications.slice(0, 3);

  notificationsList.innerHTML = latest.map(notification => `
      <div class="notification-card" data-notification-id="${notification.id}">
          <div class="notification-card-header">
              <div class="notification-card-icon">
                  <i class="fas fa-${notification.icon}"></i>
              </div>
              <div class="notification-card-content">
                  <h4 class="notification-card-title">${escapeHtml(notification.title)}</h4>
                  <p class="notification-card-message">${escapeHtml(notification.message)}</p>
                  <span class="notification-card-time">${escapeHtml(notification.time_display)}</span>
              </div>
              <div class="notification-card-actions">
                  <button class="edit-notification-btn" title="Edit" data-id="${notification.id}">
                      <i class="fas fa-edit"></i>
                  </button>
                  <button class="delete-notification-btn" title="Delete" data-id="${notification.id}">
                      <i class="fas fa-trash"></i>
                  </button>
              </div>
          </div>
      </div>
  `).join('');

  attachNotificationEventListeners();
}

// Show all notifications in a modal with delete support
function openAllNotificationsModal() {
// Support both pre-rendered modal (in DOM) and dynamic creation
let overlay = document.getElementById('allNotificationsModalOverlay');
let modal;
let close;

if (overlay) {
  // Use existing overlay: show it and wire up basic close on backdrop click
  overlay.style.display = 'block';
  modal = overlay.querySelector('.modal-content') || overlay.firstElementChild;
  close = function(){
    var modalEl = overlay.querySelector('.modal-content');
    if (modalEl) {
      modalEl.classList.add('popOut');
      modalEl.addEventListener('animationend', function handleAnim(){
        modalEl.classList.remove('popOut');
        overlay.style.display = 'none';
        modalEl.removeEventListener('animationend', handleAnim);
      });
    } else {
      overlay.style.display = 'none';
    }
  };
  overlay.addEventListener('click', function(e){ if(e.target === overlay) close(); });
  // Ensure notifications container exists
  if (!overlay.querySelector('#allNotificationsContainer') && modal) {
    const container = document.createElement('div');
    container.id = 'allNotificationsContainer';
    modal.appendChild(container);
  }
  // If there is a close button in markup, bind it
  const closeBtn = modal ? modal.querySelector('#closeAllNotificationsBtn') : null;
  if (closeBtn) closeBtn.addEventListener('click', close);
} else {
  // Create overlay and modal dynamically
  overlay = document.createElement('div');
  overlay.id = 'allNotificationsModalOverlay';
  overlay.className = 'modal-overlay';
  // Use display:block so CSS rule `.modal-overlay[style*="display:block"]` forces flex with !important
  overlay.style.cssText = 'align-items:center; justify-content:center; background:rgba(15, 23, 42, 0.45);';
  overlay.style.display = 'block';

  modal = document.createElement('div');
  modal.className = 'modal-content';
  modal.style.cssText = [
    'max-width:720px',
    'width:92%',
    'max-height:80vh',
    'overflow:auto',
    'border-radius:10px',
    'padding:16px',
    'box-shadow: 0 10px 30px rgba(0,0,0,0.25)'
  ].join(';');
  modal.innerHTML = `
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px;">
      <h3 style="margin:0; font-size:18px; color:#0f172a;">All Notifications</h3>
      <button id="closeAllNotificationsBtn" class="modal-btn" style="padding:6px 10px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; cursor:pointer;">Close</button>
    </div>
    <div id="allNotificationsContainer" style="margin-top:4px;"></div>
  `;
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  close = function(){
    var modalEl = overlay.querySelector('.modal-content');
    if (modalEl) {
      modalEl.classList.add('popOut');
      modalEl.addEventListener('animationend', function handleAnim(){
        modalEl.classList.remove('popOut');
        if (overlay && overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }
        modalEl.removeEventListener('animationend', handleAnim);
      });
    } else {
      if (overlay && overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }
  };
  overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  const closeBtn = modal.querySelector('#closeAllNotificationsBtn');
  if (closeBtn) closeBtn.addEventListener('click', close);
}

  // Fetch all notifications
  fetch('apis/notifications_handler.php', { method: 'GET', cache: 'no-store' })
    .then(r=>r.json())
    .then(result => {
      if (!result || !result.success) {
          modal.querySelector('#allNotificationsContainer').innerHTML = '<p style="color:#ef4444">Failed to load notifications</p>';
          return;
      }
      const list = result.data || [];
      const html = list.map(n => `
          <div class="notification-row" data-notification-id="${n.id}" style="display:flex; align-items:flex-start; gap:10px; padding:10px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:8px; background:#fff;">
              <div style="width:36px; height:36px; border-radius:8px; background:#eef2ff; display:flex; align-items:center; justify-content:center; color:#1e40af;">
                  <i class="fas fa-${n.icon}"></i>
              </div>
              <div style="flex:1 1 auto; min-width:0;">
                  <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                      <h4 style="margin:0; font-size:14px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(n.title)}</h4>
                      <span style="font-size:12px; color:#64748b;">${escapeHtml(n.time_display)}</span>
                  </div>
                  <p style="margin:4px 0 0 0; font-size:13px; color:#334155; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(n.message)}</p>
              </div>
              <div>
                  <button class="delete-notification-btn" data-id="${n.id}" title="Delete" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; padding:6px 8px; border-radius:6px; cursor:pointer;"><i class="fas fa-trash"></i></button>
              </div>
          </div>
      `).join('');
      modal.querySelector('#allNotificationsContainer').innerHTML = html || '<p>No notifications found.</p>';
      // Attach delete handlers inside modal
      modal.querySelectorAll('.delete-notification-btn').forEach(btn => {
          btn.addEventListener('click', async function(){
              const id = this.getAttribute('data-id');
              try {
                  await deleteNotificationFromDatabase(id);
        // Remove from modal UI (support both markup variants)
        const rowEl = this.closest('[data-notification-id]');
        if (rowEl) rowEl.remove();
                  // Reload main list
                  await loadNotifications();
              } catch(_) {}
          });
      });
    })
    .catch(()=>{
      modal.querySelector('#allNotificationsContainer').innerHTML = '<p style="color:#ef4444">Network error</p>';
    });
}

// Update dashboard notifications dropdown
function updateDashboardNotifications(notifications) {
  const notificationList = document.querySelector('.notification-list');
  if (!notificationList) return;

  // Show all notifications in the dropdown (scrollable list limits height)
  const latestNotifications = notifications; 
  notificationList.innerHTML = latestNotifications.map(notification => `
      <div class="notification-item">
          <div class="notification-icon">
              <i class="fas fa-${notification.icon}"></i>
          </div>
          <div class="notification-content">
              <p class="notification-title">${escapeHtml(notification.title)}</p>
              <p class="notification-message">${escapeHtml(notification.message)}</p>
              <p class="notification-time">${escapeHtml(notification.time_display)}</p>
          </div>
      </div>
  `).join('');
}

// Refresh CSRF token from server
// CSRF token refresh function (removed - not needed with clean approach)

// Add new notification to database (clean version)
async function addNotificationToDatabase(notificationData) {
  try {
      console.log('=== NOTIFICATION DEBUG START ===');
      console.log('Function called with data:', notificationData);
      
      // Get CSRF token
      const csrfToken = getCSRFToken();
      console.log('CSRF Token from getCSRFToken():', csrfToken);
      console.log('CSRF Token length:', csrfToken ? csrfToken.length : 0);
      
      if (!csrfToken) {
          throw new Error('CSRF token not found');
      }
      
      // Prepare URL-encoded body to ensure PHP populates $_POST
      const params = new URLSearchParams();
      params.set('action', 'add_notification');
      params.set('title', notificationData.title);
      params.set('message', notificationData.message);
      params.set('icon', notificationData.icon);
      params.set('type', notificationData.type);
      // Time fields removed; backend will default
      params.set('csrf_token', csrfToken);

      // Debug: Log encoded payload
      console.log('URL-encoded payload:', params.toString());

      // Ensure action is also present in query; send header CSRF too
      const response = await fetch('apis/notifications_handler.php?action=add_notification', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'X-CSRF-Token': csrfToken
          },
          body: params.toString()
      });

      const result = await response.json();
      console.log('=== NOTIFICATION DEBUG END ===');
      console.log('Server response:', result);

      if (result.success) {
          showNotificationToast('Notification added successfully!', 'success');
          await loadNotifications();
          resetNotificationForm();
          return result.data;
      } else {
          throw new Error(result.message || 'Failed to add notification');
      }
  } catch (error) {
      console.log('=== NOTIFICATION ERROR ===');
      console.error('Error adding notification:', error);
      showNotificationToast('Error adding notification: ' + error.message, 'error');
      throw error;
  }
}

// Update notification in database
async function updateNotificationInDatabase(id, notificationData) {
  try {
      const response = await fetch('apis/notifications_handler.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
          },
          body: JSON.stringify({
              id: id,
              ...notificationData,
              csrf_token: getCSRFToken()
          })
      });

      const result = await response.json();

      if (result.success) {
          showNotificationToast('Notification updated successfully!', 'success');
          await loadNotifications();
          resetNotificationForm();
          // Reset editing state
          const form = document.getElementById('notificationForm');
          delete form.dataset.editingId;
          const submitButton = form.querySelector('button[type="submit"]');
          submitButton.innerHTML = '<i class="fas fa-plus"></i> Add Notification';
          return result.data;
      } else {
          throw new Error(result.error || 'Failed to update notification');
      }
  } catch (error) {
      console.error('Error updating notification:', error);
      showNotificationToast('Error updating notification: ' + error.message, 'error');
      throw error;
  }
}

// Delete notification from database
async function deleteNotificationFromDatabase(id) {
  try {
      const response = await fetch('apis/notifications_handler.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
          },
          body: JSON.stringify({
              action: 'delete_notification',
              id: id,
              csrf_token: getCSRFToken()
          })
      });

      const result = await response.json();

      if (result.success) {
          showNotificationToast('Notification deleted successfully!', 'success');
          await loadNotifications();
      } else {
          throw new Error(result.error || 'Failed to delete notification');
      }
  } catch (error) {
      console.error('Error deleting notification:', error);
      showNotificationToast('Error deleting notification: ' + error.message, 'error');
  }
}

// Attach event listeners to notification action buttons
function attachNotificationEventListeners() {

      // Edit button listeners (keep existing)
  document.querySelectorAll('.edit-notification-btn').forEach(button => {
      button.addEventListener('click', function() {
          const notificationId = this.getAttribute('data-id');
          editNotification(notificationId);
      });
  });

  
  // Delete button listeners - UPDATED
  document.querySelectorAll('.delete-notification-btn').forEach(button => {
      button.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          const notificationId = this.getAttribute('data-id');
          // Support both card and compact row structures
          const containerEl = this.closest('.notification-card') || this.closest('.notification-row');
          let notificationTitle = 'this notification';
          if (containerEl) {
              const titleEl = containerEl.querySelector('.notification-card-title') || containerEl.querySelector('h4');
              if (titleEl && titleEl.textContent) notificationTitle = titleEl.textContent;
          }
          showDeleteNotificationModal(notificationId, notificationTitle);
      });
  });


}

// Edit notification function
async function editNotification(id) {
  try {
      // Find the notification data from the DOM
      const notificationCard = document.querySelector(`[data-notification-id="${id}"]`);
      if (!notificationCard) return;

      const title = notificationCard.querySelector('.notification-card-title').textContent;
      const message = notificationCard.querySelector('.notification-card-message').textContent;
      const time = notificationCard.querySelector('.notification-card-time').textContent;
      const icon = notificationCard.querySelector('.notification-card-icon i').className.split('fa-')[1];

      // Populate the form with existing data
      document.getElementById('notificationTitle').value = title;
      document.getElementById('notificationMessage').value = message;
      document.getElementById('selectedIcon').value = icon;
      
      // Select the icon
      document.querySelectorAll('.icon-option').forEach(option => {
          option.classList.remove('selected');
          if (option.getAttribute('data-icon') === icon) {
              option.classList.add('selected');
          }
      });

      // Set time value
      // Time fields removed from UI

      // Update character count
      updateCharacterCount();

      // Scroll to form
      document.getElementById('notificationForm').scrollIntoView({ behavior: 'smooth' });

      // Change form submit behavior to update instead of create
      const form = document.getElementById('notificationForm');
      form.dataset.editingId = id;
      
      // Change button text
      const submitButton = form.querySelector('button[type="submit"]');
      submitButton.innerHTML = '<i class="fas fa-save"></i> Update Notification';

      showNotificationToast('Notification loaded for editing', 'info');

  } catch (error) {
      console.error('Error editing notification:', error);
      showNotificationToast('Error loading notification for editing.', 'error');
  }
}

// Helper functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function updateNotificationBadge(count) {
  const badge = document.querySelector('.notification-badge');
  if (badge) {
      badge.textContent = Math.min(count, 99);
      badge.style.display = count > 0 ? 'block' : 'none';
  }
}

function updateCharacterCount() {
  const messageInput = document.getElementById('notificationMessage');
  const charCount = document.getElementById('messageCharCount');
  if (messageInput && charCount) {
      const currentLength = messageInput.value.length;
      charCount.textContent = currentLength;
      
      // Change color based on character limit
      if (currentLength > 180) {
          charCount.style.color = 'var(--destructive)';
      } else if (currentLength > 150) {
          charCount.style.color = 'var(--orange-500)';
      } else {
          charCount.style.color = 'var(--muted-foreground)';
      }
      
      // Prevent exceeding 200 characters
      if (currentLength > 200) {
          messageInput.value = messageInput.value.substring(0, 200);
          charCount.textContent = '200';
      }
  }
}

function clearIconSelection() {
  document.querySelectorAll('.icon-option').forEach(option => {
      option.classList.remove('selected');
  });
  document.getElementById('selectedIcon').value = '';
}

// Function to convert time value to display text
// getTimeDisplayText removed (no longer needed)

// Function to reset notification form
function resetNotificationForm() {
  const form = document.getElementById('notificationForm');
  if (form) {
      form.reset();
      
      // Clear icon selection
      clearIconSelection();
      
      // Reset character count
      updateCharacterCount();
      
      // Hide custom time group
      const customTimeGroup = document.getElementById('customTimeGroup');
      if (customTimeGroup) {
          customTimeGroup.style.display = 'none';
          document.getElementById('customTime').required = false;
      }
  }
}


// Enhanced toast function for notifications
function showNotificationToast(message, type = 'info') {
  // Remove any existing toasts first
  const existingToasts = document.querySelectorAll('.notification-toast');
  existingToasts.forEach(toast => toast.remove());
  
  const toast = document.createElement('div');
  toast.className = `notification-toast toast-${type}`;
  toast.textContent = message;
  
  toast.style.cssText = `
      position: fixed;
      top: 80px;
      right: 20px;
      padding: 12px 20px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      z-index: 1000;
      animation: slideIn 0.3s ease-out;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      min-width: 250px;
      text-align: center;
  `;
  
  switch (type) {
      case 'success':
          toast.style.background = 'var(--green-500)';
          break;
      case 'error':
          toast.style.background = 'var(--destructive)';
          break;
      case 'warning':
          toast.style.background = 'var(--orange-500)';
          break;
      case 'info':
          toast.style.background = 'var(--blue-500)';
          break;
      default:
          toast.style.background = 'var(--blue-500)';
  }
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s ease-out forwards';
      setTimeout(() => {
          if (document.body.contains(toast)) {
              document.body.removeChild(toast);
          }
      }, 300);
  }, 3000);
}

// Main initialization when page loads
document.addEventListener("DOMContentLoaded", () => {
  // Load notifications from database when page loads
  loadNotifications();
  
  // Initialize notification form functionality
  initializeNotificationForm();
});

function initializeNotificationForm() {
  // Icon selection functionality
  const iconOptions = document.querySelectorAll('.icon-option');
  const selectedIconInput = document.getElementById('selectedIcon');
  
  iconOptions.forEach(option => {
      option.addEventListener('click', function() {
          // Remove selected class from all options
          iconOptions.forEach(opt => opt.classList.remove('selected'));
          
          // Add selected class to clicked option
          this.classList.add('selected');
          
          // Store selected icon value
          const iconValue = this.dataset.icon;
          selectedIconInput.value = iconValue;
      });
  });

  // Character counter for notification message
  const messageTextarea = document.getElementById('notificationMessage');
  if (messageTextarea) {
      messageTextarea.addEventListener('input', updateCharacterCount);
  }

  // Time inputs removed

  // Form submission with database integration
  const notificationForm = document.getElementById('notificationForm');
  if (notificationForm) {
      notificationForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Validate icon selection
      if (!selectedIconInput.value) {
          showNotificationToast('Please select an icon for the notification', 'error');
          return;
      }
      
      const formData = new FormData(this);
      const notificationData = {
          title: formData.get('title'),
          icon: formData.get('icon'),
          message: formData.get('message'),
          type: formData.get('type'),
          csrf_token: document.getElementById('csrf_token').value
      };
          
      // Validate required fields
      if (!notificationData.title || !notificationData.message || !notificationData.type) {
          showNotificationToast('Please fill in all required fields', 'error');
          return;
      }
      
      // Validate CSRF token
      if (!notificationData.csrf_token) {
          showNotificationToast('CSRF token not found. Please refresh the page.', 'error');
          return;
      }
      
      // Debug CSRF token
      console.log('Sending CSRF token:', notificationData.csrf_token);
      console.log('CSRF token length:', notificationData.csrf_token.length);
          
      try {
          // Send as JSON data to match the handler's expectations
          const response = await fetch('apis/notifications_handler.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
              },
              body: JSON.stringify(notificationData)
          });
          
          // Parse response
          const responseText = await response.text();
          
          let data;
          try {
              data = JSON.parse(responseText);
          } catch (e) {
              console.error('Failed to parse JSON response:', e);
              throw new Error('Invalid response from server: ' + responseText);
          }
          
          if (data.success) {
              showNotificationToast('Notification added successfully!', 'success');
              await loadNotifications();
              resetNotificationForm();
          } else {
              // If CSRF token is invalid, try to refresh it
              if (data.error && data.error.includes('Invalid CSRF token')) {
                  console.log('CSRF token invalid, refreshing...');
                  await refreshCSRFToken();
                  showNotificationToast('CSRF token was invalid. Please try again.', 'error');
                  return;
              }
              throw new Error(data.message || 'Failed to add notification');
          }
      } catch (error) {
          console.error('Error adding notification:', error);
          showNotificationToast('Error adding notification: ' + error.message, 'error');
      }
      });
  }


  // Clear form functionality
  const clearBtn = document.getElementById('clearNotificationForm');
  if (clearBtn) {
      clearBtn.addEventListener('click', function() {
          resetNotificationForm();
          
          // Reset editing state
          const form = document.getElementById('notificationForm');
          delete form.dataset.editingId;
          const submitButton = form.querySelector('button[type="submit"]');
          submitButton.innerHTML = '<i class="fas fa-plus"></i> Add Notification';
          
          showNotificationToast('Form cleared', 'info');
      });
  }
}

// Add CSS animations for toast if not already present
const notificationStyles = `
  @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
  }
  
  @keyframes slideOut {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(100%); opacity: 0; }
  }
  
  .no-notifications {
      text-align: center;
      color: var(--text-secondary);
      font-style: italic;
      padding: 2rem;
      border: 2px dashed var(--border);
      border-radius: 8px;
      background: var(--card-background);
  }
`;

// Add styles if not already present
if (!document.head.querySelector("style[data-notification-toast-styles]")) {
  const styleSheet = document.createElement("style");
  styleSheet.textContent = notificationStyles;
  styleSheet.setAttribute("data-notification-toast-styles", "true");
  document.head.appendChild(styleSheet);
}

// Global variable to track which notification is being deleted
let notificationToDelete = null;

// Function to show delete confirmation modal
function showDeleteNotificationModal(notificationId, notificationTitle) {
  notificationToDelete = notificationId;
  
  // Create modal HTML
  const modalHTML = `
      <div id="deleteNotificationModal" class="delete-notification-modal">
          <div class="delete-modal-content">
              <h2>
                  <i class="fas fa-exclamation-triangle"></i>
                  Delete Notification
              </h2>
              <p>Are you sure you want to delete the notification "<strong>${escapeHtml(notificationTitle)}</strong>"? This action cannot be undone.</p>
              <div class="delete-modal-buttons">
                  <button type="button" class="delete-modal-btn confirm" id="confirmDeleteNotificationBtn">
                      <i class="fas fa-trash"></i>
                      Yes, Delete
                  </button>
                  <button type="button" class="delete-modal-btn cancel" id="cancelDeleteNotificationBtn">
                      <i class="fas fa-times"></i>
                      Cancel
                  </button>
              </div>
          </div>
      </div>
  `;
  
  // Add modal to document
  document.body.insertAdjacentHTML('beforeend', modalHTML);
  
  // Get modal elements
  const modal = document.getElementById('deleteNotificationModal');
  const modalContent = modal.querySelector('.delete-modal-content');
  const confirmBtn = document.getElementById('confirmDeleteNotificationBtn');
  const cancelBtn = document.getElementById('cancelDeleteNotificationBtn');
  
  // Show modal with animation
  modalContent.classList.remove('popOut');
  modalContent.style.animation = 'scaleIn 0.25s ease-out';
  
  // Add event listeners
  confirmBtn.addEventListener('click', function() {
      confirmDeleteNotification(notificationToDelete);
  });
  
  cancelBtn.addEventListener('click', function() {
      hideDeleteNotificationModal();
  });
  
  // Close on overlay click
  modal.addEventListener('click', function(e) {
      if (e.target === modal) {
          hideDeleteNotificationModal();
      }
  });
  
  // Close on Escape key
  document.addEventListener('keydown', function handleEscape(e) {
      if (e.key === 'Escape') {
          hideDeleteNotificationModal();
          document.removeEventListener('keydown', handleEscape);
      }
  });
}

// Function to hide delete modal with animation
function hideDeleteNotificationModal() {
  const modal = document.getElementById('deleteNotificationModal');
  if (modal) {
      const modalContent = modal.querySelector('.delete-modal-content');
      
      modalContent.style.animation = 'popOut 0.25s ease-in';
      modalContent.classList.add('popOut');
      
      modalContent.addEventListener('animationend', function handler() {
          modal.remove();
          modalContent.removeEventListener('animationend', handler);
      });
  }
  notificationToDelete = null;
}

// Function to confirm deletion
async function confirmDeleteNotification(notificationId) {
  const confirmBtn = document.getElementById('confirmDeleteNotificationBtn');
  const originalText = confirmBtn.innerHTML;
  
  confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
  confirmBtn.disabled = true;
  
  try {
      console.log('Deleting notification with ID:', notificationId);
      
      const formData = new FormData();
      formData.append('action', 'delete_notification');
      formData.append('id', notificationId);
      
      const csrfToken = document.getElementById('csrf_token');
      if (csrfToken && csrfToken.value) {
          formData.append('csrf_token', csrfToken.value);
      }
      
      const response = await fetch('apis/notifications_handler.php', {
          method: 'POST',
          body: formData
      });
      
      if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const text = await response.text();
      const data = JSON.parse(text);
      
      if (data.success) {
          showNotification('Notification deleted successfully!', 'success');
          loadRecentNotifications();
          hideDeleteNotificationModal();
      } else {
          showNotification(data.message || 'Failed to delete notification', 'error');
      }
  } catch (error) {
      console.error('Delete error:', error);
      showNotification('Network error: ' + error.message, 'error');
  } finally {
      confirmBtn.innerHTML = originalText;
      confirmBtn.disabled = false;
  }
}

// Function to load recent notifications (copied from announcement pattern)
function loadRecentNotifications() {
  const formData = new FormData();
  formData.append('action', 'get_notifications');
  
  // Get CSRF token if it exists
  const csrfToken = document.getElementById('csrf_token');
  if (csrfToken && csrfToken.value) {
      formData.append('csrf_token', csrfToken.value);
  }
  
  fetch('apis/notifications_handler.php', {
      method: 'POST',
      body: formData
  })
  .then(response => response.json())
  .then(data => {
      if (data.success && data.notifications) {
          displayNotifications(data.notifications);
          updateNotificationBadge(data.notifications.length);
          updateDashboardNotifications(data.notifications);
      } else {
          console.error('Failed to load notifications:', data.message);
      }
  })
  .catch(error => {
      console.error('Error loading notifications:', error);
  });
}

// Add this temporary function to check CSRF token
function debugCSRFToken() {
  const csrfInput = document.getElementById('csrf_token');
  console.log('CSRF Input element:', csrfInput);
  console.log('CSRF Token value:', csrfInput ? csrfInput.value : 'NOT FOUND');
  return csrfInput ? csrfInput.value : '';
}

document.addEventListener("DOMContentLoaded", () => {
  // Initialize Add Trainees functionality
  initializeAddTraineesPage();
  
  // Initialize edit functionality
  const editTraineeManager = initializeEditTraineeFunctionality();
  
  // Make edit functionality available globally
  window.attachTraineeEditListener = function(editButton) {
      if (editButton && editTraineeManager) {
          editButton.addEventListener("click", editTraineeManager.handleEditClick);
      }
  };

// Bind Show more for notifications (hardened)
const showAllBtn = document.getElementById('showAllNotificationsBtn');
// Ensure button doesn't submit a parent form
if (showAllBtn && showAllBtn.type !== 'button') { showAllBtn.type = 'button'; }
if (showAllBtn) {
  showAllBtn.addEventListener('click', function(e){
    e.preventDefault(); // avoid form submit/page reload
    e.stopPropagation(); // avoid delegated handler double-firing
    openAllNotificationsModal();
  });
}
// Fallback: event delegation in case the button is rendered later
document.addEventListener('click', function(e){
  var t = e.target;
  if (!t) return;
  var btn = (t.id === 'showAllNotificationsBtn') ? t : (t.closest ? t.closest('#showAllNotificationsBtn') : null);
  if (btn) {
    e.preventDefault();
    openAllNotificationsModal();
  }
});
});

function initializeAddTraineesPage() {
  // Character counter for additional notes
  const traineeNotes = document.getElementById("traineeNotes");
  const notesCharCount = document.getElementById("notesCharCount");
  
  if (traineeNotes && notesCharCount) {
      traineeNotes.addEventListener("input", function() {
          const currentLength = this.value.length;
          notesCharCount.textContent = currentLength;
          
          // Change color based on character limit
          if (currentLength > 450) {
              notesCharCount.style.color = "var(--destructive)";
          } else if (currentLength > 400) {
              notesCharCount.style.color = "var(--orange-500)";
          } else {
              notesCharCount.style.color = "var(--muted-foreground)";
          }
          
          // Prevent exceeding 500 characters
          if (currentLength > 500) {
              this.value = this.value.substring(0, 500);
              notesCharCount.textContent = "500";
          }
      });
  }



// Save draft button handler
const saveDraftBtn = document.getElementById("saveTraineeDraftBtn");
if (saveDraftBtn) {
  saveDraftBtn.addEventListener("click", function() {
    const formData = new FormData(traineeForm);
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
      draftData[key] = value;
    }
    
    draftData.saved = new Date().toISOString();
    
    localStorage.setItem("traineeDraft", JSON.stringify(draftData));
    showTraineeNotification("Draft saved successfully", "success");
  });
  }


  // Initialize student number validation
  initializeStudentNumberValidation();

  // Initialize student number formatting
  initializeStudentNumberFormatting();

  // Set today's date as default for enrollment date
  setDefaultEnrollmentDate();

  // Load saved draft
  loadSavedTraineeDraft();

  // Recently Added Trainees UI removed – no loading or init needed
}

// Function to set today's date as default for enrollment date
function setDefaultEnrollmentDate() {
  const enrollDateInput = document.getElementById('traineeEnrollDate');
  if (enrollDateInput && !enrollDateInput.value) {
    // Get today's date in YYYY-MM-DD format
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const todayString = `${year}-${month}-${day}`;
    
    // Set the default value
    enrollDateInput.value = todayString;
  }
}

// Initialize student number validation functionality
function initializeStudentNumberValidation() {
  const studentNumberInput = document.getElementById('traineeStudentNumber');
  const studentNumberCaution = document.getElementById('studentNumberCaution');
  
  if (!studentNumberInput || !studentNumberCaution) {
    console.warn('Student number validation elements not found');
    return;
  }
  
  let validationTimeout;
  
  studentNumberInput.addEventListener('input', function() {
    const studentNumber = this.value.trim();
    
    // Clear previous timeout
    if (validationTimeout) {
      clearTimeout(validationTimeout);
    }
    
    // Hide caution message immediately when user starts typing
    studentNumberCaution.style.display = 'none';
    
    // Only validate if student number is complete (23 characters: DJR-90-402-14011-001)
    if (studentNumber.length === 23) {
      // Debounce the validation to avoid too many API calls
      validationTimeout = setTimeout(() => {
        validateStudentNumber(studentNumber);
      }, 500); // Wait 500ms after user stops typing
    }
  });
  
  // Also validate on blur (when user leaves the field)
  studentNumberInput.addEventListener('blur', function() {
    const studentNumber = this.value.trim();
    if (studentNumber.length === 23) {
      validateStudentNumber(studentNumber);
    }
  });
}

// Function to validate student number via API
function validateStudentNumber(studentNumber) {
  const studentNumberCaution = document.getElementById('studentNumberCaution');
  
  if (!studentNumberCaution) return;
  
  // Show loading state
  studentNumberCaution.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Checking student number...</span>';
  studentNumberCaution.style.display = 'block';
  studentNumberCaution.style.color = 'var(--blue-500)';
  
  fetch(`apis/student_number_validation.php?student_number=${encodeURIComponent(studentNumber)}`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Student number validation response:', data);
      if (data.success) {
        if (data.exists) {
          // Student number exists - show warning
          studentNumberCaution.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>This student number already exists in the system</span>';
          studentNumberCaution.style.display = 'block';
          studentNumberCaution.style.color = 'var(--destructive)';
          // Update duplicate state for button control
          if (window.updateDuplicateState) {
            window.updateDuplicateState('studentNumber', true);
          }
        } else {
          // Student number is available - hide the message
          studentNumberCaution.style.display = 'none';
          // Update duplicate state for button control
          if (window.updateDuplicateState) {
            window.updateDuplicateState('studentNumber', false);
          }
        }
      } else {
        // API error - show error message
        studentNumberCaution.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error checking student number availability</span>';
        studentNumberCaution.style.display = 'block';
        studentNumberCaution.style.color = 'var(--orange-500)';
        // Update duplicate state for button control
        if (window.updateDuplicateState) {
          window.updateDuplicateState('studentNumber', false);
        }
      }
    })
    .catch(error => {
      console.error('Student number validation error:', error);
      // Show error message
      studentNumberCaution.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error checking student number availability</span>';
      studentNumberCaution.style.display = 'block';
      studentNumberCaution.style.color = 'var(--orange-500)';
      // Update duplicate state for button control
      if (window.updateDuplicateState) {
        window.updateDuplicateState('studentNumber', false);
      }
    });
}

// Initialize student number formatting functionality
function initializeStudentNumberFormatting() {
  // Make the formatting function globally available
  window.formatStudentNumber = function(input) {
    let value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    // Limit to 16 characters (3+2+3+5+3 = 16)
    if (value.length > 16) {
      value = value.substring(0, 16);
    }
    
    // Format: DJR-90-402-14011-001
    let formatted = '';
    if (value.length > 0) {
      formatted = value.substring(0, 3);
    }
    if (value.length > 3) {
      formatted += '-' + value.substring(3, 5);
    }
    if (value.length > 5) {
      formatted += '-' + value.substring(5, 8);
    }
    if (value.length > 8) {
      formatted += '-' + value.substring(8, 13);
    }
    if (value.length > 13) {
      formatted += '-' + value.substring(13, 16);
    }
    
    input.value = formatted;
  };
}

// Function to initialize edit buttons for all trainee cards
function initializeEditButtonsForAllCards() { return; }

// Global edit click handler
function handleGlobalEditClick(event) {
  console.log('Edit button clicked!');
  event.preventDefault();
  event.stopPropagation();
  
  const traineeCard = event.target.closest('.trainee-card');
  if (!traineeCard) { return; }
  
  console.log('Found trainee card:', traineeCard);
  
  // Check if the edit modal exists
  const editModal = document.getElementById('editTraineeModal');
  if (!editModal) { return; }
  
  // Extract trainee data
  const traineeData = extractTraineeData(traineeCard);
  console.log('Extracted trainee data:', traineeData);
  
  // Set global variables (make sure these are declared)
  window.currentTraineeBeingEdited = traineeCard;
  window.originalTraineeData = traineeData;
  
  // Populate the form
  populateEditForm(traineeData);
  
  // Show the modal
  editModal.style.display = 'flex';
  const modalContent = editModal.querySelector('.modal-content');
  if (modalContent) {
      modalContent.classList.remove('popOut');
      modalContent.style.animation = 'scaleIn 0.25s';
  }
  
  console.log('Edit modal shown');
}


// Function to initialize actions for dynamically created cards
function initializeTraineeActionsForDynamicCards() {
  console.log('Initializing trainee actions for dynamic cards...');
  
  // Edit trainee buttons
  const editBtns = document.querySelectorAll('.edit-trainee-btn');
  console.log('Found', editBtns.length, 'edit buttons');
  
  editBtns.forEach(btn => {
      // Remove existing listeners to prevent duplicates
      btn.removeEventListener('click', handleEditClickWrapper);
      btn.addEventListener('click', handleEditClickWrapper);
  });
  
  // Delete trainee buttons
  const deleteBtns = document.querySelectorAll('.delete-trainee-btn');
  console.log('Found', deleteBtns.length, 'delete buttons');
  
  deleteBtns.forEach(btn => {
      btn.removeEventListener('click', handleDeleteClick);
      btn.addEventListener('click', handleDeleteClick);
  });
  
  console.log('Trainee actions initialized successfully');
}

// Wrapper function for edit click to ensure proper context
function handleEditClickWrapper(event) {
  console.log('Edit button clicked');
  
  // Make sure the edit functionality is available
  if (typeof handleEditClick === 'function') {
      handleEditClick(event);
  } else {
      console.error('handleEditClick function not found');
      // Fallback: trigger the edit modal manually
      const traineeCard = event.target.closest('.trainee-card');
      if (traineeCard) {
          manualEditTrigger(traineeCard);
      }
  }
}

// Manual edit trigger as fallback
function manualEditTrigger(traineeCard) {
  console.log('Manual edit trigger for card:', traineeCard);
  
  // Extract data manually
  const traineeData = extractTraineeData(traineeCard);
  console.log('Extracted trainee data:', traineeData);
  
  // Set global variables
  currentTraineeBeingEdited = traineeCard;
  originalTraineeData = traineeData;
  
  // Populate form
  populateEditForm(traineeData);
  
  // Show modal
  const editTraineeModal = document.getElementById("editTraineeModal");
  if (editTraineeModal) {
      editTraineeModal.style.display = "flex";
      const modalContent = editTraineeModal.querySelector(".modal-content");
      if (modalContent) {
          modalContent.classList.remove("popOut");
          modalContent.style.animation = "scaleIn 0.25s";
      }
      console.log('Edit modal shown');
  } else {
      console.error('Edit modal not found');
  }
}

// Handle delete click
function handleDeleteClick(event) {
  console.log('Delete button clicked');
  
  const traineeCard = event.target.closest('.trainee-card');
  const traineeName = traineeCard.querySelector('.trainee-name').textContent;
  const traineeId = traineeCard.dataset.traineeId;
  
  if (confirm(`Are you sure you want to delete ${traineeName}?`)) {
      deleteTraineeFromDatabase(traineeId, traineeCard);
  }
}

// Function to edit trainee (exact same pattern as editAnnouncement)
function editTrainee(trainee) {
  console.log('Editing trainee:', trainee);
  
  // Populate form with trainee data (same pattern as announcements)
  document.getElementById("traineeSurname").value = trainee.surname || '';
  document.getElementById("traineeFirstname").value = trainee.firstname || '';
  document.getElementById("traineeContact").value = trainee.contact_number || trainee.contact || '';
  document.getElementById("traineeCourse").value = trainee.course || '';
  
  // Handle date formatting
  if (trainee.date_enrolled) {
      // Convert date to YYYY-MM-DD format for input field
      const date = new Date(trainee.date_enrolled);
      const formattedDate = date.toISOString().split('T')[0];
      document.getElementById("traineeEnrollDate").value = formattedDate;
  }
  
  // Handle notes
  const notesField = document.getElementById("traineeNotes");
  if (notesField) {
      notesField.value = trainee.notes || '';
      
      // Update character count
      const notesCharCount = document.getElementById("notesCharCount");
      if (notesCharCount) {
          notesCharCount.textContent = (trainee.notes || '').length;
      }
  }
  
  // Scroll to form (same as announcements)
  document.querySelector(".trainee-form-container").scrollIntoView({ 
      behavior: "smooth" 
  });
  
  // Show notification (exact same as announcements)
  showTraineeNotification("Trainee loaded for editing", "info");
}

// Function to handle trainee card creation with proper edit functionality
function createTraineeCard(trainee) {
  console.log("Creating trainee card for:", trainee);
  
  const card = document.createElement("div");
  card.className = "trainee-card";
  card.dataset.traineeId = trainee.id; // Use real database ID
  
  // Format date
  const createdDate = new Date(trainee.created_at);
  const timeAgo = formatTimeAgo(createdDate);
  
  // Format enrolled date
  const enrolledDate = new Date(trainee.date_enrolled);
  const formattedEnrollDate = enrolledDate.toLocaleDateString();
  
  // Course abbreviation for styling
  const courseAbbr = trainee.course.toLowerCase().replace(/[^a-z]/g, '');
  
  card.innerHTML = `
      <div class="trainee-header">
          <div class="trainee-meta">
              <span class="trainee-course ${courseAbbr}">${trainee.course}</span>
              <span class="trainee-date">${timeAgo}</span>
          </div>
          <div class="trainee-actions">
              <button class="edit-trainee-btn" title="Edit">
                  <i class="fas fa-edit"></i>
              </button>
              <button class="delete-trainee-btn" title="Delete">
                  <i class="fas fa-trash"></i>
              </button>
          </div>
      </div>
      <h4 class="trainee-name">${trainee.surname}, ${trainee.firstname}${trainee.middlename ? ' ' + trainee.middlename.charAt(0) + '.' : ''}</h4>
      <p class="trainee-details">Contact: ${trainee.contact_number} | Enrolled: ${formattedEnrollDate}</p>
      <div class="trainee-stats">
          <span><i class="fas fa-calendar"></i> ${trainee.days_enrolled || 0} days enrolled</span>
          <span><i class="fas fa-graduation-cap"></i> ${trainee.status.charAt(0).toUpperCase() + trainee.status.slice(1)}</span>
      </div>
  `;
  
  // Add event listeners (exact same pattern as announcements)
  const editBtn = card.querySelector(".edit-trainee-btn");
  const deleteBtn = card.querySelector(".delete-trainee-btn");
  
  // Edit button event listener (same pattern as announcements)
  editBtn.addEventListener("click", () => editTrainee(trainee));
  
  // Delete button event listener (same pattern as announcements)
  deleteBtn.addEventListener("click", () => deleteTrainee(trainee.id, card));
  
  console.log("Created trainee card with ID:", trainee.id);
  return card;
}

// Function to delete trainee (same pattern as announcements)
function deleteTrainee(traineeId, card) {
  console.log('Delete trainee button clicked for ID:', traineeId);
  
  const traineeName = card.querySelector('.trainee-name').textContent;
  
  // Create delete modal (same structure as announcements)
  const deleteModal = document.createElement("div");
  deleteModal.className = "modal-overlay";
  deleteModal.style.display = "flex";
  
  deleteModal.innerHTML = `
      <div class="modal-content">
          <h2>Delete Trainee</h2>
          <p>Are you sure you want to delete ${traineeName}? This action cannot be undone.</p>
          <div style="margin-top:2rem;">
              <button type="button" class="modal-btn confirm" id="confirmDeleteTraineeBtn">
                  Yes, Delete
              </button>
              <button type="button" class="modal-btn cancel" id="cancelDeleteTraineeBtn">
                  Cancel
              </button>
          </div>
      </div>
  `;
  
  document.body.appendChild(deleteModal);
  
  // Add event listeners (same pattern as announcements)
  const confirmBtn = deleteModal.querySelector('#confirmDeleteTraineeBtn');
  const cancelBtn = deleteModal.querySelector('#cancelDeleteTraineeBtn');
  
  confirmBtn.addEventListener('click', () => {
      // Show loading
      confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
      confirmBtn.disabled = true;
      
      // Send delete request (same pattern as announcements)
      const formData = new FormData();
      formData.append('action', 'delete_trainee');
      formData.append('id', traineeId);
      
      const csrfToken = document.getElementById('csrf_token');
      if (csrfToken && csrfToken.value) {
          formData.append('csrf_token', csrfToken.value);
      }
      
      fetch('apis/trainee_handler.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Remove card with animation (same as announcements)
              card.style.animation = "slideOut 0.3s ease-out forwards";
              setTimeout(() => {
                  card.remove();
              }, 300);
              
              showTraineeNotification("Trainee deleted successfully", "success");
          } else {
              showTraineeNotification(data.message || "Failed to delete trainee", "error");
          }
      })
      .catch(error => {
          console.error('Delete error:', error);
          showTraineeNotification("Error deleting trainee", "error");
      })
      .finally(() => {
          deleteModal.remove();
      });
  });
  
  cancelBtn.addEventListener('click', () => {
      deleteModal.remove();
  });
  
  // Close on overlay click (same as announcements)
  deleteModal.addEventListener("click", (e) => {
      if (e.target === deleteModal) {
          deleteModal.remove();
      }
  });
}

// Function to initialize actions for dynamically created cards
function initializeTraineeActionsForDynamicCards() {
  // Edit trainee buttons
  const editBtns = document.querySelectorAll('.edit-trainee-btn');
  editBtns.forEach(btn => {
      // Remove existing listeners to prevent duplicates
      btn.removeEventListener('click', handleEditClick);
      btn.addEventListener('click', handleEditClick);
  });
  
  // Delete trainee buttons
  const deleteBtns = document.querySelectorAll('.delete-trainee-btn');
  deleteBtns.forEach(btn => {
      btn.addEventListener('click', function() {
          const traineeCard = this.closest('.trainee-card');
          const traineeName = traineeCard.querySelector('.trainee-name').textContent;
          const traineeId = traineeCard.dataset.traineeId;
          
          if (confirm(`Are you sure you want to delete ${traineeName}?`)) {
              deleteTraineeFromDatabase(traineeId, traineeCard);
          }
      });
  });
}

// Function to delete trainee from database
function deleteTraineeFromDatabase(traineeId, traineeCard) {
  const formData = new FormData();
  formData.append('action', 'delete_trainee');
  formData.append('id', traineeId);
  
  const csrfToken = document.getElementById('csrf_token');
  if (csrfToken && csrfToken.value) {
      formData.append('csrf_token', csrfToken.value);
  }
  
  fetch('apis/trainee_handler.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          // Remove card with animation
          traineeCard.style.animation = "slideOut 0.3s ease-out forwards";
          setTimeout(() => {
              traineeCard.remove();
          }, 300);
          
          showTraineeNotification("Trainee deleted successfully", "success");
      } else {
          showTraineeNotification(data.message || "Failed to delete trainee", "error");
      }
  })
  .catch(error => {
      console.error('Delete error:', error);
      showTraineeNotification("Error deleting trainee", "error");
  });
}



// Load saved draft
function loadSavedTraineeDraft() {
const savedDraft = localStorage.getItem("traineeDraft");
if (savedDraft) {
  try {
    const draftData = JSON.parse(savedDraft);
    
    if (confirm('You have a saved trainee draft. Would you like to load it?')) {
      if (draftData.surname) document.getElementById("traineeSurname").value = draftData.surname;
      if (draftData.firstname) document.getElementById("traineeFirstname").value = draftData.firstname;
      if (draftData.contact) document.getElementById("traineeContact").value = draftData.contact;
      if (draftData.course) document.getElementById("traineeCourse").value = draftData.course;
      if (draftData.enrollDate) document.getElementById("traineeEnrollDate").value = draftData.enrollDate;
      if (draftData.notes) {
        document.getElementById("traineeNotes").value = draftData.notes;
        const notesCharCount = document.getElementById("notesCharCount");
        if (notesCharCount) notesCharCount.textContent = draftData.notes.length;
      }
      
      showTraineeNotification("Draft loaded from previous session", "info");
    }
  } catch (error) {
    console.error("Error loading trainee draft:", error);
    localStorage.removeItem("traineeDraft");
  }
}
}

// Load recent trainees (mock data for now)
function loadRecentTrainees() {
// This would typically fetch from database
console.log("Loading recent trainees...");

// Mock implementation - in real app, this would be an API call
// The HTML already contains sample trainee cards
}

// Initialize trainee action buttons
function initializeTraineeActions() {
// Edit trainee buttons
const editBtns = document.querySelectorAll('.edit-trainee-btn');
editBtns.forEach(btn => {
  btn.addEventListener('click', function() {
    const traineeCard = this.closest('.trainee-card');
    const traineeName = traineeCard.querySelector('.trainee-name').textContent;
    
    // For now, just show a message
    showTraineeNotification(`Edit functionality for ${traineeName} will be implemented`, 'info');
    
    // In real implementation, this would populate the form with trainee data
    // and switch to edit mode
  });
});

// Delete trainee buttons
const deleteBtns = document.querySelectorAll('.delete-trainee-btn');
deleteBtns.forEach(btn => {
  btn.addEventListener('click', function() {
    const traineeCard = this.closest('.trainee-card');
    const traineeName = traineeCard.querySelector('.trainee-name').textContent;
    
    if (confirm(`Are you sure you want to delete ${traineeName}?`)) {
      // Add deletion animation
      traineeCard.style.animation = 'slideOut 0.3s ease-out forwards';
      
      setTimeout(() => {
        traineeCard.remove();
        showTraineeNotification(`${traineeName} has been removed`, 'success');
      }, 300);
    }
  });
});
}

// Show trainee notification
function showTraineeNotification(message, type = 'info') {
  // Remove any existing notifications first
  const existingNotifications = document.querySelectorAll('.trainee-notification');
  existingNotifications.forEach(notif => notif.remove());
  
  const notification = document.createElement('div');
  notification.className = `trainee-notification notification-${type}`;
  notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 5px;
      color: white;
      font-weight: bold;
      z-index: 9999;
      min-width: 300px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      animation: slideInRight 0.3s ease-out;
  `;
  
  // Set background color based on type
  switch(type) {
      case 'success':
          notification.style.backgroundColor = '#4CAF50';
          break;
      case 'error':
          notification.style.backgroundColor = '#f44336';
          break;
      case 'warning':
          notification.style.backgroundColor = '#ff9800';
          break;
      case 'info':
          notification.style.backgroundColor = '#2196F3';
          break;
      default:
          notification.style.backgroundColor = '#2196F3';
  }
  
  notification.textContent = message;
  document.body.appendChild(notification);
  
  // Remove notification after 4 seconds
  setTimeout(() => {
      if (notification.parentNode) {
          notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
          setTimeout(() => {
              if (notification.parentNode) {
                  notification.parentNode.removeChild(notification);
              }
          }, 300);
      }
  }, 4000);
}

// Add CSS animations for notifications and cards
const traineeStyles = `
@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideOutRight {
  from {
    opacity: 1;
    transform: translateX(0);
  }
  to {
    opacity: 0;
    transform: translateX(100%);
  }
}

@keyframes slideOut {
  from {
    opacity: 1;
    transform: translateX(0);
  }
  to {
    opacity: 0;
    transform: translateX(-100%);
  }
}

.trainee-card.newly-added {
  animation: slideInFromRight 0.5s ease-out;
}
`;

// Add styles if not already present
if (!document.head.querySelector("style[data-trainee-styles]")) {
const styleSheet = document.createElement("style");
styleSheet.textContent = traineeStyles;
styleSheet.setAttribute("data-trainee-styles", "true");
document.head.appendChild(styleSheet);
}

// Global variable to track which trainee is being edited
let currentTraineeBeingEdited = null;
let originalTraineeData = {};

// Initialize edit trainee functionality
function initializeEditTraineeFunctionality() {
  const editTraineeModal = document.getElementById("editTraineeModal");
  const cancelEditTrainee = document.getElementById("cancelEditTrainee");
  const editTraineeForm = document.getElementById("editTraineeForm");

// Function to extract trainee data from a trainee card
function extractTraineeData(traineeCard) {
  const traineeName = traineeCard.querySelector(".trainee-name")?.textContent || "";
  
  // Better parsing of name
  let surname = "", firstname = "";
  if (traineeName.includes(',')) {
      const nameParts = traineeName.split(',').map(name => name.trim());
      surname = nameParts[0] || "";
      firstname = nameParts[1] || "";
  } else {
      // If no comma, assume it's firstname only
      firstname = traineeName.trim();
  }
  
  const traineeDetails = traineeCard.querySelector(".trainee-details")?.textContent || "";
  const contactMatch = traineeDetails.match(/Contact:\s*([^|]+)/);
  const enrolledMatch = traineeDetails.match(/Enrolled:\s*(.+)/);
  
  const contact = contactMatch ? contactMatch[1].trim() : "";
  const enrolledDateStr = enrolledMatch ? enrolledMatch[1].trim() : "";
  
  const courseElement = traineeCard.querySelector(".trainee-course");
  const course = courseElement ? courseElement.textContent.trim() : "";
  
  // Better date parsing
  let formattedDate = "";
  if (enrolledDateStr) {
      try {
          // Handle different date formats
          const date = new Date(enrolledDateStr);
          if (!isNaN(date.getTime())) {
              formattedDate = date.toISOString().split('T')[0];
          }
      } catch (e) {
          console.error("Date parsing error:", e);
      }
  }

  // Try to detect status from the card (if displayed)
  let status = "active"; // default
  const statusElement = traineeCard.querySelector(".trainee-stats span:last-child");
  if (statusElement && statusElement.textContent.toLowerCase().includes('inactive')) {
      status = "inactive";
  } else if (statusElement && statusElement.textContent.toLowerCase().includes('graduated')) {
      status = "graduated";
  }

  const extractedData = {
      surname: surname,
      firstname: firstname,
      contact: contact,
      course: course,
      enrollDate: formattedDate,
      status: status,
      notes: "" // Notes aren't displayed in the card, so default to empty
  };
  
  console.log("Extracted trainee data:", extractedData);
  return extractedData;
}

  // Function to populate edit form with current data
  function populateEditForm(traineeData) {
      document.getElementById("editTraineeId").value = traineeData.id;
      document.getElementById("editTraineeSurname").value = traineeData.surname;
      document.getElementById("editTraineeFirstname").value = traineeData.firstname;
      const midEl = document.getElementById("editTraineeMiddlename");
      if (midEl) midEl.value = traineeData.middlename || '';
      document.getElementById("editTraineeContact").value = traineeData.contact;
      document.getElementById("editTraineeCourse").value = traineeData.course;
      document.getElementById("editTraineeEnrollDate").value = traineeData.enrollDate;
      document.getElementById("editTraineeStatus").value = traineeData.status;
      document.getElementById("editTraineeNotes").value = traineeData.notes;
  }

// Function to update trainee card with new data (FIXED VERSION)
function updateTraineeCard(traineeCard, newData) {
  console.log("Updating trainee card with new data:", newData);
  
  // Update trainee name
  const traineeNameElement = traineeCard.querySelector(".trainee-name");
  if (traineeNameElement) {
      traineeNameElement.textContent = `${newData.surname}, ${newData.firstname}`;
      console.log("Updated name to:", traineeNameElement.textContent);
  }

  // Update contact and enrolled date
  const traineeDetailsElement = traineeCard.querySelector(".trainee-details");
  if (traineeDetailsElement) {
      const formattedDate = new Date(newData.enrollDate).toLocaleDateString();
      traineeDetailsElement.textContent = `Contact: ${newData.contact} | Enrolled: ${formattedDate}`;
      console.log("Updated details to:", traineeDetailsElement.textContent);
  }

  // Update course
  const courseElement = traineeCard.querySelector(".trainee-course");
  if (courseElement) {
      courseElement.textContent = newData.course;
      
      // Update course class for styling
      const allCourseClasses = ['smaw', 'eim', 'ats', 'rac', 'css', 'plumbing', 'masonry', 'carpentry'];
      allCourseClasses.forEach(cls => courseElement.classList.remove(cls));
      
      // Add new course class
      const courseClass = newData.course.toLowerCase().replace(/[^a-z]/g, '');
      if (allCourseClasses.includes(courseClass)) {
          courseElement.classList.add(courseClass);
      }
      console.log("Updated course to:", courseElement.textContent);
  }

  // Update status in stats
  const traineeStats = traineeCard.querySelector(".trainee-stats");
  if (traineeStats) {
      const statusSpan = traineeStats.querySelector("span:last-child");
      if (statusSpan) {
          const statusText = newData.status.charAt(0).toUpperCase() + newData.status.slice(1);
          const statusIcon = newData.status === 'active' ? 'fas fa-graduation-cap' : 
                            newData.status === 'graduated' ? 'fas fa-award' :
                            newData.status === 'inactive' ? 'fas fa-pause' : 'fas fa-flag';
          statusSpan.innerHTML = `<i class="${statusIcon}"></i> ${statusText}`;
          console.log("Updated status to:", statusText);
      }
  }

  // FIXED: Simple success animation that doesn't interfere with content
  traineeCard.style.border = "2px solid var(--green-500)";
  traineeCard.style.boxShadow = "0 0 10px rgba(34, 197, 94, 0.3)";
  
  // Flash effect to show update
  const originalBackground = traineeCard.style.backgroundColor;
  traineeCard.style.backgroundColor = "rgba(34, 197, 94, 0.1)";
  
  setTimeout(() => {
      traineeCard.style.backgroundColor = originalBackground;
      traineeCard.style.border = "";
      traineeCard.style.boxShadow = "";
  }, 1500);
  
  console.log("Card update animation applied");
}

  // Function to check if data has changed
  function hasDataChanged(originalData, newData) {
      return JSON.stringify(originalData) !== JSON.stringify(newData);
  }

  // Add event listeners to all existing edit buttons
  function attachEditListeners() {
      const editButtons = document.querySelectorAll(".edit-trainee-btn");
      editButtons.forEach(button => {
          // Remove existing listeners to prevent duplicates
          button.removeEventListener("click", handleEditClick);
          button.addEventListener("click", handleEditClick);
      });
  }

  // Handle edit button click
  function handleEditClick(event) {
      const traineeCard = event.target.closest(".trainee-card");
      if (!traineeCard) return;

      currentTraineeBeingEdited = traineeCard;
      originalTraineeData = extractTraineeData(traineeCard);
      
      console.log("Editing trainee:", originalTraineeData);
      
      // Populate form with current data
      populateEditForm(originalTraineeData);
      
      // Show modal
      if (editTraineeModal) {
          editTraineeModal.style.display = "flex";
          const modalContent = editTraineeModal.querySelector(".modal-content");
          if (modalContent) {
              modalContent.classList.remove("popOut");
              modalContent.style.animation = "scaleIn 0.25s";
          }
      }
  }

  // Hide modal function
  function hideEditModal() {
      if (editTraineeModal) {
          const modalContent = editTraineeModal.querySelector(".modal-content");
          if (modalContent) {
              modalContent.style.animation = "popOut 0.25s";
              modalContent.classList.add("popOut");
              modalContent.addEventListener(
                  "animationend",
                  function handler() {
                      editTraineeModal.style.display = "none";
                      modalContent.classList.remove("popOut");
                      modalContent.removeEventListener("animationend", handler);
                  }
              );
          } else {
              editTraineeModal.style.display = "none";
          }
      }
      currentTraineeBeingEdited = null;
      originalTraineeData = {};
  }

  // Cancel button event listener
  if (cancelEditTrainee) {
      cancelEditTrainee.addEventListener("click", hideEditModal);
  }

  // Hide modal on overlay click
  if (editTraineeModal) {
      editTraineeModal.addEventListener("click", (e) => {
          if (e.target === editTraineeModal) {
              hideEditModal();
          }
      });
  }

// Form submission handler
if (editTraineeForm) {
  editTraineeForm.addEventListener("submit", function (e) {
      e.preventDefault();
      
      if (!currentTraineeBeingEdited) return;

      const formData = new FormData(editTraineeForm);
      const newData = {
          surname: formData.get("surname"),
          firstname: formData.get("firstname"),
          contact: formData.get("contact"),
          course: formData.get("course"),
          enrollDate: formData.get("enrollDate"),
          status: formData.get("status"),
          notes: formData.get("notes")
      };

      console.log("New trainee data:", newData);
      console.log("Original trainee data:", originalTraineeData);

      // Check if data has actually changed
      if (hasDataChanged(originalTraineeData, newData)) {
          // Show loading state
          const submitBtn = this.querySelector('button[type="submit"]');
          const originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
          submitBtn.disabled = true;

          // Add CSRF token and action to formData
          formData.append('csrf_token', document.getElementById('csrf_token').value);
          formData.append('action', 'update_trainee');
          
          // Add original trainee data to identify the record
          formData.append('original_surname', originalTraineeData.surname);
          formData.append('original_firstname', originalTraineeData.firstname);
          formData.append('original_course', originalTraineeData.course);

          // Log form data for debugging
          console.log('Update trainee form data:');
          for (let [key, value] of formData.entries()) {
              console.log(key + ': ' + value);
          }

          // REAL API CALL
          fetch('apis/trainee_handler.php', {
              method: 'POST',
              body: formData,
              credentials: 'same-origin'
          })
          .then(response => {
              console.log('Response status:', response.status, response.statusText);
              
              if (!response.ok) {
                  throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
              }
              
              return response.text();
          })
          .then(text => {
              console.log('Raw response:', text);
              
              try {
                  const data = JSON.parse(text);
                  console.log('Parsed JSON response:', data);
                  
                  if (data.success) {
                      // Update the trainee card with new data
                      updateTraineeCard(currentTraineeBeingEdited, newData);
                      
                      let message = "Trainee updated successfully!";
                      if (data.action === 'inserted') {
                          message = "Trainee added to database successfully!";
                      }
                      
                      showTraineeNotification(message, "success");
                      
                      // Hide modal
                      hideEditModal();
                      
                  } else {
                      showTraineeNotification(data.message || "Failed to update trainee", "error");
                  }
              } catch (jsonError) {
                  console.error('JSON parse error:', jsonError);
                  console.error('Response was not valid JSON:', text);
                  showTraineeNotification('Server returned invalid response. Check console for details.', 'error');
              }
          })
          .catch(error => {
              console.error('Update error:', error);
              showTraineeNotification("Network error: " + error.message, "error");
          })
          .finally(() => {
              // ALWAYS reset button state
              submitBtn.innerHTML = originalText;
              submitBtn.disabled = false;
          });
              
      } else {
          console.log("No changes detected, keeping original data");
          showTraineeNotification("No changes detected", "info");
          hideEditModal();
      }
  });
}

  // Initialize listeners for existing buttons
  attachEditListeners();

  // Return function to attach listeners to new buttons (for dynamically created trainee cards)
  return {
      attachEditListeners,
      handleEditClick
  };
}

// Initialize the edit functionality when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize edit functionality
  const editTraineeManager = initializeEditTraineeFunctionality();

  // Make edit functionality available globally for new trainee cards
  window.attachTraineeEditListener = function(editButton) {
      if (editButton && editTraineeManager) {
          editButton.addEventListener("click", editTraineeManager.handleEditClick);
      }
  };
});


// Global variable to track if we're editing
let isEditingTrainee = false;
let currentEditingTraineeId = null;

// Initialize trainee edit functionality when DOM loads
document.addEventListener("DOMContentLoaded", () => {
  // Initialize edit functionality after a short delay
  setTimeout(() => {
      initializeTraineeEditButtons();
  }, 1000);
});

// Function to initialize edit button functionality
function initializeTraineeEditButtons() {
  console.log('Initializing trainee edit buttons...');
  
  // Use event delegation for edit buttons (similar to announcement edit)
  document.addEventListener('click', handleTraineeEditButtonClick);
  
  // Modify the form submission to handle both add and edit
  const traineeForm = document.getElementById('traineeForm');
  if (traineeForm) {
      // Remove existing listener and add new one
      traineeForm.removeEventListener('submit', handleTraineeFormSubmit);
      traineeForm.addEventListener('submit', handleTraineeFormSubmit);
  }
  
  console.log('Trainee edit functionality initialized');
}

// Handle edit button clicks using event delegation
function handleTraineeEditButtonClick(event) {
  if (event.target.closest('.edit-trainee-btn')) {
      event.preventDefault();
      event.stopPropagation();
      
      console.log('Trainee edit button clicked!');
      
      const traineeCard = event.target.closest('.trainee-card');
      if (!traineeCard) {
          console.error('Could not find trainee card');
          return;
      }
      
      // Extract trainee data from the card
      const traineeData = extractTraineeDataFromCard(traineeCard);
      console.log('Extracted trainee data:', traineeData);
      
      // Load data into the main form
      loadTraineeDataIntoForm(traineeData);
      
      // Set editing mode
      setTraineeEditingMode(true, traineeCard.dataset.traineeId);
      
      // Scroll to the form
      scrollToTraineeForm();
      
      // Show success message
      showTraineeNotification('Trainee data loaded for editing', 'info');
  }
}

// Function to extract trainee data from card
function extractTraineeDataFromCard(traineeCard) {
  const traineeName = traineeCard.querySelector('.trainee-name')?.textContent || '';
  
  // Parse name (surname, firstname format)
  let surname = '', firstname = '';
  if (traineeName.includes(',')) {
      const nameParts = traineeName.split(',').map(name => name.trim());
      surname = nameParts[0] || '';
      firstname = nameParts[1] || '';
  } else {
      firstname = traineeName.trim();
  }
  
  // Extract other details
  const traineeDetails = traineeCard.querySelector('.trainee-details')?.textContent || '';
  const contactMatch = traineeDetails.match(/Contact:\s*([^|]+)/);
  const enrolledMatch = traineeDetails.match(/Enrolled:\s*(.+)/);
  
  const contact = contactMatch ? contactMatch[1].trim() : '';
  const enrolledDateStr = enrolledMatch ? enrolledMatch[1].trim() : '';
  
  // Convert date to YYYY-MM-DD format
  let formattedDate = '';
  if (enrolledDateStr) {
      try {
          const date = new Date(enrolledDateStr);
          if (!isNaN(date.getTime())) {
              formattedDate = date.toISOString().split('T')[0];
          }
      } catch (e) {
          console.error('Date parsing error:', e);
      }
  }
  
  const courseElement = traineeCard.querySelector('.trainee-course');
  const course = courseElement ? courseElement.textContent.trim() : '';
  
  // Get trainee ID from dataset
  const traineeId = traineeCard.dataset.traineeId || '';
  
  return {
      id: traineeId,
      surname: surname,
      firstname: firstname,
      contact: contact,
      course: course,
      enrollDate: formattedDate,
      notes: '' // We don't display notes in the card, so default to empty
  };
}

// Function to load trainee data into the main form
function loadTraineeDataIntoForm(traineeData) {
  console.log('Loading trainee data into form:', traineeData);
  
  // Populate form fields
  const fields = {
      'traineeSurname': traineeData.surname,
      'traineeFirstname': traineeData.firstname,
      'traineeContact': traineeData.contact,
      'traineeCourse': traineeData.course,
      'traineeEnrollDate': traineeData.enrollDate,
      'traineeNotes': traineeData.notes
  };
  
  // Set values for each field
  Object.keys(fields).forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) {
          field.value = fields[fieldId] || '';
          console.log(`Set ${fieldId} to:`, fields[fieldId]);
      } else {
          console.warn(`Field ${fieldId} not found`);
      }
  });
  
  // Update character count for notes
  const notesCharCount = document.getElementById('notesCharCount');
  if (notesCharCount) {
      notesCharCount.textContent = (traineeData.notes || '').length;
  }
}

// Function to set editing mode
function setTraineeEditingMode(editing, traineeId = null) {
  isEditingTrainee = editing;
  currentEditingTraineeId = traineeId;
  
  // Update the submit button text
  const submitBtn = document.querySelector('#traineeForm button[type="submit"]');
  if (submitBtn) {
      if (editing) {
          submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Trainee';
          submitBtn.classList.remove('publish');
          submitBtn.classList.add('draft'); // Use draft styling for edit mode
      } else {
          submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Add Trainee';
          submitBtn.classList.remove('draft');
          submitBtn.classList.add('publish');
      }
  }
  
  // Add a visual indicator that we're in edit mode
  const formContainer = document.querySelector('.trainee-form-container');
  if (formContainer) {
      if (editing) {
          formContainer.style.border = '2px solid var(--orange-500)';
          formContainer.style.boxShadow = '0 0 10px rgba(249, 115, 22, 0.3)';
          
          // Add edit indicator
          let editIndicator = formContainer.querySelector('.edit-indicator');
          if (!editIndicator) {
              editIndicator = document.createElement('div');
              editIndicator.className = 'edit-indicator';
              editIndicator.innerHTML = '<i class="fas fa-edit"></i> EDITING MODE';
              editIndicator.style.cssText = `
                  background: var(--orange-500);
                  color: white;
                  padding: 0.5rem 1rem;
                  border-radius: 0.5rem;
                  font-weight: bold;
                  font-size: 0.875rem;
                  margin-bottom: 1rem;
                  text-align: center;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  gap: 0.5rem;
              `;
              formContainer.insertBefore(editIndicator, formContainer.firstChild);
          }
      } else {
          formContainer.style.border = '';
          formContainer.style.boxShadow = '';
          
          // Remove edit indicator
          const editIndicator = formContainer.querySelector('.edit-indicator');
          if (editIndicator) {
              editIndicator.remove();
          }
      }
  }
  
  console.log('Editing mode set to:', editing);
}

// Function to scroll to the form
function scrollToTraineeForm() {
  const formContainer = document.querySelector('.trainee-form-container');
  if (formContainer) {
      formContainer.scrollIntoView({ 
          behavior: 'smooth',
          block: 'start'
      });
  }
}

// Function to refresh trainee record data
function refreshTraineeRecordData() {
    // Check if we're on the trainee record page
    if (typeof allStudentsData !== 'undefined' && typeof populateCoursesGrid === 'function') {
        // Fetch fresh student data from the server
        fetch('apis/trainee_handler.php?action=list', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.trainees) {
                // Update the global allStudentsData array
                allStudentsData = data.trainees.map(trainee => ({
                    student_number: trainee.student_number,
                    first_name: trainee.firstname,
                    last_name: trainee.surname,
                    course: trainee.course,
                    final_grade: trainee.final_grade || 0
                }));
                
                // Refresh the course cards
                populateCoursesGrid();
                
                // If we're currently viewing students for a course, refresh that view too
                if (typeof currentSelectedCourse !== 'undefined' && currentSelectedCourse && typeof showStudentsForCourse === 'function') {
                    showStudentsForCourse(currentSelectedCourse);
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing trainee record data:', error);
        });
    }
}

// Enhanced form submission handler
function handleTraineeFormSubmit(e) {
  e.preventDefault();
  
  console.log('Trainee form submitted. Editing mode:', isEditingTrainee);
  
  const formData = new FormData(e.target);
  
  // Get CSRF token
  const csrfToken = document.getElementById('csrf_token');
  if (csrfToken && csrfToken.value) {
      formData.append('csrf_token', csrfToken.value);
      console.log('Trainee form - CSRF token:', csrfToken.value);
  } else {
      console.error('Trainee form - CSRF token not found!');
  }
  
  // Set action based on whether we're editing or adding
  if (isEditingTrainee && currentEditingTraineeId) {
      formData.append('action', 'update_trainee');
      formData.append('trainee_id', currentEditingTraineeId);
      console.log('Updating trainee with ID:', currentEditingTraineeId);
  } else {
      formData.append('action', 'add_trainee');
      console.log('Adding new trainee');
  }
  
  // Log form data for debugging
  console.log('Form data:');
  for (let [key, value] of formData.entries()) {
      console.log(key + ': ' + value);
  }
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  const actionText = isEditingTrainee ? 'Updating' : 'Adding';
  submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${actionText} Trainee...`;
  submitBtn.disabled = true;
  
  // Make API call
  fetch('apis/trainee_handler.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
  })
  .then(response => {
      console.log('Response status:', response.status, response.statusText);
      
      if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
      }
      
      return response.text();
  })
  .then(text => {
      console.log('Raw response:', text);
      
      try {
          const data = JSON.parse(text);
          console.log('Parsed JSON response:', data);
          
          if (data.success) {
              const successMessage = isEditingTrainee ? 'Trainee updated successfully!' : 'Trainee added successfully!';
              showTraineeNotification(successMessage, 'success');
              
              // Reset form and editing mode
              e.target.reset();
              const notesCharCount = document.getElementById('notesCharCount');
              if (notesCharCount) notesCharCount.textContent = '0';
              
              // Exit editing mode
              setTraineeEditingMode(false);
              
              // Clear saved draft
              localStorage.removeItem('traineeDraft');
              
              // Reload trainees list
              // Refresh list only if the section exists
              if (document.getElementById('traineesList')) {
                  loadRecentTraineesFromAPI();
              }
              
              // Refresh Trainee Record page course cards if we're on that page
              if (typeof populateCoursesGrid === 'function' && document.getElementById('coursesGrid')) {
                  // Refresh student data first, then update course cards
                  refreshTraineeRecordData();
              }
              
          } else {
              showTraineeNotification(data.message || 'Failed to save trainee', 'error');
          }
      } catch (jsonError) {
          console.error('JSON parse error:', jsonError);
          showTraineeNotification('Server returned invalid response. Check console for details.', 'error');
      }
  })
  .catch(error => {
      console.error('Fetch error:', error);
      showTraineeNotification('Network error: ' + error.message, 'error');
  })
  .finally(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
  });
}

// Add a "Cancel Edit" button functionality
document.addEventListener('DOMContentLoaded', () => {
  // Add cancel edit functionality to the reset button when in edit mode
  const resetBtn = document.querySelector('#traineeForm button[type="reset"]');
  if (resetBtn) {
      resetBtn.addEventListener('click', () => {
          if (isEditingTrainee) {
              // Exit editing mode
              setTraineeEditingMode(false);
              showTraineeNotification('Edit cancelled', 'info');
          }
          // Set default enrollment date after form reset
          setTimeout(() => {
              setDefaultEnrollmentDate();
          }, 100);
      });
  }

  // Email field is now always visible and required in Add Trainee form
  const emailInput = document.getElementById('traineeEmail');
  const studentNumberInput = document.getElementById('traineeStudentNumber');
  
  // Ensure email field is required
  if (emailInput) {
      emailInput.required = true;
  }
});

// Function to show trainee notifications (reuse existing)
function showTraineeNotification(message, type = 'info') {
  // Remove any existing notifications first
  const existingNotifications = document.querySelectorAll('.trainee-notification');
  existingNotifications.forEach(notif => notif.remove());
  
  const notification = document.createElement('div');
  notification.className = `trainee-notification notification-${type}`;
  notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 5px;
      color: white;
      font-weight: bold;
      z-index: 9999;
      min-width: 300px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      animation: slideInRight 0.3s ease-out;
  `;
  
  // Set background color based on type
  switch(type) {
      case 'success':
          notification.style.backgroundColor = '#4CAF50';
          break;
      case 'error':
          notification.style.backgroundColor = '#f44336';
          break;
      case 'warning':
          notification.style.backgroundColor = '#ff9800';
          break;
      case 'info':
          notification.style.backgroundColor = '#2196F3';
          break;
      default:
          notification.style.backgroundColor = '#2196F3';
  }
  
  notification.textContent = message;
  document.body.appendChild(notification);
  
  // Remove notification after 4 seconds
  setTimeout(() => {
      if (notification.parentNode) {
          notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
          setTimeout(() => {
              if (notification.parentNode) {
                  notification.parentNode.removeChild(notification);
              }
          }, 300);
      }
  }, 4000);
}

console.log('Trainee edit form functionality loaded');




// END OF CODE ONLY
// Make sure the function is available globally
window.editTrainee = editTrainee;

console.log('Trainee edit functionality loaded - exact match to announcements');

// ... existing code ...

// Bind Show more for announcements (mirrors notifications)
const showAllAnnouncementsBtn = document.getElementById('showAllAnnouncementsBtn');
if (showAllAnnouncementsBtn && showAllAnnouncementsBtn.type !== 'button') { showAllAnnouncementsBtn.type = 'button'; }
if (showAllAnnouncementsBtn) {
  showAllAnnouncementsBtn.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();
    openAllAnnouncementsModal();
  });
}
// Delegation fallback if button is rendered later
document.addEventListener('click', function(e){
  var t = e.target;
  if (!t) return;
  var btn = (t.id === 'showAllAnnouncementsBtn') ? t : (t.closest ? t.closest('#showAllAnnouncementsBtn') : null);
  if (btn) {
    e.preventDefault();
    openAllAnnouncementsModal();
  }
});

// ... existing code ...

// Show all announcements in a modal with delete support (mirrors notifications)
function openAllAnnouncementsModal() {
// Support both pre-rendered modal (in DOM) and dynamic creation
let overlay = document.getElementById('allAnnouncementsModalOverlay');
let modal;
let close;

if (overlay) {
  // Show existing overlay and wire close with popOut animation
  overlay.style.display = 'block';
  modal = overlay.querySelector('.modal-content') || overlay.firstElementChild;
  // Clear any lingering popOut state so it can close next time
  if (modal && modal.classList) modal.classList.remove('popOut');
  close = function(){
    var modalEl = overlay.querySelector('.modal-content');
    if (modalEl) {
      modalEl.classList.add('popOut');
      modalEl.addEventListener('animationend', function handleAnim(){
        modalEl.classList.remove('popOut');
        overlay.style.display = 'none';
        modalEl.removeEventListener('animationend', handleAnim);
      });
    } else {
      overlay.style.display = 'none';
    }
  };
  // Close when clicking outside modal content
  overlay.addEventListener('click', function(e){
    var content = overlay.querySelector('.modal-content');
    if (content && !content.contains(e.target)) close();
  });
  // Close on Escape key
  (function(){
    function onEsc(evt){ if (evt.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); } }
    document.addEventListener('keydown', onEsc);
  })();
  // No explicit close button; backdrop click closes
} else {
  // Create overlay and modal dynamically
  overlay = document.createElement('div');
  overlay.id = 'allAnnouncementsModalOverlay';
  overlay.className = 'modal-overlay';
  overlay.style.cssText = 'align-items:center; justify-content:center; background:rgba(15, 23, 42, 0.45);';
  overlay.style.display = 'block';

  modal = document.createElement('div');
  modal.className = 'modal-content announcements-modal';
  modal.style.cssText = [
    'max-width:720px',
    'width:92%',
    'max-height:80vh',
    'overflow:auto',
    'border-radius:10px',
    'padding:16px',
    'box-shadow: 0 10px 30px rgba(0,0,0,0.25)'
  ].join(';');
  modal.innerHTML = `
    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px;">
      <h3 style="margin:0; font-size:18px; color:#0f172a;">All Announcements</h3>
    </div>
    <div id="allAnnouncementsContainer" style="margin-top:4px;"></div>
  `;
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  close = function(){
    var modalEl = overlay.querySelector('.modal-content');
    if (modalEl) {
      modalEl.classList.add('popOut');
      modalEl.addEventListener('animationend', function handleAnim(){
        modalEl.classList.remove('popOut');
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        modalEl.removeEventListener('animationend', handleAnim);
      });
    } else {
      if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }
  };
  // Close when clicking outside modal content
  overlay.addEventListener('click', function(e){
    var content = overlay.querySelector('.modal-content');
    if (content && !content.contains(e.target)) close();
  });
  // Close on Escape key
  (function(){
    function onEsc(evt){ if (evt.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); } }
    document.addEventListener('keydown', onEsc);
  })();
  // No explicit close button; backdrop click closes
}

// Fetch all announcements and render cards mirroring existing layout
const fd = new FormData();
fd.append('action', 'get_announcements');
const csrf = document.getElementById('csrf_token');
if (csrf && csrf.value) fd.append('csrf_token', csrf.value);

fetch('apis/announcement_handler.php', { method: 'POST', body: fd, cache: 'no-store' })
  .then(function(r){ return r.json(); })
  .then(function(result){
    var container = (modal || document).querySelector('#allAnnouncementsContainer');
    if (!container) return;
    if (!result || !result.success) {
      container.innerHTML = '<p style="color:#ef4444">Failed to load announcements</p>';
      return;
    }
    var list = Array.isArray(result.announcements) ? result.announcements : [];
    if (list.length === 0) {
      container.innerHTML = '<p style="color: var(--muted-foreground);">No announcements found.</p>';
      return;
    }
    // Build announcement cards using the same structure as existing cards
    var frag = document.createDocumentFragment();
    list.forEach(function(a){
      var card = document.createElement('div');
      card.className = 'announcement-card';
      card.setAttribute('data-announcement-id', String(a.id));
    card.innerHTML = [
      '<div class="announcement-header">',
      '  <div class="announcement-meta">',
      '    <span class="announcement-type">' + (a.type ? String(a.type) : 'general') + '</span>',
      '    <span class="announcement-date">' + (a.date_created ? String(a.date_created) : '') + '</span>',
      '  </div>',
      '  <div class="announcement-controls">',
      '    <button class="announcement-toggle" data-id="' + String(a.id) + '" data-active="' + String(a.is_active ?? 1) + '">',
      '      ' + ((a.is_active ?? 1) ? 'Active' : 'Inactive') + '',
      '    </button>',
      '    <button class="delete-announcement-btn" title="Delete" data-id="' + String(a.id) + '"><i class="fas fa-trash"></i></button>',
      '  </div>',
      '</div>',
      '<h4 class="announcement-title">' + (a.title ? String(a.title) : '') + '</h4>',
      '<p class="announcement-content">' + (a.content ? String(a.content) : '') + '</p>'
    ].join('');
      frag.appendChild(card);
    });
    container.innerHTML = '';
    container.appendChild(frag);
    // Wire delete buttons to existing deleteAnnouncement flow
    container.querySelectorAll('.delete-announcement-btn').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        var id = this.getAttribute('data-id');
        var cardEl = this.closest('.announcement-card');
        if (id) deleteAnnouncement(parseInt(id, 10), cardEl);
      });
    });
  })
  .catch(function(){
    var container = (modal || document).querySelector('#allAnnouncementsContainer');
    if (container) container.innerHTML = '<p style="color:#ef4444">Network error</p>';
  });
}

// CSV Import Modal functionality
function initializeCsvImport() {
  const importBtn = document.getElementById('importCsvBtn');
  const modal = document.getElementById('csvImportModal');
  const closeBtn = document.getElementById('closeCsvModal');
  const cancelBtn = document.getElementById('cancelCsvImport');
  const chartTypeSelect = document.getElementById('chartTypeSelect');
  const csvFormatInfo = document.getElementById('csvFormatInfo');
  const form = document.getElementById('csvImportForm');
  const submitBtn = document.getElementById('submitCsvImport');

  if (!importBtn || !modal) return;

  // Show modal
  importBtn.addEventListener('click', function() {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  });

  // Hide modal
  function hideModal() {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    form.reset();
    csvFormatInfo.textContent = 'Select a chart type to see expected format';
  }

  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);

  // Close modal when clicking outside
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      hideModal();
    }
  });

  // Update format info based on chart type
  chartTypeSelect.addEventListener('change', function() {
    const formatInfo = {
      'employment': 'Expected columns: course_name, course_code, year, employment_rate<br>Example: "Computer Science", "CS101", "2025", "85.5"',
      'graduates': 'Expected columns: year, course_id, batch, student_count<br>Example: "2025", "CS101", "1", "25"',
      'graduates_course_popularity': 'Expected columns: year, course_id, student_count<br>Example: "2025", "CS101", "25"',
      'industry': 'Expected columns: industry_id, year, batch, student_count<br>Example: "Technology", "2025", "1", "30"'
    };
    
    const selectedType = this.value;
    if (formatInfo[selectedType]) {
      csvFormatInfo.innerHTML = formatInfo[selectedType];
    } else {
      csvFormatInfo.textContent = 'Select a chart type to see expected format';
    }
  });

  // Handle form submission
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(form);
    const fileInput = document.getElementById('csvFileInput');
    
    if (!fileInput.files[0]) {
      alert('Please select a CSV file.');
      return;
    }

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

    fetch('apis/csv_import_simple.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        hideModal();
        window.location.reload();
      } else {
        alert('Import failed: ' + data.message);
      }
    })
    .catch(error => {
      alert('Import failed: ' + error.message);
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-upload"></i> Import CSV';
    });
  });
}

// CSV Delete Modal functionality
function initializeCsvDelete() {
  const deleteBtn = document.getElementById('deleteCsvBtn');
  const deleteModal = document.getElementById('deleteDataModal');
  const closeDeleteBtn = document.getElementById('closeDeleteModal');
  const cancelDeleteBtn = document.getElementById('cancelDelete');
  const confirmDeleteBtn = document.getElementById('confirmDelete');
  const deleteChartTypeSelect = document.getElementById('deleteChartTypeSelect');

  if (!deleteBtn || !deleteModal) return;

  // Show delete modal
  deleteBtn.addEventListener('click', function() {
    deleteModal.classList.add('show');
    document.body.style.overflow = 'hidden';
  });

  // Hide delete modal
  function hideDeleteModal() {
    deleteModal.classList.remove('show');
    document.body.style.overflow = 'auto';
    deleteChartTypeSelect.value = '';
  }

  closeDeleteBtn.addEventListener('click', hideDeleteModal);
  cancelDeleteBtn.addEventListener('click', hideDeleteModal);

  // Close modal when clicking outside
  deleteModal.addEventListener('click', function(e) {
    if (e.target === deleteModal) {
      hideDeleteModal();
    }
  });

  // Handle delete confirmation
  confirmDeleteBtn.addEventListener('click', function() {
    const dataType = deleteChartTypeSelect.value;
    
    if (!dataType) {
      alert('Please select a data type to delete.');
      return;
    }

    const typeNames = {
      'employment': 'Employment Data',
      'graduates': 'Graduates Data',
      'graduates_course_popularity': 'Graduates Course Popularity Data',
      'industry': 'Industry Data',
      'all': 'ALL data files'
    };

    if (confirm(`Are you sure you want to delete ${typeNames[dataType]}? This action cannot be undone.`)) {
      // Disable button
      confirmDeleteBtn.disabled = true;
      confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

      const formData = new FormData();
      formData.append('data_type', dataType);
      
      fetch('apis/csv_delete_handler.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          hideDeleteModal();
          window.location.reload();
        } else {
          alert('Delete failed: ' + data.message);
        }
      })
      .catch(error => {
        alert('Delete failed: ' + error.message);
      })
      .finally(() => {
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Data';
      });
    }
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    initializeCsvImport();
    initializeCsvDelete();
  });
} else {
  initializeCsvImport();
  initializeCsvDelete();
}

// Attendance Management Functions
function initializeAttendanceManagement() {
  console.log('Initializing attendance management...');
  
  // Tab switching for attendance
  const attendanceTab = document.querySelector('[data-tab="attendance"]');
  if (attendanceTab) {
    console.log('Attendance tab found, adding event listener');
    attendanceTab.addEventListener('click', function() {
      console.log('Attendance tab clicked');
      // Switch to attendance tab
      document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.style.display = 'none';
      });
      
      this.classList.add('active');
      document.querySelector('[data-panel="attendance"]').style.display = 'block';
      
      // Load attendance data for current batch
      const activeBatchTab = document.querySelector('.batch-tab.active');
      if (activeBatchTab) {
        const currentBatch = activeBatchTab.getAttribute('data-batch');
        loadAttendanceData(currentBatch);
      } else {
        console.log('No active batch tab found when switching to attendance tab');
      }
    });
  } else {
    console.log('Attendance tab not found');
  }

  // Batch tab switching
  const batchTabs = document.querySelectorAll('.batch-tab');
  batchTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Remove active class from all batch tabs
      batchTabs.forEach(t => t.classList.remove('active'));
      // Add active class to clicked tab
      this.classList.add('active');
      
      const batch = this.getAttribute('data-batch');
      // Update attendance data for selected batch
      loadAttendanceData(batch);
    });
  });

  // Export attendance button
  const exportAttendanceBtn = document.getElementById('exportAttendanceBtn');
  if (exportAttendanceBtn) {
    exportAttendanceBtn.addEventListener('click', function() {
      exportAttendanceData();
    });
  }

  // Date change handler
  const attendanceDate = document.getElementById('attendanceDate');
  if (attendanceDate) {
    attendanceDate.addEventListener('change', function() {
      loadAttendanceForDate(this.value);
    });
  }

  // Initialize attendance data
  const activeBatchTab = document.querySelector('.batch-tab.active');
  if (activeBatchTab) {
    const currentBatch = activeBatchTab.getAttribute('data-batch');
    loadAttendanceData(currentBatch);
  } else {
    console.log('No active batch tab found - attendance management may not be available');
  }
}

function initializeAttendanceModal() {
  const attendanceModal = document.getElementById('attendanceModal');
  const attendanceModalClose = document.getElementById('attendanceModalClose');
  const cancelAttendanceBtn = document.getElementById('cancelAttendanceBtn');
  const attendanceForm = document.getElementById('attendanceForm');

  // Close modal
  if (attendanceModalClose) {
    attendanceModalClose.addEventListener('click', function() {
      attendanceModal.style.display = 'none';
    });
  }

  if (cancelAttendanceBtn) {
    cancelAttendanceBtn.addEventListener('click', function() {
      attendanceModal.style.display = 'none';
    });
  }

  // Batch selection in modal
  const modalBatchTabs = document.querySelectorAll('#attendanceModal .batch-tab');
  modalBatchTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      modalBatchTabs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      
      const batch = this.getAttribute('data-batch');
      document.getElementById('attendanceBatch').value = batch;
      loadAttendanceHistory(batch);
    });
  });

  // Form submission
  if (attendanceForm) {
    attendanceForm.addEventListener('submit', function(e) {
      e.preventDefault();
      saveAttendance();
    });
  }

  // Set default date to today
  const attendanceDate = document.getElementById('attendanceDate');
  if (attendanceDate) {
    attendanceDate.value = new Date().toISOString().split('T')[0];
  }
}

function manageAttendance(studentId) {
  const attendanceModal = document.getElementById('attendanceModal');
  const attendanceStudentId = document.getElementById('attendanceStudentId');
  const attendanceStudentName = document.getElementById('attendanceStudentName');
  const attendanceStudentCourse = document.getElementById('attendanceStudentCourse');

  // Get student data
  const studentData = window.studentGradeData[studentId];
  if (studentData) {
    attendanceStudentName.textContent = `${studentData.firstName} ${studentData.lastName}`;
    attendanceStudentCourse.textContent = `Course: ${studentData.course}`;
  }

  attendanceStudentId.value = studentId;
  attendanceModal.style.display = 'flex';
  
  // Load attendance history for current batch
  const currentBatch = document.querySelector('#attendanceModal .batch-tab.active').getAttribute('data-batch');
  loadAttendanceHistory(currentBatch);
}

function loadAttendanceData(batch) {
  console.log('Loading attendance data for batch:', batch);
  
  // Load attendance for today's date by default
  const today = new Date().toISOString().split('T')[0];
  loadAttendanceForDate(today);
}

function loadAttendanceForDate(date) {
  console.log('Loading attendance for date:', date);
  
  // Reset all attendance statuses
  const statusElements = document.querySelectorAll('.attendance-status .status-indicator');
  statusElements.forEach(element => {
    element.textContent = 'Not marked';
    element.className = 'status-indicator';
  });
  
  // Reset summary counters
  updateAttendanceSummary();
  
  // Here you would fetch actual attendance data from the server for the selected date
  // For now, we'll simulate some data
  const sampleAttendance = {
    '2024-01-15': {
      'STU001': { status: 'present', score: 100 },
      'STU002': { status: 'absent', score: 50 },
      'STU003': { status: 'present', score: 100 }
    }
  };
  
  if (sampleAttendance[date]) {
    Object.keys(sampleAttendance[date]).forEach(studentId => {
      const attendance = sampleAttendance[date][studentId];
      updateStudentAttendanceStatus(studentId, attendance.status, attendance.score);
    });
  }
}

function loadAttendanceHistory(batch) {
  const attendanceHistoryBody = document.getElementById('attendanceHistoryBody');
  if (attendanceHistoryBody) {
    // Clear existing data
    attendanceHistoryBody.innerHTML = '';
    
    // Here you would fetch actual attendance history from the server
    // For now, we'll show placeholder data
    const sampleData = [
      { date: '2024-01-15', time: '08:00', status: 'Present', notes: 'On time' },
      { date: '2024-01-16', time: '08:05', status: 'Present', notes: 'Slightly late' },
      { date: '2024-01-17', time: '', status: 'Absent', notes: 'Sick leave' },
      { date: '2024-01-18', time: '08:00', status: 'Present', notes: 'On time' }
    ];
    
    sampleData.forEach(record => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${record.date}</td>
        <td>${record.time || '-'}</td>
        <td><span class="badge ${record.status === 'Present' ? 'bg-success' : 'bg-danger'}">${record.status}</span></td>
        <td>${record.notes}</td>
        <td>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteAttendanceRecord('${record.date}')">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      `;
      attendanceHistoryBody.appendChild(row);
    });
  }
}

function markAttendance(studentId, status, score) {
  console.log('markAttendance called with:', studentId, status, score);
  
  const selectedDate = document.getElementById('attendanceDate').value;
  const currentBatch = document.querySelector('.batch-tab.active').getAttribute('data-batch');
  
  console.log(`Marking attendance for student ${studentId}: ${status} (${score}) on ${selectedDate} for batch ${currentBatch}`);
  
  // Update the UI immediately
  updateStudentAttendanceStatus(studentId, status, score);
  
  // Send to server
  const formData = new FormData();
  formData.append('action', 'mark_attendance');
  formData.append('student_id', studentId);
  formData.append('status', status);
  formData.append('score', score);
  formData.append('date', selectedDate);
  formData.append('batch', currentBatch);
  
  // Add CSRF token if available
  const csrfToken = document.getElementById('csrf_token');
  if (csrfToken) {
    formData.append('csrf_token', csrfToken.value);
  }
  
  // Send to server (you would implement this endpoint)
  fetch('apis/attendance_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Attendance saved successfully');
      updateAttendanceSummary();
    } else {
      console.error('Error saving attendance:', data.message);
      // Revert UI changes on error
      updateStudentAttendanceStatus(studentId, 'not_marked', 0);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    // Revert UI changes on error
    updateStudentAttendanceStatus(studentId, 'not_marked', 0);
  });
}

function updateStudentAttendanceStatus(studentId, status, score) {
  const statusElement = document.getElementById(`status-${studentId}`);
  if (statusElement) {
    const indicator = statusElement.querySelector('.status-indicator');
    if (indicator) {
      if (status === 'present') {
        indicator.textContent = 'Present (100)';
        indicator.className = 'status-indicator present';
      } else if (status === 'absent') {
        indicator.textContent = 'Absent (50)';
        indicator.className = 'status-indicator absent';
      } else {
        indicator.textContent = 'Not marked';
        indicator.className = 'status-indicator';
      }
    }
  }
  
  // Update the student card visual state
  const studentCard = document.querySelector(`[data-student-id="${studentId}"]`);
  if (studentCard) {
    // Remove previous status classes
    studentCard.classList.remove('marked-present', 'marked-absent');
    
    if (status === 'present') {
      studentCard.classList.add('marked-present');
    } else if (status === 'absent') {
      studentCard.classList.add('marked-absent');
    }
  }
}

function updateAttendanceSummary() {
  const presentElements = document.querySelectorAll('.status-indicator.present');
  const absentElements = document.querySelectorAll('.status-indicator.absent');
  
  const totalPresent = presentElements.length;
  const totalAbsent = absentElements.length;
  
  document.getElementById('totalPresent').textContent = totalPresent;
  document.getElementById('totalAbsent').textContent = totalAbsent;
}

function deleteAttendanceRecord(date) {
  if (confirm('Are you sure you want to delete this attendance record?')) {
    // Here you would send a delete request to the server
    console.log('Deleting attendance record for date:', date);
    // For now, just remove from the table
    const row = event.target.closest('tr');
    row.remove();
  }
}

function showAddAttendanceForm() {
  // This function would show a form to add attendance for multiple students
  alert('Add attendance form would open here');
}

function exportAttendanceData() {
  // This function would export attendance data to CSV
  alert('Exporting attendance data...');
}

// Initialize attendance management when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  initializeAttendanceManagement();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAttendanceManagement);
} else {
  initializeAttendanceManagement();
}

// Add Course functionality
document.addEventListener('DOMContentLoaded', function() {
  initializeAddCourseFunctionality();
  // initializeDeleteCourseFunctionality(); // Removed - no longer needed with single-row interface
  loadCoursesFromDatabase();
  
  // Initialize single-row course management
  initializeSingleRowCourseManagement();
  
  // Checkbox functionality removed - no longer needed with single-row interface
  
  // Also populate Quick Enrollment dropdown on page load
  setTimeout(() => {
    console.log('Attempting to populate Quick Enrollment dropdown...');
    populateQuickEnrollmentDropdownFromAPI();
  }, 500);
  
  // Also try to populate immediately
  console.log('Attempting immediate population of Quick Enrollment dropdown...');
  populateQuickEnrollmentDropdownFromAPI();
  
  // Make the function globally available for manual testing
  window.populateQuickEnrollmentDropdown = populateQuickEnrollmentDropdown;
  window.populateQuickEnrollmentDropdownFromAPI = populateQuickEnrollmentDropdownFromAPI;
  
  // Test function to manually populate with sample data
  window.testPopulateDropdown = function() {
    console.log('Testing dropdown population with sample data...');
    const sampleCourses = [
      { id: 1, code: 'RAC', name: 'Refrigeration and Air Conditioning', is_active: 1 },
      { id: 2, code: 'SMAW', name: 'Shielded Metal Arc Welding', is_active: 1 },
      { id: 3, code: 'EIM', name: 'Electrical Installation and Maintenance', is_active: 1 }
    ];
    populateQuickEnrollmentDropdown(sampleCourses);
  };
  
  // Test function for debugging delete functionality
  window.testDeleteAPI = function() {
    console.log('Testing delete API...');
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('course_ids', JSON.stringify([1, 2])); // Test with IDs 1 and 2
    
    fetch('apis/course_admin.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => {
      console.log('Test response status:', response.status);
      return response.text();
    })
    .then(text => {
      console.log('Test response text:', text);
      try {
        const data = JSON.parse(text);
        console.log('Test response data:', data);
      } catch (e) {
        console.log('Response is not JSON:', text);
      }
    })
    .catch(error => {
      console.error('Test error:', error);
    });
  };
  
  // Simple test to check if API is accessible
  window.testAPIAccess = function() {
    console.log('Testing API access...');
    fetch('apis/course_admin.php?action=list', {
      credentials: 'same-origin'
    })
    .then(response => {
      console.log('List API response status:', response.status);
      return response.text();
    })
    .then(text => {
      console.log('List API response text:', text);
    })
    .catch(error => {
      console.error('List API error:', error);
    });
  };
  
  // Add refresh button functionality
  const refreshTableBtn = document.getElementById('refreshTableBtn');
  if (refreshTableBtn) {
    refreshTableBtn.addEventListener('click', function() {
      addCheckboxesToExistingRows();
      updateDeleteButtonVisibility();
      showNotification('Table refreshed! Checkboxes added to existing rows.', 'success');
    });
  }
});

function initializeAddCourseFunctionality() {
  const addCourseBtn = document.getElementById('addCourseBtn');
  const addCourseModal = document.getElementById('addCourseModal');
  const cancelAddCourseBtn = document.getElementById('cancelAddCourseBtn');
  const addCourseForm = document.getElementById('addCourseForm');
  const courseNameInput = document.getElementById('courseName');
  const courseCodeInput = document.getElementById('courseCode');
  const coursesTableBody = document.getElementById('coursesTableBody');

  // Open modal when Add Course button is clicked
  if (addCourseBtn) {
    addCourseBtn.addEventListener('click', function() {
      if (addCourseModal) {
        addCourseModal.style.display = 'flex';
        // Focus on the first input
        setTimeout(() => {
          if (courseNameInput) courseNameInput.focus();
        }, 100);
      }
    });
  }

  // Close modal when Cancel button is clicked
  if (cancelAddCourseBtn) {
    cancelAddCourseBtn.addEventListener('click', function() {
      closeAddCourseModal();
    });
  }

  // Close modal when clicking outside of it
  if (addCourseModal) {
    addCourseModal.addEventListener('click', function(e) {
      if (e.target === addCourseModal) {
        closeAddCourseModal();
      }
    });
  }

  // Handle form submission
  if (addCourseForm) {
    addCourseForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const courseName = courseNameInput.value.trim();
      const courseCode = courseCodeInput.value.trim().toUpperCase();
      
      if (!courseName || !courseCode) {
        alert('Please fill in both course name and course code.');
        return;
      }
      
      // Check if course code already exists
      if (isCourseCodeExists(courseCode)) {
        alert('Course code already exists. Please use a different code.');
        return;
      }
      
      // Save course to database first
      saveCourseToDatabase(courseName, courseCode);
    });
  }

  // Auto-generate course code from course name
  if (courseNameInput) {
    courseNameInput.addEventListener('input', function() {
      const courseName = this.value.trim();
      if (courseName && !courseCodeInput.value) {
        // Generate code from first letters of each word
        const words = courseName.split(' ').filter(word => word.length > 0);
        const code = words.map(word => word.charAt(0).toUpperCase()).join('');
        courseCodeInput.value = code;
      }
    });
  }

  // Update graduate course filter when new course is added
  function updateGraduateCourseFilter(courseName) {
    const graduateCourseFilter = document.getElementById('graduateCourseFilter');
    if (!graduateCourseFilter) return;
    
    // Check if course already exists in dropdown
    const existingOption = Array.from(graduateCourseFilter.options).find(option => option.value === courseName);
    if (existingOption) return; // Course already exists
    
    // Add new course option
    const newOption = document.createElement('option');
    newOption.value = courseName;
    newOption.textContent = courseName;
    graduateCourseFilter.appendChild(newOption);
    
    console.log(`Added new course "${courseName}" to graduate course filter`);
  }

  // Populate graduate course filter dropdown
  function populateGraduateCourseFilter(courses) {
    const graduateCourseFilter = document.getElementById('graduateCourseFilter');
    console.log('populateGraduateCourseFilter called with courses:', courses);
    console.log('graduateCourseFilter element:', graduateCourseFilter);
    
    if (!graduateCourseFilter) {
      console.error('graduateCourseFilter element not found!');
      console.log('Graduate course filter element not found!');
      return;
    }
    
    // Clear existing options except the first one
    graduateCourseFilter.innerHTML = '<option value="">All Courses</option>';
    
    // Filter only active courses
    const activeCourses = courses.filter(course => course.is_active == 1);
    console.log('Active courses for graduate filter:', activeCourses);
    console.log('Number of active courses:', activeCourses.length);
    
    // Add course options to dropdown
    activeCourses.forEach(course => {
      console.log('Adding course:', course.name, 'with code:', course.code);
      const option = document.createElement('option');
      option.value = course.name;
      option.textContent = course.name;
      graduateCourseFilter.appendChild(option);
    });
    
    console.log('Graduate course filter populated with', activeCourses.length, 'courses');
    
    // Debug logging only
    if (activeCourses.length === 0) {
      console.log('No active courses found in database!');
    } else {
      console.log(`Graduate course filter populated with ${activeCourses.length} courses`);
    }
  }

  // Update trainee course dropdown when new course is added
  function updateTraineeCourseDropdown(courseName, courseCode) {
    const traineeCourseSelect = document.getElementById('traineeCourse');
    if (!traineeCourseSelect) return;
    
    // Check if course already exists in dropdown
    const existingOption = Array.from(traineeCourseSelect.options).find(option => option.value === courseName);
    if (existingOption) return; // Course already exists
    
    // Add new course option
    const newOption = document.createElement('option');
    newOption.value = courseName; // Store full database name for proper matching
    
    // Display name without parentheses and not in all caps
    let displayName = courseName;
    // Remove parentheses and their contents
    displayName = displayName.replace(/\s*\([^)]*\)\s*/g, '');
    // Convert to title case (first letter of each word capitalized)
    displayName = displayName.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
    
    newOption.textContent = displayName;
    traineeCourseSelect.appendChild(newOption);
    
    console.log(`Added new course "${courseName}" to trainee course dropdown`);
  }

  // Remove course from trainee course dropdown when course is deleted
  function removeCourseFromTraineeDropdown(courseName) {
    const traineeCourseSelect = document.getElementById('traineeCourse');
    if (!traineeCourseSelect) return;
    
    // Find and remove the course option
    const courseOption = Array.from(traineeCourseSelect.options).find(option => option.value === courseName);
    if (courseOption) {
      courseOption.remove();
      console.log(`Removed course "${courseName}" from trainee course dropdown`);
    }
  }

  function closeAddCourseModal() {
    if (addCourseModal) {
      addCourseModal.style.display = 'none';
    }
    // Reset form
    if (addCourseForm) {
      addCourseForm.reset();
    }
  }

  function isCourseCodeExists(code) {
    if (!coursesTableBody) return false;
    
    const existingRows = coursesTableBody.querySelectorAll('tr');
    for (let row of existingRows) {
      const codeCell = row.querySelector('td:first-child');
      if (codeCell && codeCell.textContent.trim().toUpperCase() === code) {
        return true;
      }
    }
    return false;
  }

  function saveCourseToDatabase(courseName, courseCode) {
    // Show loading state
    const saveBtn = document.getElementById('saveCourseBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('code', courseCode);
    formData.append('name', courseName);
    
    // Send request to API
    fetch('apis/course_admin.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Add course to table with the database ID
          addCourseToTable(courseName, courseCode, data.id);
          
          // Update graduate course filter dropdown
          updateGraduateCourseFilter(courseName);
          
          // Update trainee course dropdown
          updateTraineeCourseDropdown(courseName, courseCode);
          
          // Refresh the course selection dropdown
          loadCoursesFromDatabase();
          
          // Close modal and reset form
          closeAddCourseModal();
          
          // Show success message
          showNotification('Course added successfully!', 'success');
        } else {
          // Show error message
          alert(data.message || 'Failed to save course');
        }
      })
      .catch(error => {
        console.error('Error saving course:', error);
        alert('Failed to save course. Please try again.');
      })
      .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
      });
  }

  function addCourseToTable(courseName, courseCode, courseId = null) {
    if (!coursesTableBody) return;
    
    // Remove loading row if it exists
    const loadingRow = coursesTableBody.querySelector('.loading-row');
    if (loadingRow) {
      loadingRow.remove();
    }
    
    // Use database ID if provided, otherwise generate a unique ID
    let newId;
    if (courseId) {
      newId = courseId;
    } else {
      const existingRows = coursesTableBody.querySelectorAll('tr');
      const maxId = Math.max(...Array.from(existingRows).map(row => {
        const saveBtn = row.querySelector('.save-course');
        return saveBtn ? parseInt(saveBtn.getAttribute('data-id')) || 0 : 0;
      }));
      newId = maxId + 1;
    }
    
    // Create new table row
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
      <td>
        <input type="checkbox" class="course-row-checkbox" data-course-id="${newId}">
      </td>
      <td>${courseCode}</td>
      <td>${courseName}</td>
      <td>
        <select data-id="${newId}" class="course-status">
          <option value="upcoming" selected>upcoming</option>
          <option value="ongoing">ongoing</option>
          <option value="completed">completed</option>
          <option value="cancelled">cancelled</option>
        </select>
      </td>
      <td>
        <input type="date" value="" class="course-start" data-id="${newId}">
      </td>
      <td>
        <input type="date" value="" class="course-end" data-id="${newId}">
      </td>
      <td>
        <input type="number" min="1" value="90" class="course-dur" data-id="${newId}" style="max-width:120px;">
      </td>
      <td>
        <select data-id="${newId}" class="course-active">
          <option value="1" selected>Yes</option>
          <option value="0">No</option>
        </select>
      </td>
      <td>
        <button class="save-course" data-id="${newId}">Save</button>
      </td>
    `;
    
    // Add the new row to the table
    coursesTableBody.appendChild(newRow);
    
    // Update course counts if they exist
    updateCourseCounts();
  }

  function showNotification(message, type = 'info') {
    // Create a simple notification
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${type === 'success' ? '#10b981' : '#3b82f6'};
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 10000;
      font-weight: 500;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 3000);
  }

} // End of initializeAddCourseFunctionality

// Function to populate graduate course filter from API
function populateGraduateCourseFilterFromAPI() {
  console.log('populateGraduateCourseFilterFromAPI called');
  
  // Fetch courses from API
  fetch('apis/course_admin.php?action=list', {
    credentials: 'same-origin'
  })
    .then(response => {
      console.log('API response status:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('API response data:', data);
      if (data.success && data.data) {
        console.log('Courses loaded for graduate filter:', data.data);
        console.log('Number of courses found:', data.data.length);
        populateGraduateCourseFilter(data.data);
      } else {
        console.error('Failed to load courses for graduate filter:', data);
        // Debug logging only
        console.log('Failed to load courses: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error loading courses for graduate filter:', error);
      // Debug logging only
      console.log('Error loading courses: ' + error.message);
    });
}

function loadCoursesFromDatabase() {
  console.log('Loading courses for single-row management...');
  
  // Fetch courses from API
  fetch('apis/course_admin.php?action=list', {
    credentials: 'same-origin'
  })
    .then(response => response.json())
    .then(data => {
      if (data.success && data.data) {
        console.log('Courses loaded successfully:', data.data);
        
        // Populate the course selection dropdown
        populateCourseSelectionDropdown(data.data);
        
        // Update course counts
        updateCourseCounts();
        
        // Also populate the Quick Enrollment dropdown
        populateQuickEnrollmentDropdown(data.data);
        
        // Also populate the trainee course dropdown
        populateTraineeCourseDropdown(data.data);
        
        // Also populate the graduate course filter
        populateGraduateCourseFilter(data.data);
      } else {
        console.error('Failed to load courses:', data);
      }
    })
    .catch(error => {
      console.error('Error loading courses:', error);
    });
}

// Function to populate the course selection dropdown
function populateCourseSelectionDropdown(courses) {
  const courseSelection = document.getElementById('courseSelection');
  console.log('populateCourseSelectionDropdown called with courses:', courses);
  console.log('courseSelection element:', courseSelection);
  
  if (!courseSelection) {
    console.error('courseSelection element not found!');
    return;
  }
  
  // Clear existing options except the first one
  courseSelection.innerHTML = '<option value="">Choose a course to manage...</option>';
  
  // Add course options to dropdown
  courses.forEach(course => {
    const option = document.createElement('option');
    option.value = course.id;
    option.textContent = `${course.code} - ${course.name}`;
    option.setAttribute('data-course', JSON.stringify(course));
    courseSelection.appendChild(option);
  });
  
  console.log('Course selection dropdown populated with', courses.length, 'courses');
}

// Function to handle course selection
function handleCourseSelection() {
  const courseSelection = document.getElementById('courseSelection');
  const courseDetailsRow = document.getElementById('courseDetailsRow');
  
  if (!courseSelection || !courseDetailsRow) return;
  
  const selectedOption = courseSelection.options[courseSelection.selectedIndex];
  
  if (selectedOption.value === '') {
    // No course selected, hide details
    courseDetailsRow.style.display = 'none';
    return;
  }
  
  // Get course data from the option
  const courseData = JSON.parse(selectedOption.getAttribute('data-course'));
  console.log('Selected course:', courseData);
  
  // Populate the form fields
  document.getElementById('selectedCourseCode').value = courseData.code;
  document.getElementById('selectedCourseStatus').value = courseData.status || 'upcoming';
  document.getElementById('selectedCourseStartDate').value = courseData.start_date || '';
  document.getElementById('selectedCourseEndDate').value = courseData.end_date || '';
  document.getElementById('selectedCourseDuration').value = courseData.default_duration_days || 90;
  document.getElementById('selectedCourseActive').value = courseData.is_active || 1;
  
  // Show the details row
  courseDetailsRow.style.display = 'block';
}

// Function to save course changes
function saveCourseChanges() {
  const courseSelection = document.getElementById('courseSelection');
  const selectedOption = courseSelection.options[courseSelection.selectedIndex];
  
  if (!selectedOption || selectedOption.value === '') {
    alert('Please select a course first.');
    return;
  }
  
  const courseId = selectedOption.value;
  const courseData = JSON.parse(selectedOption.getAttribute('data-course'));
  
  // Get form values
  const formData = new FormData();
  formData.append('action', 'update');
  formData.append('id', courseId);
  formData.append('status', document.getElementById('selectedCourseStatus').value);
  formData.append('start_date', document.getElementById('selectedCourseStartDate').value);
  formData.append('end_date', document.getElementById('selectedCourseEndDate').value);
  formData.append('default_duration_days', document.getElementById('selectedCourseDuration').value);
  formData.append('is_active', document.getElementById('selectedCourseActive').value);
  
  console.log('Saving course changes for ID:', courseId);
  
  // Send update request
  fetch('apis/course_admin.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Course updated successfully');
      alert('Course updated successfully!');
      // Reload courses to refresh the dropdown
      loadCoursesFromDatabase();
    } else {
      console.error('Failed to update course:', data.message);
      alert('Failed to update course: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error updating course:', error);
    alert('Error updating course. Please try again.');
  });
}

// Function to reset course form
function resetCourseForm() {
  const courseSelection = document.getElementById('courseSelection');
  const courseDetailsRow = document.getElementById('courseDetailsRow');
  
  if (courseSelection) {
    courseSelection.selectedIndex = 0;
  }
  
  if (courseDetailsRow) {
    courseDetailsRow.style.display = 'none';
  }
  
  // Clear form fields
  document.getElementById('selectedCourseCode').value = '';
  document.getElementById('selectedCourseStatus').value = 'upcoming';
  document.getElementById('selectedCourseStartDate').value = '';
  document.getElementById('selectedCourseEndDate').value = '';
  document.getElementById('selectedCourseDuration').value = '90';
  document.getElementById('selectedCourseActive').value = '1';
}

// Initialize single-row course management
function initializeSingleRowCourseManagement() {
  console.log('Initializing single-row course management...');
  
  // Add event listener for course selection dropdown
  const courseSelection = document.getElementById('courseSelection');
  if (courseSelection) {
    courseSelection.addEventListener('change', handleCourseSelection);
    console.log('Course selection dropdown event listener added');
  } else {
    console.error('Course selection dropdown not found');
  }
  
  // Add event listener for save button
  const saveButton = document.getElementById('saveCourseChanges');
  if (saveButton) {
    saveButton.addEventListener('click', saveCourseChanges);
    console.log('Save button event listener added');
  } else {
    console.error('Save button not found');
  }
  
  // Add event listener for reset button
  const resetButton = document.getElementById('resetCourseForm');
  if (resetButton) {
    resetButton.addEventListener('click', resetCourseForm);
    console.log('Reset button event listener added');
  } else {
    console.error('Reset button not found');
  }
  
  // Make functions globally available for testing
  window.handleCourseSelection = handleCourseSelection;
  window.saveCourseChanges = saveCourseChanges;
  window.resetCourseForm = resetCourseForm;
  window.populateCourseSelectionDropdown = populateCourseSelectionDropdown;
}

// Function to populate the Quick Enrollment course code dropdown
function populateQuickEnrollmentDropdown(courses) {
  const enrollCourseCodeSelect = document.getElementById('enrollCourseCode');
  console.log('populateQuickEnrollmentDropdown called with courses:', courses);
  console.log('enrollCourseCodeSelect element:', enrollCourseCodeSelect);
  
  if (!enrollCourseCodeSelect) {
    console.error('enrollCourseCodeSelect element not found!');
    return;
  }
  
  // Clear existing options except the first one
  enrollCourseCodeSelect.innerHTML = '<option value="">Select Course Code</option>';
  
  // Filter only active courses
  const activeCourses = courses.filter(course => course.is_active == 1);
  console.log('Active courses:', activeCourses);
  
  // Add course options to dropdown
  activeCourses.forEach(course => {
    const option = document.createElement('option');
    option.value = course.code;
    option.textContent = `${course.code} - ${course.name}`;
    enrollCourseCodeSelect.appendChild(option);
  });
  
  console.log('Dropdown populated with', activeCourses.length, 'courses');
}

// Function to populate the trainee course dropdown
function populateTraineeCourseDropdown(courses) {
  const traineeCourseSelect = document.getElementById('traineeCourse');
  console.log('populateTraineeCourseDropdown called with courses:', courses);
  console.log('traineeCourseSelect element:', traineeCourseSelect);
  
  if (!traineeCourseSelect) {
    console.error('traineeCourseSelect element not found!');
    return;
  }
  
  // Clear existing options except the first one
  traineeCourseSelect.innerHTML = '<option value="">Select Course</option>';
  
  // Filter only active courses
  const activeCourses = courses.filter(course => course.is_active == 1);
  console.log('Active courses for trainee dropdown:', activeCourses);
  
  // Add course options to dropdown
  activeCourses.forEach(course => {
    const option = document.createElement('option');
    option.value = course.name; // Store full database name for proper matching
    
    // Display name without parentheses and not in all caps
    let displayName = course.name;
    // Remove parentheses and their contents
    displayName = displayName.replace(/\s*\([^)]*\)\s*/g, '');
    // Convert to title case (first letter of each word capitalized)
    displayName = displayName.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
    
    option.textContent = displayName;
    traineeCourseSelect.appendChild(option);
  });
  
  console.log('Trainee course dropdown populated with', activeCourses.length, 'courses');
}

// Function to populate Quick Enrollment dropdown from API
function populateQuickEnrollmentDropdownFromAPI() {
  console.log('populateQuickEnrollmentDropdownFromAPI called');
  
  // Wait for the element to be available
  const waitForElement = (selector, timeout = 5000) => {
    return new Promise((resolve, reject) => {
      const element = document.getElementById(selector);
      if (element) {
        resolve(element);
        return;
      }
      
      const startTime = Date.now();
      const checkInterval = setInterval(() => {
        const element = document.getElementById(selector);
        if (element) {
          clearInterval(checkInterval);
          resolve(element);
        } else if (Date.now() - startTime > timeout) {
          clearInterval(checkInterval);
          reject(new Error(`Element ${selector} not found within ${timeout}ms`));
        }
      }, 100);
    });
  };
  
  waitForElement('enrollCourseCode')
    .then(enrollCourseCodeSelect => {
      console.log('enrollCourseCodeSelect element found:', enrollCourseCodeSelect);
      console.log('Fetching courses from API...');
      
      // Fetch courses from API
      return fetch('apis/course_admin.php?action=list', {
        credentials: 'same-origin'
      });
    })
    .then(response => {
      console.log('API response status:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('API response data:', data);
      if (data.success && data.data) {
        console.log('Successfully received courses data, populating dropdown...');
        populateQuickEnrollmentDropdown(data.data);
      } else {
        console.error('API returned unsuccessful response:', data);
      }
    })
    .catch(error => {
      console.error('Error loading courses for Quick Enrollment dropdown:', error);
    });
}

function addCourseToTableFromDatabase(course) {
  const coursesTableBody = document.getElementById('coursesTableBody');
  if (!coursesTableBody) return;
  
  // Create new table row with database values
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td>
      <input type="checkbox" class="course-row-checkbox" data-course-id="${course.id}">
    </td>
    <td>${course.code}</td>
    <td>${course.name}</td>
    <td>
      <select data-id="${course.id}" class="course-status">
        <option value="upcoming" ${course.status === 'upcoming' ? 'selected' : ''}>upcoming</option>
        <option value="ongoing" ${course.status === 'ongoing' ? 'selected' : ''}>ongoing</option>
        <option value="completed" ${course.status === 'completed' ? 'selected' : ''}>completed</option>
        <option value="cancelled" ${course.status === 'cancelled' ? 'selected' : ''}>cancelled</option>
      </select>
    </td>
    <td>
      <input type="date" value="${course.start_date || ''}" class="course-start" data-id="${course.id}">
    </td>
    <td>
      <input type="date" value="${course.end_date || ''}" class="course-end" data-id="${course.id}">
    </td>
    <td>
      <input type="number" min="1" value="${course.default_duration_days || 90}" class="course-dur" data-id="${course.id}" style="max-width:120px;">
    </td>
    <td>
      <select data-id="${course.id}" class="course-active">
        <option value="1" ${course.is_active == 1 ? 'selected' : ''}>Yes</option>
        <option value="0" ${course.is_active == 0 ? 'selected' : ''}>No</option>
      </select>
    </td>
    <td>
      <button class="save-course" data-id="${course.id}">Save</button>
    </td>
  `;
  
  // Add the new row to the table
  coursesTableBody.appendChild(newRow);
}

// Function to add checkboxes to existing table rows
function addCheckboxesToExistingRows() {
  const coursesTableBody = document.getElementById('coursesTableBody');
  if (!coursesTableBody) return;
  
  const existingRows = coursesTableBody.querySelectorAll('tr');
  
  existingRows.forEach(row => {
    // Skip if checkbox already exists
    if (row.querySelector('.course-row-checkbox')) return;
    
    // Skip loading row
    if (row.classList.contains('loading-row')) return;
    
    // Get the course ID from the save button
    const saveBtn = row.querySelector('.save-course');
    if (!saveBtn) return;
    
    const courseId = saveBtn.getAttribute('data-id');
    
    // Create checkbox cell
    const checkboxCell = document.createElement('td');
    checkboxCell.innerHTML = `<input type="checkbox" class="course-row-checkbox" data-course-id="${courseId}">`;
    
    // Insert checkbox cell as the first cell
    row.insertBefore(checkboxCell, row.firstChild);
  });
}

function updateCourseCounts() {
  const totalCoursesElement = document.getElementById('totalCourses');
  const activeCoursesElement = document.getElementById('activeCourses');
  const coursesTableBody = document.getElementById('coursesTableBody');
  
  if (totalCoursesElement && coursesTableBody) {
    const totalRows = coursesTableBody.querySelectorAll('tr').length;
    totalCoursesElement.textContent = totalRows;
  }
  
  if (activeCoursesElement && coursesTableBody) {
    const activeRows = coursesTableBody.querySelectorAll('tr .course-active option[value="1"]:checked').length;
    activeCoursesElement.textContent = activeRows;
  }
}

function initializeDeleteCourseFunctionality() {
  const deleteCourseBtn = document.getElementById('deleteCourseBtn');
  const selectAllCheckbox = document.getElementById('selectAllCourses');
  
  // Handle delete button click
  if (deleteCourseBtn) {
    deleteCourseBtn.addEventListener('click', function() {
      const selectedCourses = getSelectedCourses();
      if (selectedCourses.length === 0) {
        alert('Please select at least one course to delete.');
        return;
      }
      
      // Show confirmation dialog
      const courseNames = selectedCourses.map(course => course.name).join(', ');
      if (confirm(`Are you sure you want to delete the following courses?\n\n${courseNames}\n\nThis action cannot be undone.`)) {
        deleteSelectedCourses(selectedCourses);
      }
    });
  }
  
  // Handle select all checkbox
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      const courseCheckboxes = document.querySelectorAll('.course-row-checkbox');
      courseCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
      updateDeleteButtonVisibility();
    });
  }
  
  // Update delete button visibility when individual checkboxes change
  document.addEventListener('change', function(e) {
    if (e.target.classList.contains('course-row-checkbox')) {
      updateDeleteButtonVisibility();
      updateSelectAllCheckbox();
    }
  });
}

function getSelectedCourses() {
  const selectedCourses = [];
  const courseCheckboxes = document.querySelectorAll('.course-row-checkbox:checked');
  
  console.log('Found', courseCheckboxes.length, 'selected checkboxes');
  
  courseCheckboxes.forEach((checkbox, index) => {
    const row = checkbox.closest('tr');
    const courseId = checkbox.getAttribute('data-course-id');
    const courseCode = row.querySelector('td:nth-child(2)').textContent;
    const courseName = row.querySelector('td:nth-child(3)').textContent;
    
    console.log(`Course ${index + 1}:`, {
      id: courseId,
      code: courseCode,
      name: courseName
    });
    
    // Validate course ID
    if (!courseId || courseId === 'null' || courseId === 'undefined') {
      console.error(`Invalid course ID for course ${index + 1}:`, courseId);
      return; // Skip this course
    }
    
    selectedCourses.push({
      id: parseInt(courseId), // Convert to integer
      code: courseCode,
      name: courseName,
      row: row
    });
  });
  
  console.log('Selected courses:', selectedCourses);
  return selectedCourses;
}

function updateDeleteButtonVisibility() {
  const deleteCourseBtn = document.getElementById('deleteCourseBtn');
  const selectedCount = document.querySelectorAll('.course-row-checkbox:checked').length;
  
  if (deleteCourseBtn) {
    if (selectedCount > 0) {
      deleteCourseBtn.style.display = 'inline-block';
      deleteCourseBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${selectedCount})`;
    } else {
      deleteCourseBtn.style.display = 'none';
    }
  }
}

function updateSelectAllCheckbox() {
  const selectAllCheckbox = document.getElementById('selectAllCourses');
  const courseCheckboxes = document.querySelectorAll('.course-row-checkbox');
  const checkedCount = document.querySelectorAll('.course-row-checkbox:checked').length;
  
  if (selectAllCheckbox && courseCheckboxes.length > 0) {
    if (checkedCount === 0) {
      selectAllCheckbox.indeterminate = false;
      selectAllCheckbox.checked = false;
    } else if (checkedCount === courseCheckboxes.length) {
      selectAllCheckbox.indeterminate = false;
      selectAllCheckbox.checked = true;
    } else {
      selectAllCheckbox.indeterminate = true;
      selectAllCheckbox.checked = false;
    }
  }
}

function deleteSelectedCourses(selectedCourses) {
  const deleteCourseBtn = document.getElementById('deleteCourseBtn');
  
  // Show loading state
  const originalText = deleteCourseBtn.innerHTML;
  deleteCourseBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
  deleteCourseBtn.disabled = true;
  
  // Prepare course IDs for deletion
  const courseIds = selectedCourses.map(course => course.id);
  
  console.log('Preparing to delete courses:', courseIds);
  console.log('Selected courses data:', selectedCourses);
  
  // Validate course IDs
  const validCourseIds = courseIds.filter(id => id && !isNaN(id) && id > 0);
  if (validCourseIds.length === 0) {
    alert('No valid course IDs found. Please refresh the table and try again.');
    deleteCourseBtn.innerHTML = originalText;
    deleteCourseBtn.disabled = false;
    return;
  }
  
  if (validCourseIds.length !== courseIds.length) {
    console.warn('Some course IDs were invalid and filtered out:', courseIds.filter(id => !validCourseIds.includes(id)));
  }
  
  // Send delete request to API
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('course_ids', JSON.stringify(validCourseIds));
  
  console.log('FormData contents:');
  for (let [key, value] of formData.entries()) {
    console.log(key, ':', value);
  }
  
  fetch('apis/course_admin.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(response => {
    console.log('Delete response status:', response.status);
    if (!response.ok) {
      // Try to get the error message from the response
      return response.text().then(text => {
        console.log('Error response text:', text);
        try {
          const errorData = JSON.parse(text);
          throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        } catch (e) {
          throw new Error(`HTTP error! status: ${response.status}. Response: ${text}`);
        }
      });
    }
    return response.json();
  })
  .then(data => {
    console.log('Delete response data:', data);
    if (data.success) {
      // Remove selected rows from table
      selectedCourses.forEach(course => {
        course.row.remove();
        // Also remove from trainee course dropdown
        removeCourseFromTraineeDropdown(course.name);
      });
      
      // Update course counts
      updateCourseCounts();
      
      // Refresh all course dropdowns to reflect the deletion
      loadCoursesFromDatabase();
      
      // Hide delete button
      deleteCourseBtn.style.display = 'none';
      
      // Reset select all checkbox
      const selectAllCheckbox = document.getElementById('selectAllCourses');
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
      }
      
      // Show success message
      showNotification(`${selectedCourses.length} course(s) deleted successfully!`, 'success');
    } else {
      console.error('Delete failed:', data.message);
      alert(data.message || 'Failed to delete courses');
    }
  })
  .catch(error => {
    console.error('Error deleting courses:', error);
    alert('Failed to delete courses: ' + error.message);
  })
  .finally(() => {
    // Restore button state
    deleteCourseBtn.innerHTML = originalText;
    deleteCourseBtn.disabled = false;
  });
}