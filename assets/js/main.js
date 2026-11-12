// assets/js/main.js - Unified Client Interaction for CampusForum

// 1. Theme Switcher Logic
function getSavedTheme() {
  return localStorage.getItem('campus_theme') || 'dark';
}

function updateThemeUI(theme) {
  const sunIcons = document.querySelectorAll('.theme-icon-sun');
  const moonIcons = document.querySelectorAll('.theme-icon-moon');
  const labelTexts = document.querySelectorAll('.theme-label-text, #theme-label-text');
  const themeSwitchPill = document.getElementById('mobile-theme-switch-pill');

  if (theme === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
    sunIcons.forEach(el => el.style.display = 'block');
    moonIcons.forEach(el => el.style.display = 'none');
    labelTexts.forEach(el => el.textContent = 'Theme: Light');
    if (themeSwitchPill) themeSwitchPill.classList.add('active');
  } else {
    document.documentElement.setAttribute('data-theme', 'dark');
    sunIcons.forEach(el => el.style.display = 'none');
    moonIcons.forEach(el => el.style.display = 'block');
    labelTexts.forEach(el => el.textContent = 'Theme: Dark');
    if (themeSwitchPill) themeSwitchPill.classList.remove('active');
  }
}

function toggleTheme() {
  const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('campus_theme', newTheme);
  updateThemeUI(newTheme);
  showToast(`Switched to ${newTheme.toUpperCase()} mode`);
}

document.addEventListener('DOMContentLoaded', () => {
  updateThemeUI(getSavedTheme());
});

// 2. Global Click Listener to Close Popups
document.addEventListener('click', (e) => {
  // Close three dots dropdowns
  if (!e.target.closest('.post-actions-menu')) {
    document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));
  }

  // Close profile dropdown
  if (!e.target.closest('.user-profile-bar')) {
    const profileDropdown = document.getElementById('profile-dropdown-menu');
    if (profileDropdown) profileDropdown.classList.remove('active');
  }
});

// 4. Three Dots Menu Toggle
function togglePostMenu(e, menuId) {
  e.stopPropagation();
  const currentMenu = document.getElementById(menuId);
  const isOpen = currentMenu && currentMenu.classList.contains('show');

  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));

  if (currentMenu && !isOpen) {
    currentMenu.classList.add('show');
  }
}

// Profile Bar Menu Toggle
function toggleProfileDropdown(e) {
  e.stopPropagation();
  const menu = document.getElementById('profile-dropdown-menu');
  if (menu) menu.classList.toggle('active');
}

// 5. Modals Management
function openChooseGroupModal() {
  const modal = document.getElementById('choose-group-modal');
  if (!modal) return;
  modal.classList.add('active');
  const searchInput = document.getElementById('choose-group-search');
  if (searchInput) {
    searchInput.value = '';
    document.querySelectorAll('#choose-group-list .choose-group-item').forEach(el => {
      el.style.display = '';
    });
    setTimeout(() => searchInput.focus(), 80);
  }
}

function closeChooseGroupModal() {
  const modal = document.getElementById('choose-group-modal');
  if (modal) modal.classList.remove('active');
}

function selectComposerGroup(groupId, groupName, imgUrl) {
  const hiddenInput = document.getElementById('post-group-id');
  const labelEl = document.getElementById('composer-selected-group-label');
  const iconWrap = document.getElementById('composer-selected-group-icon');
  const trigger = document.getElementById('group-picker-trigger');

  if (hiddenInput) hiddenInput.value = groupId ? String(groupId) : '';
  if (labelEl) labelEl.textContent = groupName || 'Select Group';

  if (iconWrap) {
    if (groupId && imgUrl) {
      iconWrap.innerHTML = `<img src="${imgUrl.replace(/"/g, '&quot;')}" alt="">`;
      iconWrap.style.display = 'inline-flex';
    } else if (groupId) {
      iconWrap.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>`;
      iconWrap.style.display = 'inline-flex';
    } else {
      iconWrap.innerHTML = '';
      iconWrap.style.display = 'none';
    }
  }

  document.querySelectorAll('#choose-group-list .choose-group-item').forEach(el => {
    el.classList.toggle('is-selected', el.getAttribute('data-id') === String(groupId));
  });

  if (trigger) trigger.classList.remove('has-error');
  clearCreatePostErrors();
  closeChooseGroupModal();
}

function clearCreatePostErrors() {
  const trigger = document.getElementById('group-picker-trigger');
  const errEl = document.getElementById('create-post-group-error');
  const fbEl = document.getElementById('create-post-form-feedback');
  if (trigger) trigger.classList.remove('has-error');
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  if (fbEl) { fbEl.style.display = 'none'; fbEl.textContent = ''; fbEl.classList.remove('is-success'); }
}

function openCreateThreadModal(groupId) {
  const modal = document.getElementById('create-thread-modal');
  if (modal) {
    clearCreatePostErrors();
    const hiddenInput = document.getElementById('post-group-id');
    const targetId = groupId ? String(groupId) : (hiddenInput ? hiddenInput.value : '');
    if (targetId) {
      const item = document.querySelector(`#choose-group-list .choose-group-item[data-id="${targetId}"]`);
      if (item) {
        selectComposerGroup(targetId, item.getAttribute('data-name') || '', item.getAttribute('data-image') || '');
      }
    } else {
      selectComposerGroup('', 'Select Group', '');
    }
    modal.classList.add('active');
    setTimeout(() => {
      const input = document.getElementById('post-title');
      if (input) input.focus();
    }, 100);
  } else {
    window.location.href = 'register.php';
  }
}

function closeCreateThreadModal() {
  const modal = document.getElementById('create-thread-modal');
  if (modal) modal.classList.remove('active');
  const editor = document.getElementById('post-content-editor');
  if (editor) {
    editor.innerHTML = '';
    if (typeof syncRichEditor === 'function') syncRichEditor(editor);
  }
}

function openEditPostModal(idOrBtn, title, content) {
  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));
  
  const modal = document.getElementById('edit-post-modal');
  if (!modal) return;

  let id = idOrBtn;
  let rawImages = [];
  if (typeof idOrBtn === 'object' && idOrBtn !== null) {
    const btn = idOrBtn;
    id = btn.getAttribute('data-id');
    title = btn.getAttribute('data-title') || '';
    content = btn.getAttribute('data-content') || '';
    const imgData = btn.getAttribute('data-images');
    if (imgData) {
      try {
        rawImages = JSON.parse(imgData);
      } catch (e) {
        rawImages = [];
      }
    }
  }

  document.getElementById('edit-post-id').value = id || '';
  document.getElementById('edit-post-title').value = title || '';
  document.getElementById('edit-post-content').value = content || '';
  const editEditor = document.getElementById('edit-post-content-editor');
  if (editEditor) {
    editEditor.innerHTML = content || '';
    if (typeof syncRichEditor === 'function') syncRichEditor(editEditor);
  }
  
  // Remove any previously appended delete_image_ids hidden inputs
  const form = document.getElementById('edit-post-form');
  if (form) {
    form.querySelectorAll('input[name="delete_image_ids[]"]').forEach(el => el.remove());
  }

  // Render current existing images with delete/remove button
  const existingGroup = document.getElementById('edit-existing-images-group');
  const existingContainer = document.getElementById('edit-existing-images-container');
  if (existingGroup && existingContainer) {
    existingContainer.innerHTML = '';
    if (rawImages && rawImages.length > 0) {
      existingGroup.style.display = 'block';
      rawImages.forEach(img => {
        const item = document.createElement('div');
        item.className = 'existing-img-card';
        item.id = `existing-img-${img.id}`;
        
        item.innerHTML = `
          <img src="${img.url}" alt="Post image">
          <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${img.id}, this)" title="Remove image">✕</button>
        `;
        existingContainer.appendChild(item);
      });
    } else {
      existingGroup.style.display = 'none';
    }
  }

  // Reset file input and preview strip for new uploads
  const editPreviewStrip = document.getElementById('edit-image-previews');
  if (editPreviewStrip) editPreviewStrip.innerHTML = '';
  const fileInput = document.getElementById('edit-images-input');
  if (fileInput) fileInput.value = '';

  modal.classList.add('active');
}

function removeExistingImage(imageId, btn) {
  const card = document.getElementById(`existing-img-${imageId}`);
  if (card) {
    card.classList.add('marked-for-delete');
    
    // Replace remove button with undo button
    btn.outerHTML = `
      <button type="button" class="btn-undo-existing-img" onclick="undoRemoveExistingImage(${imageId}, this)" title="Undo remove">↩</button>
      <div class="delete-badge">Delete</div>
    `;

    // Append hidden input to notify backend to delete this image
    const form = document.getElementById('edit-post-form');
    if (form && !document.getElementById(`input-del-img-${imageId}`)) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'delete_image_ids[]';
      hidden.id = `input-del-img-${imageId}`;
      hidden.value = imageId;
      form.appendChild(hidden);
    }
  }
}

function undoRemoveExistingImage(imageId, btn) {
  const card = document.getElementById(`existing-img-${imageId}`);
  if (card) {
    card.classList.remove('marked-for-delete');
    
    // Remove the delete badge and restore remove button
    const badge = card.querySelector('.delete-badge');
    if (badge) badge.remove();

    btn.outerHTML = `
      <button type="button" class="btn-remove-existing-img" onclick="removeExistingImage(${imageId}, this)" title="Remove image">✕</button>
    `;

    const hidden = document.getElementById(`input-del-img-${imageId}`);
    if (hidden) hidden.remove();
  }
}

function closeEditPostModal() {
  const modal = document.getElementById('edit-post-modal');
  if (modal) modal.classList.remove('active');
}

// 6. Delete Post Confirmation (Handled directly in index.php)
function confirmDeletePost(id) {
  if (confirm('Are you sure you want to delete this discussion? All replies will also be deleted.')) {
    window.location.href = `index.php?action=delete_post&id=${id}`;
  }
}


// 7. Inline Replies Accordion & Scroll Helpers
function toggleRepliesSection(postId) {
  const section = document.getElementById(`replies-section-${postId}`);
  if (!section) return;
  if (section.dataset.singlePost === '1') {
    const composerBox = document.getElementById(`reply-composer-box-${postId}`);
    if (!composerBox) return;
    const isBoxHidden = window.getComputedStyle(composerBox).display === 'none';
    composerBox.style.display = isBoxHidden ? 'block' : 'none';
    if (isBoxHidden) {
      const textarea = composerBox.querySelector('textarea');
      if (textarea) {
        setTimeout(() => {
          textarea.focus();
          textarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
      }
    }
    return;
  }
  const isHidden = window.getComputedStyle(section).display === 'none';
  section.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    const textarea = section.querySelector('textarea');
    if (textarea) {
      setTimeout(() => {
        textarea.focus();
        textarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 50);
    }
  }
}

function expandPost(postId) {
  const section = document.getElementById(`replies-section-${postId}`);
  if (section) section.style.display = 'block';
  const postEl = document.getElementById(`post-${postId}`);
  if (postEl) postEl.scrollIntoView({ behavior: 'smooth' });
}

function toggleNestedComposer(replyId) {
  const composer = document.getElementById(`nested-composer-${replyId}`);
  if (composer) {
    const isHidden = window.getComputedStyle(composer).display === 'none';
    composer.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
      const textarea = composer.querySelector('textarea');
      if (textarea) textarea.focus();
    }
  }
}

function toggleEditReplyForm(replyId) {
  const textEl = document.getElementById(`reply-text-${replyId}`);
  const formEl = document.getElementById(`reply-edit-form-${replyId}`);

  if (textEl && formEl) {
    const isHidden = window.getComputedStyle(formEl).display === 'none';
    formEl.style.display = isHidden ? 'block' : 'none';
    textEl.style.display = isHidden ? 'none' : 'block';
    if (isHidden) {
      const ta = formEl.querySelector('textarea');
      if (ta) ta.focus();
    }
  }
}

function handlePostCardClick(event, postId) {
  const selection = window.getSelection ? window.getSelection() : null;
  if (selection && selection.toString().length > 0) {
    return;
  }
  const target = event.target;
  if (target.closest('button, a, input, textarea, label, .post-actions-menu, .post-replies-collapse, .post-media-grid, .post-action-btn, .reply-card')) {
    return;
  }
  window.location.href = 'index.php?post=' + postId;
}

window.handlePostCardClick = handlePostCardClick;
window.toggleRepliesSection = toggleRepliesSection;
window.expandPost = expandPost;
window.toggleNestedComposer = toggleNestedComposer;
window.toggleEditReplyForm = toggleEditReplyForm;

// 8. Image Selection Previews
function handleImageSelection(input) {
  const previewContainer = document.getElementById('create-image-previews');
  if (!previewContainer) return;
  previewContainer.innerHTML = '';

  const files = Array.from(input.files);
  files.slice(0, 4).forEach((file, index) => {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb-container';
      thumb.innerHTML = `
        <img src="${e.target.result}" alt="Preview">
        <button type="button" class="btn-remove-thumb" onclick="removeSelectedFile(this, ${index})">✕</button>
      `;
      previewContainer.appendChild(thumb);
    };
    reader.readAsDataURL(file);
  });
}

function removeSelectedFile(btn, index) {
  btn.closest('.preview-thumb-container').remove();
  const input = document.getElementById('post-images-input');
  if (input) input.value = '';
}

function handleEditImageSelection(input) {
  const previewContainer = document.getElementById('edit-image-previews');
  if (!previewContainer) return;
  previewContainer.innerHTML = '';

  const files = Array.from(input.files);
  files.slice(0, 4).forEach((file, index) => {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb-container';
      thumb.style.cssText = 'position: relative; width: 70px; height: 70px; border-radius: 8px; overflow: hidden; display: inline-block; margin-right: 8px; margin-top: 8px; border: 1px solid var(--border-color);';
      thumb.innerHTML = `
        <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
        <button type="button" class="btn-remove-thumb" onclick="removeSelectedEditFile(this)" style="position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.75); color: #fff; border: none; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;">✕</button>
      `;
      previewContainer.appendChild(thumb);
    };
    reader.readAsDataURL(file);
  });
}

function removeSelectedEditFile(btn) {
  btn.closest('.preview-thumb-container').remove();
  const previewContainer = document.getElementById('edit-image-previews');
  if (previewContainer && previewContainer.children.length === 0) {
    const fileInput = document.getElementById('edit-images-input');
    if (fileInput) fileInput.value = '';
  }
}

window.openEditPostModal = openEditPostModal;
window.closeEditPostModal = closeEditPostModal;
window.removeExistingImage = removeExistingImage;
window.undoRemoveExistingImage = undoRemoveExistingImage;
window.handleEditImageSelection = handleEditImageSelection;
window.removeSelectedEditFile = removeSelectedEditFile;

function previewReplyImage(input, postId) {
  const strip = document.getElementById(`reply-preview-${postId}`);
  if (!strip) return;
  strip.innerHTML = '';
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = (e) => {
      strip.style.display = 'block';
      strip.innerHTML = `
        <div class="preview-thumb-container" style="max-width: 100px; position: relative;">
          <img src="${e.target.result}" alt="Attachment" style="border-radius: 6px; width: 100%;">
          <button type="button" class="btn-remove-thumb" onclick="clearReplyImage(${postId})">✕</button>
        </div>
      `;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function clearReplyImage(postId) {
  const strip = document.getElementById(`reply-preview-${postId}`);
  if (strip) {
    strip.style.display = 'none';
    strip.innerHTML = '';
  }
  const fileInput = document.querySelector(`#replies-section-${postId} input[type="file"]`);
  if (fileInput) fileInput.value = '';
}

// 9. Like & Bookmark Toggles (AJAX requests to index.php)
function toggleLikePost(e, postId) {
  if (e) e.stopPropagation();
  const btn = document.getElementById(`like-btn-${postId}`);
  const countSpan = document.getElementById(`like-count-${postId}`);

  const formData = new FormData();
  formData.append('action', 'toggle_like');
  formData.append('post_id', postId);

  fetch('index.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.redirect) {
      window.location.href = data.redirect;
      return;
    }
    if (data.success) {
      if (data.liked) {
        btn.classList.add('active-liked');
        btn.querySelector('svg').setAttribute('fill', 'currentColor');
      } else {
        btn.classList.remove('active-liked');
        btn.querySelector('svg').setAttribute('fill', 'none');
      }
      if (countSpan) countSpan.textContent = data.count > 0 ? data.count : '';
    }
  })
  .catch(err => {
    console.error('Like toggle error:', err);
  });
}

// 10. Image Lightbox
function openLightbox(imgSrc) {
  const overlay = document.getElementById('lightbox-overlay');
  const img = document.getElementById('lightbox-img');
  if (overlay && img) {
    img.src = imgSrc;
    overlay.classList.add('active');
  }
}

function closeLightbox(e) {
  const overlay = document.getElementById('lightbox-overlay');
  if (overlay) overlay.classList.remove('active');
}

// ESC Key Listener
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeCreateThreadModal();
    closeEditPostModal();
    closeLightbox();
  }
});

// Toast Helper
function showToast(message) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 2400);
}

// 11. X-Style Mobile Interactions
function openMobileDrawer() {
  const drawer = document.getElementById('mobile-drawer');
  const backdrop = document.getElementById('mobile-drawer-backdrop');
  if (drawer && backdrop) {
    drawer.classList.add('active');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeMobileDrawer() {
  const drawer = document.getElementById('mobile-drawer');
  const backdrop = document.getElementById('mobile-drawer-backdrop');
  if (drawer) drawer.classList.remove('active');
  if (backdrop) backdrop.classList.remove('active');
  document.body.style.overflow = '';
}

function toggleMobileSearch() {
  const searchDrawer = document.getElementById('mobile-bottom-search-drawer');
  const searchTab = document.getElementById('mobile-bottom-search-tab');
  if (!searchDrawer) return;

  const isOpen = searchDrawer.classList.contains('open');
  if (isOpen) {
    closeMobileSearch();
  } else {
    searchDrawer.classList.add('open');
    if (searchTab) searchTab.classList.add('active');
    setTimeout(() => {
      const input = document.getElementById('mobile-search-input');
      if (input) input.focus();
    }, 150);
  }
}

function closeMobileSearch() {
  const searchDrawer = document.getElementById('mobile-bottom-search-drawer');
  const searchTab = document.getElementById('mobile-bottom-search-tab');
  if (searchDrawer) searchDrawer.classList.remove('open');
  // keep searchTab active only if there is an active search query
  if (searchTab) {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('q')) {
      searchTab.classList.remove('active');
    }
  }
}

function submitMobileSearch(e) {
  const input = document.getElementById('mobile-search-input');
  if (!input) return true;
  const val = input.value.trim();
  if (!val) {
    e.preventDefault();
    window.location.href = 'index.php';
    return false;
  }
  return true;
}

function handleMobilePostClick() {
  const modal = document.getElementById('create-thread-modal');
  if (modal) {
    openCreateThreadModal();
  } else {
    window.location.href = 'index.php?compose=1';
  }
}

// Check for deep links like compose=1 or settings=1
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('compose') === '1') {
    if (typeof openCreateThreadModal === 'function') {
      setTimeout(() => openCreateThreadModal(), 200);
    }
  }
  if (params.get('settings') === '1') {
    if (typeof openEditProfileModal === 'function') {
      setTimeout(() => openEditProfileModal(), 200);
    }
  }

  // Admin Console: Live Group Filter
  const adminGroupsSearch = document.getElementById('admin-groups-search');
  const adminGroupsStatus = document.getElementById('admin-groups-status-filter');
  const adminGroupsTable = document.getElementById('admin-groups-table');
  if (adminGroupsTable && (adminGroupsSearch || adminGroupsStatus)) {
    const filterAdminGroups = () => {
      const q = (adminGroupsSearch ? adminGroupsSearch.value : '').trim().toLowerCase();
      const status = adminGroupsStatus ? adminGroupsStatus.value : 'all';
      adminGroupsTable.querySelectorAll('.admin-group-row, tbody tr').forEach((row) => {
        const text = row.textContent.toLowerCase();
        const rowStatus = row.getAttribute('data-status') || '';
        const matchesText = !q || text.includes(q);
        const matchesStatus = status === 'all' || rowStatus === status;
        row.style.display = (matchesText && matchesStatus) ? '' : 'none';
      });
    };
    if (adminGroupsSearch) adminGroupsSearch.addEventListener('input', filterAdminGroups);
    if (adminGroupsStatus) adminGroupsStatus.addEventListener('change', filterAdminGroups);

    // Custom Status Dropdown Interactions
    const statusDropdown = document.getElementById('admin-status-dropdown');
    const statusTrigger = document.getElementById('admin-status-trigger');
    const statusMenu = document.getElementById('admin-status-menu');
    const statusTriggerLabel = document.getElementById('admin-status-trigger-label');
    const statusTriggerDot = document.getElementById('admin-status-trigger-dot');
    if (statusDropdown && statusTrigger && statusMenu && adminGroupsStatus) {
      const closeStatusMenu = () => {
        statusDropdown.classList.remove('open');
        statusTrigger.setAttribute('aria-expanded', 'false');
      };

      statusTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = statusDropdown.classList.toggle('open');
        statusTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      statusMenu.querySelectorAll('.admin-status-option').forEach((opt) => {
        opt.addEventListener('click', () => {
          const val = opt.getAttribute('data-value') || 'all';
          const label = opt.getAttribute('data-label') || 'All Statuses';
          const dotClass = opt.getAttribute('data-dot') || 'dot-all';

          adminGroupsStatus.value = val;
          if (statusTriggerLabel) statusTriggerLabel.textContent = label;
          if (statusTriggerDot) statusTriggerDot.className = 'admin-status-dot ' + dotClass;

          statusMenu.querySelectorAll('.admin-status-option').forEach((o) => {
            const isSelected = o === opt;
            o.classList.toggle('active', isSelected);
            o.setAttribute('aria-selected', isSelected ? 'true' : 'false');
          });

          closeStatusMenu();
          filterAdminGroups();
        });
      });

      document.addEventListener('click', (e) => {
        if (!statusDropdown.contains(e.target)) closeStatusMenu();
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && statusDropdown.classList.contains('open')) {
          closeStatusMenu();
          statusTrigger.focus();
        }
      });
    }
  }

  // Custom Group Image Upload Controls (Create & Edit Group Modals)
  document.querySelectorAll('[data-upload-control]').forEach((control) => {
    const input = control.querySelector('input[type="file"]');
    const preview = control.querySelector('[data-upload-preview]');
    const filenameEl = control.querySelector('[data-upload-filename]');
    const clearBtn = control.querySelector('[data-upload-clear]');
    if (!input || !preview || !filenameEl) return;

    const defaultIconHtml = preview.innerHTML;
    const defaultLabel = filenameEl.textContent;

    const resetUpload = () => {
      input.value = '';
      preview.innerHTML = defaultIconHtml;
      filenameEl.textContent = defaultLabel;
      if (clearBtn) clearBtn.style.display = 'none';
    };

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) {
        resetUpload();
        return;
      }
      filenameEl.textContent = file.name;
      if (clearBtn) clearBtn.style.display = 'inline-flex';
      if (file.type && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (ev) => {
          preview.innerHTML = `<img src="${ev.target.result}" alt="Selected group image">`;
        };
        reader.readAsDataURL(file);
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetUpload();
      });
    }
  });

  // Custom Modal Status Dropdowns (Create & Edit Group Modals)
  const syncModalDropdown = (dropdown, value) => {
    const hiddenInput = dropdown.querySelector('input[type="hidden"]');
    const triggerLabel = dropdown.querySelector('[data-status-label]');
    const triggerDot = dropdown.querySelector('[data-status-dot]');
    const options = dropdown.querySelectorAll('.admin-status-option');
    let matched = null;
    options.forEach((opt) => {
      const isSelected = opt.getAttribute('data-value') === value;
      opt.classList.toggle('active', isSelected);
      opt.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      if (isSelected) matched = opt;
    });
    if (matched) {
      if (hiddenInput) hiddenInput.value = value;
      if (triggerLabel) triggerLabel.textContent = matched.getAttribute('data-label') || '';
      if (triggerDot) triggerDot.className = 'admin-status-dot ' + (matched.getAttribute('data-dot') || 'dot-active');
    }
  };

  window.syncModalStatusDropdown = (inputId, value) => {
    const input = document.getElementById(inputId);
    const dropdown = input ? input.closest('[data-modal-status-dropdown]') : null;
    if (dropdown) syncModalDropdown(dropdown, value);
  };

  document.querySelectorAll('[data-modal-status-dropdown]').forEach((dropdown) => {
    const trigger = dropdown.querySelector('.admin-status-trigger');
    const options = dropdown.querySelectorAll('.admin-status-option');
    if (!trigger) return;

    const closeMenu = () => {
      dropdown.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = dropdown.classList.toggle('open');
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    options.forEach((opt) => {
      opt.addEventListener('click', () => {
        syncModalDropdown(dropdown, opt.getAttribute('data-value') || 'active');
        closeMenu();
      });
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) closeMenu();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && dropdown.classList.contains('open')) {
        closeMenu();
        trigger.focus();
      }
    });
  });

  // Admin Console: Live Moderation Post Search
  const adminPostsSearch = document.getElementById('admin-posts-search');
  const adminPostsTable = document.getElementById('admin-posts-table');
  if (adminPostsSearch && adminPostsTable) {
    adminPostsSearch.addEventListener('input', () => {
      const q = adminPostsSearch.value.trim().toLowerCase();
      adminPostsTable.querySelectorAll('tbody tr').forEach((row) => {
        row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
      });
    });
  }

  // Admin Subnav Active State
  const subnavLinks = document.querySelectorAll('.admin-subnav-link');
  subnavLinks.forEach((link) => {
    link.addEventListener('click', () => {
      subnavLinks.forEach((l) => l.classList.remove('active'));
      link.classList.add('active');
    });
  });

  // Groups Directory: Live Search
  const groupsSearch = document.getElementById('groups-directory-search');
  const groupsGrid = document.getElementById('groups-directory-grid');
  if (groupsSearch && groupsGrid) {
    groupsSearch.addEventListener('input', () => {
      const q = groupsSearch.value.trim().toLowerCase();
      groupsGrid.querySelectorAll('.group-card').forEach((card) => {
        card.style.display = (!q || card.textContent.toLowerCase().includes(q)) ? '' : 'none';
      });
    });
  }

  // Choose Group Popup: Live Search
  const chooseGroupSearch = document.getElementById('choose-group-search');
  if (chooseGroupSearch) {
    chooseGroupSearch.addEventListener('input', () => {
      const q = chooseGroupSearch.value.trim().toLowerCase();
      document.querySelectorAll('#choose-group-list .choose-group-item').forEach((item) => {
        item.style.display = (!q || item.textContent.toLowerCase().includes(q)) ? '' : 'none';
      });
    });
  }

  // Create Post Modal: Group selection, validation & AJAX submit
  const createPostForm = document.getElementById('create-post-form');
  const postGroupInput = document.getElementById('post-group-id');
  if (postGroupInput && postGroupInput.value) {
    const preItem = document.querySelector(`#choose-group-list .choose-group-item[data-id="${postGroupInput.value}"]`);
    if (preItem) {
      selectComposerGroup(postGroupInput.value, preItem.getAttribute('data-name') || '', preItem.getAttribute('data-image') || '');
    }
  }

  if (createPostForm) {
    createPostForm.setAttribute('novalidate', 'novalidate');
    createPostForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (createPostForm.dataset.submitting === '1') return;

      clearCreatePostErrors();
      const groupVal = postGroupInput ? postGroupInput.value.trim() : '';
      const titleInput = document.getElementById('post-title');
      const contentInput = document.getElementById('post-content');
      const postEditor = document.getElementById('post-content-editor');
      if (postEditor && typeof syncRichEditor === 'function') {
        syncRichEditor(postEditor);
      }
      const submitBtn = document.getElementById('btn-submit-post');
      const trigger = document.getElementById('group-picker-trigger');
      const groupErr = document.getElementById('create-post-group-error');
      const feedbackEl = document.getElementById('create-post-form-feedback');

      if (!groupVal) {
        if (trigger) trigger.classList.add('has-error');
        if (groupErr) {
          groupErr.textContent = 'Please select a group before publishing your discussion.';
          groupErr.style.display = 'block';
        }
        if (trigger) trigger.focus();
        return;
      }

      if (!titleInput || !titleInput.value.trim() || !contentInput || !contentInput.value.trim()) {
        if (feedbackEl) {
          feedbackEl.textContent = 'Please enter both a discussion title and content.';
          feedbackEl.style.display = 'block';
        }
        if (titleInput && !titleInput.value.trim()) titleInput.focus();
        else if (contentInput) contentInput.focus();
        return;
      }

      createPostForm.dataset.submitting = '1';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Publishing...';
      }

      try {
        const res = await fetch(createPostForm.getAttribute('action') || 'index.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(createPostForm)
        });
        const data = await res.json();

        if (!data.success) {
          if (data.field === 'group_id' && trigger && groupErr) {
            trigger.classList.add('has-error');
            groupErr.textContent = data.message || 'Please select a group before publishing your discussion.';
            groupErr.style.display = 'block';
          } else if (feedbackEl) {
            feedbackEl.textContent = data.message || 'Unable to publish discussion. Please try again.';
            feedbackEl.style.display = 'block';
          }
          createPostForm.dataset.submitting = '0';
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Post';
          }
          return;
        }

        if (feedbackEl) {
          feedbackEl.classList.add('is-success');
          feedbackEl.textContent = data.message || 'Discussion published!';
          feedbackEl.style.display = 'block';
        }
        setTimeout(() => {
          closeCreateThreadModal();
          window.location.href = data.redirect || 'index.php';
        }, 250);
      } catch (err) {
        if (feedbackEl) {
          feedbackEl.textContent = 'Network error while publishing. Your text has been preserved.';
          feedbackEl.style.display = 'block';
        }
        createPostForm.dataset.submitting = '0';
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Post';
        }
      }
    });
  }
});

// ========================================================
// Visual Rich Post Composer Formatting Engine (WYSIWYG Bold, Italic, Strike, Heading)
// ========================================================

function syncRichEditor(editor) {
  if (!editor) return;
  const targetId = editor.getAttribute('data-sync');
  let textarea = targetId ? document.getElementById(targetId) : null;
  if (!textarea && editor.parentElement) {
    textarea = editor.parentElement.querySelector('textarea');
  }
  if (!textarea) return;

  const text = editor.innerText.trim();
  if (text.length === 0) {
    textarea.value = '';
    editor.setAttribute('data-empty', 'true');
  } else {
    textarea.value = editor.innerHTML;
    editor.removeAttribute('data-empty');
  }
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function getActiveRichEditor(toolbar) {
  // 1. Check focused element
  const active = document.activeElement;
  if (active && active.classList.contains('rich-editor')) return active;

  // 2. Check toolbar data-target
  if (toolbar) {
    const targetId = toolbar.getAttribute('data-target');
    if (targetId) {
      const editor = document.getElementById(targetId + '-editor') || document.getElementById(targetId);
      if (editor && editor.classList.contains('rich-editor')) return editor;
    }
    const container = toolbar.closest('form') || toolbar.closest('.x-compose-dialog') || toolbar.closest('.modal-dialog');
    if (container) {
      const editor = container.querySelector('.rich-editor');
      if (editor) return editor;
    }
  }

  // 3. Fallback to open modals
  const activeModal = document.querySelector('.modal-overlay.active');
  if (activeModal) {
    const editor = activeModal.querySelector('.rich-editor');
    if (editor) return editor;
  }

  return document.getElementById('post-content-editor');
}

function applyVisualFormat(editor, formatType) {
  if (!editor) return;
  editor.focus();

  const selection = window.getSelection();
  const hasSelection = (selection && !selection.isCollapsed && selection.toString().length > 0);

  if (formatType === 'heading') {
    let inHeading = false;
    if (selection && selection.anchorNode) {
      const parent = selection.anchorNode.nodeType === 1 ? selection.anchorNode : selection.anchorNode.parentElement;
      if (parent && parent.closest('h1, h2, h3, h4, h5, h6')) inHeading = true;
    }
    if (inHeading) {
      document.execCommand('formatBlock', false, '<p>');
    } else {
      if (!hasSelection) {
        document.execCommand('insertHTML', false, '<h3>Heading</h3>');
      } else {
        document.execCommand('formatBlock', false, '<h3>');
      }
    }
  } else if (formatType === 'bold') {
    if (!hasSelection) {
      document.execCommand('insertHTML', false, '<b>bold text</b>');
    } else {
      document.execCommand('bold', false, null);
    }
  } else if (formatType === 'italic') {
    if (!hasSelection) {
      document.execCommand('insertHTML', false, '<i>italic text</i>');
    } else {
      document.execCommand('italic', false, null);
    }
  } else if (formatType === 'strike') {
    if (!hasSelection) {
      document.execCommand('insertHTML', false, '<s>strikethrough text</s>');
    } else {
      document.execCommand('strikeThrough', false, null);
    }
  }

  syncRichEditor(editor);
}

// Prevent format buttons from stealing selection focus on mousedown
document.addEventListener('mousedown', (e) => {
  const btn = e.target.closest('.format-btn');
  if (btn) {
    e.preventDefault();
  }
});

// Click listener for format buttons
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.format-btn');
  if (!btn) return;
  e.preventDefault();
  e.stopPropagation();

  const toolbar = btn.closest('.format-toolbar');
  const format = btn.getAttribute('data-format');

  // Quick composer trigger
  if (toolbar && toolbar.closest('.x-quick-composer')) {
    if (typeof openCreateThreadModal === 'function') {
      openCreateThreadModal();
      setTimeout(() => {
        const editor = document.getElementById('post-content-editor');
        if (editor) applyVisualFormat(editor, format);
      }, 100);
      return;
    }
  }

  const editor = getActiveRichEditor(toolbar);
  if (editor && editor.classList.contains('rich-editor')) {
    applyVisualFormat(editor, format);
    return;
  }

  // Plain textarea fallback if used outside rich-editor
  const targetId = toolbar ? toolbar.getAttribute('data-target') : null;
  const textarea = targetId ? document.getElementById(targetId) : (toolbar ? toolbar.closest('form')?.querySelector('textarea') : null);
  if (textarea) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? 0;
    const val = textarea.value;
    const selected = val.substring(start, end);
    const wrap = { bold: ['**', '**'], italic: ['*', '*'], strike: ['~~', '~~'], heading: ['## ', ''] }[format] || ['**', '**'];
    const replacement = selected.length > 0 ? (wrap[0] + selected + wrap[1]) : (wrap[0] + format + ' text' + wrap[1]);
    textarea.value = val.substring(0, start) + replacement + val.substring(end);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
});

// Synchronize rich-editor elements on input, keyup, paste, and blur
document.addEventListener('input', (e) => {
  if (e.target && e.target.classList.contains('rich-editor')) {
    syncRichEditor(e.target);
  }
});

document.addEventListener('blur', (e) => {
  if (e.target && e.target.classList.contains('rich-editor')) {
    syncRichEditor(e.target);
  }
}, true);

// Ensure forms sync editors before submit
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (form) {
    form.querySelectorAll('.rich-editor').forEach(syncRichEditor);
  }
});

window.syncRichEditor = syncRichEditor;
window.applyVisualFormat = applyVisualFormat;
