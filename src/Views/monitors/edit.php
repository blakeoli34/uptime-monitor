<?php
use \Core\CSRF;
?>
<div class="content">
    <div class="columns is-centered">
        <div class="column is-two-thirds">
            <div class="box">
                <h1 class="title is-4"><?= $isNew ? 'Add Monitor' : 'Edit Monitor' ?></h1>

                <form method="POST" action="<?= $isNew ? '/monitors/add' : "/monitors/{$monitor['id']}/edit" ?>">
                    <?= \Core\CSRF::getFormField() ?>
                    <!-- Name Field -->
                    <div class="field">
                        <label class="label">Name</label>
                        <div class="control">
                            <input class="input" type="text" name="name" 
                                value="<?= html_entity_decode(htmlspecialchars($monitor['name'] ?? '')) ?>"
                                placeholder="My Website" required>
                        </div>
                        <p class="help">A friendly name to identify this monitor</p>
                    </div>

                    <!-- Monitor Type -->
                    <div class="field">
                        <label class="label">Type</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="type" id="monitorType">
                                    <option value="http" <?= ($monitor['type'] ?? 'http') === 'http' ? 'selected' : '' ?>>HTTP(S)</option>
                                    <option value="tcp" <?= ($monitor['type'] ?? '') === 'tcp' ? 'selected' : '' ?>>TCP Port</option>
                                    <option value="ssl" <?= ($monitor['type'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL Certificate</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- URL Field -->
                    <div class="field">
                        <label class="label">URL/Hostname</label>
                        <div class="control">
                            <input class="input" type="text" name="url" id="urlInput"
                                value="<?= htmlspecialchars($monitor['url'] ?? '') ?>"
                                placeholder="https://example.com" required>
                        </div>
                        <p class="help" id="urlHelp">The full URL to monitor (including https:// for websites)</p>
                    </div>

                    <!-- Port Field (for TCP monitors) -->
                    <div class="field" id="portField" style="display: none;">
                        <label class="label">Port</label>
                        <div class="control">
                            <input class="input" type="number" name="port" 
                                value="<?= htmlspecialchars($monitor['port'] ?? '80') ?>"
                                min="1" max="65535">
                        </div>
                        <p class="help">The port number to monitor</p>
                    </div>

                    <!-- Check Interval -->
                    <div class="field">
                        <label class="label">Check Interval</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="interval_seconds">
                                    <option value="60" <?= ($monitor['interval_seconds'] ?? 300) == 60 ? 'selected' : '' ?>>Every minute</option>
                                    <option value="300" <?= ($monitor['interval_seconds'] ?? 300) == 300 ? 'selected' : '' ?>>Every 5 minutes</option>
                                </select>
                            </div>
                        </div>
                        <p class="help">How often to check the monitor</p>
                    </div>

                    <!-- Webhook URL -->
                    <div class="field">
                        <label class="label">Webhook URL (Optional)</label>
                        <div class="control">
                            <input class="input" type="url" name="webhook_url"
                                value="<?= htmlspecialchars($monitor['webhook_url'] ?? '') ?>"
                                placeholder="https://api.example.com/webhook">
                        </div>
                        <p class="help">URL to receive notifications when the monitor status changes</p>
                    </div>

                    <!-- Validation Options (for HTTP monitors) -->
                    <div id="httpOptions" style="display: none;">
                        <div class="field">
                            <label class="label">Expected Status Code (Optional)</label>
                            <div class="control">
                                <input class="input" type="number" name="expected_status"
                                    value="<?= htmlspecialchars($monitor['expected_status'] ?? '') ?>"
                                    placeholder="200" min="100" max="599">
                            </div>
                            <p class="help">Leave empty to accept any 2xx status code</p>
                        </div>

                        <div class="field">
                            <label class="label">Search String (Optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="search_string"
                                    value="<?= htmlspecialchars($monitor['search_string'] ?? '') ?>"
                                    placeholder="Text that should appear in the response">
                            </div>
                            <p class="help">Monitor will fail if this text is not found in the response</p>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="field is-grouped mt-5">
                        <div class="control">
                            <button type="submit" class="button is-primary">
                                <?= $isNew ? 'Create Monitor' : 'Update Monitor' ?>
                            </button>
                        </div>
                        <div class="control">
                            <a href="/monitors" class="button is-light">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monitorType = document.getElementById('monitorType');
    const portField = document.getElementById('portField');
    const httpOptions = document.getElementById('httpOptions');
    const urlInput = document.getElementById('urlInput');
    const urlHelp = document.getElementById('urlHelp');

    function updateFields() {
        const type = monitorType.value;
        
        // Show/hide port field
        portField.style.display = type === 'tcp' ? 'block' : 'none';
        
        // Show/hide HTTP specific options
        httpOptions.style.display = type === 'http' ? 'block' : 'none';
        
        // Update URL placeholder and help text
        switch(type) {
            case 'http':
                urlInput.placeholder = 'https://example.com';
                urlHelp.textContent = 'The full URL to monitor (including https:// for websites)';
                break;
            case 'tcp':
                urlInput.placeholder = 'example.com';
                urlHelp.textContent = 'The hostname or IP address to monitor';
                break;
            case 'ssl':
                urlInput.placeholder = 'example.com';
                urlHelp.textContent = 'The hostname to check SSL certificate for';
                break;
        }
    }

    // Update fields on type change
    monitorType.addEventListener('change', updateFields);
    
    // Initial update
    updateFields();
});
</script>