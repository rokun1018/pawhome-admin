// ============================================
// ADOPTION APPLICATION FUNCTIONS
// ============================================

// The questions are printed by adoption-application.php (ADOPTION_PHASES),
// so the browser and the server always check the same list.
const adoptionPhases = ADOPTION_PHASES;

function escapeHTML(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

let currentPhase = 0;
let applicationData = {};

/**
 * Initialize adoption application
 */
function initializeAdoption() {
    currentPhase = 0;
    applicationData = Object.assign({}, ADOPTION_PREFILL); // name, email, phone, address from the account
    renderPhase();
}

/**
 * Render current phase
 */
function renderPhase() {
    const phase = adoptionPhases[currentPhase];
    const formContainer = document.getElementById("formContainer");
    
    let html = `
        <h2>${escapeHTML(phase.title)}</h2>
        <p>${escapeHTML(phase.description)}</p>
    `;

    phase.questions.forEach(question => {
        html += renderQuestion(question);
    });

    formContainer.innerHTML = html;
    updateProgress();
    attachEventListeners();
}

/**
 * Render individual question
 */
function renderQuestion(question) {
    const value = applicationData[question.id] || "";
    
    if (question.type === "buttons") {
        return `
            <div class="form-question">
                <label>${question.label}</label>
                <div class="button-group">
                    ${question.options.map(opt => `
                        <button type="button" class="option-btn ${value === opt.value ? 'selected' : ''}" 
                            onclick="selectOption('${question.id}', '${opt.value}', this)">
                            ${opt.label}
                        </button>
                    `).join('')}
                </div>
                <input type="hidden" id="${question.id}" value="${value}">
            </div>
        `;
    }
    else if (question.type === "quiz") {
        return `
            <div class="form-question">
                <label>${question.label}</label>
                <p>${question.subtitle}</p>
                <div class="button-group" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    ${question.options.map(opt => `
                        <button type="button" class="option-btn ${value === opt.value ? 'selected' : ''}" 
                            onclick="selectOption('${question.id}', '${opt.value}', this)"
                            style="padding: 1rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 2rem;">${opt.icon}</span>
                            <strong>${opt.label}</strong>
                            <span style="font-size: 0.8rem; color: #666;">${opt.desc}</span>
                        </button>
                    `).join('')}
                </div>
                <input type="hidden" id="${question.id}" value="${value}">
            </div>
        `;
    }
    else if (question.type === "select") {
        return `
            <div class="form-question">
                <label for="${question.id}">${escapeHTML(question.label)}</label>
                <select class="form-input" id="${question.id}">
                    <option value="">Choose a pet...</option>
                    ${question.options.map(opt => `<option value="${escapeHTML(opt.value)}" ${value === opt.value ? 'selected' : ''}>${escapeHTML(opt.label)}</option>`).join('')}
                </select>
            </div>
        `;
    }
    else if (question.type === "text" || question.type === "email" || question.type === "tel") {
        return `
            <div class="form-question">
                <label for="${question.id}">${escapeHTML(question.label)}</label>
                <input type="${question.type === 'email' ? 'email' : question.type === 'tel' ? 'tel' : 'text'}" 
                    class="form-input" 
                    id="${question.id}" 
                    placeholder="${escapeHTML(question.placeholder)}"
                    maxlength="255"
                    value="${escapeHTML(value)}">
            </div>
        `;
    }
    else if (question.type === "checkbox") {
        return `
            <div class="form-question">
                <div class="checkbox-item">
                    <input type="checkbox" id="${question.id}" ${value ? 'checked' : ''}>
                    <label for="${question.id}">${question.label}</label>
                </div>
            </div>
        `;
    }
}

/**
 * Select an option button
 */
function selectOption(fieldId, value, btn) {
    const siblings = btn.parentElement.querySelectorAll('.option-btn');
    siblings.forEach(s => s.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById(fieldId).value = value;
    applicationData[fieldId] = value;
}

/**
 * Attach event listeners to form inputs
 */
function attachEventListeners() {
    const inputs = document.querySelectorAll('.form-input, input[type="checkbox"]');
    inputs.forEach(input => {
        // "input" as well as "change", so typed text is kept even before leaving the box
        ['input', 'change'].forEach(evt => input.addEventListener(evt, (e) => {
            if (e.target.type === 'checkbox') {
                applicationData[e.target.id] = e.target.checked;
            } else {
                applicationData[e.target.id] = e.target.value;
            }
        }));
    });
}

/**
 * Validate current phase
 */
function validatePhase() {
    const phase = adoptionPhases[currentPhase];
    
    for (let question of phase.questions) {
        const value = applicationData[question.id];
        
        if (question.type === "checkbox") {
            if (!value) {
                alert(`Please agree to: ${question.label}`);
                return false;
            }
        } else if (!value && !question.optional) {
            alert(`Please answer: ${question.label}`);
            return false;
        }
        
        // Validate email format
        if (question.type === "email" && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                alert("Please enter a valid email address");
                return false;
            }
        }
        
        // Validate phone format
        if (question.type === "tel" && value) {
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(value)) {
                alert("Please enter a valid phone number");
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Move to next step
 */
function nextStep() {
    if (!validatePhase()) {
        return;
    }
    
    if (currentPhase < adoptionPhases.length - 1) {
        currentPhase++;
        renderPhase();
        window.scrollTo(0, 0);
    } else {
        // All phases complete
        submitApplication();
    }
}

/**
 * Move to previous step
 */
function previousStep() {
    if (currentPhase > 0) {
        currentPhase--;
        renderPhase();
        window.scrollTo(0, 0);
    }
}

/**
 * Update progress bar and steps
 */
function updateProgress() {
    const totalPhases = adoptionPhases.length;
    const progressPercent = ((currentPhase + 1) / totalPhases) * 100;
    document.getElementById("progressBar").style.width = progressPercent + "%";
    
    // Update step indicators
    let stepsHtml = '';
    for (let i = 0; i < totalPhases; i++) {
        const phase = adoptionPhases[i];
        let stepClass = 'step';
        let stepContent = i + 1;
        
        if (i < currentPhase) {
            stepClass += ' completed';
            stepContent = '✓';
        } else if (i === currentPhase) {
            stepClass += ' active';
        }
        
        stepsHtml += `
            <div class="${stepClass}">
                <div class="step-dot">${stepContent}</div>
                <p class="step-label">Step ${i + 1}: ${phase.title}</p>
            </div>
        `;
    }
    
    document.getElementById("progressSteps").innerHTML = stepsHtml;
    
    // Update button states
    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");
    
    if (currentPhase === 0) {
        prevBtn.style.display = "none";
    } else {
        prevBtn.style.display = "block";
    }
    
    if (currentPhase === totalPhases - 1) {
        nextBtn.textContent = "Submit Application";
    } else {
        nextBtn.textContent = "Next Step";
    }
}

/**
 * Submit application
 */
async function submitApplication() {
    const nextBtn = document.getElementById("nextBtn");
    const errorBox = document.getElementById("applicationError");
    errorBox.hidden = true;
    nextBtn.disabled = true;
    nextBtn.textContent = "Submitting…";

    try {
        const res = await fetch('adoption-application.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'fetch',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ pet_id: ADOPTION_PET_ID || applicationData.petId, answers: applicationData })
        });
        if (res.status === 401) { goToPage('login.php?next=adoption-application.php'); return; }
        const body = await res.json().catch(() => null);
        if (!body || !body.ok) throw new Error((body && body.error) || 'Something went wrong. Please try again.');
        goToPage('adoption-complete.php?id=' + body.id);
    } catch (err) {
        errorBox.textContent = err.message === 'Failed to fetch' ? 'Could not reach the server. Check your internet and try again.' : err.message;
        errorBox.hidden = false;
        nextBtn.disabled = false;
        nextBtn.textContent = "Submit Application";
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('formContainer')) {
        initializeAdoption();
    }
});