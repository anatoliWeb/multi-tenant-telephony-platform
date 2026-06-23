const STORAGE_KEY = 'admin.sidebar.collapsed';

function applySidebarState(layout, isCollapsed) {
  layout.classList.toggle('is-sidebar-collapsed', isCollapsed);
}

function initAdminSidebar() {
  const layout = document.querySelector('[data-admin-layout]');
  const toggleButton = document.querySelector('[data-sidebar-toggle]');

  if (!layout || !toggleButton) {
    return;
  }

  const savedState = localStorage.getItem(STORAGE_KEY);
  const isCollapsed = savedState === '1';

  applySidebarState(layout, isCollapsed);

  toggleButton.addEventListener('click', () => {
    const nextState = !layout.classList.contains('is-sidebar-collapsed');
    applySidebarState(layout, nextState);
    localStorage.setItem(STORAGE_KEY, nextState ? '1' : '0');
  });
}

document.querySelectorAll('.activity-item').forEach(item => {
    item.addEventListener('mouseenter', () => {
        item.style.transform = 'translateX(4px)';
    });

    item.addEventListener('mouseleave', () => {
        item.style.transform = 'translateX(0)';
    });
});

document.addEventListener('DOMContentLoaded', initAdminSidebar);
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.querySelectorAll('.c-alert').forEach((el) => el.remove());
  }, 3000);
});
