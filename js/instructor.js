 // Jobs list rendering for Instructor Dashboard
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
                    try { fetch('apis/client_event_log.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ event:'cookie_flag', page: window.location.href }) }); } catch(_){ }
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
            var link = document.querySelector('a[href="logout.php"]');
            if (btnA) { btnA.addEventListener('click', handleLogoutClick, { capture: true }); }
            if (link) { link.addEventListener('click', handleLogoutClick, { capture: true }); }
        });
    })();
    function renderJobs(jobs){
        var grid = document.querySelector('#job-matching .job-cards-grid');
        if(!grid) return;
        grid.innerHTML = jobs.map(function(job){
            return (
                '<div class="job-card">'
              + '  <div class="job-header">'
              + '    <h3 class="job-title">'+ escapeHtml(job.title) +'</h3>'
              + '  </div>'
              + '  <div class="job-details">'
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

// ========== GLOBAL STATE ==========
const globalState = {
  activeSection: "dashboard",
  sidebarCollapsed: false,
  sidebarMobileOpen: false,
  notificationOpen: false,
  userDropdownOpen: false,
  currentCourseFilter: null // Track current course filter for quiz submissions
};

// ========== GRADE BREAKDOWN FUNCTIONS ==========

// Enhanced function to render student grade breakdown table
function renderStudentGradesBreakdown(student) {
  const tbody = document.getElementById("studentGradesBreakdownBody");
  if (!tbody || !student) return;

  tbody.innerHTML = "";

  const breakdown = student.gradeBreakdown || {
    grade1: 0,
    grade2: 0,
    grade3: 0,
    grade4: 0,
    finalGrade: 0,
    remarks: "NO DATA",
  };

  // Determine remark styling
  let remarkClass = "badge ";
  let remarkIcon = "";
  switch (breakdown.remarks) {
    case "PASSED":
      remarkClass += "bg-success";
      remarkIcon = '<i class="fas fa-check-circle me-1"></i>';
      break;
    case "FAILED":
      remarkClass += "bg-danger";
      remarkIcon = '<i class="fas fa-times-circle me-1"></i>';
      break;
    case "INCOMPLETE":
      remarkClass += "bg-warning text-dark";
      remarkIcon = '<i class="fas fa-clock me-1"></i>';
      break;
    default:
      remarkClass += "bg-secondary";
      remarkIcon = '<i class="fas fa-question-circle me-1"></i>';
  }

  // Create the single row for the selected student
  const row = document.createElement("tr");
  row.className = "align-middle";

  row.innerHTML = `
          <td class="text-center fw-bold">1</td>
          <td class="text-center fw-semibold">${student.last_name || "N/A"}</td>
          <td class="text-center fw-semibold">${
            student.first_name || "N/A"
          }</td>
          <td class="text-center">${student.middle_name || "N/A"}</td>
          <td class="text-center">
              <span class="grade-cell ${getGradeColorClass(breakdown.grade1)}">
                  ${breakdown.grade1.toFixed(2)}%
              </span>
          </td>
          <td class="text-center">
              <span class="grade-cell ${getGradeColorClass(breakdown.grade2)}">
                  ${breakdown.grade2.toFixed(2)}%
              </span>
          </td>
          <td class="text-center">
              <span class="grade-cell ${getGradeColorClass(breakdown.grade3)}">
                  ${breakdown.grade3.toFixed(2)}%
              </span>
          </td>
          <td class="text-center">
              <span class="grade-cell ${getGradeColorClass(breakdown.grade4)}">
                  ${breakdown.grade4.toFixed(2)}%
              </span>
          </td>
          <td class="text-center">
              <span class="final-grade-cell ${getFinalGradeColorClass(
                breakdown.finalGrade
              )}">
                  <strong>${breakdown.finalGrade.toFixed(2)}%</strong>
              </span>
          </td>
          <td class="text-center">
              <span class="${remarkClass}">
                  ${remarkIcon}${breakdown.remarks}
              </span>
          </td>
      `;

  tbody.appendChild(row);

  // Update visual grade breakdown chart
  updateGradeBreakdownChart(breakdown);

  // Update performance summary
  updatePerformanceSummary(student, breakdown);
}

// ========== DETAIL TAB FUNCTIONS ==========

function initializeDetailTabs() {}

function updateDetailContent() {}

// ========== SLIDE SYSTEM FUNCTIONS ==========

function showDetailForStudent() {}

function updateHeaderForDetailView() {}

function initializeSlideSystem() {}

// ========== TABLE RENDERING FUNCTIONS ==========

function renderStudentsForActiveTab() {}

function renderGradesTable() {}

function renderAttendanceTable() {}

function renderEvaluationTable() {}

// ========== FILTERING SYSTEM ==========

function applyTraineeFilter() {
  // Search functionality removed - no longer needed
}

function initializeAllFilters() {
  console.log("Initializing filters...");
  
  // Search functionality removed - no longer needed
  
  console.log("Filters initialized successfully!");
}

// ========== TAB SWITCHING SYSTEM ==========

function initializeTabSwitching() {
  const tabButtons = document.querySelectorAll("#trainee .tabs-nav .tab");

  if (tabButtons.length === 0) {
    console.log("No trainee tab buttons found");
    return;
  }

  function showTable(tabName) {
    // Hide all tables
    const tables = [
      "gradesTable",
      "studentsTable",
      "jobMatchingAssessmentTable",
    ];
    tables.forEach((id) => {
      const table = document.getElementById(id);
      if (table) {
        table.style.display = "none";
      }
    });

    // Show the target table
    let targetTableId = "";

    switch (tabName) {
      case "grades":
        targetTableId = "gradesTable";
        break;
      case "attendance":
        targetTableId = "studentsTable";
        break;
      case "evaluation":
        targetTableId = "jobMatchingAssessmentTable";
        break;
    }

    const targetTable = document.getElementById(targetTableId);
    if (targetTable) {
      targetTable.style.display = "table";
      renderStudentsForActiveTab();
    }

    // Update Create Activity button visibility
    updateCreateActivityButtonVisibility(tabName);
  }

  // Add click event listeners
  const tabContainer = document.getElementById("traineeTabsContainer");
  if (tabContainer) {
      tabContainer.addEventListener("click", function(e) {
          const button = e.target.closest(".tab");
          if (button) {
              e.preventDefault();
              const tabName = button.getAttribute("data-tab");
              if (tabName) {
                  switchTab(tabName);
              }
          }
      });
  }

  // Initialize with grades tab
  const gradesTab = document.querySelector('#trainee [data-tab="grades"]');
  if (gradesTab) {
    gradesTab.classList.add("active");
    showTable("grades");
  }
}

function updateCreateActivityButtonVisibility(activeTabName) {
  const createActivityBtn = document.querySelector(".create-activity-btn");
  if (!createActivityBtn) return;

  const tabsNav = document.querySelector("#trainee .tabs-nav");
  if (tabsNav) {
    tabsNav.setAttribute("data-active-tab", activeTabName || "grades");
  }

  if (activeTabName === "grades") {
    createActivityBtn.style.display = "flex";
  } else {
    createActivityBtn.style.display = "none";
  }
}

function initializeAllTables() {
  console.log("Initializing all tables...");

  initializeTabSwitching();
  renderStudentsForActiveTab();

  console.log("All tables initialized!");
}

// ========== TRAINEE RECORD TAB FUNCTIONALITY ==========

function initializeTraineeRecordTabs() {
  const tabContainer = document.getElementById("traineeTabsContainer");
  if (!tabContainer) return;

  const tabButtons = tabContainer.querySelectorAll(".tab");
  const tabPanels = tabContainer.querySelectorAll(".tab-panel");
  const createBtn = tabContainer.querySelector(".create-activity-btn");

  function switchTab(targetTab) {
    // Update tab buttons
    tabButtons.forEach((btn) => btn.classList.remove("active"));
    const activeButton = tabContainer.querySelector(
      `[data-tab="${targetTab}"]`
    );
    if (activeButton) {
      activeButton.classList.add("active");
    }

    // Update tab panels
    tabPanels.forEach((panel) => {
      panel.classList.remove("active");
      panel.style.display = "none";
    });

    const activePanel = tabContainer.querySelector(
      `[data-panel="${targetTab}"]`
    );
    if (activePanel) {
      activePanel.classList.add("active");
      activePanel.style.display = "block";
    }

    // Update create button text and visibility
    if (createBtn) {
      if (targetTab === "grades") {
        createBtn.style.display = "flex";
        createBtn.innerHTML =
          '<i class="fas fa-plus"></i><span>Add Grade</span>';
      } else if (targetTab === "attendance") {
        createBtn.style.display = "flex";
        createBtn.innerHTML =
          '<i class="fas fa-calendar-plus"></i><span>Mark Attendance</span>';
      } else if (targetTab === "quizzes") {
        createBtn.style.display = "flex";
        createBtn.innerHTML =
          '<i class="fas fa-plus"></i><span>Create Quiz</span>';
      } else if (targetTab === "exam") {
        createBtn.style.display = "flex";
        createBtn.innerHTML =
          '<i class="fas fa-plus"></i><span>Create Exam</span>';
      } else {
        createBtn.style.display = "none";
      }
    }

    console.log(`Switched to ${targetTab} tab`);
  }

  // Add click event listeners to tab buttons
  tabButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const tabName = this.getAttribute("data-tab");
      if (tabName) {
        switchTab(tabName);
      }
    });
  });

  // Initialize with grades tab active
  switchTab("grades");

  console.log("Trainee record tabs initialized successfully");
}

// Placeholder functions for button actions
function showStudentDetails(studentId) {
  console.log(`Showing details for student: ${studentId}`);
}

// ========== QUIZZES TAB FUNCTIONALITY ==========

function backToQuizzesCourses() {
  const quizzesContent = document.getElementById('quizzesContent');
  const quizzesCourseSelector = document.querySelector('.quizzes-course-selector');
  
  if (quizzesContent && quizzesCourseSelector) {
    quizzesContent.style.display = 'none';
    quizzesCourseSelector.style.display = 'block';
  }
}

function createNewQuiz() {
  console.log('Opening quiz creation modal...');
  const modal = document.getElementById('quizCreationModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    // Initialize with one question
    addQuizQuestion();
  }
}

function closeQuizModal() {
  const modal = document.getElementById('quizCreationModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    // Reset form
    document.getElementById('quizTitle').value = 'Untitled Quiz';
    document.getElementById('quizDescription').value = '';
    // Clear all questions
    const questionBuilder = document.getElementById('quizQuestionBuilder');
    if (questionBuilder) {
      questionBuilder.innerHTML = '';
    }
    // Reset question count
    quizQuestionCount = 0;
    
    // Reset edit mode flags
    window.isEditMode = false;
    window.currentEditQuizId = null;
  }
}

function selectQuizFormat(format) {
  // Remove active class from all format options
  document.querySelectorAll('.format-option').forEach(option => {
    option.classList.remove('active');
  });
  
  // Add active class to selected format
  const selectedOption = document.querySelector(`input[value="${format}"]`).closest('.format-option');
  if (selectedOption) {
    selectedOption.classList.add('active');
    selectedOption.querySelector('input[type="radio"]').checked = true;
  }
}

// ========== QUIZ QUESTION BUILDER FUNCTIONS ==========

let quizQuestionCount = 0;

function addQuizQuestion() {
  quizQuestionCount++;
  const questionBuilder = document.getElementById('quizQuestionBuilder');
  
  const questionCard = document.createElement('div');
  questionCard.className = 'gforms-question-card';
  questionCard.id = `quiz-question-${quizQuestionCount}`;
  
  questionCard.innerHTML = `
    <div class="gforms-question-header">
      <input type="text" class="gforms-question-title" placeholder="Untitled Question" value="Untitled Question">
      <div class="gforms-question-type" onclick="showQuestionTypeMenu('quiz-question-${quizQuestionCount}')">
        <i class="fas fa-list-ul"></i>
        <span>Multiple choice</span>
        <i class="fas fa-chevron-down"></i>
      </div>
    </div>
    <div class="gforms-question-body">
      <div class="gforms-option">
        <input type="radio" name="quiz-question-${quizQuestionCount}" disabled>
        <input type="text" placeholder="Option 1" value="Option 1">
        <label class="gforms-correct-answer">
          <input type="checkbox" class="correct-answer-checkbox">
          <span>Correct Answer</span>
        </label>
      </div>
      <div class="gforms-option">
        <input type="radio" name="quiz-question-${quizQuestionCount}" disabled>
        <input type="text" placeholder="Option 2" value="Option 2">
        <label class="gforms-correct-answer">
          <input type="checkbox" class="correct-answer-checkbox">
          <span>Correct Answer</span>
        </label>
      </div>
      <div class="gforms-add-option" onclick="addQuizOption('quiz-question-${quizQuestionCount}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>
    </div>
    <div class="gforms-question-footer">
      <div class="gforms-question-actions">
        <button onclick="duplicateQuizQuestion('quiz-question-${quizQuestionCount}')" title="Duplicate">
          <i class="fas fa-copy"></i>
        </button>
        <button onclick="deleteQuizQuestion('quiz-question-${quizQuestionCount}')" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
        <div class="gforms-required-toggle">
          <input type="checkbox" id="required-${quizQuestionCount}">
          <label for="required-${quizQuestionCount}">Required</label>
        </div>
      </div>
    </div>
  `;
  
  questionBuilder.appendChild(questionCard);
  
  // Focus on the question title
  const titleInput = questionCard.querySelector('.gforms-question-title');
  titleInput.focus();
  titleInput.select();
}

function addQuizOption(questionId) {
  const questionCard = document.getElementById(questionId);
  const questionBody = questionCard.querySelector('.gforms-question-body');
  const addOptionDiv = questionCard.querySelector('.gforms-add-option');
  
  const optionDiv = document.createElement('div');
  optionDiv.className = 'gforms-option';
  optionDiv.innerHTML = `
    <input type="radio" name="${questionId}" disabled>
    <input type="text" placeholder="Option">
    <label class="gforms-correct-answer">
      <input type="checkbox" class="correct-answer-checkbox">
      <span>Correct Answer</span>
    </label>
  `;
  
  questionBody.insertBefore(optionDiv, addOptionDiv);
  
  // Focus on the new option input
  const optionInput = optionDiv.querySelector('input[type="text"]');
  optionInput.focus();
}

function duplicateQuizQuestion(questionId) {
  const originalCard = document.getElementById(questionId);
  const clonedCard = originalCard.cloneNode(true);
  
  quizQuestionCount++;
  clonedCard.id = `quiz-question-${quizQuestionCount}`;
  
  // Update radio button names
  const radioButtons = clonedCard.querySelectorAll('input[type="radio"]');
  radioButtons.forEach(radio => {
    radio.name = `quiz-question-${quizQuestionCount}`;
  });
  
  // Clear input values
  const textInputs = clonedCard.querySelectorAll('input[type="text"]');
  textInputs.forEach(input => {
    if (input.placeholder.includes('Option')) {
      input.value = input.placeholder;
    }
  });
  
  originalCard.parentNode.insertBefore(clonedCard, originalCard.nextSibling);
}

function deleteQuizQuestion(questionId) {
  const questionCard = document.getElementById(questionId);
  if (questionCard) {
    questionCard.remove();
  }
}

function showQuestionTypeMenu(questionId) {
  // Create dropdown menu for question types
  const questionCard = document.getElementById(questionId);
  const questionTypeDiv = questionCard.querySelector('.gforms-question-type');
  
  // Remove existing dropdown if any
  const existingDropdown = document.querySelector('.gforms-question-type-dropdown');
  if (existingDropdown) {
    existingDropdown.remove();
  }
  
  // Create dropdown menu
  const dropdown = document.createElement('div');
  dropdown.className = 'gforms-question-type-dropdown';
  dropdown.innerHTML = `
    <div class="gforms-dropdown-option" onclick="changeQuestionType('${questionId}', 'multiple-choice')">
      <i class="fas fa-list-ul"></i>
      <span>Multiple choice</span>
    </div>
    <div class="gforms-dropdown-option" onclick="changeQuestionType('${questionId}', 'paragraph')">
      <i class="fas fa-align-left"></i>
      <span>Paragraph</span>
    </div>
  `;
  
  // Position dropdown
  questionTypeDiv.style.position = 'relative';
  questionTypeDiv.appendChild(dropdown);
  
  // Close dropdown when clicking outside
  setTimeout(() => {
    document.addEventListener('click', function closeDropdown(e) {
      if (!questionTypeDiv.contains(e.target)) {
        dropdown.remove();
        document.removeEventListener('click', closeDropdown);
      }
    });
  }, 0);
}

function changeQuestionType(questionId, type) {
  const questionCard = document.getElementById(questionId);
  const questionBody = questionCard.querySelector('.gforms-question-body');
  const questionTypeDiv = questionCard.querySelector('.gforms-question-type');
  
  // Update the question type display
  const typeSpan = questionTypeDiv.querySelector('span');
  const typeIcon = questionTypeDiv.querySelector('i');
  
  if (type === 'multiple-choice') {
    typeIcon.className = 'fas fa-list-ul';
    typeSpan.textContent = 'Multiple choice';
    
    // Update question body for multiple choice
    questionBody.innerHTML = `
      <div class="gforms-option">
        <input type="radio" name="${questionId}" disabled>
        <input type="text" placeholder="Option 1" value="Option 1">
      </div>
      <div class="gforms-option">
        <input type="radio" name="${questionId}" disabled>
        <input type="text" placeholder="Option 2" value="Option 2">
      </div>
      <div class="gforms-add-option" onclick="addQuizOption('${questionId}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>
    `;
  } else if (type === 'paragraph') {
    typeIcon.className = 'fas fa-align-left';
    typeSpan.textContent = 'Paragraph';
    
    // Update question body for paragraph
    questionBody.innerHTML = `
      <div class="gforms-paragraph-answer">
        <textarea placeholder="Long answer text" class="gforms-long-answer" disabled></textarea>
      </div>
    `;
  }
  
  // Remove dropdown
  const dropdown = questionCard.querySelector('.gforms-question-type-dropdown');
  if (dropdown) {
    dropdown.remove();
  }
}

function saveQuiz() {
  const title = document.getElementById('quizTitle').value;
  const description = document.getElementById('quizDescription').value;
  
  if (!title.trim()) {
    alert('Please enter a quiz title.');
    return;
  }
  
  // Collect all questions
  const questions = [];
  const questionCards = document.querySelectorAll('#quizQuestionBuilder .gforms-question-card');
  
  if (questionCards.length === 0) {
    alert('Please add at least one question to the quiz.');
    return;
  }
  
  questionCards.forEach((card, index) => {
    const questionTitle = card.querySelector('.gforms-question-title').value;
    const questionType = card.querySelector('.gforms-question-type span').textContent.toLowerCase();
    const required = card.querySelector('input[type="checkbox"]').checked;
    
    let questionData = {
      title: questionTitle,
      type: questionType,
      required: required,
      order: index + 1
    };
    
    if (questionType === 'multiple choice') {
      const options = [];
      const optionInputs = card.querySelectorAll('.gforms-question-body input[type="text"]');
      const correctCheckboxes = card.querySelectorAll('.gforms-question-body .correct-answer-checkbox');
      
      optionInputs.forEach((input, index) => {
        if (input.value.trim()) {
          options.push({
            text: input.value.trim(),
            is_correct: correctCheckboxes[index] ? correctCheckboxes[index].checked : false
          });
        }
      });
      
      questionData.options = options;
    } else if (questionType === 'paragraph') {
      questionData.isLongAnswer = true;
    }
    
    questions.push(questionData);
  });
  
  const quizData = {
    title: title,
    description: description,
    questions: questions,
    created_at: new Date().toISOString()
  };
  
  console.log('Saving quiz:', quizData);
  
  // Check if we're in edit mode
  if (window.isEditMode && window.currentEditQuizId) {
    // Update existing quiz
    updateQuizToDatabase(quizData, window.currentEditQuizId);
  } else {
    // Create new quiz
    saveQuizToDatabase(quizData);
  }
}

function saveQuizToDatabase(quizData) {
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'create_quiz',
      quiz: quizData
    })
  })
  .then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      return response.text().then(text => {
        console.error('Non-JSON response:', text);
        throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
      });
    }
    
    return response.json();
  })
  .then(data => {
    console.log('API Response:', data);
    if (data.success) {
      // Show the beautiful confirmation popup
      showQuizSaveConfirmation(quizData.title, quizData.questions.length, data.quiz_id);
      closeQuizModal();
      // Reload the quiz list to show the new quiz
      loadQuizzes();
    } else {
      alert('Error saving quiz: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Detailed error:', error);
    alert('Error saving quiz: ' + error.message + '\n\nCheck browser console for details.');
  });
}

function previewQuiz() {
  alert('Preview functionality will be implemented here');
}

// ========== EXAM QUESTION BUILDER FUNCTIONS ==========

let examQuestionCount = 0;

function addExamQuestion() {
  examQuestionCount++;
  const questionBuilder = document.getElementById('examQuestionBuilder');
  
  const questionCard = document.createElement('div');
  questionCard.className = 'gforms-question-card';
  questionCard.id = `exam-question-${examQuestionCount}`;
  
  questionCard.innerHTML = `
    <div class="gforms-question-header">
      <input type="text" class="gforms-question-title" placeholder="Untitled Question" value="Untitled Question">
      <div class="gforms-question-type" onclick="showExamQuestionTypeMenu('exam-question-${examQuestionCount}')">
        <i class="fas fa-list-ul"></i>
        <span>Multiple choice</span>
        <i class="fas fa-chevron-down"></i>
      </div>
    </div>
    <div class="gforms-question-body">
      <div class="gforms-option">
        <input type="radio" name="exam-question-${examQuestionCount}" disabled>
        <input type="text" placeholder="Option 1" value="Option 1">
        <div class="gforms-correct-answer">
          <input type="checkbox" id="correct-exam-${examQuestionCount}-0" class="correct-answer-checkbox" data-option="0">
          <label for="correct-exam-${examQuestionCount}-0">Correct Answer</label>
        </div>
      </div>
      <div class="gforms-option">
        <input type="radio" name="exam-question-${examQuestionCount}" disabled>
        <input type="text" placeholder="Option 2" value="Option 2">
        <div class="gforms-correct-answer">
          <input type="checkbox" id="correct-exam-${examQuestionCount}-1" class="correct-answer-checkbox" data-option="1">
          <label for="correct-exam-${examQuestionCount}-1">Correct Answer</label>
        </div>
      </div>
      <div class="gforms-add-option" onclick="addExamOption('exam-question-${examQuestionCount}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>
    </div>
    <div class="gforms-question-footer">
      <div class="gforms-question-actions">
        <button onclick="duplicateExamQuestion('exam-question-${examQuestionCount}')" title="Duplicate">
          <i class="fas fa-copy"></i>
        </button>
        <button onclick="deleteExamQuestion('exam-question-${examQuestionCount}')" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
        <div class="gforms-required-toggle">
          <input type="checkbox" id="required-exam-${examQuestionCount}">
          <label for="required-exam-${examQuestionCount}">Required</label>
        </div>
      </div>
    </div>
  `;
  
  questionBuilder.appendChild(questionCard);
  
  // Focus on the question title
  const titleInput = questionCard.querySelector('.gforms-question-title');
  titleInput.focus();
  titleInput.select();
}

function addExamOption(questionId) {
  const questionCard = document.getElementById(questionId);
  const questionBody = questionCard.querySelector('.gforms-question-body');
  const addOptionDiv = questionCard.querySelector('.gforms-add-option');
  
  const optionDiv = document.createElement('div');
  optionDiv.className = 'gforms-option';
  
  // Get the current option count for this question
  const existingOptions = questionCard.querySelectorAll('.gforms-option:not(.gforms-add-option)');
  const optionIndex = existingOptions.length;
  
  optionDiv.innerHTML = `
    <input type="radio" name="${questionId}" disabled>
    <input type="text" placeholder="Option">
    <div class="gforms-correct-answer">
      <input type="checkbox" id="correct-${questionId}-${optionIndex}" class="correct-answer-checkbox" data-option="${optionIndex}">
      <label for="correct-${questionId}-${optionIndex}">Correct Answer</label>
    </div>
  `;
  
  questionBody.insertBefore(optionDiv, addOptionDiv);
  
  // Focus on the new option input
  const optionInput = optionDiv.querySelector('input[type="text"]');
  optionInput.focus();
}


function saveExam() {
  const title = document.getElementById('examTitle').value;
  const description = document.getElementById('examDescription').value;
  
  if (!title.trim()) {
    alert('Please enter an exam title.');
    return;
  }
  
  // Collect all questions
  const questions = [];
  const questionCards = document.querySelectorAll('#examQuestionBuilder .gforms-question-card');
  
  if (questionCards.length === 0) {
    alert('Please add at least one question to the exam.');
    return;
  }
  
  questionCards.forEach((card, index) => {
    const questionTitle = card.querySelector('.gforms-question-title').value;
    const questionType = card.querySelector('.gforms-question-type span').textContent.toLowerCase();
    const required = card.querySelector('input[type="checkbox"]').checked;
    
    let questionData = {
      title: questionTitle,
      type: questionType,
      required: required,
      order: index + 1
    };
    
    if (questionType === 'multiple choice') {
      const options = [];
      const optionInputs = card.querySelectorAll('.gforms-question-body input[type="text"]');
      const correctAnswerCheckboxes = card.querySelectorAll('.correct-answer-checkbox');
      
      optionInputs.forEach((input, index) => {
        if (input.value.trim()) {
          const isCorrect = correctAnswerCheckboxes[index] && correctAnswerCheckboxes[index].checked;
          // For now, send as simple text and store correct answer index separately
          options.push(input.value.trim());
        }
      });
      
      // Find which option is marked as correct
      let correctAnswerIndex = -1;
      correctAnswerCheckboxes.forEach((checkbox, index) => {
        if (checkbox.checked) {
          correctAnswerIndex = index;
        }
      });
      
      questionData.correctAnswerIndex = correctAnswerIndex;
      
      questionData.options = options;
    } else if (questionType === 'paragraph') {
      questionData.isLongAnswer = true;
    }
    
    questions.push(questionData);
  });
  
  const examData = {
    title: title,
    description: description,
    questions: questions,
    created_at: new Date().toISOString()
  };
  
  console.log('Saving exam:', examData);
  
  // Save to database
  saveExamToDatabase(examData);
}

function saveExamToDatabase(examData) {
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'create_exam',
      exam: examData
    })
  })
  .then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      return response.text().then(text => {
        console.error('Non-JSON response:', text);
        throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
      });
    }
    
    return response.json();
  })
  .then(data => {
    console.log('API Response:', data);
    if (data.success) {
      // Show exam save confirmation popup
      showExamSaveConfirmation(examData.title, examData.course_name);
      closeExamModal();
      
      // Reload exams list after successful save
      const selectedCourseName = document.getElementById('selectedExamCourseName');
      if (selectedCourseName) {
        loadExamsForCourse(selectedCourseName.textContent);
      }
    } else {
      alert('Error saving exam: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Detailed error:', error);
    alert('Error saving exam: ' + error.message + '\n\nCheck browser console for details.');
  });
}

function previewExam() {
  alert('Preview functionality will be implemented here');
}

// ========== TOOLBAR FUNCTIONS ==========

function importQuestions() {
  alert('Import questions functionality will be implemented here');
}

function addTitleDescription() {
  alert('Add title and description functionality will be implemented here');
}

function addImage() {
  alert('Add image functionality will be implemented here');
}

function addVideo() {
  alert('Add video functionality will be implemented here');
}

function addSection() {
  alert('Add section functionality will be implemented here');
}

function loadQuizzesCourses() {
  console.log('Loading quizzes courses...');
  const tableBody = document.getElementById('quizzesCoursesTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="3" class="text-center">
          <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-primary me-2" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            Loading courses...
          </div>
        </td>
      </tr>
    `;
  }
  
  // Fetch courses via AJAX (same API as attendance)
  fetch('apis/course_students.php?action=courses')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayQuizzesCourses(data.courses);
      } else {
        console.error('Error loading courses:', data.message);
        showQuizzesCoursesError(data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      showQuizzesCoursesError('Network error occurred');
    });
}

// Function to display quizzes courses
function displayQuizzesCourses(courses) {
  const tableBody = document.getElementById('quizzesCoursesTableBody');
  if (tableBody) {
    if (courses.length > 0) {
      let html = '';
      courses.forEach(course => {
        const courseName = course.course;
        const studentCount = course.student_count;
        
        html += `
          <tr class="clickable-row course-row" onclick="viewQuizzesForCourse('${courseName}')">
            <td>
              <div class="course-name-content">
                <div class="fw-semibold text-primary">${courseName}</div>
                <small class="text-muted">Click to manage quizzes for this course</small>
              </div>
            </td>
            <td class="text-center">
              <span class="badge bg-info">
                <i class="fas fa-users me-1"></i>
                ${studentCount} student${studentCount !== 1 ? 's' : ''}
              </span>
            </td>
            <td class="text-center">
              <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); viewQuizzesForCourse('${courseName}')">
                <i class="fas fa-question-circle me-1"></i>Manage Quizzes
              </button>
            </td>
          </tr>
        `;
      });
      tableBody.innerHTML = html;
    } else {
      tableBody.innerHTML = `
        <tr>
          <td colspan="3" class="text-center text-muted">No courses found.</td>
        </tr>
      `;
    }
  }
}

// Function to show quizzes courses error
function showQuizzesCoursesError(message) {
  const tableBody = document.getElementById('quizzesCoursesTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="3" class="text-center text-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          ${message}
        </td>
      </tr>
    `;
  }
}

// Function to view quizzes for a specific course
function viewQuizzesForCourse(courseName) {
  console.log(`Viewing quizzes for course: ${courseName}`);
  
  // Hide course selector and show quizzes content
  const quizzesCourseSelector = document.querySelector('.quizzes-course-selector');
  const quizzesContent = document.getElementById('quizzesContent');
  
  if (quizzesCourseSelector && quizzesContent) {
    quizzesCourseSelector.style.display = 'none';
    quizzesContent.style.display = 'block';
    
    // Update the course name in the header
    const selectedCourseName = document.getElementById('selectedQuizzesCourseName');
    if (selectedCourseName) {
      selectedCourseName.textContent = courseName;
    }
    
    // TODO: Load quizzes for this specific course
    loadQuizzesForCourse(courseName);
  }
}

// Function to load quizzes for a specific course
function loadQuizzesForCourse(courseName) {
  console.log(`Loading quizzes for course: ${courseName}`);
  loadQuizzes();
}

// Function to load all quizzes
function loadQuizzes() {
  console.log('Loading quizzes...');
  const tableBody = document.getElementById('quizzesListTableBody');
  
  if (tableBody) {
    // Show loading state
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center">
          <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-primary me-2" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            Loading quizzes...
          </div>
        </td>
      </tr>
    `;
  }
  
  // Fetch quizzes from API
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_quizzes'
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('Quizzes API Response:', data);
    if (data.success) {
      displayQuizzes(data.quizzes);
    } else {
      console.error('Error loading quizzes:', data.message);
      showQuizError('Error loading quizzes: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error fetching quizzes:', error);
    showQuizError('Error loading quizzes: ' + error.message);
  });
}

// Function to display quizzes in the table
function displayQuizzes(quizzes) {
  const tableBody = document.getElementById('quizzesListTableBody');
  
  if (!tableBody) {
    console.error('Quiz table body not found');
    return;
  }
  
  if (!quizzes || quizzes.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center text-muted">No quizzes created yet</td>
      </tr>
    `;
    return;
  }
  
  // Clear existing content
  tableBody.innerHTML = '';
  
  // Add each quiz as a table row
  quizzes.forEach(quiz => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>
        <div class="d-flex align-items-center">
          <i class="fas fa-question-circle text-primary me-2"></i>
          <div>
            <div class="fw-bold">${escapeHtml(quiz.title)}</div>
            <small class="text-muted">${quiz.question_count || 0} questions</small>
          </div>
        </div>
      </td>
      <td>
        <div class="text-muted">
          <i class="fas fa-calendar-alt me-1"></i>
          ${formatDate(quiz.created_at)}
        </div>
      </td>
      <td>
        <span class="badge ${getStatusBadgeClass(quiz.status)}">${quiz.status || 'draft'}</span>
      </td>
      <td>
        <div class="btn-group" role="group">
          <button class="btn btn-sm btn-outline-secondary" onclick="editQuiz(${quiz.id})" title="Edit">
            <i class="fas fa-edit"></i>
          </button>
          ${quiz.status === 'draft' ? 
            `<button class="btn btn-sm btn-outline-success" onclick="publishQuiz(${quiz.id})" title="Publish">
              <i class="fas fa-paper-plane"></i>
            </button>` : 
            `<button class="btn btn-sm btn-outline-warning" onclick="unpublishQuiz(${quiz.id})" title="Unpublish">
              <i class="fas fa-eye-slash"></i>
            </button>`
          }
          <button class="btn btn-sm btn-outline-danger" onclick="deleteQuiz(${quiz.id})" title="Delete">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </td>
    `;
    tableBody.appendChild(row);
  });
}

// Function to show quiz error
function showQuizError(message) {
  const tableBody = document.getElementById('quizzesListTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center text-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          ${escapeHtml(message)}
        </td>
      </tr>
    `;
  }
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Helper function to format date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// Helper function to get status badge class
function getStatusBadgeClass(status) {
  switch (status) {
    case 'published':
      return 'bg-success';
    case 'draft':
      return 'bg-secondary';
    case 'archived':
      return 'bg-warning';
    default:
      return 'bg-secondary';
  }
}

// Function to update quiz in database
function updateQuizToDatabase(quizData, quizId) {
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_quiz',
      quiz_id: quizId,
      quiz: quizData
    })
  })
  .then(response => {
    console.log('Update response status:', response.status);
    console.log('Update response headers:', response.headers);
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      return response.text().then(text => {
        console.error('Non-JSON response:', text);
        throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
      });
    }
    
    return response.json();
  })
  .then(data => {
    console.log('Update API Response:', data);
    if (data.success) {
      // Show the beautiful confirmation popup
      showQuizUpdateConfirmation(quizData.title, quizData.questions.length, quizId);
      closeQuizModal();
      // Reload the quiz list to show the updated quiz
      loadQuizzes();
    } else {
      alert('Error updating quiz: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Detailed error:', error);
    alert('Error updating quiz: ' + error.message + '\n\nCheck browser console for details.');
  });
}

// Function to show quiz update confirmation popup
function showQuizUpdateConfirmation(quizTitle, questionCount, quizId) {
  const modal = document.getElementById('quizSaveConfirmationModal');
  const titleElement = document.getElementById('confirmationQuizTitle');
  const questionCountElement = document.getElementById('confirmationQuestionCount');
  
  if (modal && titleElement && questionCountElement) {
    // Update the popup content
    titleElement.textContent = quizTitle;
    questionCountElement.textContent = questionCount;
    
    // Update the title to indicate update
    const popupTitle = modal.querySelector('.quiz-save-confirmation-title');
    if (popupTitle) {
      popupTitle.textContent = 'Quiz Updated Successfully!';
    }
    
    const popupSubtitle = modal.querySelector('.quiz-save-confirmation-subtitle');
    if (popupSubtitle) {
      popupSubtitle.textContent = 'Your quiz has been updated and saved';
    }
    
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Auto-close after 5 seconds if user doesn't interact
    setTimeout(() => {
      if (modal.classList.contains('show')) {
        closeQuizSaveConfirmation();
      }
    }, 5000);
  }
}

// Function to show exam save confirmation popup
function showExamSaveConfirmation(examTitle, courseName) {
  const modal = document.getElementById('examSaveConfirmationModal');
  const titleElement = document.getElementById('confirmationExamTitle');
  const courseElement = document.getElementById('confirmationExamCourse');
  
  if (modal && titleElement && courseElement) {
    // Update the popup content
    titleElement.textContent = examTitle;
    courseElement.textContent = courseName;
    
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Auto-close after 5 seconds if user doesn't interact
    setTimeout(() => {
      if (modal.classList.contains('show')) {
        closeExamSaveConfirmation();
      }
    }, 5000);
  }
}

// Function to close the exam save confirmation popup
function closeExamSaveConfirmation() {
  const modal = document.getElementById('examSaveConfirmationModal');
  
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clean up after animation
    setTimeout(() => {
      modal.style.display = 'none';
    }, 300);
  }
}

// Function to view exam list from confirmation popup
function viewExamList() {
  closeExamSaveConfirmation();
  
  // Switch to exams tab and load exams
  const examsTab = document.querySelector('[data-tab="exams"]');
  if (examsTab) {
    examsTab.click();
  }
  
  // Load exams after a short delay to ensure tab is active
  setTimeout(() => {
    const selectedCourseName = document.getElementById('selectedExamCourseName');
    if (selectedCourseName) {
      loadExamsForCourse(selectedCourseName.textContent);
    }
  }, 100);
}

// Function to preview quiz
function previewQuizById(quizId) {
  console.log('Previewing quiz:', quizId);
  alert('Preview functionality will be implemented for quiz ID: ' + quizId);
}

// Function to edit quiz
function editQuiz(quizId) {
  console.log('Editing quiz:', quizId);
  
  // Set edit mode flag
  window.isEditMode = true;
  window.currentEditQuizId = quizId;
  
  // Show loading state in modal
  showQuizModal();
  showQuizLoadingState();
  
  // Fetch quiz data
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_quiz',
      quiz_id: quizId
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('Quiz data for editing:', data);
    if (data.success) {
      populateQuizForEditing(data.quiz);
    } else {
      alert('Error loading quiz: ' + data.message);
      closeQuizModal();
    }
  })
  .catch(error => {
    console.error('Error fetching quiz:', error);
    alert('Error loading quiz: ' + error.message);
    closeQuizModal();
  });
}

// Function to show quiz modal
function showQuizModal() {
  const modal = document.getElementById('quizCreationModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

// Function to show loading state in quiz modal
function showQuizLoadingState() {
  const questionBuilder = document.getElementById('quizQuestionBuilder');
  if (questionBuilder) {
    questionBuilder.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading quiz data...</p>
      </div>
    `;
  }
}

// Function to populate quiz data for editing
function populateQuizForEditing(quiz) {
  // Set quiz title and description
  document.getElementById('quizTitle').value = quiz.title || '';
  document.getElementById('quizDescription').value = quiz.description || '';
  
  // Clear existing questions
  const questionBuilder = document.getElementById('quizQuestionBuilder');
  if (questionBuilder) {
    questionBuilder.innerHTML = '';
  }
  
  // Reset question count
  quizQuestionCount = 0;
  
  // Add questions from the quiz data
  if (quiz.questions && quiz.questions.length > 0) {
    quiz.questions.forEach((question, index) => {
      addQuizQuestionForEdit(question, index + 1);
    });
  } else {
    // If no questions, add one empty question
    addQuizQuestion();
  }
  
  // Update modal title to indicate edit mode
  const modalTitle = document.querySelector('.gforms-title-section input');
  if (modalTitle) {
    modalTitle.placeholder = 'Edit Quiz';
  }
}

// Function to add a quiz question for editing
function addQuizQuestionForEdit(questionData, questionNumber) {
  const questionBuilder = document.getElementById('quizQuestionBuilder');
  if (!questionBuilder) return;
  
  quizQuestionCount++;
  const questionId = `quiz-question-${quizQuestionCount}`;
  
  const questionCard = document.createElement('div');
  questionCard.className = 'gforms-question-card';
  questionCard.id = questionId;
  
  // Determine question type
  const questionType = questionData.question_type === 'multiple_choice' ? 'multiple choice' : 'paragraph';
  
  questionCard.innerHTML = `
    <div class="gforms-question-header">
      <input type="text" class="gforms-question-title" placeholder="Untitled Question" value="${escapeHtml(questionData.question_text || '')}">
      <div class="gforms-question-type" onclick="showQuestionTypeMenu('${questionId}')">
        <i class="fas fa-list-ul"></i>
        <span>${questionType}</span>
        <i class="fas fa-chevron-down"></i>
      </div>
    </div>
    <div class="gforms-question-body">
      ${generateQuestionBodyForEdit(questionData, questionId)}
    </div>
    <div class="gforms-question-footer">
      <div class="gforms-question-actions">
        <button onclick="duplicateQuizQuestion('${questionId}')" title="Duplicate">
          <i class="fas fa-copy"></i>
        </button>
        <button onclick="deleteQuizQuestion('${questionId}')" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
        <div class="gforms-required-toggle">
          <input type="checkbox" id="required-${quizQuestionCount}" ${questionData.is_required ? 'checked' : ''}>
          <label for="required-${quizQuestionCount}">Required</label>
        </div>
      </div>
    </div>
  `;
  
  questionBuilder.appendChild(questionCard);
}

// Function to generate question body for editing
function generateQuestionBodyForEdit(questionData, questionId) {
  if (questionData.question_type === 'multiple_choice' && questionData.options) {
    let optionsHtml = '';
    questionData.options.forEach((option, index) => {
      optionsHtml += `
        <div class="gforms-option">
          <input type="radio" name="${questionId}" disabled="">
          <input type="text" placeholder="Option ${index + 1}" value="${escapeHtml(option.option_text || '')}">
        </div>
      `;
    });
    
    // Add "Add option" button
    optionsHtml += `
      <div class="gforms-add-option" onclick="addQuizOption('${questionId}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>
    `;
    
    return optionsHtml;
  } else {
    // Paragraph question
    return `
      <div class="gforms-paragraph">
        <textarea placeholder="Enter your answer here..." disabled></textarea>
      </div>
    `;
  }
}

// Function to delete quiz
function deleteQuiz(quizId) {
  // Store the quiz ID for the confirmation
  window.currentDeleteQuizId = quizId;
  
  // Show the beautiful delete confirmation popup
  showQuizDeleteConfirmation();
}

// Function to show the quiz delete confirmation popup
function showQuizDeleteConfirmation() {
  const modal = document.getElementById('quizDeleteConfirmationModal');
  
  if (modal) {
    // Reset modal display
    modal.style.display = 'flex';
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
  }
}

// Function to close the quiz delete confirmation popup
function closeQuizDeleteConfirmation() {
  const modal = document.getElementById('quizDeleteConfirmationModal');
  
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clean up after animation
    setTimeout(() => {
      modal.style.display = 'none';
      // Clear the stored quiz ID when closing
      window.currentDeleteQuizId = null;
    }, 300);
  }
}

// Function to confirm quiz deletion
function confirmDeleteQuiz() {
  const quizId = window.currentDeleteQuizId;
  
  if (!quizId) {
    console.error('No quiz ID found for deletion');
    return;
  }
  
  console.log('Deleting quiz:', quizId);
  
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'delete_quiz',
      quiz_id: quizId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Clear the stored quiz ID
      window.currentDeleteQuizId = null;
      // Close the delete confirmation popup
      closeQuizDeleteConfirmation();
      // Show the beautiful delete success popup
      showQuizDeleteSuccess();
      loadQuizzes(); // Reload the quiz list
    } else {
      alert('Error deleting quiz: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error deleting quiz:', error);
    // Clear the stored quiz ID on error
    window.currentDeleteQuizId = null;
    alert('Error deleting quiz: ' + error.message);
  });
}

// ========== EXAM DELETE CONFIRMATION POPUP ==========

// Function to show exam delete confirmation popup
function showExamDeleteConfirmation(examId) {
  const modal = document.getElementById('examDeleteConfirmationModal');
  
  if (modal) {
    // Store the exam ID for deletion
    window.currentDeleteExamId = examId;
    
    // Reset modal display
    modal.style.display = 'flex';
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
  }
}

// Function to close the exam delete confirmation popup
function closeExamDeleteConfirmation() {
  const modal = document.getElementById('examDeleteConfirmationModal');
  
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clean up after animation
    setTimeout(() => {
      modal.style.display = 'none';
      // Clear the stored exam ID when closing
      window.currentDeleteExamId = null;
    }, 300);
  }
}

// Function to confirm exam deletion
function confirmDeleteExam() {
  const examId = window.currentDeleteExamId;
  
  if (!examId) {
    console.error('No exam ID found for deletion');
    return;
  }
  
  console.log(`Deleting exam with ID: ${examId}`);
  
  // Make API call to delete the exam
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'delete_exam',
      exam_id: examId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Exam deleted successfully');
      closeExamDeleteConfirmation();
      
      // Reload exams list after successful deletion
      const selectedCourseName = document.getElementById('selectedExamCourseName');
      if (selectedCourseName) {
        loadExamsForCourse(selectedCourseName.textContent);
      }
    } else {
      console.error('Error deleting exam:', data.message);
      alert('Error deleting exam: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error deleting exam:', error);
    alert('Error deleting exam: ' + error.message);
  });
}

// ========== QUIZ DELETE SUCCESS CONFIRMATION POPUP ==========

// Function to show the quiz delete success popup
function showQuizDeleteSuccess() {
  const modal = document.getElementById('quizDeleteSuccessModal');
  
  if (modal) {
    // Reset modal display
    modal.style.display = 'flex';
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Auto-close after 4 seconds if user doesn't interact
    setTimeout(() => {
      if (modal.classList.contains('show')) {
        closeQuizDeleteSuccess();
      }
    }, 4000);
  }
}

// Function to close the quiz delete success popup
function closeQuizDeleteSuccess() {
  const modal = document.getElementById('quizDeleteSuccessModal');
  
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clean up after animation
    setTimeout(() => {
      modal.style.display = 'none';
    }, 300);
  }
}

// Function to view quiz list from delete success popup
function viewQuizListFromDelete() {
  closeQuizDeleteSuccess();
  
  // Switch to quizzes tab and load quizzes
  const quizzesTab = document.querySelector('[data-tab="quizzes"]');
  if (quizzesTab) {
    quizzesTab.click();
  }
  
  // Load quizzes after a short delay to ensure tab is active
  setTimeout(() => {
    loadQuizzes();
  }, 200);
}

// ========== QUIZ SAVE CONFIRMATION POPUP ==========

// Function to show the quiz save confirmation popup
function showQuizSaveConfirmation(quizTitle, questionCount, quizId) {
  const modal = document.getElementById('quizSaveConfirmationModal');
  const titleElement = document.getElementById('confirmationQuizTitle');
  const questionCountElement = document.getElementById('confirmationQuestionCount');
  
  if (modal && titleElement && questionCountElement) {
    // Update the popup content
    titleElement.textContent = quizTitle;
    questionCountElement.textContent = questionCount;
    
    // Show the modal with animation
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Auto-close after 5 seconds if user doesn't interact
    setTimeout(() => {
      if (modal.classList.contains('show')) {
        closeQuizSaveConfirmation();
      }
    }, 5000);
  }
}

// Function to close the quiz save confirmation popup
function closeQuizSaveConfirmation() {
  const modal = document.getElementById('quizSaveConfirmationModal');
  
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Clean up after animation
    setTimeout(() => {
      modal.style.display = 'none';
    }, 300);
  }
}

// Function to view quiz list (called from confirmation popup)
function viewQuizList() {
  closeQuizSaveConfirmation();
  
  // Switch to quizzes tab and load quizzes
  const quizzesTab = document.querySelector('[data-tab="quizzes"]');
  if (quizzesTab) {
    quizzesTab.click();
  }
  
  // Load quizzes after a short delay to ensure tab is active
  setTimeout(() => {
    loadQuizzes();
  }, 200);
}

// ========== EXAM TAB FUNCTIONALITY ==========

function backToExamCourses() {
  const examContent = document.getElementById('examContent');
  const examCourseSelector = document.querySelector('.exam-course-selector');
  
  if (examContent && examCourseSelector) {
    examContent.style.display = 'none';
    examCourseSelector.style.display = 'block';
  }
}

function createNewExam() {
  console.log('Opening exam creation modal...');
  const modal = document.getElementById('examCreationModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    // Initialize with one question
    addExamQuestion();
  }
}

function closeExamModal() {
  const modal = document.getElementById('examCreationModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    
    // Reset edit mode
    window.currentEditExamId = null;
    modal.classList.remove('exam-edit-mode');
    
    // Reset form
    document.getElementById('examTitle').value = 'Untitled Exam';
    document.getElementById('examDescription').value = '';
    
    // Clear all questions
    const questionBuilder = document.getElementById('examQuestionBuilder');
    if (questionBuilder) {
      questionBuilder.innerHTML = '';
    }
    
    // Reset question count
    examQuestionCount = 0;
    
    // Reset save button
    const saveButton = document.querySelector('#examCreationModal .gforms-btn-primary');
    if (saveButton) {
      saveButton.textContent = 'Save Exam';
      saveButton.onclick = saveExam;
    }
    
    // Reset modal title
    const modalTitle = document.getElementById('examTitle');
    if (modalTitle) {
      modalTitle.value = 'Untitled Exam';
    }
  }
}

function selectExamFormat(format) {
  // Remove active class from all format options in exam modal
  const examModal = document.getElementById('examCreationModal');
  examModal.querySelectorAll('.format-option').forEach(option => {
    option.classList.remove('active');
  });
  
  // Add active class to selected format
  const selectedOption = examModal.querySelector(`input[value="${format}"]`).closest('.format-option');
  if (selectedOption) {
    selectedOption.classList.add('active');
    selectedOption.querySelector('input[type="radio"]').checked = true;
  }
}

function createExam() {
  const form = document.getElementById('examCreationForm');
  const formData = new FormData(form);
  
  const examData = {
    title: formData.get('examTitle'),
    format: formData.get('examFormat'),
    description: formData.get('examDescription')
  };
  
  // Validate required fields
  if (!examData.title.trim()) {
    alert('Please enter an exam title.');
    return;
  }
  
  console.log('Creating exam with data:', examData);
  
  // TODO: Implement actual exam creation API call
  // For now, show success message
  alert(`Exam "${examData.title}" created successfully!\nFormat: ${examData.format}\nDescription: ${examData.description || 'None'}`);
  
  // Close modal and reset form
  closeExamModal();
}

function loadExamCourses() {
  console.log('Loading exam courses...');
  const tableBody = document.getElementById('examCoursesTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="3" class="text-center">
          <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-primary me-2" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            Loading courses...
          </div>
        </td>
      </tr>
    `;
  }
  
  // Fetch courses via AJAX (same API as attendance)
  fetch('apis/course_students.php?action=courses')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayExamCourses(data.courses);
      } else {
        console.error('Error loading courses:', data.message);
        showExamCoursesError(data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      showExamCoursesError('Network error occurred');
    });
}

// Function to display exam courses
function displayExamCourses(courses) {
  const tableBody = document.getElementById('examCoursesTableBody');
  if (tableBody) {
    if (courses.length > 0) {
      let html = '';
      courses.forEach(course => {
        const courseName = course.course;
        const studentCount = course.student_count;
        
        html += `
          <tr class="clickable-row course-row" onclick="viewExamsForCourse('${courseName}')">
            <td>
              <div class="course-name-content">
                <div class="fw-semibold text-primary">${courseName}</div>
                <small class="text-muted">Click to manage exams for this course</small>
              </div>
            </td>
            <td class="text-center">
              <span class="badge bg-info">
                <i class="fas fa-users me-1"></i>
                ${studentCount} student${studentCount !== 1 ? 's' : ''}
              </span>
            </td>
            <td class="text-center">
              <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); viewExamsForCourse('${courseName}')">
                <i class="fas fa-clipboard-check me-1"></i>Manage Exams
              </button>
            </td>
          </tr>
        `;
      });
      tableBody.innerHTML = html;
    } else {
      tableBody.innerHTML = `
        <tr>
          <td colspan="3" class="text-center text-muted">No courses found.</td>
        </tr>
      `;
    }
  }
}

// Function to show exam courses error
function showExamCoursesError(message) {
  const tableBody = document.getElementById('examCoursesTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="3" class="text-center text-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          ${message}
        </td>
      </tr>
    `;
  }
}

// Function to view exams for a specific course
function viewExamsForCourse(courseName) {
  console.log(`Viewing exams for course: ${courseName}`);
  
  // Hide course selector and show exam content
  const examCourseSelector = document.querySelector('.exam-course-selector');
  const examContent = document.getElementById('examContent');
  
  if (examCourseSelector && examContent) {
    examCourseSelector.style.display = 'none';
    examContent.style.display = 'block';
    
    // Update the course name in the header
    const selectedCourseName = document.getElementById('selectedExamCourseName');
    if (selectedCourseName) {
      selectedCourseName.textContent = courseName;
    }
    
    // TODO: Load exams for this specific course
    loadExamsForCourse(courseName);
  }
}

// Function to load all exams
function loadExams() {
  console.log('Loading exams...');
  const tableBody = document.getElementById('examListTableBody');
  
  if (!tableBody) {
    console.error('Exam list table body not found');
    return;
  }
  
  // Show loading state
  tableBody.innerHTML = `
    <tr>
      <td colspan="4" class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        Loading exams...
      </td>
    </tr>
  `;
  
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_exams'
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('Exams loaded:', data);
    if (data.success && data.exams) {
      displayExams(data.exams);
    } else {
      displayNoExams();
    }
  })
  .catch(error => {
    console.error('Error loading exams:', error);
    displayExamError('Failed to load exams');
  });
}

// Function to load exams for a specific course
function loadExamsForCourse(courseName) {
  console.log(`Loading exams for course: ${courseName}`);
  
  // Show loading state
  const tableBody = document.getElementById('examListTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center">
          <div class="spinner-border spinner-border-sm me-2" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          Loading exams...
        </td>
      </tr>
    `;
  }
  
  // Fetch exams from the API
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_exams'
    })
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    console.log('Exams loaded:', data);
    if (data.success && data.exams) {
      displayExams(data.exams);
    } else {
      displayNoExams();
    }
  })
  .catch(error => {
    console.error('Error loading exams:', error);
    displayError('Failed to load exams. Please try again.');
  });
}

// Function to display exams in the table
function displayExams(exams) {
  const tableBody = document.getElementById('examListTableBody');
  if (!tableBody) return;
  
  if (exams.length === 0) {
    displayNoExams();
    return;
  }
  
  let html = '';
  exams.forEach(exam => {
    const createdDate = new Date(exam.created_at).toLocaleDateString();
    const statusBadge = getStatusBadge(exam.status);
    
    html += `
      <tr>
        <td>
          <div class="exam-title">
            <strong>${exam.title}</strong>
            ${exam.description ? `<small class="text-muted d-block">${exam.description}</small>` : ''}
          </div>
        </td>
        <td>${createdDate}</td>
        <td>${statusBadge}</td>
        <td>
          <div class="btn-group btn-group-sm" role="group">
            ${exam.status === 'draft' ? 
              `<button class="btn btn-outline-success btn-sm" onclick="publishExam(${exam.id})" title="Publish">
                <i class="fas fa-paper-plane"></i>
              </button>` : 
              `<button class="btn btn-outline-warning btn-sm" onclick="unpublishExam(${exam.id})" title="Unpublish">
                <i class="fas fa-eye-slash"></i>
              </button>`
            }
            <button class="btn btn-outline-secondary btn-sm" onclick="editExam(${exam.id})" title="Edit Exam">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="deleteExam(${exam.id})" title="Delete Exam">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  });
  
  tableBody.innerHTML = html;
}

// Function to display no exams message
function displayNoExams() {
  const tableBody = document.getElementById('examListTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center text-muted">
          <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
          No exams created yet
        </td>
      </tr>
    `;
  }
}

// Function to display exam error message
function displayExamError(message) {
  const tableBody = document.getElementById('examListTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center text-danger">
          <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
          ${message}
        </td>
      </tr>
    `;
  }
}

// Function to publish an exam
function publishExam(examId) {
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'publish_exam',
      exam_id: examId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Exam published successfully!');
      loadExams(); // Refresh the exam list
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error publishing exam');
  });
}

// Function to unpublish an exam
function unpublishExam(examId) {
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'unpublish_exam',
      exam_id: examId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Exam unpublished successfully!');
      loadExams(); // Refresh the exam list
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error unpublishing exam');
  });
}

// Function to display error message
function displayError(message) {
  const tableBody = document.getElementById('examListTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center text-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          ${message}
        </td>
      </tr>
    `;
  }
}

// Function to get status badge HTML
function getStatusBadge(status) {
  const statusMap = {
    'draft': '<span class="badge bg-warning">Draft</span>',
    'published': '<span class="badge bg-success">Published</span>',
    'archived': '<span class="badge bg-secondary">Archived</span>'
  };
  return statusMap[status] || '<span class="badge bg-light text-dark">Unknown</span>';
}

// Function to edit exam
function editExam(examId) {
  console.log(`Editing exam: ${examId}`);
  
  // Show loading state
  const examModal = document.getElementById('examCreationModal');
  if (examModal) {
    examModal.style.display = 'flex';
    
    // Show loading in modal
    const questionBuilder = document.getElementById('examQuestionBuilder');
    if (questionBuilder) {
      const originalContent = questionBuilder.innerHTML;
      questionBuilder.innerHTML = `
        <div class="text-center p-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Loading exam data...</p>
        </div>
      `;
      
      // Fetch exam data
      fetch('apis/exam_handler.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'get_exam',
          exam_id: examId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Restore original content
          questionBuilder.innerHTML = originalContent;
          
          // Populate the form with exam data
          populateExamForm(data.exam, data.exam.questions);
          
          // Set edit mode
          window.currentEditExamId = examId;
          updateExamModalForEdit();
        } else {
          console.error('Error loading exam:', data.message);
          questionBuilder.innerHTML = `
            <div class="text-center p-4">
              <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading exam: ${data.message}
              </div>
              <button class="btn btn-secondary" onclick="closeExamModal()">Close</button>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error loading exam:', error);
        questionBuilder.innerHTML = `
          <div class="text-center p-4">
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle me-2"></i>
              Error loading exam: ${error.message}
            </div>
            <button class="btn btn-secondary" onclick="closeExamModal()">Close</button>
          </div>
        `;
      });
    }
  }
}

// Function to populate exam form with existing data
function populateExamForm(exam, questions) {
  // Populate exam title and description
  const titleInput = document.getElementById('examTitle');
  const descriptionInput = document.getElementById('examDescription');
  
  if (titleInput) titleInput.value = exam.title || '';
  if (descriptionInput) descriptionInput.value = exam.description || '';
  
  // Clear existing questions
  const questionBuilder = document.getElementById('examQuestionBuilder');
  if (questionBuilder) {
    questionBuilder.innerHTML = '';
  }
  
  // Add questions to the form
  if (questions && questions.length > 0) {
    questions.forEach((question, index) => {
      addQuestionToForm(question, index);
    });
  }
}

// Function to add a question to the form (for editing)
function addQuestionToForm(question, index) {
  const questionBuilder = document.getElementById('examQuestionBuilder');
  if (!questionBuilder) return;
  
  const questionId = `exam-question-${index + 1}`;
  const questionType = question.question_type || 'multiple_choice';
  const isRequired = question.is_required || false;
  
  let questionHtml = `
    <div class="gforms-question-card" id="${questionId}">
      <div class="gforms-question-header">
        <input type="text" class="gforms-question-title" placeholder="Untitled Question" value="${question.question_text || ''}">
        <div class="gforms-question-type" onclick="showExamQuestionTypeMenu('${questionId}')">
          <i class="fas fa-list-ul"></i>
          <span>${questionType.replace('_', ' ')}</span>
          <i class="fas fa-chevron-down"></i>
        </div>
      </div>
      <div class="gforms-question-body">`;
  
  if (questionType === 'multiple_choice') {
    if (question.options && question.options.length > 0) {
      question.options.forEach((option, optionIndex) => {
        questionHtml += `
          <div class="gforms-option">
            <input type="radio" name="${questionId}" disabled>
            <input type="text" placeholder="Option ${optionIndex + 1}" value="${option.option_text || ''}">
          </div>`;
      });
    } else {
      questionHtml += `
        <div class="gforms-option">
          <input type="radio" name="${questionId}" disabled>
          <input type="text" placeholder="Option 1" value="Option 1">
        </div>
        <div class="gforms-option">
          <input type="radio" name="${questionId}" disabled>
          <input type="text" placeholder="Option 2" value="Option 2">
        </div>`;
    }
    
    questionHtml += `
      <div class="gforms-add-option" onclick="addExamOption('${questionId}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>`;
  } else if (questionType === 'paragraph') {
    questionHtml += `
      <div class="gforms-paragraph">
        <p class="text-muted">This is a paragraph question. Students will provide a text response.</p>
      </div>`;
  }
  
  questionHtml += `
      </div>
      <div class="gforms-question-footer">
        <div class="gforms-question-actions">
          <button onclick="duplicateExamQuestion('${questionId}')" title="Duplicate">
            <i class="fas fa-copy"></i>
          </button>
          <button onclick="deleteExamQuestion('${questionId}')" title="Delete">
            <i class="fas fa-trash"></i>
          </button>
          <div class="gforms-required-toggle">
            <input type="checkbox" id="required-${index + 1}" ${isRequired ? 'checked' : ''}>
            <label for="required-${index + 1}">Required</label>
          </div>
        </div>
      </div>
    </div>`;
  
  questionBuilder.insertAdjacentHTML('beforeend', questionHtml);
}

// Function to show exam question type menu
function showExamQuestionTypeMenu(questionId) {
  // Create dropdown menu for question types
  const questionCard = document.getElementById(questionId);
  const questionTypeDiv = questionCard.querySelector('.gforms-question-type');
  
  // Remove existing dropdown if any
  const existingDropdown = document.querySelector('.gforms-question-type-dropdown');
  if (existingDropdown) {
    existingDropdown.remove();
  }
  
  // Create dropdown menu
  const dropdown = document.createElement('div');
  dropdown.className = 'gforms-question-type-dropdown';
  dropdown.innerHTML = `
    <div class="gforms-dropdown-option" onclick="changeExamQuestionType('${questionId}', 'multiple-choice')">
      <i class="fas fa-list-ul"></i>
      <span>Multiple choice</span>
    </div>
    <div class="gforms-dropdown-option" onclick="changeExamQuestionType('${questionId}', 'paragraph')">
      <i class="fas fa-align-left"></i>
      <span>Paragraph</span>
    </div>
  `;
  
  // Position dropdown
  questionTypeDiv.style.position = 'relative';
  questionTypeDiv.appendChild(dropdown);
  
  // Close dropdown when clicking outside
  setTimeout(() => {
    document.addEventListener('click', function closeDropdown(e) {
      if (!questionTypeDiv.contains(e.target)) {
        dropdown.remove();
        document.removeEventListener('click', closeDropdown);
      }
    });
  }, 0);
}

// Function to change exam question type
function changeExamQuestionType(questionId, type) {
  const questionCard = document.getElementById(questionId);
  const questionBody = questionCard.querySelector('.gforms-question-body');
  const questionTypeDiv = questionCard.querySelector('.gforms-question-type');
  
  // Update the question type display
  const typeSpan = questionTypeDiv.querySelector('span');
  const typeIcon = questionTypeDiv.querySelector('i');
  
  if (type === 'multiple-choice') {
    typeIcon.className = 'fas fa-list-ul';
    typeSpan.textContent = 'Multiple choice';
    
    // Update question body for multiple choice
    questionBody.innerHTML = `
      <div class="gforms-option">
        <input type="radio" name="${questionId}" disabled>
        <input type="text" placeholder="Option 1" value="Option 1">
      </div>
      <div class="gforms-option">
        <input type="radio" name="${questionId}" disabled>
        <input type="text" placeholder="Option 2" value="Option 2">
      </div>
      <div class="gforms-add-option" onclick="addExamOption('${questionId}')">
        <i class="fas fa-plus"></i>
        <span>Add option</span>
      </div>
    `;
  } else if (type === 'paragraph') {
    typeIcon.className = 'fas fa-align-left';
    typeSpan.textContent = 'Paragraph';
    
    // Update question body for paragraph
    questionBody.innerHTML = `
      <div class="gforms-paragraph-answer">
        <textarea placeholder="Long answer text" class="gforms-long-answer" disabled></textarea>
      </div>
    `;
  }
  
  // Remove dropdown
  const dropdown = questionCard.querySelector('.gforms-question-type-dropdown');
  if (dropdown) {
    dropdown.remove();
  }
}

// Function to add exam option
function addExamOption(questionId) {
  const questionCard = document.getElementById(questionId);
  const questionBody = questionCard.querySelector('.gforms-question-body');
  const addOptionDiv = questionCard.querySelector('.gforms-add-option');
  
  const optionDiv = document.createElement('div');
  optionDiv.className = 'gforms-option';
  optionDiv.innerHTML = `
    <input type="radio" name="${questionId}" disabled>
    <input type="text" placeholder="Option">
  `;
  
  questionBody.insertBefore(optionDiv, addOptionDiv);
  
  // Focus on the new option input
  const optionInput = optionDiv.querySelector('input[type="text"]');
  optionInput.focus();
}

// Function to duplicate exam question
function duplicateExamQuestion(questionId) {
  const originalCard = document.getElementById(questionId);
  const clonedCard = originalCard.cloneNode(true);
  
  // Generate new question ID
  const newQuestionId = `exam-question-${Date.now()}`;
  clonedCard.id = newQuestionId;
  
  // Update radio button names
  const radioButtons = clonedCard.querySelectorAll('input[type="radio"]');
  radioButtons.forEach(radio => {
    radio.name = newQuestionId;
  });
  
  // Clear input values
  const textInputs = clonedCard.querySelectorAll('input[type="text"]');
  textInputs.forEach(input => {
    if (input.placeholder.includes('Option')) {
      input.value = input.placeholder;
    }
  });
  
  // Update onclick handlers
  const addOptionBtn = clonedCard.querySelector('.gforms-add-option');
  if (addOptionBtn) {
    addOptionBtn.setAttribute('onclick', `addExamOption('${newQuestionId}')`);
  }
  
  const duplicateBtn = clonedCard.querySelector('button[onclick*="duplicateExamQuestion"]');
  if (duplicateBtn) {
    duplicateBtn.setAttribute('onclick', `duplicateExamQuestion('${newQuestionId}')`);
  }
  
  const deleteBtn = clonedCard.querySelector('button[onclick*="deleteExamQuestion"]');
  if (deleteBtn) {
    deleteBtn.setAttribute('onclick', `deleteExamQuestion('${newQuestionId}')`);
  }
  
  const questionTypeBtn = clonedCard.querySelector('.gforms-question-type');
  if (questionTypeBtn) {
    questionTypeBtn.setAttribute('onclick', `showExamQuestionTypeMenu('${newQuestionId}')`);
  }
  
  originalCard.parentNode.insertBefore(clonedCard, originalCard.nextSibling);
}

// Function to update exam modal for edit mode
function updateExamModalForEdit() {
  const modal = document.getElementById('examCreationModal');
  if (modal) {
    modal.classList.add('exam-edit-mode');
  }
  
  const saveButton = document.querySelector('#examCreationModal .gforms-btn-primary');
  if (saveButton) {
    saveButton.textContent = 'Update Exam';
    saveButton.onclick = updateExam;
  }
  
  // Update modal title if needed
  const modalTitle = document.getElementById('examTitle');
  if (modalTitle) {
    modalTitle.placeholder = 'Edit Exam';
  }
  
  // Add editing class to question cards
  const questionCards = document.querySelectorAll('#examQuestionBuilder .gforms-question-card');
  questionCards.forEach(card => {
    card.classList.add('editing');
  });
}

// Function to update exam
function updateExam() {
  const title = document.getElementById('examTitle').value;
  const description = document.getElementById('examDescription').value;
  
  if (!title.trim()) {
    alert('Please enter an exam title.');
    return;
  }
  
  // Collect all questions
  const questions = [];
  const questionCards = document.querySelectorAll('#examQuestionBuilder .gforms-question-card');
  
  if (questionCards.length === 0) {
    alert('Please add at least one question to the exam.');
    return;
  }
  
  questionCards.forEach((card, index) => {
    const questionTitle = card.querySelector('.gforms-question-title').value;
    const questionType = card.querySelector('.gforms-question-type span').textContent.toLowerCase().replace(' ', '_');
    const required = card.querySelector('input[type="checkbox"]').checked;
    
    let questionData = {
      title: questionTitle,
      type: questionType,
      required: required,
      order: index + 1
    };
    
    if (questionType === 'multiple_choice') {
      const options = [];
      const optionInputs = card.querySelectorAll('.gforms-option input[type="text"]');
      
      optionInputs.forEach(input => {
        if (input.value.trim()) {
          options.push(input.value.trim());
        }
      });
      
      questionData.options = options;
    } else if (questionType === 'paragraph') {
      questionData.isLongAnswer = true;
    }
    
    questions.push(questionData);
  });
  
  const examData = {
    title: title,
    description: description,
    questions: questions
  };
  
  // Show loading state
  const saveButton = document.querySelector('#examCreationModal .gforms-btn-primary');
  const originalText = saveButton.textContent;
  saveButton.textContent = 'Updating...';
  saveButton.disabled = true;
  
  // Make API call to update exam
  fetch('apis/exam_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_exam',
      exam_id: window.currentEditExamId,
      exam: examData
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show success message
      alert(`Exam "${examData.title}" updated successfully!`);
      closeExamModal();
      
      // Reload exams list after successful update
      const selectedCourseName = document.getElementById('selectedExamCourseName');
      if (selectedCourseName) {
        loadExamsForCourse(selectedCourseName.textContent);
      }
    } else {
      alert('Error updating exam: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error updating exam:', error);
    alert('Error updating exam: ' + error.message);
  })
  .finally(() => {
    // Restore button state
    saveButton.textContent = originalText;
    saveButton.disabled = false;
  });
}

// Function to delete exam
function deleteExam(examId) {
  console.log(`Showing delete confirmation for exam: ${examId}`);
  
  // Show the exam delete confirmation popup
  showExamDeleteConfirmation(examId);
}

function showAttendanceDetails(studentId) {
  console.log(`Showing attendance details for student: ${studentId}`);
}

function markAttendance(studentId, status) {
  console.log(`Marking ${status} for student: ${studentId}`);

  // Find the button group for this student
  const row = event.target.closest("tr");
  const buttons = row.querySelectorAll(".btn-group .btn");

  // Remove active class from all buttons in this group
  buttons.forEach((btn) => btn.classList.remove("active"));

  // Add active class to the clicked button
  event.target.closest(".btn").classList.add("active");

  // Update attendance rate (this would typically connect to your backend)
  console.log(`Attendance updated for student ${studentId}: ${status}`);
}

// ========== OTHER UTILITY FUNCTIONS ==========

function handleEvaluateClick() {}

function exportStudentGrades() {}

// ========== SIDEBAR FUNCTIONALITY ==========

function initializeSidebar() {
  const sidebarToggleDesktop = document.getElementById("sidebarToggle");
  const sidebarToggleMobile = document.getElementById("mobileSidebarToggle");
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.getElementById("mainContent");

  const applySidebarState = () => {
    if (window.innerWidth <= 768) {
      if (globalState.sidebarMobileOpen) {
        sidebar?.classList.add("show");
        if (mainContent) mainContent.style.marginLeft = "0";
      } else {
        sidebar?.classList.remove("show");
        if (mainContent) mainContent.style.marginLeft = "0";
      }
      sidebar?.classList.remove("collapsed");
    } else {
      sidebar?.classList.remove("show");
      if (globalState.sidebarCollapsed) {
        sidebar?.classList.add("collapsed");
        if (mainContent) mainContent.style.marginLeft = "5rem";
      } else {
        sidebar?.classList.remove("collapsed");
        if (mainContent) mainContent.style.marginLeft = "16rem";
      }
    }
  };

  const toggleDesktopSidebar = () => {
    globalState.sidebarCollapsed = !globalState.sidebarCollapsed;
    applySidebarState();
  };

  const toggleMobileSidebar = () => {
    globalState.sidebarMobileOpen = !globalState.sidebarMobileOpen;
    applySidebarState();
  };

  sidebarToggleDesktop?.addEventListener("click", toggleDesktopSidebar);
  sidebarToggleMobile?.addEventListener("click", toggleMobileSidebar);

  const handleResize = () => {
    if (window.innerWidth <= 768) {
      globalState.sidebarMobileOpen = false;
      globalState.sidebarCollapsed = false;
    } else {
      globalState.sidebarCollapsed = false;
      globalState.sidebarMobileOpen = false;
    }
    applySidebarState();
  };

  window.addEventListener("resize", handleResize);
  handleResize();
}

// ========== NAVIGATION FUNCTIONALITY ==========

function initializeNavigation() {
  const navItems = document.querySelectorAll(".nav-item");

  navItems.forEach((item) => {
    item.addEventListener("click", function () {
      const section = this.dataset.section;
      if (section) {
        showSection(section);
        updateActiveNav(section);
        if (window.innerWidth <= 768) {
          const sidebar = document.getElementById("sidebar");
          sidebar?.classList.remove("show");
          globalState.sidebarMobileOpen = false;
        }
      }
    });
  });
}

function showSection(sectionName) {
  document.querySelectorAll(".page-section").forEach((section) => {
    section.classList.remove("active");
  });

  const activeSectionElement = document.getElementById(sectionName);
  if (activeSectionElement) {
    activeSectionElement.classList.add("active");
    updateMainHeaderTitle(sectionName);

    // Initialize trainee tabs when trainee section is shown
    if (sectionName === "trainee") {
      setTimeout(() => {
        initializeTraineeRecordTabs();
      }, 100);
    }

    // Add animation to elements
    const animatable = activeSectionElement.querySelectorAll(
      ".card, .job-card, .activity-item, .feature-card"
    );
    animatable.forEach((el) => {
      el.classList.remove("animate");
      void el.offsetWidth;
      el.classList.add("animate");
    });
  }

  globalState.activeSection = sectionName;
}

function updateMainHeaderTitle(sectionName) {
  const mainTitleElement = document.querySelector(".main-header .main-title");
  const activeNavItem = document.querySelector(
    `.nav-item[data-section="${sectionName}"]`
  );
  if (mainTitleElement && activeNavItem) {
    const navTextElement = activeNavItem.querySelector(".nav-text");
    if (navTextElement) {
      mainTitleElement.textContent = navTextElement.textContent;
    }
  }
}

function updateActiveNav(sectionName) {
  document.querySelectorAll(".nav-item").forEach((item) => {
    item.classList.remove("active");
  });

  const activeNavItem = document.querySelector(
    `[data-section="${sectionName}"]`
  );
  if (activeNavItem) {
    activeNavItem.classList.add("active");
  }
}

// ========== THEME FUNCTIONALITY ==========

function initializeTheme() {
  const themeToggle = document.getElementById("themeToggle");
  let isDarkMode = localStorage.getItem("theme") === "dark";

  if (isDarkMode) {
    document.body.setAttribute("data-theme", "dark");
    themeToggle?.querySelector("i")?.classList.remove("fa-moon");
    themeToggle?.querySelector("i")?.classList.add("fa-sun");
  } else {
    document.body.setAttribute("data-theme", "light");
    themeToggle?.querySelector("i")?.classList.remove("fa-sun");
    themeToggle?.querySelector("i")?.classList.add("fa-moon");
  }

  themeToggle?.addEventListener("click", function () {
    isDarkMode = !isDarkMode;

    const icon = this.querySelector("i");
    if (icon) {
      icon.classList.add("theme-icon-spin");

      setTimeout(() => {
        if (isDarkMode) {
          icon.className = "fas fa-sun theme-icon-spin";
          document.body.setAttribute("data-theme", "dark");
        } else {
          icon.className = "fas fa-moon theme-icon-spin";
          document.body.setAttribute("data-theme", "light");
        }
        localStorage.setItem("theme", isDarkMode ? "dark" : "light");
      }, 350);

      setTimeout(() => {
        icon.classList.remove("theme-icon-spin");
      }, 700);
    }

    console.log(`Theme switched to ${isDarkMode ? "dark" : "light"} mode`);
  });
}

// ========== LOGOUT FUNCTIONALITY ==========

function initializeLogout() {
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutModal = document.getElementById("logoutModal");
  const cancelLogout = document.getElementById("cancelLogout");

  if (logoutBtn && logoutModal && cancelLogout) {
    // Show modal
    logoutBtn.addEventListener("click", () => {
      logoutModal.classList.add("show");
    });

    // Hide modal
    cancelLogout.addEventListener("click", () => {
      logoutModal.classList.remove("show");
    });

    // Hide modal when clicking outside
    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        logoutModal.classList.remove("show");
      }
    });

    // Hide modal on escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && logoutModal.classList.contains("show")) {
        logoutModal.classList.remove("show");
      }
    });
  }
}

// ========== NOTIFICATION FUNCTIONALITY ==========

function initializeNotifications() {
  const notificationBell = document.getElementById("notificationBell");
  const notificationDropdown = document.getElementById("notificationDropdown");
  const notificationClose = document.getElementById("notificationClose");

  function showDropdown() {
    if (!notificationDropdown) return;
    notificationDropdown.classList.remove("hide");
    notificationDropdown.classList.add("show");
    notificationDropdown.style.display = "block";
    notificationBell?.classList.add("active");
  }

  function hideDropdown() {
    if (!notificationDropdown) return;
    globalState.notificationOpen = false;
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
    globalState.notificationOpen = !globalState.notificationOpen;
    if (globalState.notificationOpen) {
      showDropdown();
    } else {
      hideDropdown();
    }
  });

  notificationClose?.addEventListener("click", () => {
    globalState.notificationOpen = false;
    hideDropdown();
  });

  // Close notifications when clicking outside
  document.addEventListener("click", (event) => {
    if (!event.target.closest(".notification-container")) {
      if (globalState.notificationOpen) {
        globalState.notificationOpen = false;
        hideDropdown();
      }
    }
  });

  // Load notifications and refresh every 60s
  loadInstructorNotifications();
  setInterval(loadInstructorNotifications, 60000);
}

// Fetch notifications from admin API and populate dropdown
function loadInstructorNotifications() {
  const listEl = document.getElementById("notificationList");
  const countEl = document.getElementById("notificationCount");
  if (!listEl) return;

  function escapeHtml(str) {
    return String(str || "").replace(
      /[&<>"']/g,
      (s) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[s])
    );
  }

  fetch("apis/notifications_handler.php", {
    method: "GET",
    headers: { Accept: "application/json" },
  })
    .then((res) => res.json())
    .then((json) => {
      const notifications = json?.data || json?.notifications || [];
      listEl.innerHTML = "";
      if (!notifications.length) {
        listEl.innerHTML = '<div class="p-3 text-muted">No notifications</div>';
        if (countEl) {
          countEl.style.display = "none";
        }
        return;
      }

      notifications.slice(0, 10).forEach((n) => {
        const icon = escapeHtml(n.icon || "bell");
        const title = escapeHtml(n.title || "");
        const message = escapeHtml(n.message || "");
        const time = escapeHtml(n.time_display || n.custom_time || "");
        const item = document.createElement("div");
        item.className = "notification-item";
        item.innerHTML = `
            <div class="notification-icon"><i class="fas fa-${icon}"></i></div>
            <div class="notification-content">
              <div class="notification-title">${title}</div>
              <div class="notification-message">${message}</div>
              <div class="notification-time">${time}</div>
            </div>`;
        listEl.appendChild(item);
      });

      if (countEl) {
        countEl.textContent = String(notifications.length);
        countEl.style.display = notifications.length ? "" : "none";
      }
    })
    .catch((err) => {
      console.error("Failed to load notifications", err);
    });
}

// ========== HEADER FUNCTIONALITY ==========

function initializeHeader() {
  const userProfileDropdown = document.getElementById("userProfileDropdown");

  userProfileDropdown?.addEventListener("click", (e) => {
    if (e.target.closest(".dropdown-content")) {
      return;
    }
    globalState.userDropdownOpen = !globalState.userDropdownOpen;
    if (userProfileDropdown) {
      if (globalState.userDropdownOpen) {
        userProfileDropdown.classList.add("active");
      } else {
        userProfileDropdown.classList.remove("active");
      }
    }
    if (globalState.notificationOpen) {
      globalState.notificationOpen = false;
      const notificationDropdownElement = document.getElementById(
        "notificationDropdown"
      );
      if (notificationDropdownElement) {
        notificationDropdownElement.classList.remove("show");
        notificationDropdownElement.classList.add("hide");
        const notificationBell = document.getElementById("notificationBell");
        notificationBell?.classList.remove("active");
      }
    }
  });

  document.addEventListener("click", (e) => {
    if (
      globalState.userDropdownOpen &&
      userProfileDropdown &&
      !userProfileDropdown.contains(e.target) &&
      !e.target.closest(".user-profile-dropdown")
    ) {
      globalState.userDropdownOpen = false;
      userProfileDropdown.classList.remove("active");
    }
  });
}

// ========== TO-DO LIST INITIALIZATION ==========

function initializeTodoList() {
  const todoList = document.getElementById("todo-list");
  if (!todoList || todoList.children.length > 0) return;

  const tasks = [
    {
      title: "Math 101: Post new assignment",
      dueDate: "2025-06-25",
      priority: "High",
    },
    {
      title: "Physics 201: Prepare quiz",
      dueDate: "2025-06-27",
      priority: "Medium",
    },
    {
      title: "Chemistry 301: Upload slides",
      dueDate: "2025-07-01",
      priority: "Low",
    },
  ];

  const priorityToBadgeClass = {
    High: "bg-primary",
    Medium: "bg-warning",
    Low: "bg-secondary",
  };

  tasks.forEach((task) => {
    const due = new Date(task.dueDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);

    const timeDiff = due.getTime() - today.getTime();
    const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));

    let dueLabel = "";
    if (daysDiff === 0) {
      dueLabel = "Due today";
    } else if (daysDiff === 1) {
      dueLabel = "Due tomorrow";
    } else if (daysDiff < 7) {
      dueLabel = `Due in ${daysDiff} days`;
    } else {
      dueLabel = `Due on ${due.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
      })}`;
    }

    const priorityBadge = priorityToBadgeClass[task.priority] || "bg-light";

    const listItem = `
            <li class="list-group-item d-flex align-items-center">
                <input type="checkbox" class="form-check-input me-3 flex-shrink-0">
                <div>
                    <h6 class="mb-1">${task.title}</h6>
                    <small class="text-muted">${dueLabel}</small>
                </div>
                <div class="ms-auto">
                    <span class="badge ${priorityBadge}">${task.priority}</span>
                </div>
            </li>`;

    todoList.insertAdjacentHTML("beforeend", listItem);
  });
}

// ========== CREATE ACTIVITY MODAL ==========

function initializeCreateActivityModal() {}

// ========== GRADE EDIT MODAL FUNCTIONALITY ==========

// Sample student data with detailed grade information (merged from both files)
const studentGradeData = {
  2024001: {
    id: "2024001",
    lastName: "dela Cruz",
    firstName: "Juan",
    middleName: "Santos",
    course: "Electronics Technology",
    gradeBreakdown: {
      grade1: 85.5,
      grade2: 89.2,
      grade3: 92.1,
      grade4: 88.7,
      finalGrade: 89.5,
      remarks: "PASSED",
    },
  },
  2024002: {
    id: "2024002",
    lastName: "Santos",
    firstName: "Maria",
    middleName: "Garcia",
    course: "Automotive Technology",
    gradeBreakdown: {
      grade1: 78.3,
      grade2: 82.1,
      grade3: 85.4,
      grade4: 83.8,
      finalGrade: 82.3,
      remarks: "PASSED",
    },
  },
  2024003: {
    id: "2024003",
    lastName: "Gonzales",
    firstName: "Pedro",
    middleName: "Rivera",
    course: "Welding Technology",
    gradeBreakdown: {
      grade1: 72.5,
      grade2: 75.8,
      grade3: 78.2,
      grade4: 76.1,
      finalGrade: 75.8,
      remarks: "PASSED",
    },
  },
};

// Database connectivity functions from insjs.js
async function refreshAggregatesForStudent(studentId) {
  const fetchAvg = (g) =>
    fetch(
      `apis/grade_details.php?action=aggregate&student_number=${encodeURIComponent(
        studentId
      )}&grade_number=${g}`
    )
      .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
      .then((j) => (typeof j.average === "number" ? j.average : 0))
      .catch(() => 0);

  try {
    const [g1, g2, g3, g4] = await Promise.all([
      fetchAvg(1),
      fetchAvg(2),
      fetchAvg(3),
      fetchAvg(4),
    ]);
    const s = studentGradeData[studentId];
    if (!s) return;
    const breakdown = s.gradeBreakdown || {
      grade1: 0,
      grade2: 0,
      grade3: 0,
      grade4: 0,
      finalGrade: 0,
      remarks: "INCOMPLETE",
    };
    breakdown.grade1 = g1;
    breakdown.grade2 = g2;
    breakdown.grade3 = g3;
    breakdown.grade4 = g4;
    const finalAvg = (g1 + g2 + g3 + g4) / 4;
    breakdown.finalGrade = Number(finalAvg.toFixed(1));
    breakdown.remarks =
      breakdown.finalGrade >= 75
        ? "PASSED"
        : breakdown.finalGrade > 0
        ? "FAILED"
        : "INCOMPLETE";
    s.gradeBreakdown = breakdown;

    // If the Grade Management modal table is present, re-render it
    if (document.getElementById("gradeBreakdownTableBody")) {
      renderGradeBreakdownTable(s);
    }

    // Also reflect into the main Grades table row
    updateMainGradesTableFinal(studentId, breakdown.finalGrade);
  } catch (_) {
    // ignore
  }
}

async function refreshAllStudentsAggregates() {
  const ids = Object.keys(studentGradeData || {});
  await Promise.all(ids.map((id) => refreshAggregatesForStudent(id)));
  // As a safety, after all are refreshed, ensure table shows latest values
  ids.forEach((id) => {
    const s = studentGradeData[id];
    if (s && s.gradeBreakdown)
      updateMainGradesTableFinal(id, s.gradeBreakdown.finalGrade);
  });
}

function updateMainGradesTableFinal(studentId, finalVal) {
  try {
    const tbody = document.getElementById("gradesTableBody");
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const row = rows.find(
      (r) =>
        (r.querySelector("td span.fw-bold")?.textContent || "").trim() ===
        String(studentId)
    );
    if (!row) return;
    // The Final Grade cell is the 4th column (index 3)
    const cell = row.children[3];
    if (!cell) return;
    let span = cell.querySelector(".final-grade-cell");
    if (!span) {
      span = document.createElement("span");
      span.className = "final-grade-cell";
      cell.innerHTML = "";
      cell.appendChild(span);
    }
    const fixed = Number((finalVal || 0).toFixed(1));
    span.className = `final-grade-cell ${getFinalGradeColorClass(fixed)}`;
    span.innerHTML = `<strong>${fixed.toFixed(1)}%</strong>`;
  } catch (_) {}
}

// Ensure in-memory grade cache has entries for all students rendered in the table
function initializeStudentGradeDataFromTable() {
  try {
    const tbody = document.getElementById("gradesTableBody");
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll("tr"));
    rows.forEach((row) => {
      const idText = (row.querySelector("td span.fw-bold")?.textContent || "").trim();
      if (idText) {
        ensureStudentInGradeData(idText);
      }
    });
  } catch (_) {}
}

// Initialize grade modal functionality
function initializeGradeModal() {
  const modal = document.getElementById("gradeModal");
  const closeBtn = document.getElementById("gradeModalClose");

  if (!modal || !closeBtn) return;

  // Close modal handlers
  closeBtn.addEventListener("click", closeGradeModal);

  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      closeGradeModal();
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("show")) {
      closeGradeModal();
    }
  });

  // Initialize modal tabs
  initializeGradeModalTabs();

  console.log("Grade modal initialized");
}

// Initialize modal tab functionality
function initializeGradeModalTabs() {
  const tabButtons = document.querySelectorAll(".grade-modal-tab");
  const tabPanels = document.querySelectorAll(".grade-modal-tab-panel");

  tabButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const targetTab = this.getAttribute("data-tab");

      // Remove active class from all tabs and panels
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      tabPanels.forEach((panel) => panel.classList.remove("active"));

      // Add active class to clicked tab and corresponding panel
      this.classList.add("active");
      const targetPanel = document.querySelector(`[data-panel="${targetTab}"]`);
      if (targetPanel) {
        targetPanel.classList.add("active");
      }
    });
  });
}

// Enhanced editGrade function to show modal with proper database connectivity
function editGrade(studentId) {
  console.log(`Opening grade modal for student: ${studentId}`);

  // Ensure student exists in local cache; create from table row if missing
  const student = ensureStudentInGradeData(studentId);
  if (!student) {
    console.error("Student data not found and could not be initialized for ID:", studentId);
    return;
  }

  // Update modal title with student name
  updateGradeModalTitle(student);

  // Render the grade breakdown table with working click events
  renderGradeBreakdownTable(student);

  // Show the modal
  showGradeModal();
}

// Update modal title with student information
function updateGradeModalTitle(student) {
  const titleElement = document.getElementById("gradeModalTitle");
  if (titleElement) {
    titleElement.innerHTML = `
      <i class="fas fa-chart-line"></i>
      Grade Management - ${student.firstName} ${student.lastName} (${student.id})
    `;
  }
}

// Attempt to build a student entry from the Grades table if not already present
function ensureStudentInGradeData(studentId) {
  if (studentGradeData && studentGradeData[studentId]) {
    return studentGradeData[studentId];
  }

  const row = findGradesTableRowByStudentId(studentId);
  if (!row) {
    return null;
  }

  // Extract name and course from the row
  const idCell = row.querySelector('td:nth-child(1) .fw-bold');
  const nameEl = row.querySelector('td:nth-child(2) .student-name-content .fw-semibold');
  const courseElFromNameCell = row.querySelector('td:nth-child(2) .student-name-content small');
  const courseElFromThirdCell = row.querySelector('td:nth-child(3)');

  const idText = (idCell ? idCell.textContent : '').trim();
  const fullName = (nameEl ? nameEl.textContent : '').trim();
  let courseText = (courseElFromNameCell ? courseElFromNameCell.textContent : '').trim();
  if (!courseText) {
    courseText = (courseElFromThirdCell ? courseElFromThirdCell.textContent : '').trim();
  }

  const { firstName, lastName } = splitFullName(fullName);

  const initialized = {
    id: String(idText || studentId),
    firstName,
    lastName,
    course: courseText || '—',
    gradeBreakdown: {
      grade1: 0,
      grade2: 0,
      grade3: 0,
      grade4: 0,
      finalGrade: 0,
    },
    aggregates: {
      avg1: 0,
      avg2: 0,
      avg3: 0,
      avg4: 0,
    },
  };

  if (typeof studentGradeData !== 'object' || !studentGradeData) {
    // Fallback: initialize container if somehow undefined
    window.studentGradeData = {};
  }
  studentGradeData[studentId] = initialized;
  return initialized;
}

function findGradesTableRowByStudentId(studentId) {
  const tbody = document.getElementById('gradesTableBody');
  if (!tbody) return null;
  const rows = Array.from(tbody.querySelectorAll('tr'));
  for (const row of rows) {
    const idEl = row.querySelector('td:nth-child(1) .fw-bold');
    if (!idEl) continue;
    const idText = (idEl.textContent || '').trim();
    if (idText === String(studentId)) {
      return row;
    }
  }
  return null;
}

function splitFullName(fullName) {
  if (!fullName) return { firstName: '', lastName: '' };
  const parts = fullName.split(/\s+/).filter(Boolean);
  if (parts.length === 1) return { firstName: parts[0], lastName: '' };
  const lastName = parts.pop();
  const firstName = parts.join(' ');
  return { firstName, lastName };
}

// Fixed renderGradeBreakdownTable function with working click events
function renderGradeBreakdownTable(student) {
  console.log('renderGradeBreakdownTable called with student:', student);
  
  const tbody = document.getElementById("gradeBreakdownTableBody");
  if (!tbody) {
    console.error('gradeBreakdownTableBody not found!');
    return;
  }

  const breakdown = student.gradeBreakdown;
  console.log('Grade breakdown:', breakdown);

  // Clear existing content
  tbody.innerHTML = "";

  // Create the row
  const row = document.createElement("tr");
  row.className = "align-middle";

  // Create each grade cell with proper click functionality
  for (let i = 1; i <= 4; i++) {
    const td = document.createElement("td");
    const span = document.createElement("span");
    
    const gradeValue = breakdown[`grade${i}`] || 0;
    
    span.className = `grade-cell ${getGradeColorClass(gradeValue)}`;
    span.setAttribute("data-student-id", student.id);
    span.setAttribute("data-grade-number", i);
    span.setAttribute("data-grade-value", gradeValue.toFixed(1));
    span.textContent = `${gradeValue.toFixed(1)}%`;
    
    // Add styling for better click experience
    span.style.cssText = `
      cursor: pointer !important;
      display: inline-block !important;
      padding: 8px 12px !important;
      border-radius: 6px !important;
      font-weight: bold !important;
      transition: all 0.2s ease !important;
      user-select: none !important;
      pointer-events: auto !important;
      position: relative !important;
      z-index: 10 !important;
    `;
    
    // Add click event listener for popup
    span.addEventListener("click", function(e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      const studentId = this.getAttribute("data-student-id");
      const gradeNumber = this.getAttribute("data-grade-number");
      const gradeValue = this.getAttribute("data-grade-value");
      
      openGradeDetail(studentId, gradeNumber, gradeValue);
    }, true);
    
    // Add hover effects
    span.addEventListener("mouseenter", function() {
      console.log("Mouse entered grade cell");
      this.style.transform = "scale(1.05)";
      this.style.boxShadow = "0 4px 12px rgba(0, 0, 0, 0.15)";
      this.style.backgroundColor = "rgba(0, 123, 255, 0.1)";
    });
    
    span.addEventListener("mouseleave", function() {
      this.style.transform = "scale(1)";
      this.style.boxShadow = "none";
      this.style.backgroundColor = "";
    });
    
    td.appendChild(span);
    row.appendChild(td);
  }

  // Add final grade cell (non-clickable)
  const finalTd = document.createElement("td");
  const finalSpan = document.createElement("span");
  finalSpan.className = `final-grade-cell ${getFinalGradeColorClass(breakdown.finalGrade)}`;
  finalSpan.innerHTML = `<strong>${breakdown.finalGrade.toFixed(1)}%</strong>`;
  finalTd.appendChild(finalSpan);
  row.appendChild(finalTd);

  tbody.appendChild(row);
  
  console.log("Grade breakdown table rendered successfully");
  console.log("Clickable spans:", tbody.querySelectorAll(".grade-cell[data-student-id]").length);
}

// Show grade modal
function showGradeModal() {
  const modal = document.getElementById("gradeModal");
  if (modal) {
    modal.classList.add("show");

    // Ensure view tab panel is active
    const viewGradesTab = document.querySelector('[data-tab="view-grades"]');
    const viewPanel = document.querySelector('[data-panel="view-grades"]');
    if (viewGradesTab) viewGradesTab.classList.add("active");
    if (viewPanel) viewPanel.classList.add("active");
  }
}

// Close grade modal with smooth pop-out animation
function closeGradeModal() {
  const overlay = document.getElementById("gradeModal");
  if (!overlay) return;

  const content = overlay.querySelector(".grade-modal-content");
  if (!content) {
    overlay.classList.remove("show");
    return;
  }

  // Prevent double-trigger
  if (content.classList.contains("closing")) return;

  content.classList.add("closing");

  const onAnimEnd = () => {
    content.classList.remove("closing");
    overlay.classList.remove("show");
    content.removeEventListener("animationend", onAnimEnd);
  };

  content.addEventListener("animationend", onAnimEnd, { once: true });
}

// Enhanced getGradeColorClass function
function getGradeColorClass(grade) {
  if (grade >= 90) return "grade-excellent";
  if (grade >= 80) return "grade-good";
  if (grade >= 75) return "grade-passing";
  if (grade > 0) return "grade-failing";
  return "grade-no-data";
}

// Enhanced getFinalGradeColorClass function
function getFinalGradeColorClass(grade) {
  if (grade >= 90) return "final-grade-excellent";
  if (grade >= 80) return "final-grade-good";
  if (grade >= 75) return "final-grade-passing";
  if (grade > 0) return "final-grade-failing";
  return "final-grade-no-data";
}

// Handle add grade form submission with validation
function handleAddGradeSubmit(studentId) {
  const form = document.getElementById("addGradeForm");
  const formData = new FormData(form);

  // Validate grades
  const grade1 = parseFloat(formData.get("grade1")) || 0;
  const grade2 = parseFloat(formData.get("grade2")) || 0;
  const grade3 = parseFloat(formData.get("grade3")) || 0;
  const grade4 = parseFloat(formData.get("grade4")) || 0;

  // Check if grades are within valid range
  if (
    grade1 < 0 ||
    grade1 > 100 ||
    grade2 < 0 ||
    grade2 > 100 ||
    grade3 < 0 ||
    grade3 > 100 ||
    grade4 < 0 ||
    grade4 > 100
  ) {
    alert("Please enter grades between 0 and 100");
    return;
  }

  const gradeData = {
    studentId: studentId,
    grade1: grade1,
    grade2: grade2,
    grade3: grade3,
    grade4: grade4,
  };

  // Calculate final grade (average)
  gradeData.finalGrade =
    (gradeData.grade1 +
      gradeData.grade2 +
      gradeData.grade3 +
      gradeData.grade4) /
    4;

  // Determine remarks based on final grade
  let remarks = "INCOMPLETE";
  if (gradeData.finalGrade >= 75) {
    remarks = "PASSED";
  } else if (gradeData.finalGrade > 0 && gradeData.finalGrade < 75) {
    remarks = "FAILED";
  }

  // Update student data
  if (studentGradeData[studentId]) {
    studentGradeData[studentId].gradeBreakdown = {
      grade1: gradeData.grade1,
      grade2: gradeData.grade2,
      grade3: gradeData.grade3,
      grade4: gradeData.grade4,
      finalGrade: gradeData.finalGrade,
      remarks: remarks,
    };
  }

  console.log("Grade data updated:", gradeData);

  // Refresh the view grades table
  renderGradeBreakdownTable(studentGradeData[studentId]);

  // Switch to view grades tab
  const viewGradesTab = document.querySelector('[data-tab="view-grades"]');
  if (viewGradesTab) {
    viewGradesTab.click();
  }

  // Show success message
  alert("Grades updated successfully!");
}

// Function to restrict input to numbers only
function restrictToNumbers(input) {
  input.addEventListener("input", function (e) {
    // Remove any non-numeric characters except decimal point
    this.value = this.value.replace(/[^0-9.]/g, "");

    // Ensure only one decimal point
    const parts = this.value.split(".");
    if (parts.length > 2) {
      this.value = parts[0] + "." + parts.slice(1).join("");
    }

    // Limit to 2 decimal places
    if (parts[1] && parts[1].length > 2) {
      this.value = parts[0] + "." + parts[1].substring(0, 2);
    }

    // Ensure value is within 0-100 range
    const numValue = parseFloat(this.value);
    if (numValue > 100) {
      this.value = "100";
    }
  });

  // Prevent non-numeric keypress
  input.addEventListener("keypress", function (e) {
    const charCode = e.which ? e.which : e.keyCode;

    // Allow: backspace, delete, tab, escape, enter, decimal point
    if (
      [8, 9, 27, 13, 46].indexOf(charCode) !== -1 ||
      // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
      (charCode === 65 && e.ctrlKey === true) ||
      (charCode === 67 && e.ctrlKey === true) ||
      (charCode === 86 && e.ctrlKey === true) ||
      (charCode === 88 && e.ctrlKey === true)
    ) {
      return;
    }

    // Allow decimal point only once
    if (charCode === 46 && this.value.indexOf(".") !== -1) {
      e.preventDefault();
      return;
    }

    // Ensure that it is a number and stop the keypress
    if ((charCode < 48 || charCode > 57) && charCode !== 46) {
      e.preventDefault();
    }
  });

  // Handle paste events
  input.addEventListener("paste", function (e) {
    e.preventDefault();
    const paste = (e.clipboardData || window.clipboardData).getData("text");
    const cleanedPaste = paste.replace(/[^0-9.]/g, "");

    if (cleanedPaste && !isNaN(cleanedPaste)) {
      const numValue = parseFloat(cleanedPaste);
      this.value = numValue > 100 ? "100" : cleanedPaste;
    }
  });
}

// Initialize number restriction for grade inputs
function initializeGradeInputs() {
  const gradeInputs = [
    document.getElementById("grade1"),
    document.getElementById("grade2"),
    document.getElementById("grade3"),
    document.getElementById("grade4"),
  ];

  gradeInputs.forEach((input) => {
    if (input) {
      restrictToNumbers(input);
    }
  });
}

// Setup the add grade form
function setupAddGradeForm(student) {
  const form = document.getElementById("addGradeForm");
  if (!form) return;

  // Initialize number-only inputs
  initializeGradeInputs();

  // Setup form submission
  form.onsubmit = function (e) {
    e.preventDefault();
    handleAddGradeSubmit(student.id);
  };
}

// ========== AUTO-POPULATE QUIZ GRADES FUNCTIONALITY REMOVED ==========

// Sync all quiz grades for instructor
function syncAllQuizGrades() {
    if (!confirm('This will sync all quiz submissions to Grade 1 for all students. Continue?')) {
        return;
    }
    
    fetch('apis/auto_grade_integration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'sync_quiz_grades'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully processed ${data.processed} quiz submissions!`);
            if (data.errors && data.errors.length > 0) {
                console.warn('Some errors occurred:', data.errors);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to sync quiz grades');
    });
}

// Get student quiz summary
function getStudentQuizSummary(studentNumber) {
    return fetch('apis/auto_grade_integration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_student_quiz_summary',
            student_number: studentNumber
        })
    })
    .then(response => response.json());
}

// ========== EXAM SUBMISSIONS FUNCTIONALITY ==========

// Load exam submissions
function loadExamSubmissions() {
    fetch('apis/exam_submissions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_exam_submissions'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayExamSubmissions(data.submissions);
        } else {
            showExamSubmissionsError('Error loading submissions: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showExamSubmissionsError('Failed to load exam submissions');
    });
}

// Display exam submissions
function displayExamSubmissions(submissions) {
    const tableBody = document.getElementById('examSubmissionsTableBody');
    if (!tableBody) return;
    
    if (submissions.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted">No exam submissions yet</td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    submissions.forEach(submission => {
        const submittedDate = new Date(submission.submitted_at).toLocaleDateString();
        const score = submission.score ? (parseFloat(submission.score) || 0).toFixed(1) + '%' : 'Not scored';
        const badgeClass = getScoreBadgeClass(submission.score);
        
        html += `
            <tr>
                <td>
                    <div class="student-info">
                        <strong>${submission.first_name} ${submission.last_name}</strong>
                        <small class="text-muted d-block">${submission.student_number} - ${submission.course}</small>
                    </div>
                </td>
                <td>${submission.exam_title}</td>
                <td>
                    <span class="badge ${badgeClass}">${score}</span>
                </td>
                <td>${submittedDate}</td>
                <td>
                    <span class="badge ${submission.status === 'submitted' ? 'bg-success' : 'bg-warning'}">
                        ${submission.status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewExamSubmissionDetails(${submission.submission_id}, 'exam')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

// Show exam submissions error
function showExamSubmissionsError(message) {
    const tableBody = document.getElementById('examSubmissionsTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </td>
            </tr>
        `;
    }
}

// View exam submission details
function viewExamSubmissionDetails(submissionId, type) {
    fetch('apis/exam_submissions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_submission_details',
            submission_id: submissionId,
            type: type
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showExamSubmissionDetailsModal(data.submission);
        } else {
            alert('Error loading submission details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load submission details');
    });
}

// Show exam submission details modal
function showExamSubmissionDetailsModal(submission) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;';
    
    const score = submission.score ? (parseFloat(submission.score) || 0).toFixed(1) + '%' : 'Not scored';
    
    modal.innerHTML = `
        <div class="modal-container" style="background: white; border-radius: 8px; max-width: 800px; width: 90%; max-height: 90%; overflow-y: auto;">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Exam Submission Details</h3>
                <button onclick="this.closest('.modal-overlay').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Student:</strong> ${submission.first_name} ${submission.last_name}<br>
                        <strong>Student Number:</strong> ${submission.student_number}<br>
                        <strong>Course:</strong> ${submission.course}
                    </div>
                    <div class="col-md-6">
                        <strong>Exam:</strong> ${submission.exam_title}<br>
                        <strong>Score:</strong> ${score}<br>
                        <strong>Submitted:</strong> ${new Date(submission.submitted_at).toLocaleString()}
                    </div>
                </div>
                
                <h5>Answers:</h5>
                <div class="answers-container">
                    ${submission.answers.map((answer, index) => `
                        <div class="answer-item" style="border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
                            <strong>Question ${index + 1}:</strong> ${answer.question_text}<br>
                            <strong>Type:</strong> ${answer.question_type}<br>
                            ${answer.answer_text ? `<strong>Answer:</strong> ${answer.answer_text}<br>` : ''}
                            ${answer.option_text ? `<strong>Selected Option:</strong> ${answer.option_text}<br>` : ''}
                            <strong>Correct:</strong> 
                            <span class="badge ${answer.is_correct ? 'bg-success' : 'bg-danger'}">
                                ${answer.is_correct ? 'Yes' : 'No'}
                            </span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Sync all exam grades
function syncAllExamGrades() {
    if (!confirm('This will sync all exam submissions to Grade 1 for all students. Continue?')) {
        return;
    }
    
    fetch('apis/auto_grade_integration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'sync_exam_grades'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Successfully processed ${data.processed} exam submissions!`);
            if (data.errors && data.errors.length > 0) {
                console.warn('Some errors occurred:', data.errors);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to sync exam grades');
    });
}

// ========== GRADE DETAIL MODAL FUNCTIONALITY ==========

// Initialize grade detail modal functionality with database connectivity
function initializeGradeDetailModal() {
  for (let i = 1; i <= 4; i++) {
    const modal = document.getElementById(`gradeDetailModal${i}`);
    const closeBtn = document.getElementById(`gradeDetailModalClose${i}`);
    const openPopupBtn = document.getElementById(`openAddGradePopupBtn${i}`);
    const deleteColumnBtn = document.getElementById(`deleteGradeColumnBtn${i}`);
    const editColumnBtn = document.getElementById(`editGradeColumnBtn${i}`);
    const deletePopup = document.getElementById(`gradeDetailDeletePopup${i}`);
    const autoPopulateBtn = document.getElementById(`autoPopulateQuizBtn${i}`);
    const closeDeletePopup = document.getElementById(`closeDeletePopup${i}`);
    const cancelDeletePopup = document.getElementById(`cancelDeletePopup${i}`);
    const deleteList = document.getElementById(`deleteColumnsList${i}`);
    const deleteForm = document.getElementById(`gradeDetailDeleteForm${i}`);
    const popup = document.getElementById(`gradeDetailAddPopup${i}`);
    const closePopupBtn = document.getElementById(`closeAddGradePopup${i}`);
    const cancelPopupBtn = document.getElementById(`cancelAddGradePopup${i}`);
    const popupForm = document.getElementById(`gradeDetailAddForm${i}`);
    const popupValueInput = document.getElementById(`gradeDetailPopupValue${i}`);

    if (!modal || !closeBtn) continue;

    // Close modal handlers
    closeBtn.addEventListener("click", () => closeGradeDetailModal(i));

    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        closeGradeDetailModal(i);
      }
    });

    // Open popup handler
    if (openPopupBtn && popup) {
      openPopupBtn.addEventListener("click", () => {
        openAddGradePopup(i);
      });
    }

    // Auto-populate functionality removed

    // Close/cancel popup handlers
    if (closePopupBtn)
      closePopupBtn.addEventListener("click", () => closeAddGradePopup(i));
    if (cancelPopupBtn)
      cancelPopupBtn.addEventListener("click", () => closeAddGradePopup(i));

    // Delete column handler
    if (deleteColumnBtn) {
      deleteColumnBtn.addEventListener("click", () => {
        openDeletePopup(i);
      });
    }

    // Edit column handler
    if (editColumnBtn) {
      editColumnBtn.addEventListener("click", () => {
        openEditPopup(i);
      });
    }

    // Delete popup controls
    if (closeDeletePopup) {
      closeDeletePopup.addEventListener("click", () => closeDelete(i));
    }
    if (cancelDeletePopup) {
      cancelDeletePopup.addEventListener("click", () => closeDelete(i));
    }

    // Delete form submission handler
    if (deleteForm) {
      deleteForm.addEventListener("submit", function(e) {
        e.preventDefault();
        const studentId = document.getElementById(`detailStudentId${i}`)?.value;
        const gradeNumber = document.getElementById(`detailGradeNumber${i}`)?.value;
        
        // Get all checked checkboxes
        const checkedBoxes = deleteForm.querySelectorAll('input[name="deleteIndexes"]:checked');
        const selectedIndexes = Array.from(checkedBoxes).map(cb => parseInt(cb.value));
        
        if (selectedIndexes.length === 0) {
          alert('Please select at least one item to delete.');
          return;
        }
        
        // Get selected entries data for display in the modal
        const key = getEntryKey(studentId, gradeNumber);
        const allEntries = gradeDetailEntries[key] || [];
        const selectedEntries = selectedIndexes.map(idx => allEntries[idx]).filter(Boolean);
        
        // Use unified confirmation modal
        showDeleteConfirmation(selectedEntries, () => {
          performDelete(studentId, gradeNumber, selectedIndexes);
        });
      });
    }

    // Submit popup form
    if (popupForm) {
      popupForm.addEventListener("submit", function (e) {
        e.preventDefault();
        saveGradeDetail(i);
      });
    }

    // Numeric restriction for popup input
    if (popupValueInput) {
      restrictToNumbers(popupValueInput);
    }

    // Setup input event listeners for this grade's form
    setupGradeFormListeners(i);
  }

  // Global ESC key handler
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      for (let i = 1; i <= 4; i++) {
        const modal = document.getElementById(`gradeDetailModal${i}`);
        if (modal && modal.classList.contains("show")) {
          closeGradeDetailModal(i);
          break;
        }
      }
    }
  });

  console.log("Grade detail modal initialized");
}

// Date validation initialization
function initializeDateValidation() {
  for (let i = 1; i <= 4; i++) {
    const dateInput = document.getElementById(`gradeAssessmentDate${i}`);
    if (!dateInput) continue;

    // Add input event listener to validate year length
    dateInput.addEventListener("input", function (e) {
      const value = e.target.value;
      if (!value) return;

      // Split the date to check year part
      const dateParts = value.split("-");
      if (dateParts.length === 3) {
        const year = dateParts[0];

        // Check if year is exactly 4 digits
        if (year.length !== 4 || !/^\d{4}$/.test(year)) {
          e.target.setCustomValidity("Year must be exactly 4 digits");
          e.target.classList.add("is-invalid");
        } else {
          e.target.setCustomValidity("");
          e.target.classList.remove("is-invalid");
        }
      }
    });

    // Add blur event to validate when user leaves the field
    dateInput.addEventListener("blur", function (e) {
      const value = e.target.value;
      if (!value) {
        e.target.setCustomValidity("");
        e.target.classList.remove("is-invalid");
        return;
      }

      const dateParts = value.split("-");
      if (dateParts.length === 3) {
        const year = dateParts[0];
        const month = dateParts[1];
        const day = dateParts[2];

        // Validate year is exactly 4 digits
        if (year.length !== 4 || !/^\d{4}$/.test(year)) {
          e.target.setCustomValidity("Year must be exactly 4 digits (YYYY)");
          e.target.classList.add("is-invalid");
          return;
        }

        // Validate it's a valid date
        const dateObj = new Date(value);
        const isValidDate =
          dateObj instanceof Date &&
          !isNaN(dateObj) &&
          dateObj.getFullYear().toString() === year &&
          (dateObj.getMonth() + 1).toString().padStart(2, "0") === month &&
          dateObj.getDate().toString().padStart(2, "0") === day;

        if (!isValidDate) {
          e.target.setCustomValidity("Please enter a valid date");
          e.target.classList.add("is-invalid");
        } else {
          e.target.setCustomValidity("");
          e.target.classList.remove("is-invalid");
        }
      } else {
        e.target.setCustomValidity("Please use the format MM/DD/YYYY");
        e.target.classList.add("is-invalid");
      }
    });

    // Prevent typing more than 4 digits in year when manually typing
    dateInput.addEventListener("keydown", function (e) {
      // Allow special keys (backspace, delete, tab, escape, enter, etc.)
      if (
        [8, 9, 27, 13, 46, 37, 38, 39, 40].indexOf(e.keyCode) !== -1 ||
        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        (e.keyCode === 65 && e.ctrlKey === true) ||
        (e.keyCode === 67 && e.ctrlKey === true) ||
        (e.keyCode === 86 && e.ctrlKey === true) ||
        (e.keyCode === 88 && e.ctrlKey === true)
      ) {
        return;
      }

      const value = e.target.value;
      const cursorPos = e.target.selectionStart;

      // Check if we're in the year section (first 4 characters for YYYY-MM-DD format)
      if (cursorPos <= 3 && value.length >= 4 && !e.ctrlKey && !e.metaKey) {
        const yearSection = value.substring(0, 4);
        if (yearSection.length >= 4 && /^\d{4}$/.test(yearSection)) {
          // Year already has 4 digits, prevent adding more
          if (
            cursorPos <= 3 &&
            e.target.selectionEnd === e.target.selectionStart
          ) {
            e.preventDefault();
          }
        }
      }
    });
  }
}

// Function to open grade detail modal
function openGradeDetail(studentId, gradeNumber, gradeValue) {
  const student = studentGradeData[studentId];
  if (!student) {
    console.error("Student data not found for ID:", studentId);
    return;
  }

  const modalId = `gradeDetailModal${gradeNumber}`;
  const titleId = `gradeDetailModalTitle${gradeNumber}`;

  // Update modal title
  const titleElement = document.getElementById(titleId);
  if (titleElement) {
    const gradeDescriptions = {
      1: "GRADE 1: WRITTEN ACTIVITIES/QUIZZES/ASSIGNMENT (25%)",
      2: "GRADE 2: ATTENDANCE & ATTITUDE (25%)",
      3: "GRADE 3: PRACTICAL & MINOR ACTIVITIES (25%)",
      4: "GRADE 4: INST'L ASSESSMENT (25%)",
    };

    titleElement.innerHTML = `
      <i class="fas fa-chart-bar"></i>
      ${gradeDescriptions[gradeNumber]} - ${student.firstName} ${student.lastName} (${student.id}) - ${gradeValue}%
    `;
  }

  // Set hidden form values
  const studentIdInput = document.getElementById(
    `detailStudentId${gradeNumber}`
  );
  const gradeNumberInput = document.getElementById(
    `detailGradeNumber${gradeNumber}`
  );
  if (studentIdInput) studentIdInput.value = studentId;
  if (gradeNumberInput) gradeNumberInput.value = gradeNumber;

  // Render grid for this specific grade
  renderGradeDetailGrid(studentId, gradeNumber);

  // Load from database and refresh
  loadGradeDetailsFromDb(studentId, gradeNumber).then(() => {
    renderGradeDetailGrid(studentId, gradeNumber);
    const key = getEntryKey(studentId, gradeNumber);
    const entries = gradeDetailEntries[key] || [];
    const avg = entries.length
      ? entries.reduce((a, e) => a + (parseFloat(e.transmuted) || 0), 0) /
        entries.length
      : 0;
    const s = studentGradeData[studentId];
    if (s && s.gradeBreakdown) {
      if (String(gradeNumber) === "1") s.gradeBreakdown.grade1 = avg;
      if (String(gradeNumber) === "2") s.gradeBreakdown.grade2 = avg;
      if (String(gradeNumber) === "3") s.gradeBreakdown.grade3 = avg;
      if (String(gradeNumber) === "4") s.gradeBreakdown.grade4 = avg;
      s.gradeBreakdown.finalGrade =
        (s.gradeBreakdown.grade1 +
          s.gradeBreakdown.grade2 +
          s.gradeBreakdown.grade3 +
          s.gradeBreakdown.grade4) /
        4;
      renderGradeBreakdownTable(s);
    }
  });

  // Show the specific modal
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("show");
  }
}

// Close grade detail modal with smooth animation
function closeGradeDetailModal(gradeNumber) {
  const overlay = document.getElementById(`gradeDetailModal${gradeNumber}`);
  if (!overlay) return;

  const content = overlay.querySelector(".grade-detail-modal-content");
  if (!content) {
    overlay.classList.remove("show");
    closeAddGradePopup(gradeNumber);
    return;
  }

  if (content.dataset.closing === "1") return;
  content.dataset.closing = "1";

  content.style.animation = "none";
  void content.offsetHeight;
  content.style.animation = "modalSlideOut 0.22s ease-in forwards";

  const finish = () => {
    content.style.animation = "";
    content.classList.remove("closing");
    content.dataset.closing = "0";
    overlay.classList.remove("show");
    closeAddGradePopup(gradeNumber);
  };

  const fallback = setTimeout(finish, 260);

  content.addEventListener("animationend", function onEnd(e) {
    if (e.target !== content) return;
    clearTimeout(fallback);
    content.removeEventListener("animationend", onEnd);
    finish();
  });
}

function openAddGradePopup(gradeNumber) {
  const popup = document.getElementById(`gradeDetailAddPopup${gradeNumber}`);
  const rawInput = document.getElementById(`gradeDetailPopupRaw${gradeNumber}`);

  if (popup) {
    popup.classList.remove("closing");
    popup.style.display = "flex";

    if (rawInput) {
      setTimeout(() => {
        rawInput.focus();
        rawInput.select();
      }, 100);
    }
  }
}

function closeAddGradePopup(gradeNumber) {
  const popup = document.getElementById(`gradeDetailAddPopup${gradeNumber}`);
  const popupForm = document.getElementById(`gradeDetailAddForm${gradeNumber}`);

  if (popup) {
    popup.classList.add("closing");

    setTimeout(() => {
      popup.style.display = "none";
      popup.classList.remove("closing");

      if (popupForm) popupForm.reset();
    }, 250);
  }
}

function openEditPopup(gradeNumber) {
  const studentId = document.getElementById(`detailStudentId${gradeNumber}`)?.value;
  const gradeNumberValue = document.getElementById(`detailGradeNumber${gradeNumber}`)?.value;
  
  if (!studentId || !gradeNumberValue) return;

  // Get existing entries for this grade
  const key = getEntryKey(studentId, gradeNumberValue);
  const entries = gradeDetailEntries[key] || [];
  
  if (entries.length === 0) {
    alert('No grades found to edit. Please add some grades first.');
    return;
  }

  // Create edit popup dynamically
  createEditPopup(gradeNumber, entries);
}

function createEditPopup(gradeNumber, entries) {
  // Remove existing edit popup if any
  const existingPopup = document.getElementById(`gradeDetailEditPopup${gradeNumber}`);
  if (existingPopup) {
    existingPopup.remove();
  }

  // Create edit popup HTML
  const editPopup = document.createElement('div');
  editPopup.id = `gradeDetailEditPopup${gradeNumber}`;
  editPopup.style.cssText = `
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
  `;
  
  editPopup.innerHTML = `
    <div style="
      background: var(--background);
      width: min(600px, 90%);
      max-height: 80vh;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    ">
      <div style="
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
      ">
        <h3 style="
          margin: 0;
          font-size: 18px;
          display: flex;
          align-items: center;
          gap: 8px;
          color: var(--foreground);
        ">
          <i class="fas fa-edit"></i> Edit Grades
        </h3>
        <button id="closeEditPopup${gradeNumber}" style="
          background: none;
          border: none;
          font-size: 20px;
          cursor: pointer;
          color: var(--foreground);
          padding: 4px;
        ">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div style="
        padding: 20px;
        flex: 1;
        overflow-y: auto;
      ">
        <form id="gradeDetailEditForm${gradeNumber}">
          <div id="editEntriesList${gradeNumber}">
            ${entries.map((entry, index) => `
              <div style="
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                background: var(--background);
              ">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                  <h4 style="margin: 0; color: var(--foreground);">${entry.component || 'Entry ' + (index + 1)}</h4>
                  <button type="button" onclick="removeEditEntry(${gradeNumber}, ${index})" style="
                    background: #dc3545;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    padding: 4px 8px;
                    cursor: pointer;
                    font-size: 12px;
                  ">
                    <i class="fas fa-trash"></i> Remove
                  </button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                  <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--foreground);">Date:</label>
                    <input type="date" name="editDate${index}" value="${entry.date || entry.date_given || new Date().toISOString().split('T')[0]}" style="
                      width: 100%;
                      padding: 8px;
                      border: 1px solid var(--border);
                      border-radius: 4px;
                      background: var(--background);
                      color: var(--foreground);
                    " required>
                  </div>
                  <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--foreground);">Component:</label>
                    ${gradeNumber === 2 ? `
                      <div style="display: flex; gap: 15px; margin-top: 5px;">
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                          <input type="radio" name="editComponent${index}" value="Present" ${entry.component === 'Present' || entry.component === 'present' ? 'checked' : ''} style="margin: 0;">
                          <span style="color: var(--foreground);">Present</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                          <input type="radio" name="editComponent${index}" value="Absent" ${entry.component === 'Absent' || entry.component === 'absent' ? 'checked' : ''} style="margin: 0;">
                          <span style="color: var(--foreground);">Absent</span>
                        </label>
                      </div>
                    ` : (gradeNumber === 1 || gradeNumber === 3) ? `
                      <select name="editComponent${index}" style="
                        width: 100%;
                        padding: 8px;
                        border: 1px solid var(--border);
                        border-radius: 4px;
                        background: var(--background);
                        color: var(--foreground);
                      " required>
                        <option value="quiz" ${entry.component === 'quiz' ? 'selected' : ''}>Quiz</option>
                        <option value="homework" ${entry.component === 'homework' ? 'selected' : ''}>Homework</option>
                        <option value="activity" ${entry.component === 'activity' ? 'selected' : ''}>Activity</option>
                        <option value="exam" ${entry.component === 'exam' ? 'selected' : ''}>Exam</option>
                      </select>
                    ` : `
                      <input type="text" name="editComponent${index}" value="${entry.component || ''}" style="
                        width: 100%;
                        padding: 8px;
                        border: 1px solid var(--border);
                        border-radius: 4px;
                        background: var(--background);
                        color: var(--foreground);
                      " required>
                    `}
                  </div>
                  ${gradeNumber === 1 || gradeNumber === 3 || gradeNumber === 4 ? `
                  <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--foreground);">Total Items:</label>
                    <input type="number" name="editTotal${index}" value="${entry.total || entry.total_items || 50}" min="1" max="100" style="
                      width: 100%;
                      padding: 8px;
                      border: 1px solid var(--border);
                      border-radius: 4px;
                      background: var(--background);
                      color: var(--foreground);
                    " required>
                  </div>
                  ` : ''}
                  <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--foreground);">Raw Score:</label>
                    <input type="number" name="editRaw${index}" value="${entry.raw || entry.raw_score || 0}" min="0" max="${gradeNumber === 1 || gradeNumber === 3 || gradeNumber === 4 ? (entry.total || entry.total_items || 50) : 100}" style="
                      width: 100%;
                      padding: 8px;
                      border: 1px solid var(--border);
                      border-radius: 4px;
                      background: var(--background);
                      color: var(--foreground);
                    " required>
                  </div>
                  <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--foreground);">Transmuted:</label>
                    <input type="number" name="editTransmuted${index}" value="${gradeNumber === 2 ? (entry.component === 'Present' || entry.component === 'present' ? '100' : (entry.component === 'Absent' || entry.component === 'absent' ? '50' : (entry.transmuted || 0))) : (entry.transmuted || 0)}" min="0" max="100" step="0.01" ${gradeNumber === 2 || gradeNumber === 1 || gradeNumber === 3 || gradeNumber === 4 ? 'readonly style="opacity: 0.7;"' : ''} style="
                      width: 100%;
                      padding: 8px;
                      border: 1px solid var(--border);
                      border-radius: 4px;
                      background: var(--background);
                      color: var(--foreground);
                    " required>
                  </div>
                </div>
                <input type="hidden" name="editIndex${index}" value="${index}">
              </div>
            `).join('')}
          </div>
          <div style="
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
          ">
            <button type="button" id="cancelEditPopup${gradeNumber}" style="
              background: #6c757d;
              color: white;
              border: none;
              border-radius: 4px;
              padding: 10px 20px;
              cursor: pointer;
            ">
              Cancel
            </button>
            <button type="submit" style="
              background: #28a745;
              color: white;
              border: none;
              border-radius: 4px;
              padding: 10px 20px;
              cursor: pointer;
            ">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  document.body.appendChild(editPopup);
  
  // Add event listeners
  const closeBtn = document.getElementById(`closeEditPopup${gradeNumber}`);
  const cancelBtn = document.getElementById(`cancelEditPopup${gradeNumber}`);
  const form = document.getElementById(`gradeDetailEditForm${gradeNumber}`);
  
  if (closeBtn) {
    closeBtn.addEventListener('click', () => closeEditPopup(gradeNumber));
  }
  
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => closeEditPopup(gradeNumber));
  }
  
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      saveEditGrades(gradeNumber);
    });
  }
  
  // Close on backdrop click
  editPopup.addEventListener('click', (e) => {
    if (e.target === editPopup) {
      closeEditPopup(gradeNumber);
    }
  });
  
  // Add event listeners for radio button changes to update transmuted values (Grade 2 only)
  if (gradeNumber === 2) {
    const radioButtons = editPopup.querySelectorAll('input[type="radio"][name^="editComponent"]');
    radioButtons.forEach(radio => {
      radio.addEventListener('change', function() {
        const index = this.name.match(/editComponent(\d+)/)[1];
        const transmutedInput = editPopup.querySelector(`input[name="editTransmuted${index}"]`);
        if (transmutedInput) {
          transmutedInput.value = this.value === 'Present' ? '100' : '50';
        }
      });
    });
  }
  
  // Add event listeners for Grade 1, Grade 3, and Grade 4 automatic transmuted calculation
  if (gradeNumber === 1 || gradeNumber === 3 || gradeNumber === 4) {
    const rawInputs = editPopup.querySelectorAll('input[name^="editRaw"]');
    const totalInputs = editPopup.querySelectorAll('input[name^="editTotal"]');
    
    rawInputs.forEach(rawInput => {
      rawInput.addEventListener('input', function() {
        const index = this.name.match(/editRaw(\d+)/)[1];
        const totalInput = editPopup.querySelector(`input[name="editTotal${index}"]`);
        const transmutedInput = editPopup.querySelector(`input[name="editTransmuted${index}"]`);
        
        if (totalInput && transmutedInput && totalInput.value && this.value) {
          const raw = parseFloat(this.value);
          const total = parseFloat(totalInput.value);
          
          // Validate that raw score doesn't exceed total items
          if (raw > total) {
            alert(`Raw score (${raw}) cannot exceed total items (${total}). Please enter a valid score.`);
            this.value = total; // Set to maximum allowed value
            return;
          }
          
          if (total > 0) {
            // Calculate transmuted grade: (raw/total) * 50 + 50
            const transmuted = (raw / total) * 50 + 50;
            transmutedInput.value = Math.round(transmuted * 100) / 100; // Round to 2 decimal places
          }
        }
      });
    });
    
    totalInputs.forEach(totalInput => {
      totalInput.addEventListener('input', function() {
        const index = this.name.match(/editTotal(\d+)/)[1];
        const rawInput = editPopup.querySelector(`input[name="editRaw${index}"]`);
        const transmutedInput = editPopup.querySelector(`input[name="editTransmuted${index}"]`);
        
        if (rawInput && transmutedInput && rawInput.value && this.value) {
          const raw = parseFloat(rawInput.value);
          const total = parseFloat(this.value);
          
          // Update max attribute of raw score input
          rawInput.max = total;
          
          // Validate that raw score doesn't exceed new total items
          if (raw > total) {
            alert(`Raw score (${raw}) cannot exceed total items (${total}). Please enter a valid score.`);
            rawInput.value = total; // Set to maximum allowed value
            return;
          }
          
          if (total > 0) {
            // Calculate transmuted grade: (raw/total) * 50 + 50
            const transmuted = (raw / total) * 50 + 50;
            transmutedInput.value = Math.round(transmuted * 100) / 100; // Round to 2 decimal places
          }
        }
      });
    });
  }
}

function removeEditEntry(gradeNumber, index) {
  const entryDiv = document.querySelector(`#editEntriesList${gradeNumber} > div:nth-child(${index + 1})`);
  if (entryDiv) {
    entryDiv.remove();
  }
}

function closeEditPopup(gradeNumber) {
  const popup = document.getElementById(`gradeDetailEditPopup${gradeNumber}`);
  if (popup) {
    popup.remove();
  }
}

// Function to refresh grade display without page reload
function refreshGradeDisplay(studentId, gradeNumber) {
  console.log('Refreshing grade display for student:', studentId, 'grade:', gradeNumber);
  
  // Reload grade details from database
  loadGradeDetailsFromDb(studentId, gradeNumber).then(() => {
    // Re-render the grade detail grid
    renderGradeDetailGrid(studentId, gradeNumber);
    
    // Update the grade breakdown table
    const student = window.studentGradeData[studentId];
    if (student) {
      // Recalculate grade breakdown
      const key = getEntryKey(studentId, gradeNumber);
      const entries = gradeDetailEntries[key] || [];
      const avg = entries.length
        ? entries.reduce((a, e) => a + (parseFloat(e.transmuted) || 0), 0) / entries.length
        : 0;
      
      // Update the specific grade in the breakdown
      if (gradeNumber == 1) student.gradeBreakdown.grade1 = avg;
      else if (gradeNumber == 2) student.gradeBreakdown.grade2 = avg;
      else if (gradeNumber == 3) student.gradeBreakdown.grade3 = avg;
      else if (gradeNumber == 4) student.gradeBreakdown.grade4 = avg;
      
      // Recalculate final grade
      student.gradeBreakdown.finalGrade = (
        student.gradeBreakdown.grade1 +
        student.gradeBreakdown.grade2 +
        student.gradeBreakdown.grade3 +
        student.gradeBreakdown.grade4
      ) / 4;
      
      // Re-render the grade breakdown table
      renderGradeBreakdownTable(student);
      
      // Update the main grades table if visible
      updateMainGradesTableFinal(studentId, student.gradeBreakdown.finalGrade);
    }
  }).catch(error => {
    console.error('Error refreshing grade display:', error);
  });
}

function saveEditGrades(gradeNumber) {
  const studentId = document.getElementById(`detailStudentId${gradeNumber}`)?.value;
  const gradeNumberValue = document.getElementById(`detailGradeNumber${gradeNumber}`)?.value;
  
  if (!studentId || !gradeNumberValue) return;
  
  const form = document.getElementById(`gradeDetailEditForm${gradeNumber}`);
  const formData = new FormData(form);
  
  // Collect all edited entries
  const editedEntries = [];
  const entryDivs = document.querySelectorAll(`#editEntriesList${gradeNumber} > div`);
  
  entryDivs.forEach((div, index) => {
    const date = div.querySelector(`input[name="editDate${index}"]`)?.value;
    const rawScore = div.querySelector(`input[name="editRaw${index}"]`)?.value;
    const transmuted = div.querySelector(`input[name="editTransmuted${index}"]`)?.value;
    const originalIndex = div.querySelector(`input[name="editIndex${index}"]`)?.value;
    const totalItems = div.querySelector(`input[name="editTotal${index}"]`)?.value;
    
    let component;
    if (gradeNumber === 2) {
      // For Grade 2 (Attendance), get component from radio button
      const componentRadio = div.querySelector(`input[name="editComponent${index}"]:checked`);
      component = componentRadio?.value;
    } else if (gradeNumber === 1 || gradeNumber === 3) {
      // For Grade 1 and Grade 3, get component from dropdown
      const componentSelect = div.querySelector(`select[name="editComponent${index}"]`);
      component = componentSelect?.value;
    } else {
      // For other grades, get component from text input
      component = div.querySelector(`input[name="editComponent${index}"]`)?.value;
    }
    
    if (date && component && rawScore && transmuted) {
      editedEntries.push({
        index: parseInt(originalIndex),
        date: date,
        component: component,
        raw_score: gradeNumber === 2 ? (component === 'Present' ? 100 : 50) : parseFloat(rawScore),
        total_items: gradeNumber === 1 || gradeNumber === 3 || gradeNumber === 4 ? parseFloat(totalItems) : (gradeNumber === 2 ? 100 : 100),
        transmuted: parseFloat(transmuted)
      });
    }
  });
  
  if (editedEntries.length === 0) {
    alert('No valid entries to save.');
    return;
  }
  
  // Send update request to server
  fetch('apis/grade_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_grades',
      student_id: studentId,
      grade_number: gradeNumberValue,
      entries: editedEntries
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Grades updated successfully!');
      closeEditPopup(gradeNumber);
      // Refresh the grade display without page reload
      refreshGradeDisplay(studentId, gradeNumberValue);
    } else {
      alert('Error updating grades: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error updating grades. Please try again.');
  });
}

function openDeletePopup(gradeNumber) {
  const studentId = document.getElementById(`detailStudentId${gradeNumber}`)?.value;
  const gradeNumberValue = document.getElementById(`detailGradeNumber${gradeNumber}`)?.value;
  const deletePopup = document.getElementById(`gradeDetailDeletePopup${gradeNumber}`);
  const deleteList = document.getElementById(`deleteColumnsList${gradeNumber}`);
  
  if (!studentId || !gradeNumberValue || !deletePopup || !deleteList) return;

  // Populate list with checkboxes
  const key = getEntryKey(studentId, gradeNumberValue);
  const entries = gradeDetailEntries[key] || [];
  deleteList.innerHTML = '';
  
  if (entries.length === 0) {
    const none = document.createElement('div');
    none.className = 'list-group-item text-center text-muted';
    none.textContent = 'No grade entries to delete';
    deleteList.appendChild(none);
  } else {
    // Add select all controls
    const selectAllContainer = document.createElement('div');
    selectAllContainer.className = 'list-group-item bg-light';
    selectAllContainer.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary">Selection Controls:</span>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn${gradeNumber}">
            <i class="fas fa-check-double"></i> Select All
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn${gradeNumber}">
            <i class="fas fa-times"></i> Clear All
          </button>
        </div>
      </div>
    `;
    deleteList.appendChild(selectAllContainer);
    
    // Add individual checkbox items
    entries.forEach((e, idx) => {
      const item = document.createElement('label');
      item.className = 'list-group-item d-flex align-items-center gap-2 hover-item';
      item.style.cursor = 'pointer';
      item.innerHTML = `
        <input type="checkbox" name="deleteIndexes" value="${idx}" class="form-check-input">
        <div class="flex-grow-1">
          <div class="fw-semibold">${idx + 1}. ${e.date || 'No Date'} • ${(e.component||'Quiz').toUpperCase()}</div>
          <small class="text-muted">Raw Score: ${e.raw}/${e.total} • Transmuted: ${parseFloat(e.transmuted||0).toFixed(1)}%</small>
        </div>
      `;
      deleteList.appendChild(item);
    });
    
    // Add event listeners for select all/deselect all
    document.getElementById(`selectAllBtn${gradeNumber}`)?.addEventListener('click', function() {
      const checkboxes = deleteList.querySelectorAll('input[name="deleteIndexes"]');
      checkboxes.forEach(cb => cb.checked = true);
      updateDeleteButtonText(gradeNumber);
    });
    
    document.getElementById(`deselectAllBtn${gradeNumber}`)?.addEventListener('click', function() {
      const checkboxes = deleteList.querySelectorAll('input[name="deleteIndexes"]');
      checkboxes.forEach(cb => cb.checked = false);
      updateDeleteButtonText(gradeNumber);
    });
    
    // Add change event listeners to update button text
    deleteList.addEventListener('change', () => updateDeleteButtonText(gradeNumber));
  }
  
  deletePopup.style.display = 'flex';
  updateDeleteButtonText(gradeNumber);
}

function closeDelete(gradeNumber) {
  const deletePopup = document.getElementById(`gradeDetailDeletePopup${gradeNumber}`);
  const deleteForm = document.getElementById(`gradeDetailDeleteForm${gradeNumber}`);
  if (deletePopup) deletePopup.style.display = 'none';
  if (deleteForm) deleteForm.reset();
}

function updateDeleteButtonText(gradeNumber) {
  const deleteBtn = document.getElementById(`confirmDeleteColumnBtn${gradeNumber}`);
  const checkedBoxes = document.querySelectorAll(`#gradeDetailDeletePopup${gradeNumber} input[name="deleteIndexes"]:checked`);
  const count = checkedBoxes.length;
  
  if (deleteBtn) {
    if (count === 0) {
      deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Selected';
      deleteBtn.disabled = true;
      deleteBtn.classList.add('opacity-50');
    } else if (count === 1) {
      deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete 1 Entry';
      deleteBtn.disabled = false;
      deleteBtn.classList.remove('opacity-50');
    } else {
      deleteBtn.innerHTML = `<i class="fas fa-trash"></i> Delete ${count} Entries`;
      deleteBtn.disabled = false;
      deleteBtn.classList.remove('opacity-50');
    }
  }
}

// Initialize delete confirmation modal
function initializeDeleteConfirmationModal() {
  const modal = document.getElementById('deleteConfirmationModal');
  const cancelBtn = document.getElementById('cancelDeleteConfirmation');
  const confirmBtn = document.getElementById('confirmDeleteConfirmation');
  
  if (!modal || !cancelBtn || !confirmBtn) return;

  // Close modal handlers
  cancelBtn.addEventListener('click', hideDeleteConfirmation);
  
  // Close on backdrop click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      hideDeleteConfirmation();
    }
  });

  // Close on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      hideDeleteConfirmation();
    }
  });

  console.log('Delete confirmation modal initialized');
}

// Show delete confirmation modal
function showDeleteConfirmation(selectedEntries, onConfirm) {
  const modal = document.getElementById('deleteConfirmationModal');
  const titleElement = document.getElementById('deleteConfirmationTitle');
  const messageElement = document.getElementById('deleteConfirmationMessage');
  const detailsElement = document.getElementById('deleteConfirmationDetails');
  const confirmBtn = document.getElementById('confirmDeleteConfirmation');
  
  if (!modal || !selectedEntries || selectedEntries.length === 0) return;

  const count = selectedEntries.length;
  const isPlural = count > 1;
  
  // Update title
  if (titleElement) {
    titleElement.textContent = `Delete ${count} Grade ${isPlural ? 'Entries' : 'Entry'}`;
  }
  
  // Update message
  if (messageElement) {
    messageElement.textContent = isPlural 
      ? `Are you sure you want to delete these ${count} grade entries? This action cannot be undone.`
      : 'Are you sure you want to delete this grade entry? This action cannot be undone.';
  }
  
  // Update details
  if (detailsElement) {
    detailsElement.innerHTML = '';
    selectedEntries.forEach((entry, index) => {
      const detailItem = document.createElement('div');
      detailItem.className = 'delete-detail-item';
      detailItem.innerHTML = `
        <div class="delete-detail-left">
          <div class="delete-detail-icon">
            <i class="fas fa-trash"></i>
          </div>
          <div>
            <div class="fw-semibold">${entry.date || 'No Date'} • ${(entry.component || 'Quiz').toUpperCase()}</div>
          </div>
        </div>
        <div class="delete-detail-right">
          Raw: ${entry.raw}/${entry.total} • ${parseFloat(entry.transmuted || 0).toFixed(1)}%
        </div>
      `;
      detailsElement.appendChild(detailItem);
    });
  }
  
  // Update confirm button
  if (confirmBtn) {
    confirmBtn.innerHTML = `<i class="fas fa-trash"></i><span>Delete ${isPlural ? `${count} Entries` : 'Entry'}</span>`;
    
    // Remove any existing event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add new event listener
    newConfirmBtn.addEventListener('click', () => {
      if (onConfirm && typeof onConfirm === 'function') {
        // Show loading state
        newConfirmBtn.classList.add('loading');
        newConfirmBtn.innerHTML = '<i class="fas fa-spinner"></i><span>Deleting...</span>';
        
        // Execute the callback
        onConfirm();
      }
    });
  }
  
  // Show modal
  modal.classList.add('show');
}

// Hide delete confirmation modal
function hideDeleteConfirmation() {
  const modal = document.getElementById('deleteConfirmationModal');
  const confirmBtn = document.getElementById('confirmDeleteConfirmation');
  
  if (modal) {
    modal.classList.remove('show');
  }
  
  // Reset confirm button
  if (confirmBtn) {
    confirmBtn.classList.remove('loading');
    confirmBtn.innerHTML = '<i class="fas fa-trash"></i><span>Delete</span>';
  }
}

// Perform the actual deletion with database connectivity
function performDelete(studentId, gradeNumber, selectedIndexes) {
  try {
    const key = getEntryKey(studentId, gradeNumber);
    const allEntries = gradeDetailEntries[key] || [];
    const ids = selectedIndexes
      .map(idx => allEntries[idx]?.id)
      .filter(v => typeof v !== 'undefined' && v !== null);

    const form = new FormData();
    form.append('action', 'delete');
    ids.forEach(id => form.append('ids[]', id));

    fetch('apis/grade_details.php', { method: 'POST', body: form })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) throw new Error(json?.message || 'Delete failed');

        // After successful server delete, update UI (delete in descending order to avoid index shift)
        selectedIndexes.sort((a, b) => b - a).forEach(idx => {
          deleteGradeColumnByIndex(studentId, gradeNumber, idx);
        });

        hideDeleteConfirmation();
        closeDelete(gradeNumber);

        // Refresh aggregates after deletion
        refreshAggregatesForStudent(studentId);
      })
      .catch(err => {
        console.error('Error deleting entries:', err);
        hideDeleteConfirmation();
      });
  } catch (error) {
    console.error('Error preparing deletion:', error);
    hideDeleteConfirmation();
  }
}

function saveGradeDetail(gradeNumber) {
  const idInput = document.getElementById(`detailStudentId${gradeNumber}`);
  const numberInput = document.getElementById(`detailGradeNumber${gradeNumber}`);
  const rawInput = document.getElementById(`gradeDetailPopupRaw${gradeNumber}`);
  const transmutedOutput = document.getElementById(`gradeDetailPopupTransmuted${gradeNumber}`);

  if (!idInput || !numberInput || !rawInput) return;

  const studentId = idInput.value;
  const raw = parseFloat(rawInput.value) || 0;
  let total = 100; // Default for attendance
  let transmuted = parseFloat((transmutedOutput?.value || "").replace("%", "")) || raw;

  // For attendance (grade 2), handle differently
  if (gradeNumber === 2) {
    const attendanceSelect = document.getElementById(`gradeAssessmentType${gradeNumber}`);
    if (!attendanceSelect || !attendanceSelect.value) {
      alert("Please select attendance status");
      return;
    }
    // For attendance, raw score and transmuted are the same
    total = 100;
    transmuted = raw;
  } else {
    // Original validation for other grades
    const totalInput = document.getElementById(`gradeAssessmentTotal${gradeNumber}`);
    if (!totalInput) return;
    total = parseFloat(totalInput.value) || 0;

    if (raw < 0 || total <= 0 || raw > total) {
      alert("Please enter a valid raw and total score");
      return;
    }

    transmuted = parseFloat((transmutedOutput?.value || "").replace("%", "")) || (total > 0 ? (raw / total) * 100 : 0);
  }

  // Update student data
  const student = studentGradeData[studentId];
  if (!student) return;

  const breakdown = student.gradeBreakdown || {
    grade1: 0,
    grade2: 0,
    grade3: 0,
    grade4: 0,
    finalGrade: 0,
    remarks: "INCOMPLETE",
  };

  // Persist entry for grid and rerender
  persistGradeDetailEntry(studentId, gradeNumber, {
    component: gradeNumber === 2
      ? document.getElementById(`gradeAssessmentType${gradeNumber}`)?.value || ""
      : document.getElementById(`gradeAssessmentType${gradeNumber}`)?.value || "quiz",
    date: document.getElementById(`gradeAssessmentDate${gradeNumber}`)?.value || "",
    raw: raw,
    total: total,
    transmuted: transmuted,
  });

  // Rerender grade breakdown table
  renderGradeBreakdownTable(student);

  // Loading state on Save button
  const saveBtn = document.getElementById(`saveAddGradePopupBtn${gradeNumber}`);
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.classList.add('btn-saving');
    const previousHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    // Persist to backend then refresh aggregate
    const payload = new FormData();
    payload.append('action', 'add');
    payload.append('student_number', studentId);
    payload.append('grade_number', gradeNumber);
    payload.append('component', gradeNumber === 2
      ? document.getElementById(`gradeAssessmentType${gradeNumber}`)?.value || ""
      : document.getElementById(`gradeAssessmentType${gradeNumber}`)?.value || "quiz");
    payload.append('date', document.getElementById(`gradeAssessmentDate${gradeNumber}`)?.value || "");
    payload.append('raw', String(raw));
    payload.append('total', String(total));
    payload.append('transmuted', String(transmuted));

    // Include CSRF token (input and header) if present
    const csrfInput = document.getElementById('csrf_token');
    const csrfToken = csrfInput && csrfInput.value ? csrfInput.value : '';
    if (csrfToken) {
      payload.append('csrf_token', csrfToken);
    }

    fetch('apis/grade_details.php', {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : undefined,
    })
      .then(async (r) => {
        const data = await r.json().catch(() => ({ success: false, message: 'Invalid server response' }));
        if (!r.ok || !data || data.success !== true) {
          const msg = (data && data.message) ? data.message : `Failed to save grade (HTTP ${r.status})`;
          throw new Error(msg);
        }
        return data;
      })
      .then(() => {
        refreshAggregatesForStudent(studentId);
        closeAddGradePopup(gradeNumber);
      })
      .catch(err => {
        console.warn('Save grade detail failed', err);
        alert(err && err.message ? err.message : 'Failed to save grade. Please try again.');
      })
      .finally(() => {
        saveBtn.disabled = false;
        saveBtn.classList.remove('btn-saving');
        saveBtn.innerHTML = previousHtml;
      });
  } else {
    closeAddGradePopup(gradeNumber);
  }
}

function setupGradeFormListeners(gradeNumber) {
  // Handle attendance popup (Grade 2) differently
  if (gradeNumber === 2) {
    setupAttendanceFormListeners(gradeNumber);
    return;
  }

  // Original logic for other grade numbers (1, 3, 4)
  const compSelect = document.getElementById(`gradeAssessmentType${gradeNumber}`);
  const dateInput = document.getElementById(`gradeAssessmentDate${gradeNumber}`);
  const totalInput = document.getElementById(`gradeAssessmentTotal${gradeNumber}`);
  const baseInput = document.getElementById(`gradeAssessmentBase${gradeNumber}`);
  const rawInput = document.getElementById(`gradeDetailPopupRaw${gradeNumber}`);
  const transmutedOutput = document.getElementById(`gradeDetailPopupTransmuted${gradeNumber}`);
  const totalDisplay = document.getElementById(`gradeAssessmentTotalDisplay${gradeNumber}`);
  const baseDisplay = document.getElementById(`gradeAssessmentBaseDisplay${gradeNumber}`);

  function updateTotalDisplay() {
    if (totalDisplay && totalInput) {
      totalDisplay.textContent = `Total Items: ${totalInput.value || "--"}`;
    }
  }

  function computeTransmuted() {
    if (!rawInput || !totalInput || !transmutedOutput) return;
    const raw = parseFloat(rawInput.value);
    const total = parseFloat(totalInput.value);
    const base = Math.max(1, parseFloat(baseInput?.value || '50') || 50);
    const convertedEl = document.getElementById(`convertedScoreDisplay${gradeNumber}`);
    if (
      isNaN(raw) ||
      isNaN(total) ||
      isNaN(base) ||
      total <= 0 ||
      raw < 0 ||
      raw > total
    ) {
      transmutedOutput.value = "";
      if (convertedEl) convertedEl.textContent = `Converted Score: -- out of ${ (50 + (isNaN(base) ? 50 : base)).toFixed(0) }`;
      return;
    }
    // converted_score = (raw/total)*base + 50
    const converted = (raw / total) * base + 50;
    const rounded = Math.round(converted * 100) / 100; // two decimals
    transmutedOutput.value = `${rounded.toFixed(2)}%`;
    if (convertedEl) convertedEl.textContent = `Converted Score: ${rounded.toFixed(2)} out of ${(50 + base).toFixed(0)}`;
  }

  if (totalInput) {
    totalInput.addEventListener("input", () => {
      updateTotalDisplay();
      computeTransmuted();
    });
  }
  if (baseInput) {
    baseInput.addEventListener("input", () => {
      if (baseDisplay) {
        const base = Math.max(1, parseFloat(baseInput.value || '50') || 50);
        baseDisplay.textContent = `Uses (raw/total) × ${base} + 50`;
      }
      computeTransmuted();
    });
  }
  if (rawInput) {
    rawInput.addEventListener("input", computeTransmuted);
  }

  updateTotalDisplay();
  if (baseDisplay) {
    const base = Math.max(1, parseFloat(baseInput?.value || '50') || 50);
    baseDisplay.textContent = `Uses (raw/total) × ${base} + 50`;
  }
}

function setupAttendanceFormListeners(gradeNumber) {
  const attendanceSelect = document.getElementById(`gradeAssessmentType${gradeNumber}`);
  const rawInput = document.getElementById(`gradeDetailPopupRaw${gradeNumber}`);
  const transmutedOutput = document.getElementById(`gradeDetailPopupTransmuted${gradeNumber}`);

  if (attendanceSelect && rawInput && transmutedOutput) {
    attendanceSelect.addEventListener("change", function () {
      const selectedValue = this.value;

      if (selectedValue === "present") {
        rawInput.value = "100";
        transmutedOutput.value = "100%";
      } else if (selectedValue === "absent") {
        rawInput.value = "50";
        transmutedOutput.value = "50%";
      } else {
        rawInput.value = "";
        transmutedOutput.value = "";
      }
    });
  }
}

// In-memory store for per-student grade detail entries
const gradeDetailEntries = {};

function getEntryKey(studentId, gradeNumber) {
  return `${studentId}__${gradeNumber}`;
}

function persistGradeDetailEntry(studentId, gradeNumber, entry) {
  const key = getEntryKey(studentId, gradeNumber);
  if (!gradeDetailEntries[key]) gradeDetailEntries[key] = [];
  gradeDetailEntries[key].push(entry);
  renderGradeDetailGrid(studentId, gradeNumber);
}

function renderGradeDetailGrid(studentId, gradeNumber) {
  const key = getEntryKey(studentId, gradeNumber);
  const entries = gradeDetailEntries[key] || [];

  const headerDates = document.getElementById(`gridHeaderDates${gradeNumber}`);
  const headerTypes = document.getElementById(`gridHeaderTypes${gradeNumber}`);
  const rowRaw = document.getElementById(`gridRowRaw${gradeNumber}`);
  const rowTrans = document.getElementById(`gridRowTransmuted${gradeNumber}`);

  if (!headerDates || !headerTypes || !rowRaw || !rowTrans) return;

  // Build columns
  headerDates.innerHTML = "";
  headerTypes.innerHTML = "";
  rowRaw.innerHTML = "";
  rowTrans.innerHTML = "";

  entries.forEach((e) => {
    const thDate = document.createElement("th");
    thDate.textContent = e.date || "--";
    headerDates.appendChild(thDate);

    const thType = document.createElement("th");
    thType.textContent = (e.component || "").replace(/^[a-z]/, (c) =>
      c.toUpperCase()
    );
    headerTypes.appendChild(thType);

    const tdRaw = document.createElement("td");
    tdRaw.textContent = `${e.raw} / ${e.total}`;
    rowRaw.appendChild(tdRaw);

    const tdTrans = document.createElement("td");
    const val =
      typeof e.transmuted === "number"
        ? e.transmuted
        : parseFloat(e.transmuted) || 0;
    tdTrans.textContent = `${val.toFixed(0)}%`;
    rowTrans.appendChild(tdTrans);
  });

  // Append final column (GX) with computed value
  const sums = entries.reduce(
    (acc, e) => acc + (parseFloat(e.transmuted) || 0),
    0
  );
  const count = entries.length || 1;
  const avg = sums / count;

  const thDateFinal = document.createElement("th");
  thDateFinal.textContent = "";
  headerDates.appendChild(thDateFinal);

  const thTypeFinal = document.createElement("th");
  thTypeFinal.textContent = `G${gradeNumber}`;
  headerTypes.appendChild(thTypeFinal);

  const tdRawFinal = document.createElement("td");
  tdRawFinal.textContent = "";
  rowRaw.appendChild(tdRawFinal);

  const tdTransFinal = document.createElement("td");
  tdTransFinal.textContent = `${avg.toFixed(2)}%`;
  rowTrans.appendChild(tdTransFinal);

  // Update student's grade breakdown
  const student = studentGradeData[studentId];
  if (student) {
    const breakdown = student.gradeBreakdown || {
      grade1: 0,
      grade2: 0,
      grade3: 0,
      grade4: 0,
      finalGrade: 0,
      remarks: "INCOMPLETE",
    };
    switch (String(gradeNumber)) {
      case "1":
        breakdown.grade1 = avg;
        break;
      case "2":
        breakdown.grade2 = avg;
        break;
      case "3":
        breakdown.grade3 = avg;
        break;
      case "4":
        breakdown.grade4 = avg;
        break;
    }
    breakdown.finalGrade =
      (breakdown.grade1 +
        breakdown.grade2 +
        breakdown.grade3 +
        breakdown.grade4) /
      4;
    student.gradeBreakdown = breakdown;
    renderGradeBreakdownTable(student);
  }
}

// Load saved grade details from DB and hydrate in-memory grid
function loadGradeDetailsFromDb(studentId, gradeNumber) {
  return fetch(
    `apis/grade_details.php?action=list&student_number=${encodeURIComponent(
      studentId
    )}&grade_number=${encodeURIComponent(gradeNumber)}`
  )
    .then((r) => r.json())
    .then((json) => {
      if (!json || !json.success) return;
      const key = getEntryKey(studentId, gradeNumber);
      gradeDetailEntries[key] = (json.data || []).map((row) => ({
        id: row.id,
        component: row.component || "quiz",
        date: row.date_given || "",
        raw: parseInt(row.raw_score || 0, 10),
        total: parseInt(row.total_items || 0, 10),
        transmuted: parseFloat(row.transmuted || 0),
      }));
    })
    .catch((err) => console.warn("Failed to load grade details", err));
}

function computeGridFinal(studentId, gradeNumber) {
  const key = getEntryKey(studentId, gradeNumber);
  const entries = gradeDetailEntries[key] || [];
  if (entries.length === 0) return 0;
  const sum = entries.reduce(
    (acc, e) => acc + (parseFloat(e.transmuted) || 0),
    0
  );
  const avg = sum / entries.length;
  return avg;
}

function deleteGradeColumnByIndex(studentId, gradeNumber, index) {
  const key = getEntryKey(studentId, gradeNumber);
  const entries = gradeDetailEntries[key] || [];
  if (index < 0 || index >= entries.length) return;
  entries.splice(index, 1);
  gradeDetailEntries[key] = entries;
  renderGradeDetailGrid(studentId, gradeNumber);

  const student = studentGradeData[studentId];
  if (student) {
    const breakdown = student.gradeBreakdown || {
      grade1: 0,
      grade2: 0,
      grade3: 0,
      grade4: 0,
      finalGrade: 0,
      remarks: "INCOMPLETE",
    };
    const newVal = computeGridFinal(studentId, gradeNumber);
    switch (String(gradeNumber)) {
      case "1":
        breakdown.grade1 = newVal;
        break;
      case "2":
        breakdown.grade2 = newVal;
        break;
      case "3":
        breakdown.grade3 = newVal;
        break;
      case "4":
        breakdown.grade4 = newVal;
        break;
    }
    breakdown.finalGrade =
      (breakdown.grade1 +
        breakdown.grade2 +
        breakdown.grade3 +
        breakdown.grade4) /
      4;
    breakdown.remarks =
      breakdown.finalGrade >= 75
        ? "PASSED"
        : breakdown.finalGrade > 0
        ? "FAILED"
        : "INCOMPLETE";
    student.gradeBreakdown = breakdown;
    renderGradeBreakdownTable(student);
  }
}

// ==================== Enhanced Grade Management JavaScript ====================
// Grade Management Class
class GradeManagement {
  constructor() {
    this.currentAssessment = null;
    this.students = [];
    this.assessmentTypes = [];
    this.transmutationData = {
      100: {},
      95: {},
    };
    this.autoSaveInterval = null;
    this.unsavedChanges = false;

    this.init();
  }

  async init() {
    try {
      await this.loadInitialData();
      this.setupEventListeners();
      this.setupAutoSave();
      console.log("Grade Management initialized successfully");
    } catch (error) {
      console.error("Error initializing Grade Management:", error);
      this.showNotification("Error initializing system", "error");
    }
  }

  async loadInitialData() {
    // Load students
    const studentsResponse = await fetch(
      "grade_management.php?action=get_students"
    );
    this.students = await studentsResponse.json();

    // Load assessment types
    const typesResponse = await fetch(
      "grade_management.php?action=get_assessment_types"
    );
    this.assessmentTypes = await typesResponse.json();

    // Load transmutation data for common item counts
    await this.loadTransmutationData();
  }

  async loadTransmutationData() {
    // Load common transmutation data for faster lookup
    const commonItemCounts = [
      5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 60, 70, 80, 90, 100,
    ];

    for (let items of commonItemCounts) {
      try {
        // Load 100-point scale
        const response100 = await fetch(
          `grade_management.php?action=get_transmutation&scale=100&items=${items}`
        );
        if (response100.ok) {
          const data100 = await response100.json();
          this.transmutationData[100][items] = data100;
        }

        // Load 95-point scale
        const response95 = await fetch(
          `grade_management.php?action=get_transmutation&scale=95&items=${items}`
        );
        if (response95.ok) {
          const data95 = await response95.json();
          this.transmutationData[95][items] = data95;
        }
      } catch (error) {
        console.warn(
          `Failed to load transmutation data for ${items} items:`,
          error
        );
      }
    }
  }

  setupEventListeners() {
    // Assessment form submission
    const assessmentForm = document.getElementById("assessmentForm");
    if (assessmentForm) {
      assessmentForm.addEventListener("submit", (e) =>
        this.handleAssessmentSubmit(e)
      );
    }

    // Assessment type change
    const assessmentType = document.getElementById("assessmentType");
    if (assessmentType) {
      assessmentType.addEventListener("change", () =>
        this.updateScaleDisplay()
      );
    }

    // Total items change
    const totalItems = document.getElementById("totalItems");
    if (totalItems) {
      totalItems.addEventListener("change", () => this.validateTotalItems());
    }

    // Save shortcuts
    document.addEventListener("keydown", (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        this.saveAllGrades();
      }
    });

    // Warn before leaving with unsaved changes
    window.addEventListener("beforeunload", (e) => {
      if (this.unsavedChanges) {
        e.preventDefault();
        e.returnValue = "";
      }
    });
  }

  setupAutoSave() {
    // Auto-save every 30 seconds if there are unsaved changes
    this.autoSaveInterval = setInterval(() => {
      if (this.unsavedChanges && this.currentAssessment) {
        this.autoSaveGrades();
      }
    }, 30000);
  }

  updateScaleDisplay() {
    const typeSelect = document.getElementById("assessmentType");
    const scaleDisplay = document.getElementById("scaleDisplay");

    if (!typeSelect || !scaleDisplay) return;

    const selectedType = this.assessmentTypes.find(
      (type) => type.id == typeSelect.value
    );

    if (selectedType) {
      // Performance Task (category_id 2) uses 95-point scale
      const scale = selectedType.category_id == 2 ? 95 : 100;
      scaleDisplay.textContent = `${scale}-point`;
      scaleDisplay.className = scale === 95 ? "text-warning" : "text-info";
    }
  }

  validateTotalItems() {
    const totalItemsInput = document.getElementById("totalItems");
    if (!totalItemsInput) return;

    const value = parseInt(totalItemsInput.value);

    if (value < 1 || value > 100) {
      totalItemsInput.setCustomValidity(
        "Total items must be between 1 and 100"
      );
      this.showNotification("Total items must be between 1 and 100", "warning");
    } else {
      totalItemsInput.setCustomValidity("");
    }
  }

  async handleAssessmentSubmit(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    formData.append("action", "create_assessment");
    formData.append("quarter", 1); // Default to quarter 1

    try {
      this.showLoadingState(true);

      const response = await fetch("grade_management.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.currentAssessment = {
          id: result.assessment_id,
          name: formData.get("assessment_name"),
          type_id: parseInt(formData.get("assessment_type_id")),
          total_items: parseInt(formData.get("total_items")),
          date: formData.get("date_given"),
          scale: this.getScaleForType(
            parseInt(formData.get("assessment_type_id"))
          ),
        };

        this.showCurrentAssessmentInfo();
        this.generateGradeTable();
        this.showNotification("Assessment created successfully!", "success");

        // Enable grade input
        this.enableGradeInput(true);
      } else {
        this.showNotification(
          result.message || "Error creating assessment",
          "error"
        );
      }
    } catch (error) {
      console.error("Error creating assessment:", error);
      this.showNotification("Network error occurred", "error");
    } finally {
      this.showLoadingState(false);
    }
  }

  getScaleForType(typeId) {
    const type = this.assessmentTypes.find((t) => t.id === typeId);
    return type && type.category_id === 2 ? 95 : 100; // Performance Task uses 95-point scale
  }

  showCurrentAssessmentInfo() {
    const currentAssessmentDiv = document.getElementById("currentAssessment");
    const assessmentNameSpan = document.getElementById("currentAssessmentName");
    const totalItemsSpan = document.getElementById("currentTotalItems");
    const scaleSpan = document.getElementById("currentScale");

    if (currentAssessmentDiv && this.currentAssessment) {
      assessmentNameSpan.textContent = this.currentAssessment.name;
      totalItemsSpan.textContent = this.currentAssessment.total_items;
      scaleSpan.textContent = this.currentAssessment.scale + "-point";
      currentAssessmentDiv.style.display = "block";
    }
  }

  generateGradeTable() {
    const tbody = document.getElementById("gradeTableBody");
    if (!tbody || !this.currentAssessment) return;

    tbody.innerHTML = "";

    this.students.forEach((student, index) => {
      const row = this.createStudentRow(student, index);
      tbody.appendChild(row);
    });

    // Show statistics and action buttons
    this.showAssessmentControls(true);
    this.updateStatistics();
  }

  createStudentRow(student, index) {
    const row = document.createElement("tr");
    row.className = "grade-row";
    row.setAttribute("data-student-id", student.id);

    row.innerHTML = `
            <td class="student-name-cell">
                <div class="d-flex align-items-center">
                    <div class="student-avatar me-2">
                        <i class="fas fa-user-circle text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <strong>${student.last_name}, ${student.first_name} ${
      student.middle_name || ""
    }</strong><br>
                        <small class="text-muted">${
                          student.student_number
                        }</small>
                    </div>
                </div>
            </td>
            <td class="grade-cell">
                <div class="input-group input-group-sm">
                    <input type="number" 
                           class="form-control grade-input text-center" 
                           id="raw_${student.id}"
                           min="0" 
                           max="${this.currentAssessment.total_items}"
                           placeholder="0"
                           data-student-id="${student.id}"
                           autocomplete="off">
                    <span class="input-group-text">/${
                      this.currentAssessment.total_items
                    }</span>
                </div>
            </td>
            <td id="transmuted_${student.id}" class="transmuted-cell">
                <div class="transmuted-display">--</div>
            </td>
            <td id="status_${student.id}" class="status-cell">
                <span class="badge bg-secondary">Not Graded</span>
            </td>
        `;

    // Add event listeners for this row
    const rawInput = row.querySelector(`#raw_${student.id}`);
    if (rawInput) {
      rawInput.addEventListener("input", () =>
        this.handleGradeInput(student.id)
      );
      rawInput.addEventListener("blur", () =>
        this.validateGradeInput(student.id)
      );
      rawInput.addEventListener("keypress", (e) =>
        this.handleKeyPress(e, student.id, index)
      );
    }

    return row;
  }

  handleKeyPress(event, studentId, index) {
    // Allow navigation with arrow keys
    if (event.key === "Enter" || event.key === "ArrowDown") {
      event.preventDefault();
      this.focusNextStudent(index);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      this.focusPreviousStudent(index);
    }
  }

  focusNextStudent(currentIndex) {
    const nextIndex = currentIndex + 1;
    if (nextIndex < this.students.length) {
      const nextInput = document.getElementById(
        `raw_${this.students[nextIndex].id}`
      );
      if (nextInput) {
        nextInput.focus();
        nextInput.select();
      }
    }
  }

  focusPreviousStudent(currentIndex) {
    const prevIndex = currentIndex - 1;
    if (prevIndex >= 0) {
      const prevInput = document.getElementById(
        `raw_${this.students[prevIndex].id}`
      );
      if (prevInput) {
        prevInput.focus();
        prevInput.select();
      }
    }
  }

  async handleGradeInput(studentId) {
    const rawInput = document.getElementById(`raw_${studentId}`);
    const transmutedCell = document.getElementById(`transmuted_${studentId}`);
    const statusCell = document.getElementById(`status_${studentId}`);

    if (!rawInput || !transmutedCell || !statusCell) return;

    const rawScore = parseInt(rawInput.value) || 0;

    // Mark as having unsaved changes
    this.unsavedChanges = true;
    this.updateSaveButtonState();

    // Validate input
    if (rawScore < 0 || rawScore > this.currentAssessment.total_items) {
      this.showInvalidGrade(transmutedCell, statusCell);
      return;
    }

    if (rawScore === 0 && rawInput.value !== "0") {
      this.showEmptyGrade(transmutedCell, statusCell);
      return;
    }

    // Calculate transmuted grade
    try {
      const transmutedGrade = await this.calculateTransmutedGrade(rawScore);
      this.displayTransmutedGrade(
        transmutedCell,
        statusCell,
        transmutedGrade,
        rawScore
      );
      this.updateStatistics();
    } catch (error) {
      console.error("Error calculating transmuted grade:", error);
      this.showErrorGrade(transmutedCell, statusCell);
    }
  }

  validateGradeInput(studentId) {
    const rawInput = document.getElementById(`raw_${studentId}`);
    if (!rawInput) return;

    const value = parseInt(rawInput.value);

    if (
      isNaN(value) ||
      value < 0 ||
      value > this.currentAssessment.total_items
    ) {
      rawInput.classList.add("is-invalid");
      rawInput.setCustomValidity(
        `Score must be between 0 and ${this.currentAssessment.total_items}`
      );
    } else {
      rawInput.classList.remove("is-invalid");
      rawInput.setCustomValidity("");
    }
  }

  async calculateTransmutedGrade(rawScore) {
    const totalItems = this.currentAssessment.total_items;
    const scale = this.currentAssessment.scale;

    // Try to get from cached data first
    if (this.transmutationData[scale][totalItems]) {
      const cached = this.transmutationData[scale][totalItems];
      if (cached[rawScore] !== undefined) {
        return cached[rawScore];
      }
    }

    // Fallback to server calculation
    const formData = new FormData();
    formData.append("action", "get_transmuted_grade");
    formData.append("raw_score", rawScore);
    formData.append("total_items", totalItems);
    formData.append("scale", scale);

    const response = await fetch("grade_management.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      return parseFloat(result.transmuted_grade);
    } else {
      throw new Error(result.message || "Failed to calculate transmuted grade");
    }
  }

  showInvalidGrade(transmutedCell, statusCell) {
    transmutedCell.innerHTML =
      '<div class="transmuted-display grade-failing">Invalid</div>';
    statusCell.innerHTML = '<span class="badge bg-danger">Invalid Score</span>';
  }

  showEmptyGrade(transmutedCell, statusCell) {
    transmutedCell.innerHTML = '<div class="transmuted-display">--</div>';
    statusCell.innerHTML = '<span class="badge bg-secondary">Not Graded</span>';
  }

  showErrorGrade(transmutedCell, statusCell) {
    transmutedCell.innerHTML =
      '<div class="transmuted-display grade-failing">Error</div>';
    statusCell.innerHTML =
      '<span class="badge bg-danger">Calculation Error</span>';
  }

  displayTransmutedGrade(
    transmutedCell,
    statusCell,
    transmutedGrade,
    rawScore
  ) {
    // Determine grade classification
    let gradeClass = "grade-failing";
    let statusClass = "bg-danger";
    let statusText = "Failed";
    let gradeIcon = "✗";

    if (transmutedGrade >= 90) {
      gradeClass = "grade-excellent";
      statusClass = "bg-success";
      statusText = "Excellent";
      gradeIcon = "★";
    } else if (transmutedGrade >= 85) {
      gradeClass = "grade-good";
      statusClass = "bg-info";
      statusText = "Very Good";
      gradeIcon = "✓";
    } else if (transmutedGrade >= 80) {
      gradeClass = "grade-good";
      statusClass = "bg-info";
      statusText = "Good";
      gradeIcon = "✓";
    } else if (transmutedGrade >= 75) {
      gradeClass = "grade-passing";
      statusClass = "bg-warning text-dark";
      statusText = "Passed";
      gradeIcon = "✓";
    }

    transmutedCell.innerHTML = `
            <div class="transmuted-display ${gradeClass}">
                <span class="grade-icon">${gradeIcon}</span>
                ${transmutedGrade.toFixed(1)}
                <small class="d-block text-muted">${(
                  (rawScore / this.currentAssessment.total_items) *
                  100
                ).toFixed(1)}%</small>
            </div>
        `;

    statusCell.innerHTML = `<span class="badge ${statusClass}">${statusText}</span>`;
  }

  updateStatistics() {
    const stats = this.calculateStatistics();

    document.getElementById("totalStudents").textContent = stats.totalStudents;
    document.getElementById("averageRaw").textContent =
      stats.averageRaw.toFixed(1);
    document.getElementById("averageTransmuted").textContent =
      stats.averageTransmuted.toFixed(1);
    document.getElementById("passingRate").textContent =
      stats.passingRate.toFixed(1) + "%";
    document.getElementById("completionRate").textContent =
      stats.completionRate.toFixed(1) + "%";

    // Update progress bars if they exist
    this.updateProgressBars(stats);
  }

  calculateStatistics() {
    let totalStudents = this.students.length;
    let gradedStudents = 0;
    let totalRaw = 0;
    let totalTransmuted = 0;
    let passingStudents = 0;

    this.students.forEach((student) => {
      const rawInput = document.getElementById(`raw_${student.id}`);
      const transmutedDiv = document.querySelector(
        `#transmuted_${student.id} .transmuted-display`
      );

      if (
        rawInput &&
        rawInput.value &&
        transmutedDiv &&
        !transmutedDiv.textContent.includes("--") &&
        !transmutedDiv.textContent.includes("Invalid") &&
        !transmutedDiv.textContent.includes("Error")
      ) {
        gradedStudents++;
        totalRaw += parseInt(rawInput.value);

        const transmutedText = transmutedDiv.textContent.trim();
        const transmutedScore = parseFloat(transmutedText.split("\n")[0]);

        if (!isNaN(transmutedScore)) {
          totalTransmuted += transmutedScore;
          if (transmutedScore >= 75) {
            passingStudents++;
          }
        }
      }
    });

    return {
      totalStudents,
      gradedStudents,
      averageRaw: gradedStudents > 0 ? totalRaw / gradedStudents : 0,
      averageTransmuted:
        gradedStudents > 0 ? totalTransmuted / gradedStudents : 0,
      passingRate:
        gradedStudents > 0 ? (passingStudents / gradedStudents) * 100 : 0,
      completionRate:
        totalStudents > 0 ? (gradedStudents / totalStudents) * 100 : 0,
    };
  }

  updateProgressBars(stats) {
    // Update completion progress bar
    const completionBar = document.getElementById("completionProgressBar");
    if (completionBar) {
      completionBar.style.width = stats.completionRate + "%";
      completionBar.setAttribute("aria-valuenow", stats.completionRate);
    }

    // Update passing rate progress bar
    const passingBar = document.getElementById("passingProgressBar");
    if (passingBar) {
      passingBar.style.width = stats.passingRate + "%";
      passingBar.setAttribute("aria-valuenow", stats.passingRate);

      // Change color based on passing rate
      passingBar.className = "progress-bar";
      if (stats.passingRate >= 90) {
        passingBar.classList.add("bg-success");
      } else if (stats.passingRate >= 75) {
        passingBar.classList.add("bg-info");
      } else if (stats.passingRate >= 60) {
        passingBar.classList.add("bg-warning");
      } else {
        passingBar.classList.add("bg-danger");
      }
    }
  }

  async saveAllGrades() {
    if (!this.currentAssessment) {
      this.showNotification("No assessment selected", "warning");
      return;
    }

    const grades = this.collectGradeData();

    if (grades.length === 0) {
      this.showNotification("No grades to save", "warning");
      return;
    }

    try {
      this.showLoadingState(true);

      const formData = new FormData();
      formData.append("action", "save_grades");
      formData.append("grades", JSON.stringify(grades));

      const response = await fetch("grade_management.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.unsavedChanges = false;
        this.updateSaveButtonState();
        this.showNotification(
          `Successfully saved ${grades.length} grades!`,
          "success"
        );

        // Mark saved grades visually
        this.markGradesAsSaved(grades);
      } else {
        this.showNotification(result.message || "Error saving grades", "error");
      }
    } catch (error) {
      console.error("Error saving grades:", error);
      this.showNotification("Network error occurred while saving", "error");
    } finally {
      this.showLoadingState(false);
    }
  }

  collectGradeData() {
    const grades = [];

    this.students.forEach((student) => {
      const rawInput = document.getElementById(`raw_${student.id}`);
      const transmutedDiv = document.querySelector(
        `#transmuted_${student.id} .transmuted-display`
      );

      if (
        rawInput &&
        rawInput.value &&
        transmutedDiv &&
        !transmutedDiv.textContent.includes("--") &&
        !transmutedDiv.textContent.includes("Invalid") &&
        !transmutedDiv.textContent.includes("Error")
      ) {
        const transmutedText = transmutedDiv.textContent.trim();
        const transmutedScore = parseFloat(transmutedText.split("\n")[0]);

        if (!isNaN(transmutedScore)) {
          grades.push({
            student_id: student.id,
            assessment_id: this.currentAssessment.id,
            raw_score: parseInt(rawInput.value),
            transmuted_grade: transmutedScore,
          });
        }
      }
    });

    return grades;
  }

  markGradesAsSaved(grades) {
    grades.forEach((grade) => {
      const row = document.querySelector(
        `[data-student-id="${grade.student_id}"]`
      );
      if (row) {
        row.classList.add("grade-saved");

        // Add a small saved indicator
        const savedIndicator = document.createElement("span");
        savedIndicator.className = "badge bg-success ms-1";
        savedIndicator.innerHTML = '<i class="fas fa-check"></i>';
        savedIndicator.title = "Saved";

        const statusCell = row.querySelector(".status-cell .badge");
        if (statusCell && !statusCell.nextElementSibling) {
          statusCell.parentNode.appendChild(savedIndicator);
        }
      }
    });
  }

  async autoSaveGrades() {
    try {
      await this.saveAllGrades();
      console.log("Auto-save completed");
    } catch (error) {
      console.error("Auto-save failed:", error);
    }
  }

  updateSaveButtonState() {
    const saveButton = document.querySelector('[onclick="saveAllGrades()"]');
    if (saveButton) {
      if (this.unsavedChanges) {
        saveButton.classList.remove("btn-success");
        saveButton.classList.add("btn-warning");
        saveButton.innerHTML = '<i class="fas fa-save me-1"></i>Save Changes *';
      } else {
        saveButton.classList.remove("btn-warning");
        saveButton.classList.add("btn-success");
        saveButton.innerHTML =
          '<i class="fas fa-save me-1"></i>Save All Grades';
      }
    }
  }

  showAssessmentControls(show) {
    const statsDiv = document.getElementById("assessmentStats");
    const actionsDiv = document.getElementById("actionButtons");

    if (statsDiv) statsDiv.style.display = show ? "grid" : "none";
    if (actionsDiv) actionsDiv.style.display = show ? "flex" : "none";
  }

  enableGradeInput(enable) {
    const gradeInputs = document.querySelectorAll(".grade-input");
    gradeInputs.forEach((input) => {
      input.disabled = !enable;
    });
  }

  showLoadingState(loading) {
    const submitBtn = document.querySelector(
      '#assessmentForm button[type="submit"]'
    );
    const saveBtn = document.querySelector('[onclick="saveAllGrades()"]');

    if (loading) {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<i class="fas fa-spinner fa-spin me-1"></i>Creating...';
      }
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML =
          '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
      }
    } else {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-plus me-1"></i>Create';
      }
      if (saveBtn) {
        saveBtn.disabled = false;
        this.updateSaveButtonState();
      }
    }
  }

  showNotification(message, type = "info") {
    // Create notification element
    const notification = document.createElement("div");
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText =
      "top: 20px; right: 20px; z-index: 9999; min-width: 300px;";

    notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);
  }

  resetForm() {
    // Reset form
    const form = document.getElementById("assessmentForm");
    if (form) form.reset();

    // Clear current assessment
    this.currentAssessment = null;
    this.unsavedChanges = false;

    // Hide controls
    this.showAssessmentControls(false);

    // Clear table
    const tbody = document.getElementById("gradeTableBody");
    if (tbody) tbody.innerHTML = "";

    // Hide current assessment info
    const currentAssessmentDiv = document.getElementById("currentAssessment");
    if (currentAssessmentDiv) currentAssessmentDiv.style.display = "none";

    // Reset button states
    this.updateSaveButtonState();
    this.enableGradeInput(false);

    this.showNotification("Form reset successfully", "info");
  }

  exportGrades() {
    if (!this.currentAssessment) {
      this.showNotification("No assessment to export", "warning");
      return;
    }

    const grades = this.collectGradeData();
    if (grades.length === 0) {
      this.showNotification("No grades to export", "warning");
      return;
    }

    // Create CSV content
    let csvContent =
      "Student Number,Last Name,First Name,Middle Name,Raw Score,Transmuted Grade,Status\n";

    grades.forEach((grade) => {
      const student = this.students.find((s) => s.id === grade.student_id);
      if (student) {
        const status = grade.transmuted_grade >= 75 ? "PASSED" : "FAILED";
        csvContent += `"${student.student_number}","${student.last_name}","${
          student.first_name
        }","${student.middle_name || ""}","${grade.raw_score}","${
          grade.transmuted_grade
        }","${status}"\n`;
      }
    });

    // Download CSV
    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${this.currentAssessment.name}_grades_${
      new Date().toISOString().split("T")[0]
    }.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    this.showNotification("Grades exported successfully!", "success");
  }

  destroy() {
    // Clean up interval
    if (this.autoSaveInterval) {
      clearInterval(this.autoSaveInterval);
    }
  }
}

// Initialize Grade Management when DOM is loaded
let gradeManagement;

// ========== ANIMATION FUNCTIONS ==========

(function () {
  function animateNumber(el, target, duration) {
    const start = 0;
    const startTime = performance.now();
    function step(now) {
      const p = Math.min(1, (now - startTime) / duration);
      const val = start + (target - start) * p;
      el.textContent = val.toFixed(1) + "%";
      if (p < 1) requestAnimationFrame(step);
      else el.closest(".final-grade-cell")?.classList.remove("animating");
    }
    requestAnimationFrame(step);
  }

  function runFinalGradeCountUp(scope) {
    const root = scope || document;
    const cells = root.querySelectorAll(
      "#gradesTable .final-grade-cell strong"
    );
    cells.forEach((s) => {
      const wrap = s.closest(".final-grade-cell");
      if (!wrap) return;
      const target = parseFloat((s.textContent || "0").replace("%", "")) || 0;
      s.textContent = "0.0%";
      wrap.classList.add("animating");
      animateNumber(s, target, 1100);
    });
  }

  // Initial run after page scripts settle
  window.addEventListener("load", function () {
    // Build cache from table, then sync aggregates from DB so values persist across refreshes
    initializeStudentGradeDataFromTable();
    refreshAllStudentsAggregates().then(() => {
      setTimeout(runFinalGradeCountUp, 150);
    });
  });

  // Re-run when the Grades tab is activated
  document.addEventListener("click", function (e) {
    const tab = e.target.closest(".tab");
    if (tab && tab.getAttribute("data-tab") === "grades") {
      // Ensure cache is built and aggregates are fresh whenever Grades tab is opened
      initializeStudentGradeDataFromTable();
      refreshAllStudentsAggregates().then(() => {
        setTimeout(runFinalGradeCountUp, 150);
      });
    }
  });
})();

// Search functionality removed - no longer needed

// Added: Career Analytics initializer (copied from student dashboard)
// Copied from admin_dashboard: Initialize Career Analytics for instructors (mirrors admin logic and behavior)
function initializeCareerAnalytics() {
  const courseSelect = document.getElementById("analyticsCourseSelect");
  const info = document.getElementById("analyticsInfo");
  const trendCtx = document.getElementById("analyticsTrendChart")?.getContext("2d");
  const forecastCtx = document.getElementById("analyticsForecastChart")?.getContext("2d");

  if (!courseSelect || !trendCtx || !forecastCtx) return;

  let trendChart = null;
  let forecastChart = null;
  let dataset = null;

  const chartConfig = {
    responsive: true,
    maintainAspectRatio: false,
    aspectRatio: 2,
    resizeDelay: 200,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: { boxWidth: 12, padding: 15, font: { size: 12 } }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } }
    },
    interaction: { intersect: false, mode: 'index' },
    elements: { point: { radius: 3, hoverRadius: 5 } }
  };

  async function loadCSV() {
    try {
      const res = await fetch("data/Graduates_.csv", { cache: "no-store" });
      if (!res.ok) throw new Error("CSV not found");
      const text = await res.text();
      dataset = parseCSV(text);
      populateCourses(dataset);
      renderForSelection();
    } catch (e) {
      console.warn("Career analytics CSV not available. Place it at htdocs/data/Graduates_.csv");
      if (info) info.textContent = "Upload data/Graduates_.csv to enable analytics.";
    }
  }

  function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter((l) => l.trim().length);
    if (lines.length < 2) return { rows: [], courses: [], years: [] };

    const header = lines[0].split(",").map((h) => h.trim());
    const col = (name) => header.findIndex((h) => h.toLowerCase() === name);
    const idxYear = col("year");
    const idxCourse = col("course_id");
    const idxBatch = col("batch");
    const idxCount = col("student_count");

    const rows = [];
    const coursesSet = new Set();
    const yearsSet = new Set();

    for (let i = 1; i < lines.length; i++) {
      const parts = safeSplitCSV(lines[i], header.length);
      if (!parts || parts.length < header.length) continue;

      const year = Number(parts[idxYear]);
      const course = String(parts[idxCourse]);
      const batch = Number(parts[idxBatch]);
      const count = Number(parts[idxCount]);

      if (!Number.isFinite(year) || !course) continue;

      rows.push({ year, course_id: course, batch, student_count: Number.isFinite(count) ? count : 0 });
      coursesSet.add(course);
      yearsSet.add(year);
    }

    return { rows, courses: Array.from(coursesSet).sort(), years: Array.from(yearsSet).sort((a, b) => a - b) };
  }

  function safeSplitCSV(line, minCols) {
    const result = [];
    let current = "";
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (ch === '"') {
        if (inQuotes && line[i + 1] === '"') { current += '"'; i++; } else { inQuotes = !inQuotes; }
      } else if (ch === "," && !inQuotes) {
        result.push(current);
        current = "";
      } else {
        current += ch;
      }
    }
    result.push(current);

    return result.length >= minCols ? result.map((s) => s.trim()) : null;
  }

  function populateCourses(data) {
    courseSelect.innerHTML = `<option value="__ALL__">All Courses</option>`;
    data.courses.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = c;
      opt.textContent = c;
      courseSelect.appendChild(opt);
    });
  }

  function aggregate(data, selectedCourse) {
    const filtered = selectedCourse === "__ALL__" ? data.rows : data.rows.filter((r) => r.course_id === selectedCourse);
    const byYear = new Map();
    filtered.forEach((r) => { byYear.set(r.year, (byYear.get(r.year) || 0) + (r.student_count || 0)); });
    const years = Array.from(byYear.keys()).sort((a, b) => a - b);
    const totals = years.map((y) => byYear.get(y));
    return { years, totals };
  }

  function simpleForecast(years, totals, nextYear) {
    if (years.length < 2) return { predicted: totals[totals.length - 1] || 0, acc: null };
    const x = years.map((y) => y);
    const y = totals;
    const n = x.length;
    const sumX = x.reduce((s, v) => s + v, 0);
    const sumY = y.reduce((s, v) => s + v, 0);
    const sumXY = x.reduce((s, v, i) => s + v * y[i], 0);
    const sumXX = x.reduce((s, v) => s + v * v, 0);
    const denom = n * sumXX - sumX * sumX;
    const a = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0;
    const b = (sumY - a * sumX) / n;
    const predicted = Math.max(0, Math.round(a * nextYear + b));
    let acc = null;
    if (n >= 3) {
      const lastPred = Math.max(0, Math.round(a * x[n - 1] + b));
      const lastActual = y[n - 1];
      const mae = Math.abs(lastPred - lastActual);
      const base = Math.max(1, Math.abs(lastActual));
      acc = Math.max(0, 100 - (mae / base) * 100);
    }
    return { predicted, acc };
  }

  function renderCharts(selectedCourse) {
    // For display, use filtered data
    const { years, totals } = aggregate(dataset, selectedCourse);
    
    // For prediction, always use the full historical data for the selected course (or all courses if "__ALL__")
    // This ensures the 2026 prediction is always calculated from sufficient historical data
    const predictionCourse = selectedCourse; // Keep the same course for prediction
    const { years: predictionYears, totals: predictionTotals } = aggregate(dataset, predictionCourse);
    const nextYear = (predictionYears[predictionYears.length - 1] || 2025) + 1;
    const { predicted, acc } = simpleForecast(predictionYears, predictionTotals, 2026);

    if (trendChart) { trendChart.destroy(); trendChart = null; }
    if (forecastChart) { forecastChart.destroy(); forecastChart = null; }

    const trendLabels = years.map((y) => String(y)).concat(["2026"]);
    const actualData = totals.concat([null]);
    const predictionData = Array(Math.max(0, years.length - 1)).fill(null).concat([totals[totals.length - 1] || 0, predicted]);

    const trendData = {
      labels: trendLabels,
      datasets: [
        { label: "Total Students", data: actualData, borderColor: "#1f77b4", backgroundColor: "rgba(31,119,180,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 },
        { label: "2026 Prediction", data: predictionData, borderColor: "#ff7f0e", backgroundColor: "rgba(255,127,14,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, borderDash: [6, 4] }
      ]
    };

    trendChart = new Chart(trendCtx, { type: "line", data: trendData, options: chartConfig });

    const forecastData = {
      labels: [String((years[years.length - 1] || 2025)), "2026"],
      datasets: [ { label: "Enrollment", data: [totals[totals.length - 1] || 0, predicted], backgroundColor: ["#1f77b4", "#ff7f0e"], borderColor: ["#1f77b4", "#ff7f0e"], borderWidth: 1 } ]
    };

    forecastChart = new Chart(forecastCtx, { type: "bar", data: forecastData, options: chartConfig });

    if (info) {
      const courseText = selectedCourse === "__ALL__" ? "All Courses" : selectedCourse;
      info.textContent = `Forecast for 2026 • ${courseText}${acc ? ` • Est. accuracy ~${acc.toFixed(1)}%` : ""}`;
    }
  }

  function renderForSelection() {
    const selected = courseSelect.value || "__ALL__";
    if (!dataset || !dataset.rows.length) return;
    renderCharts(selected);
  }

  function handleResize() {
    if (trendChart) trendChart.resize();
    if (forecastChart) forecastChart.resize();
  }

  let resizeTimeout;
  window.addEventListener('resize', () => { clearTimeout(resizeTimeout); resizeTimeout = setTimeout(handleResize, 300); });
  courseSelect.addEventListener("change", renderForSelection);
  loadCSV();
}

// Copied from admin_dashboard: Initialize Admin-style Career Analytics (exact IDs/markup copied to instructors_dashboard)
function initializeCareerAnalyticsAdmin() {
  const courseSelect = document.getElementById("adminAnalyticsCourseSelect");
  const info = document.getElementById("adminAnalyticsInfo");
  const trendCtx = document.getElementById("adminAnalyticsTrendChart")?.getContext("2d");
  const forecastCtx = document.getElementById("adminAnalyticsForecastChart")?.getContext("2d");

  if (!courseSelect || !trendCtx || !forecastCtx) return;

  let trendChart = null;
  let forecastChart = null;
  let dataset = null;

  const chartConfig = {
    responsive: true,
    maintainAspectRatio: false,
    aspectRatio: 2,
    resizeDelay: 200,
    plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 15, font: { size: 12 } } } },
    scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } } },
    interaction: { intersect: false, mode: 'index' },
    elements: { point: { radius: 3, hoverRadius: 5 } }
  };

  async function loadCSV() {
    try {
      const res = await fetch("data/Graduates_.csv", { cache: "no-store" });
      if (!res.ok) throw new Error("CSV not found");
      const text = await res.text();
      dataset = parseCSV(text);
      populateCourses(dataset);
      renderForSelection();
    } catch (_) {
      console.warn("Admin analytics CSV not available. Place it at htdocs/data/Graduates_.csv");
      if (info) info.textContent = "Upload data/Graduates_.csv to enable analytics.";
    }
  }

  function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter((l) => l.trim().length);
    if (lines.length < 2) return { rows: [], courses: [], years: [] };
    const header = lines[0].split(",").map((h) => h.trim());
    const col = (name) => header.findIndex((h) => h.toLowerCase() === name);
    const idxYear = col("year"), idxCourse = col("course_id"), idxBatch = col("batch"), idxCount = col("student_count");
    const rows = [], coursesSet = new Set(), yearsSet = new Set();
    for (let i = 1; i < lines.length; i++) {
      const parts = safeSplitCSV(lines[i], header.length);
      if (!parts || parts.length < header.length) continue;
      const year = Number(parts[idxYear]);
      const course = String(parts[idxCourse]);
      const batch = Number(parts[idxBatch]);
      const count = Number(parts[idxCount]);
      if (!Number.isFinite(year) || !course) continue;
      rows.push({ year, course_id: course, batch, student_count: Number.isFinite(count) ? count : 0 });
      coursesSet.add(course);
      yearsSet.add(year);
    }
    return { rows, courses: Array.from(coursesSet).sort(), years: Array.from(yearsSet).sort((a, b) => a - b) };
  }

  function safeSplitCSV(line, minCols) {
    const result = [];
    let current = "", inQuotes = false;
    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (ch === '"') { if (inQuotes && line[i + 1] === '"') { current += '"'; i++; } else { inQuotes = !inQuotes; } }
      else if (ch === "," && !inQuotes) { result.push(current); current = ""; }
      else { current += ch; }
    }
    result.push(current);
    return result.length >= minCols ? result.map((s) => s.trim()) : null;
  }

  function populateCourses(data) {
    courseSelect.innerHTML = `<option value="__ALL__">All Courses</option>`;
    data.courses.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = c; opt.textContent = c; courseSelect.appendChild(opt);
    });
  }

  function aggregate(data, selectedCourse) {
    const filtered = selectedCourse === "__ALL__" ? data.rows : data.rows.filter((r) => r.course_id === selectedCourse);
    const byYear = new Map();
    filtered.forEach((r) => { byYear.set(r.year, (byYear.get(r.year) || 0) + (r.student_count || 0)); });
    const years = Array.from(byYear.keys()).sort((a, b) => a - b);
    const totals = years.map((y) => byYear.get(y));
    return { years, totals };
  }

  function simpleForecast(years, totals, nextYear) {
    if (years.length < 2) return { predicted: totals[totals.length - 1] || 0, acc: null };
    const x = years.map((y) => y), y = totals, n = x.length;
    const sumX = x.reduce((s, v) => s + v, 0);
    const sumY = y.reduce((s, v) => s + v, 0);
    const sumXY = x.reduce((s, v, i) => s + v * y[i], 0);
    const sumXX = x.reduce((s, v) => s + v * v, 0);
    const denom = n * sumXX - sumX * sumX;
    const a = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0;
    const b = (sumY - a * sumX) / n;
    const predicted = Math.max(0, Math.round(a * nextYear + b));
    let acc = null;
    if (n >= 3) {
      const lastPred = Math.max(0, Math.round(a * x[n - 1] + b));
      const lastActual = y[n - 1];
      const mae = Math.abs(lastPred - lastActual);
      const base = Math.max(1, Math.abs(lastActual));
      acc = Math.max(0, 100 - (mae / base) * 100);
    }
    return { predicted, acc };
  }

  function renderCharts(selectedCourse) {
    const { years, totals } = aggregate(dataset, selectedCourse);
    const { predicted, acc } = simpleForecast(years, totals, 2026);
    if (trendChart) { trendChart.destroy(); trendChart = null; }
    if (forecastChart) { forecastChart.destroy(); forecastChart = null; }
    const trendLabels = years.map((y) => String(y)).concat(["2026"]);
    const actualData = totals.concat([null]);
    const predictionData = Array(Math.max(0, years.length - 1)).fill(null).concat([totals[totals.length - 1] || 0, predicted]);
    const trendData = {
      labels: trendLabels,
      datasets: [
        { label: "Total Students", data: actualData, borderColor: "#1f77b4", backgroundColor: "rgba(31,119,180,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 },
        { label: "2026 Prediction", data: predictionData, borderColor: "#ff7f0e", backgroundColor: "rgba(255,127,14,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, borderDash: [6, 4] }
      ]
    };
    trendChart = new Chart(trendCtx, { type: "line", data: trendData, options: chartConfig });
    const forecastData = { labels: [String((years[years.length - 1] || 2025)), "2026"], datasets: [{ label: "Enrollment", data: [totals[totals.length - 1] || 0, predicted], backgroundColor: ["#1f77b4", "#ff7f0e"], borderColor: ["#1f77b4", "#ff7f0e"], borderWidth: 1 }] };
    forecastChart = new Chart(forecastCtx, { type: "bar", data: forecastData, options: chartConfig });
    if (info) { const courseText = selectedCourse === "__ALL__" ? "All Courses" : selectedCourse; info.textContent = `Forecast for 2026 • ${courseText}${acc ? ` • Est. accuracy ~${acc.toFixed(1)}%` : ""}`; }
  }

  function renderForSelection() { const selected = courseSelect.value || "__ALL__"; if (!dataset || !dataset.rows.length) return; renderCharts(selected); }
  function handleResize() { if (trendChart) trendChart.resize(); if (forecastChart) forecastChart.resize(); }
  let resizeTimeout; window.addEventListener('resize', () => { clearTimeout(resizeTimeout); resizeTimeout = setTimeout(handleResize, 300); });
  courseSelect.addEventListener("change", renderForSelection);
  loadCSV();
}

// ========== MAIN INITIALIZATION ==========
function initializeDashboard() {
  initializeSidebar();
  initializeNavigation();
  initializeTheme();
  initializeLogout();
  initializeNotifications();
  initializeHeader();
  initializeGradeModal();
  initializeGradeDetailModal();
  // Search functionality removed - no longer needed
  // Copied from admin_dashboard: initialize Career Analytics (admin-style IDs) on instructors page
  initializeCareerAnalyticsAdmin();
  showSection("dashboard");
  // Wire refresh button for job recommendations (spin 1s and reapply filters)
  try {
    const refreshBtn = document.getElementById('instructorRefreshJobsBtn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function(){
        refreshBtn.classList.add('is-spinning');
        setTimeout(function(){ refreshBtn.classList.remove('is-spinning'); }, 1000);
        // Re-apply current filters if function exists
        if (typeof applyFilters === 'function') {
          try { applyFilters(); } catch(_) {}
        }
      });
    }
  } catch(_) {}
}

document.addEventListener("DOMContentLoaded", () => {
  console.log("Initializing dashboard...");

  // Initialize main dashboard
  initializeDashboard();
  
  // Initialize date validation
  initializeDateValidation();
  
  // Initialize delete confirmation modal
  initializeDeleteConfirmationModal();

  // Initialize Grade Management if on grade management page
  if (document.getElementById("assessmentForm")) {
    gradeManagement = new GradeManagement();
  }

  setTimeout(() => {
    initializeTodoList();
    // Initialize trainee tabs if trainee section is active
    if (globalState.activeSection === "trainee") {
      initializeTraineeRecordTabs();
    }
    
    // Auto-load quiz submissions filtered by instructor's course
    if (window.__instructorCourse && window.__instructorCourse.trim() !== '') {
      console.log('Auto-loading quiz submissions for instructor course:', window.__instructorCourse);
      loadQuizSubmissions(window.__instructorCourse);
    }
  }, 200);
});

// ========== GLOBAL FUNCTIONS ==========

// Make functions available globally for onclick handlers
window.showStudentDetails = showStudentDetails;
window.showAttendanceDetails = showAttendanceDetails;
window.editGrade = editGrade;
window.markAttendance = markAttendance;
window.openGradeDetail = openGradeDetail;

// Grade Management global functions
window.saveAllGrades = function () {
  if (gradeManagement) {
    gradeManagement.saveAllGrades();
  }
};

window.resetForm = function () {
  if (gradeManagement) {
    gradeManagement.resetForm();
  }
};

window.exportGrades = function () {
  if (gradeManagement) {
    gradeManagement.exportGrades();
  }
};

window.calculateQuarterlyGrades = function () {
  if (gradeManagement) {
    gradeManagement.showNotification(
      "Quarterly grade calculation feature coming soon!",
      "info"
    );
  }
};

// Debug functions
window.debugFilterState = function () {
  console.log("Current filter state:", globalState.currentFilters);
  console.log(
    "Students count:",
    globalState.students ? globalState.students.length : 0
  );
  console.log(
    "Filtered students count:",
    globalState.filteredStudents ? globalState.filteredStudents.length : 0
  );
};

// Debug function to test grade clicks
window.testGradeClicks = function () {
  console.log('Testing grade cell clicks...');
  const gradeSpans = document.querySelectorAll('.grade-cell[data-student-id]');
  console.log('Found grade spans:', gradeSpans.length);
  
  gradeSpans.forEach((span, index) => {
    console.log(`Span ${index}:`, {
      studentId: span.getAttribute('data-student-id'),
      gradeNumber: span.getAttribute('data-grade-number'),
      gradeValue: span.getAttribute('data-grade-value'),
      cursor: getComputedStyle(span).cursor,
      pointerEvents: getComputedStyle(span).pointerEvents,
      zIndex: getComputedStyle(span).zIndex
    });
  });
};

console.log("Instructor Dashboard JavaScript loaded successfully!");

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
          label: `Top Companies Hiring MMTVTC Graduates`,
          data: values,
          backgroundColor: 'rgba(135, 206, 250, 0.6)',
          borderColor: 'rgba(0, 71, 171, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
          legend: { display: true, position: 'top' },
          tooltip: {
            callbacks: {
              title: function(context) {
                return 'Company: ' + context[0].label;
              },
              label: function(context) {
                return 'MMTVTC Graduates: ' + context.parsed.y;
              }
            }
          }
        },
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
      const currentBatch = document.querySelector('.batch-tab.active').getAttribute('data-batch');
      loadAttendanceData(currentBatch);
      
      // Start auto-refresh when attendance tab is active
      startAttendanceAutoRefresh();
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
      console.log('Batch tab clicked:', batch);
      
      // Reload students for the selected batch
      const selectedCourse = document.querySelector('#selectedCourseName');
      if (selectedCourse && selectedCourse.textContent && selectedCourse.textContent !== 'Course Name') {
        console.log('Reloading students for course:', selectedCourse.textContent, 'batch:', batch);
        viewAttendanceForCourse(selectedCourse.textContent);
      } else {
        console.log('No course selected, just loading attendance data');
        loadAttendanceData(batch);
      }
    });
  });

  // Stop auto-refresh when switching to other tabs
  const otherTabs = document.querySelectorAll('.tab:not([data-tab="attendance"])');
  otherTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      stopAttendanceAutoRefresh();
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
  const currentBatch = document.querySelector('.batch-tab.active');
  if (currentBatch) {
    loadAttendanceData(currentBatch.getAttribute('data-batch'));
  }
  
  // Also load attendance data when the attendance tab is first shown
  const attendanceTabElement = document.querySelector('[data-tab="attendance"]');
  if (attendanceTabElement) {
    // Add a one-time event listener to load data when tab is first clicked
    const loadDataOnce = function() {
      const currentBatch = document.querySelector('.batch-tab.active');
      if (currentBatch) {
        loadAttendanceData(currentBatch.getAttribute('data-batch'));
      }
      attendanceTabElement.removeEventListener('click', loadDataOnce);
    };
    attendanceTabElement.addEventListener('click', loadDataOnce);
  }
}

function loadAttendanceData(batch) {
  console.log('Loading attendance data for batch:', batch);
  
  // Load attendance for today's date by default
  const today = new Date().toISOString().split('T')[0];
  loadAttendanceForDate(today);
}

function loadAttendanceForDate(date) {
  console.log('Loading attendance for date:', date);
  
  // Reset all attendance statuses first
  const statusElements = document.querySelectorAll('.attendance-status-inline .status-indicator');
  statusElements.forEach(element => {
    element.textContent = 'Not marked';
    element.className = 'status-indicator';
  });
  
  // Reset table row highlighting
  const tableRows = document.querySelectorAll('#attendanceTableBody tr');
  tableRows.forEach(row => {
    row.classList.remove('marked-present', 'marked-absent');
  });
  
  // Reset summary counters
  updateAttendanceSummary();
  
  // Show loading message
  console.log('Loading attendance data for date:', date);
  
  // Load attendance data from database for all students
  loadAttendanceFromDatabase(date);
}

function loadAttendanceFromDatabase(date) {
  console.log('Loading attendance from database for date:', date);
  
  // Get all student IDs from the table
  const studentRows = document.querySelectorAll('#attendanceTableBody tr');
  const studentIds = [];
  
  studentRows.forEach(row => {
    const cb = row.querySelector('input.student-checkbox');
    if (cb && cb.dataset && cb.dataset.id) {
      studentIds.push(cb.dataset.id);
      return;
    }
    const studentIdSpan = row.querySelector('td span.fw-bold');
    if (studentIdSpan) {
      studentIds.push(studentIdSpan.textContent.trim());
    }
  });
  
  console.log('Loading attendance for students:', studentIds);
  
  // Load attendance for each student with a small delay to prevent overwhelming the server
  studentIds.forEach((studentId, index) => {
    setTimeout(() => {
      loadStudentAttendance(studentId, date);
    }, index * 100); // 100ms delay between each request
  });
}

function loadStudentAttendance(studentId, date) {
  console.log(`Loading attendance for student ${studentId} on date ${date}`);
  
  const formData = new FormData();
  formData.append('action', 'get_attendance');
  formData.append('student_id', studentId);
  formData.append('date', date);
  
  // CSRF token removed for testing
  
  fetch('apis/attendance_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    console.log(`Response for student ${studentId}:`, data);
    if (data.success && data.attendance) {
      console.log(`Found attendance for ${studentId}:`, data.attendance);
      updateStudentAttendanceStatus(studentId, data.attendance.status, data.attendance.score);
    } else if (data.success && !data.attendance) {
      console.log(`No attendance found for ${studentId} on ${date} - keeping as "Not marked"`);
      // Keep the student as "Not marked" - this is correct behavior
    } else {
      console.error(`Error loading attendance for ${studentId}:`, data.message);
    }
  })
  .catch(error => {
    console.error(`Network error loading attendance for ${studentId}:`, error);
  });
}

function markAttendance(studentId, status, score) {
  console.log('markAttendance called with:', studentId, status, score);
  console.log('Student ID type:', typeof studentId, 'Value:', studentId);
  
  // Prevent default behavior
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  // Validate student ID
  if (!studentId || studentId === 'undefined' || studentId === 'null') {
    console.error('Invalid student ID:', studentId);
    alert('Error: Invalid student ID');
    return;
  }
  
  // Update the UI immediately for better user experience
  updateStudentAttendanceStatus(studentId, status, score);
  updateAttendanceSummary();
  
  // Get form data
  const selectedDate = document.getElementById('attendanceDate').value;
  const currentBatch = document.querySelector('.batch-tab.active').getAttribute('data-batch');
  
  console.log(`Marking attendance for student ${studentId}: ${status} (${score}) on ${selectedDate} for batch ${currentBatch}`);
  
  // Send to server
  const formData = new FormData();
  formData.append('action', 'mark_attendance');
  formData.append('student_id', studentId);
  formData.append('status', status);
  formData.append('score', score);
  formData.append('date', selectedDate);
  formData.append('batch', currentBatch);
  
  // CSRF token removed for testing
  
  // Send to server
  fetch('apis/attendance_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Attendance saved successfully:', data.message);
      // Update summary after successful save
      updateAttendanceSummary();
    } else {
      console.error('Error saving attendance:', data.message);
      // Show error message to user
      alert('Error saving attendance: ' + data.message);
      // Revert UI changes on error
      updateStudentAttendanceStatus(studentId, 'not_marked', 0);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    // Show error message to user
    alert('Error saving attendance. Please try again.');
    // Revert UI changes on error
    updateStudentAttendanceStatus(studentId, 'not_marked', 0);
  });
}

function updateStudentAttendanceStatus(studentId, status, score) {
  console.log('Updating status for student:', studentId, 'status:', status);
  
  // Find the specific status element for this student ID
  const statusElement = document.getElementById(`status-${studentId}`);
  if (statusElement) {
    console.log('Found status element for student:', studentId);
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
      console.log('Updated indicator for student:', studentId, 'to:', status);
    }
  } else {
    console.error('Status element not found for student:', studentId);
  }
  
  // Update the table row visual state - find the row containing the status element
  const statusRow = statusElement ? statusElement.closest('tr') : null;
  if (statusRow) {
    console.log('Found row for student:', studentId);
    // Remove previous status classes
    statusRow.classList.remove('marked-present', 'marked-absent');
    
    if (status === 'present') {
      statusRow.classList.add('marked-present');
    } else if (status === 'absent') {
      statusRow.classList.add('marked-absent');
    }
    console.log('Updated row classes for student:', studentId);
  } else {
    console.error('Row not found for student:', studentId);
  }
}

function updateAttendanceSummary() {
  const presentElements = document.querySelectorAll('.status-indicator.present');
  const absentElements = document.querySelectorAll('.status-indicator.absent');
  
  const totalPresent = presentElements.length;
  const totalAbsent = absentElements.length;
  
  const totalPresentEl = document.getElementById('totalPresent');
  const totalAbsentEl = document.getElementById('totalAbsent');
  
  if (totalPresentEl) totalPresentEl.textContent = totalPresent;
  if (totalAbsentEl) totalAbsentEl.textContent = totalAbsent;
}

function exportAttendanceData() {
  // This function would export attendance data to CSV
  alert('Exporting attendance data...');
}

// Initialize attendance management when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, initializing attendance management');
  initializeAttendanceManagement();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAttendanceManagement);
} else {
  console.log('DOM already loaded, initializing attendance management immediately');
  initializeAttendanceManagement();
}

// Initialize attendance courses when attendance tab is clicked
document.addEventListener('DOMContentLoaded', function() {
  const attendanceTab = document.querySelector('[data-tab="attendance"]');
  if (attendanceTab) {
    attendanceTab.addEventListener('click', function() {
      console.log('Attendance tab clicked, loading courses');
      loadAttendanceCourses();
    });
  }
  
  // Initialize quizzes courses when quizzes tab is clicked
  const quizzesTab = document.querySelector('[data-tab="quizzes"]');
  if (quizzesTab) {
    quizzesTab.addEventListener('click', function() {
      console.log('Quizzes tab clicked, loading courses');
      loadQuizzesCourses();
      // Also load quizzes when the tab is clicked
      setTimeout(() => {
        loadQuizzes();
      }, 100);
    });
  }
  
  // Initialize exam courses when exam tab is clicked
  const examTab = document.querySelector('[data-tab="exam"]');
  if (examTab) {
    examTab.addEventListener('click', function() {
      console.log('Exam tab clicked, loading courses');
      loadExamCourses();
    });
  }
});

// Add event listeners for modal overlays to close on outside click
document.addEventListener('DOMContentLoaded', function() {
  // Quiz modal overlay click
  const quizModal = document.getElementById('quizCreationModal');
  if (quizModal) {
    quizModal.addEventListener('click', function(e) {
      if (e.target === quizModal) {
        closeQuizModal();
      }
    });
  }
  
  // Quiz save confirmation modal overlay click
  const quizSaveConfirmationModal = document.getElementById('quizSaveConfirmationModal');
  if (quizSaveConfirmationModal) {
    quizSaveConfirmationModal.addEventListener('click', function(e) {
      if (e.target === quizSaveConfirmationModal) {
        closeQuizSaveConfirmation();
      }
    });
  }
  
  // Exam save confirmation modal overlay click
  const examSaveConfirmationModal = document.getElementById('examSaveConfirmationModal');
  if (examSaveConfirmationModal) {
    examSaveConfirmationModal.addEventListener('click', function(e) {
      if (e.target === examSaveConfirmationModal) {
        closeExamSaveConfirmation();
      }
    });
  }
  
  // Quiz delete confirmation modal overlay click
  const quizDeleteConfirmationModal = document.getElementById('quizDeleteConfirmationModal');
  if (quizDeleteConfirmationModal) {
    quizDeleteConfirmationModal.addEventListener('click', function(e) {
      if (e.target === quizDeleteConfirmationModal) {
        closeQuizDeleteConfirmation();
      }
    });
  }
  
  // Exam delete confirmation modal overlay click
  const examDeleteConfirmationModal = document.getElementById('examDeleteConfirmationModal');
  if (examDeleteConfirmationModal) {
    examDeleteConfirmationModal.addEventListener('click', function(e) {
      if (e.target === examDeleteConfirmationModal) {
        closeExamDeleteConfirmation();
      }
    });
  }
  
  // Quiz delete success modal overlay click
  const quizDeleteSuccessModal = document.getElementById('quizDeleteSuccessModal');
  if (quizDeleteSuccessModal) {
    quizDeleteSuccessModal.addEventListener('click', function(e) {
      if (e.target === quizDeleteSuccessModal) {
        closeQuizDeleteSuccess();
      }
    });
  }
  
  // Exam modal overlay click
  const examModal = document.getElementById('examCreationModal');
  if (examModal) {
    examModal.addEventListener('click', function(e) {
      if (e.target === examModal) {
        closeExamModal();
      }
    });
  }
  
  // ESC key to close modals
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (quizModal && quizModal.style.display === 'flex') {
        closeQuizModal();
      }
      if (quizSaveConfirmationModal && quizSaveConfirmationModal.classList.contains('show')) {
        closeQuizSaveConfirmation();
      }
      if (quizDeleteConfirmationModal && quizDeleteConfirmationModal.classList.contains('show')) {
        closeQuizDeleteConfirmation();
      }
      if (quizDeleteSuccessModal && quizDeleteSuccessModal.classList.contains('show')) {
        closeQuizDeleteSuccess();
      }
      if (examModal && examModal.style.display === 'flex') {
        closeExamModal();
      }
      if (examSaveConfirmationModal && examSaveConfirmationModal.classList.contains('show')) {
        closeExamSaveConfirmation();
      }
      if (examDeleteConfirmationModal && examDeleteConfirmationModal.classList.contains('show')) {
        closeExamDeleteConfirmation();
      }
    }
  });
});

// Make markAttendance function globally available
window.markAttendance = markAttendance;

// Function to publish a quiz
function publishQuiz(quizId) {
  if (!confirm('Are you sure you want to publish this quiz? Students will be able to take it.')) {
    return;
  }
  
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'publish_quiz',
      quiz_id: quizId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Quiz published successfully!');
      loadQuizzes(); // Reload the quiz list
    } else {
      alert('Error publishing quiz: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error publishing quiz:', error);
    alert('Error publishing quiz: ' + error.message);
  });
}

// Function to unpublish a quiz
function unpublishQuiz(quizId) {
  if (!confirm('Are you sure you want to unpublish this quiz? Students will no longer be able to take it.')) {
    return;
  }
  
  fetch('apis/quiz_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'unpublish_quiz',
      quiz_id: quizId
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Quiz unpublished successfully!');
      loadQuizzes(); // Reload the quiz list
    } else {
      alert('Error unpublishing quiz: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error unpublishing quiz:', error);
    alert('Error unpublishing quiz: ' + error.message);
  });
}

// Function to refresh quiz submissions while maintaining current filter
function refreshQuizSubmissions() {
    // Use the current course filter from global state
    const currentFilter = globalState.currentCourseFilter;
    console.log('Refreshing quiz submissions with current filter:', currentFilter);
    loadQuizSubmissions(currentFilter);
}

// Function to update course filter display
function updateCourseFilterDisplay(courseFilter) {
    const filterDisplay = document.getElementById('courseFilterDisplay');
    const currentCourseSpan = document.getElementById('currentCourseFilter');
    
    if (courseFilter && courseFilter !== 'all') {
        if (filterDisplay) {
            filterDisplay.classList.remove('d-none');
        }
        if (currentCourseSpan) {
            currentCourseSpan.textContent = courseFilter;
        }
    } else {
        if (filterDisplay) {
            filterDisplay.classList.add('d-none');
        }
    }
}

// Function to load quiz submissions
function loadQuizSubmissions(courseFilter = null) {
  console.log('Loading quiz submissions...', courseFilter ? `for course: ${courseFilter}` : 'for all courses');
  
  // Update global state with current course filter
  globalState.currentCourseFilter = courseFilter;
  
  // Update course filter display
  updateCourseFilterDisplay(courseFilter);
  
  const tableBody = document.getElementById('quizSubmissionsTableBody');
  
  if (tableBody) {
    // Show loading state
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center">
          <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-primary me-2" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            Loading submissions...
          </div>
        </td>
      </tr>
    `;
  }
  
  // Prepare request body
  const requestBody = {
    action: 'get_quiz_submissions'
  };
  
  // Add course filter if provided
  if (courseFilter && courseFilter !== 'all') {
    requestBody.course = courseFilter;
  }
  
  // Fetch submissions from API
  fetch('apis/quiz_submissions.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(requestBody)
  })
  .then(response => response.json())
  .then(data => {
    console.log('Submissions API Response:', data);
    if (data.success) {
      displayQuizSubmissions(data.submissions, data.course_filter);
    } else {
      console.error('Error loading submissions:', data.message);
      showSubmissionsError('Error loading submissions: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error fetching submissions:', error);
    showSubmissionsError('Error loading submissions: ' + error.message);
  });
}

// Function to display quiz submissions in the table
function displayQuizSubmissions(submissions, courseFilter = null) {
  const tableBody = document.getElementById('quizSubmissionsTableBody');
  
  if (!tableBody) {
    console.error('Submissions table body not found');
    return;
  }
  
  if (!submissions || submissions.length === 0) {
    const noDataMessage = courseFilter ? `No submissions found for ${courseFilter}` : 'No submissions yet';
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center text-muted">${noDataMessage}</td>
      </tr>
    `;
    return;
  }
  
  // Clear existing content
  tableBody.innerHTML = '';
  
  // Add each submission as a table row
  submissions.forEach(submission => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>
        <div class="d-flex align-items-center">
          <i class="fas fa-user text-primary me-2"></i>
          <div>
            <div class="fw-bold">${escapeHtml(submission.first_name + ' ' + submission.last_name)}</div>
            <small class="text-muted">${submission.student_number}</small>
          </div>
        </div>
      </td>
      <td>
        <div class="fw-semibold">${escapeHtml(submission.quiz_title)}</div>
        <small class="text-muted">${submission.course || 'No course'}</small>
      </td>
      <td>
        <span class="badge ${getScoreBadgeClass(submission.score)}">
          ${submission.score ? (parseFloat(submission.score) || 0).toFixed(1) + '%' : 'Not scored'}
        </span>
        <div class="small text-muted">
          ${submission.correct_answers || 0}/${submission.total_questions || 0} correct
        </div>
      </td>
      <td>
        <div class="text-muted">
          <i class="fas fa-calendar-alt me-1"></i>
          ${formatDate(submission.submitted_at)}
        </div>
      </td>
      <td>
        <span class="badge ${getStatusBadgeClass(submission.status)}">${submission.status || 'submitted'}</span>
      </td>
      <td>
        <div class="btn-group" role="group">
          <button class="btn btn-sm btn-outline-primary" onclick="viewSubmissionDetails(${submission.submission_id}, 'quiz')" title="View Details">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </td>
    `;
    tableBody.appendChild(row);
  });
}

// Function to get score badge class
function getScoreBadgeClass(score) {
  if (!score) return 'bg-secondary';
  const numericScore = parseFloat(score) || 0;
  if (numericScore >= 90) return 'bg-success';
  if (numericScore >= 80) return 'bg-primary';
  if (numericScore >= 70) return 'bg-warning';
  return 'bg-danger';
}

// Function to show submissions error
function showSubmissionsError(message) {
  const tableBody = document.getElementById('quizSubmissionsTableBody');
  if (tableBody) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center text-danger">${message}</td>
      </tr>
    `;
  }
}

// Function to view submission details
function viewSubmissionDetails(submissionId, type) {
  // Fetch submission details
  fetch('apis/quiz_submissions.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_submission_details',
      submission_id: submissionId,
      type: type
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSubmissionDetailsModal(data.submission);
    } else {
      alert('Error loading submission details: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error fetching submission details:', error);
    alert('Error loading submission details: ' + error.message);
  });
}

// Function to show submission details modal
function showSubmissionDetailsModal(submission) {
  // Create modal HTML
  const modalHtml = `
    <div class="modal fade" id="submissionDetailsModal" tabindex="-1" aria-labelledby="submissionDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="submissionDetailsModalLabel">Submission Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-6">
                <strong>Student:</strong> ${submission.first_name} ${submission.last_name} (${submission.student_number})
              </div>
              <div class="col-md-6">
                <strong>Course:</strong> ${submission.course || 'No course'}
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <strong>Quiz:</strong> ${submission.quiz_title || submission.exam_title}
              </div>
              <div class="col-md-6">
                <strong>Score:</strong> 
                <span class="badge ${getScoreBadgeClass(submission.score)}">
                  ${submission.score ? (parseFloat(submission.score) || 0).toFixed(1) + '%' : 'Not scored'}
                </span>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <strong>Submitted:</strong> ${formatDate(submission.submitted_at)}
              </div>
              <div class="col-md-6">
                <strong>Status:</strong> 
                <span class="badge ${getStatusBadgeClass(submission.status)}">${submission.status}</span>
              </div>
            </div>
            
            <h6>Answers:</h6>
            <div class="answers-container">
              ${submission.answers ? submission.answers.map((answer, index) => `
                <div class="card mb-2">
                  <div class="card-body">
                    <h6 class="card-title">Question ${index + 1}</h6>
                    <p class="card-text">${answer.question_text}</p>
                    <div class="answer-details">
                      <strong>Student Answer:</strong> ${answer.answer_text || 'No answer'}
                      ${answer.option_text ? `<br><strong>Selected Option:</strong> ${answer.option_text}` : ''}
                      <br><strong>Correct:</strong> 
                      <span class="badge ${answer.is_correct ? 'bg-success' : 'bg-danger'}">
                        ${answer.is_correct ? 'Yes' : 'No'}
                      </span>
                    </div>
                  </div>
                </div>
              `).join('') : 'No answers found'}
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  // Remove existing modal if any
  const existingModal = document.getElementById('submissionDetailsModal');
  if (existingModal) {
    existingModal.remove();
  }
  
  // Add modal to body
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  
  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('submissionDetailsModal'));
  modal.show();
}

// Make quiz functions globally available
window.loadQuizzes = loadQuizzes;
window.loadQuizSubmissions = loadQuizSubmissions;
window.refreshQuizSubmissions = refreshQuizSubmissions;
window.updateCourseFilterDisplay = updateCourseFilterDisplay;
window.viewSubmissionDetails = viewSubmissionDetails;
window.previewQuizById = previewQuizById;
window.editQuiz = editQuiz;
window.deleteQuiz = deleteQuiz;
window.publishQuiz = publishQuiz;
window.unpublishQuiz = unpublishQuiz;
window.showQuizSaveConfirmation = showQuizSaveConfirmation;
window.closeQuizSaveConfirmation = closeQuizSaveConfirmation;
window.viewQuizList = viewQuizList;
window.showQuizDeleteConfirmation = showQuizDeleteConfirmation;
window.closeQuizDeleteConfirmation = closeQuizDeleteConfirmation;
window.confirmDeleteQuiz = confirmDeleteQuiz;
window.showQuizDeleteSuccess = showQuizDeleteSuccess;
window.closeQuizDeleteSuccess = closeQuizDeleteSuccess;
window.viewQuizListFromDelete = viewQuizListFromDelete;
window.updateQuizToDatabase = updateQuizToDatabase;
window.showQuizUpdateConfirmation = showQuizUpdateConfirmation;
window.showExamSaveConfirmation = showExamSaveConfirmation;
window.closeExamSaveConfirmation = closeExamSaveConfirmation;
window.viewExamList = viewExamList;
window.showExamDeleteConfirmation = showExamDeleteConfirmation;
window.closeExamDeleteConfirmation = closeExamDeleteConfirmation;
window.confirmDeleteExam = confirmDeleteExam;
window.showExamQuestionTypeMenu = showExamQuestionTypeMenu;
window.changeExamQuestionType = changeExamQuestionType;
window.addExamOption = addExamOption;
window.duplicateExamQuestion = duplicateExamQuestion;
window.editExam = editExam;
window.populateExamForm = populateExamForm;
window.addQuestionToForm = addQuestionToForm;
window.updateExamModalForEdit = updateExamModalForEdit;
window.updateExam = updateExam;

// Function to view students in a specific course (AJAX version)
function viewCourseStudents(courseName) {
    console.log('Viewing students for course:', courseName);
    
    // Show loading state
    showCourseLoading();
    
    // Fetch students via AJAX
    fetch(`apis/course_students.php?course=${encodeURIComponent(courseName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudentsForCourse(data.course, data.students);
            } else {
                console.error('Error loading students:', data.message);
                showCourseError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showCourseError('Network error occurred');
        });
}

// Function to show loading state
function showCourseLoading() {
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading students...
                    </div>
                </td>
            </tr>
        `;
    }
}

// Function to show error state
function showCourseError(message) {
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error: ${message}
                </td>
            </tr>
        `;
    }
}

// Function to add batch selector to the header
function addBatchSelectorToHeader(courseName) {
    // Find the course header section
    const courseHeader = document.querySelector('.course-header');
    if (courseHeader) {
        // Check if batch selector already exists
        let batchSelector = document.getElementById('courseBatchSelector');
        if (!batchSelector) {
            // Create batch selector HTML
            const batchSelectorHTML = `
                <div class="batch-selector-container mt-3" id="courseBatchSelector">
                    <label for="courseBatchSelect" class="form-label fw-semibold">
                        <i class="fas fa-layer-group me-2"></i>Select Batch:
                    </label>
                    <select id="courseBatchSelect" class="form-select" style="max-width: 200px;">
                        <option value="1">Batch 1 - January to March</option>
                        <option value="2">Batch 2 - April to June</option>
                        <option value="3">Batch 3 - July to September</option>
                        <option value="4">Batch 4 - October to December</option>
                    </select>
                </div>
            `;
            
            // Add the batch selector to the header
            courseHeader.insertAdjacentHTML('beforeend', batchSelectorHTML);
            
            // Add event listener for batch selection
            const batchSelect = document.getElementById('courseBatchSelect');
            if (batchSelect) {
                batchSelect.addEventListener('change', function() {
                    const selectedBatch = this.value;
                    console.log('Batch changed to:', selectedBatch);
                    // Reload students for the selected batch
                    loadStudentsForBatch(courseName, selectedBatch);
                });
            }
        }
    }
}

// Function to load students for a specific batch
function loadStudentsForBatch(courseName, batch) {
    console.log('Loading students for course:', courseName, 'batch:', batch);
    
    // Show loading state
    showCourseLoading();
    
    // Fetch students for the specific batch
    fetch(`apis/course_students.php?course=${encodeURIComponent(courseName)}&batch=${batch}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Found', data.students.length, 'students for batch', batch);
                displayStudentsForBatch(courseName, data.students);
            } else {
                console.error('Error loading students for batch:', data.message);
                showCourseError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showCourseError('Network error occurred');
        });
}

// Function to display students for a specific batch (without adding batch selector again)
function displayStudentsForBatch(courseName, students) {
    // Update table headers for student view
    updateTableHeaders(true);
    
    // Populate table body
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        if (students.length > 0) {
            let html = '';
            students.forEach(student => {
                const studentId = student.student_number;
                const firstName = student.first_name || '';
                const lastName = student.last_name || '';
                const course = student.course || '';
                const finalGrade = student.final_grade || 0;
                
                html += `
                    <tr class="clickable-row" onclick="showStudentDetails('${studentId}')">
                        <td class="text-center">
                            <span class="fw-bold text-primary">${studentId}</span>
                        </td>
                        <td class="text-center">
                            <div class="student-name-content">
                                <div class="fw-semibold">${firstName} ${lastName}</div>
                                <small>${course || '—'}</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light">${course || '—'}</span>
                        </td>
                        <td class="text-center">
                            <span class="final-grade-cell final-grade-good">
                                <strong>${finalGrade.toFixed(1)}%</strong>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); editGrade('${studentId}')">
                                <i class="fas fa-edit me-1"></i>Edit Grade
                            </button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        <i class="fas fa-users me-2"></i>No students found in this batch
                    </td>
                </tr>
            `;
        }
    }
    
    // Update student grade data for existing functions
    updateStudentGradeData(students);
}

// Function to display students for a course
function displayStudentsForCourse(courseName, students) {
    // Update the header
    updateCourseHeader(courseName);
    
    // Add batch selector to the header (only if not already added)
    addBatchSelectorToHeader(courseName);
    
    // Update table headers for student view
    updateTableHeaders(true);
    
    // Populate table body
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        if (students.length > 0) {
            let html = '';
            students.forEach(student => {
                const studentId = student.student_number;
                const firstName = student.first_name || '';
                const lastName = student.last_name || '';
                const course = student.course || '';
                const finalGrade = student.final_grade || 0;
                
                html += `
                    <tr class="clickable-row" onclick="showStudentDetails('${studentId}')">
                        <td class="text-center">
                            <span class="fw-bold text-primary">${studentId}</span>
                        </td>
                        <td class="text-center">
                            <div class="student-name-content">
                                <div class="fw-semibold">${firstName} ${lastName}</div>
                                <small>${course || '—'}</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light">${course || '—'}</span>
                        </td>
                        <td class="text-center">
                            <span class="final-grade-cell final-grade-good">
                                <strong>${finalGrade.toFixed(1)}%</strong>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); editGrade('${studentId}')">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">No students found in this course.</td>
                </tr>
            `;
        }
    }
    
    // Update student grade data for existing functions
    updateStudentGradeData(students);
}

// Function to update course header
function updateCourseHeader(courseName) {
    const gradesPanel = document.querySelector('[data-panel="grades"]');
    if (gradesPanel) {
        // Remove existing header if it exists
        const existingHeader = gradesPanel.querySelector('.course-header');
        if (existingHeader) {
            existingHeader.remove();
        }
        
        // Add new header
        const header = document.createElement('div');
        header.className = 'course-header mb-3';
        header.innerHTML = `
            <button class="btn btn-secondary" onclick="backToCourses()">
                <i class="fas fa-arrow-left me-2"></i>Back to Courses
            </button>
            <h4 class="d-inline-block ms-3">Students in: <span class="text-primary">${courseName}</span></h4>
        `;
        gradesPanel.insertBefore(header, gradesPanel.querySelector('.table-container'));
    }
}

// Function to update table headers
function updateTableHeaders(isStudentView) {
    const tableHead = document.querySelector('#gradesTable thead tr');
    if (tableHead) {
        if (isStudentView) {
            tableHead.innerHTML = `
                <th scope="col">Student ID</th>
                <th scope="col">Student Name</th>
                <th scope="col">Course</th>
                <th scope="col">Final Grade</th>
                <th scope="col">Actions</th>
            `;
        } else {
            tableHead.innerHTML = `
                <th scope="col">Course Name</th>
                <th scope="col">Student Count</th>
                <th scope="col">Actions</th>
            `;
        }
    }
}

// Function to go back to courses view
function backToCourses() {
    // Remove course header
    const existingHeader = document.querySelector('.course-header');
    if (existingHeader) {
        existingHeader.remove();
    }
    
    // Update table headers for course view
    updateTableHeaders(false);
    
    // Reload courses
    loadCoursesView();
}

// Function to load courses view
function loadCoursesView() {
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading courses...
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Fetch courses via AJAX
    fetch('apis/course_students.php?action=courses')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCourses(data.courses);
            } else {
                console.error('Error loading courses:', data.message);
                showCourseError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showCourseError('Network error occurred');
        });
}

// Function to display courses
function displayCourses(courses) {
    const tableBody = document.getElementById('gradesTableBody');
    if (tableBody) {
        if (courses.length > 0) {
            let html = '';
            courses.forEach(course => {
                const courseName = course.course;
                const studentCount = course.student_count;
                
                html += `
                    <tr class="clickable-row course-row" onclick="viewCourseStudents('${courseName}')">
                        <td>
                            <div class="course-name-content">
                                <div class="fw-semibold text-primary">${courseName}</div>
                                <small class="text-muted">Click to view students</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info">
                                <i class="fas fa-users me-1"></i>
                                ${studentCount} student${studentCount !== 1 ? 's' : ''}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); viewCourseStudents('${courseName}')">
                                <i class="fas fa-eye me-1"></i>View Students
                            </button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">No courses found.</td>
                </tr>
            `;
        }
    }
}

// Function to update student grade data for existing functions
function updateStudentGradeData(students) {
    window.studentGradeData = window.studentGradeData || {};
    students.forEach(student => {
        const id = student.student_number;
        if (id) {
            window.studentGradeData[id] = {
                id: id,
                firstName: student.first_name || '',
                lastName: student.last_name || '',
                course: student.course || '',
                gradeBreakdown: { 
                    grade1: 0, 
                    grade2: 0, 
                    grade3: 0, 
                    grade4: 0, 
                    finalGrade: student.final_grade || 0 
                }
            };
        }
    });
}

// Function to view modules for a specific course
function viewCourseModules(courseName) {
    console.log('Viewing modules for course:', courseName);
    
    // Show loading state
    showModulesLoading();
    
    // Fetch modules via AJAX
    fetch(`apis/course_modules.php?course=${encodeURIComponent(courseName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayModulesForCourse(data.course, data.modules);
                // Also load quiz submissions for this specific course
                loadQuizSubmissions(courseName);
            } else {
                console.error('Error loading modules:', data.message);
                showModulesError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showModulesError('Network error occurred');
        });
}

// Function to show modules loading state
function showModulesLoading() {
    const tableBody = document.getElementById('coursesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading modules...
                    </div>
                </td>
            </tr>
        `;
    }
}

// Function to show modules error state
function showModulesError(message) {
    const tableBody = document.getElementById('coursesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error: ${message}
                </td>
            </tr>
        `;
    }
}

// Function to display modules for a course
function displayModulesForCourse(courseName, modules) {
    // Update the header
    updateModulesHeader(courseName);
    
    // Update table headers for modules view
    updateModulesTableHeaders();
    
    // Populate table body
    const tableBody = document.getElementById('coursesTableBody');
    if (tableBody) {
        if (modules.length > 0) {
            let html = '';
            modules.forEach(module => {
                html += `
                    <tr class="module-row">
                        <td>
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge rounded-pill bg-primary">${module.id}</span>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold">${module.name}</span>
                                    <small class="text-muted">Module ${module.id}</small>
                                </div>
                            </div>
                        </td>
                        <td style="min-width:200px;">
                            <div class="d-flex flex-column align-items-start">
                                <div class="w-100 d-flex justify-content-end mb-1">
                                    <small class="text-muted">${module.progress}%</small>
                                </div>
                                <div class="progress w-100" style="height:6px;">
                                    <div class="progress-bar bg-warning" style="width:${module.progress}%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-warning-subtle text-warning">• ${module.status}</span>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">No modules found for this course.</td>
                </tr>
            `;
        }
    }
}

// Function to update modules header
function updateModulesHeader(courseName) {
    const coursesPanel = document.querySelector('[data-panel="courses"]');
    if (coursesPanel) {
        // Remove existing header if it exists
        const existingHeader = coursesPanel.querySelector('.modules-header');
        if (existingHeader) {
            existingHeader.remove();
        }
        
        // Add new header
        const header = document.createElement('div');
        header.className = 'modules-header mb-3';
        header.innerHTML = `
            <button class="btn btn-secondary" onclick="backToCoursesView()">
                <i class="fas fa-arrow-left me-2"></i>Back to Course
            </button>
            <h4 class="d-inline-block ms-3">Modules for: <span class="text-primary">${courseName}</span></h4>
        `;
        coursesPanel.insertBefore(header, coursesPanel.querySelector('.table-container'));
    }
}

// Function to update modules table headers
function updateModulesTableHeaders() {
    const tableHead = document.querySelector('#coursesTable thead tr');
    if (tableHead) {
        tableHead.innerHTML = `
            <th scope="col">Module</th>
            <th scope="col">Progress</th>
            <th scope="col">Status</th>
        `;
    }
}

// Function to go back to courses view
function backToCoursesView() {
    // Remove modules header
    const existingHeader = document.querySelector('.modules-header');
    if (existingHeader) {
        existingHeader.remove();
    }
    
    // Update table headers for course view
    updateCoursesTableHeaders();
    
    // Reload courses view
    loadCoursesViewForModules();
}

// Function to update courses table headers
function updateCoursesTableHeaders() {
    const tableHead = document.querySelector('#coursesTable thead tr');
    if (tableHead) {
        tableHead.innerHTML = `
            <th scope="col">Course</th>
            <th scope="col">Progress</th>
            <th scope="col">Status</th>
        `;
    }
}

// Function to load courses view for modules tab
function loadCoursesViewForModules() {
    const tableBody = document.getElementById('coursesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading course...
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Reload the page to show the original course view
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Function to view attendance for a specific course
function viewAttendanceForCourse(courseName) {
    console.log('Viewing attendance for course:', courseName);
    
    // Show loading state
    showAttendanceLoading();
    
    // Get current batch
    const currentBatch = document.querySelector('.batch-tab.active');
    const batch = currentBatch ? currentBatch.getAttribute('data-batch') : '1';
    
    console.log('Loading students for course:', courseName, 'batch:', batch);
    console.log('API URL:', `apis/course_students.php?course=${encodeURIComponent(courseName)}&batch=${batch}`);
    
    // Fetch students for the course via AJAX with batch filter
    fetch(`apis/course_students.php?course=${encodeURIComponent(courseName)}&batch=${batch}`)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            if (data.success) {
                console.log('Found', data.students.length, 'students for batch', batch);
                displayAttendanceStudents(data.course, data.students);
            } else {
                console.error('Error loading students for attendance:', data.message);
                showAttendanceError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showAttendanceError('Network error occurred');
        });
}

// Function to show attendance loading state
function showAttendanceLoading() {
    const tableBody = document.getElementById('attendanceTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading students...
                    </div>
                </td>
            </tr>
        `;
    }
}

// Function to show attendance error state
function showAttendanceError(message) {
    const tableBody = document.getElementById('attendanceTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error: ${message}
                </td>
            </tr>
        `;
    }
}

// Function to display students for attendance
function displayAttendanceStudents(courseName, students) {
    // Update the header
    updateAttendanceHeader(courseName);
    
    // Show attendance content
    document.getElementById('attendanceContent').style.display = 'block';
    document.querySelector('.attendance-course-selector').style.display = 'none';
    
    // Populate attendance table
    const tableBody = document.getElementById('attendanceTableBody');
    if (tableBody) {
        if (students.length > 0) {
            let html = '';
            students.forEach(student => {
                const studentId = student.student_number;
                const firstName = student.first_name || '';
                const lastName = student.last_name || '';
                const course = student.course || '';
                
                html += `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="student-checkbox" data-id="${studentId}" />
                        </td>
                        <td class="text-center">
                            <span class="fw-bold text-primary">${studentId}</span>
                        </td>
                        <td class="text-center">
                            <div class="student-name-content">
                                <div class="fw-semibold">${firstName} ${lastName}</div>
                                <small>${course || '—'}</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light">${course || '—'}</span>
                        </td>
                        <td class="text-center">
                            <span class="attendance-present">0</span>
                        </td>
                        <td class="text-center">
                            <span class="attendance-absent">0</span>
                        </td>
                        <td class="text-center">
                            <div class="attendance-buttons-inline">
                                <button class="btn btn-success btn-sm attendance-mark-btn" onclick="markAttendance('${studentId}', 'present', 100)" title="Mark as Present (100)">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-sm attendance-mark-btn" onclick="markAttendance('${studentId}', 'absent', 50)" title="Mark as Absent (50)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="attendance-status-inline" id="status-${studentId}">
                                <span class="status-indicator">Not marked</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="attendance-rate">0%</span>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
            
            // Update total students count
            document.getElementById('totalStudents').textContent = students.length;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">No students found in this course.</td>
                </tr>
            `;
        }
    }
    
    // Load attendance data for the current date
    const today = new Date().toISOString().split('T')[0];
    loadAttendanceForDate(today);
}

// Function to update attendance header
function updateAttendanceHeader(courseName) {
    document.getElementById('selectedCourseName').textContent = courseName;
}

// Function to go back to attendance courses
function backToAttendanceCourses() {
    // Hide attendance content
    document.getElementById('attendanceContent').style.display = 'none';
    document.querySelector('.attendance-course-selector').style.display = 'block';
    
    // Reset attendance table
    const tableBody = document.getElementById('attendanceTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">Select a course to view students</td>
            </tr>
        `;
    }
    
    // Reset summary
    document.getElementById('totalStudents').textContent = '0';
    document.getElementById('totalPresent').textContent = '0';
    document.getElementById('totalAbsent').textContent = '0';
}

// Function to load attendance courses
function loadAttendanceCourses() {
    const tableBody = document.getElementById('attendanceCoursesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading courses...
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Fetch courses via AJAX
    fetch('apis/course_students.php?action=courses')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAttendanceCourses(data.courses);
            } else {
                console.error('Error loading courses:', data.message);
                showAttendanceCoursesError(data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showAttendanceCoursesError('Network error occurred');
        });
}

// Function to display attendance courses
function displayAttendanceCourses(courses) {
    const tableBody = document.getElementById('attendanceCoursesTableBody');
    if (tableBody) {
        if (courses.length > 0) {
            let html = '';
            courses.forEach(course => {
                const courseName = course.course;
                const studentCount = course.student_count;
                
                html += `
                    <tr class="clickable-row course-row" onclick="viewAttendanceForCourse('${courseName}')">
                        <td>
                            <div class="course-name-content">
                                <div class="fw-semibold text-primary">${courseName}</div>
                                <small class="text-muted">Click to view students for attendance</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info">
                                <i class="fas fa-users me-1"></i>
                                ${studentCount} student${studentCount !== 1 ? 's' : ''}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); viewAttendanceForCourse('${courseName}')">
                                <i class="fas fa-calendar-check me-1"></i>Take Attendance
                            </button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">No courses found.</td>
                </tr>
            `;
        }
    }
}

// Function to show attendance courses error
function showAttendanceCoursesError(message) {
    const tableBody = document.getElementById('attendanceCoursesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error: ${message}
                </td>
            </tr>
        `;
    }
}

// Make functions globally available
window.viewCourseStudents = viewCourseStudents;
window.backToCourses = backToCourses;
window.viewCourseModules = viewCourseModules;
window.backToCoursesView = backToCoursesView;
window.viewAttendanceForCourse = viewAttendanceForCourse;
window.backToAttendanceCourses = backToAttendanceCourses;
window.syncAllQuizGrades = syncAllQuizGrades;
window.publishExam = publishExam;
window.unpublishExam = unpublishExam;
window.loadExamSubmissions = loadExamSubmissions;
window.viewExamSubmissionDetails = viewExamSubmissionDetails;
window.syncAllExamGrades = syncAllExamGrades;
window.loadExams = loadExams;
window.removeEditEntry = removeEditEntry;

// Test function to verify buttons are working
window.testAttendance = function() {
  console.log('Testing attendance system...');
  const testStudentId = '4389933922'; // Use one of the actual student IDs
  console.log('Testing with student ID:', testStudentId);
  markAttendance(testStudentId, 'present', 100);
};

// Debug function to check all student IDs in the table
window.debugStudentIds = function() {
  console.log('Debugging student IDs in table...');
  const studentRows = document.querySelectorAll('#attendanceTableBody tr');
  studentRows.forEach((row, index) => {
    const studentIdCell = row.querySelector('td:first-child span');
    const statusElement = row.querySelector('[id^="status-"]');
    if (studentIdCell) {
      const studentId = studentIdCell.textContent.trim();
      console.log(`Row ${index}: Student ID = "${studentId}", Status Element ID = "${statusElement ? statusElement.id : 'NOT FOUND'}"`);
    }
  });
};

// Debug function to check attendance for a specific date
window.debugAttendanceForDate = function(date) {
  console.log(`Debugging attendance for date: ${date}`);
  const studentRows = document.querySelectorAll('#attendanceTableBody tr');
  studentRows.forEach((row, index) => {
    const studentIdCell = row.querySelector('td:first-child span');
    if (studentIdCell) {
      const studentId = studentIdCell.textContent.trim();
      console.log(`Checking attendance for student ${studentId} on ${date}...`);
      loadStudentAttendance(studentId, date);
    }
  });
};

// Auto-refresh attendance data every 30 seconds to keep it live
let attendanceRefreshInterval;

function startAttendanceAutoRefresh() {
  // Clear any existing interval
  if (attendanceRefreshInterval) {
    clearInterval(attendanceRefreshInterval);
  }
  
  // Set up new interval
  attendanceRefreshInterval = setInterval(function() {
    const attendanceTab = document.querySelector('[data-tab="attendance"]');
    if (attendanceTab && attendanceTab.classList.contains('active')) {
      const currentBatch = document.querySelector('.batch-tab.active');
      if (currentBatch) {
        console.log('Auto-refreshing attendance data...');
        loadAttendanceData(currentBatch.getAttribute('data-batch'));
      }
    }
  }, 30000); // 30 seconds
}

function stopAttendanceAutoRefresh() {
  if (attendanceRefreshInterval) {
    clearInterval(attendanceRefreshInterval);
    attendanceRefreshInterval = null;
  }
}

// Start auto-refresh when attendance tab is active
document.addEventListener('DOMContentLoaded', function() {
  // Start auto-refresh after a short delay
  setTimeout(startAttendanceAutoRefresh, 2000);
});

// Add click event listeners to all attendance buttons for debugging
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    const presentButtons = document.querySelectorAll('.attendance-mark-btn.btn-success');
    const absentButtons = document.querySelectorAll('.attendance-mark-btn.btn-danger');
    
    console.log('Found present buttons:', presentButtons.length);
    console.log('Found absent buttons:', absentButtons.length);
    
    presentButtons.forEach((btn, index) => {
      console.log(`Present button ${index}:`, btn);
      btn.addEventListener('click', function(e) {
        console.log('Present button clicked directly');
        e.preventDefault();
        e.stopPropagation();
      });
    });
    
    absentButtons.forEach((btn, index) => {
      console.log(`Absent button ${index}:`, btn);
      btn.addEventListener('click', function(e) {
        console.log('Absent button clicked directly');
        e.preventDefault();
        e.stopPropagation();
      });
    });
  }, 1000);
});