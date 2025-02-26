<div class="content">
    <div class="level">
        <div class="level-left">
            <div class="level-item">
                <h1 class="title">Status Pages</h1>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <a href="/status-pages/add" class="button">
                    <span class="icon">
                        <i class="fas fa-plus"></i>
                    </span>
                    <span>Add Status Page</span>
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($pages)): ?>
        <div class="box has-text-centered">
            <p class="has-text-grey">No status pages found. Create your first status page to share your monitors' status!</p>
            <a href="/status-pages/add" class="button is-primary is-outlined mt-4">Create Status Page</a>
        </div>
    <?php else: ?>
        <div class="columns is-multiline">
            <?php foreach ($pages as $page): ?>
                <div class="column is-one-third">
                    <div class="box">
                        <!-- Header -->
                        <div class="level is-mobile mb-2">
                            <div class="level-left">
                                <div class="level-item">
                                    <span class="icon-text">
                                        <span class="icon">
                                            <i class="fas fa-signal"></i>
                                        </span>
                                        <span class="has-text-weight-bold"><?= html_entity_decode(htmlspecialchars($page['name'])) ?></span>
                                    </span>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <div class="dropdown is-right is-hoverable">
                                        <div class="dropdown-trigger">
                                            <button class="button is-small is-ghost" aria-label="more options">
                                                <span class="icon">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </span>
                                            </button>
                                        </div>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-content">
                                                <a href="/status/<?= $page['slug'] ?>" target="_blank" class="dropdown-item">
                                                    <span class="icon-text">
                                                        <span class="icon"><i class="fas fa-external-link-alt"></i></span>
                                                        <span>View Page</span>
                                                    </span>
                                                </a>
                                                <a href="/status-pages/<?= $page['id'] ?>/edit" class="dropdown-item">
                                                    <span class="icon-text">
                                                        <span class="icon"><i class="fas fa-edit"></i></span>
                                                        <span>Edit</span>
                                                    </span>
                                                </a>
                                                <hr class="dropdown-divider">
                                                <a href="#" class="dropdown-item has-text-danger" 
                                                   onclick="confirmDelete(<?= $page['id'] ?>, '<?= htmlspecialchars($page['name']) ?>')">
                                                    <span class="icon-text">
                                                        <span class="icon"><i class="fas fa-trash"></i></span>
                                                        <span>Delete</span>
                                                    </span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- URL -->
                        <p class="has-text-grey is-size-7 mb-3">
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="fas fa-link"></i>
                                </span>
                                <span><?= $config['app']['url'] ?>/status/<?= htmlspecialchars($page['slug']) ?></span>
                            </span>
                        </p>

                        <!-- Description -->
                        <?php if (!empty($page['description'])): ?>
                            <p class="has-text-grey mb-3"><?= html_entity_decode(htmlspecialchars($page['description'])) ?></p>
                        <?php endif; ?>

                        <!-- Tags/Status -->
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control">
                                <div class="tags has-addons">
                                    <span class="tag">Status</span>
                                    <span class="tag <?= $page['is_public'] ? 'is-success' : 'is-warning' ?>">
                                        <?= $page['is_public'] ? 'Public' : 'Private' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="control">
                                <div class="tags has-addons">
                                    <span class="tag">Monitors</span>
                                    <span class="tag is-dark">
                                        <?= $page['monitor_count'] ?? '0' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Confirm Delete</p>
            <button class="delete" aria-label="close" onclick="closeDeleteModal()"></button>
        </header>
        <section class="modal-card-body">
            Are you sure you want to delete the status page "<span id="deletePageName"></span>"? This action cannot be undone.
        </section>
        <footer class="modal-card-foot">
            <form id="deleteForm" method="POST">
                <button class="button is-danger">Delete</button>
            </form>
            <button class="button" onclick="closeDeleteModal()">Cancel</button>
        </footer>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const pageNameSpan = document.getElementById('deletePageName');
    
    form.action = `/status-pages/${id}/delete`;
    pageNameSpan.textContent = name;
    modal.classList.add('is-active');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('is-active');
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteModal');
    if (e.target.classList.contains('modal-background')) {
        modal.classList.remove('is-active');
    }
});
</script>