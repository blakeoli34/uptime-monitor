<?php
use \Core\CSRF;
?>
<div class="content">
    <div class="columns is-centered">
        <div class="column is-two-thirds">
            <div class="box">
                <h1 class="title is-4"><?= $isNew ? 'Create Status Page' : 'Edit Status Page' ?></h1>

                <form method="POST" action="<?= $isNew ? '/status-pages/add' : "/status-pages/{$page['id']}/edit" ?>">
                    <?= \Core\CSRF::getFormField() ?>
                    <!-- Name Field -->
                    <div class="field">
                        <label class="label">Name</label>
                        <div class="control">
                            <input class="input" type="text" name="name" 
                                value="<?= html_entity_decode(htmlspecialchars($page['name'] ?? '')) ?>"
                                placeholder="e.g., Company Services Status" required>
                        </div>
                        <p class="help">A name for your status page</p>
                    </div>

                    <!-- Slug Field -->
                    <div class="field">
                        <label class="label">Slug</label>
                        <div class="control">
                            <input class="input" type="text" name="slug" id="slugInput"
                                value="<?= htmlspecialchars($page['slug'] ?? '') ?>"
                                pattern="[a-z0-9-]+" 
                                placeholder="e.g., company-status" required>
                        </div>
                        <p class="help">URL-friendly name (lowercase letters, numbers, and hyphens only)</p>
                    </div>

                    <!-- Description Field -->
                    <div class="field">
                        <label class="label">Description (Optional)</label>
                        <div class="control">
                            <textarea class="textarea" name="description" rows="3"
                                placeholder="Describe what this status page monitors"><?= html_entity_decode(htmlspecialchars($page['description'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <!-- Public/Private Toggle -->
                    <div class="field">
                        <div class="control">
                            <label class="checkbox">
                                <input type="checkbox" name="is_public" 
                                    <?= (isset($page['is_public']) && $page['is_public']) ? 'checked' : '' ?>>
                                Make this status page public
                            </label>
                        </div>
                        <p class="help">Public status pages can be viewed without authentication</p>
                    </div>

                    <!-- Monitor Selection -->
                    <div class="field">
                        <label class="label">Monitors</label>
                        <div class="control">
                            <?php if (empty($monitors)): ?>
                                <p class="has-text-grey">No monitors available. <a href="/monitors/add">Create a monitor</a> first.</p>
                            <?php else: ?>
                                <?php foreach ($monitors as $monitor): ?>
                                    <label class="checkbox block mb-2">
                                        <input type="checkbox" name="monitor_ids[]" 
                                            value="<?= $monitor['id'] ?>"
                                            <?= in_array($monitor['id'], $selectedMonitors ?? []) ? 'checked' : '' ?>>
                                        <?= html_entity_decode(htmlspecialchars($monitor['name'])) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="help">Select the monitors to display on this status page</p>
                    </div>

                    <!-- Custom Domain -->
                    <div class="field">
                        <label class="label">Custom Domain (Optional)</label>
                        <div class="control">
                            <input class="input" type="text" name="custom_domain" 
                                value="<?= htmlspecialchars($page['custom_domain'] ?? '') ?>"
                                placeholder="example.com">
                        </div>
                        <p class="help">Point a CNAME record from status.yourdomain.com to <?= $config['app']['url'] ?></p>
                    </div>

                    <!-- Preview Section -->
                    <div class="field">
                        <label class="label">Status Page URL</label>
                        <div class="control">
                            <div class="box has-background-light">
                                <p class="is-family-monospace">
                                    <?= $config['app']['url'] ?>/status/<span id="slugPreview">
                                        <?= htmlspecialchars($page['slug'] ?? 'your-page-slug') ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="field is-grouped mt-5">
                        <div class="control">
                            <button type="submit" class="button is-primary">
                                <?= $isNew ? 'Create Status Page' : 'Update Status Page' ?>
                            </button>
                        </div>
                        <div class="control">
                            <a href="/status-pages" class="button is-light">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slugInput = document.getElementById('slugInput');
    const slugPreview = document.getElementById('slugPreview');
    const nameInput = document.querySelector('input[name="name"]');

    // Function to generate slug from text
    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Replace spaces with -
            .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
            .replace(/\-\-+/g, '-')         // Replace multiple - with single -
            .replace(/^-+/, '')             // Trim - from start of text
            .replace(/-+$/, '');            // Trim - from end of text
    }

    // Update slug preview when slug input changes
    slugInput.addEventListener('input', function() {
        slugPreview.textContent = this.value;
    });

    // Auto-generate slug from name if slug is empty
    nameInput.addEventListener('input', function() {
        if (!slugInput.value) {
            const slug = slugify(this.value);
            slugInput.value = slug;
            slugPreview.textContent = slug;
        }
    });

    // Ensure slug follows the rules
    slugInput.addEventListener('input', function() {
        this.value = slugify(this.value);
    });
});
</script>