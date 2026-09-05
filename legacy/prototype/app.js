// Global State
let currentUser = null; // null, 'user', or 'admin'
let videoStream = null;
let currentAuthMode = 'login'; // Track current authentication mode: 'login' or 'signin'

// Initialize persistent storage for reported documents
let reportedDocuments = JSON.parse(localStorage.getItem('reportedDocuments')) || [];

// Sections guests are allowed to view without logging in
const publicSections = ['landing', 'register', 'login', 'feedback'];

// Authentication Mode Toggle Handler
function setAuthMode(mode) {
    // mode can be 'login' (existing account) or 'signin' (new account)
    currentAuthMode = mode;
    const isExistingAccount = mode === 'login';
    
    // Update Section Title / Heading
    const authHeading = document.getElementById('authHeading');
    if (authHeading) {
        authHeading.innerText = isExistingAccount ? 'Login' : 'Sign In';
    }

    // Update Submit Button Label
    const authSubmitBtn = document.getElementById('authSubmitBtn');
    if (authSubmitBtn) {
        authSubmitBtn.innerText = isExistingAccount ? 'Log In' : 'Sign In';
    }

    // Toggle Form Fields (e.g., hide Name/Phone when logging in)
    const registerOnlyFields = document.querySelectorAll('.register-only');
    registerOnlyFields.forEach(el => {
        el.style.display = isExistingAccount ? 'none' : 'block';
    });
}

function toggleAuthMode() {
  const newMode = currentAuthMode === 'login' ? 'signin' : 'login';
  setAuthMode(newMode);
}

// View Switcher with Navigation Guard
function showSection(sectionId) {
  stopCamera();

  // Gatekeeping Check: Redirect to login if non-public section is requested by an unauthenticated user
  if (!publicSections.includes(sectionId) && !currentUser) {
    showAlert('You must log in or register before performing this action.', 'danger');
    setAuthMode('login');
    sectionId = 'login';
  }

  // Admin Access Guard
  if (sectionId === 'adminDashboard' && currentUser !== 'admin') {
    showAlert('Display Error Message: Unauthorized admin access.', 'danger');
    sectionId = 'login';
  }

  document.querySelectorAll('.page-section').forEach(sec => sec.classList.add('d-none'));

  const target = document.getElementById(sectionId);
  if (target) {
    target.classList.remove('d-none');
  }

  document.getElementById('alertContainer').innerHTML = '';
}

// Global Alert Handler
function showAlert(message, type = 'danger') {
  const container = document.getElementById('alertContainer');
  if (!container) return;
  const isSuccess = type === 'success';
  const prefix = isSuccess ? 'Display successful message: ' : 'Display error message: ';
  
  container.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
      <strong>${prefix}</strong>${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
}

function updateNavbar() {
  const nav = document.getElementById('navLinks');
  if (!nav) return;
  
  if (currentUser === 'admin') {
    nav.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('adminDashboard')">Admin Control Panel</a></li>
      <li class="nav-item"><a class="nav-link" href="#" onclick="handleLogout()">Logout</a></li>
    `;
  } else if (currentUser === 'user') {
    nav.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('roleSelect')">Dashboard</a></li>
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('notifications')">Notifications</a></li>
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('feedback')">Make Feedback</a></li>
      <li class="nav-item"><a class="nav-link" href="#" onclick="handleLogout()">Logout</a></li>
    `;
  } else {
    nav.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('landing')">Home</a></li>
      <li class="nav-item"><a class="nav-link" id="navAuthBtn" href="#" onclick="setAuthMode('login'); showSection('login');">Log In</a></li>
      <li class="nav-item"><a class="nav-link" href="#" onclick="showSection('feedback')">Make Feedback</a></li>
    `;
  }
}

// Register Action
function handleRegister(e) {
  e.preventDefault();
  const pass = document.getElementById('regPass').value;
  const confirm = document.getElementById('regConfirmPass').value;

  // Step 2.1: Check conformity
  if (pass !== confirm) {
    showAlert('Passwords do not match.', 'danger');
    return;
  }

  showAlert('User account created successfully! You can now log in.', 'success');
  setAuthMode('login');
  showSection('login');
}

// Authentication Flow Logic
function handleLogin(e) {
  e.preventDefault();
  const username = document.getElementById('loginUser').value.trim().toLowerCase();
  const pass = document.getElementById('loginPass').value.trim();

  // Step 2.1: Check conformity
  if (!username || !pass) {
    showAlert('Please fill in all required fields.', 'danger');
    return;
  }

  // Request result check (DBMS query simulation)
  if (username === 'admin' && pass === 'admin123') {
    currentUser = 'admin';
    updateNavbar();
    showAlert('Authentication successful! Welcome Admin.', 'success');
    showSection('adminDashboard');
  } else if (username !== 'admin' && pass.length >= 4) {
    currentUser = 'user';
    updateNavbar();
    showAlert('Authentication successful!', 'success');
    showSection('roleSelect');
  } else {
    showAlert('Invalid username or password.', 'danger');
  }
}

function handleLogout() {
  currentUser = null;
  updateNavbar();
  showSection('landing');
  showAlert('You have been logged out successfully.', 'info');
}

function setRole(role) {
  if (role === 'finder') showSection('finderDashboard');
  if (role === 'owner') showSection('ownerDashboard');
}

function handleFeedback(e) {
  e.preventDefault();
  showAlert('Feedback submitted successfully. Thank you!', 'success');
  e.target.reset();
}

// Finder Sequence Diagram Flow Logic (Includes Login Gatekeeping & Dynamic Duplicate Checking)
function handleReportSubmit(e) {
  e.preventDefault();

  // Gatekeeping Check: Ensure user is logged in before allowing submission
  if (!currentUser) {
    showAlert('Please log in or register before submitting a report.', 'danger');
    setAuthMode('login');
    showSection('login');
    return;
  }

  const type = document.getElementById('reportType').value;
  const name = document.getElementById('reportName').value.trim();
  const docNum = document.getElementById('reportDocNum').value.trim();

  // Step 3.1: Check conformity
  if (!type || !name) {
    showAlert('Missing required fields in form.', 'danger');
    return;
  }

  // Step 3.3.2: DBMS verification simulation
  if (docNum === '000') {
    showAlert('Invalid document details supplied.', 'danger');
    return;
  }

  // Dynamic Duplicate Checking
  const isDuplicate = reportedDocuments.some(doc => {
    if (docNum && doc.docNum) {
      return doc.type === type && doc.docNum.toLowerCase() === docNum.toLowerCase();
    }
    return doc.type === type && doc.name.toLowerCase() === name.toLowerCase();
  });

  if (isDuplicate || docNum === '111') {
    showAlert('This document has already been reported in the database.', 'warning');
    return;
  }

  // Store entry to local memory and persistent storage
  reportedDocuments.push({ type, name, docNum, createdAt: new Date().toISOString() });
  localStorage.setItem('reportedDocuments', JSON.stringify(reportedDocuments));

  showSection('reportSuccess');
}

// Owner Declare Lost Sequence Diagram Flow Logic (Includes Login Gatekeeping)
function handleDeclareSubmit(e) {
  e.preventDefault();

  // Gatekeeping Check: Ensure user is logged in
  if (!currentUser) {
    showAlert('Please log in or register before declaring a lost document.', 'danger');
    setAuthMode('login');
    showSection('login');
    return;
  }

  const type = document.getElementById('declareType').value;
  const docNum = document.getElementById('declareDocNum').value.trim();

  // Step 2.1: Check conformity
  if (!type) {
    showAlert('Please select a valid document category.', 'danger');
    return;
  }

  // Step 2.3.2: DBMS query verification simulation
  if (docNum === '000') {
    showAlert('Invalid document number or missing required parameters.', 'danger');
  } else if (docNum === 'err') {
    showAlert('Database processing error. Please try again.', 'danger');
  } else {
    showAlert('Lost document declaration recorded successfully in database!', 'success');
    showSection('ownerDashboard');
  }
}

// Search & Payment
function handleSearchSubmit(e) {
  e.preventDefault();
  const docNum = document.getElementById('searchInputNum') ? document.getElementById('searchInputNum').value.trim() : '';
  if (docNum === '123') {
    showSection('matchFound');
  } else {
    showSection('noMatchFound');
  }
}

function handlePayment(e) {
  e.preventDefault();
  showAlert('Payment processed successfully!', 'success');
  showSection('consultInfo');
}

// WebRTC Camera implementation
async function startCamera() {
  const video = document.getElementById('webcam');
  const placeholder = document.getElementById('cameraPlaceholder');

  try {
    videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
    video.srcObject = videoStream;
    video.classList.remove('d-none');
    placeholder.classList.add('d-none');
  } catch (err) {
    showAlert('Camera device could not be accessed.', 'danger');
  }
}

function stopCamera() {
  if (videoStream) {
    videoStream.getTracks().forEach(track => track.stop());
    videoStream = null;
  }
}

function toggleLanguage(lang) {
  currentLang = lang;
  
  // 1. Translate all DOM elements containing [data-i18n]
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (typeof translations !== 'undefined' && translations[lang] && translations[lang][key]) {
      el.innerText = translations[lang][key];
    }
  });

  // 2. Re-render dynamic components with localized content
  updateNavbar();
  populateCategoryDropdowns();
  if (currentUser && currentUser.role === "Administrator" && typeof renderAdminTables === 'function') {
    renderAdminTables();
  }
  if (document.getElementById('notificationsContainer') && typeof renderNotifications === 'function') {
    renderNotifications();
  }
}

function populateCategoryDropdowns() {
  if (typeof db === 'undefined' || !db.categories) return;

  const placeholderText = (typeof currentLang !== 'undefined' && currentLang === 'fr') 
    ? 'Sélectionner une catégorie...' 
    : 'Select Category...';

  const optionsHtml = `<option value="">${placeholderText}</option>` + 
    db.categories.map(c => `<option value="${c}">${c}</option>`).join('');

  ['repType', 'searchType', 'decType'].forEach(id => {
    const select = document.getElementById(id);
    if (select) select.innerHTML = optionsHtml;
  });

  const filterSelect = document.getElementById('adminTypeFilter');
  if (filterSelect) {
    const allCategoriesText = (typeof currentLang !== 'undefined' && currentLang === 'fr') 
      ? 'Toutes les catégories' 
      : 'All Categories';
    filterSelect.innerHTML = `<option value="">${allCategoriesText}</option>` + 
      db.categories.map(c => `<option value="${c}">${c}</option>`).join('');
  }
}

function renderNotifications() {
  const container = document.getElementById('notificationsContainer');
  if (!container || typeof db === 'undefined' || !db.notifications) return;

  if (db.notifications.length === 0) {
    const emptyMsg = (typeof currentLang !== 'undefined' && currentLang === 'fr') 
      ? 'Aucune nouvelle notification.' 
      : 'No new notifications.';
    container.innerHTML = `<p class="text-muted text-center my-3">${emptyMsg}</p>`;
    return;
  }

  const btnViewText = (typeof currentLang !== 'undefined' && currentLang === 'fr') ? 'Voir Correspondance' : 'View Match';

  container.innerHTML = db.notifications.map(n => `
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div><i class="fa-solid fa-bell me-2"></i>${n.message[currentLang] || n.message['en'] || n.message}</div>
      <button class="btn btn-sm btn-teal" onclick="viewMatchFromNotif(${n.matchedDoc.id})">${btnViewText}</button>
    </div>
  `).join('');
}

// Admin Tabs Navigation
function showAdminTab(tabId, btnElement) {
  document.querySelectorAll('.admin-tab').forEach(tab => tab.classList.add('d-none'));
  const targetTab = document.getElementById(tabId);
  if (targetTab) targetTab.classList.remove('d-none');

  if (btnElement) {
    const group = btnElement.parentElement;
    group.querySelectorAll('.list-group-item').forEach(b => b.classList.remove('active', 'bg-navy', 'border-navy'));
    btnElement.classList.add('active', 'bg-navy', 'border-navy');
  }
}