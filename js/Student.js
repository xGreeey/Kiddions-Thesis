// Jobs list rendering for Student Dashboard
(function(){
  // Minimal CSRF header injection for same-origin fetch
  try{
    var _origFetch = window.fetch && window.fetch.bind(window);
    if (_origFetch){
      function _sameOrigin(u){ try{ var url=new URL(u, window.location.origin); return url.origin===window.location.origin; }catch(e){ return true; } }
      function _getCsrf(){
        try{
          var el=document.getElementById('csrf_token'); if (el&&el.value) return el.value;
          var meta=document.querySelector('meta[name="csrf-token"]'); if (meta&&meta.getAttribute('content')) return meta.getAttribute('content');
        }catch(e){}
        return '';
      }
      window.fetch = function(resource, options){
        options = options || {};
        if (!options.credentials && _sameOrigin(resource)) { options.credentials = 'same-origin'; }
        if (!options.headers) { options.headers = {}; }
        if (_sameOrigin(resource)){
          var t=_getCsrf();
          if (t && !options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) options.headers['X-CSRF-Token']=t;
          if (options.body instanceof FormData){
            try{ if (options.headers instanceof Headers){ options.headers.delete('Content-Type'); } else { delete options.headers['Content-Type']; delete options.headers['content-type']; } }catch(e){}
          }
        }
        return _origFetch(resource, options);
      };
    }
  }catch(e){}
    // Cross-tab logout sync and session polling
    (function setupLogoutSync(){
        // Broadcast logout to other tabs right before submitting any logout form
        function wireLogoutBroadcast(){
            var logoutForms = Array.prototype.slice.call(document.querySelectorAll('form'));
            logoutForms.forEach(function(f){
                if (f.__logoutWired) return;
                var hasLogoutButton = !!f.querySelector('button[name="logout"],input[name="logout"]');
                if (hasLogoutButton) {
                    f.addEventListener('submit', function(){
                        try {
                            localStorage.setItem('MMTVTC_LOGOUT', String(Date.now()));
                        } catch(e) {}
                    }, { capture: true });
                    f.__logoutWired = true;
                }
            });
        }

        // Listen for logout broadcasts from other tabs
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
                    try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'cookie_flag', page: window.location.href }) }); } catch(_){ }
                    clearInterval(__logoutCookieInterval);
                    try { window.location.replace('index.php'); } catch(e) { window.location.href = 'index.php'; }
                }
            } catch(e) {}
        }, 1000);

        // Lightweight polling to detect server-side session invalidation
        function startSessionPolling(){
            var POLL_MS = 7000; // 7s faster detection
            function check(){
                fetch('apis/session_status.php', { credentials: 'same-origin', cache: 'no-store' })
                    .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                    .then(function(j){
                        if (!j || !j.authenticated) {
                            try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'session_invalid', page: window.location.href, info: j }) }); } catch(_){ }
                            try { window.location.replace('index.php'); } catch(e) { window.location.href = 'index.php'; }
                        }
                    })
                    .catch(function(err){
                        try { console.error('session_status failed', err); } catch(_) {}
                        try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'session_check_error', page: window.location.href, info: String(err) }) }); } catch(_){ }
                    });
            }
            setInterval(check, POLL_MS);
            // Also re-check on tab visibility gain and back/forward cache restore
            document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'visible') { check(); } });
            window.addEventListener('pageshow', function(e){ if (e && e.persisted) { check(); } });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function(){ wireLogoutBroadcast(); startSessionPolling(); });
        } else {
            wireLogoutBroadcast(); startSessionPolling();
        }

        // Logout button/link handler to broadcast immediately
        function handleLogoutClick(e){
            try {
                localStorage.setItem('logout_timestamp', String(Date.now()));
                localStorage.removeItem('logout_timestamp');
            } catch(_) {}
        }
        document.addEventListener('DOMContentLoaded', function(){
            var btnA = document.getElementById('logout-btn');
            var link = document.querySelector('a[href="../auth/logout.php"]');
            if (btnA) { btnA.addEventListener('click', handleLogoutClick, { capture: true }); }
            if (link) { link.addEventListener('click', handleLogoutClick, { capture: true }); }
        });
    })();

    function renderJobs(jobs){
        var course = (window.currentStudentCourse || '').toString().trim();
        if(course){
            // Normalize: admin uses short codes like (SMAW). Keep loose contains match
            var lc = course.toLowerCase();
            jobs = (jobs || []).filter(function(job){
                var jc = (job.course || job.title || '').toString().toLowerCase();
                return jc.indexOf(lc) !== -1 || lc.indexOf(jc) !== -1;
            });
        }
        var grid = document.querySelector('#jobs_posting .job-cards-grid');
        if(!grid) return;
        grid.innerHTML = jobs.map(function(job){
            return (
                '<div class="job-card">'
              + '  <div class="job-header">'
              + '    <h3 class="job-title">'+ escapeHtml(job.title) +'</h3>'
              + '    <div class="job-actions"></div>'
              + '  </div>'
              + '  <div class="job-details">'
              + (job.course ? ('    <p><strong>Course:</strong> ' + escapeHtml(job.course) + '</p>') : '')
              + '    <p><strong>Company:</strong> ' + escapeHtml(job.company) + '</p>'
              + '    <div class="job-info">'
              + '      <div class="job-info-item"><i class="fas fa-map-marker-alt"></i><span>' + escapeHtml(job.location) + '</span></div>'
              + '      <div class="job-info-item"><i class="fas fa-dollar-sign"></i><span>' + escapeHtml(job.salary || '—') + '</span></div>'
              + '      <div class="job-info-item"><i class="fas fa-clock"></i><span>' + escapeHtml(job.experience || '—') + '</span></div>'
              + '    </div>'
              + '    <p class="job-description">' + escapeHtml(job.description || '') + '</p>'
              + '  </div>'
              + '</div>'
            );
        }).join('');
    }

    function escapeHtml(s){
        s = String(s==null?'':s);
        return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); });
    }

    function loadJobs(){
        fetch('apis/jobs_handler.php', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(j){ if(j && j.success){ renderJobs(j.data || []); } })
            .catch(function(){});
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', loadJobs);
    } else { loadJobs(); }
})();

// Load current student's detailed grade entries (per grade_number)
(function(){
    function loadDetails(){
        var details = document.getElementById('studentGradeDetails');
        if(!details) return;
        details.innerHTML = '<div style="opacity:.7;">Loading...</div>';
        var sns = window.currentStudentNumber || null;
        var reqs = [1,2,3,4].map(function(gn){
            var url = 'apis/grade_details.php?action=list&student_number=' + encodeURIComponent(sns || '') + '&grade_number=' + gn;
            return fetch(url, {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(j){ return (j && j.success && Array.isArray(j.data)) ? j.data : []; })
                .catch(function(){ return []; });
        });
        Promise.all(reqs).then(function(all){
            var html = '';
            [1,2,3,4].forEach(function(gn, idx){
                var rows = all[idx] || [];
                html += '<div class="grades-table-wrapper" style="margin-top:12px;">'
                     +  '<h4 style="margin:4px 0; text-align:center;">Grade ' + gn + '</h4>'
                     +  '<table class="grades-table grades-table--details"><thead><tr>'
                     +  '<th>Date</th><th>Component</th><th>Raw</th><th>Total</th><th>Transmuted</th>'
                     +  '</tr></thead><tbody>'
                     +  (rows.length ? rows.map(function(r){
                            var date = r.date_given || '—';
                            var comp = r.component || '—';
                            var raw = (r.raw_score!=null?r.raw_score:'—');
                            var total = (r.total_items!=null?r.total_items:'—');
                            var trans = (r.transmuted!=null? Number(r.transmuted).toFixed(2)+'%':'—');
                            return '<tr><td>'+date+'</td><td>'+escapeHtml(comp)+'</td><td>'+raw+'</td><td>'+total+'</td><td>'+trans+'</td></tr>';
                        }).join('') : '<tr><td colspan="5" style="text-align:center;opacity:.7;">No entries</td></tr>')
                     +  '</tbody></table></div>';
            });
            details.innerHTML = html;
        });
    }
    function escapeHtml(s){ s = String(s==null?'':s); return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); }); }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', loadDetails);
    } else { loadDetails(); }
})();

// --- Modal and Dashboard Logic ---
document.addEventListener("DOMContentLoaded", () => {
  // Logout modal removed: direct logout is handled by cross-tab-logout.js

  // Add Trainee Modal Logic
  const addTraineeBtn = document.getElementById("addTraineeBtn")
  const addTraineeModal = document.getElementById("addTraineeModal")
  const cancelAddTrainee = document.getElementById("cancelAddTrainee")
  const addTraineeForm = document.getElementById("addTraineeForm")
  if (addTraineeBtn && addTraineeModal && cancelAddTrainee && addTraineeForm) {
    addTraineeBtn.addEventListener("click", () => {
      addTraineeModal.style.display = "flex"
      const modalContent = addTraineeModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.classList.remove("popOut")
        modalContent.style.animation = "scaleIn 0.25s"
      }
    })
    cancelAddTrainee.addEventListener("click", () => {
      const modalContent = addTraineeModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s"
        modalContent.classList.add("popOut")
        modalContent.addEventListener("animationend", function handler() {
          addTraineeModal.style.display = "none"
          modalContent.classList.remove("popOut")
          modalContent.removeEventListener("animationend", handler)
        })
      } else {
        addTraineeModal.style.display = "none"
      }
    })
    // Hide modal on overlay click (optional)
    addTraineeModal.addEventListener("click", (e) => {
      if (e.target === addTraineeModal) {
        const modalContent = addTraineeModal.querySelector(".modal-content")
        if (modalContent) {
          modalContent.style.animation = "popOut 0.25s"
          modalContent.classList.add("popOut")
          modalContent.addEventListener("animationend", function handler() {
            addTraineeModal.style.display = "none"
            modalContent.classList.remove("popOut")
            modalContent.removeEventListener("animationend", handler)
          })
        } else {
          addTraineeModal.style.display = "none"
        }
      }
    })
    // Add Trainee Form Submission
    addTraineeForm.addEventListener("submit", (e) => {
      e.preventDefault()
      const formData = new FormData(addTraineeForm)
      const firstName = formData.get("firstName")
      const lastName = formData.get("lastName")
      const phone = formData.get("phone")
      const course = formData.get("course")
      const grade = formData.get("grade")
      // Generate a new ID (simple increment based on table rows)
      const table = document.querySelector(".data-table tbody")
      const newId = "T" + String(table.rows.length + 1).padStart(3, "0")
      // Insert new row
      const row = table.insertRow()
      row.innerHTML = `
        <td>${newId}</td>
        <td>${firstName} ${lastName}</td>
        <td>${course}</td>
        <td><span class="grade-badge">${grade}%</span></td>
        <td><button class="view-btn">View Details</button></td>
      `
      // Close modal with popOut animation
      const modalContent = addTraineeModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s"
        modalContent.classList.add("popOut")
        modalContent.addEventListener("animationend", function handler() {
          addTraineeModal.style.display = "none"
          modalContent.classList.remove("popOut")
          modalContent.removeEventListener("animationend", handler)
        })
      } else {
        addTraineeModal.style.display = "none"
      }
      addTraineeForm.reset()
    })
  }

  // Add Jobs Modal Logic
  const addJobsBtn = document.getElementById("addJobsBtn")
  const addJobsModal = document.getElementById("addJobsModal")
  const cancelAddJobs = document.getElementById("cancelAddJobs")
  const addJobsForm = document.getElementById("addJobsForm")

  if (addJobsBtn && addJobsModal && cancelAddJobs && addJobsForm) {
    // Show modal when Add Jobs button is clicked
    addJobsBtn.addEventListener("click", () => {
      addJobsModal.style.display = "flex"
      const modalContent = addJobsModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.classList.remove("popOut")
        modalContent.style.animation = "scaleIn 0.25s"
      }
    })

    // Hide modal when Cancel button is clicked
    cancelAddJobs.addEventListener("click", () => {
      const modalContent = addJobsModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s"
        modalContent.classList.add("popOut")
        modalContent.addEventListener("animationend", function handler() {
          addJobsModal.style.display = "none"
          modalContent.classList.remove("popOut")
          modalContent.removeEventListener("animationend", handler)
        })
      } else {
        addJobsModal.style.display = "none"
      }
    })

    // Hide modal on overlay click
    addJobsModal.addEventListener("click", (e) => {
      if (e.target === addJobsModal) {
        const modalContent = addJobsModal.querySelector(".modal-content")
        if (modalContent) {
          modalContent.style.animation = "popOut 0.25s"
          modalContent.classList.add("popOut")
          modalContent.addEventListener("animationend", function handler() {
            addJobsModal.style.display = "none"
            modalContent.classList.remove("popOut")
            modalContent.removeEventListener("animationend", handler)
          })
        } else {
          addJobsModal.style.display = "none"
        }
      }
    })

    // Add Jobs Form Submission (Replace the existing one in your script.js)
    addJobsForm.addEventListener("submit", (e) => {
      e.preventDefault()
      const formData = new FormData(addJobsForm)
      const jobTitle = formData.get("jobTitle")
      const companyName = formData.get("companyName")
      const location = formData.get("location")
      const salary = formData.get("salary")
      const experience = formData.get("experience")
      const description = formData.get("description")

      // Create new job card
      const jobCardsGrid = document.querySelector(".job-cards-grid")
      const newJobCard = document.createElement("div")
      newJobCard.className = "job-card newly-added" // Add newly-added class for animation

      newJobCard.innerHTML = `
        <div class="job-header">
          <h3 class="job-title">${jobTitle}</h3>
          <div class="job-actions">
            <button class="edit-btn">
              <i class="fas fa-edit"></i>
            </button>
            <button class="delete-btn">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        <div class="job-details">
          <p><strong>Company:</strong> ${companyName}</p>
          <div class="job-info">
            <div class="job-info-item">
              <i class="fas fa-map-marker-alt"></i>
              <span>${location}</span>
            </div>
            <div class="job-info-item">
              <i class="fas fa-dollar-sign"></i>
              <span>${salary}</span>
            </div>
            <div class="job-info-item">
              <i class="fas fa-clock"></i>
              <span>${experience}</span>
            </div>
          </div>
          <p class="job-description">${description}</p>
        </div>
      `

      // Add the new job card to the grid first
      jobCardsGrid.appendChild(newJobCard)

      // Remove the animation class after animation completes to reset for future use
      setTimeout(() => {
        newJobCard.classList.remove("newly-added")
      }, 1200) // Match animation duration

      // Add event listeners to the new job card's action buttons
      const editBtn = newJobCard.querySelector(".edit-btn")
      const deleteBtn = newJobCard.querySelector(".delete-btn")

      // Attach edit functionality to new button
      if (window.attachEditListener) {
        window.attachEditListener(editBtn)
      }

      deleteBtn.addEventListener("click", () => {
        const deleteJobModal = document.getElementById("deleteJobModal")
        const confirmDeleteJobBtn = document.getElementById("confirmDeleteJobBtn")
        const cancelDeleteJobBtn = document.getElementById("cancelDeleteJobBtn")

        if (deleteJobModal) {
          deleteJobModal.style.display = "flex"

          // Set up delete confirmation for this specific card
          const confirmHandler = () => {
            newJobCard.classList.add("deleting")
            newJobCard.addEventListener("animationend", () => {
              newJobCard.remove()
            })
            deleteJobModal.style.display = "none"
            confirmDeleteJobBtn.removeEventListener("click", confirmHandler)
          }

          const cancelHandler = () => {
            deleteJobModal.style.display = "none"
            confirmDeleteJobBtn.removeEventListener("click", confirmHandler)
            cancelDeleteJobBtn.removeEventListener("click", cancelHandler)
          }

          confirmDeleteJobBtn.addEventListener("click", confirmHandler)
          cancelDeleteJobBtn.addEventListener("click", cancelHandler)

          // Hide modal on overlay click
          const overlayHandler = (e) => {
            if (e.target === deleteJobModal) {
              deleteJobModal.style.display = "none"
              confirmDeleteJobBtn.removeEventListener("click", confirmHandler)
              cancelDeleteJobBtn.removeEventListener("click", cancelHandler)
              deleteJobModal.removeEventListener("click", overlayHandler)
            }
          }
          deleteJobModal.addEventListener("click", overlayHandler)
        }
      })

      // Close modal with popOut animation
      const modalContent = addJobsModal.querySelector(".modal-content")
      if (modalContent) {
        modalContent.style.animation = "popOut 0.25s"
        modalContent.classList.add("popOut")
        modalContent.addEventListener("animationend", function handler() {
          addJobsModal.style.display = "none"
          modalContent.classList.remove("popOut")
          modalContent.removeEventListener("animationend", handler)
        })
      } else {
        addJobsModal.style.display = "none"
      }

      // Reset form
      addJobsForm.reset()

      // Show success message (optional)
      console.log("Job added successfully:", jobTitle)
    })
  }

  // --- End Modal and Dashboard Logic ---
})

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
      // Update header title to reflect current section
      updateMainHeaderTitle(sectionName)
    }

    // Toggle Dashboard-only elements
    const dashboardTasksWrapper = document.getElementById("dashboardTasksWrapper")
    if (dashboardTasksWrapper) {
      if (sectionName === "dashboard") {
        dashboardTasksWrapper.style.display = "block"
      } else {
        dashboardTasksWrapper.style.display = "none"
      }
    }

    // Toggle Activities section visibility
    const activitiesSection = document.getElementById("activities")
    if (activitiesSection) {
      if (sectionName === "activities") {
        activitiesSection.style.display = "block"
      } else {
        activitiesSection.style.display = "none"
      }
    }

    // Update global state
    activeSection = sectionName // Update local variable
  }

  // Update the main header title based on the active nav item's text
  function updateMainHeaderTitle(sectionName) {
    const mainTitleElement = document.querySelector(".main-header .main-title")
    const activeNavItem = document.querySelector(`.nav-item[data-section="${sectionName}"]`)
    if (mainTitleElement && activeNavItem) {
      const navTextElement = activeNavItem.querySelector(".nav-text")
      if (navTextElement) {
        mainTitleElement.textContent = navTextElement.textContent
      }
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

  // Notification functionality
  function initializeNotifications() {
    const notificationBell = document.getElementById("notificationBell")
    const notificationDropdown = document.getElementById("notificationDropdown")
    const notificationClose = document.getElementById("notificationClose")
    const notificationBadge = document.getElementById("notificationBadge")
    const notificationList = document.getElementById("notificationList")
    let isNotificationAnimating = false

    function showDropdown() {
      if (!notificationDropdown) return
      if (isNotificationAnimating) return
      isNotificationAnimating = true
      notificationDropdown.classList.remove("hide")
      notificationDropdown.classList.add("show")
      notificationDropdown.style.display = "block"
      notificationBell?.classList.add("active")
      // End animating after scaleIn duration (~300ms)
      setTimeout(() => { isNotificationAnimating = false }, 320)
    }

    function hideDropdown() {
      if (!notificationDropdown) return
      if (isNotificationAnimating) return
      isNotificationAnimating = true
      notificationDropdown.classList.remove("show")
      notificationDropdown.classList.add("hide")
      notificationBell?.classList.remove("active")
      // Wait for animation to finish before hiding
      function onAnimEnd() {
        notificationDropdown.classList.remove("hide")
        notificationDropdown.style.display = "none"
        notificationDropdown.removeEventListener("animationend", onAnimEnd)
        isNotificationAnimating = false
      }
      notificationDropdown.addEventListener("animationend", onAnimEnd)
    }

    // Expose a global method to close the notification dropdown and return a Promise that resolves when hidden
    window.hideStudentNotificationDropdown = function() {
      return new Promise(function(resolve){
        if (!notificationDropdown) { resolve(); return }
        const isOpen = notificationDropdown.style.display === 'block' && notificationDropdown.classList.contains('show')
        if (!isOpen) {
          notificationDropdown.classList.remove('show','hide')
          notificationDropdown.style.display = 'none'
          resolve();
          return
        }
        function onEnd(){
          notificationDropdown.removeEventListener('animationend', onEnd)
          resolve()
        }
        notificationDropdown.addEventListener('animationend', onEnd)
        hideDropdown()
        // Safety resolve in case animationend doesn't fire
        setTimeout(onEnd, 450)
      })
    }

    notificationBell?.addEventListener("click", (ev) => {
      ev.preventDefault()
      ev.stopPropagation()
      if (isNotificationAnimating) return
      const currentlyOpen = !!(notificationDropdown && notificationDropdown.style.display === 'block' && notificationDropdown.classList.contains('show'))
      if (!currentlyOpen) {
        // Close profile dropdown first, then show notifications
        var profileDd = document.getElementById("profileDropdown")
        function hideProfileWithPromise(){
          return new Promise(function(resolve){
            if (!profileDd) { resolve(); return }
            const open = profileDd.classList && (profileDd.classList.contains('show') || profileDd.style.display === 'block')
            if (!open) { profileDd.style.display = 'none'; resolve(); return }
            function onEnd(){ profileDd.removeEventListener('animationend', onEnd); resolve() }
            profileDd.addEventListener('animationend', onEnd)
            profileDd.classList.remove('show')
            profileDd.classList.add('hide')
            setTimeout(function(){
              profileDd.classList.remove('hide')
              profileDd.style.display = 'none'
            }, 250)
            setTimeout(onEnd, 320)
          })
        }
        hideProfileWithPromise().then(function(){ showDropdown() })
      } else {
        hideDropdown()
      }
    })

    notificationClose?.addEventListener("click", () => {
      hideDropdown()
    })

    // Do not auto-close on outside clicks; keep open until another button interaction

    async function fetchNotifications() {
      try {
        const response = await fetch("apis/notifications_handler.php", {
          method: "GET",
          headers: { "Accept": "application/json" },
          credentials: "same-origin",
        })
        if (!response.ok) throw new Error("Failed to load notifications")
        const data = await response.json()
        const notifications = data.data || data.notifications || []

        // Update badge
        if (notificationBadge) {
          const count = notifications.length
          notificationBadge.textContent = String(count)
          notificationBadge.style.display = count > 0 ? "inline-block" : "none"
        }

        // Render list
        if (notificationList) {
          if (!notifications.length) {
            notificationList.innerHTML = `<div class="notification-empty">No notifications</div>`
          } else {
            notificationList.innerHTML = notifications
              .map((n) => {
                const iconClass = mapIcon(n.icon)
                const title = escapeHTML(n.title || "")
                const message = escapeHTML(n.message || "")
                const timeText = escapeHTML(n.time_display || n.custom_time || "")
                return `
                  <div class="notification-item" data-id="${n.id}">
                    <div class="notification-icon">
                      <i class="${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                      <p class="notification-title">${title}</p>
                      <p class="notification-message">${message}</p>
                      <p class="notification-time">${timeText}</p>
                    </div>
                  </div>
                `
              })
              .join("")
          }
        }
      } catch (err) {
        console.error("Notifications fetch error:", err)
      }
    }

    function mapIcon(icon) {
      // Map DB icon value to Font Awesome classes
      const map = {
        info: "fas fa-info-circle",
        success: "fas fa-check-circle",
        warning: "fas fa-exclamation-triangle",
        error: "fas fa-times-circle",
        bell: "fas fa-bell",
        cog: "fas fa-cog",
        database: "fas fa-database",
        file: "fas fa-file",
      }
      if (!icon) return "fas fa-bell"
      if (icon.includes("fa-")) return icon // already a class
      return map[icon] || "fas fa-bell"
    }

    function escapeHTML(str) {
      const div = document.createElement("div")
      div.textContent = String(str)
      return div.innerHTML
    }

    // Initial load and polling
    fetchNotifications()
    const POLL_MS = 60 * 1000
    setInterval(fetchNotifications, POLL_MS)
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
    const filterSelects = document.querySelectorAll(".filter-select")

    // Add filter change listeners
    filterSelects.forEach((select) => {
      select.addEventListener("change", () => {
        applyFilters()
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

    // Here you would implement the actual filtering logic
    // For now, we'll just log the filters
  }

  // Theme functionality
  function initializeTheme() {
    const themeToggle = document.getElementById("themeToggle")
    let isDarkMode = document.body.classList.contains("dark-theme") // Initialize based on current state

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
            document.body.classList.add("dark-theme")
          } else {
            icon.className = "fas fa-moon theme-icon-spin"
            document.body.classList.remove("dark-theme")
          }
        }, 350)

        // Remove spin animation after it completes
        setTimeout(() => {
          icon.classList.remove("theme-icon-spin")
        }, 700) // Match animation duration
      }

      console.log(`Theme switched to ${isDarkMode ? "dark" : "light"} mode`)
    })
  }

  // Logout functionality
  function initializeLogout() {
    // No JS needed; handled by modal in HTML
    // (Removed all alert or popup for logout)
  }

  // Career Analytics
  function initializeCareerAnalytics() {
    const courseSelect = document.getElementById("analyticsCourseSelect")
    const info = document.getElementById("analyticsInfo")
    const trendCtx = document.getElementById("analyticsTrendChart")?.getContext("2d")
    const forecastCtx = document.getElementById("analyticsForecastChart")?.getContext("2d")
  
    if (!courseSelect || !trendCtx || !forecastCtx) return
  
    let trendChart = null
    let forecastChart = null
    let dataset = null
  
    // Chart configuration with proper responsive settings
    const chartConfig = {
      responsive: true,
      maintainAspectRatio: false, // This is crucial
      aspectRatio: 2,
      resizeDelay: 200,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            boxWidth: 12,
            padding: 15,
            font: { size: 12 }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.1)' },
          ticks: { font: { size: 11 } }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      },
      elements: {
        point: {
          radius: 3,
          hoverRadius: 5
        }
      }
    }
  
    async function loadCSV() {
      try {
        const res = await fetch("data/Graduates_.csv", { cache: "no-store" })
        if (!res.ok) throw new Error("CSV not found")
        const text = await res.text()
        dataset = parseCSV(text)
        populateCourses(dataset)
        renderForSelection()
      } catch (e) {
        console.warn("Career analytics CSV not available. Place it at htdocs/data/Graduates_.csv")
        info && (info.textContent = "Upload data/Graduates_.csv to enable analytics.")
      }
    }
  
    function parseCSV(text) {
      const lines = text.split(/\r?\n/).filter((l) => l.trim().length)
      if (lines.length < 2) return { rows: [], courses: [], years: [] }
      
      const header = lines[0].split(",").map((h) => h.trim())
      const col = (name) => header.findIndex((h) => h.toLowerCase() === name)
      const idxYear = col("year")
      const idxCourse = col("course_id")
      const idxBatch = col("batch")
      const idxCount = col("student_count")
      
      const rows = []
      const coursesSet = new Set()
      const yearsSet = new Set()
      
      for (let i = 1; i < lines.length; i++) {
        const parts = safeSplitCSV(lines[i], header.length)
        if (!parts || parts.length < header.length) continue
        
        const year = Number(parts[idxYear])
        const course = String(parts[idxCourse])
        const batch = Number(parts[idxBatch])
        const count = Number(parts[idxCount])
        
        if (!Number.isFinite(year) || !course) continue
        
        rows.push({ 
          year, 
          course_id: course, 
          batch, 
          student_count: Number.isFinite(count) ? count : 0 
        })
        
        coursesSet.add(course)
        yearsSet.add(year)
      }
      
      return { 
        rows, 
        courses: Array.from(coursesSet).sort(), 
        years: Array.from(yearsSet).sort((a, b) => a - b) 
      }
    }
  
    function safeSplitCSV(line, minCols) {
      const result = []
      let current = ""
      let inQuotes = false
      
      for (let i = 0; i < line.length; i++) {
        const ch = line[i]
        if (ch === '"') {
          if (inQuotes && line[i + 1] === '"') { 
            current += '"'; i++ 
          } else { 
            inQuotes = !inQuotes 
          }
        } else if (ch === "," && !inQuotes) {
          result.push(current)
          current = ""
        } else {
          current += ch
        }
      }
      result.push(current)
      
      return result.length >= minCols ? result.map((s) => s.trim()) : null
    }
  
    function populateCourses(data) {
      courseSelect.innerHTML = `<option value="__ALL__">All Courses</option>`
      data.courses.forEach((c) => {
        const opt = document.createElement("option")
        opt.value = c
        opt.textContent = c
        courseSelect.appendChild(opt)
      })
    }
  
    function aggregate(data, selectedCourse) {
      const filtered = selectedCourse === "__ALL__" ? data.rows : data.rows.filter((r) => r.course_id === selectedCourse)
      const byYear = new Map()
      
      filtered.forEach((r) => {
        byYear.set(r.year, (byYear.get(r.year) || 0) + (r.student_count || 0))
      })
      
      const years = Array.from(byYear.keys()).sort((a, b) => a - b)
      const totals = years.map((y) => byYear.get(y))
      
      return { years, totals }
    }
  
    function simpleForecast(years, totals, nextYear) {
      if (years.length < 2) return { predicted: totals[totals.length - 1] || 0, acc: null }
      
      const x = years.map((y) => y)
      const y = totals
      const n = x.length
      const sumX = x.reduce((s, v) => s + v, 0)
      const sumY = y.reduce((s, v) => s + v, 0)
      const sumXY = x.reduce((s, v, i) => s + v * y[i], 0)
      const sumXX = x.reduce((s, v) => s + v * v, 0)
      const denom = n * sumXX - sumX * sumX
      const a = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0
      const b = (sumY - a * sumX) / n
      const predicted = Math.max(0, Math.round(a * nextYear + b))
      
      let acc = null
      if (n >= 3) {
        const lastPred = Math.max(0, Math.round(a * x[n - 1] + b))
        const lastActual = y[n - 1]
        const mae = Math.abs(lastPred - lastActual)
        const base = Math.max(1, Math.abs(lastActual))
        acc = Math.max(0, 100 - (mae / base) * 100)
      }
      
      return { predicted, acc }
    }
  
    function renderCharts(selectedCourse) {
      // For display, use filtered data
      const { years, totals } = aggregate(dataset, selectedCourse)
      
      // For prediction, always use the full historical data for the selected course (or all courses if "__ALL__")
      // This ensures the 2026 prediction is always calculated from sufficient historical data
      const predictionCourse = selectedCourse; // Keep the same course for prediction
      const { years: predictionYears, totals: predictionTotals } = aggregate(dataset, predictionCourse)
      const nextYear = (predictionYears[predictionYears.length - 1] || 2025) + 1
      const { predicted, acc } = simpleForecast(predictionYears, predictionTotals, 2026)
  
      // Properly destroy existing charts to prevent memory leaks
      if (trendChart) {
        trendChart.destroy()
        trendChart = null
      }
      if (forecastChart) {
        forecastChart.destroy()
        forecastChart = null
      }
  
      // Trend chart with 2026 prediction dashed trace
      const trendLabels = years.map((y) => String(y)).concat(["2026"])
      const actualData = totals.concat([null])
      const predictionData = Array(Math.max(0, years.length - 1))
        .fill(null)
        .concat([totals[totals.length - 1] || 0, predicted])

      const trendData = {
        labels: trendLabels,
        datasets: [
          {
            label: "Total Students",
            data: actualData,
            borderColor: "#1f77b4",
            backgroundColor: "rgba(31,119,180,0.15)",
            fill: true,
            tension: 0.25,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2
          },
          {
            label: "2026 Prediction",
            data: predictionData,
            borderColor: "#ff7f0e",
            backgroundColor: "rgba(255,127,14,0.15)",
            fill: true,
            tension: 0.25,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2,
            borderDash: [6, 4]
          }
        ],
      }
  
      trendChart = new Chart(trendCtx, {
        type: "line",
        data: trendData,
        options: chartConfig
      })
  
      // Forecast chart with fixed configuration
      const forecastData = {
        labels: [String((years[years.length - 1] || 2025)), "2026"],
        datasets: [
          {
            label: "Enrollment",
            data: [totals[totals.length - 1] || 0, predicted],
            backgroundColor: ["#1f77b4", "#ff7f0e"],
            borderColor: ["#1f77b4", "#ff7f0e"],
            borderWidth: 1
          },
        ],
      }
  
      forecastChart = new Chart(forecastCtx, {
        type: "bar",
        data: forecastData,
        options: chartConfig
      })
  
      if (info) {
        const courseText = selectedCourse === "__ALL__" ? "All Courses" : selectedCourse
        info.textContent = `Forecast for 2026 • ${courseText}${acc ? ` • Est. accuracy ~${acc.toFixed(1)}%` : ""}`
      }
    }
  
    function renderForSelection() {
      const selected = courseSelect.value || "__ALL__"
      if (!dataset || !dataset.rows.length) return
      renderCharts(selected)
    }
  
    // Handle window resize to properly resize charts
    function handleResize() {
      if (trendChart) trendChart.resize()
      if (forecastChart) forecastChart.resize()
    }
  
    let resizeTimeout
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout)
      resizeTimeout = setTimeout(handleResize, 300)
    })
  
    courseSelect.addEventListener("change", renderForSelection)
    loadCSV()
  }

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
  let currentJobCardBeingEdited = null // Track which job card is being edited
  let originalJobData = {} // Store original data for comparison

  // Initialize edit job functionality
  function initializeEditJobFunctionality() {
    const editJobModal = document.getElementById("editJobModal")
    const cancelEditJob = document.getElementById("cancelEditJob")
    const editJobForm = document.getElementById("editJobForm")

    // Function to extract job data from a job card
    function extractJobData(jobCard) {
      const jobTitle = jobCard.querySelector(".job-title")?.textContent || ""
      const companyName =
        jobCard.querySelector(".job-details p strong")?.parentNode?.textContent?.replace("Company:", "").trim() || ""
      const location =
        jobCard.querySelector(".job-info-item .fa-map-marker-alt")?.parentNode?.querySelector("span")?.textContent || ""
      const salary =
        jobCard.querySelector(".job-info-item .fa-dollar-sign")?.parentNode?.querySelector("span")?.textContent || ""
      const experience =
        jobCard.querySelector(".job-info-item .fa-clock")?.parentNode?.querySelector("span")?.textContent || ""
      const description = jobCard.querySelector(".job-description")?.textContent || ""

      return {
        jobTitle,
        companyName,
        location,
        salary,
        experience,
        description,
      }
    }

    // Function to populate edit form with current data
    function populateEditForm(jobData) {
      document.getElementById("editJobTitle").value = jobData.jobTitle
      document.getElementById("editCompanyName").value = jobData.companyName
      document.getElementById("editLocation").value = jobData.location
      document.getElementById("editSalary").value = jobData.salary
      document.getElementById("editExperience").value = jobData.experience
      document.getElementById("editDescription").value = jobData.description
    }

    // Function to update job card with new data
    function updateJobCard(jobCard, newData) {
      // Update job title
      const jobTitleElement = jobCard.querySelector(".job-title")
      if (jobTitleElement) jobTitleElement.textContent = newData.jobTitle

      // Update company name
      const companyElement = jobCard.querySelector(".job-details p strong")
      if (companyElement && companyElement.parentNode) {
        companyElement.parentNode.innerHTML = `<strong>Company:</strong> ${newData.companyName}`
      }

      // Update location
      const locationElement = jobCard
        .querySelector(".job-info-item .fa-map-marker-alt")
        ?.parentNode?.querySelector("span")
      if (locationElement) locationElement.textContent = newData.location

      // Update salary
      const salaryElement = jobCard.querySelector(".job-info-item .fa-dollar-sign")?.parentNode?.querySelector("span")
      if (salaryElement) salaryElement.textContent = newData.salary

      // Update experience
      const experienceElement = jobCard.querySelector(".job-info-item .fa-clock")?.parentNode?.querySelector("span")
      if (experienceElement) experienceElement.textContent = newData.experience

      // Update description
      const descriptionElement = jobCard.querySelector(".job-description")
      if (descriptionElement) descriptionElement.textContent = newData.description

      // Add a brief highlight animation to show the card was updated
      jobCard.style.animation = "pulse 0.5s ease-out"
      setTimeout(() => {
        jobCard.style.animation = ""
      }, 500)
    }

    // Function to check if data has changed
    function hasDataChanged(originalData, newData) {
      return JSON.stringify(originalData) !== JSON.stringify(newData)
    }

    // Add event listeners to all existing edit buttons
    function attachEditListeners() {
      const editButtons = document.querySelectorAll(".edit-btn")
      editButtons.forEach((button) => {
        // Remove existing listeners to prevent duplicates
        button.removeEventListener("click", handleEditClick)
        button.addEventListener("click", handleEditClick)
      })
    }

    // Handle edit button click
    function handleEditClick(event) {
      const jobCard = event.target.closest(".job-card")
      if (!jobCard) return

      currentJobCardBeingEdited = jobCard
      originalJobData = extractJobData(jobCard)

      // Populate form with current data
      populateEditForm(originalJobData)

      // Show modal
      if (editJobModal) {
        editJobModal.style.display = "flex"
        const modalContent = editJobModal.querySelector(".modal-content")
        if (modalContent) {
          modalContent.classList.remove("popOut")
          modalContent.style.animation = "scaleIn 0.25s"
        }
      }
    }

    // Hide modal function
    function hideEditModal() {
      if (editJobModal) {
        const modalContent = editJobModal.querySelector(".modal-content")
        if (modalContent) {
          modalContent.style.animation = "popOut 0.25s"
          modalContent.classList.add("popOut")
          modalContent.addEventListener("animationend", function handler() {
            editJobModal.style.display = "none"
            modalContent.classList.remove("popOut")
            modalContent.removeEventListener("animationend", handler)
          })
        } else {
          editJobModal.style.display = "none"
        }
      }
      currentJobCardBeingEdited = null
      originalJobData = {}
    }

    // Cancel button event listener
    if (cancelEditJob) {
      cancelEditJob.addEventListener("click", hideEditModal)
    }

    // Hide modal on overlay click
    if (editJobModal) {
      editJobModal.addEventListener("click", (e) => {
        if (e.target === editJobModal) {
          hideEditModal()
        }
      })
    }

    // Form submission handler
    if (editJobForm) {
      editJobForm.addEventListener("submit", (e) => {
        e.preventDefault()

        if (!currentJobCardBeingEdited) return

        const formData = new FormData(editJobForm)
        const newData = {
          jobTitle: formData.get("jobTitle"),
          companyName: formData.get("companyName"),
          location: formData.get("location"),
          salary: formData.get("salary"),
          experience: formData.get("experience"),
          description: formData.get("description"),
        }

        // Check if data has actually changed
        if (hasDataChanged(originalJobData, newData)) {
          // Update the job card with new data
          updateJobCard(currentJobCardBeingEdited, newData)
          console.log("Job updated successfully:", newData.jobTitle)

          // Show success message (optional)
          if (window.dashboardFunctions && window.dashboardFunctions.showToast) {
            window.dashboardFunctions.showToast("Job updated successfully!", "success")
          }
        } else {
          console.log("No changes detected, keeping original data")
        }

        // Hide modal
        hideEditModal()
      })
    }

    // Initialize listeners for existing buttons
    attachEditListeners()

    // Return function to attach listeners to new buttons (for dynamically created job cards)
    return {
      attachEditListeners,
      handleEditClick,
    }
  }

  // Initialize the edit functionality
  const editJobManager = initializeEditJobFunctionality()

  // Make edit functionality available globally for new job cards
  window.attachEditListener = (editButton) => {
    if (editButton && editJobManager) {
      editButton.addEventListener("click", editJobManager.handleEditClick)
    }
  }

  // Enhanced job actions with edit and delete functionality
  function initializeJobActionsWithEdit() {
    const editBtns = document.querySelectorAll(".edit-btn")
    const deleteBtns = document.querySelectorAll(".delete-btn")
    const deleteJobModal = document.getElementById("deleteJobModal")
    const confirmDeleteJobBtn = document.getElementById("confirmDeleteJobBtn")
    const cancelDeleteJobBtn = document.getElementById("cancelDeleteJobBtn")
    let jobCardToDelete = null

    // Edit buttons are now handled by the edit functionality above
    // Delete button functionality remains the same
    deleteBtns.forEach((btn) => {
      btn.addEventListener("click", function () {
        jobCardToDelete = this.closest(".job-card")
        if (deleteJobModal) {
          deleteJobModal.style.display = "flex"
        }
      })
    })

    if (cancelDeleteJobBtn && deleteJobModal) {
      cancelDeleteJobBtn.addEventListener("click", () => {
        deleteJobModal.style.display = "none"
        jobCardToDelete = null
      })
    }

    if (confirmDeleteJobBtn && deleteJobModal) {
      confirmDeleteJobBtn.addEventListener("click", () => {
        if (jobCardToDelete) {
          jobCardToDelete.classList.add("deleting")
          jobCardToDelete.addEventListener("animationend", () => {
            jobCardToDelete.remove()
          })
          deleteJobModal.style.display = "none"
        }
      })
    }

    // Hide modal on overlay click
    if (deleteJobModal) {
      deleteJobModal.addEventListener("click", (e) => {
        if (e.target === deleteJobModal) {
          deleteJobModal.style.display = "none"
          jobCardToDelete = null
        }
      })
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
    initializeCareerAnalytics()

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

function addRecentTask(title) {
  const recentTasksList = document.querySelector(".recent-tasks-list")
  if (!recentTasksList) return

  const taskElement = document.createElement("div")
  taskElement.className = "recent-task"
  taskElement.innerHTML = `
    <span class="recent-task-title">${title}</span>
    <span class="recent-task-date">${new Date().toLocaleDateString()}</span>
  `

  // Insert at the top
  recentTasksList.prepend(taskElement)

  // Optional limit to last 5 tasks
  const tasks = recentTasksList.querySelectorAll(".recent-task")
  if (tasks.length > 5) tasks[tasks.length - 1].remove()
}

function addCompletedTask(title) {
  const completedTasksList = document.querySelector(".completed-tasks-list")
  if (!completedTasksList) return

  const taskElement = document.createElement("div")
  taskElement.className = "completed-task"
  taskElement.innerHTML = `
    <span class="completed-task-title">${title}</span>
    <span class="completed-task-date">${new Date().toLocaleDateString()}</span>
  `

  completedTasksList.prepend(taskElement)

  // Optional: Limit to 5 tasks
  const tasks = completedTasksList.querySelectorAll(".completed-task")
  if (tasks.length > 5) tasks[tasks.length - 1].remove()
}

function addActivity(title, date = new Date().toLocaleDateString()) {
  const activitiesList = document.getElementById("activitiesList")
  if (!activitiesList) return

  const activityCard = document.createElement("div")
  activityCard.className = "activity-card"
  activityCard.innerHTML = `
    <div class="activity-title">${title}</div>
    <div class="activity-date">Posted on ${date}</div>
  `

  activitiesList.prepend(activityCard)
}

function initializeGradesFiltering() {
  const semesterFilter = document.getElementById("semesterFilter")
  const courseFilter = document.getElementById("courseFilter")
  const gradesTableBody = document.getElementById("gradesTableBody")

  if (!semesterFilter || !courseFilter || !gradesTableBody) return

  // Sample grades data (in a real application, this would come from a database)
  const allGrades = [
    {
      courseCode: "WLD-101",
      courseName: "Basic Welding Techniques",
      instructor: "Prof. Martinez",
      credits: 3,
      grade: "A",
      status: "Completed",
      semester: "2024-1",
      category: "welding",
    },
    {
      courseCode: "ELE-102",
      courseName: "Electrical Fundamentals",
      instructor: "Prof. Santos",
      credits: 4,
      grade: "B+",
      status: "Completed",
      semester: "2024-1",
      category: "electrical",
    },
    {
      courseCode: "AUTO-103",
      courseName: "Engine Diagnostics",
      instructor: "Prof. Reyes",
      credits: 3,
      grade: "A-",
      status: "Completed",
      semester: "2024-1",
      category: "automotive",
    },
    {
      courseCode: "GEN-104",
      courseName: "Technical Mathematics",
      instructor: "Prof. Cruz",
      credits: 3,
      grade: "B",
      status: "Completed",
      semester: "2023-2",
      category: "general",
    },
    {
      courseCode: "WLD-201",
      courseName: "Advanced Welding",
      instructor: "Prof. Martinez",
      credits: 4,
      grade: "In Progress",
      status: "In Progress",
      semester: "current",
      category: "welding",
    },
    {
      courseCode: "ELE-201",
      courseName: "Industrial Wiring",
      instructor: "Prof. Santos",
      credits: 4,
      grade: "In Progress",
      status: "In Progress",
      semester: "current",
      category: "electrical",
    },
  ]

  function getGradeBadgeClass(grade) {
    if (grade === "In Progress") return "grade-in-progress"
    if (grade.startsWith("A")) return "grade-a"
    if (grade.startsWith("B")) return "grade-b"
    if (grade.startsWith("C")) return "grade-c"
    if (grade.startsWith("D")) return "grade-d"
    if (grade.startsWith("F")) return "grade-f"
    return "grade-in-progress"
  }

  function getStatusBadgeClass(status) {
    if (status === "Completed") return "status-completed"
    if (status === "In Progress") return "status-in-progress"
    if (status === "Failed") return "status-failed"
    return "status-in-progress"
  }

  function renderGrades(grades) {
    gradesTableBody.innerHTML = ""

    grades.forEach((grade, index) => {
      const row = document.createElement("tr")
      row.style.animationDelay = `${index * 0.1}s`
      row.className = "grade-row-animate"

      row.innerHTML = `
        <td>${grade.courseCode}</td>
        <td>${grade.courseName}</td>
        <td>${grade.instructor}</td>
        <td>${grade.credits}</td>
        <td><span class="grade-badge ${getGradeBadgeClass(grade.grade)}">${grade.grade}</span></td>
        <td><span class="status-badge ${getStatusBadgeClass(grade.status)}">${grade.status}</span></td>
        <td>${grade.semester === "current" ? "Current" : grade.semester}</td>
      `

      gradesTableBody.appendChild(row)
    })
  }

  function filterGrades() {
    const selectedSemester = semesterFilter.value
    const selectedCourse = courseFilter.value

    let filteredGrades = allGrades

    // Filter by semester
    if (selectedSemester && selectedSemester !== "all") {
      filteredGrades = filteredGrades.filter((grade) => grade.semester === selectedSemester)
    }

    // Filter by course category
    if (selectedCourse) {
      filteredGrades = filteredGrades.filter((grade) => grade.category === selectedCourse)
    }

    renderGrades(filteredGrades)
  }

  // Add event listeners
  semesterFilter.addEventListener("change", filterGrades)
  courseFilter.addEventListener("change", filterGrades)

  // Initial render
  renderGrades(allGrades)
}

function initializeGradeAnimations() {
  // Add CSS for row animation
  const style = document.createElement("style")
  style.textContent = `
    .grade-row-animate {
      opacity: 0;
      transform: translateX(-20px);
      animation: slideInRow 0.5s ease-out forwards;
    }
    
    @keyframes slideInRow {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
  `
  document.head.appendChild(style)

  // Animate grade cards when grades section becomes active
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const cards = entry.target.querySelectorAll(".grade-detail-card")
          cards.forEach((card, index) => {
            setTimeout(() => {
              card.style.opacity = "1"
              card.style.transform = "translateY(0)"
            }, index * 200)
          })
        }
      })
    },
    { threshold: 0.1 },
  )

  const gradeDetailsSection = document.querySelector(".grade-details-section")
  if (gradeDetailsSection) {
    observer.observe(gradeDetailsSection)

    // Initially hide cards for animation
    const cards = gradeDetailsSection.querySelectorAll(".grade-detail-card")
    cards.forEach((card) => {
      card.style.opacity = "0"
      card.style.transform = "translateY(20px)"
      card.style.transition = "opacity 0.6s ease-out, transform 0.6s ease-out"
    })
  }
}

// Function to calculate and update GPA (could be called when grades change)
function updateGPA() {
  const gradePoints = {
    A: 4.0,
    "A-": 3.7,
    "B+": 3.3,
    B: 3.0,
    "B-": 2.7,
    "C+": 2.3,
    C: 2.0,
    "C-": 1.7,
    "D+": 1.3,
    D: 1.0,
    F: 0.0,
  }

  // This would typically fetch real grade data
  const completedGrades = ["A", "B+", "A-", "B"]
  const totalPoints = completedGrades.reduce((sum, grade) => sum + (gradePoints[grade] || 0), 0)
  const gpa = (totalPoints / completedGrades.length).toFixed(2)

  const gpaElement = document.querySelector(".stat-value")
  if (gpaElement && gpaElement.textContent.includes(".")) {
    gpaElement.textContent = gpa
  }
}

// Export functions for potential external use
window.gradesModule = {
  updateGPA,
  initializeGradesFiltering,
  initializeGradeAnimations,
}
function initializePassFailGradesFiltering() {
  const semesterFilter = document.getElementById("semesterFilter")
  const courseFilter = document.getElementById("courseFilter")
  const gradesTableBody = document.getElementById("gradesTableBody")

  if (!semesterFilter || !courseFilter || !gradesTableBody) return

  // Sample Pass/Fail grades data (without credits)
  const allGrades = [
    {
      courseCode: "WLD-101",
      courseName: "Basic Welding Techniques",
      instructor: "Prof. Martinez",
      grade: "PASS",
      status: "Completed",
      semester: "2024-1",
      category: "welding",
    },
    {
      courseCode: "ELE-102",
      courseName: "Electrical Fundamentals",
      instructor: "Prof. Santos",
      grade: "PASS",
      status: "Completed",
      semester: "2024-1",
      category: "electrical",
    },
    {
      courseCode: "AUTO-103",
      courseName: "Engine Diagnostics",
      instructor: "Prof. Reyes",
      grade: "PASS",
      status: "Completed",
      semester: "2024-1",
      category: "automotive",
    },
    {
      courseCode: "GEN-104",
      courseName: "Technical Mathematics",
      instructor: "Prof. Cruz",
      grade: "PASS",
      status: "Completed",
      semester: "2023-2",
      category: "general",
    },
    {
      courseCode: "MEC-105",
      courseName: "Machine Operations",
      instructor: "Prof. Garcia",
      grade: "PASS",
      status: "Completed",
      semester: "2023-2",
      category: "general",
    },
    {
      courseCode: "SAF-106",
      courseName: "Workplace Safety",
      instructor: "Prof. Lopez",
      grade: "PASS",
      status: "Completed",
      semester: "2023-1",
      category: "general",
    },
    {
      courseCode: "WLD-201",
      courseName: "Advanced Welding",
      instructor: "Prof. Martinez",
      grade: "In Progress",
      status: "In Progress",
      semester: "current",
      category: "welding",
    },
    {
      courseCode: "ELE-201",
      courseName: "Industrial Wiring",
      instructor: "Prof. Santos",
      grade: "In Progress",
      status: "In Progress",
      semester: "current",
      category: "electrical",
    },
  ]

  function getGradeBadgeClass(grade) {
    if (grade === "PASS") return "grade-pass"
    if (grade === "FAIL") return "grade-fail"
    return "grade-in-progress"
  }

  function getStatusBadgeClass(status) {
    if (status === "Completed") return "status-completed"
    if (status === "In Progress") return "status-in-progress"
    if (status === "Failed") return "status-failed"
    return "status-in-progress"
  }

  function renderGrades(grades) {
    gradesTableBody.innerHTML = ""

    grades.forEach((grade, index) => {
      const row = document.createElement("tr")
      row.style.animationDelay = `${index * 0.1}s`
      row.className = "grade-row-animate"

      // Updated row HTML without credits column
      row.innerHTML = `
        <td>${grade.courseCode}</td>
        <td>${grade.courseName}</td>
        <td>${grade.instructor}</td>
        <td><span class="grade-badge ${getGradeBadgeClass(grade.grade)}">${grade.grade}</span></td>
        <td><span class="status-badge ${getStatusBadgeClass(grade.status)}">${grade.status}</span></td>
        <td>${grade.semester === "current" ? "Current" : grade.semester}</td>
      `

      gradesTableBody.appendChild(row)
    })

    // Update statistics after rendering
    updateStatistics(grades)
  }

  function filterGrades() {
    const selectedSemester = semesterFilter.value
    const selectedCourse = courseFilter.value

    let filteredGrades = allGrades

    // Filter by semester
    if (selectedSemester && selectedSemester !== "all") {
      filteredGrades = filteredGrades.filter((grade) => grade.semester === selectedSemester)
    }

    // Filter by course category
    if (selectedCourse) {
      filteredGrades = filteredGrades.filter((grade) => grade.category === selectedCourse)
    }

    renderGrades(filteredGrades)
  }

  function updateStatistics(grades) {
    const completedCourses = grades.filter((grade) => grade.status === "Completed")
    const passedCourses = completedCourses.filter((grade) => grade.grade === "PASS")
    const totalCourses = grades.length
    const passRate =
      completedCourses.length > 0 ? Math.round((passedCourses.length / completedCourses.length) * 100) : 100

    // Update stat cards
    const statValues = document.querySelectorAll(".stat-value")
    if (statValues.length >= 3) {
      statValues[0].textContent = passedCourses.length // Courses Passed
      statValues[1].textContent = totalCourses // Total Courses
      statValues[2].textContent = `${passRate}%` // Pass Rate
    }
  }

  // Add event listeners
  semesterFilter.addEventListener("change", filterGrades)
  courseFilter.addEventListener("change", filterGrades)

  // Initial render
  renderGrades(allGrades)
}

function initializeGradeAnimations() {
  // Add CSS for row animation
  const style = document.createElement("style")
  style.textContent = `
    .grade-row-animate {
      opacity: 0;
      transform: translateX(-20px);
      animation: slideInRow 0.5s ease-out forwards;
    }
    
    @keyframes slideInRow {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
  `
  document.head.appendChild(style)

  // Animate grade cards when grades section becomes active
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const cards = entry.target.querySelectorAll(".grade-detail-card")
          cards.forEach((card, index) => {
            setTimeout(() => {
              card.style.opacity = "1"
              card.style.transform = "translateY(0)"
            }, index * 200)
          })
        }
      })
    },
    { threshold: 0.1 },
  )

  const gradeDetailsSection = document.querySelector(".grade-details-section")
  if (gradeDetailsSection) {
    observer.observe(gradeDetailsSection)

    // Initially hide cards for animation
    const cards = gradeDetailsSection.querySelectorAll(".grade-detail-card")
    cards.forEach((card) => {
      card.style.opacity = "0"
      card.style.transform = "translateY(20px)"
      card.style.transition = "opacity 0.6s ease-out, transform 0.6s ease-out"
    })
  }
}

function updatePassRate() {
  // Calculate pass rate from completed courses
  const completedGrades = ["PASS", "PASS", "PASS", "PASS", "PASS", "PASS"] // Sample data
  const passedCourses = completedGrades.filter((grade) => grade === "PASS").length
  const passRate = Math.round((passedCourses / completedGrades.length) * 100)

  // Update the pass rate display
  const passRateElement = document.querySelector(".stat-card:last-child .stat-value")
  if (passRateElement) {
    passRateElement.textContent = `${passRate}%`
  }
}

// Function to add a new grade (for future use) - updated without credits
function addNewGrade(courseData) {
  const gradesTableBody = document.getElementById("gradesTableBody")
  if (!gradesTableBody) return

  const row = document.createElement("tr")
  row.className = "grade-row-animate"

  const gradeClass =
    courseData.grade === "PASS" ? "grade-pass" : courseData.grade === "FAIL" ? "grade-fail" : "grade-in-progress"

  const statusClass =
    courseData.status === "Completed"
      ? "status-completed"
      : courseData.status === "Failed"
        ? "status-failed"
        : "status-in-progress"

  // Updated row HTML without credits column
  row.innerHTML = `
    <td>${courseData.courseCode}</td>
    <td>${courseData.courseName}</td>
    <td>${courseData.instructor}</td>
    <td><span class="grade-badge ${gradeClass}">${courseData.grade}</span></td>
    <td><span class="status-badge ${statusClass}">${courseData.status}</span></td>
    <td>${courseData.semester}</td>
  `

  gradesTableBody.appendChild(row)
}

// Export functions for potential external use
window.passFailGradesModule = {
  updatePassRate,
  initializePassFailGradesFiltering,
  initializeGradeAnimations,
  addNewGrade,
}

// Industry Chart Rendering (copied from admin.js)
document.addEventListener('DOMContentLoaded', function() {
  // Render single Top-10 bar chart if payload exists
  const industryCanvas = document.getElementById("industryEmploymentChart")
  if (industryCanvas && window.__industryBarData && Array.isArray(window.__industryBarData.values)) {
    console.log('Rendering industry chart with data:', window.__industryBarData)
    const ctx = industryCanvas.getContext("2d")
    const labels = window.__industryBarData.labels || []
    const values = window.__industryBarData.values || []

    // eslint-disable-next-line no-undef
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: `MMTVTC Graduates`,
          data: values,
          backgroundColor: 'rgba(135, 206, 250, 0.6)',
          borderColor: 'rgba(0, 71, 171, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45, minRotation: 0 } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } }
        }
      }
    })
  } else if (industryCanvas) {
    console.warn('Industry chart missing data. window.__industryBarData =', window.__industryBarData)
  }

  // Render top program trend line with confidence range
  const trendCanvas = document.getElementById("industryTopTrendChart")
  if (trendCanvas && window.__industryBarData && window.__industryBarData.top) {
    const t = window.__industryBarData.top
    const years = (t.years || []).map(Number)
    const totals = (t.totals || []).map(Number)
    const lastYear = years.length ? years[years.length - 1] : null
    const predYear = (lastYear || 2025) + 1

    const pred = Number(t.pred || 0)
    const lower = Number(t.lower || 0)
    const upper = Number(t.upper || 0)

    const labels = years.concat([predYear])
    const histData = totals

    // eslint-disable-next-line no-undef
    let trendChart = new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Historical',
            data: histData,
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            fill: false,
            tension: 0.1
          },
          {
            label: 'Prediction',
            data: new Array(histData.length - 1).fill(null).concat([histData[histData.length - 1], pred]),
            borderColor: 'rgba(255, 99, 132, 1)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            fill: false,
            tension: 0.1,
            borderDash: [5, 5]
          },
          {
            label: 'Upper Bound',
            data: new Array(histData.length - 1).fill(null).concat([null, upper]),
            borderColor: 'rgba(255, 99, 132, 0.3)',
            backgroundColor: 'rgba(255, 99, 132, 0.05)',
            fill: '+1',
            tension: 0.1,
            borderDash: [2, 2]
          },
          {
            label: 'Lower Bound',
            data: new Array(histData.length - 1).fill(null).concat([null, lower]),
            borderColor: 'rgba(255, 99, 132, 0.3)',
            backgroundColor: 'rgba(255, 99, 132, 0.05)',
            fill: false,
            tension: 0.1,
            borderDash: [2, 2]
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 } } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } }
        }
      }
    })

    // Populate dropdown and wire change handler
    const select = document.getElementById('industryTrendSelect')
    const titleEl = document.getElementById('industryTrendTitle')
    if (select && Array.isArray(window.__industryBarData.topList)) {
      select.innerHTML = ''
      window.__industryBarData.topList.forEach((item, idx) => {
        const opt = document.createElement('option')
        opt.value = String(idx)
        opt.textContent = item.name
        select.appendChild(opt)
      })
      select.value = '0'

      select.addEventListener('change', () => {
        const idx = Number(select.value)
        const item = window.__industryBarData.topList[idx]
        if (!item) return
        const y = (item.years || []).map(Number)
        const totals2 = (item.totals || []).map(Number)
        const lastY = y.length ? y[y.length - 1] : null
        const pYear = (lastY || 2025) + 1
        const lbls = y.concat([pYear])

        // Update datasets in place
        trendChart.data.labels = lbls
        trendChart.data.datasets[0].data = totals2
        trendChart.data.datasets[1].data = new Array(totals2.length - 1).fill(null).concat([totals2[totals2.length - 1], Number(item.pred || 0)])
        trendChart.data.datasets[2].data = new Array(totals2.length - 1).fill(null).concat([null, Number(item.upper || 0)])
        trendChart.data.datasets[3].data = new Array(totals2.length - 1).fill(null).concat([null, Number(item.lower || 0)])
        titleEl && (titleEl.textContent = String(item.name).slice(0, 25) + '...')
        trendChart.update()
      })
    }
  }
})

