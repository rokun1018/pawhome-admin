// ============================================
// PROFILE PAGE: tabs and the edit form.
// The data itself is printed by profile.php from the database.
// ============================================

/**
 * Switch between tabs without reloading (the links also work without JavaScript)
 */
function switchTab(tabName, link) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));

    const tabId = tabName === 'personal' ? 'personal-info-tab' :
                  tabName === 'adoption' ? 'adoption-history-tab' :
                  tabName === 'applications' ? 'applications-tab' :
                  'preferences-tab';
    document.getElementById(tabId).classList.add('active');
    if (link) link.classList.add('active');
    history.replaceState(null, '', '?tab=' + tabName);
}

document.querySelectorAll('.menu-item[data-tab]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        switchTab(link.dataset.tab, link);
    });
});

/**
 * Show or hide the edit form for personal information
 */
function editProfile(show = true) {
    document.getElementById('profileView').hidden = show;
    document.getElementById('profileEdit').hidden = !show;
    document.getElementById('editProfileBtn').hidden = show;
    if (show) document.getElementById('pfName').focus();
}
