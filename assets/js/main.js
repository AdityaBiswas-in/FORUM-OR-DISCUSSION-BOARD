/* ==========================================================================
   QUADDIT PLATFORM - CLIENT INTERACTION ENGINE
   File: assets/js/main.js
   Purpose: Global client-side interactivity, theme switching, modal management,
            visual rich text editor, AJAX pagination, filters, and member moderation.
   ========================================================================== */

/* ==========================================================================
   MODULE 1: THEME & DISPLAY PREFERENCES
   Controls Light/Dark theme switching, persistence, and reactive UI icons.
   ========================================================================== */
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
      const editor = composer.querySelector('.rich-editor') || composer.querySelector('textarea');
      if (editor) editor.focus();
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
      const editor = formEl.querySelector('.rich-editor') || formEl.querySelector('textarea');
      if (editor) editor.focus();
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
    closeGroupMembersModal();
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
        const matchesStatus = status === 'all' || rowStatus === status || (status === 'public' && (rowStatus === 'public' || rowStatus === 'active'));
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

  /* ==========================================================================
     MODULE 10: ADMIN CONTENT MODERATION (SEARCH, FILTER & PAGINATION)
     Real-time client filtering by group, activity, keywords, and Google-style pagination.
     ========================================================================== */
  // Admin Console: Live Moderation Post Search, Filters & 15-per-page Pagination
  const adminPostsSearch = document.getElementById('admin-posts-search');
  const adminPostsGroup = document.getElementById('admin-posts-group-filter');
  const adminPostsActivity = document.getElementById('admin-posts-activity-filter');
  const adminPostsSort = document.getElementById('admin-posts-sort-filter');
  const adminPostsReset = document.getElementById('admin-posts-reset-filters');
  const adminPostsTable = document.getElementById('admin-posts-table');
  const adminPostsNoResults = document.getElementById('admin-posts-no-results');
  const adminPostsPagination = document.getElementById('admin-posts-pagination');
  const adminPostsPaginationControls = document.getElementById('admin-posts-pagination-controls');
  const adminPostsShowingStart = document.getElementById('admin-posts-showing-start');
  const adminPostsShowingEnd = document.getElementById('admin-posts-showing-end');
  const adminPostsTotalCount = document.getElementById('admin-posts-total-count');

  if (adminPostsTable && (adminPostsSearch || adminPostsGroup || adminPostsActivity || adminPostsSort)) {
    const PAGE_SIZE = 15;
    let currentModerationPage = 1;

    const renderPaginationControls = (totalPages, currentPage) => {
      if (!adminPostsPaginationControls) return;
      adminPostsPaginationControls.innerHTML = '';
      adminPostsPaginationControls.style.display = 'flex';

      // Previous button
      const prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.className = 'admin-page-btn' + (currentPage <= 1 ? ' disabled' : '');
      prevBtn.innerHTML = `&larr; Prev`;
      prevBtn.disabled = (currentPage <= 1);
      prevBtn.setAttribute('aria-label', 'Previous page');
      prevBtn.addEventListener('click', () => {
        if (currentModerationPage > 1) {
          currentModerationPage--;
          filterAdminPosts(false);
        }
      });
      adminPostsPaginationControls.appendChild(prevBtn);

      // Google-style window of page numbers
      const getPageList = () => {
        if (totalPages <= 7) {
          return Array.from({ length: Math.max(1, totalPages) }, (_, i) => i + 1);
        }
        if (currentPage <= 4) {
          return [1, 2, 3, 4, 5, '...', totalPages];
        }
        if (currentPage >= totalPages - 3) {
          return [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        }
        return [1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages];
      };

      const pages = getPageList();
      pages.forEach((p) => {
        if (p === '...') {
          const ellipsis = document.createElement('span');
          ellipsis.className = 'admin-page-ellipsis';
          ellipsis.textContent = '…';
          adminPostsPaginationControls.appendChild(ellipsis);
        } else {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'admin-page-btn' + (p === currentPage ? ' active' : '');
          btn.textContent = p;
          btn.setAttribute('aria-label', `Page ${p}`);
          if (p === currentPage) {
            btn.setAttribute('aria-current', 'page');
          } else {
            btn.addEventListener('click', () => {
              currentModerationPage = p;
              filterAdminPosts(false);
            });
          }
          adminPostsPaginationControls.appendChild(btn);
        }
      });

      // Next button
      const nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.className = 'admin-page-btn' + (currentPage >= totalPages ? ' disabled' : '');
      nextBtn.innerHTML = `Next &rarr;`;
      nextBtn.disabled = (currentPage >= totalPages);
      nextBtn.setAttribute('aria-label', 'Next page');
      nextBtn.addEventListener('click', () => {
        if (currentModerationPage < totalPages) {
          currentModerationPage++;
          filterAdminPosts(false);
        }
      });
      adminPostsPaginationControls.appendChild(nextBtn);
    };

    const filterAdminPosts = (resetPage = false) => {
      if (resetPage) {
        currentModerationPage = 1;
      }

      const q = (adminPostsSearch ? adminPostsSearch.value.trim().toLowerCase() : '');
      const selectedGroup = (adminPostsGroup ? adminPostsGroup.value : 'all');
      const selectedActivity = (adminPostsActivity ? adminPostsActivity.value : 'all');
      const selectedSort = (adminPostsSort ? adminPostsSort.value : 'newest');

      const tbody = adminPostsTable.querySelector('tbody');
      if (!tbody) return;

      const rows = Array.from(tbody.querySelectorAll('tr[data-post-id]'));
      const matchingRows = [];

      rows.forEach((row) => {
        const title = row.getAttribute('data-title') || '';
        const groupName = row.getAttribute('data-group-name') || '';
        const author = row.getAttribute('data-author') || '';
        const groupId = row.getAttribute('data-group-id') || '';
        const replies = parseInt(row.getAttribute('data-replies') || '0', 10);

        // Search match
        const matchesSearch = !q ||
          title.includes(q) ||
          groupName.includes(q) ||
          author.includes(q);

        // Group match
        const matchesGroup = (selectedGroup === 'all' || selectedGroup === groupId);

        // Activity match
        let matchesActivity = true;
        if (selectedActivity === 'has_replies') {
          matchesActivity = (replies > 0);
        } else if (selectedActivity === 'no_replies') {
          matchesActivity = (replies === 0);
        }

        if (matchesSearch && matchesGroup && matchesActivity) {
          matchingRows.push(row);
        } else {
          row.style.display = 'none';
        }
      });

      // Sort matching rows
      matchingRows.sort((a, b) => {
        const timeA = parseInt(a.getAttribute('data-timestamp') || '0', 10);
        const timeB = parseInt(b.getAttribute('data-timestamp') || '0', 10);
        const repA = parseInt(a.getAttribute('data-replies') || '0', 10);
        const repB = parseInt(b.getAttribute('data-replies') || '0', 10);

        if (selectedSort === 'newest') return timeB - timeA;
        if (selectedSort === 'oldest') return timeA - timeB;
        if (selectedSort === 'most_replies') return repB - repA || (timeB - timeA);
        if (selectedSort === 'fewest_replies') return repA - repB || (timeB - timeA);
        return 0;
      });

      // Re-append sorted rows into DOM
      matchingRows.forEach((r) => tbody.appendChild(r));

      // Calculate pagination
      const totalMatching = matchingRows.length;
      const totalPages = Math.ceil(totalMatching / PAGE_SIZE) || 1;
      if (currentModerationPage > totalPages) {
        currentModerationPage = totalPages;
      }
      if (currentModerationPage < 1) {
        currentModerationPage = 1;
      }

      const startIndex = (currentModerationPage - 1) * PAGE_SIZE;
      const endIndex = startIndex + PAGE_SIZE;

      matchingRows.forEach((row, idx) => {
        if (idx >= startIndex && idx < endIndex) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });

      // Empty State Handling
      if (adminPostsNoResults) {
        if (totalMatching === 0) {
          tbody.appendChild(adminPostsNoResults);
          adminPostsNoResults.style.display = '';
        } else {
          adminPostsNoResults.style.display = 'none';
        }
      }

      // Update Pagination summary info
      if (adminPostsPagination) {
        if (totalMatching === 0) {
          adminPostsPagination.style.display = 'none';
        } else {
          adminPostsPagination.style.display = 'flex';
          const displayStart = startIndex + 1;
          const displayEnd = Math.min(endIndex, totalMatching);
          if (adminPostsShowingStart) adminPostsShowingStart.textContent = displayStart;
          if (adminPostsShowingEnd) adminPostsShowingEnd.textContent = displayEnd;
          if (adminPostsTotalCount) adminPostsTotalCount.textContent = totalMatching;
          const curPageEl = document.getElementById('admin-posts-current-page');
          const totalPagesEl = document.getElementById('admin-posts-total-pages');
          if (curPageEl) curPageEl.textContent = currentModerationPage;
          if (totalPagesEl) totalPagesEl.textContent = totalPages;
        }
      }

      // Render Google-style page buttons
      renderPaginationControls(totalPages, currentModerationPage);

      // Toggle Reset Button
      if (adminPostsReset) {
        const isFiltered = (q !== '' || selectedGroup !== 'all' || selectedActivity !== 'all' || selectedSort !== 'newest');
        adminPostsReset.style.display = isFiltered ? 'inline-flex' : 'none';
      }
    };

    if (adminPostsSearch) adminPostsSearch.addEventListener('input', () => filterAdminPosts(true));
    if (adminPostsGroup) adminPostsGroup.addEventListener('change', () => filterAdminPosts(true));
    if (adminPostsActivity) adminPostsActivity.addEventListener('change', () => filterAdminPosts(true));
    if (adminPostsSort) adminPostsSort.addEventListener('change', () => filterAdminPosts(true));

    if (adminPostsReset) {
      adminPostsReset.addEventListener('click', () => {
        if (adminPostsSearch) adminPostsSearch.value = '';
        if (adminPostsGroup) adminPostsGroup.value = 'all';
        if (adminPostsActivity) adminPostsActivity.value = 'all';
        if (adminPostsSort) adminPostsSort.value = 'newest';
        filterAdminPosts(true);
      });
    }

    // Initial run to activate 15 items per page pagination
    filterAdminPosts(false);
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

/* ==========================================================================
   MODULE 11: VISUAL RICH-TEXT FORMATTING ENGINE (WYSIWYG)
   Handles inline formatting (Bold, Italic, Strikethrough, Heading, Lists, Links).
   ========================================================================== */

function syncRichEditor(editor) {
  if (!editor) return;
  const targetId = editor.getAttribute('data-sync');
  let textarea = targetId ? document.getElementById(targetId) : null;
  if (!textarea && editor.parentElement) {
    textarea = editor.parentElement.querySelector('textarea');
  }
  if (!textarea) return;

  const rawText = editor.innerText || '';
  const cleanText = rawText.replace(/\u200B/g, '').trim();
  const hasFormatting = editor.querySelector('ul, ol, li, h1, h2, h3, h4, h5, h6, s, strike, del, a, b, strong, i, em, u, img');

  if (cleanText.length === 0 && !hasFormatting) {
    textarea.value = '';
    editor.setAttribute('data-empty', 'true');
  } else if (cleanText.length === 0) {
    textarea.value = '';
    editor.removeAttribute('data-empty');
  } else {
    textarea.value = editor.innerHTML.replace(/\u200B/g, '');
    editor.removeAttribute('data-empty');
  }
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function getActiveRichEditor(toolbar) {
  // 1. Explicit data-target or container on the toolbar takes FIRST priority
  if (toolbar) {
    const targetId = toolbar.getAttribute('data-target');
    if (targetId) {
      const editor = document.getElementById(targetId + '-editor') || document.getElementById(targetId);
      if (editor && editor.classList && editor.classList.contains('rich-editor')) return editor;
    }
    const container = toolbar.closest('form') ||
                      toolbar.closest('.reply-composer-form') ||
                      toolbar.closest('.reply-composer-box') ||
                      toolbar.closest('.x-compose-dialog') ||
                      toolbar.closest('.modal-dialog') ||
                      toolbar.closest('.modal-body') ||
                      toolbar.parentElement;
    if (container) {
      const editor = container.querySelector('.rich-editor');
      if (editor) return editor;
    }
  }

  // 2. Focused element if it is a rich editor
  const active = document.activeElement;
  if (active && active.classList && active.classList.contains('rich-editor')) return active;

  // 3. Fallback to open modals
  const activeModal = document.querySelector('.modal-overlay.active, .modal-overlay[style*="display: block"], .modal-overlay[style*="display: flex"]');
  if (activeModal) {
    const editor = activeModal.querySelector('.rich-editor');
    if (editor) return editor;
  }

  // 4. Default post composer only if toolbar belongs to it or no specific toolbar
  if (!toolbar || toolbar.closest('#modal-create-thread') || toolbar.closest('.x-quick-composer') || toolbar.closest('.x-compose-box')) {
    const defaultPostEditor = document.getElementById('post-content-editor');
    if (defaultPostEditor) return defaultPostEditor;
  }

  return null;
}

function ensureEditorFocus(editor) {
  if (!editor) return;
  if (document.activeElement !== editor) {
    editor.focus();
  }
  const sel = window.getSelection();
  if (!sel || sel.rangeCount === 0 || !editor.contains(sel.anchorNode)) {
    const range = document.createRange();
    if (!editor.firstChild) {
      range.setStart(editor, 0);
      range.collapse(true);
    } else {
      range.selectNodeContents(editor);
      range.collapse(false);
    }
    if (sel) {
      sel.removeAllRanges();
      sel.addRange(range);
    }
  }
}

function updateToolbarStates(editor) {
  if (!editor) {
    editor = document.activeElement;
    if (!editor || !editor.classList || !editor.classList.contains('rich-editor')) return;
  }

  let isBold = false;
  let isItalic = false;
  let isUnderline = false;
  let isStrike = false;
  let isHeading = false;
  let isList = false;
  let isLink = false;

  const isFocused = (document.activeElement === editor || editor.contains(document.activeElement));

  if (isFocused) {
    try {
      isBold = document.queryCommandState('bold');
      isItalic = document.queryCommandState('italic');
      isUnderline = document.queryCommandState('underline');
      isStrike = document.queryCommandState('strikeThrough');
      isList = document.queryCommandState('insertUnorderedList');
    } catch (e) {}
  }

  const sel = window.getSelection();
  if (sel && sel.anchorNode && editor.contains(sel.anchorNode)) {
    const parent = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement;
    if (parent) {
      if (!isFocused) {
        if (parent.closest('b, strong')) isBold = true;
        if (parent.closest('i, em')) isItalic = true;
        if (parent.closest('u')) isUnderline = true;
        if (parent.closest('s, strike, del') || (window.getComputedStyle(parent).textDecorationLine || '').includes('line-through')) {
          isStrike = true;
        }
      }
      if (parent.closest('h1, h2, h3, h4, h5, h6')) {
        isHeading = true;
      }
      if (parent.closest('ul, ol, li')) {
        isList = true;
      }
      if (parent.closest('a')) {
        isLink = true;
      }
    }
  }

  const toolbars = document.querySelectorAll('.format-toolbar');
  toolbars.forEach(tb => {
    const targetId = tb.getAttribute('data-target');
    const matchesTarget = targetId && (editor.id === targetId || editor.id === targetId + '-editor');
    const sameDialog = (tb.closest('.modal-dialog') && tb.closest('.modal-dialog') === editor.closest('.modal-dialog')) ||
                       (tb.closest('form') && tb.closest('form') === editor.closest('form')) ||
                       (tb.closest('.reply-composer-box') && tb.closest('.reply-composer-box') === editor.closest('.reply-composer-box')) ||
                       (tb.closest('.reply-composer-form') && tb.closest('.reply-composer-form') === editor.closest('.reply-composer-form')) ||
                       (tb.closest('.reply-card') && tb.closest('.reply-card') === editor.closest('.reply-card'));

    if (matchesTarget || sameDialog || (!targetId && !editor.id) || (!targetId && editor.id === 'post-content-editor')) {
      const bBtn = tb.querySelector('[data-format="bold"]');
      const iBtn = tb.querySelector('[data-format="italic"]');
      const uBtn = tb.querySelector('[data-format="underline"]');
      const sBtn = tb.querySelector('[data-format="strike"]');
      const hBtn = tb.querySelector('[data-format="heading"]');
      const lBtn = tb.querySelector('[data-format="bullet"], [data-format="list"]');
      const aBtn = tb.querySelector('[data-format="link"]');

      if (bBtn) bBtn.classList.toggle('active', !!isBold);
      if (iBtn) iBtn.classList.toggle('active', !!isItalic);
      if (uBtn) uBtn.classList.toggle('active', !!isUnderline);
      if (sBtn) sBtn.classList.toggle('active', !!isStrike);
      if (hBtn) hBtn.classList.toggle('active', !!isHeading);
      if (lBtn) lBtn.classList.toggle('active', !!isList);
      if (aBtn) aBtn.classList.toggle('active', !!isLink);
    }
  });
}

function applyInlineFormat(editor, formatType) {
  if (!editor) return;
  ensureEditorFocus(editor);

  const cmdMap = {
    bold: 'bold',
    italic: 'italic',
    underline: 'underline',
    strike: 'strikeThrough'
  };

  const cmd = cmdMap[formatType];
  if (!cmd) return;

  try {
    document.execCommand('styleWithCSS', false, false);
    document.execCommand(cmd, false, null);
  } catch (e) {}

  syncRichEditor(editor);
  updateToolbarStates(editor);
}

function applyVisualFormat(editor, formatType) {
  if (!editor) return;
  ensureEditorFocus(editor);

  const sel = window.getSelection();

  if (formatType === 'heading') {
    let headingNode = null;
    if (sel && sel.anchorNode && editor.contains(sel.anchorNode)) {
      const parent = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement;
      headingNode = parent ? parent.closest('h1, h2, h3, h4, h5, h6') : null;
    }

    if (headingNode) {
      // Toggle OFF: replace heading with <p>
      const p = document.createElement('p');
      while (headingNode.firstChild) {
        p.appendChild(headingNode.firstChild);
      }
      if (!p.firstChild) p.innerHTML = '<br>';
      headingNode.parentNode.replaceChild(p, headingNode);
      if (sel) {
        const range = document.createRange();
        range.selectNodeContents(p);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    } else {
      // Toggle ON: convert to <h3>
      const text = editor.innerText.trim();
      const isEmpty = !text && (!editor.firstChild || editor.innerHTML === '' || editor.innerHTML === '<br>' || editor.innerHTML === '<p><br></p>' || editor.innerHTML === '<div><br></div>');
      if (isEmpty) {
        editor.innerHTML = '<h3><br></h3>';
        const h3 = editor.querySelector('h3');
        if (h3 && sel) {
          const range = document.createRange();
          range.setStart(h3, 0);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        }
      } else {
        try {
          document.execCommand('formatBlock', false, '<h3>');
        } catch (e) {}

        const checkNode = sel && sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement) : null;
        if (!checkNode || !checkNode.closest('h1, h2, h3, h4, h5, h6')) {
          if (sel && sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
            const range = sel.getRangeAt(0);
            if (!sel.isCollapsed) {
              const h3 = document.createElement('h3');
              h3.appendChild(range.extractContents());
              range.insertNode(h3);
              const newRange = document.createRange();
              newRange.selectNodeContents(h3);
              sel.removeAllRanges();
              sel.addRange(newRange);
            } else {
              const block = checkNode ? (checkNode.closest('p, div') || checkNode) : null;
              if (block && block !== editor && block.parentNode === editor) {
                const h3 = document.createElement('h3');
                while (block.firstChild) h3.appendChild(block.firstChild);
                if (!h3.firstChild) h3.innerHTML = '<br>';
                editor.replaceChild(h3, block);
                const newRange = document.createRange();
                newRange.selectNodeContents(h3);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
              } else {
                const h3 = document.createElement('h3');
                h3.innerHTML = '<br>';
                editor.appendChild(h3);
                const newRange = document.createRange();
                newRange.setStart(h3, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
              }
            }
          }
        }
      }
    }
  } else if (formatType === 'bold' || formatType === 'italic' || formatType === 'underline' || formatType === 'strike') {
    applyInlineFormat(editor, formatType);
    return;
  } else if (formatType === 'bullet' || formatType === 'list') {
    let listNode = null;
    if (sel && sel.anchorNode && editor.contains(sel.anchorNode)) {
      const parent = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement;
      listNode = parent ? parent.closest('ul, ol') : null;
    }

    if (listNode) {
      // Toggle OFF: unwrap list items into <p>s
      const parent = listNode.parentNode;
      const frag = document.createDocumentFragment();
      Array.from(listNode.querySelectorAll('li')).forEach(li => {
        const p = document.createElement('p');
        while (li.firstChild) p.appendChild(li.firstChild);
        if (!p.firstChild) p.innerHTML = '<br>';
        frag.appendChild(p);
      });
      parent.replaceChild(frag, listNode);
      if (sel) {
        const lastP = editor.querySelector('p:last-of-type') || editor;
        const range = document.createRange();
        range.selectNodeContents(lastP);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    } else {
      // Toggle ON:
      const text = editor.innerText.trim();
      const isEmpty = !text && (!editor.firstChild || editor.innerHTML === '' || editor.innerHTML === '<br>' || editor.innerHTML === '<p><br></p>' || editor.innerHTML === '<div><br></div>');
      if (isEmpty) {
        editor.innerHTML = '<ul><li><br></li></ul>';
        const li = editor.querySelector('li');
        if (li && sel) {
          const range = document.createRange();
          range.setStart(li, 0);
          range.collapse(true);
          sel.removeAllRanges();
          sel.addRange(range);
        }
      } else {
        try {
          document.execCommand('insertUnorderedList', false, null);
        } catch (e) {}

        const checkNode = sel && sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement) : null;
        if (!checkNode || !checkNode.closest('ul, ol, li')) {
          if (sel && sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
            const range = sel.getRangeAt(0);
            const ul = document.createElement('ul');
            const li = document.createElement('li');
            if (!sel.isCollapsed) {
              li.appendChild(range.extractContents());
            } else {
              li.innerHTML = '<br>';
            }
            ul.appendChild(li);
            range.insertNode(ul);
            const newRange = document.createRange();
            newRange.setStart(li, 0);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
          }
        }
      }
    }
  } else if (formatType === 'link') {
    let linkNode = null;
    if (sel && sel.anchorNode && editor.contains(sel.anchorNode)) {
      const parent = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement;
      linkNode = parent ? parent.closest('a') : null;
    }

    if (linkNode) {
      // Toggle OFF: Unlink
      const parent = linkNode.parentNode;
      const range = document.createRange();
      range.selectNodeContents(linkNode);
      while (linkNode.firstChild) {
        parent.insertBefore(linkNode.firstChild, linkNode);
      }
      parent.removeChild(linkNode);
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(range);
      }
      syncRichEditor(editor);
      updateToolbarStates(editor);
      return;
    }

    // Toggle ON:
    let savedRange = null;
    let selectedText = '';
    if (sel && sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
      savedRange = sel.getRangeAt(0).cloneRange();
      if (!sel.isCollapsed) {
        selectedText = sel.toString().trim();
      }
    }

    const defaultUrl = 'https://';
    let url = window.prompt('Enter URL (https://...):', defaultUrl);
    if (!url || !url.trim() || url.trim() === 'https://') return;
    url = url.trim();
    if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) {
      url = 'https://' + url;
    }

    editor.focus();

    const linkText = selectedText || url;
    const a = document.createElement('a');
    a.href = url;
    a.textContent = linkText;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';

    if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
      savedRange.deleteContents();
      savedRange.insertNode(a);
      const space = document.createTextNode('\u00A0');
      if (a.nextSibling) {
        a.parentNode.insertBefore(space, a.nextSibling);
      } else {
        a.parentNode.appendChild(space);
      }
      const newRange = document.createRange();
      newRange.setStartAfter(space);
      newRange.collapse(true);
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(newRange);
      }
    } else {
      editor.appendChild(a);
      const space = document.createTextNode('\u00A0');
      editor.appendChild(space);
      if (sel) {
        const newRange = document.createRange();
        newRange.setStartAfter(space);
        newRange.collapse(true);
        sel.removeAllRanges();
        sel.addRange(newRange);
      }
    }
  }

  syncRichEditor(editor);
  updateToolbarStates(editor);
}

// Prevent format buttons from stealing selection focus on mousedown / pointerdown / touchstart
['mousedown', 'pointerdown', 'touchstart'].forEach(evtName => {
  document.addEventListener(evtName, (e) => {
    const btn = e.target.closest('.format-btn');
    if (btn) {
      e.preventDefault();
    }
  });
});

// Click listener for format buttons (use capture phase so ancestor onclick="event.stopPropagation()" doesn't block it)
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
        if (editor) {
          editor.focus();
          applyVisualFormat(editor, format);
        }
      }, 150);
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
    if (format === 'bullet' || format === 'list') {
      const prefix = (start > 0 && val[start - 1] !== '\n') ? '\n' : '';
      const replacement = prefix + '• ' + (selected || '');
      textarea.setRangeText(replacement, start, end, 'end');
    } else if (format === 'link') {
      let url = prompt('Enter URL (https://...):', 'https://');
      if (url && url.trim() && url.trim() !== 'https://') {
        url = url.trim();
        if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) {
          url = 'https://' + url;
        }
        const text = selected || prompt('Enter text to display:', url) || url;
        const replacement = '[' + text + '](' + url + ')';
        textarea.setRangeText(replacement, start, end, 'end');
      }
    } else {
      const wrap = { bold: ['**', '**'], italic: ['*', '*'], underline: ['<u>', '</u>'], strike: ['~~', '~~'], heading: ['## ', ''] }[format] || ['**', '**'];
      if (selected.length > 0) {
        textarea.value = val.substring(0, start) + wrap[0] + selected + wrap[1] + val.substring(end);
      } else {
        textarea.setRangeText(wrap[0] + 'text' + wrap[1], start, end, 'select');
      }
    }
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
}, true);

// Synchronize rich-editor elements on input, keyup, paste, and blur
document.addEventListener('input', (e) => {
  if (e.target && e.target.classList.contains('rich-editor')) {
    syncRichEditor(e.target);
    updateToolbarStates(e.target);
  }
});

document.addEventListener('keyup', (e) => {
  if (e.target && e.target.classList.contains('rich-editor')) {
    updateToolbarStates(e.target);
  }
});

document.addEventListener('mouseup', (e) => {
  if (e.target && e.target.classList.contains('rich-editor')) {
    updateToolbarStates(e.target);
  }
});

document.addEventListener('selectionchange', () => {
  const active = document.activeElement;
  if (active && active.classList.contains('rich-editor')) {
    updateToolbarStates(active);
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
    if (form.classList.contains('reply-composer-form')) {
      const editor = form.querySelector('.rich-editor');
      const fileInput = form.querySelector('input[name="reply_image"]');
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
      if (editor) {
        const text = (editor.innerText || '').replace(/\u200B/g, '').trim();
        const hasList = editor.querySelector('ul, ol, li');
        if (!text && !hasList && !hasFile) {
          e.preventDefault();
          editor.focus();
        }
      }
    }
  }
});

// Keyboard shortcuts for formatting (Ctrl/Cmd + B, I, U)
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && !e.altKey) {
    const key = (e.key || '').toLowerCase();
    let format = null;
    if (key === 'b') format = 'bold';
    else if (key === 'i') format = 'italic';
    else if (key === 'u') format = 'underline';

    if (format) {
      const active = document.activeElement;
      if (active && active.classList && active.classList.contains('rich-editor')) {
        e.preventDefault();
        applyVisualFormat(active, format);
      }
    }
  }
});

window.syncRichEditor = syncRichEditor;
window.applyVisualFormat = applyVisualFormat;
window.updateToolbarStates = updateToolbarStates;

/* ==========================================================================
   MODULE 12: GROUP MEMBERS & MODERATION MODAL
   Allows community administrators to view active members, remove, and ban users.
   ========================================================================== */
let currentMembersGroupId = 0;
let groupMembersCache = { members: [], banned: [] };

function openGroupMembersModal(groupId, groupName) {
  currentMembersGroupId = parseInt(groupId, 10) || 0;
  if (!currentMembersGroupId) return;

  const modal = document.getElementById('group-members-modal');
  if (!modal) return;

  const headerText = document.getElementById('group-members-header-text');
  if (headerText) {
    headerText.textContent = (groupName ? groupName + ' – ' : '') + 'Community Members';
  }

  const searchInput = document.getElementById('group-members-search');
  if (searchInput) searchInput.value = '';

  switchGroupMembersTab('active');
  modal.classList.add('active');

  loadGroupMembersData(currentMembersGroupId);
}

function closeGroupMembersModal() {
  const modal = document.getElementById('group-members-modal');
  if (modal) modal.classList.remove('active');
}

function switchGroupMembersTab(tab) {
  const activeTabBtn = document.getElementById('tab-active-members');
  const bannedTabBtn = document.getElementById('tab-banned-members');
  const activeList = document.getElementById('group-active-members-list');
  const bannedList = document.getElementById('group-banned-members-list');

  if (tab === 'banned') {
    if (activeTabBtn) activeTabBtn.classList.remove('active');
    if (bannedTabBtn) bannedTabBtn.classList.add('active');
    if (activeList) activeList.style.display = 'none';
    if (bannedList) bannedList.style.display = 'flex';
  } else {
    if (activeTabBtn) activeTabBtn.classList.add('active');
    if (bannedTabBtn) bannedTabBtn.classList.remove('active');
    if (activeList) activeList.style.display = 'flex';
    if (bannedList) bannedList.style.display = 'none';
  }
  filterGroupMembersList();
}

function loadGroupMembersData(groupId) {
  const loadingEl = document.getElementById('group-members-loading');
  const activeList = document.getElementById('group-active-members-list');
  const bannedList = document.getElementById('group-banned-members-list');
  const emptyEl = document.getElementById('group-members-empty');

  if (loadingEl) loadingEl.style.display = 'block';
  if (activeList) activeList.innerHTML = '';
  if (bannedList) bannedList.innerHTML = '';
  if (emptyEl) emptyEl.style.display = 'none';

  fetch(`index.php?action=get_group_members&group_id=${groupId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  })
    .then(res => res.json())
    .then(data => {
      if (loadingEl) loadingEl.style.display = 'none';
      if (!data.success) {
        if (emptyEl) {
          emptyEl.textContent = data.message || 'Could not load group members.';
          emptyEl.style.display = 'block';
        }
        return;
      }

      groupMembersCache.members = data.members || [];
      groupMembersCache.banned = data.banned || [];

      // Update badge counts
      const countActive = document.getElementById('count-active-members');
      if (countActive) countActive.textContent = groupMembersCache.members.length;
      const countBanned = document.getElementById('count-banned-members');
      if (countBanned) countBanned.textContent = groupMembersCache.banned.length;

      // Render Active Members
      renderActiveMembers(groupMembersCache.members, data.is_admin, groupId);

      // Render Banned Members
      renderBannedMembers(groupMembersCache.banned, data.is_admin, groupId);

      filterGroupMembersList();
    })
    .catch(() => {
      if (loadingEl) loadingEl.style.display = 'none';
      if (emptyEl) {
        emptyEl.textContent = 'Failed to load group members. Please try again.';
        emptyEl.style.display = 'block';
      }
    });
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderActiveMembers(members, isAdmin, groupId) {
  const container = document.getElementById('group-active-members-list');
  if (!container) return;
  container.innerHTML = '';

  if (!members || members.length === 0) {
    return;
  }

  members.forEach(m => {
    const item = document.createElement('div');
    item.className = 'group-member-item';
    item.setAttribute('data-name', (m.name || '').toLowerCase());
    item.setAttribute('data-username', (m.username || '').toLowerCase());

    const roleBadge = m.is_creator
      ? `<span class="gm-badge gm-badge-creator">Creator</span>`
      : m.role === 'admin'
      ? `<span class="gm-badge gm-badge-admin">Admin</span>`
      : `<span class="gm-badge gm-badge-student">User</span>`;

    let actionsHtml = '';
    if (isAdmin && m.can_moderate) {
      actionsHtml = `
        <div class="group-member-actions" style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
          <button type="button" class="btn-gm-remove" onclick="confirmRemoveMember(${groupId}, ${m.id}, '${escapeHtml(m.name)}')" title="Remove member from this group">
            Remove
          </button>
          <button type="button" class="btn-gm-ban" onclick="confirmBanMember(${groupId}, ${m.id}, '${escapeHtml(m.name)}')" title="Ban user from this group">
            Ban
          </button>
        </div>
      `;
    } else if (m.is_self) {
      actionsHtml = `<span style="font-size: 11.5px; color: var(--text-dim); padding: 4px 8px; border-radius: 9999px; background: rgba(255,255,255,0.05);">You</span>`;
    }

    item.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
        <a href="profile.php?id=${m.id}" style="flex-shrink: 0;">
          <img src="${escapeHtml(m.avatar)}" alt="${escapeHtml(m.name)}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color); display: block;" onerror="this.src='assets/images/default-avatar.svg'">
        </a>
        <div style="min-width: 0; flex: 1;">
          <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <a href="profile.php?id=${m.id}" style="font-weight: 700; font-size: 13.5px; color: var(--text-main); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              ${escapeHtml(m.name)}
            </a>
            ${roleBadge}
          </div>
          <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
            ${m.username ? '@' + escapeHtml(m.username) + ' · ' : ''}Joined ${escapeHtml(m.joined_formatted)}
          </div>
        </div>
      </div>
      ${actionsHtml}
    `;
    container.appendChild(item);
  });
}

function renderBannedMembers(banned, isAdmin, groupId) {
  const container = document.getElementById('group-banned-members-list');
  if (!container) return;
  container.innerHTML = '';

  if (!banned || banned.length === 0) {
    return;
  }

  banned.forEach(b => {
    const item = document.createElement('div');
    item.className = 'group-member-item';
    item.setAttribute('data-name', (b.name || '').toLowerCase());
    item.setAttribute('data-username', (b.username || '').toLowerCase());

    item.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
        <a href="profile.php?id=${b.id}" style="flex-shrink: 0;">
          <img src="${escapeHtml(b.avatar)}" alt="${escapeHtml(b.name)}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(239, 68, 68, 0.35); display: block;" onerror="this.src='assets/images/default-avatar.svg'">
        </a>
        <div style="min-width: 0; flex: 1;">
          <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <a href="profile.php?id=${b.id}" style="font-weight: 700; font-size: 13.5px; color: var(--text-main); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              ${escapeHtml(b.name)}
            </a>
            <span class="gm-badge gm-badge-banned">Banned</span>
          </div>
          <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
            Banned ${escapeHtml(b.banned_formatted)} by ${escapeHtml(b.banned_by)}
          </div>
        </div>
      </div>
      ${isAdmin ? `
        <div class="group-member-actions" style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
          <button type="button" class="btn-gm-unban" onclick="confirmUnbanMember(${groupId}, ${b.id}, '${escapeHtml(b.name)}')" title="Unban user from this group">
            Unban
          </button>
        </div>
      ` : ''}
    `;
    container.appendChild(item);
  });
}

function filterGroupMembersList() {
  const searchInput = document.getElementById('group-members-search');
  const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
  const activeTabBtn = document.getElementById('tab-active-members');
  const isBannedTab = !activeTabBtn || !activeTabBtn.classList.contains('active');
  const container = isBannedTab
    ? document.getElementById('group-banned-members-list')
    : document.getElementById('group-active-members-list');
  const emptyEl = document.getElementById('group-members-empty');
  const loadingEl = document.getElementById('group-members-loading');

  if (loadingEl && loadingEl.style.display !== 'none') {
    if (emptyEl) emptyEl.style.display = 'none';
    return;
  }

  if (!container) return;

  const items = container.querySelectorAll('.group-member-item');
  let visibleCount = 0;

  items.forEach(item => {
    const name = item.getAttribute('data-name') || '';
    const username = item.getAttribute('data-username') || '';
    if (!query || name.includes(query) || username.includes(query)) {
      item.style.display = 'flex';
      visibleCount++;
    } else {
      item.style.display = 'none';
    }
  });

  if (emptyEl) {
    if (visibleCount === 0) {
      emptyEl.textContent = query
        ? `No members found matching "${query}".`
        : (isBannedTab ? 'No banned users in this group.' : 'No members found in this group.');
      emptyEl.style.display = 'block';
    } else {
      emptyEl.style.display = 'none';
    }
  }
}

function confirmRemoveMember(groupId, userId, userName) {
  if (!confirm(`Are you sure you want to remove "${userName}" from this community?\n\nThey will be removed from the members list.`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'remove_group_member');
  formData.append('group_id', groupId);
  formData.append('user_id', userId);
  formData.append('ajax', '1');

  fetch('index.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast(data.message || 'Member removed successfully.');
        }
        const headerCount = document.getElementById('group-header-member-count');
        if (headerCount && data.member_count !== undefined) {
          headerCount.textContent = data.member_count;
        }
        loadGroupMembersData(groupId);
      } else {
        alert(data.message || 'Failed to remove member.');
      }
    })
    .catch(() => alert('Network error while removing member.'));
}

function confirmBanMember(groupId, userId, userName) {
  if (!confirm(`Are you sure you want to BAN "${userName}" from this community?\n\nThey will be removed immediately and blocked from rejoining or posting in this group.`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'ban_group_member');
  formData.append('group_id', groupId);
  formData.append('user_id', userId);
  formData.append('ajax', '1');

  fetch('index.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast(data.message || 'User has been banned from this group.');
        }
        const headerCount = document.getElementById('group-header-member-count');
        if (headerCount && data.member_count !== undefined) {
          headerCount.textContent = data.member_count;
        }
        loadGroupMembersData(groupId);
      } else {
        alert(data.message || 'Failed to ban member.');
      }
    })
    .catch(() => alert('Network error while banning member.'));
}

function confirmUnbanMember(groupId, userId, userName) {
  if (!confirm(`Unban "${userName}" from this community?\n\nThey will be able to rejoin the group.`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'unban_group_member');
  formData.append('group_id', groupId);
  formData.append('user_id', userId);
  formData.append('ajax', '1');

  fetch('index.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast(data.message || 'User unbanned successfully.');
        }
        loadGroupMembersData(groupId);
      } else {
        alert(data.message || 'Failed to unban user.');
      }
    })
    .catch(() => alert('Network error while unbanning user.'));
}

window.openGroupMembersModal = openGroupMembersModal;
window.closeGroupMembersModal = closeGroupMembersModal;
window.switchGroupMembersTab = switchGroupMembersTab;
window.filterGroupMembersList = filterGroupMembersList;
window.confirmRemoveMember = confirmRemoveMember;
window.confirmBanMember = confirmBanMember;
window.confirmUnbanMember = confirmUnbanMember;
