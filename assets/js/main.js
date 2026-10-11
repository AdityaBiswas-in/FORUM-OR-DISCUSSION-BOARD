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

  // Close custom topic dropdown
  if (!e.target.closest('#x-topic-custom-wrapper')) {
    const topicWrapper = document.getElementById('x-topic-custom-wrapper');
    if (topicWrapper) topicWrapper.classList.remove('open');
  }
});

// 3. Custom Topic Dropdown Handlers
function toggleCustomTopicDropdown(e) {
  e.stopPropagation();
  const wrapper = document.getElementById('x-topic-custom-wrapper');
  if (wrapper) wrapper.classList.toggle('open');
}

function selectCustomTopic(el, value, label) {
  const hiddenInput = document.getElementById('post-topic');
  const labelSpan = document.getElementById('x-topic-label');
  const wrapper = document.getElementById('x-topic-custom-wrapper');

  if (hiddenInput) hiddenInput.value = value;
  if (labelSpan) labelSpan.textContent = label;

  document.querySelectorAll('.x-custom-select-option').forEach(opt => opt.classList.remove('selected'));
  el.classList.add('selected');

  if (wrapper) wrapper.classList.remove('open');
}

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

function openEditPostModal(idOrBtn, title, topicId, content) {
  document.querySelectorAll('.three-dots-dropdown.show').forEach(el => el.classList.remove('show'));
  
  const modal = document.getElementById('edit-post-modal');
  if (!modal) return;

  let id = idOrBtn;
  if (typeof idOrBtn === 'object' && idOrBtn !== null) {
    const btn = idOrBtn;
    id = btn.getAttribute('data-id');
    title = btn.getAttribute('data-title') || '';
    topicId = btn.getAttribute('data-topic') || 1;
    content = btn.getAttribute('data-content') || '';
  }

  document.getElementById('edit-post-id').value = id || '';
  document.getElementById('edit-post-title').value = title || '';
  document.getElementById('edit-post-topic').value = topicId || 1;
  document.getElementById('edit-post-content').value = content || '';
  
  const editPreviewStrip = document.getElementById('edit-image-previews');
  if (editPreviewStrip) editPreviewStrip.innerHTML = '';

  modal.classList.add('active');
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

// Copy link directly pointing to discussion in index.php
function copyThreadLink(id) {
  const url = `${window.location.origin}${window.location.pathname.replace(/\/[^\/]*$/, '')}/index.php#post-${id}`;
  navigator.clipboard.writeText(url).then(() => {
    showToast('Discussion link copied to clipboard!');
  }).catch(() => {
    showToast('Link: ' + url);
  });
}

// 7. Inline Replies Accordion & Scroll Helpers
function toggleRepliesSection(postId) {
  const section = document.getElementById(`replies-section-${postId}`);
  if (!section) return;
  const isHidden = section.style.display === 'none' || section.style.display === '';
  section.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    const textarea = section.querySelector('textarea');
    if (textarea) textarea.focus();
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
    const isVisible = composer.style.display === 'block';
    composer.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) {
      const textarea = composer.querySelector('textarea');
      if (textarea) textarea.focus();
    }
  }
}

function toggleEditReplyForm(replyId) {
  const textEl = document.getElementById(`reply-text-${replyId}`);
  const formEl = document.getElementById(`reply-edit-form-${replyId}`);

  if (textEl && formEl) {
    if (formEl.style.display === 'none' || formEl.style.display === '') {
      formEl.style.display = 'block';
      textEl.style.display = 'none';
      const ta = formEl.querySelector('textarea');
      if (ta) ta.focus();
    } else {
      formEl.style.display = 'none';
      textEl.style.display = 'block';
    }
  }
}

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
  files.slice(0, 4).forEach((file) => {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const thumb = document.createElement('div');
      thumb.className = 'preview-thumb-container';
      thumb.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
      previewContainer.appendChild(thumb);
    };
    reader.readAsDataURL(file);
  });
}

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

function toggleSavePost(e, postId) {
  if (e) e.stopPropagation();
  const btn = document.getElementById(`save-btn-${postId}`);

  const formData = new FormData();
  formData.append('action', 'toggle_save');
  formData.append('post_id', postId);

  fetch('index.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (btn) {
        if (data.saved) {
          btn.classList.add('active-saved');
          btn.querySelector('svg').setAttribute('fill', 'currentColor');
          showToast('Saved to your bookmarks!');
        } else {
          btn.classList.remove('active-saved');
          btn.querySelector('svg').setAttribute('fill', 'none');
          showToast('Removed from saved.');
        }
      } else {
        showToast(data.saved ? 'Discussion saved!' : 'Discussion unsaved.');
      }
    }
  })
  .catch(err => {
    console.error('Save toggle error:', err);
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
