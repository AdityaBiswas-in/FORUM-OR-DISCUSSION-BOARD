// assets/js/main.js - Unified Client Interaction for CampusForum

// 1. Theme Switcher Logic
function getSavedTheme() {
  return localStorage.getItem('campus_theme') || 'dark';
}

function updateThemeUI(theme) {
  const sunIcon = document.querySelector('.theme-icon-sun');
  const moonIcon = document.querySelector('.theme-icon-moon');
  const labelText = document.getElementById('theme-label-text');

  if (theme === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
    if (sunIcon) sunIcon.style.display = 'block';
    if (moonIcon) moonIcon.style.display = 'none';
    if (labelText) labelText.textContent = 'Theme: Light';
  } else {
    document.documentElement.setAttribute('data-theme', 'dark');
    if (sunIcon) sunIcon.style.display = 'none';
    if (moonIcon) moonIcon.style.display = 'block';
    if (labelText) labelText.textContent = 'Theme: Dark';
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
function openCreateThreadModal() {
  const modal = document.getElementById('create-thread-modal');
  if (modal) {
    modal.classList.add('active');
    setTimeout(() => {
      const input = document.getElementById('post-title');
      if (input) input.focus();
    }, 100);
  }
}

function closeCreateThreadModal() {
  const modal = document.getElementById('create-thread-modal');
  if (modal) modal.classList.remove('active');
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
