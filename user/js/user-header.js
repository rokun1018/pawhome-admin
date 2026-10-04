// Profile menu in the header (opens on click, closes when clicking elsewhere)
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function (event) {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown && !event.target.closest('.profile-dropdown-container')) dropdown.style.display = 'none';
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target.id === 'profileAvatarHeader') toggleProfileDropdown();
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});
