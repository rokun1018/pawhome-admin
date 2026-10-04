// ============================================
// NAVIGATION FUNCTIONS
// ============================================

/**
 * Navigate to a page
 * @param {string} page - Page file name
 */
function goToPage(page) {
    window.location.href = page;
}

/**
 * Get current page name
 * @returns {string} Current page
 */
function getCurrentPage() {
    return window.location.pathname.split('/').pop() || 'index.html';
}

/**
 * Store data in session
 * @param {string} key - Key name
 * @param {any} value - Value to store
 */
function setSessionData(key, value) {
    sessionStorage.setItem(key, JSON.stringify(value));
}

/**
 * Get data from session
 * @param {string} key - Key name
 * @returns {any} Stored value
 */
function getSessionData(key) {
    const data = sessionStorage.getItem(key);
    return data ? JSON.parse(data) : null;
}

/**
 * Find pet by ID
 * @param {number} petId - Pet ID
 * @returns {object} Pet object
 */
function findPetById(petId) {
    return petsData.find(pet => pet.id === petId);
}

/**
 * Get random item from array
 * @param {array} arr - Array
 * @returns {any} Random item
 */
function getRandomItem(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}