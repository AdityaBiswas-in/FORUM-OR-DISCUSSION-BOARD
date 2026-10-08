<?php
// includes/modals.php - Create Thread, Edit Post, and Image Lightbox Modals
$allTopics = get_all_topics();
if (!isset($currentUser)) {
    $currentUser = current_user();
}
?>

<!-- CREATE THREAD / POST MODAL (Matching X Reference Screenshot) -->
<div class="modal-overlay" id="create-thread-modal">
  <div class="modal-dialog x-compose-dialog">
    <!-- Top Bar: Close icon on left, Drafts on right -->
    <div class="x-modal-top-bar">
      <button type="button" class="x-compose-close-btn" onclick="closeCreateThreadModal()" title="Close">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
          <path d="M10.59 12L4.54 5.96l1.42-1.42L12 10.59l6.04-6.05 1.42 1.42L13.41 12l6.05 6.04-1.42 1.42L12 13.41l-6.04 6.05-1.42-1.42L10.59 12z"/>
        </svg>
      </button>
      <button type="button" class="x-compose-drafts-btn" onclick="showToast('No saved drafts')">Drafts</button>
    </div>

    <form action="api/create_post.php" method="POST" enctype="multipart/form-data" id="create-post-form">
      <div class="x-compose-body">
        <div class="x-compose-left">
          <img src="<?= e($currentUser['avatar']) ?>" alt="<?= e($currentUser['name']) ?>" class="x-compose-avatar">
        </div>
        <div class="x-compose-right">
          <!-- Title Input (Subtle & clean) -->
          <input type="text" name="title" id="post-title" class="x-compose-title-input" placeholder="Title of your discussion..." required>

          <!-- Custom Topic Dropdown (Styled matching X) -->
          <div class="x-custom-select-wrapper" id="x-topic-custom-wrapper">
            <input type="hidden" name="topic_id" id="post-topic" value="<?= (int)($allTopics[0]['id'] ?? 1) ?>" required>
            
            <button type="button" class="x-custom-select-btn" id="x-topic-trigger" onclick="toggleCustomTopicDropdown(event)">
              <span id="x-topic-label"># <?= e($allTopics[0]['name'] ?? 'General') ?></span>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                <path d="M3.543 8.96l1.414-1.42L12 14.59l7.043-7.05 1.414 1.42L12 17.41 3.543 8.96z"/>
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div class="x-custom-select-menu" id="x-topic-menu">
              <?php foreach ($allTopics as $index => $t): ?>
                <div class="x-custom-select-option <?= $index === 0 ? 'selected' : '' ?>" 
                     data-value="<?= (int)$t['id'] ?>" 
                     data-label="# <?= e($t['name']) ?>"
                     onclick="selectCustomTopic(this, <?= (int)$t['id'] ?>, '# <?= e($t['name']) ?>')">
                  <span class="x-option-tag"># <?= e($t['name']) ?></span>
                  <svg class="x-option-check" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M9 20.42l-6.21-6.21 1.42-1.42L9 17.58 20.79 5.79l1.42 1.42z"/>
                  </svg>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Main Textarea with "What's happening?" placeholder -->
          <textarea name="content" id="post-content" class="x-compose-textarea" placeholder="What’s happening?" required></textarea>

          <!-- Previews for selected photos -->
          <div class="image-preview-strip" id="create-image-previews"></div>
        </div>
      </div>

      <!-- Bottom Toolbar: Image upload only on left, Post pill button on right -->
      <div class="x-compose-bottom-toolbar">
        <div class="x-toolbar-icons">
          <!-- Image upload option only -->
          <button type="button" class="x-tool-btn" onclick="document.getElementById('post-images-input').click()" title="Add photos">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
              <path d="M3 5.5C3 4.119 4.119 3 5.5 3h13C19.881 3 21 4.119 21 5.5v13c0 1.381-1.119 2.5-2.5 2.5h-13C4.119 21 3 19.881 3 18.5v-13zM5.5 5c-.276 0-.5.224-.5.5v9.086l3.293-3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-1.293 1.293H18.5c.276 0 .5-.224.5-.5V5.5c0-.276-.224-.5-.5-.5h-13zM15 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
            </svg>
          </button>
          <input type="file" name="images[]" id="post-images-input" accept="image/*" multiple style="display: none;" onchange="handleImageSelection(this)">
        </div>

        <button type="submit" class="x-compose-submit-btn" id="btn-submit-post">Post</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT POST MODAL (Activated via 3-dots menu) -->
<div class="modal-overlay" id="edit-post-modal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Edit Discussion Thread</h3>
      <button class="btn-close-modal" onclick="closeEditPostModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <form action="api/edit_post.php" method="POST" enctype="multipart/form-data" id="edit-post-form">
      <input type="hidden" name="post_id" id="edit-post-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="edit-post-title">Thread Title</label>
          <input type="text" name="title" id="edit-post-title" class="form-input" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit-post-topic">Topic Category</label>
          <select name="topic_id" id="edit-post-topic" class="form-select" required>
            <?php foreach ($allTopics as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="edit-post-content">Discussion Content</label>
          <textarea name="content" id="edit-post-content" class="form-textarea" required></textarea>
        </div>

        <!-- Add more images if desired -->
        <div class="form-group">
          <label class="form-label">Add More Photos (Optional)</label>
          <div class="image-upload-zone" onclick="document.getElementById('edit-images-input').click()">
            <svg class="upload-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <circle cx="8.5" cy="8.5" r="1.5"></circle>
              <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <div class="upload-text">Click to add photos</div>
            <input type="file" name="images[]" id="edit-images-input" accept="image/*" multiple style="display: none;" onchange="handleEditImageSelection(this)">
          </div>
          <div class="image-preview-strip" id="edit-image-previews"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" onclick="closeEditPostModal()">Cancel</button>
        <button type="submit" class="btn-modal-submit" id="btn-save-edit-post">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- FULL IMAGE LIGHTBOX PREVIEW -->
<div class="lightbox-overlay" id="lightbox-overlay" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightbox(event)">✕</button>
  <img src="" alt="Enlarged view" id="lightbox-img" class="lightbox-img" onclick="event.stopPropagation()">
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>
